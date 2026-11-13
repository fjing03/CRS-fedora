<?php

namespace App\Services;

/**
 * One expected 30-minute cell of an original class occurrence and its
 * classification evidence (SDD cancelled-slot-reuse, design D3).
 *
 * `reason` carries the refusal key for missing/ambiguous entries;
 * `heldByRequestId` names the live owner for request-held entries.
 *
 * Its own PSR-4 file on purpose — the apply brief warns against relying on
 * co-file load order for new code (the multi-class co-files are the trap).
 */
final class OriginalOccurrenceEntry
{
    /**
     * @param  string  $startTime  the cell's own grid start ('H:i:s')
     * @param  string  $endTime  always start + 30 minutes
     * @param  string  $classification  one of OriginalOccurrencePlan's classification constants
     * @param  int|null  $slotId  the coordinate's primary row (lowest id); null only when missing
     * @param  string|null  $status  the primary row's status at plan time
     * @param  int|null  $ownerSessionId  the primary row's `class_session_id` at plan time (the stale/legacy id)
     * @param  int|null  $version  the primary row's OCC version at plan time
     * @param  list<int>  $duplicateSlotIds  every row found at this coordinate, ascending (one id when unique)
     * @param  int|null  $heldByRequestId  the live pending/approved request owning the cell, when held
     * @param  string|null  $reason  the refusal key (`missing_row`, `duplicate_coordinates`, …) or null
     */
    public function __construct(
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly string $classification,
        public readonly ?int $slotId = null,
        public readonly ?string $status = null,
        public readonly ?int $ownerSessionId = null,
        public readonly ?int $version = null,
        public readonly array $duplicateSlotIds = [],
        public readonly ?int $heldByRequestId = null,
        public readonly ?string $reason = null,
    ) {}

    /** The release refuses the whole occurrence on this cell. */
    public function isRefusing(): bool
    {
        return $this->classification === OriginalOccurrencePlan::MISSING
            || $this->classification === OriginalOccurrencePlan::AMBIGUOUS;
    }

    /** The release leaves this cell untouched and reports it as a no-op. */
    public function isNoOp(): bool
    {
        return $this->classification === OriginalOccurrencePlan::AVAILABLE
            || $this->classification === OriginalOccurrencePlan::REQUEST_HELD;
    }
}
