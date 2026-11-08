<?php

namespace App\Support;

use Carbon\Carbon;
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

    /**
     * "What is today ON CAMPUS?" — the calendar the RULES ask (SDD
     * `local-calendar-rules`, 2026-11-08).
     *
     * `today()` (the framework helper) answers in the APP clock (UTC), so between
     * 00:00 and 08:00 Malaysia time it still calls it yesterday: a class that has
     * already happened reads "Approved", the landing week can be one week low on
     * a Monday, and day-ago labels are off by one. This answers with the campus
     * calendar instead — the SAME INSTANT, a different zone, so every instant
     * comparison that uses it stays correct.
     *
     * NOT for stored stamps: `submitted_at` / `decided_at` / `created_at` stay
     * UTC on purpose (writing campus time into those columns would corrupt the
     * history). It is for calendar reads only.
     */
    public static function today(): Carbon
    {
        return Carbon::now(self::TZ)->startOfDay();
    }

    /** The campus date as 'Y-m-d' — the safe thing to compare with any stored date column. */
    public static function dateString(): string
    {
        return self::today()->toDateString();
    }

    /**
     * Whole days from a `Y-m-d` (or any date-ish value) to the campus today —
     * positive when the date is in the past. Both sides are read as campus
     * midnights, so an 8-hour offset can never move the count by one.
     */
    public static function daysSince(CarbonInterface|string $date): int
    {
        $from = ($date instanceof CarbonInterface ? $date->format('Y-m-d') : (string) $date);

        return (int) Carbon::parse($from, self::TZ)->startOfDay()->diffInDays(self::today());
    }
}
