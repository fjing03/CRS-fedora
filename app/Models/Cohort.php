<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $programme_id
 * @property int $current_year
 * @property int $semester
 * @property int $tutorial_group
 * @property string $academic_year
 * @property string|null $intake
 * @property int|null $student_count
 */
class Cohort extends Model
{
    protected $fillable = ['programme_id', 'current_year', 'semester', 'tutorial_group', 'academic_year', 'intake', 'student_count'];

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
