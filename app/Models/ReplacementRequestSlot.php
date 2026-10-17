<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Full-block reservation junction (SDD full-block-reservation, design D0):
 * one row per reserved slot, linking the LIVE reservation to its request.
 * Rows are created by the OCC reservation and DELETED on release (cancel;
 * future approval reuses the same release) — the receipt of a LIVE
 * reservation only; history lives in audit_logs + the request row.
 * `time_slot_id` is UNIQUE: a slot is live-linked by at most one request.
 *
 * @property int $replacement_request_id
 * @property int $time_slot_id
 */
#[Fillable(['replacement_request_id', 'time_slot_id'])]
final class ReplacementRequestSlot extends Model
{
    /** @return BelongsTo<ReplacementRequest, $this> */
    public function replacementRequest(): BelongsTo
    {
        return $this->belongsTo(ReplacementRequest::class);
    }

    /** @return BelongsTo<TimeSlot, $this> */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }
}
