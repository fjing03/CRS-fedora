<?php

namespace App\Actions;

use App\Mail\ReplacementApprovalNotice;
use App\Mail\ReplacementDecided;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ApprovalValidity;
use App\Services\ReplacementRequestMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * PL decision — APPROVE a pending replacement request (SDD
 * span-aware-approval-flow, design D1 + spec decision-flow R1; grill Q13: no
 * ownership guard — a PL may decide any pending request).
 *
 * The 10-step two-phase txn (verbatim design D1): the request-row lock → the
 * ordered junction lock (+ the count>=1 guard) → check ALL → flip ALL
 * version-checked (throw-out-of-txn on any mismatch — NOTHING stays flipped)
 * → the junction rows STAY (the LIVE receipt) → the approve stamp → ONE
 * summary audit (`action='approved'`, LEGAL token; `approver_note` rides the
 * details ONLY when non-blank — D13) → one per-slot flip audit → the
 * original-release (D3, inside the txn) → mails queued AFTER commit (never
 * inside the OCC window).
 *
 * Losers return `['ok' => false, 'conflict_reason' => …]` with the EXISTING
 * family strings ('already decided' / the `slot_not_available` named-time
 * copy) — no new token names (Q5).
 */
final class ApproveRequest
{
    /**
     * @return array{ok: bool, conflict_reason: string|null}
     */
    public function __invoke(User $decider, int $requestId, ?string $approverNote = null): array
    {
        $request = ReplacementRequest::query()->find($requestId);

        // A stale page's id either moved on or is gone — case (a).
        if ($request === null) {
            return $this->loser(ApprovalValidity::ALREADY_DECIDED);
        }

        $slotIds = ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)
            ->orderBy('time_slot_id')
            ->pluck('time_slot_id');

        if ($slotIds->isEmpty()) {
            // Legacy anchor-only rows flip the anchor (the OCC legacy shape).
            $slotIds = collect([(int) $request->replacement_time_slot_id]);
        }

        $anchorSlot = TimeSlot::find((int) $request->replacement_time_slot_id);

        /** Setup-time case (b): a locked-and-drifted span before the txn. */
        $validity = app(ApprovalValidity::class)->evaluate($request);
        if (! $validity->valid) {
            return $this->loser($validity->reason);
        }

        $this->assertSpanIsContiguousAndReal($slotIds, $anchorSlot);

        try {
            DB::transaction(function () use ($request, $decider, $approverNote, $slotIds): void {
                // Step 1 — request-row lock (held for the WHOLE txn).
                $locked = ReplacementRequest::query()->whereKey($request->id)->lockForUpdate()->first();

                if ($locked === null || (string) $locked->status !== 'pending') {
                    throw new StaleDecisionException(ApprovalValidity::ALREADY_DECIDED);
                }

                // Step 2 — ordered lock over the whole span.
                $slots = TimeSlot::query()
                    ->whereIn('id', $slotIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($slots->count() !== $slotIds->count()) {
                    $missingId = (int) $slotIds->first(fn ($id) => $slots->get($id) === null);
                    $gone = TimeSlot::find($missingId);
                    $fallback = $slots->get((int) $slotIds->first());

                    throw new SpanDriftException(
                        ApprovalValidity::notAvailableMessage(
                            (string) ($gone !== null ? $gone->start_time : ($fallback !== null ? $fallback->start_time : '')),
                            (string) ($gone !== null ? $gone->end_time : ($fallback !== null ? $fallback->end_time : '')),
                        ),
                    );
                }

                // Step 3 — check ALL.
                foreach ($slotIds as $slotId) {
                    $slot = $slots->get($slotId);

                    if ($slot === null) {
                        throw new SpanDriftException(ApprovalValidity::ALREADY_DECIDED);
                    }

                    if ((string) $slot->status !== 'pending') {
                        throw new SpanDriftException(
                            ApprovalValidity::notAvailableMessage(
                                (string) $slot->start_time,
                                (string) $slot->end_time,
                            ),
                        );
                    }
                }

                // Step 4 — flip ALL (version-checked).
                foreach ($slotIds as $slotId) {
                    $slot = $slots->get($slotId);
                    $expected = (int) $slot->version;
                    $affected = DB::update(
                        'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                        ['occupied', $expected + 1, $slot->id, $expected],
                    );

                    if ($affected === 0) {
                        throw new SpanDriftException(
                            ApprovalValidity::notAvailableMessage(
                                (string) $slot->start_time,
                                (string) $slot->end_time,
                            ),
                        );
                    }
                }

                // Step 5 — the junction rows STAY (the LIVE receipt).

                // Step 6 — the approve stamp.
                $locked->fill([
                    'status' => 'approved',
                    'approver_id' => $decider->id,
                    'decided_at' => now(),
                ]);
                $locked->save();

                // Step 7 — one per-slot flip audit each.
                foreach ($slotIds as $slotId) {
                    AuditLog::create([
                        'user_id' => $decider->id,
                        'action' => 'approved',
                        'replacement_request_id' => $locked->id,
                        'time_slot_id' => (int) $slotId,
                        'old_status' => 'pending',
                        'new_status' => 'occupied',
                        'details' => [
                            'decision' => 'approved',
                        ],
                    ]);
                }

                // The summary details live UP FRONT (the release rider below
                // mutates them); the summary audit row writes after.
                $details = [
                    'decision' => 'approved',
                    'slots' => $slotIds->values()->all(),
                ];
                if ($approverNote !== null && trim($approverNote) !== '') {
                    $details['approver_note'] = trim((string) $approverNote);
                }

                // Release (design D3) inside the same txn BEFORE the summary
                // audit so its rider lands in the row.
                $released = $this->releaseOriginal($locked, $decider, $details);

                // Step 7 — the summary audit (action='approved', LEGAL token).
                AuditLog::create([
                    'user_id' => $decider->id,
                    'action' => 'approved',
                    'replacement_request_id' => $locked->id,
                    'time_slot_id' => (int) $locked->replacement_time_slot_id,
                    'old_status' => 'pending',
                    'new_status' => 'occupied',
                    'occ_validation_result' => 'success',
                    'details' => $details,
                ]);

                // Step 10 — mails AFTER COMMIT (never inside the OCC window).
                DB::afterCommit(function () use ($locked, $decider, $details, $released): void {
                    $this->queueDecidedMail($locked, $decider->name, $details);
                    $this->queueCohortMail($locked, $released);
                });
            });
        } catch (StaleDecisionException $e) {
            return $this->loser($e->reason);
        } catch (SpanDriftException $e) {
            return $this->loser($e->reason);
        }

        return ['ok' => true, 'conflict_reason' => null];
    }

    /**
     * D3 — stamp `official_event` for the (original session, ORIGINAL week)
     * + free the original occurrence's booked rows. The keyed source is the
     * REQUEST row's `week_number` (the original occurrence's week) — never
     * the junction slots'. The exception write is SAVEPOINT-wrapped so a
     * UNIQUE violation (a sibling approve racing the stamp / a live cancel)
     * rolls back ONLY to the savepoint and the txn CONTINUES (Q5: no 500).
     *
     * @param  array<string, mixed>  $details  (mutates: the release rider)
     * @return array<int, int> the freed slot ids
     */
    private function releaseOriginal(ReplacementRequest $locked, User $decider, array &$details): array
    {
        $originalSessionId = (int) $locked->class_session_id;
        $originalWeek = (int) $locked->week_number;

        DB::transaction(function () use ($originalSessionId, $originalWeek): void {
            $exists = ClassException::query()
                ->where('class_session_id', $originalSessionId)
                ->where('week_number', $originalWeek)
                ->exists();

            if (! $exists) {
                ClassException::create([
                    'class_session_id' => $originalSessionId,
                    'week_number' => $originalWeek,
                    'reason' => 'official_event',
                ]);
            }
        });

        // The booked rows carrying the ORIGINAL session's own booking for the
        // original week — identity-keyed, NEVER location matching (spec R3).
        $occupied = TimeSlot::query()
            ->where('class_session_id', $originalSessionId)
            ->where('week_number', $originalWeek)
            ->where('status', 'occupied')
            ->orderBy('id')
            ->get();

        $freed = [];
        foreach ($occupied as $slot) {
            // A savepoint per flip too — a UNIQUE-race freed row already set
            // available by a sibling txn must not abort the approve txn.
            DB::transaction(function () use ($slot, &$freed): void {
                $expected = (int) $slot->version;
                $affected = DB::update(
                    'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ? AND status = ?',
                    ['available', $expected + 1, $slot->id, $expected, 'occupied'],
                );

                if ($affected > 0) {
                    $freed[] = (int) $slot->id;
                }
            });
        }

        $details['original_release'] = [
            'class_session_id' => $originalSessionId,
            'week_number' => $originalWeek,
            'exception_stamped' => true,
            'freed_slots' => $freed,
        ];

        return $freed;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function queueDecidedMail(ReplacementRequest $locked, string $deciderName, array $details): void
    {
        $proposer = $locked->proposer;

        if ($proposer === null) {
            return; // orphaned row — nothing to notify
        }

        // CAPTURE-AT-QUEUE-TIME (spec notifications R1#2): span/venue/reason
        // are resolved NOW, pre-release — a rejected row's junction is gone
        // by the time a worker renders.
        $mapper = app(ReplacementRequestMapper::class);
        $span = $mapper->replacementStrings($locked, Carbon::parse(now()->toDateString()));

        Mail::to((string) $proposer->email)->queue(new ReplacementDecided(
            (int) $locked->id,
            (string) ($locked->classSession->module->module_code ?? ''),
            'approved',
            $span['time'] ?? '',
            $span['venue'] ?? '',
            null,
            $deciderName,
        ));
    }

    /**
     * @param  array<int, int>  $freedSlots
     */
    private function queueCohortMail(ReplacementRequest $locked, array $freedSlots): void
    {
        $session = $locked->classSession;

        if ($session === null) {
            return;
        }

        $mapper = app(ReplacementRequestMapper::class);
        $span = $mapper->replacementStrings($locked, Carbon::parse(now()->toDateString()));

        foreach ($session->cohorts as $cohort) {
            DB::afterCommit(function () use ($cohort, $locked, $span): void {
                $students = $cohort->students()->with('user')->get();

                foreach ($students as $student) {
                    $user = $student->user;

                    if ($user !== null && $user->email !== '') {
                        Mail::to((string) $user->email)->queue(new ReplacementApprovalNotice(
                            (int) $locked->id,
                            (string) ($locked->classSession->module->module_code ?? ''),
                            (string) ($cohort->programme->programme_code ?? ''),
                            $span['date'] ?? '',
                            $span['time'] ?? '',
                            $span['venue'] ?? '',
                        ));
                    }
                }
            });
        }
    }

    /**
     * @param  Collection<int, int>  $slotIds
     */
    private function assertSpanIsContiguousAndReal(Collection $slotIds, ?TimeSlot $anchor): void
    {
        // The count>=1 guard: a junction-less pending row whose anchor is gone
        // may never approve with zero flips (spec R1 step 2's positive guard).
        if ($slotIds->isEmpty() || ((int) $slotIds->first() === 0 && $anchor === null)) {
            throw new SpanDriftException(ApprovalValidity::ALREADY_DECIDED);
        }
    }

    /** @return array{ok: false, conflict_reason: string} */
    private function loser(string $reason): array
    {
        return ['ok' => false, 'conflict_reason' => $reason];
    }
}

/** Case (a): the request row moved (status ≠ pending). Rides its own text. */
final class StaleDecisionException extends RuntimeException
{
    public function __construct(public readonly string $reason) {}
}

/** Case (b): the span drifted (member missing / status ≠ pending / version moved). */
final class SpanDriftException extends RuntimeException
{
    public function __construct(public readonly string $reason) {}
}
