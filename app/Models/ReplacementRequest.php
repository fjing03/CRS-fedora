<?php

namespace App\Models;

use Database\Factories\ReplacementRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $semester_id
 * @property int $proposer_id
 * @property int $class_session_id
 * @property int $week_number
 * @property int $replacement_time_slot_id
 * @property int|null $approver_id
 * @property string $status
 * @property string|null $rejection_reason
 * @property string|null $remarks
 * @property Carbon $submitted_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['semester_id', 'proposer_id', 'class_session_id', 'week_number', 'replacement_time_slot_id', 'approver_id', 'status', 'rejection_reason', 'remarks', 'submitted_at', 'decided_at'])]
class ReplacementRequest extends Model
{
    /** @use HasFactory<ReplacementRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'week_number' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** @return BelongsTo<TimeSlot, $this> */
    public function replacementTimeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class, 'replacement_time_slot_id');
    }

    /** @return BelongsTo<ClassSession, $this> */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }
}
