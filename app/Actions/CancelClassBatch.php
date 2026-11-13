<?php

namespace App\Actions;

use App\Exceptions\OccurrenceWriteLockContended;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Holiday;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\OccurrenceMutationLocks;
use App\Services\OriginalOccurrencePlan;
use App\Support\LocalTime;
use App\Support\Notifications\NotificationEmitter;
use App\Support\WorkingHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a SET of class occurrences as ONE decision (SDD cancel-whole-day, T2;
 * SDD cancelled-slot-reuse A05/A06 — original-slot release integrated).
 *
 * The user's rule (2026-11-11): a lecturer who is away for a day cancels the day,
 * not four separate classes. Because that one action can also withdraw requests
 * students were waiting on, it is deliberately strict:
 *
 *   · every item is re-checked BEFORE anything is written — owner, the 1–14 week
 *     range, the 2-working-day window, the holiday/Sunday ban, the reason, and
 *     "no exception for this occurrence yet" — first cheaply, then AGAIN under
 *     the locks, so the locked truth is what is cancelled;
 *   · ONE transaction: the complete mutation union (advisory locks, source
 *     sessions, attached requests and the original + landing slot union) is
 *     locked FIRST through {@see OccurrenceMutationLocks}, in D4 order;
 *   · EVERY selected occurrence's original cells are classified BEFORE any
 *     flip or closure — a missing or ambiguous cell refuses the WHOLE batch;
 *   · a pending or approved request on a cancelled occurrence is CLOSED in the
 *     same transaction (status `cancelled`, `decided_at`) and ONLY its proven
 *     landing cells are freed through `ReleaseRequestSlots`' explicit
 *     cancellation-only policy — a newer holder is never released; the flips
 *     ride a truthful `attached_release` receipt inside the `class_cancelled`
 *     audit (junction ids captured before the delete, per cell the ACTUAL old
 *     state/version/owner → available, owner pointer cleared);
 *   · the original cells are released through `ReleaseOriginalOccurrenceSlots`
 *     with the locked, freshly re-planned plan; the receipt rides the existing
 *     legal `class_cancelled` audit (`original_release`) — no new audit kind;
 *   · one audit row per cancelled class;
 *   · notifications AFTER the OUTERMOST commit, one fan-out per class — a
 *     rollback sends none.
 */
final class CancelClassBatch
{
    /**
     * @param  list<array{session: ClassSession, week: int}>  $items
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
        $refusal = null;

        try {
            DB::transaction(function () use ($user, $items, $reason, $otherDetail, $enforceWindow, &$cancelled, &$requestsClosed, &$refusal): void {
                // A05 — the complete union FIRST, then everything re-read under it.
                $plans = app(OccurrenceMutationLocks::class)->lock($items);

                $freshSessions = ClassSession::query()
                    ->whereIn('id', array_map(fn (array $item): int => (int) $item['session']->id, $items))
                    ->get()
                    ->keyBy('id');

                // Re-validate EVERY item under the locks, and classify EVERY
                // occurrence's original cells, before any flip or closure.
                $classified = [];

                foreach ($items as $item) {
                    $session = $freshSessions->get((int) $item['session']->id);
                    $week = (int) $item['week'];

                    if ($session === null) {
                        $refusal = $this->refuse('refresh', 'One of these classes no longer exists — please refresh and try again.');

                        return;
                    }

                    $refusal = $this->refusalFor($user, $session, $week, $reason, $enforceWindow);

                    if ($refusal !== null) {
                        return;
                    }

                    $plan = $plans[$session->id.':'.$week] ?? null;

                    if (! $plan instanceof OriginalOccurrencePlan || $plan->hasRefusal()) {
                        $refusal = $this->refuse('original_release', $this->releaseHint($plan));

                        return;
                    }

                    // the attached request is captured BEFORE any link is
                    // deleted — more than one live claim on one occurrence is
                    // corrupted data the planner may not guess its way out of
                    $attachedRequests = ReplacementRequest::query()
                        ->where('class_session_id', (int) $session->id)
                        ->where('week_number', $week)
                        ->whereIn('status', ['pending', 'approved'])
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($attachedRequests->count() > 1) {
                        $refusal = $this->refuse('attached_ambiguous',
                            'This class holds more than one active replacement request for this week — nothing was changed. Please contact the administrator.');

                        return;
                    }

                    $request = $attachedRequests->first();

                    $classified[] = ['session' => $session, 'week' => $week, 'plan' => $plan, 'request' => $request];
                }

                // Phase B — flips and closures, all-or-nothing.
                foreach ($classified as $entry) {
                    /** @var ClassSession $session */
                    $session = $entry['session'];
                    $week = (int) $entry['week'];
                    /** @var OriginalOccurrencePlan $plan */
                    $plan = $entry['plan'];
                    /** @var ReplacementRequest|null $request */
                    $request = $entry['request'];

                    $details = [
                        'class_session_id' => (int) $session->id,
                        'week_number' => $week,
                        'reason' => $reason,
                    ];
                    if ($reason === 'other' && trim((string) $otherDetail) !== '') {
                        $details['other_detail'] = trim((string) $otherDetail);
                    }

                    // the closure first: closing the request deletes its junction rows,
                    // so the plan's classifications were captured above, before this
                    if ($request !== null) {
                        $oldStatus = (string) $request->status;

                        // the junction receipt's ids, captured BEFORE the
                        // release deletes them — the audit must name what it
                        // removed
                        $junctionSlotIds = ReplacementRequestSlot::query()
                            ->where('replacement_request_id', (int) $request->id)
                            ->orderBy('time_slot_id')
                            ->pluck('time_slot_id')
                            ->map(fn ($id): int => (int) $id)
                            ->values()
                            ->all();

                        $attachedRelease = [];

                        (new ReleaseRequestSlots)($request, false, function (TimeSlot $slot) use (&$attachedRelease): void {
                            // the model still holds the PRE-flip row (the flip
                            // is a version-checked raw UPDATE), so the receipt
                            // states the truth: the ACTUAL old state and the
                            // owner pointer the flip cleared
                            $attachedRelease[] = [
                                'slot_id' => (int) $slot->id,
                                'week_number' => (int) $slot->week_number,
                                'day_of_week' => (int) $slot->day_of_week,
                                'venue_id' => (int) $slot->venue_id,
                                'start_time' => (string) $slot->start_time,
                                'end_time' => (string) $slot->end_time,
                                'before' => [
                                    'status' => (string) $slot->status,
                                    'class_session_id' => $slot->class_session_id !== null ? (int) $slot->class_session_id : null,
                                    'version' => (int) $slot->version,
                                ],
                                'after' => [
                                    'status' => 'available',
                                    'class_session_id' => null,
                                    'version' => (int) $slot->version + 1,
                                ],
                            ];
                        }, true);

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
                            'old_status' => $oldStatus,
                            'new_status' => null,
                            'details' => ['cancelled_by' => 'class_cancelled', 'reason' => $reason],
                        ]);

                        $details['request_id'] = (int) $request->id;
                        $details['closed_request'] = true;
                        $details['closed_request_status'] = $oldStatus;
                        $details['attached_release'] = [
                            'request_id' => (int) $request->id,
                            'old_status' => $oldStatus,
                            'junction_slot_ids' => $junctionSlotIds,
                            'changed' => $attachedRelease,
                        ];
                        $requestsClosed++;
                    }

                    $exception = ClassException::create([
                        'class_session_id' => (int) $session->id,
                        'week_number' => $week,
                        'reason' => $reason,
                    ]);

                    // the original cells: available/protected cells ride the receipt as no-ops
                    $receipt = (new ReleaseOriginalOccurrenceSlots)($plan);

                    $details['original_release'] = [
                        'class_session_id' => (int) $session->id,
                        'week_number' => $week,
                        'changed' => $receipt->changed,
                        'no_ops' => $receipt->noOps,
                        'out_of_range' => $plan->outOfRange,
                    ];

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
        } catch (OccurrenceWriteLockContended) {
            return $this->refuse('refresh', 'Someone else is changing these classes right now — please try again.');
        } catch (OriginalOccurrenceReleaseException $e) {
            return $this->refuse('original_release', $this->releaseHint(null, $e->reason));
        } catch (ReleaseDriftException) {
            return $this->refuse('original_release',
                'A replacement block tied to these classes changed while cancelling — nothing was changed. Please try again.');
        }

        if ($refusal !== null) {
            // the batch refused under the locks — nothing was written, so no fan-out
            return $refusal;
        }

        // one fan-out per cancelled class — the existing wording, nothing new;
        // DB::afterCommit fires on the OUTERMOST commit (the day path's outer
        // transaction), so a rolled-back selection notifies nobody.
        // POST-COMMIT FAN-OUT IS BEST-EFFORT: the commit already stands, so a
        // failing emitter must never bubble back up through the day path's
        // catch as a false "Nothing changed" rollback claim — each class's
        // notification failure is logged and the fan-out continues.
        DB::afterCommit(function () use ($user, $items): void {
            foreach ($items as $item) {
                try {
                    app(NotificationEmitter::class)->classCancelled($user, $item['session'], (int) $item['week']);
                } catch (\Throwable $e) {
                    report($e);
                }
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

    /**
     * The helpful copy for an original-release refusal — the plan's own reason
     * when it carries one, else the exception's (`membership_drift` asks for a
     * refresh; stale flips say retry). Never a 500.
     */
    private function releaseHint(?OriginalOccurrencePlan $plan, ?string $exceptionReason = null): string
    {
        $reason = $exceptionReason ?? $plan?->refusalReason() ?? 'unavailable';

        if ($reason === 'membership_drift') {
            return 'These classes changed while you were cancelling — please refresh and try again.';
        }

        return 'One of the selected classes\' original slots could not be released safely ('
            .$reason.'). Nothing was changed — please try again or contact the administrator if this repeats.';
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
