<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property int|null $replacement_request_id
 * @property int|null $time_slot_id
 * @property string|null $old_status
 * @property string|null $new_status
 * @property string|null $occ_validation_result
 * @property array<string, mixed>|null $details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'action', 'replacement_request_id', 'time_slot_id', 'old_status', 'new_status', 'occ_validation_result', 'details'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
