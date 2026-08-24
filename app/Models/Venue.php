<?php

namespace App\Models;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $room_code
 * @property string|null $room_name
 * @property int $capacity
 * @property string $room_type
 * @property string $allowed_session_types
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['room_code', 'room_name', 'capacity', 'room_type', 'allowed_session_types'])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    /** @return HasMany<ClassSession, $this> */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    /** @return HasMany<TimeSlot, $this> */
    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }
}
