<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $role_view
 * @property string $type
 * @property string $title
 * @property string $body
 * @property string $link
 * @property int|null $request_id
 * @property int|null $class_session_id
 * @property int|null $week
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notification extends Model
{
    protected $fillable = [
        'user_id', 'role_view', 'type', 'title', 'body', 'link',
        'request_id', 'class_session_id', 'week', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'week' => 'integer',
            'request_id' => 'integer',
            'class_session_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ReplacementRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ReplacementRequest::class);
    }
}
