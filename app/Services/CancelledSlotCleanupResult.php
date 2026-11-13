<?php

namespace App\Services;

/**
 * The structured outcome of one cleanup apply attempt (SDD
 * cancelled-slot-reuse, design D9, spec S28/S29).
 *
 * `applied = false` means the plan was refused or aborted BEFORE any lasting
 * write (gate refusal, drift abort, contention, stale member). `verification`
 * collects the post-commit receipt checks — an empty list is a clean receipt;
 * a finding after commit is reported loudly, never silently swallowed.
 */
final class CancelledSlotCleanupResult
{
    /**
     * @param  list<array{class_session_id: int, week_number: int, changed: list<array<string, mixed>>, no_ops: list<array<string, mixed>>, audit_id: int|null}>  $occurrences
     * @param  list<string>  $verification  post-commit receipt findings; empty = clean
     */
    public function __construct(
        public readonly bool $applied,
        public readonly ?string $abortedReason,
        public readonly array $occurrences = [],
        public readonly array $verification = [],
    ) {}

    /** The total number of cells the apply actually flipped. */
    public function changedCellCount(): int
    {
        $count = 0;

        foreach ($this->occurrences as $occurrence) {
            $count += count($occurrence['changed']);
        }

        return $count;
    }

    /** The number of maintenance audit rows the apply wrote (one per released occurrence, never for a no-op). */
    public function auditsWritten(): int
    {
        $count = 0;

        foreach ($this->occurrences as $occurrence) {
            if ($occurrence['audit_id'] !== null) {
                $count++;
            }
        }

        return $count;
    }

    /** A successful apply that flipped nothing (the repeat/zero-change case). */
    public function isNoOp(): bool
    {
        return $this->applied && $this->changedCellCount() === 0;
    }
}
