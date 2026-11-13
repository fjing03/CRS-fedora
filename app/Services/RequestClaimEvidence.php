<?php

namespace App\Services;

/**
 * The typed verdict of ONE replacement request's claim validation (SDD
 * cancelled-slot-reuse — the FULL request-claim geometry/state law, factored
 * out of the original-occurrence planner's active-claim checks so the
 * cancellation paths consume the SAME validation instead of a weaker
 * near-copy).
 *
 * SELECT-only — it never locks and never writes. It answers:
 *
 *   · is the claim's geometry coherent at all — the junction run (one
 *     semester/week/day/room, contiguous 30-minute cells opened by the
 *     request's own anchor in the request's semester, every member present)
 *     or the documented junction-less anchor/span fallback (a real
 *     non-midnight-wrapping run in the request's semester)?
 *   · which cells the claim PROVES right now: every cell's status matching
 *     the request's claim state (pending → `pending`, approved → `occupied`).
 *     A stale or contradicting state is a refusal, never proof — stricter
 *     than the occurrence-planner's duplicate tolerance, because a release
 *     must never free a cell the claim no longer holds;
 *   · which rows the claim's own receipt may touch (`$landingSlotIds`) — the
 *     lock-union set a mutating caller must already hold before it
 *     re-derives anything, junction ids or the anchor plus every row at the
 *     mapped span's coordinates (the seeded grid keeps duplicate `available`
 *     rows on the same coordinates; they are part of the union, never proof).
 */
final class RequestClaimEvidence
{
    /**
     * @param  list<array{slot_id: int, semester_id: int, week_number: int, day_of_week: int, venue_id: int, start_time: string, end_time: string, status: string, version: int, class_session_id: int|null}>  $cells  the PROVEN cells, ascending by id (empty when refused)
     * @param  list<int>  $landingSlotIds  every row the claim's own receipt/span may touch, ascending
     * @param  string  $kind  `junction` or `fallback` — meaningful only when proven
     * @param  int|null  $refusalSlotId  the cell that carried the refusal, when one does (the caller attaches it to its own drift exception)
     */
    public function __construct(
        public readonly ?string $refusalReason,
        public readonly array $cells = [],
        public readonly array $landingSlotIds = [],
        public readonly string $kind = 'junction',
        public readonly ?int $refusalSlotId = null,
    ) {}

    /** True when the claim is fully coherent AND every cell is proven. */
    public function isProven(): bool
    {
        return $this->refusalReason === null;
    }

    /** The proven cells' slot ids, ascending — the release set.
     *
     * @return list<int>
     */
    public function provenSlotIds(): array
    {
        return array_map(fn (array $cell): int => (int) $cell['slot_id'], $this->cells);
    }
}
