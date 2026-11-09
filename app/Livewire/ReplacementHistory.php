<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Services\ReplacementRequestMapper;
use App\Support\LocalTime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Class Replacements (student) — the student's own view of every CONFLICTED class
 * occurrence and where its replacement stands. Read-only: students never act on a
 * request (RBAC), and this page adds no write path of any kind.
 *
 * 2026-11-09 (SDD `student-class-replacements`, eight user rulings): the page
 * stopped listing REQUESTS and started listing OCCURRENCES — a session × week
 * that is conflicted by a `class_exceptions` row (the lecturer cancelled it) or by
 * a calendar holiday, whether or not a request exists. That is the staff page's
 * model, minus every action: a class nobody has arranged yet is exactly what a
 * student most needs to see, and it used to be invisible.
 *
 * Eight user rulings, applied here:
 *  - rows = cancelled class **or** holiday, request or not (R1);
 *  - no request / cancelled request / rejected request all read
 *    `Awaiting Arrangement` — the page never mentions a dead request (R2);
 *  - the week filter keys on the CLASS's week (R3);
 *  - `Exclude COMPLETED Status` is decided by the make-up date (R4);
 *  - the page is Class Replacements at /student-class-replacements (R5);
 *  - read-only, with one jump button in the pop-up (R6);
 *  - five cards, `Class Hours Affected` last (R7, R8).
 */
class ReplacementHistory extends Component
{
    use ResolvesTimetableTimeline;

    private const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function render(): View
    {
        $semester = $this->timelineSemester();
        $semesterId = $semester !== null ? $semester->id : 1;
        $startDate = Carbon::parse($semester !== null ? $semester->start_date : LocalTime::today());
        $weeks = $this->timelineWeeks();

        $student = Student::query()->where('user_id', auth()->id())->first();
        $cohort = $student?->cohort;
        $cohortLabel = $cohort !== null ? $this->cohortCode($cohort) : null;

        $rows = [];
        if ($cohort !== null) {
            /* One read per input, none per row (design §1): my classes, the
               exceptions that cancelled them, the calendar, the latest request per
               occurrence, its span, and the rooms those spans point at. */
            $sessions = ClassSession::query()
                ->with(['module', 'venue', 'lecturer'])
                ->where('semester_id', $semesterId)
                ->whereHas('cohorts', fn ($q) => $q->whereKey($cohort->id))
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            $sessionIds = $sessions->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $exceptions = $sessionIds === []
                ? collect()
                : ClassException::query()
                    ->whereIn('class_session_id', $sessionIds)
                    ->get()
                    ->keyBy(fn (ClassException $e): string => $e->class_session_id.':'.$e->week_number);

            $holidays = collect($this->holidaysForJs())
                ->keyBy(fn (array $h): string => $h['week'].':'.$h['dayIndex']);

            /* The LATEST request per occurrence — last write wins, so a
               re-submitted request replaces the earlier one. Cancelled and
               rejected rows are read too: they explain why an occurrence that was
               once offered has no live request (R2 keeps that fact off the page,
               but the data must not lie to itself). */
            $requests = $sessionIds === []
                ? collect()
                : ReplacementRequest::query()
                    ->with(['replacementTimeSlot.venue'])
                    ->whereIn('class_session_id', $sessionIds)
                    ->orderBy('submitted_at')
                    ->orderBy('id')
                    ->get([
                        'id', 'class_session_id', 'week_number', 'status', 'submitted_at',
                        'semester_id', 'replacement_time_slot_id', 'replacement_span',
                    ])
                    ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);

            $mapper = app(ReplacementRequestMapper::class);

            $spans = $mapper->spansForRequests(
                $requests->map(fn (ReplacementRequest $r): int => (int) $r->id)->values()->all()
            );
            $venueIds = collect($spans)->pluck('venue_id')->filter()->unique()->values()->all();
            $venues = $venueIds === []
                ? collect()
                : Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id');

            foreach ($sessions as $session) {
                for ($week = 1; $week <= $weeks; $week++) {
                    $exception = $exceptions->get($session->id.':'.$week);
                    $holiday = $holidays->get($week.':'.$session->day_of_week);

                    // neither a cancellation nor a holiday — the class runs normally
                    if ($exception === null && $holiday === null) {
                        continue;
                    }

                    $rows[] = $this->occurrenceRow(
                        $session,
                        $week,
                        $exception,
                        $holiday,
                        $requests->get($session->id.':'.$week),
                        $spans,
                        $venues,
                        $mapper,
                        $startDate,
                    );
                }
            }

            /* Default order: class date ascending (design §1a) — a tracker reads
               chronologically. The page's sortable headers reorder from here. */
            usort($rows, fn (array $a, array $b): int => strcmp($a['orig']['date'], $b['orig']['date'])
                ?: ($a['id'] <=> $b['id']));
        }

        return view('livewire.replacement-history', [
            'cohortLabel' => $cohortLabel,
            'payload' => [
                'semester' => $this->semesterJs(),
                'holidays' => $this->holidaysForJs(),
                'currentUser' => ['name' => (string) auth()->user()->name],
                /* the mock-persona flag map is unused with real data — an empty
                   map keeps the frozen modal's fallback path safe */
                'cohortTimetable' => ['rsd3g2Flags' => []],
                'replacementHistory' => $rows,
            ],
        ])
            ->extends('layouts.ui-template', [
                'homeUrl' => '/student-my-timetable-ui',
                'activeNav' => 'replacement-history',
                'pageKey' => 'replacementHistory',
            ])
            ->section('content');
    }

    /**
     * ONE conflicted occurrence. The row's identity is the OCCURRENCE
     * (`"<sessionId>:<week>"`), never the request — most rows have no request at
     * all, and a request id would leave them unclickable (design §1a).
     *
     * @param  array<int, array{week: int, day: int, venue_id: int, start: string, end: string, count: int}>  $spans
     * @param  array<string, int|string>|null  $holiday
     * @param  Collection<int, Venue>  $venues
     * @return array<string, mixed>
     */
    private function occurrenceRow(
        ClassSession $session,
        int $week,
        ?ClassException $exception,
        ?array $holiday,
        ?ReplacementRequest $request,
        array $spans,
        Collection $venues,
        ReplacementRequestMapper $mapper,
        Carbon $semesterStart,
    ): array {
        /* R2: a cancelled or rejected request leaves the occurrence UNARRANGED.
           The page shows the class, never the dead request. `$live` therefore
           also implies "a request exists" for every reader below. */
        $live = $request !== null && ! in_array((string) $request->status, ['cancelled', 'rejected'], true);

        $classDate = $semesterStart->copy()
            ->addWeeks($week - 1)
            ->addDays((int) $session->day_of_week);

        /* The requested slot's TRUE run: the junction span, with the anchor
           fallback widened by `replacement_span` (the junction is deleted on
           cancel/reject, so the fallback is not optional). */
        $span = $live
            ? ($spans[(int) $request->id] ?? $mapper->spanFromAnchor(
                $request->replacementTimeSlot,
                $request->replacement_span !== null ? (int) $request->replacement_span : null,
            ))
            : null;

        $requested = $span === null ? null : [
            'week' => (int) $span['week'],
            'date' => $semesterStart->copy()
                ->addWeeks((int) $span['week'] - 1)
                ->addDays((int) $span['day'])
                ->toDateString(),
            'day' => self::DAY_NAMES[(int) $span['day']] ?? '',
            'start' => Carbon::parse((string) $span['start'])->format('H:i'),
            'end' => Carbon::parse((string) $span['end'])->format('H:i'),
            'duration' => self::hours((string) $span['start'], (string) $span['end']),
            'venue' => self::venueCode($venues->get((int) $span['venue_id']) ?? null, $request->replacementTimeSlot),
        ];

        /* The derived status the staff page prints, then ONE of four words.
           `none` is the payload's value; the MAPPER is asked with `null` (its own
           key), which is how its arms are written. */
        $statusKey = 'none';
        if ($live) {
            $statusKey = $mapper->effectiveStatus($request, $semesterStart, $requested['date'] ?? null);
        }

        $deadline = $statusKey === 'pending' && $requested !== null
            ? $mapper->approvalDeadlineLabel(
                $requested['date'],
                $requested['start'].' – '.$requested['end'],
                (int) $request->semester_id,
            )
            : null;

        $vocabulary = $mapper->statusVocabulary(
            $statusKey === 'none' ? null : $statusKey,
            $requested['date'] ?? null,
            'student',
            $deadline,
        );

        /* The conflict's CAUSE — the staff page's own classification, then the
           student wording (2026-11-09 Q1=B): a calendar holiday keeps its real
           name, everything else reads the bare "Class Cancelled". A leave type
           never leaves this method. */
        $rawReason = null;
        if ($exception !== null) {
            $rawReason = (string) $exception->reason;
            if ($holiday !== null && $rawReason === 'official_event') {
                $rawReason = 'Public Holiday';
            }
        } elseif ($holiday !== null) {
            $rawReason = 'Public Holiday';
        }

        $conflictKind = $rawReason === null
            ? null
            : ($rawReason === 'Public Holiday' ? 'Public Holiday' : 'Class Cancelled');

        return [
            /* the OCCURRENCE is the identity — a request id would be null on most rows */
            'id' => $session->id.':'.$week,
            'week' => $week - 1,
            'requestId' => $live ? (int) $request->id : null,
            'code' => (string) $session->module->module_code,
            'name' => $session->module->displayName(),
            'type' => (string) $session->session_type,
            'lecturer' => $session->lecturer !== null ? (string) $session->lecturer->name : '',
            'orig' => [
                'week' => $week,
                'date' => $classDate->toDateString(),
                'day' => self::DAY_NAMES[(int) $session->day_of_week] ?? '',
                'start' => Carbon::parse((string) $session->start_time)->format('H:i'),
                'end' => Carbon::parse((string) $session->end_time)->format('H:i'),
                'duration' => self::hours((string) $session->start_time, (string) $session->end_time),
                'venue' => (string) $session->venue->room_code,
            ],
            'req' => $requested,
            'status' => $statusKey,
            'statusLabel' => $vocabulary['label'],
            'statusTip' => $vocabulary['tip'],
            'conflictKind' => $conflictKind,
            'holidayName' => $conflictKind === 'Public Holiday'
                ? (trim((string) ($holiday['label'] ?? '')) !== '' ? (string) $holiday['label'] : 'Semester holiday')
                : null,
            'requestedAt' => $live ? $mapper->stampDisplay($request->submitted_at, 'd M Y, h:i A') : null,
        ];
    }

    /** The span's room code, falling back to the anchor slot's own venue. */
    private static function venueCode(?Venue $spanVenue, ?TimeSlot $anchor): string
    {
        if ($spanVenue !== null) {
            return (string) $spanVenue->room_code;
        }

        if ($anchor !== null && $anchor->venue !== null) {
            return (string) $anchor->venue->room_code;
        }

        return '';
    }

    /** Whole hours between two H:i stamps (the staff page's int-hours rule). */
    private static function hours(string $start, string $end): int
    {
        return (int) (Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60);
    }
}
