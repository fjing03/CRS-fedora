<?php

namespace App\Actions;

use App\Mail\ReplacementDecided;
use App\Models\AuditLog;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\ApprovalValidity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * PL decision — REJECT a pending replacement request (SDD
 * span-aware-approval-flow, design D2 + spec decision-flow R2).
 *
 * The release family: the request-row lock HELD across the whole release →

 the ordered junction lock → check ALL still pending → per-slot version-checked
 * `pending→available` flips (VERBATIM CancelPendingRequest's flip+DELETE shape
 * ONLY — cancel's skip semantics are NOT copied: a drifted row THROWS out of
 * the txn, nothing half-releases) → junction rows DELETE → `status='rejected'`
 * + `approver_id` (the deciding PL — one identity slot, no rejector column) +
 * `decided_at` + the reason VERBATIM → ONE summary audit + per-flip audits →
 * mails AFTER commit. NO exception stamp, NO original release (the natural
 * conflict occurrence auto-returns on every read).
 */
final class RejectRequest
{
    /**
     * @return array{ok: bool, conflict_reason: string|null}
     */
    public function __invoke(User $decider, int $requestId, string $reason): array
    {
        // FR 3.6 — the LAST line of defence (the modal validates for UX only).
        $reason = trim($reason);

        if ($reason === '') {
            return ['ok' => false, 'conflict_reason' => 'A rejection reason is required.'];
        }

        $request = ReplacementRequest::query()->find($requestId);

        if ($request === null) {
            return ['ok' => false, 'conflict_reason' => ApprovalValidity::ALREADY_DECIDED];
        }

        $slotIds = ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)
            ->orderBy('time_slot_id')
            ->pluck('time_slot_id');

        if ($slotIds->isEmpty()) {
            $slotIds = collect([(int) $request->replacement_time_slot_id]);
        }

        /** Setup-time validity: pending row + span intact (the same predicate). */
        $validity = app(ApprovalValidity::class)->evaluate($request);
        if (! $validity->valid) {
            return ['ok' => false, 'conflict_reason' => $validity->reason];
        }

        try {
            DB::transaction(function () use ($request, $decider, $reason, $slotIds): void {
                // Step 1 — the request-row lock (held for the WHOLE release).
                $locked = ReplacementRequest::query()->whereKey($request->id)->lockForUpdate()->first();

                if ($locked === null || (string) $locked->status !== 'pending') {
                    throw new RejectStaleException(ApprovalValidity::ALREADY_DECIDED);
                }

                // Step 2 — ordered lock over the span.
                $slots = TimeSlot::query()
                    ->whereIn('id', $slotIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($slots->count() !== $slotIds->count()) {
                    throw new RejectDriftException(
                        ApprovalValidity::notAvailableMessage('', ''),
                    );
                }

                // Step 3 — validity = every member still `pending`.
                foreach ($slotIds as $slotId) {
                    $slot = $slots->get($slotId);

                    if ($slot === null) {
                        throw new RejectDriftException(ApprovalValidity::ALREADY_DECIDED);
                    }

                    if ((string) $slot->status !== 'pending') {
                        throw new RejectDriftException(
                            ApprovalValidity::notAvailableMessage(
                                (string) $slot->start_time,
                                (string) $slot->end_time,
                            ),
                        );
                    }
                }

                // Step 4 — flip ALL `pending→available` (version-checked;
                // version advances per flip).
                foreach ($slotIds as $slotId) {
                    $slot = $slots->get($slotId);
                    $expected = (int) $slot->version;
                    $affected = DB::update(
                        'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                        ['available', $expected + 1, $slot->id, $expected],
                    );

                    if ($affected === 0) {
                        throw new RejectDriftException(
                            ApprovalValidity::notAvailableMessage(
                                (string) $slot->start_time,
                                (string) $slot->end_time,
                            ),
                        );
                    }
                }

                // Step 5 — the junction rows DELETE (the release family is
                // VERBATIM `CancelPendingRequest`'s flip+DELETE shape ONLY).
                ReplacementRequestSlot::query()
                    ->where('replacement_request_id', $locked->id)
                    ->delete();

                // Step 6 — the reject stamp (`approver_id` = the deciding PL).
                $locked->fill([
                    'status' => 'rejected',
                    'approver_id' => $decider->id,
                    'decided_at' => now(),
                    'rejection_reason' => $reason,
                ]);
                $locked->save();

                // Step 7 — summary audit (+ `time_slot_id = anchor` symmetry)
                // + per-flip audits.
                AuditLog::create([
                    'user_id' => $decider->id,
                    'action' => 'rejected',
                    'replacement_request_id' => $locked->id,
                    'time_slot_id' => (int) $locked->replacement_time_slot_id,
                    'old_status' => 'pending',
                    'new_status' => null,
                    'occ_validation_result' => 'success',
                    'details' => [
                        'decision' => 'rejected',
                        'reason' => $reason,
                        'slots' => $slotIds->values()->all(),
                    ],
                ]);

                foreach ($slotIds as $slotId) {
                    AuditLog::create([
                        'user_id' => $decider->id,
                        'action' => 'rejected',
                        'replacement_request_id' => $locked->id,
                        'time_slot_id' => (int) $slotId,
                        'old_status' => 'pending',
                        'new_status' => 'available',
                        'details' => [
                            'decision' => 'rejected',
                            'reason' => $reason,
                        ],
                    ]);
                }

                // Step 9 — mails AFTER COMMIT (capture-at-queue-time: read the
                // PRE-release state internally).

                DB::afterCommit(function () use ($locked, $decider, $reason, $slotIds): void {
                    $this->queueMail($locked, $decider->name, $reason, $slotIds);
                });
            });
        } catch (RejectStaleException $e) {
            return ['ok' => false, 'conflict_reason' => $e->reason];
        } catch (RejectDriftException $e) {
            return ['ok' => false, 'conflict_reason' => $e->reason];
        }

        return ['ok' => true, 'conflict_reason' => null];
    }

    /**
     * Capture-at-QUEUE-TIME (spec notifications R1#2): the span facts ride
     * the pre-release state (the junction is being deleted by this txn —
     * the mail resolves its strings HERE, before enqueue).
     *
     * @param  Collection<int, int>  $slotIds
     */
    private function queueMail(ReplacementRequest $locked, string $deciderName, string $reason, $slotIds): void
    {
        $proposer = $locked->proposer;

        if ($proposer === null) {
            return; // orphaned row — nothing to notify
        }

        $anchor = TimeSlot::find((int) $locked->replacement_time_slot_id);
        $start = (string) ($slotIds->isNotEmpty() ? TimeSlot::find((int) $slotIds->first())->start_time ?? '' : $anchor?->start_time);
        $end = (string) ($slotIds->isNotEmpty() ? TimeSlot::find((int) $slotIds->last())->end_time ?? '' : $anchor?->end_time);
        $venueId = $slotIds->isNotEmpty() ? TimeSlot::find((int) $slotIds->first())->venue_id : $anchor?->venue_id;
        $venue = $venueId !== null ? Venue::find((int) $venueId) : null;
        $venueLabel = $venue !== null ? (string) $venue->room_code : '';

        Mail::to((string) $proposer->email)->queue(new ReplacementDecided(
            (int) $locked->id,
            (string) ($locked->classSession->module->module_code ?? ''),
            'rejected',
            $start.' – '.$end,
            $venueLabel,
            $reason,
            $deciderName,
        ));
    }
}

/** Reject's case (a). */
final class RejectStaleException extends RuntimeException
{
    public function __construct(public readonly string $reason) {}
}

/** Reject's case (b). */
final class RejectDriftException extends RuntimeException
{
    public function __construct(public readonly string $reason) {}
}
