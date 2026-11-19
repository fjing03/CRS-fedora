<?php

namespace App\Support;

/**
 * The login page's daily quote (SDD `login-daily-quote`, 2026-11-19).
 *
 * Seven fixed records live in `config/login-quotes.php`, keyed by ISO weekday
 * (1 = Monday … 7 = Sunday). The weekday comes from the CAMPUS clock, never
 * the UTC app clock: between 00:00 and 08:00 Malaysia time the two disagree
 * about which day it is, and the quote must roll over at 00:00 MYT.
 *
 * Read-only by construction — no DB, no cache, no state. An unknown day or an
 * incomplete record answers `null`, and the login page then renders nothing
 * rather than an empty block.
 */
final class LoginQuote
{
    /**
     * The quote for a given ISO weekday, or null when that day has no usable
     * record.
     *
     * @return array{quote: string, author: string, avatar: string}|null
     */
    public static function forDay(int $isoDay): ?array
    {
        $rows = config('login-quotes', []);

        if (! is_array($rows)) {
            return null;
        }

        $row = $rows[$isoDay] ?? null;

        if (! is_array($row)) {
            return null;
        }

        foreach (['quote', 'author', 'avatar'] as $field) {
            if (! isset($row[$field]) || ! is_string($row[$field]) || trim($row[$field]) === '') {
                return null;
            }
        }

        /** @var array{quote: string, author: string, avatar: string} $row */
        return $row;
    }

    /**
     * Today's quote on the campus calendar — the one the login page shows.
     *
     * @return array{quote: string, author: string, avatar: string}|null
     */
    public static function today(): ?array
    {
        return self::forDay(LocalTime::today()->dayOfWeekIso);
    }
}
