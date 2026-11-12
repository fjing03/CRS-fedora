<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Holiday;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\Notifications\NotificationEmitter;
use App\Support\WorkingHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a SET of class occurrences as ONE decision (SDD cancel-whole-day, T2).
 *
 * The user's rule (2026-11-11): a lecturer who is away for a day cancels the day,
 * not four separate classes. Because that one action can also withdraw requests
 * students were waiting on, it is deliberately strict:
 *
 *   · every item is re-checked BEFORE anything is written — owner, the 1–14 week
 *     range, the 2-working-day window, the holiday/Sunday ban, the reason, and
 *     "no exception for this occurrence yet";
 *   · ONE transaction — a single failure writes NOTHING (all-or-nothing);
 *   · a pending or approved request on a cancelled occurrence is CLOSED in the
 *     same transaction (status `cancelled`, `decided_at`, slots released through
 *     ReleaseRequestSlots, junction rows deleted) — the parked bulk-cancel rule,
 *     because a request pointing at a class that no longer runs is a lie;
 *   · one audit row per cancelled class;
 *   · notifications AFTER commit, one fan-out per class, using the existing
 *     `classCancelled` emitter — no new wording, no mail.
 *
 * The actions' own rules stay here, never on the page (design F2).
 */
final class CancelClassBatch
{
    /**
     * @param  array<int, array{session: ClassSession, week: int}>  $items
     * @return array{ok: bool, refused: string|null, hint: string|null, cancelled: array<int, array{session_id: int, week: int, exception_id: int}>, requestsClosed: int}
     */
    public function __invoke(
        User $user,
        array $items,
        string $reason,
        ?string $otherDetail = null,
        bool $enforceWindow = true,
    ): array {
        if ($items === []) {
            return $this->refuse('empty', 'Nothing was selected to cancel.');
        }

        if (! CancelClass::validReason($reason)) {
            return $this->refuse('invalid_reason', 'Please select a cancellation reason.');
        }

        if ($reason === 'other' && trim((string) $otherDetail) === '') {
            return $this->refuse('invalid_reason', 'Please describe the reason for “Other”.');
        }

        foreach ($items as $item) {
            $refusal = $this->refusalFor($user, $item['session'], (int) $item['week'], $reason, $enforceWindow);

            if ($refusal !== null) {
                return $refusal;
            }
        }

        $cancelled = [];
        $requestsClosed = 0;

        DB::transaction(function () use ($user, $items, $reason, $otherDetail, &$cancelled, &$requestsClosed): void {
            foreach ($items as $item) {
                /** @var ClassSession $session */
                $session = $item['session'];
                $week = (int) $item['week'];

                $details = [
                    'class_session_id' => (int) $session->id,
                    'week_number' => $week,
                    'reason' => $reason,
                ];
                if ($reason === 'other' && trim((string) $otherDetail) !== '') {
                    $details['other_detail'] = trim((string) $otherDetail);
                }

                // the closure first: closing the request deletes its junction rows
                $request = ReplacementRequest::query()
                    ->where('class_session_id', (int) $session->id)
                    ->where('week_number', $week)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->first();

                if ($request !== null) {
                    (new ReleaseRequestSlots)($request, false);

                    $request->fill([
                        'status' => 'cancelled',
                        'decided_at' => now(),
                    ]);
                    $request->save();

                    AuditLog::create([
                        'user_id' => (int) $user->id,
                        'action' => 'cancelled',
                        'replacement_request_id' => $request->id,
                        'time_slot_id' => (int) $request->replacement_time_slot_id,
                        'old_status' => 'pending',
                        'new_status' => null,
                        'details' => ['cancelled_by' => 'class_cancelled', 'reason' => $reason],
                    ]);

                    $details['request_id'] = (int) $request->id;
                    $details['closed_request'] = true;
                    $requestsClosed++;
                }

                $exception = ClassException::create([
                    'class_session_id' => (int) $session->id,
                    'week_number' => $week,
                    'reason' => $reason,
                ]);

                AuditLog::create([
                    'user_id' => (int) $user->id,
                    'action' => 'class_cancelled',
                    'details' => $details,
                ]);

                $cancelled[] = [
                    'session_id' => (int) $session->id,
                    'week' => $week,
                    'exception_id' => (int) $exception->id,
                ];
            }
        });

        // one fan-out per cancelled class — the existing wording, nothing new
        DB::afterCommit(function () use ($user, $items): void {
            $emitter = app(NotificationEmitter::class);

            foreach ($items as $item) {
                $emitter->classCancelled($user, $item['session'], (int) $item['week']);
            }
        });

        return [
            'ok' => true,
            'refused' => null,
            'hint' => null,
            'cancelled' => $cancelled,
            'requestsClosed' => $requestsClosed,
        ];
    }

    /** @return array{ok: false, refused: string, hint: string, cancelled: array<int, mixed>, requestsClosed: int}|null */
    private function refusalFor(User $user, ClassSession $session, int $week, string $reason, bool $enforceWindow): ?array
    {
        if ((int) $session->lecturer_id !== (int) $user->id) {
            return $this->refuse('not_owner', 'One of these classes is not yours to cancel.');
        }

        if ($week < 1 || $week > 14) {
            return $this->refuse('invalid_week', 'That week is outside the semester.');
        }

        if (! CancelClass::validReason($reason)) {
            return $this->refuse('invalid_reason', 'Please select a cancellation reason.');
        }

        $semester = Semester::query()->find((int) $session->semester_id);
        $classDate = $this->classDate($semester, $week, (int) $session->day_of_week);

        if ($classDate === null) {
            return $this->refuse('invalid_week', 'That class does not have a date in this semester.');
        }

        /* The window is the DAY path's guard. The single-class flow never had a
           server-side window (the page has always guarded it), so it passes
           `false` here — keeping `CancelClass`'s shipped behaviour intact
           (spec R7.1). */
        if ($enforceWindow && $classDate->lt($this->windowCutoff($semester))) {
            return $this->refuse('too_old', 'One of these classes is more than 2 working days ago.');
        }

        if ($this->isHoliday((int) $session->semester_id, $week, (int) $session->day_of_week)) {
            return $this->refuse('holiday', 'One of these classes falls on a holiday or a Sunday.');
        }

        $exists = ClassException::query()
            ->where('class_session_id', (int) $session->id)
            ->where('week_number', $week)
            ->exists();

        if ($exists) {
            return $this->refuse('already_cancelled', 'One of these classes is already cancelled.');
        }

        return null;
    }

    /** The occurrence's real date: semester start + (week − 1) weeks + the weekday. */
    private function classDate(?Semester $semester, int $week, int $dayOfWeek): ?Carbon
    {
        if ($semester === null || trim((string) $semester->start_date) === '') {
            return null;
        }

        return Carbon::parse((string) $semester->start_date)->startOfDay()
            ->addWeeks($week - 1)
            ->addDays($dayOfWeek);
    }

    /**
     * Midnight of (today − 2 WORKING days) — the server mirror of the page's
     * `ClassCancellation._cutoffDate()` (weekends and semester holidays skipped).
     */
    private function windowCutoff(?Semester $semester): Carbon
    {
        $calendar = new WorkingHours($semester);
        $cursor = LocalTime::today()->startOfDay();
        $counted = 0;

        for ($guard = 0; $guard < 60; $guard++) {
            $cursor = $cursor->copy()->subDay();

            if (! $calendar->isWorkingDay($cursor)) {
                continue;
            }

            if (++$counted >= 2) {
                return $cursor;
            }
        }

        return $cursor;
    }

    private function isHoliday(int $semesterId, int $week, int $dayOfWeek): bool
    {
        return Holiday::query()
            ->where('semester_id', $semesterId)
            ->where('week_number', $week)
            ->where('day_of_week', $dayOfWeek)
            ->exists();
    }

    /** @return array{ok: false, refused: string, hint: string, cancelled: array<int, mixed>, requestsClosed: int} */
    private function refuse(string $key, string $hint): array
    {
        return ['ok' => false, 'refused' => $key, 'hint' => $hint, 'cancelled' => [], 'requestsClosed' => 0];
    }
}
