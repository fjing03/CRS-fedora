<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Faculty;
use App\Models\ReplacementRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cohort Timetable — all roles (rbac-route-gating spec). Students are pinned
 * to their own cohort (FR 1.2); lecturers/PLs may view any cohort.
 */
class CohortTimetable extends Component
{
    use ResolvesTimetableTimeline;

    public ?int $facultyId = null;

    public ?int $cohortId = null;

    #[Title('Cohort Timetable')]
    public function mount(): void
    {
        $user = auth()->user();
        if ($user !== null && $user->isStudent()) {
            // FR 1.2 scoping: students are pinned to their own cohort.
            $this->cohortId = $user->student?->cohort_id;
            $this->facultyId = $this->cohortId !== null
                ? Cohort::with('programme')->find($this->cohortId)?->programme?->faculty_id
                : null;
        }
    }

    public function render(): View
    {
        $semester = $this->timelineSemester();

        $faculties = [];
        foreach (Faculty::with(['programmes.cohorts'])->orderBy('faculty_code')->get() as $f) {
            $cohorts = [];
            foreach ($f->programmes as $p) {
                foreach ($p->cohorts as $c) {
                    $cohorts[] = [
                        'id' => $c->id,
                        'name' => $p->programme_code.$c->current_year.'(S'.$c->semester.')G'.$c->tutorial_group,
                    ];
                }
            }
            usort($cohorts, fn ($a, $b) => strcmp($a['name'], $b['name']));
            $faculties[] = ['id' => $f->id, 'name' => $f->faculty_code, 'cohorts' => $cohorts];
        }

        $eventsByWeek = [];

        if ($this->cohortId !== null) {
            $sessions = ClassSession::query()
                ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
                ->join('session_cohorts', 'session_cohorts.class_session_id', '=', 'class_sessions.id')
                ->where('session_cohorts.cohort_id', $this->cohortId)
                ->where('class_sessions.semester_id', $semester !== null ? $semester->id : 1)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(['class_sessions.*']);

            $requests = ReplacementRequest::query()
                ->with(['proposer', 'replacementTimeSlot.venue'])
                ->whereIn('class_session_id', $sessions->modelKeys())
                ->whereIn('status', ['pending', 'approved'])
                ->get();

            $exceptions = ClassException::query()
                ->whereIn('class_session_id', $sessions->modelKeys())
                ->get();

            $cohort = Cohort::with('programme')->find($this->cohortId);

            $eventsByWeek = $this->buildEventsByWeek(
                $sessions,
                $requests,
                $exceptions,
                $cohort !== null ? $this->cohortCode($cohort) : null,
            );
        }

        return view('livewire.cohort-timetable', [
            'faculties' => $faculties,
            'eventsByWeek' => $eventsByWeek,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
            'currentWeek' => $this->currentTimelineWeekIndex(),
            'isStudent' => auth()->user()?->isStudent() ?? false,
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'cohort-timetables', 'pageKey' => 'cohortTimetable'])
            ->section('content');
    }
}
