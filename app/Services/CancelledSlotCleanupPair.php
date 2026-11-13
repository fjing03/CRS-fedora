<?php

namespace App\Services;

/**
 * One cancellation PAIR of the cleanup inventory (SDD cancelled-slot-reuse,
 * design D9, spec S26): a non-maintenance `class_cancelled` audit identifying
 * an exact `(class_session_id, week_number)` plus the matching CURRENT
 * class_exceptions row — the same discriminator the display side
 * (`TimetableEventIdentity`, D5/S13) must use.
 *
 * `contradiction` is null for a clean pair and carries the exclusion reason
 * otherwise (duplicate audits, undo evidence, missing/multiple exceptions,
 * reason mismatch, missing session row) — a contradicted pair is reported
 * and excluded, never repaired.
 */
final class CancelledSlotCleanupPair
{
    /**
     * @param  list<int>  $auditIds  every non-maintenance `class_cancelled` audit id backing the pair, ascending
     * @param  list<int>  $undoAuditIds  every `class_cancel_undone` audit id attributable to the same pair (legacy undo rows — never source evidence; their presence contradicts a live exception)
     */
    public function __construct(
        public readonly int $classSessionId,
        public readonly int $semesterId,
        public readonly int $weekNumber,
        public readonly string $reason,
        public readonly array $auditIds,
        public readonly array $undoAuditIds,
        public readonly ?int $exceptionId,
        public readonly ?string $exceptionReason,
        public readonly ?string $contradiction,
    ) {}

    /** The stable occurrence identity key ('<session_id>:<week>'). */
    public function key(): string
    {
        return $this->classSessionId.':'.$this->weekNumber;
    }
}
