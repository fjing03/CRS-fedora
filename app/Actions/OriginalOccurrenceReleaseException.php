<?php

namespace App\Actions;

use App\Services\OriginalOccurrenceEntry;
use RuntimeException;

/**
 * The original-occurrence release refused (SDD cancelled-slot-reuse, design
 * D3/D4): the plan carries a refusal (a missing or ambiguous cell) or a
 * conditional flip lost the before-state race (`stale_member`). The caller's
 * transaction rolls back — a refused release never leaves a partial change.
 *
 * Its own PSR-4 file on purpose: the repo's multi-class co-files (e.g.
 * `ReleaseRequestSlots` + `ReleaseDriftException`) are the trap the apply
 * brief warns about for new code.
 */
final class OriginalOccurrenceReleaseException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?OriginalOccurrenceEntry $entry = null)
    {
        parent::__construct($entry === null
            ? 'Original occurrence release refused: '.$reason.'.'
            : 'Original occurrence release refused: '.$reason.' at '.$entry->startTime.'–'.$entry->endTime.'.');
    }
}
