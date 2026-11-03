<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * THE user-facing clock (user decision 2026-10-19, promoted to one helper
 * 2026-11-04): the app/db clock is UTC while the USER's clock is
 * Asia/Kuala_Lumpur (+08). Every user-facing ABSOLUTE stamp converts here;
 * relative labels ("just now", "2 hours ago") stay as-is — they compare UTC
 * with UTC and are already correct.
 *
 * The bug this closes: the notifications panel formatted the STORED instant
 * directly, so Request #26's tooltip read `2 Nov 2026, 11:00 AM` (UTC) while
 * the history page — which converted — read `7:00 PM` for the same request.
 *
 * This is a display conversion ONLY: nothing stored is rewritten, and
 * `config/app.php`'s 'timezone' => 'UTC' stays untouched (moving that would
 * shift now(), every stored row, the semester maths and the whole suite).
 */
final class LocalTime
{
    public const TZ = 'Asia/Kuala_Lumpur';

    /** The MYT-form stamp — byte-shape identical with the previous UTC form. */
    public static function stamp(?CarbonInterface $stamp, string $format): ?string
    {
        return $stamp === null
            ? null
            : $stamp->copy()->setTimezone(self::TZ)->format($format);
    }
}
