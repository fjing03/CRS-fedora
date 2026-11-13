<?php

namespace App\Services;

use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * THE arrangement grid's actual slot holders (SDD cancelled-slot-reuse Part B,
 * design D8 / spec R7 S24) — the room arm's owner evidence, resolved per slot
 * from live request receipts, never from a stale `class_session_id`:
 *
 *   owners  — the slots whose holder is PROVEN to be exactly one active
 *             request, validated through the SAME strict claim core the
 *             release paths use ({@see OriginalOccurrenceReservation::validateRequestClaim()}:
 *             a coherent junction run or the documented junction-less
 *             anchor/span fallback via `ReplacementRequestMapper::spanFromAnchor()`,
 *             every cell in the claim's state). The live receipt outranks any
 *             stale original id;
 *   cells   — each proven request's FULL actual cell run (the holder's real
 *             span, not an anchor or half-cell);
 *   unknown — the slots an active claim touches without proving it (a
 *             malformed/contradictory receipt, or two claims on one cell):
 *             the cell stays blocked and its explanation stays NEUTRAL — no
 *             fabricated class name, no resurrected cancelled original (S25).
 *
 * Slots with NO active claim stay unclaimed: the caller's class-geometry
 * labels decide, and a cancelled original is already excluded there — a freed
 * occurrence is never described as holding the room.
 *
 * SELECT-only: it never locks and never writes. The cost is bounded by the
 * DISTINCT active requests touching the grid's slots (the strict proof runs
 * once per request), never per slot.
 */
final class ArrangementSlotHolders
{
    public function __construct(private readonly OriginalOccurrenceReservation $claims = new OriginalOccurrenceReservation) {}

    /**
     * Resolve the live ownership of every occupied/pending slot in the set.
     *
     * Discovery is threefold: the grid slots' junction links, the grid slots'
     * junction-less anchors, and the junction-less legacy claims whose anchor
     * sits OUTSIDE the grid input (a drifted-`available` anchor row) but on
     * the input's coordinate tuple — each discovered request is then proven
     * or refused through the SAME strict validator, never a divergent copy.
     *
     * @param  Collection<int, TimeSlot>  $slots  the grid's occupied/pending slots (one semester)
     * @return array{owners: array<int, int>, cells: array<int, list<array{slot_id: int, semester_id: int, week_number: int, day_of_week: int, venue_id: int, start_time: string, end_time: string, status: string, version: int, class_session_id: int|null}>>, unknownSlotIds: list<int>, requests: array<int, ReplacementRequest>}
     */
    public function resolve(Collection $slots): array
    {
        $empty = ['owners' => [], 'cells' => [], 'unknownSlotIds' => [], 'requests' => []];

        $slotIds = $slots->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($slotIds === []) {
            return $empty;
        }

        $inputIds = array_fill_keys($slotIds, true);

        // 1. the live junction receipts on these slots (one link per slot —
        //    the UNIQUE time_slot_id law — plus any stray link, fail closed).
        $touched = [];
        $candidateIds = [];

        $links = DB::table('replacement_request_slots as l')
            ->join('replacement_requests as r', 'r.id', '=', 'l.replacement_request_id')
            ->whereIn('l.time_slot_id', $slotIds)
            ->whereIn('r.status', ['pending', 'approved'])
            ->get(['l.time_slot_id', 'r.id']);

        foreach ($links as $link) {
            $slotId = (int) $link->time_slot_id;
            $requestId = (int) $link->id;
            $candidateIds[$requestId] = true;
            $touched[$slotId][$requestId] = true;
        }

        // 2. the junction-less anchor/span claims anchored on these slots —
        //    a proven fallback run's own first cell is one of them, so the
        //    anchor scopes the discovery without scanning every request.
        $anchored = ReplacementRequest::query()
            ->whereIn('status', ['pending', 'approved'])
            ->whereIn('replacement_time_slot_id', $slotIds)
            ->get(['id', 'replacement_time_slot_id']);

        foreach ($anchored as $request) {
            $requestId = (int) $request->id;
            $candidateIds[$requestId] = true;
            $touched[(int) $request->replacement_time_slot_id][$requestId] = true;
        }

        // 3. the junction-less legacy claims whose anchor is NOT one of the
        //    grid's rows — the grid input carries only occupied/pending rows,
        //    so an anchor row that drifted `available` would hide a claim
        //    whose tail cells are still in the input (S24). One bounded
        //    pass: the grid's own semester(s) only, no junction receipt,
        //    eager anchor, matched on the input's coordinate tuple — the
        //    shared validator then proves or refuses it like any other.
        $tuples = [];

        foreach ($slots as $slot) {
            $tuples[(int) $slot->week_number.'|'.(int) $slot->day_of_week.'|'.(int) $slot->venue_id] = true;
        }

        $strayAnchored = ReplacementRequest::query()
            ->whereIn('status', ['pending', 'approved'])
            ->whereIn('semester_id', $slots->pluck('semester_id')->unique()->map(fn ($id): int => (int) $id)->all())
            ->whereDoesntHave('requestSlots')
            ->with('replacementTimeSlot')
            ->get(['id', 'semester_id', 'replacement_time_slot_id']);

        foreach ($strayAnchored as $request) {
            $anchor = $request->replacementTimeSlot;

            if ($anchor === null) {
                continue;
            }

            $tuple = (int) $anchor->week_number.'|'.(int) $anchor->day_of_week.'|'.(int) $anchor->venue_id;

            if (! isset($tuples[$tuple])) {
                continue;
            }

            $candidateIds[(int) $request->id] = true;
            $touched[(int) $anchor->id][(int) $request->id] = true;
        }

        if ($candidateIds === []) {
            return $empty;
        }

        $requests = ReplacementRequest::query()
            ->with([
                'classSession.module',
                'classSession.venue',
                'classSession.lecturer.lecturer',
                'classSession.cohorts.programme',
            ])
            ->findMany(array_keys($candidateIds))
            ->keyBy(fn (ReplacementRequest $r): int => (int) $r->id);

        $proven = [];
        $unknown = [];
        $cells = [];

        foreach ($requests as $request) {
            $evidence = $this->claims->validateRequestClaim($request);

            if (! $evidence->isProven()) {
                // Malformed/contradictory/stale: the claim proves nothing
                // anywhere it touches — those cells go neutral, never guessed.
                foreach ($touched as $slotId => $requestIds) {
                    if (isset($requestIds[(int) $request->id])) {
                        $unknown[(int) $slotId] = true;
                    }
                }

                // the claim's own LANDING rows (the anchor plus every row at
                // the mapped span's coordinates): a refused fallback's
                // still-claim-state tail cells are touched evidence too —
                // they go neutral, never class geometry (S25).
                foreach ($evidence->landingSlotIds as $slotId) {
                    if (isset($inputIds[$slotId])) {
                        $unknown[$slotId] = true;
                    }
                }

                continue;
            }

            $cells[(int) $request->id] = $evidence->cells;

            foreach ($evidence->cells as $cell) {
                $proven[(int) $cell['slot_id']][(int) $request->id] = true;
            }
        }

        // One cell, one live owner: two proven claims contradict each other.
        $owners = [];

        foreach ($proven as $slotId => $requestIds) {
            $distinct = array_keys($requestIds);

            if (count($distinct) === 1) {
                $owners[$slotId] = (int) $distinct[0];
            } else {
                $unknown[$slotId] = true;
            }
        }

        // A cell carrying ANY ambiguous evidence stays neutral even when one
        // of the touching claims proved — the caller must never pick a side.
        foreach (array_keys($unknown) as $slotId) {
            unset($owners[$slotId]);
        }

        ksort($unknown);

        return [
            'owners' => $owners,
            'cells' => $cells,
            'unknownSlotIds' => array_map('intval', array_keys($unknown)),
            'requests' => $requests->all(),
        ];
    }
}
