<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
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
 * Replacement History (student) — 100% the frozen UI design (week nav +
 * completed toggle, sortable table / mobile cards, the 5-card summary strip,
 * class-detail modal — all client-side as frozen). This component is the
 * DATA BRIDGE: it emits the mock's `replacementHistory` row shape from real
 * rows — approved AND pending requests touching MY cohort (FR 1.4). View only:
 * students never act on requests (RBAC). Slice B (SDD:
 * wire-backend-into-refactored-ui). Legacy mock template untouched for the
 * D10 fallback.
 *
 * 2026-11-09 (user brief "Student Side UI Update", fourteen rulings): the page
 * is now aligned with the staff-side My Replacements table — six columns, the
 * staff 4-line slot blocks, the staff status vocabulary (`status-*` classes with
 * the server's long labels), a CONFLICT REASON column, the real requested slot
 * on PENDING rows too, and the staff's `Exclude COMPLETED Status` rule (a
 * replacement counts as completed once its DATE has passed).
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

        $student = Student::query()->where('user_id', auth()->id())->first();
        $cohort = $student?->cohort;
        $cohortLabel = $cohort !== null ? $this->cohortCode($cohort) : null;

        $rows = [];
        if ($cohort !== null) {
            $requests = ReplacementRequest::query()
                ->with(['classSession.module', 'classSession.venue', 'classSession.cohorts', 'classSession.lecturer', 'replacementTimeSlot.venue'])
                ->whereIn('status', ['pending', 'approved'])
                ->whereHas('classSession.cohorts', fn ($q) => $q->whereKey($cohort->id))
                ->orderByDesc('submitted_at')
                ->get();

            /* The conflict reason is the CLASS's own cause, not the request's
               slot validity (a decided request has no validity reason at all).
               Two batch reads, exactly like the staff page: the exceptions for
               the sessions in play, and the semester holidays already shaped for
               JS. */
            $exceptions = ClassException::query()
                ->whereIn('class_session_id', $requests->map(fn (ReplacementRequest $r): int => (int) $r->class_session_id)->unique()->values()->all())
                ->get()
                ->keyBy(fn (ClassException $e): string => $e->class_session_id.':'.$e->week_number);

            $holidays = collect($this->holidaysForJs())
                ->keyBy(fn (array $h): string => $h['week'].':'.$h['dayIndex']);

            $mapper = app(ReplacementRequestMapper::class);

            /* TWO batch reads for the whole column (§1.3 of the staff page's
               design): the junction spans, then the venues those spans point at.
               The anchor fallback is not optional — the junction is DELETEd by
               CancelPendingRequest/RejectRequest, so a junction-less request must
               still show its own evidence. */
            $spans = $mapper->spansForRequests(
                $requests->map(fn (ReplacementRequest $r): int => (int) $r->id)->values()->all()
            );
            $venueIds = collect($spans)->pluck('venue_id')->filter()->unique()->values()->all();
            $venues = $venueIds === []
                ? collect()
                : Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id');

            $rows = $requests
                ->map(fn (ReplacementRequest $r): array => $this->historyRow(
                    $r,
                    $mapper,
                    $startDate,
                    $spans[(int) $r->id] ?? $mapper->spanFromAnchor($r->replacementTimeSlot, $r->replacement_span !== null ? (int) $r->replacement_span : null),
                    $exceptions->get($r->class_session_id.':'.$r->week_number),
                    $holidays->get($r->week_number.':'.$r->classSession->day_of_week),
                    $venues,
                ))
                ->all();
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
     * The mock's exact row shape (cf. MockData.replacementHistory §), extended
     * (2026-11-09) with the staff-side primitives the two slot columns and the
     * status column now render from.
     *
     * @param  array{week: int, day: int, venue_id: int, start: string, end: string, count: int}|null  $span
     * @param  array<string, int|string>|null  $holiday
     * @param  Collection<int, Venue>  $venues
     * @return array<string, mixed>
     */
    private function historyRow(
        ReplacementRequest $r,
        ReplacementRequestMapper $mapper,
        Carbon $semesterStart,
        ?array $span,
        ?ClassException $exception,
        ?array $holiday,
        Collection $venues,
    ): array {
        $session = $r->classSession;
        $slot = $r->replacementTimeSlot;

        /* S10 (student-side-health-check, 2026-11-08): the ORIGINAL slot is the
           class's OWN occurrence — its own week, day and 30-minute block indices.
           The mock's row shape could share one `week`/`di` because its rows were
           invented; a real row moves a class from one week to another, so reusing
           the replacement's week/day here printed the wrong week, day and date
           ("Week 8 · Fri, 13 Nov" for a class that actually sits in week 14,
           Thursday 24 Dec). */
        $originalWeek = (int) $r->week_number - 1;
        $originalDate = $semesterStart->copy()
            ->addWeeks((int) $r->week_number - 1)
            ->addDays((int) $session->day_of_week);

        /* D4 item-6 (grill Q3=A) + S11: the requested slot's TRUE run — the
           junction's span, never the anchor cell alone (an anchor-based read
           printed "13:00 – 13:30" and 0.5 hours for a 13:00–14:00 replacement).
           2026-11-09 (grill Q2=A): this now applies to PENDING rows too — the
           page prints the slot the lecturer actually requested while it waits,
           because that is what the column is named after and the PL's approval
           confirms that same slot rather than choosing a new one. */
        $requested = $span === null ? null : [
            'week' => (int) $span['week'],
            'date' => $semesterStart->copy()
                ->addWeeks((int) $span['week'] - 1)
                ->addDays((int) $span['day'])
                ->toDateString(),
            'day' => self::DAY_NAMES[(int) $span['day']] ?? '',
            'start' => Carbon::parse((string) $span['start'])->format('H:i'),
            'end' => Carbon::parse((string) $span['end'])->format('H:i'),
            'duration' => self::hours($span['start'], $span['end']),
            'venue' => self::venueCode($venues->get((int) $span['venue_id']) ?? null, $slot),
        ];

        /* The status is the ONE derived vocabulary the staff page and the API
           already read (`completed` = an approved request whose replacement date
           has passed — 2026-11-09 grill Q5=A), and the labels/tips come from the
           mapper so both pages print the same words. The tip asks for the
           student voice: the shared lecturer arm says "Your request", and a
           student is not the proposer. */
        $statusKey = $mapper->effectiveStatus($r, $semesterStart, $requested['date'] ?? null);
        $deadline = $statusKey === 'pending'
            ? $mapper->approvalDeadlineLabel($requested['date'] ?? '', $requested['start'].' – '.$requested['end'], (int) $r->semester_id)
            : null;
        $vocabulary = $mapper->statusVocabulary($statusKey, $requested['date'] ?? null, 'student', $deadline);

        /* 2026-11-09 (grill Q1=B — the user's privacy ruling): a student reads the
           CAUSE, never the staff member's leave type. A calendar holiday keeps its
           real name ("Public Holiday — Deepavali Holiday (In Lieu)"); every other
           exception reads the bare "Class Cancelled". The classification is the
           staff page's own rule, so the two pages cannot disagree about WHICH kind
           of conflict it was — only about how much of it a student may see. */
        $rawReason = null;
        if ($exception !== null) {
            $rawReason = (string) $exception->reason;
            /* D9 stamp: ApproveRequest::releaseOriginal writes `official_event`
               when no exception existed, and on a holiday occurrence that stamp
               must not rewrite the cause (same rule as My Replacements). */
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
            'id' => (int) $r->id,
            'code' => (string) $session->module->module_code,
            'name' => $session->module->displayName(),
            'type' => (string) $session->session_type,
            /* the 0-based REQUESTED week — the week filter, the completed rule's
               fallback and the default order all read it */
            'week' => $requested !== null ? $requested['week'] - 1 : $originalWeek,
            'originalWeek' => $originalWeek,
            'di' => (int) $session->day_of_week,
            'start' => $this->slotIndex((string) $session->start_time),
            'end' => $this->slotIndex((string) $session->end_time) - 1,
            /* the two slots as the SAME primitive shape, so the table, the cards
               and the modal all format them with one set of helpers */
            'orig' => [
                'week' => (int) $r->week_number,
                'date' => $originalDate->toDateString(),
                'day' => self::DAY_NAMES[(int) $session->day_of_week] ?? '',
                'start' => Carbon::parse((string) $session->start_time)->format('H:i'),
                'end' => Carbon::parse((string) $session->end_time)->format('H:i'),
                'duration' => self::hours($session->start_time, $session->end_time),
                'venue' => (string) $session->venue->room_code,
            ],
            'req' => $requested,
            'lecturer' => $session->lecturer !== null ? (string) $session->lecturer->name : '',
            'status' => $statusKey,
            'statusLabel' => $vocabulary['label'],
            'statusShort' => $vocabulary['short'],
            'statusTip' => $vocabulary['tip'],
            'conflictKind' => $conflictKind,
            'holidayName' => $conflictKind === 'Public Holiday'
                ? (trim((string) ($holiday['label'] ?? '')) !== '' ? (string) $holiday['label'] : 'Semester holiday')
                : null,
            'requestedAt' => $mapper->stampDisplay($r->submitted_at, 'd M Y, h:i A'),
        ];
    }

    /** The span's room code, falling back to the anchor slot's own venue. */
    private static function venueCode(?Venue $spanVenue, ?TimeSlot $anchor): string
    {
        if ($spanVenue !== null) {
            return (string) $spanVenue->room_code;
        }

        return $anchor?->venue !== null ? (string) $anchor->venue->room_code : '';
    }

    /** Whole hours between two H:i stamps (the staff page's int-hours rule). */
    private static function hours(string $start, string $end): int
    {
        return (int) (Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60);
    }
}
