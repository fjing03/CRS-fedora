<?php

namespace App\Support;

/**
 * Title Case for user-facing labels (notif-inactive-hide-sender, 2026-11-05).
 *
 * The notification titles are STORED as sentences ("Scheduled class cancelled")
 * because they are written by several emitters over months; the panel reads
 * Title Case. Converting at render keeps the stored history intact and gives one
 * definition for every row.
 *
 * Rules (spec R2):
 *  - every word is capitalised except the MINOR words when they are neither the
 *    first nor the last word;
 *  - a token that carries digits, `#`, `/`, `&`, `@` or an existing acronym
 *    (`ID`, `PL`, `MYT`) is left exactly as it is — `New Replacement Request
 *    (ID #28)` must survive untouched;
 *  - a word that starts with punctuation keeps the punctuation and capitalises
 *    its first letter ("(late)" → "(Late)").
 */
final class TitleCase
{
    /** Words that stay lowercase mid-sentence in title case. */
    private const MINOR = [
        'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'into',
        'nor', 'of', 'on', 'onto', 'or', 'per', 'the', 'to', 'via', 'vs', 'with',
    ];

    public static function of(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $words = preg_split('/\s+/', $value) ?: [$value];
        $last = count($words) - 1;

        foreach ($words as $index => $word) {
            if ($index !== 0 && $index !== $last && self::isUntouchable($word)) {
                continue;
            }

            $words[$index] = self::capitalise($word);
        }

        return implode(' ', $words);
    }

    /** Nothing to do: a number/acronym token, or a minor word mid-sentence. */
    private static function isUntouchable(string $word): bool
    {
        $bare = self::bare($word);

        if ($bare === '') {
            return true;
        }

        // keeps `(ID`, `#28)`, `AMSE2003`, `P/T`, `A&B`, `tarc.edu.my`
        if (preg_match('/[0-9#\/&@.]/', $bare) === 1) {
            return true;
        }

        // an existing acronym stays one
        if (preg_match('/^[A-Z]{2,}$/', $bare) === 1) {
            return true;
        }

        return in_array(mb_strtolower($bare), self::MINOR, true);
    }

    private static function capitalise(string $word): string
    {
        if (preg_match('/^\P{L}*(\p{L})/u', $word, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $word;
        }

        $offset = $match[1][1];
        $letter = $match[1][0];

        return mb_substr($word, 0, $offset).mb_strtoupper($letter).mb_substr($word, $offset + mb_strlen($letter));
    }

    /** The word without its surrounding punctuation. */
    private static function bare(string $word): string
    {
        return trim($word, " \t\n\r\0\x0B\"'()[]{}<>,.;:!?—–-");
    }
}
