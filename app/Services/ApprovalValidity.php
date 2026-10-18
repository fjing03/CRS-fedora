<?php

namespace App\Services;

use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;

/**
 * Full-block approval validity (SDD span-aware-approval-flow, design D6 /
 * spec decision-flow R4): THE shared predicate for the PL queue's
 * `slotValidity` badge AND the approve/reject actions' stale-slot re-check —
 * one class, zero drift (Q11).
 *
 * The PINNED 2-case map (no third case):
 *   (a) the request row moved (status ≠ pending) → reason 'already decided';
 *   (b) span drift (junction member missing / status ≠ pending) → the
 *       EXISTING `slot_not_available` named-time copy — the exact strings the
 *       submit path renders (see notAvailableMessage; a structural test pins
 *       the equality against ReplacementArrangement's conflictText).
 * Day-end/holiday facts cannot change mid-flight (static rows). The version
 * leg NEVER evaluates at render — it surfaces via the flip guard only.
 */
final class ApprovalValidity
{
    /** Case (a): the fixed text (a decided row can't be decided again). */
    public const ALREADY_DECIDED = 'already decided';

    public function evaluate(ReplacementRequest $request): ValidityResult
    {
        if ((string) $request->status !== 'pending') {
            return ValidityResult::conflict(self::ALREADY_DECIDED);
        }

        // The junction's LIVE receipt defines the span.
        $slots = DB::table('replacement_request_slots')
            ->join('time_slots', 'replacement_request_slots.time_slot_id', '=', 'time_slots.id')
            ->where('replacement_request_slots.replacement_request_id', (int) $request->id)
            ->orderBy('time_slots.start_time')
            ->get(['time_slots.id', 'time_slots.start_time', 'time_slots.end_time', 'time_slots.status']);

        if ($slots->isEmpty()) {
            // Junction-less legacy rows are still diagnosable by their anchor.
            $anchor = TimeSlot::find((int) $request->replacement_time_slot_id);
            $error = $this->driftReason($request, $anchor);

            return $error !== null
                ? ValidityResult::conflict($error)
                : ValidityResult::valid();
        }

        foreach ($slots as $slot) {
            if ((string) $slot->status !== 'pending') {
                $error = self::notAvailableMessage((string) $slot->start_time, (string) $slot->end_time);

                return ValidityResult::conflict($error);
            }
        }

        return ValidityResult::valid();
    }

    /**
     * Case (b) for a junction-less row: the anchor's own drift decides.
     *
     * @return string|null null when the anchor is intact (still valid)
     */
    private function driftReason(ReplacementRequest $request, ?TimeSlot $anchor): ?string
    {
        if ($anchor === null || (string) $anchor->status !== 'pending') {
            $start = $anchor !== null ? (string) $anchor->start_time : '';
            $end = $anchor !== null ? (string) $anchor->end_time : '';

            return self::notAvailableMessage($start, $end);
        }

        return null;
    }

    /**
     * THE named-time copy — byte-identical with the submit path's
     * `slot_not_available` rendering (ReplacementArrangement::conflictText).
     * A structural test (ApprovalWorkflowTest) pins the equality.
     */
    public static function notAvailableMessage(string $failingStart, string $failingEnd): string
    {
        return 'The block runs into an existing booking at '.substr($failingStart, 0, 5).'–'.substr($failingEnd, 0, 5)
            .' — pick another slot.';
    }
}
