<?php

namespace App\Support;

/**
 * The PHP twin of the JS `UiLabels.classType(type, 'long')` — the ONE label law
 * for a class-session type code.
 *
 * Born 2026-11-06 (the notification tooltip rework, "Organise Notification Row
 * Hover Tooltips"): a tip line reads `AMCS2093 (Lecture) — Operating Systems`,
 * and composing that in PHP needed the long form the pages already show. The
 * raw code stays what the DB stores (`L` / `T` / `P`); this is a render-time
 * projection, never a second vocabulary: `L` = Lecture, `T` = Tutorial,
 * `P` = Practical (the same three the timetable and the modals print).
 */
final class ClassTypeLabel
{
    private const LONG = [
        'L' => 'Lecture',
        'T' => 'Tutorial',
        'P' => 'Practical',
    ];

    /** An unknown code returns unchanged — never an invented label. */
    public static function of(?string $code): string
    {
        $raw = trim((string) $code);

        return self::LONG[strtoupper($raw)] ?? $raw;
    }
}
