<?php

namespace App\Livewire;

use App\Concerns\CancelsClasses;
use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
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
 * semester calendar; Venue Restriction = the live B005 × Diploma
 * derivation). Slice B (SDD: wire-backend-into-refactored-ui). Legacy mock
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

        // D1 — one active request per block occurrence: an occurrence with a
        // pending/approved request is already being handled (it returns here
        // if the request is rejected or cancelled).
        $activeRequests = ReplacementRequest::query()
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->whereIn('status', ['pending', 'approved'])
            ->get(['class_session_id', 'week_number'])
            ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);

        $rows = [];
        foreach ($sessions as $session) {
            $venueRestricted = $this->venueRestrictionConflict($session);

            for ($week = 1; $week <= $weeks; $week++) {
                if ($activeRequests->has($session->id.':'.$week)) {
                    continue;
                }

                $exception = $exceptions->get($session->id.':'.$week);
                $holiday = $holidays->get($week.':'.$session->day_of_week);

                if ($exception !== null) {
                    // FR 2.16 reason (DB enum) → the mock's badgeClass labels
                    // ('Annual Leave', 'Medical Leave', …) so the designed
                    // reason badges render exactly as frozen.
                    $reason = self::titleCase((string) $exception->reason);
                } elseif ($holiday !== null) {
                    $reason = 'Public Holiday';
                } elseif ($venueRestricted) {
                    $reason = 'Venue Restriction';
                } else {
                    continue; // normal occurrence — the home lists conflicted classes only
                }

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
                    'conflictReason' => $reason,
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
