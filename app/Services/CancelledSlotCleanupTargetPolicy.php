<?php

namespace App\Services;

/**
 * The PURE apply-target gate of the cleanup facility (SDD cancelled-slot-reuse,
 * design D9, spec S27) — no database access, unit-testable against any
 * forbidden target.
 *
 * Live apply is allowlisted to `class_replacement_fresh` ONLY. Testing
 * rehearsals are accepted on `class_replacement_testing` with an explicit
 * confirmation. Every other database — viva, demo twins, maintenance or
 * unknown targets — fails closed. The confirmed target must equal the actual
 * `current_database()` identity exactly: a near-miss name, a swapped
 * confirmation or a printed-vs-actual difference all refuse.
 */
final class CancelledSlotCleanupTargetPolicy
{
    public const LIVE_TARGET = 'class_replacement_fresh';

    public const REHEARSAL_TARGET = 'class_replacement_testing';

    /** @return list<string> */
    public static function allowedTargets(): array
    {
        return [self::LIVE_TARGET, self::REHEARSAL_TARGET];
    }

    /**
     * null passes; otherwise the refusal reason. `$confirmedTarget` is the
     * operator's `--confirm-database` value (null when not given);
     * `$actualDatabase` is the live `current_database()` read.
     */
    public static function check(?string $confirmedTarget, string $actualDatabase): ?string
    {
        if ($confirmedTarget === null || trim($confirmedTarget) === '') {
            return 'missing_target_confirmation';
        }

        if (! in_array($actualDatabase, self::allowedTargets(), true)) {
            return 'disallowed_target:'.$actualDatabase;
        }

        if ($confirmedTarget !== $actualDatabase) {
            return 'target_mismatch:confirmed '.$confirmedTarget.' vs actual '.$actualDatabase;
        }

        return null;
    }
}
