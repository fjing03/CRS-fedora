<?php

namespace App\Concerns;

use App\Actions\CancelClass;
use App\Actions\CancelClassBatch;
use App\Actions\CancelPendingRequest;
use App\Actions\OriginalOccurrenceReleaseException;
use App\Exceptions\OccurrenceWriteLockContended;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Services\OccurrenceMutationLocks;
use App\Services\ReplacementRequestMapper;
use App\Support\CohortLabel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Renderless;
use RuntimeException;

/**
 * Bridge between the client Cancel Class flow and the CancelClass action
 * (class-cancel-db-persist). One shared trait for every page surface that
 * can cancel or undo — MyTimetable, CohortTimetable, VenueTimetable and
 * ReplacementHome — called via `window.Livewire.first().call(...)` (the
 * submitBlocks precedent: renderless array responses, no HTTP route).
 *
 * 2026-11-11 (SDD cancel-whole-day): the modal can now cancel a whole day, so
 * two more renderless methods live here — `myDayClasses()` (what the block and
 * its list print) and `cancelClassDay()` (the day as one decision). They live in
 * THIS trait so all four surfaces get them from one implementation, and so the
 * Cohort/Venue pages — which only carry one slice of the lecturer's day — can
 * still answer "my other classes that day".
 */
trait CancelsClasses
{
    /**
     * Cancel the caller's own occurrence for one week. Ownership-scoped
     * resolve: a fabricated id resolves to null. The server response is
     * the truth.
     *
     * SDD approved-replacement-cancellation (D1): with a `$requestId`, the
     * caller is cancelling an OWNED APPROVED replacement at its ACTUAL landing
     * slot — `$weekNumber`/`$dayIndex` are the LANDING coordinates and
     * `$sessionId` must be the request's SOURCE session. The batch's approved
     * path is the only executor; a missing/stale/foreign/mismatched target
     * refuses WITHOUT a normal fallback, and the server enforces the window
     * there. Legacy normal single cancellation is byte-identically unchanged.
     *
     * @return array{ok: bool, exceptionId?: int, cancelled?: array<int, array<string, mixed>>, requestsClosed?: int, hint?: string}
     */
    #[Renderless]
    public function cancelClass(string $sessionId, int $weekNumber, string $reason, ?string $otherDetail = null, ?int $requestId = null, ?int $dayIndex = null): array
    {
        if ($requestId !== null) {
            return $this->cancelApprovedLanding((int) $requestId, (int) $sessionId, (int) $weekNumber, $reason, $otherDetail, $dayIndex);
        }

        $session = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->find((int) $sessionId);
        if ($session === null) {
            return ['ok' => false, 'hint' => 'Class not found among your classes.'];
        }

        if (! CancelClass::validReason($reason)) {
            return ['ok' => false, 'hint' => 'Please select a cancellation reason.'];
        }

        $exceptionId = (new CancelClass)->cancelWithReceipt(auth()->user(), $session, $weekNumber, $reason, $otherDetail);
        if ($exceptionId === null) {
            return ['ok' => false, 'hint' => 'This class is already cancelled.'];
        }

        return ['ok' => true, 'exceptionId' => $exceptionId];
    }

    /**
     * The approved branch of `cancelClass()` (SDD approved-replacement-cancellation,
     * D1): resolve the exact OWNED APPROVED request for the shown LANDING
     * coordinate, verify its SOURCE session, and hand it to the batch's
     * approved path — the only executor. The batch re-validates every gate
     * fresh under the locks (owner, state, semester, coordinate, proven claim,
     * landing window/holiday/Sunday) and writes the receipt/audit.
     *
     * @return array{ok: bool, cancelled: array<int, array<string, mixed>>, requestsClosed: int, hint?: string}
     */
    private function cancelApprovedLanding(int $requestId, int $sessionId, int $weekNumber, string $reason, ?string $otherDetail, ?int $dayIndex): array
    {
        $refusal = fn (string $hint): array => ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'hint' => $hint];

        if ($dayIndex === null || $dayIndex < 0 || $dayIndex > 6 || $weekNumber < 1 || $weekNumber > 14) {
            return $refusal('That replacement slot is outside the semester.');
        }

        if (! CancelClass::validReason($reason)) {
            return $refusal('Please select a cancellation reason.');
        }

        $request = ReplacementRequest::query()->with('classSession')->find($requestId);

        if ($request === null
            || (int) $request->proposer_id !== (int) auth()->id()
            || (string) $request->status !== 'approved') {
            return $refusal('That replacement is not an approved request of yours.');
        }

        $session = $request->classSession;

        if ($session === null
            || (int) $session->id !== $sessionId
            || (int) $session->lecturer_id !== (int) auth()->id()) {
            return $refusal('That replacement does not belong to this class.');
        }

        $semester = Semester::active();

        if ($semester === null || (int) $request->semester_id !== (int) $semester->id) {
            return $refusal('That replacement does not belong to the active semester.');
        }

        // the shown coordinate must BE the request's actual landing slot —
        // never inferred, never silently corrected (D1)
        $span = (new ReplacementRequestMapper)->span($request);

        if ($span === null || (int) $span['week'] !== $weekNumber || (int) $span['day'] !== $dayIndex) {
            return $refusal('That replacement no longer runs at the shown time — please refresh and try again.');
        }

        $result = (new CancelClassBatch)(auth()->user(), [], $reason, $otherDetail, true, [
            ['request_id' => (int) $request->id, 'week' => $weekNumber, 'day' => $dayIndex],
        ]);

        if (! $result['ok']) {
            return $refusal((string) $result['hint']);
        }

        return [
            'ok' => true,
            'cancelled' => (array) $result['cancelled'],
            'requestsClosed' => (int) $result['requestsClosed'],
        ];
    }

    /**
     * The caller's own classes on one date of one week — the whole-day block's
     * source of truth (SDD cancel-whole-day, T3).
     *
     * The set is ownership-scoped, so a fabricated week/day can never widen it,
     * and occurrences that already hold an exception (cancelled earlier, or moved
     * by an approved replacement) are excluded — they are already off the
     * timetable. The class the modal was opened for is INCLUDED; the modal drops
     * it and treats the rest as "other".
     *
     * @return array{ok: bool, date?: string, day?: string, classes: array<int, array<string, mixed>>, hint?: string}
     */
    #[Renderless]
    public function myDayClasses(int $weekNumber, int $dayIndex): array
    {
        $semester = Semester::active();

        if ($semester === null || $weekNumber < 1 || $weekNumber > 14 || $dayIndex < 0 || $dayIndex > 6) {
            return ['ok' => false, 'classes' => [], 'hint' => 'That day is outside the semester.'];
        }

        $classes = $this->dayClasses($semester, $weekNumber, $dayIndex);

        /* 2026-11-11 (user, bug report): the grid also paints a class's PENDING or
           APPROVED make-up block in the week the make-up falls in. Such a block is
           NOT a class timetabled that day — its occurrence lives in another week —
           so it is reported by name and never tickable: a tick there would cancel a
           class from a different week (the parked bulk lane's wrong-week hazard). */
        $makeUps = $this->makeUpsOnDay($semester, $weekNumber, $dayIndex);

        $date = Carbon::parse((string) $semester->start_date)
            ->startOfDay()
            ->addWeeks($weekNumber - 1)
            ->addDays($dayIndex);

        return [
            'ok' => true,
            'date' => $date->toDateString(),
            'day' => $date->format('l'),
            'classes' => $classes,
            'makeUps' => $makeUps,
        ];
    }

    /**
     * Cancel the caller's whole day as ONE decision (SDD cancel-whole-day, T3).
     *
     * The set is resolved HERE again — the client's list is display only, so a
     * tampered page cannot widen what a tick cancels. The action owns every rule;
     * this method only translates its refusal into the modal's hint.
     *
     * @param  array<int, int|string>  $requestIds  the ticked make-up rows (request ids)
     * @param  array<int, int|string>  $sessionIds  the classes to cancel: the opened one + the ticked rows
     * @param  array<int, int|string>  $approvedRequestIds  the opened APPROVED replacement(s) landing on this day (request ids; SDD approved-replacement-cancellation D1 — the opened replacement contributes ONLY here, never to `$sessionIds`)
     * @return array{ok: bool, cancelled: array<int, array<string, mixed>>, requestsClosed: int, requestsWithdrawn: int, hint?: string}
     */
    #[Renderless]
    public function cancelClassDay(int $weekNumber, int $dayIndex, string $reason, ?string $otherDetail = null, array $requestIds = [], array $sessionIds = [], array $approvedRequestIds = []): array
    {
        $semester = Semester::active();

        if ($semester === null) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'The semester could not be read.'];
        }

        /* D1 (frozen): the reason gates run for EVERY shape of the day decision —
           a pending-only or approved-only selection still writes an audit trail,
           so an invalid reason or a blank “Other” detail refuses the WHOLE day
           before anything resolves; no withdrawals ride on an invalid reason.
           The normal single-class path keeps its own shipped flow untouched. */
        if (! CancelClass::validReason($reason)) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'Please select a cancellation reason.'];
        }

        if ($reason === 'other' && trim((string) $otherDetail) === '') {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'Please describe the reason for “Other”.'];
        }

        $classes = $this->dayClasses($semester, $weekNumber, $dayIndex);

        /* 2026-11-11 (BUG, user report): the day read returns the WHOLE day, but
           only the ticked rows may be cancelled — an unticked class must survive.
           SDD approved-replacement-cancellation (D1): the submitted normal ids
           are normalized, must resolve EXACTLY against the day's classes, and
           an empty `$sessionIds` beside a non-empty request list means NO
           normal classes — the old all-lists-empty shape keeps its
           backward-compatible whole-day reading. */
        $sessionIds = array_values(array_unique(array_map('intval', $sessionIds)));
        $requestIds = array_values(array_unique(array_map('intval', $requestIds)));
        $approvedRequestIds = array_values(array_unique(array_map('intval', $approvedRequestIds)));

        /* one request is never selected in two roles — a contradiction, never a
           double close (SDD approved-replacement-cancellation D1) */
        if ($requestIds !== [] && $approvedRequestIds !== []
            && array_intersect($requestIds, $approvedRequestIds) !== []) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'One of those requests was selected both as a make-up note and as a replacement to cancel.'];
        }

        if ($sessionIds !== []) {
            /* EXACT cardinality — every submitted normal id must be one of the
               day's classes; a fabricated/stale id refuses the WHOLE selection,
               it is never silently filtered away. */
            $dayIds = array_map(fn (array $c): int => (int) $c['sessionId'], $classes);

            if (array_diff($sessionIds, $dayIds) !== []) {
                return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                    'hint' => 'One of those classes is not part of that day.'];
            }

            $classes = array_values(array_filter(
                $classes,
                fn (array $c): bool => in_array((int) $c['sessionId'], $sessionIds, true),
            ));
        } elseif ($requestIds !== [] || $approvedRequestIds !== []) {
            /* an explicit request-only/approved-only selection: ticked make-ups
               withdraw and owned approved replacements cancel, but the classes
               are NOT silently cancelled with them — the old both-lists-empty
               shape keeps its backward-compatible whole-day reading, this one
               must not regress into a hidden day cancel. */
            $classes = [];
        }

        /* 2026-11-11 (user): a ticked MAKE-UP row withdraws its request only — the
           class it was arranged for is untouched. Ownership + pending state are
           re-checked here, never trusted from the page. */
        $withdrawable = $requestIds === [] ? [] : array_values(ReplacementRequest::query()
            ->whereIn('id', $requestIds)
            ->where('proposer_id', auth()->id())
            ->where('status', 'pending')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());

        /* every requested id must be owned + pending — an all-invalid list is
           refused too, never silently treated as an empty selection */
        if (count($withdrawable) !== count($requestIds)) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'One of those requests is not yours to withdraw any more.'];
        }

        /* SDD approved-replacement-cancellation (D1): the approved list carries
           ONLY owned APPROVED replacements whose ACTUAL landing is the shown
           day — the opened one plus, if ever, another request of the same day.
           Their landing coordinates are derived from the validated mapper span,
           never trusted from the page; the batch re-validates everything under
           the locks. */
        $approvedTargets = [];
        $mapper = new ReplacementRequestMapper;

        foreach ($approvedRequestIds as $id) {
            $request = ReplacementRequest::query()->find($id);

            if ($request === null
                || (int) $request->proposer_id !== (int) auth()->id()
                || (string) $request->status !== 'approved'
                || (int) $request->semester_id !== (int) $semester->id) {
                return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                    'hint' => 'One of those replacements is not an approved request of yours any more.'];
            }

            $span = $mapper->span($request);

            if ($span === null || (int) $span['week'] !== $weekNumber || (int) $span['day'] !== $dayIndex) {
                return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                    'hint' => 'One of those replacements does not land on that day.'];
            }

            $approvedTargets[] = ['request_id' => $id, 'week' => (int) $span['week'], 'day' => (int) $span['day']];
        }

        /* a pending withdrawal may never double as an occurrence closure: a
           request attached to one of the selected classes is closed BY that
           cancellation, so selecting it as a withdrawal too is contradictory
           (never close twice — SDD approved-replacement-cancellation R2) */
        if ($withdrawable !== [] && $classes !== []) {
            $attachedIds = ReplacementRequest::query()
                ->whereIn('class_session_id', array_map(fn (array $c): int => (int) $c['sessionId'], $classes))
                ->where('week_number', $weekNumber)
                ->whereIn('status', ['pending', 'approved'])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if (array_intersect($withdrawable, $attachedIds) !== []) {
                return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                    'hint' => 'One of those make-up notes belongs to a class being cancelled.'];
            }
        }

        if ($classes === [] && $withdrawable === [] && $approvedTargets === []) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'There is nothing to cancel that day.'];
        }

        $sessions = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->whereIn('id', array_map(fn (array $c): int => (int) $c['sessionId'], $classes))
            ->get()
            ->keyBy('id');

        /* every selected class must still resolve to an OWNED session — one that
           vanished or changed ownership refuses the WHOLE selection; it is never
           silently dropped from the items while the rest cancels (D1) */
        if ($sessions->count() !== count($classes)) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'One of those classes is not yours to cancel any more — please refresh and try again.'];
        }

        $items = [];
        foreach ($classes as $class) {
            /** @var ClassSession $session */
            $session = $sessions->get((int) $class['sessionId']);
            $items[] = ['session' => $session, 'week' => $weekNumber];
        }

        /* SDD cancelled-slot-reuse A07 — the day is ONE decision: the selected
           class batch AND every ticked make-up withdrawal live in ONE outer
           transaction, and the complete union (source sessions, requests,
           original + landing cells) is locked through the shared helper BEFORE
           the nested actions run, so they only reuse already-held locks. A
           refused batch stops the withdrawals; a failed withdrawal rolls
           EVERYTHING back, afterCommit notifications included. */
        /** @var array<string, mixed>|null $result */
        $result = null;
        $withdrawn = 0;

        try {
            DB::transaction(function () use ($items, $withdrawable, $approvedTargets, $approvedRequestIds, $reason, $otherDetail, &$result, &$withdrawn): void {
                // the COMPLETE union locks FIRST — the pending withdrawals AND
                // the approved targets' requests join the recurring items (D2);
                // the nested batch reuses these already-held locks
                app(OccurrenceMutationLocks::class)->lock($items, array_merge($withdrawable, $approvedRequestIds));

                if ($items !== [] || $approvedTargets !== []) {
                    $result = (new CancelClassBatch)(auth()->user(), $items, $reason, $otherDetail, true, $approvedTargets);

                    if (! $result['ok']) {
                        return; // a refused batch writes nothing and stops the withdrawals
                    }
                }

                // a make-up row withdraws its own request (CancelPendingRequest owns that rule)
                foreach ($withdrawable as $id) {
                    if (! (new CancelPendingRequest)(auth()->user(), $id)) {
                        // raced/contradictory selection — the throw rolls back
                        // the batch too; the catch below answers the hint
                        throw new RuntimeException('withdrawal_refused');
                    }

                    $withdrawn++;
                }
            });
        } catch (OccurrenceWriteLockContended) {
            $result = ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'hint' => 'Someone else is changing these classes right now — please try again.'];
            $withdrawn = 0;
        } catch (OriginalOccurrenceReleaseException) {
            // the lock union drifted under us — a refresh, never the user's fault
            $result = ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'hint' => 'These classes changed while you were cancelling — please refresh and try again.'];
            $withdrawn = 0;
        } catch (RuntimeException $e) {
            /* ONLY the withdrawal action's own refusal marker is a withdrawal
               refusal — a PDO/audit RuntimeException is a day fault, not an
               authorization failure, and gets the honest generic copy. The
               message is compared, never logged or echoed (no secrets). */
            $result = ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0,
                'hint' => $e->getMessage() === 'withdrawal_refused'
                    ? 'One of those requests is not yours to withdraw any more.'
                    : 'The cancellation could not be completed — nothing was changed. Please try again.'];
            $withdrawn = 0;
        } catch (\Throwable) {
            // a DB or audit failure is NOT a withdrawal refusal — the day fault
            // must not lie about whose fault it was; nothing was written
            $result = ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'hint' => 'The cancellation could not be completed — nothing was changed. Please try again.'];
            $withdrawn = 0;
        }

        if ($result === null) {
            $result = ['ok' => true, 'cancelled' => [], 'requestsClosed' => 0, 'hint' => null];
        }

        return [
            'ok' => (bool) $result['ok'],
            'cancelled' => (array) $result['cancelled'],
            'requestsClosed' => (int) $result['requestsClosed'],
            'requestsWithdrawn' => $withdrawn,
            'hint' => isset($result['hint']) ? (string) $result['hint'] : null,
        ];
    }

    /**
     * The make-up blocks that FALL on this date but belong to another week's
     * occurrence (pending or approved requests of my own). Reported for honesty,
     * never tickable — see the note in `myDayClasses()`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function makeUpsOnDay(Semester $semester, int $weekNumber, int $dayIndex): array
    {
        return ReplacementRequest::query()
            ->with(['classSession.module', 'replacementTimeSlot.venue'])
            ->withCount('requestSlots as cells_count')
            ->where('proposer_id', auth()->id())
            ->where('semester_id', (int) $semester->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('week_number', '!=', $weekNumber)   // its own occurrence is another week
            ->whereHas('replacementTimeSlot', function ($q) use ($weekNumber, $dayIndex): void {
                $q->where('week_number', $weekNumber)->where('day_of_week', $dayIndex);
            })
            ->get()
            ->map(function (ReplacementRequest $r): ?array {
                $slot = $r->replacementTimeSlot;
                // The request and its eager-loaded slot are read separately.
                if ($slot === null) {
                    return null;
                }

                $session = $r->classSession;

                /* the block's REAL run: the anchor cell widened by the number of
                   cells the request holds (a 2-cell request is an hour, not 30 min) */
                $cells = max(1, (int) ($r->cells_count ?? 1));
                $endAt = Carbon::parse((string) $slot->start_time)->addMinutes(30 * $cells);

                return [
                    'requestId' => (int) $r->id,
                    'code' => (string) ($session->module->module_code ?? ''),
                    'type' => (string) ($session->session_type ?? ''),
                    'week' => (int) $r->week_number,
                    'start' => substr((string) $slot->start_time, 0, 5),
                    'end' => $endAt->format('H:i'),
                    'venue' => (string) ($slot->venue->room_code ?? ''),
                    'request' => (string) $r->status,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The shared set resolver both methods above use — one truth for "my classes
     * on that date", so the list the user sees and the list the server cancels can
     * never disagree about WHICH classes (the rules themselves are re-checked by
     * the action).
     *
     * @return array<int, array<string, mixed>>
     */
    private function dayClasses(Semester $semester, int $weekNumber, int $dayIndex): array
    {
        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->where('lecturer_id', auth()->id())
            ->where('semester_id', (int) $semester->id)
            ->where('day_of_week', $dayIndex)
            ->whereNotExists(function ($q) use ($weekNumber): void {
                $q->select(DB::raw('1'))
                    ->from('class_exceptions')
                    ->whereColumn('class_exceptions.class_session_id', 'class_sessions.id')
                    ->where('class_exceptions.week_number', $weekNumber);
            })
            ->orderBy('start_time')
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $requests = ReplacementRequest::query()
            ->whereIn('class_session_id', $sessions->pluck('id')->all())
            ->where('week_number', $weekNumber)
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('submitted_at')
            ->get()
            ->keyBy('class_session_id');

        return $sessions->map(function (ClassSession $session) use ($requests): array {
            /** @var ReplacementRequest|null $request */
            $request = $requests->get($session->id);

            return [
                'sessionId' => (int) $session->id,
                'start' => substr((string) $session->start_time, 0, 5),
                'end' => substr((string) $session->end_time, 0, 5),
                'code' => (string) ($session->module->module_code ?? ''),
                'type' => (string) $session->session_type,
                'venue' => (string) ($session->venue->room_code ?? ''),
                'cohorts' => $session->cohorts
                    ->map(fn ($c): string => CohortLabel::spaced($c))
                    ->values()
                    ->all(),
                /* the hover says the FULL name of the same cohort — one compact
                   code on the row, the spelling-out on the tip (user, 2026-11-11) */
                'cohortDetails' => $session->cohorts
                    ->map(fn ($c): array => [
                        'label' => CohortLabel::spaced($c),
                        'full' => CohortLabel::full($c),
                    ])
                    ->values()
                    ->all(),
                'students' => (int) $session->cohorts->sum('student_count'),
                'request' => $request === null ? 'none' : (string) $request->status,
            ];
        })->all();
    }
}
