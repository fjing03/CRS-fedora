<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The dedicated contention exception (SDD cancelled-slot-reuse, design D4):
 * a CRS occurrence-write advisory lock could not be acquired WITHOUT BLOCKING.
 * The lock namespace is fixed and the key is the SOURCE class-session id —
 * never the anchor slot id — so the submit-side session-level lock and the
 * cancellation/approval-side transaction-level lock interlock on the same key.
 *
 * Callers catch ONLY this type and map it onto their EXISTING refusal
 * vocabulary — `SubmitReplacementRequest` answers its flat
 * `slot_not_available` loser shape, cancellation/approval answer the
 * refresh/retry refusal — never a blocking loop, never a silent full retry,
 * never an unmapped 500.
 */
final class OccurrenceWriteLockContended extends RuntimeException
{
    public function __construct(public readonly int $sourceSessionId)
    {
        parent::__construct("occurrence_write_lock_contended:source_session_id={$sourceSessionId}");
    }
}
