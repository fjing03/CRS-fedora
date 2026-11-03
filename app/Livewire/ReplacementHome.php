<?php

namespace App\Livewire;

use App\Concerns\CancelsClasses;
use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Services\ReplacementRequestMapper;
use App\Support\ReasonLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Replacement Home — 100% the frozen UI design (the mock template's sortable
 * conflicted-classes table, week filter, pagination, card view, quick-view
 * modal and 3-card summary all run untouched). This component is the DATA
 * BRIDGE only: it emits the mock's `conflictedClasses` row shape from real
 * DB rows — one row per conflicted/cancelled weekly occurrence (FR 2.16
 * exceptions carry the lecturer's real reason; Public Holiday from the
 * semester calendar; the Venue Restriction arm was REPEALED 2026-10-27
 * (SDD b005-diploma-allowed)). Slice B (SDD: wire-backend-into-refactored-ui). Legacy mock
 * template untouched for the D10 fallback.
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

        /* replacement-home-status-badges D1 (2026-11-03, the user's reversal of
           the 2026-10-11 hide): the occurrence stays in the table once it is
           arranged — the row carries the request's status so the Action cell can
           show a Pending/Approved badge or a Rejected/Cancelled label. The map
           holds the NEWEST request per occurrence REGARDLESS of status (a
           rejected/cancelled row needs the label too); the ordered keyBy keeps
           the last row written, i.e. the newest. */
        $latestRequests = ReplacementRequest::query()
            ->with(['replacementTimeSlot.venue'])   // the effectiveStatus span read
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->orderBy('submitted_at')->orderBy('id')
            // replacement_time_slot_id is REQUIRED: effectiveStatus() reads the span
            // through the anchor when the junction is empty (a narrow select that
            // omits the FK silently reads "no slot" → it would never say Completed).
            ->get(['id', 'class_session_id', 'week_number', 'status', 'submitted_at', 'replacement_time_slot_id'])
            ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);

        $mapper = app(ReplacementRequestMapper::class);

        $rows = [];
        foreach ($sessions as $session) {
            for ($week = 1; $week <= $weeks; $week++) {
                $exception = $exceptions->get($session->id.':'.$week);
                $holiday = $holidays->get($week.':'.$session->day_of_week);

                if ($exception !== null) {
                    // FR 2.16 reason (DB enum) → the mock's badgeClass labels
                    // ('Annual Leave', 'Medical Leave', …) so the designed
                    // reason badges render exactly as frozen.
                    $reason = self::titleCase((string) $exception->reason);

                    /* D9: the approval flow stamps `official_event` when no
                       exception existed (ApproveRequest::releaseOriginal). On a
                       calendar-holiday occurrence that stamp must NOT rewrite the
                       cause — the stamp is a consequence of the arrangement.
                       Trade-off (documented): a lecturer who DELIBERATELY chose
                       "Official Event" for a holiday week also reads "Public
                       Holiday"; the two cases share one enum value. */
                    if ($holiday !== null && (string) $exception->reason === 'official_event') {
                        $reason = 'Public Holiday';
                    }
                } elseif ($holiday !== null) {
                    $reason = 'Public Holiday';
                } else {
                    continue; // normal occurrence — the home lists conflicted classes only
                }

                $latest = $latestRequests->get($session->id.':'.$week);

                $date = $startDate->copy()->addWeeks($week - 1)->addDays((int) $session->day_of_week);

                $rows[] = [
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
                    /* D1/D2 — the occurrence's newest request, for the Action
                       cell (badge vs label) and the quick view. NULL = the row
                       still needs an arrangement. `requestStatus` is the
                       EFFECTIVE status (request-completed-status C1/C3): an
                       approved request whose replacement class has already
                       happened reads 'completed' — the badges lane's D3 note
                       ("no completed state exists") is superseded here. */
                    'requestId' => $latest !== null ? (int) $latest->id : null,
                    'requestStatus' => $latest !== null
                        ? $mapper->effectiveStatus(
                            $latest->setRelation('classSession', $session),
                            $startDate,
                        )
                        : null,
                ];
            }
        }

        return view('livewire.replacement-home', [
            'payload' => [
                'semester' => $this->semesterJs(),
                'holidays' => $this->holidaysForJs(),
                'currentUser' => ['name' => (string) auth()->user()->name],
                'conflictedClasses' => $rows,
            ],
        ])
            ->extends('layouts.ui-template', ['homeUrl' => '/my-timetable-ui', 'activeNav' => 'replacement-arrangement', 'pageKey' => 'replacementHome'])
            ->section('content');
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
