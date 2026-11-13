<?php

namespace App\Actions;

use App\Services\OriginalOccurrenceEntry;
use App\Services\OriginalOccurrencePlan;
use Illuminate\Support\Facades\DB;

/**
 * Consume a LOCKED, freshly revalidated original-occurrence plan (SDD
 * cancelled-slot-reuse, design D3/D4) and release its `original` cells.
 *
 * Contract with the caller:
 *   · the caller owns the transaction and the lock order (D4: source class
 *     sessions ascending, source replacement requests ascending, then the
 *     union of original + landing time_slots ascending id) — this action
 *     REFUSES to run outside an active database transaction, because a
 *     stale-member throw can only erase the batch through a rollback;
 *   · the plan MUST be the one re-planned inside that transaction after the
 *     locks — this action trusts its entries and defends each flip with the
 *     expected BEFORE identity/status/owner/version conditions, so a stale
 *     member throws and the caller's rollback erases the whole batch (never a
 *     partial release);
 *   · every flip is additionally bound to the plan's COMPLETE original
 *     identity coordinates (semester/week/day/venue/start/end) captured at
 *     plan time — a row whose geometry moved without a version bump cannot
 *     be silently freed;
 *   · audit composition is the caller's concern (its own legal `action`);
 *     this action only returns the structured receipt the audit embeds.
 *
 * Every changed cell becomes `available`, loses its original
 * `class_session_id` (only on CHANGED cells — stale ids on available and
 * protected rows are left byte-identical) and gains exactly one version.
 * `available` and `request_held` cells ride the receipt as no-ops with their
 * reason; duplicates are reported, never repaired.
 */
final class ReleaseOriginalOccurrenceSlots
{
    public function __invoke(OriginalOccurrencePlan $plan): OriginalOccurrenceReleaseReceipt
    {
        if (DB::transactionLevel() < 1) {
            throw new OriginalOccurrenceReleaseException('transaction_required');
        }

        if ($plan->hasRefusal()) {
            throw new OriginalOccurrenceReleaseException($plan->refusalReason() ?? 'plan_refused');
        }

        $originals = array_values(array_filter(
            $plan->entries,
            fn (OriginalOccurrenceEntry $entry): bool => $entry->classification === OriginalOccurrencePlan::ORIGINAL,
        ));

        // Flip in slot-id order — the same order the caller locked in (D4).
        usort($originals, fn (OriginalOccurrenceEntry $a, OriginalOccurrenceEntry $b): int => (int) $a->slotId <=> (int) $b->slotId);

        $changed = [];
        $noOps = [];

        foreach ($originals as $entry) {
            $changed[] = $this->flip($plan, $entry);
        }

        foreach ($plan->entries as $entry) {
            if ($entry->classification === OriginalOccurrencePlan::AVAILABLE
                || $entry->classification === OriginalOccurrencePlan::REQUEST_HELD) {
                $noOps[] = [
                    'slot_id' => $entry->slotId,
                    'start_time' => $entry->startTime,
                    'end_time' => $entry->endTime,
                    'reason' => $this->noOpReason($entry),
                    'duplicate_slot_ids' => count($entry->duplicateSlotIds) > 1 ? $entry->duplicateSlotIds : [],
                    'held_by_request_id' => $entry->heldByRequestId,
                ];
            }
        }

        return new OriginalOccurrenceReleaseReceipt($plan->classSessionId, $plan->weekNumber, $changed, $noOps);
    }

    /**
     * The version-checked, geometry-checked, owner-checked flip.
     * `affected === 0` means the row drifted behind the plan — refuse the
     * WHOLE release, the caller rolls back, no partial success (D4/S08).
     *
     * @return array{slot_id: int, start_time: string, end_time: string, before: array{status: string, class_session_id: int|null, version: int}, after: array{status: string, class_session_id: int|null, version: int}}
     */
    private function flip(OriginalOccurrencePlan $plan, OriginalOccurrenceEntry $entry): array
    {
        $slotId = (int) $entry->slotId;
        $beforeVersion = (int) $entry->version;

        $affected = DB::update(
            'UPDATE time_slots SET status = ?, class_session_id = NULL, version = ?'
            .' WHERE id = ? AND semester_id = ? AND week_number = ? AND day_of_week = ? AND venue_id = ?'
            .' AND start_time = ? AND end_time = ? AND status = ? AND class_session_id = ? AND version = ?',
            [
                'available',
                $beforeVersion + 1,
                $slotId,
                $plan->semesterId,
                $plan->weekNumber,
                $plan->dayOfWeek,
                $plan->venueId,
                $entry->startTime,
                $entry->endTime,
                'occupied',
                $plan->classSessionId,
                $beforeVersion,
            ],
        );

        if ($affected === 0) {
            throw new OriginalOccurrenceReleaseException('stale_member', $entry);
        }

        return [
            'slot_id' => $slotId,
            'start_time' => $entry->startTime,
            'end_time' => $entry->endTime,
            'before' => [
                'status' => (string) $entry->status,
                'class_session_id' => $entry->ownerSessionId,
                'version' => $beforeVersion,
            ],
            'after' => [
                'status' => 'available',
                'class_session_id' => null,
                'version' => $beforeVersion + 1,
            ],
        ];
    }

    private function noOpReason(OriginalOccurrenceEntry $entry): string
    {
        return match ($entry->classification) {
            OriginalOccurrencePlan::AVAILABLE => 'already_available',
            OriginalOccurrencePlan::REQUEST_HELD => 'request_held',
            default => $entry->classification,
        };
    }
}
