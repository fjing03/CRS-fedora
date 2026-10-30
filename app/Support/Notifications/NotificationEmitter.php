<?php

namespace App\Support\Notifications;

use App\Models\ClassSession;
use App\Models\Lecturer;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The ONE notification emitter (SDD notifications-real-bridge): every
 * event lands as fan-out rows (one per recipient) written AFTER COMMIT —
 * the same law the mails follow (never inside the transaction).
 *
 * The `type` column stores the FROZEN tile keys (theme.css .notif-tile-*:
 * submitted | awaiting | approved | rejected | update) — the
 * same-name-same-color canon: no new tile classes, no new glyphs.
 *
 * The `role_view` column is the AD-2 category key (pl | lecturer |
 * student); the link follows the per-role link law (students never link
 * the lecturer-gated cohort page).
 */
final class NotificationEmitter
{
    private const DAY_ABBRS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * Request submitted → every PL (the FR 4.15 recipient law: is_pl).
     */
    /** @param  Collection<int, User>  $pls */
    public function requestSubmitted(Collection $pls, string $moduleCode, string $moduleType, string $proposerName, int $requestId, int $week): void
    {
        $this->commit(function () use ($pls, $moduleCode, $moduleType, $proposerName, $requestId, $week): void {
            foreach ($pls as $pl) {
                $this->write(
                    $pl,
                    'pl',
                    'submitted',
                    'New replacement request',
                    $moduleCode.' ('.$moduleType.') · W'.$week.' · submitted by '.$proposerName,
                    '/request-approval-ui',
                    $requestId,
                    null,
                    $week,
                );
            }
        });
    }

    /**
     * Request decided → the proposer (the FR 2.13 recipient law).
     */
    public function requestDecided(User $proposer, string $decision, string $moduleCode, string $moduleType, int $requestId, int $week, string $deciderName, ?string $reason = null): void
    {
        $this->commit(function () use ($proposer, $decision, $moduleCode, $moduleType, $requestId, $week, $deciderName, $reason): void {
            $decision === 'approved'
                ? $body = $moduleCode.' ('.$moduleType.') · W'.$week.' · approved by '.$deciderName
                : $body = $moduleCode.' ('.$moduleType.') · W'.$week.' · rejected by '.$deciderName.($reason !== null ? ' · '.$reason : '');
            $this->write(
                $proposer,
                'lecturer',
                $decision,
                $decision === 'approved' ? 'Replacement approved' : 'Replacement rejected',
                $body,
                '/my-request-history-ui',
                $requestId,
                null,
                $week,
            );
        });
    }

    /**
     * Approval noticed → every cohort student (the FR 1.9 recipient law —
     * the same resolution the cohort mail uses).
     */
    /** @param  Collection<int, Student>  $students */
    public function approvalNotice(ClassSession $session, Collection $students, int $requestId, int $week): void
    {
        $this->commit(function () use ($session, $students, $requestId, $week): void {
            foreach ($students as $student) {
                $user = $student->user;
                if ($user === null) {
                    continue;
                }
                $this->write(
                    $user,
                    'student',
                    'approved',
                    'Replacement class updated',
                    $this->classFact($session, $week).' · your timetable is updated',
                    '/student-my-timetable-ui',
                    $requestId,
                    (int) $session->id,
                    $week,
                );
            }
        });
    }

    /**
     * Scheduled class cancelled → the THREE fan-outs (the user's clarified
     * ruling, the 5770/ABCD6767 example): the class's own lecturer (the
     * actor keeps a record row), every cohort student, and the PLs of the
     * class owner's department (DEPT-ONLY — the owner's lecturers.dept_id).
     */
    public function classCancelled(User $actor, ClassSession $session, int $weekNumber): void
    {
        $this->commit(function () use ($actor, $session, $weekNumber): void {
            $title = 'Scheduled class cancelled';
            $body = $this->classFact($session, $weekNumber).' · cancelled';
            $sessionId = (int) $session->id;

            // 1. the class's own lecturer (the actor's record row)
            $this->write($actor, 'lecturer', 'update', $title, $body, '/cohort-timetable', null, $sessionId, $weekNumber);

            // 2. every cohort student
            foreach ($session->cohorts as $cohort) {
                foreach ($cohort->students()->with('user')->get() as $student) {
                    $user = $student->user;
                    if ($user === null) {
                        continue;
                    }
                    $this->write($user, 'student', 'update', $title, $body, '/student-my-timetable-ui', null, $sessionId, $weekNumber);
                }
            }

            // 3. the PLs of the class owner's department (DEPT-ONLY)
            $deptId = Lecturer::query()->where('user_id', (int) $session->lecturer_id)->value('dept_id');
            if ($deptId !== null) {
                $pls = User::query()
                    ->whereHas('lecturer', fn ($q) => $q->where('is_pl', true)->where('dept_id', $deptId))
                    ->get();
                foreach ($pls as $pl) {
                    /* notif-actor-pl-dedupe (the user's F1, 2026-10-30): the
                       OWNER already received their actor record row in step
                       1 — the dept-PL copy would be the same event twice in
                       the unified one-feed panel. */
                    if ((int) $pl->id === (int) $actor->id) {
                        continue;
                    }
                    $this->write($pl, 'pl', 'update', $title, $body, '/venue-timetable-ui', null, $sessionId, $weekNumber);
                }
            }
        });
    }

    /**
     * Pending-request reminder → every PL (24 h, deduped until read —
     * the reminder command's law: an UNREAD `awaiting` row for the same
     * (user, request) skips; a read one re-arms on the next run).
     */
    /** @param  Collection<int, User>  $pls */
    public function pendingReminder(Collection $pls, string $moduleCode, string $moduleType, string $proposerName, int $requestId, int $week): void
    {
        $this->commit(function () use ($pls, $moduleCode, $moduleType, $proposerName, $requestId, $week): void {
            foreach ($pls as $pl) {
                $exists = Notification::query()
                    ->where('user_id', (int) $pl->id)
                    ->where('request_id', $requestId)
                    ->where('type', 'awaiting')
                    ->whereNull('read_at')
                    ->exists();
                if ($exists) {
                    continue;
                }
                $this->write(
                    $pl,
                    'pl',
                    'awaiting',
                    'Approval still pending',
                    $moduleCode.' ('.$moduleType.') · W'.$week.' · submitted by '.$proposerName.' · awaiting your decision',
                    '/request-approval-ui',
                    $requestId,
                    null,
                    $week,
                );
            }
        });
    }

    /**
     * The write primitive — ALWAYS after commit (the mails' law).
     */
    private function write(User $to, string $roleView, string $type, string $title, string $body, string $link, ?int $requestId, ?int $classSessionId, ?int $week): void
    {
        Notification::create([
            'user_id' => (int) $to->id,
            'role_view' => $roleView,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'request_id' => $requestId,
            'class_session_id' => $classSessionId,
            'week' => $week,
        ]);
    }

    /**
     * The afterCommit wrapper (the mails' law — never inside the txn).
     */
    private function commit(\Closure $rows): void
    {
        DB::afterCommit($rows);
    }

    private function classFact(ClassSession $session, int $weekNumber): string
    {
        $code = (string) ($session->module->module_code ?? '');
        $type = (string) $session->session_type;
        $venue = (string) ($session->venue->room_code ?? '');
        $abbr = self::DAY_ABBRS[(int) $session->day_of_week] ?? 'Day';

        return $code.' ('.$type.') · W'.$weekNumber.' · '.$abbr.' · '.$venue;
    }
}
