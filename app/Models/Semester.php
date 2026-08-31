<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $semester_code
 * @property string $label
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $week_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Semester extends Model
{
    protected $fillable = ['semester_code', 'label', 'start_date', 'end_date', 'week_count'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'week_count' => 'integer',
        ];
    }

    /** @return HasMany<Holiday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * The semester in progress (start_date <= today, latest first), falling
     * back to id 1 per design D9, then to the latest row overall.
     */
    public static function active(): ?self
    {
        return static::whereDate('start_date', '<=', today())
            ->orderByDesc('start_date')
            ->first()
            ?? static::find(1)
            ?? static::orderByDesc('start_date')->first();
    }
}
