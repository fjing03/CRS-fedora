<?php

namespace App\Support;

/**
 * The ONE label law for class-exception reason enums (cancelled-blocks-stay-
 * visible): the third consumer's promote-on-3rd receipt. Providers:
 * ReplacementHome::titleCase, ApiReadController's $reasonMap, and the
 * timetable payload's cancelledReason. `replaced` is dead-but-harmless (the
 * release stamp never renders a user-visible label).
 */
final class ReasonLabel
{
    private const OVERRIDES = [
        'medical_leave' => 'Medical/Sick Leave',
        'other' => 'Other',
        'replaced' => 'Replaced',
    ];

    public static function from(string $enum): string
    {
        return self::OVERRIDES[$enum] ?? ucwords(str_replace('_', ' ', $enum));
    }
}
