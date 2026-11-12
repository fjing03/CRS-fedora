<?php

namespace App\Concerns;

use App\Actions\CancelClass;
use App\Actions\CancelClassBatch;
use App\Actions\CancelPendingRequest;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Support\CohortLabel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Renderless;

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
     * @return array{ok: bool, exceptionId?: int, hint?: string}
     */
    #[Renderless]
    public function cancelClass(string $sessionId, int $weekNumber, string $reason, ?string $otherDetail = null): array
    {
        $session = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->find((int) $sessionId);
        if ($session === null) {
            return ['ok' => false, 'hint' => 'Class not found among your classes.'];
        }

        if (! CancelClass::validReason($reason)) {
            return ['ok' => false, 'hint' => 'Please select a cancellation reason.'];
        }

        $cancelled = (new CancelClass)(auth()->user(), $session, $weekNumber, $reason, $otherDetail);
        if (! $cancelled) {
            return ['ok' => false, 'hint' => 'This class is already cancelled.'];
        }

        $exception = ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', (int) $weekNumber)
            ->first();

        return ['ok' => true, 'exceptionId' => (int) $exception->id];
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
     * @return array{ok: bool, cancelled: array<int, array<string, mixed>>, requestsClosed: int, requestsWithdrawn: int, hint?: string}
     */
    #[Renderless]
    public function cancelClassDay(int $weekNumber, int $dayIndex, string $reason, ?string $otherDetail = null, array $requestIds = []): array
    {
        $semester = Semester::active();

        if ($semester === null) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'The semester could not be read.'];
        }

        $classes = $this->dayClasses($semester, $weekNumber, $dayIndex);

        /* 2026-11-11 (user): a ticked MAKE-UP row withdraws its request only — the
           class it was arranged for is untouched. Ownership + pending state are
           re-checked here, never trusted from the page. */
        $requestIds = array_values(array_unique(array_map('intval', $requestIds)));
        $withdrawable = $requestIds === [] ? [] : ReplacementRequest::query()
            ->whereIn('id', $requestIds)
            ->where('proposer_id', auth()->id())
            ->where('status', 'pending')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($withdrawable !== [] && count($withdrawable) !== count($requestIds)) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'One of those requests is not yours to withdraw any more.'];
        }

        if ($classes === [] && $withdrawable === []) {
            return ['ok' => false, 'cancelled' => [], 'requestsClosed' => 0, 'requestsWithdrawn' => 0,
                'hint' => 'There is nothing to cancel that day.'];
        }

        $sessions = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->whereIn('id', array_map(fn (array $c): int => (int) $c['sessionId'], $classes))
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($classes as $class) {
            $session = $sessions->get((int) $class['sessionId']);
            if ($session !== null) {
                $items[] = ['session' => $session, 'week' => $weekNumber];
            }
        }

        $result = $items === []
            ? ['ok' => true, 'cancelled' => [], 'requestsClosed' => 0, 'hint' => null]
            : (new CancelClassBatch)(auth()->user(), $items, $reason, $otherDetail);

        // a make-up row withdraws its own request (CancelPendingRequest owns that rule)
        $withdrawn = 0;
        foreach ($withdrawable as $id) {
            if ((new CancelPendingRequest)(auth()->user(), $id)) {
                $withdrawn++;
            }
        }

        return [
            'ok' => $result['ok'],
            'cancelled' => $result['cancelled'],
            'requestsClosed' => $result['requestsClosed'],
            'requestsWithdrawn' => $withdrawn,
            'hint' => $result['hint'],
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
            ->map(function (ReplacementRequest $r): array {
                $slot = $r->replacementTimeSlot;

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
