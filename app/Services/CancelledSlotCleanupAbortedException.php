<?php

namespace App\Services;

use RuntimeException;

/**
 * The cleanup apply was ABORTED before any lasting write (SDD
 * cancelled-slot-reuse, design D9, spec S28): a scoped ownership input
 * changed since the approved plan, a planned identity went missing, the
 * fingerprint drifted, the target moved or a planned release lost its
 * before-state race. The caller's transaction rolls back — an aborted plan
 * never leaves a partial change and always requires a fresh dry-run and a
 * new approval.
 */
final class CancelledSlotCleanupAbortedException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Cancelled-slot cleanup aborted before any write: '.$reason.'.');
    }
}
