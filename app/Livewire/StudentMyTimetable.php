<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Student My Timetable (FR 1.2/1.3) — own-cohort sessions + replacement
 * request statuses, strictly read-only (FR 1.5–1.8).
 */
class StudentMyTimetable extends Component
{
    use ResolvesTimetableTimeline;

    public int $week = 1;

    #[Title('Student My Timetable')]
    public function mount(): void
    {
        $this->week = $this->currentTimelineWeekIndex() + 1;
    }

    public function render(): View
    {
        $semester = $this->timelineSemester();
        $student = Student::with('cohort.programme')->where('user_id', auth()->id())->first();

        $eventsByWeek = [];
        $cohortCode = null;

        if ($student?->cohort_id !== null) {
            $cohortCode = $this->cohortCode($student->cohort);

            $sessions = ClassSession::query()
                ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
                ->join('session_cohorts', 'session_cohorts.class_session_id', '=', 'class_sessions.id')
                ->where('session_cohorts.cohort_id', $student->cohort_id)
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

            $eventsByWeek = $this->buildEventsByWeek($sessions, $requests, $exceptions, $cohortCode);
        }

        return view('livewire.student-my-timetable', [
            'eventsByWeek' => $eventsByWeek,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
            'currentWeek' => $this->week - 1,
            'cohortCode' => $cohortCode,
            'hasCohort' => $student?->cohort_id !== null,
        ])
            ->extends('layouts.ui-template', [
                'activeNav' => 'my-timetable',
                'pageKey' => 'studentMyTimetable',
                'navItems' => [
                    ['key' => 'my-timetable', 'label' => 'Student My Timetable', 'href' => '/student-my-timetable-ui'],
                    ['key' => 'replacement-history', 'label' => 'Replacement History', 'href' => '/replacement-history-ui'],
                ],
                'notifCount' => 0,
            ])
            ->section('content');
    }
}
