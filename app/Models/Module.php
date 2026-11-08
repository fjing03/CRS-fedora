<?php

namespace App\Models;

use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $module_code
 * @property string $module_name
 * @property string $allowed_session_types
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['module_code', 'module_name', 'allowed_session_types'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    /**
     * The subject's name as the UI should print it.
     *
     * Four seeded modules carry no real name — `module_name` simply repeats the
     * code (AMSE2002, AMSE2003, AMSE2013, MPU-2302) — so a page that prints
     * `CODE` + name read "AMSE2003 AMSE2003 (L)". A blank or duplicated name now
     * reads as "Subject AMSE2003" (user request 2026-11-08), matching the
     * suppression the notification tips already did (NotificationTip::
     * sameCodeAndName). Every display payload asks this one method, so the
     * timetable, My Replacements, the arrangement grid, the venue pages, the
     * student history and the read API all say the same thing.
     */
    public function displayName(): string
    {
        $code = trim((string) $this->module_code);
        $name = trim((string) $this->module_name);

        if ($name === '' || ($code !== '' && $name === $code)) {
            return $code !== '' ? 'Subject '.$code : '';
        }

        return $name;
    }

    /** @return HasMany<ClassSession, $this> */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}
