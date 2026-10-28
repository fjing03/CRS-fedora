<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\User;
use App\Support\Notifications\NotificationEmitter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

        try {
            DB::transaction(function () use ($session, $user, $weekNumber, $reason, $otherDetail): void {
                ClassException::create([
                    'class_session_id' => $session->id,
                    'week_number' => $weekNumber,
                    'reason' => $reason,
                ]);

                $details = [
                    'class_session_id' => (int) $session->id,
                    'week_number' => $weekNumber,
                    'reason' => $reason,
                ];
                if ($otherDetail !== null && $otherDetail !== '') {
                    $details['other_detail'] = $otherDetail;
                }

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'class_cancelled',
                    'details' => $details,
                ]);

                /* SDD notifications-real-bridge: the THREE cancelled fan-outs
                   (the class's lecturer + the cohort students + the
                   department's PLs) — the emitter adds its own afterCommit
                   closure here (this Action queues no mail of its own). */
                DB::afterCommit(function () use ($user, $session, $weekNumber): void {
                    app(NotificationEmitter::class)
                        ->classCancelled($user, $session, $weekNumber);
                });
            });
        } catch (QueryException) {
            // Unique (class_session_id, week_number) or CHECK violation.
            return false;
        }

        return true;
    }
}
