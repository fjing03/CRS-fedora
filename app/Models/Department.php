<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $dept_code
 * @property string $dept_name
 * @property int $faculty_id
 */
class Department extends Model
{
    protected $fillable = ['dept_code', 'dept_name', 'faculty_id'];

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return HasMany<Lecturer, $this> */
    public function lecturers(): HasMany
    {
        return $this->hasMany(Lecturer::class, 'dept_id');
    }
}
