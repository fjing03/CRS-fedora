<?php

namespace App\Services;

/**
 * THE generic cancellation-evidence predicates (cancelled-slot-reuse, S13/D9
 * reconciliation): ONE rule for what an audit row proves, shared by the
 * display lane ({@see TimetableEventIdentity}) and the cleanup facility —
 * so the two can never silently diverge.
 *
 * Pure static and immutable: everything is decided from the audit row's own
 * `action` + `details` payload, with no DB access. The current-exception
 * conjunction stays with each consumer (TimetableEventIdentity's provenance
 * verdict; the cleanup plan's own contradiction exclusions on top).
 */
final class CancelledOccurrenceEvidence
{
    /** The cancellation action that IS user evidence. */
    public const AUDIT_CANCELLATION = 'class_cancelled';

    /** The undo action — outside the user-evidence family entirely. */
    public const AUDIT_UNDO = 'class_cancel_undone';

    /** The semester week window a cancellation pair must name (1..14). */
    private const WEEK_MIN = 1;

    private const WEEK_MAX = 14;

    /**
     * The exact (class_session_id, week_number) pair inside an audit's
     * details, or null when no valid positive identity is readable.
     *
     * @param  array<string, mixed>  $details
     * @return array{session: int, week: int}|null
     */
    public static function pairFromDetails(array $details): ?array
    {
        $session = self::positiveInt($details['class_session_id'] ?? null);
        $week = self::positiveInt($details['week_number'] ?? null);

        return $session !== null && $week !== null
            ? ['session' => $session, 'week' => $week]
            : null;
    }

    /**
     * A truthy `details.maintenance` marks this facility's own receipt —
     * never user evidence, on every consumer.
     *
     * @param  array<string, mixed>  $details
     */
    public static function isMaintenanceDetails(array $details): bool
    {
        return ! empty($details['maintenance']);
    }

    /**
     * The shared user-cancellation audit rule: the `class_cancelled`
     * action, NOT a maintenance receipt, and a valid positive pair inside
     * the semester week window (session ≥ 1, week 1..14).
     *
     * @param  array<string, mixed>  $details
     */
    public static function isUserCancellationAudit(string $action, array $details): bool
    {
        if ($action !== self::AUDIT_CANCELLATION || self::isMaintenanceDetails($details)) {
            return false;
        }

        $pair = self::pairFromDetails($details);

        return $pair !== null
            && $pair['week'] >= self::WEEK_MIN
            && $pair['week'] <= self::WEEK_MAX;
    }

    /**
     * A positive integer in whatever shape the JSON decode produced.
     */
    private static function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value != $value) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
