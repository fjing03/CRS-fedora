<?php

namespace App\Services;

/**
 * ONE cancellation pair assembled for the cleanup inventory (SDD
 * cancelled-slot-reuse, design D9): the evidence pair, the shared planner's
 * classification of the whole occurrence, the include/exclude decision and
 * the scoped raw facts the fingerprint covers.
 *
 * `included` is true only for a clean, unambiguous, releasable occurrence —
 * everything else is reported and excluded as a WHOLE occurrence with its
 * reason (evidence contradiction, plan refusal, missing session row). The
 * release may touch ONLY the `original` cells of included occurrences.
 */
final class CancelledSlotCleanupOccurrence
{
    /**
     * @param  OriginalOccurrencePlan|null  $plan  the shared planner's classification; null only when the session row is gone
     * @param  array<string, mixed>  $facts  the scoped raw facts (session, exceptions, audits, slots, requests, junctions) hashed by the fingerprint
     */
    public function __construct(
        public readonly CancelledSlotCleanupPair $pair,
        public readonly ?OriginalOccurrencePlan $plan,
        public readonly bool $included,
        public readonly ?string $exclusionReason,
        public readonly array $facts = [],
    ) {}

    /** The stable occurrence identity key ('<session_id>:<week>'). */
    public function key(): string
    {
        return $this->pair->key();
    }
}
