<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OCCValidator
{
    /**
     * Validate OCC and reserve the time slot (available → pending).
     *
     * Full-block extension (SDD full-block-reservation, design D3): $span
     * reserves the WHOLE run TWO-PHASE (round-1 review mandate — no flip ever
     * precedes a conflict exit):
     *   1. request validation (unchanged, anchor identity);
     *   2. lock ALL span rows ordered (`ORDER BY id` — no deadlock pairing) +
     *      the row-gone defensive guard;
     *   3. check ALL statuses — any failure writes the SINGLE conflict audit
     *      row (round-3 🟡: first failing slot's id/times in details) and
     *      normal-returns with ZERO flips (nothing was written);
     *   4. only then flip ALL (version-checked per slot);
     *   5. junction rows LAST (shrunken insert-failure window) + the per-slot
     *      SUCCESS audit rows (Q4) inside the transaction.
     *
     * Peri-flip failures (a flip's affected === 0, the junction insert, an
     * audit write) THROW a RuntimeException subtype out of the transaction →
     * full rollback — and the single conflict audit row is re-written AFTER
     * the rollback, in THIS method's catch, decorated with the
     * `concurrent_reserve_conflict` forensic token + the failing slot's facts
     * (best-effort — the invariant is audit-first-then-throw; the action's
     * catch maps the surfaced throw to `reserve_interrupted` + D5). The
     * default (span = 1) is the legacy single-slot path, byte-identical.
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
     */
    public function validateAndReserve(int $timeSlotId, int $userId, int $replacementRequestId, int $span = 1): OCCResult
    {
        try {
            return DB::transaction(function () use ($timeSlotId, $userId, $replacementRequestId, $span): OCCResult {
                // Step 1 — Request validation (anchor identity unchanged).
                $request = ReplacementRequest::query()->findOrFail($replacementRequestId);

                if ($request->status !== 'pending' || (int) $request->replacement_time_slot_id !== $timeSlotId) {
                    return $this->auditConflict($userId, $request, null, 'request_not_pending_or_slot_mismatch');
                }

                // Step 2 — Lock ALL span rows, ordered (design D3: no deadlock pairing).
                $slotIds = $this->plannedSlotIds($timeSlotId, $span);

                $locked = TimeSlot::query()->whereIn('id', $slotIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                // Defensive row-gone guard (design step-8 paranoia; near-moot
                // because time-slot deletion cascades the request rows away).
                foreach ($slotIds as $slotId) {
                    if (! $locked->has($slotId)) {
                        throw new RuntimeException("occ_two_phase_row_gone:id={$slotId}");
                    }
                }

                // Pre-flip version snapshot (per-slot audit metadata as we flip).
                $expectedVersions = [];
                foreach ($locked as $orderedSlot) {
                    $expectedVersions[$orderedSlot->id] = (int) $orderedSlot->version;
                }

                // Step 3 — Check ALL statuses FIRST (no flip has happened yet).
                // The race token binds ADAPTIVELY (round-4 🔴): span 1 →
                // `slot_not_available` (legacy byte-shape); span > 1 →
                // `span_overlap` (minted HERE only).
                $reason = $span > 1 ? 'span_overlap' : 'slot_not_available';

                $failing = null;
                foreach ($locked as $orderedSlot) {
                    if ($orderedSlot->status !== 'available') {
                        $failing = $orderedSlot;

                        break;
                    }
                }

                if ($failing !== null) {
                    // auditConflict returns the OCCResult → normal return → the
                    // transaction COMMITS with ZERO flips (nothing was written).
                    // timeSlot stays null (byte-identical legacy shape; the
                    // failing facts ride the new failing_start/end keys).
                    $this->auditConflict(
                        $userId,
                        $request,
                        $failing,
                        $reason,
                        (string) $failing->start_time,
                        (string) $failing->end_time
                    );

                    return OCCResult::conflict($reason, null, (string) $failing->start_time, (string) $failing->end_time);
                }

                // Step 4 — Flip ALL (version-checked). Any failure throws (D3).
                foreach ($locked as $orderedSlot) {
                    $expectedVersion = $expectedVersions[$orderedSlot->id];
                    $affected = DB::update(
                        'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                        ['pending', $expectedVersion + 1, $orderedSlot->id, $expectedVersion]
                    );

                    if ($affected === 0) {
                        throw new RuntimeException(
                            "occ_two_phase_flip_conflict:id={$orderedSlot->id};failing_slot_h:i:s={$orderedSlot->start_time}–{$orderedSlot->end_time}"
                        );
                    }

                    // Keep the in-memory model consistent with the DB write.
                    $orderedSlot->status = 'pending';
                    $orderedSlot->version = $expectedVersion + 1;
                }

                // Step 5 — Junction rows LAST (the insert-failure window shrinks).
                $now = now();
                foreach ($slotIds as $slotId) {
                    DB::table('replacement_request_slots')->insert([
                        'replacement_request_id' => $replacementRequestId,
                        'time_slot_id' => $slotId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Step 5b — per-slot SUCCESS audit rows (Q4), inside the txn —
                // so any failure below rolls the junction back too.
                foreach ($locked as $orderedSlot) {
                    AuditLog::create([
                        'user_id' => $userId,
                        'action' => 'submitted',
                        'replacement_request_id' => $replacementRequestId,
                        'time_slot_id' => $orderedSlot->id,
                        'old_status' => 'available',
                        'new_status' => 'pending',
                        'occ_validation_result' => 'success',
                        'details' => ['version' => $expectedVersions[$orderedSlot->id]],
                    ]);
                }

                return OCCResult::success($locked->get($timeSlotId) ?? $locked->first());
            });
        } catch (RuntimeException $exception) {
            // AFTER the rollback:
            // — input-garbage (ModelNotFoundException from findOrFail on a
            //   fabricated id) passes THROUGH untouched: byte-identical legacy
            //   surface (no wrap, no audit row — the action never sees it via
            //   the RuntimeException catch either; it surfaces as-is).
            if ($exception instanceof ModelNotFoundException) {
                throw $exception;
            }

            // — peri-flip family (flip/junction/audit/row-gone — messages mint
            //   `occ_two_phase_*`) rethrow with their own decorations + the
            //   post-rollback forensic audit row.
            // — a RAW PDO loser (lock-wait abort — the test's hold connection,
            //   or any transient lock conflict) gets the.decorate wrapper +
            //   post-rollback audit + rethrow.
            $message = (string) $exception->getMessage();
            if (! str_starts_with($message, 'occ_two_phase_')) {
                $decorated = new RuntimeException(
                    "occ_two_phase_lock_timeout:id={$timeSlotId}",
                    0,
                    $exception
                );
                $exception = $decorated;
            }

            $this->postRollbackAudit($exception, $userId, $replacementRequestId);
            throw $exception;
        }
    }

    /**
     * The two-phase peri-flip catch (design D3): AFTER the rollback, best-
     * effort write the single conflict audit row (the audit table is outside
     * the rolled-back txn; `replacement_request_id` is pre-nulled here so the
     * action's later `compensateLoser` UPDATE no-ops and the row survives —
     * FR 4.12's "the conflict row survives" contract). Decorates the row with
     * the `concurrent_reserve_conflict` forensic token (round-5 fold: all
     * throw-causes share the token — `details.conflict_reason` is permanent
     * history) + the failing slot's facts parsed from the exception message.
     * Re-throws the SAME exception instance — the action's `catch
     * (RuntimeException)` maps it to `reserve_interrupted` + D5, never a 500.
     */
    private function postRollbackAudit(RuntimeException $original, int $userId, int $replacementRequestId): void
    {
        $message = (string) $original->getMessage();

        if (preg_match('/id=(\d+)/', $message, $m) === 1) {
            $failingSlotId = (int) $m[1];
        } else {
            $failingSlotId = null;
        }

        $failingStart = null;
        $failingEnd = null;
        if (preg_match('/failing_slot_h:i:s=([\d:]+)(?:–([\d:]+))?/', $message, $m) === 1) {
            $failingStart = $m[1];
            $failingEnd = ($m[2] ?? '') !== '' ? $m[2] : null;
        }

        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'submitted',
                'replacement_request_id' => null, // pre-nulled — D5's UPDATE no-ops, the row survives untouched
                'time_slot_id' => $failingSlotId,
                'old_status' => null,
                'new_status' => null,
                'occ_validation_result' => 'conflict',
                'details' => ['conflict_reason' => 'concurrent_reserve_conflict', 'failing_start' => $failingStart, 'failing_end' => $failingEnd],
            ]);
        } catch (\Throwable) {
            // best-effort: if the audit insert itself fails, the original
            // exception still surfaces (the invariant is audit-first-then-
            // throw, not exception-in-exception).
        }
    }

    /**
     * The planned (anchor..anchor+span−1) cell ids: contiguous 30-min steps
     * from the anchor's own grid row. Missing cell (a grid hole) →
     * RuntimeException with the derived expected time (the action's raceFacts
     * surfaces it through the flat return keys).
     *
     * @return list<int>
     */
    private function plannedSlotIds(int $anchorId, int $span): array
    {
        $anchor = TimeSlot::query()->findOrFail($anchorId);

        $ids = [(int) $anchorId];
        $planned = Carbon::parse((string) $anchor->start_time);

        while (count($ids) < $span) {
            $planned = $planned->copy()->addMinutes(30);
            $plannedEnd = $planned->copy()->addMinutes(30);

            $next = TimeSlot::query()
                ->where('semester_id', $anchor->semester_id)
                ->where('week_number', $anchor->week_number)
                ->where('venue_id', $anchor->venue_id)
                ->where('day_of_week', $anchor->day_of_week)
                ->where('start_time', $planned->format('H:i:s'))
                ->first();

            if ($next === null) {
                throw new RuntimeException(
                    "occ_two_phase_span_gap:id={$anchorId};failing_slot_h:i:s={$planned->format('H:i:s')}–{$plannedEnd->format('H:i:s')}"
                );
            }

            $ids[] = (int) $next->id;
        }

        return $ids;
    }

    private function auditConflict(int $userId, ReplacementRequest $request, ?TimeSlot $slot, string $reason, ?string $failingStart = null, ?string $failingEnd = null): OCCResult
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => 'submitted',
            'replacement_request_id' => $request->id,
            'time_slot_id' => $slot?->id,
            'old_status' => null,
            'new_status' => null,
            'occ_validation_result' => 'conflict',
            'details' => ['conflict_reason' => $reason, 'failing_start' => $failingStart, 'failing_end' => $failingEnd],
        ]);

        return OCCResult::conflict($reason, $slot, $failingStart, $failingEnd);
    }
}
