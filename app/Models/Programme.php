<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $programme_code
 * @property string $programme_name
 * @property int $faculty_id
 */
class Programme extends Model
{
    protected $fillable = ['programme_code', 'programme_name', 'faculty_id'];

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return HasMany<Cohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }
}
