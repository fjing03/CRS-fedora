<?php

namespace App\Actions;

use App\Models\ClassSession;
use App\Models\User;

/**
 * Cancel the caller's own scheduled class for one week (FR 2.16): a
 * class_exceptions row frees the original block (the engine reads exceptions)
 * and the cancellation is audited with the true cause.
 *
 * The reason must be one of the 10 schema CHECK values; the unique
 * (class_session_id, week_number) index enforces one exception per occurrence.
 */
final class CancelClass
{
    public const REASONS = [
        'public_holiday',
        'annual_leave',
        'medical_leave',
        'official_event',
        'emergency_leave',
        'hospitalisation_leave',
        'maternity_leave',
        'compassionate_leave',
        'paternity_leave',
        'other',
    ];

    public static function validReason(string $reason): bool
    {
        return in_array($reason, self::REASONS, true);
    }

    /**
     * @return bool false on ownership violation (FR 2.11), invalid week/reason,
     *              or a duplicate exception for the same occurrence
     */
    public function __invoke(User $user, ClassSession $session, int $weekNumber, string $reason, ?string $otherDetail = null): bool
    {
        if ((int) $session->lecturer_id !== (int) $user->id) {
            return false;
        }

        if ($weekNumber < 1 || $weekNumber > 14 || ! in_array($reason, self::REASONS, true)) {
            return false;
        }

        /* 2026-11-11 (SDD cancel-whole-day, T2): the ONE cancel path for both
           doorways. The single-class flow is this action with a one-item set, so
           it now also closes a request the occurrence holds — the same rule the
           day cancel follows, and the rule the parked bulk lane asked for
           ("cancelling a class cancels its request too"). */
        $result = (new CancelClassBatch)(
            $user,
            [['session' => $session, 'week' => $weekNumber]],
            $reason,
            $otherDetail,
            enforceWindow: false,   // the page's own S5 guard owns this for one class
        );

        return $result['ok'];
    }
}
