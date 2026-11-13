<?php

namespace App\Services;

use App\Actions\ReleaseOriginalOccurrenceSlots;

/**
 * The typed plan of ONE original class occurrence (SDD cancelled-slot-reuse,
 * design D3): every expected 30-minute cell of `(semester, class_session,
 * original_week)` at the session's immutable recurring coordinates, with a
 * classification and the evidence that produced it.
 *
 * The plan is the shared contract between the planner
 * ({@see OriginalOccurrenceReservation}, SELECT-only) and the release action
 * ({@see ReleaseOriginalOccurrenceSlots}, caller-supplied
 * transaction): the caller locks {@see OriginalOccurrencePlan::$candidateSlotIds}
 * (plus its own request rows and landing cells), re-plans inside the
 * transaction, and only then consumes the plan. Audit receipt composition
 * stays with the caller.
 */
final class OriginalOccurrencePlan
{
    /** The cell is the proven initial recurring reservation — release eligible. */
    public const ORIGINAL = 'original';

    /** Every matching row is already available (duplicates included) — no-op. */
    public const AVAILABLE = 'available';

    /** A pending/approved request owns the cell (junction or anchor/span fallback) — protected. */
    public const REQUEST_HELD = 'request_held';

    /** No row exists at the expected coordinate — the release refuses. */
    public const MISSING = 'missing';

    /** Contradictory/duplicate/unexplainable evidence — the release refuses. */
    public const AMBIGUOUS = 'ambiguous';

    /**
     * @param  list<OriginalOccurrenceEntry>  $entries  one per expected cell, ordered by start time
     * @param  list<array{slot_id: int, semester_id: int, week_number: int, day_of_week: int, venue_id: int, start_time: string, end_time: string, status: string, version: int}>  $outOfRange  rows carrying the source id OUTSIDE the occurrence's coordinates — reported, never freed
     * @param  list<int>  $candidateSlotIds  every slot id the classification read (expected rows, linked rows, consulted receipt runs, fallback anchors) — the caller's lock union, ascending
     * @param  list<int>  $claimingRequestIds  every active request the classification consulted (claims, fallback candidates) — the caller locks these before re-planning
     * @param  string|null  $refusalReason  a plan-level refusal (malformed session range, invalid week); entries are empty
     */
    public function __construct(
        public readonly int $classSessionId,
        public readonly int $semesterId,
        public readonly int $weekNumber,
        public readonly int $dayOfWeek = 0,
        public readonly int $venueId = 0,
        public readonly array $entries = [],
        public readonly array $outOfRange = [],
        public readonly array $candidateSlotIds = [],
        public readonly array $claimingRequestIds = [],
        public readonly ?string $refusalReason = null,
    ) {}

    /** True when the occurrence may not be released at all (plan refusal or any refusing cell). */
    public function hasRefusal(): bool
    {
        return $this->refusalReason !== null || $this->firstRefusingEntry() !== null;
    }

    /** The refusal that blocks the release: the plan-level reason, else the first refusing cell's reason. */
    public function refusalReason(): ?string
    {
        return $this->refusalReason ?? $this->firstRefusingEntry()?->reason;
    }

    /** The first refusing entry, when any (the caller uses it for the refusal's message). */
    public function firstRefusingEntry(): ?OriginalOccurrenceEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->isRefusing()) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The slot ids classified `original` — the ONLY cells a release may flip,
     * in entry (time) order.
     *
     * @return list<int>
     */
    public function releaseEligibleSlotIds(): array
    {
        $ids = [];

        foreach ($this->entries as $entry) {
            if ($entry->classification === self::ORIGINAL && $entry->slotId !== null) {
                $ids[] = $entry->slotId;
            }
        }

        return $ids;
    }

    /** A plan without refusal is safe to hand to the release action. */
    public function isReleasable(): bool
    {
        return ! $this->hasRefusal();
    }

    /** A releasable plan with nothing left to flip — the release is a structured no-op. */
    public function isNoOp(): bool
    {
        return $this->isReleasable() && $this->releaseEligibleSlotIds() === [];
    }
}
