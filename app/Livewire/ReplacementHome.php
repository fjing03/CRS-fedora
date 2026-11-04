<?php

namespace App\Livewire;

use App\Actions\CancelPendingRequest;
use App\Concerns\CancelsClasses;
use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\Venue;
use App\Services\ReplacementRequestMapper;
use App\Support\ReasonLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * My Replacements — the merged lecturer page (SDD replacement-merge-one-table).
 *
 * ONE table, ONE row per class occurrence (keyed class_session_id:week_number):
 * the class is the subject, its newest replacement request is the row's state.
 * The conflicting-classes query is unchanged from Replacement Home — a request
 * never adds or removes a row (design §1.1). What is new:
 *
 *  - the newest request's replacement span, remark and rejection reason reach
 *    the row through ONE shaping path (buildRow), fed by a BATCH span read so
 *    the query count never grows with the row count (design §1.3/§1.8);
 *  - the six-state vocabulary comes from ReplacementRequestMapper, so the
 *    badge, the card, the tab title and the sentence cannot disagree, and the
 *    page never decides a status (design §1.6);
 *  - cancelling is the SAME write path as before (CancelPendingRequest), and
 *    its answer carries the freshly re-read row — the page never assumes
 *    `cancelled` locally (design §1.7, law 4).
 *
 * The legacy mock template stays for the D10 mock-fallback reference.
 */
class ReplacementHome extends Component
{
    use CancelsClasses;
    use ResolvesTimetableTimeline;

    private const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private function titleCase(string $enum): string
    {
        // cancelled-blocks-stay-visible: the label law delegates to the ONE
        // helper (app/Support/ReasonLabel) — the promote-on-3rd receipt (the
        // home badge, the timetable payload's cancelledReason and the
        // /requests/conflicts map all follow the same modal-label law).
        return ReasonLabel::from($enum);
    }

    public function render(): View
    {
        $semester = $this->timelineSemester();
        $startDate = Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());
        $weeks = $this->timelineWeeks();

        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
            ->where('lecturer_id', auth()->id())
            ->where('semester_id', $semester !== null ? $semester->id : 1)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $exceptions = ClassException::query()
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->get()
            ->keyBy(fn (ClassException $e): string => $e->class_session_id.':'.$e->week_number);

        $holidays = collect($this->holidaysForJs())
            ->keyBy(fn (array $h): string => $h['week'].':'.$h['dayIndex']);

        /* The occurrence's NEWEST request, regardless of status — the row needs
           a rejected/cancelled one too. The ordered keyBy keeps the last row
           written, i.e. the newest (greatest submitted_at, tie-break id).
           `replacement_time_slot_id` is REQUIRED: it is the anchor fallback for
           a junction-less request (the batch span read only sees junction
           rows). `remarks`/`rejection_reason` are DISPLAY-ONLY columns
           (design §1.2) — no FK, no computation. */
        $latestRequests = ReplacementRequest::query()
            ->with(['replacementTimeSlot.venue'])
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->orderBy('submitted_at')->orderBy('id')
            ->get([
                'id', 'class_session_id', 'week_number', 'status', 'submitted_at',
                'replacement_time_slot_id',
                'remarks', 'rejection_reason',
            ])
            ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);

        $mapper = app(ReplacementRequestMapper::class);

        /* §1.3 — two batch reads, no per-row query: the junction spans, then the
           venues those spans point at. */
        $spans = $mapper->spansForRequests(
            $latestRequests->map(fn (ReplacementRequest $r): int => (int) $r->id)->values()->all()
        );
        $venueIds = collect($spans)->pluck('venue_id')->unique()->values()->all();
        $venues = $venueIds === []
            ? collect()
            : Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id');

        $rows = [];
        foreach ($sessions as $session) {
            for ($week = 1; $week <= $weeks; $week++) {
                $row = $this->buildRow(
                    $session,
                    $week,
                    $exceptions->get($session->id.':'.$week),
                    $holidays->get($week.':'.$session->day_of_week),
                    $latestRequests->get($session->id.':'.$week),
                    $spans,
                    $venues,
                    $startDate,
                );

                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        /* §3.4 [D] — emit in DISPLAY order (needs-action group first, then date
           asc, then id) so the default render is a fixed point of an already
           sorted array, and `replacement-home-qv.spec.ts`'s first-payload-row
           correlation keeps holding. */
        usort($rows, function (array $a, array $b): int {
            $handledA = $this->isHandledStatus($a['requestStatus']);
            $handledB = $this->isHandledStatus($b['requestStatus']);

            if ($handledA !== $handledB) {
                return $handledA <=> $handledB;
            }

            $byDate = strcmp((string) $a['date'], (string) $b['date']);

            return $byDate !== 0 ? $byDate : $a['id'] <=> $b['id'];
        });

        return view('livewire.replacement-home', [
            'payload' => [
                'semester' => $this->semesterJs(),
                'holidays' => $this->holidaysForJs(),
                'currentUser' => ['name' => (string) auth()->user()->name],
                'conflictedClasses' => $rows,
            ],
        ])
            ->extends('layouts.ui-template', ['homeUrl' => '/my-timetable-ui', 'activeNav' => 'replacement-arrangement', 'pageKey' => 'myReplacements'])
            ->section('content');
    }

    /**
     * design §1.7 — THE one shaping path: render() calls it per cell, and the
     * cancel path calls it for the one cell whose request just changed.
     *
     * @param  array<string, mixed>|null  $holiday  the calendar-holiday fact for this cell, if any
     * @param  array<int, array{week: int, day: int, venue_id: int, start: string, end: string, count: int}>  $spans
     * @param  Collection<int, Venue>  $venues
     * @return array<string, mixed>|null null only when the occurrence is not conflicted
     */
    private function buildRow(
        ClassSession $session,
        int $week,
        ?ClassException $exception,
        ?array $holiday,
        ?ReplacementRequest $latest,
        array $spans,
        Collection $venues,
        Carbon $startDate,
    ): ?array {
        if ($exception !== null) {
            // FR 2.16 reason (DB enum) → the mock's badgeClass labels
            // ('Annual Leave', 'Medical Leave', …) so the designed reason
            // badges render exactly as frozen.
            $reason = self::titleCase((string) $exception->reason);

            /* D9: the approval flow stamps `official_event` when no exception
               existed (ApproveRequest::releaseOriginal). On a calendar-holiday
               occurrence that stamp must NOT rewrite the cause — the stamp is a
               consequence of the arrangement. Trade-off (documented): a
               lecturer who DELIBERATELY chose "Official Event" for a holiday
               week also reads "Public Holiday"; the two cases share one enum
               value. */
            if ($holiday !== null && (string) $exception->reason === 'official_event') {
                $reason = 'Public Holiday';
            }
        } elseif ($holiday !== null) {
            $reason = 'Public Holiday';
        } else {
            return null; // normal occurrence — this page lists conflicted classes only
        }

        $date = $startDate->copy()->addWeeks($week - 1)->addDays((int) $session->day_of_week);

        $mapper = app(ReplacementRequestMapper::class);

        /* §1.3 — the span: junction (batch) first, the legacy anchor second.
           The fallback is not optional: the junction is DELETEd by
           CancelPendingRequest and RejectRequest, so without it every cancelled
           or rejected arrangement hides its own evidence (§6b law 6). */
        $span = $latest !== null
            ? ($spans[(int) $latest->id] ?? $mapper->spanFromAnchor($latest->replacementTimeSlot))
            : null;
        $venue = $span === null
            ? null
            : ($venues->get((int) $span['venue_id']) ?? $latest->replacementTimeSlot?->venue);
        $strings = $mapper->stringsFromSpan($span, $startDate, $venue);

        /* §1.4 — the computed date goes IN, so the derived status costs no
           second span read, and one derivation still decides `completed`. */
        $status = $latest !== null
            ? $mapper->effectiveStatus($latest->setRelation('classSession', $session), $startDate, $strings['date'])
            : null;
        $vocabulary = $mapper->statusVocabulary($status, $strings['date']);

        return [
            'id' => $session->id * 100 + $week,
            'sessionId' => (int) $session->id,
            'week' => $week,
            'lecturer' => (string) $session->lecturer->name,
            'code' => (string) $session->module->module_code,
            'name' => (string) $session->module->module_name,
            'type' => (string) $session->session_type,
            'date' => $date->toDateString(),
            'day' => self::DAY_NAMES[(int) $session->day_of_week] ?? '',
            'timeStart' => Carbon::parse($session->start_time)->format('H:i'),
            'timeEnd' => Carbon::parse($session->end_time)->format('H:i'),
            'duration' => (int) (Carbon::parse($session->start_time)->diffInMinutes(Carbon::parse($session->end_time)) / 60),
            'venue' => $session->venue !== null ? (string) $session->venue->room_code : '',
            'totalStudents' => (int) $session->cohorts->sum('student_count'),
            'cohorts' => $session->cohorts
                ->map(fn ($c): string => $this->spacedCohortLabel($c))
                ->values()
                ->all(),
            // the per-cohort breakdown (BMSE3153-class-info user law
            // 2026-10-27): the quick view renders `A + B = total` —
            // POSITIONAL with cohorts, real student_count.
            'cohortCounts' => $session->cohorts
                ->map(fn ($c): int => (int) $c->student_count)
                ->values()
                ->all(),
            'conflictReason' => $reason,
            // the modal's Original Class tab prints `venue — venueName`; the
            // table's Original Class cell keeps the code alone (§4.2).
            'venueName' => $session->venue?->room_name,
            'requestId' => $latest !== null ? (int) $latest->id : null,
            'requestStatus' => $status,
            'requestStatusLabel' => $vocabulary['label'],
            'requestStatusShort' => $vocabulary['short'],
            'requestStatusDesc' => $vocabulary['sentence'],
            'requestStatusTip' => $vocabulary['tip'],
            'replacementDate' => $strings['date'],
            'replacementTime' => $strings['time'],
            'replacementWeek' => $strings['week'],
            'replacementDay' => $strings['day'],
            'replacementVenue' => $strings['venue'],
            'replacementVenueName' => $strings['venueName'],
            'remarks' => $latest?->remarks,
            'rejectionReason' => $latest?->rejection_reason,
        ];
    }

    /**
     * Cancel the caller's own pending request — the ONE write path
     * (CancelPendingRequest: ownership + pending guard + D6 release + audit).
     * The returned row is the server's own answer, re-read through the same
     * buildRow(), so the page never assumes `cancelled` locally (design §1.7).
     *
     * @return array{ok: bool, row: array<string, mixed>|null}
     */
    public function cancelRequest(int $id): array
    {
        $ok = app(CancelPendingRequest::class)(auth()->user(), $id);

        if (! $ok) {
            return ['ok' => false, 'row' => null];
        }

        $request = ReplacementRequest::query()
            ->with([
                'classSession.module',
                'classSession.venue',
                'classSession.cohorts.programme',
                'classSession.lecturer',
                'replacementTimeSlot.venue',
            ])
            ->find($id);

        return ['ok' => true, 'row' => $request === null ? null : $this->rowFor($request)];
    }

    /**
     * Re-read ONE occurrence through the same shaping path render() uses.
     *
     * @return array<string, mixed>|null
     */
    private function rowFor(ReplacementRequest $request): ?array
    {
        $session = $request->classSession;

        if ($session === null) {
            return null;
        }

        $semester = $this->timelineSemester();
        $startDate = Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());
        $week = (int) $request->week_number;

        $exception = ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', $week)
            ->first();

        $holiday = collect($this->holidaysForJs())->first(
            fn (array $h): bool => (int) $h['week'] === $week
                && (int) $h['dayIndex'] === (int) $session->day_of_week
        );

        $mapper = app(ReplacementRequestMapper::class);
        $spans = $mapper->spansForRequests([(int) $request->id]);
        $venueIds = collect($spans)->pluck('venue_id')->unique()->values()->all();
        $venues = $venueIds === []
            ? collect()
            : Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id');

        return $this->buildRow($session, $week, $exception, $holiday, $request, $spans, $venues, $startDate);
    }

    /** design §3.4 — the grouping law: handled rows follow the needs-action ones. */
    private function isHandledStatus(?string $status): bool
    {
        return $status !== null && $status !== 'rejected' && $status !== 'cancelled';
    }

    /**
     * The mock's spaced cohort convention ('DFT2 (S1) G1' — the exact output
     * of the shared ui-common formatCohortLabel applied to the compact code).
     */
    private function spacedCohortLabel(?Cohort $cohort): string
    {
        if ($cohort === null || $cohort->programme === null) {
            return '—';
        }

        return $cohort->programme->programme_code
            .$cohort->current_year.' (S'.$cohort->semester.')'
            .' G'.$cohort->tutorial_group;
    }
}
