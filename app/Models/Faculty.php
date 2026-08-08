<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $faculty_code
 * @property string $faculty_name
 */
class Faculty extends Model
{
    protected $fillable = ['faculty_code', 'faculty_name'];

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return HasMany<Programme, $this> */
    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }
}
