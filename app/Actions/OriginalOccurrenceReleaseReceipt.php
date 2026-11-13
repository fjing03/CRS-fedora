<?php

namespace App\Actions;

/**
 * The structured before/after/no-op receipt of one original-occurrence
 * release (SDD cancelled-slot-reuse, design D3/D4). The caller embeds it in
 * its own audit row (its legal `action`) — D4's `original_release` evidence
 * lives there, not in a new audit kind.
 *
 * Its own PSR-4 file on purpose: the repo's multi-class co-files (e.g.
 * `ReleaseRequestSlots` + `ReleaseDriftException`) are the trap the apply
 * brief warns about for new code.
 */
final class OriginalOccurrenceReleaseReceipt
{
    /**
     * @param  list<array{slot_id: int, start_time: string, end_time: string, before: array{status: string, class_session_id: int|null, version: int}, after: array{status: string, class_session_id: int|null, version: int}}>  $changed  each flipped cell's before/after identity/status/owner/version
     * @param  list<array{slot_id: int|null, start_time: string, end_time: string, reason: string, duplicate_slot_ids: list<int>, held_by_request_id: int|null}>  $noOps  available/protected cells with their reason (duplicates and live owners named)
     */
    public function __construct(
        public readonly int $classSessionId,
        public readonly int $weekNumber,
        public readonly array $changed,
        public readonly array $noOps,
    ) {}

    /** The release changed nothing — every cell was already available or protected. */
    public function isNoOp(): bool
    {
        return $this->changed === [];
    }
}
