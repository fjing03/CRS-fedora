<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $class_session_id
 * @property int $week_number
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClassException extends Model
{
    protected $fillable = ['class_session_id', 'week_number', 'reason'];

    protected function casts(): array
    {
        return [
            'week_number' => 'integer',
        ];
    }

    /** @return BelongsTo<ClassSession, $this> */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }
}
