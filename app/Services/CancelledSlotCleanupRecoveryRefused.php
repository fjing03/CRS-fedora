<?php

namespace App\Services;

use RuntimeException;

/**
 * The recovery rehearsal refused EVERYTHING (SDD cancelled-slot-reuse,
 * design D9, spec S30): at least one receipt cell drifted or was reused, so
 * no partial restore may happen. The transaction rolls back untouched — the
 * real recovery decision belongs to the operator, with the affected
 * bookings reported.
 */
final class CancelledSlotCleanupRecoveryRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Cancelled-slot recovery rehearsal refused with no partial restore: '.$reason.'.');
    }
}
