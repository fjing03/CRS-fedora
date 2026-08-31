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
 * Cohort Timetable — all roles (rbac-route-gating spec). Students receive
 * only their own cohort's events (FR 1.2); lecturers/PLs get every cohort
 * and switch client-side (same as the legacy mock behaviour).
 */
class CohortTimetable extends Component
{
    use ResolvesTimetableTimeline;

    #[Title('Cohort Timetable')]
    public function mount(): void
    {
        //
    }

    public function render(): View
    {
        $semester = $this->timelineSemester();
        $isStudent = auth()->user()?->isStudent() ?? false;

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

        $eventsByCohort = $this->eventsByCohort($isStudent);

        return view('livewire.cohort-timetable', [
            'faculties' => $faculties,
            'eventsByCohort' => $eventsByCohort,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
            'currentWeek' => $this->currentTimelineWeekIndex(),
            'isStudent' => $isStudent,
            'pinnedCohortId' => $isStudent ? (auth()->user()->student?->cohort_id) : null,
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'cohort-timetables', 'pageKey' => 'cohortTimetable'])
            ->section('content');
    }

    /**
     * Per-cohort per-week event maps. Students: own cohort only (FR 1.2).
     *
     * @return array<int, array<int, array<int, array<string, mixed>>>>
     */
    private function eventsByCohort(bool $isStudent): array
    {
        $semester = $this->timelineSemester();

        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
            ->where('semester_id', $semester !== null ? $semester->id : 1)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $requests = ReplacementRequest::query()
            ->with(['proposer', 'replacementTimeSlot.venue'])
            ->whereIn('class_session_id', $sessions->modelKeys())
            ->whereIn('status', ['pending', 'approved'])
            ->get();

        $exceptions = ClassException::query()
            ->whereIn('class_session_id', $sessions->modelKeys())
            ->get();

        $cohortIds = $isStudent
            ? [auth()->user()?->student?->cohort_id]
            : $sessions->flatMap(fn ($s) => $s->cohorts->pluck('id'))->unique()->values()->all();

        $out = [];
        foreach ($cohortIds as $cohortId) {
            if ($cohortId === null) {
                continue;
            }
            $cohortId = (int) $cohortId;

            $cohortSessions = $sessions->filter(
                fn ($s) => $s->cohorts->contains('id', $cohortId),
            )->values();

            if ($cohortSessions->isEmpty()) {
                $out[$cohortId] = [];

                continue;
            }

            $cohort = Cohort::with('programme')->find($cohortId);

            $out[$cohortId] = $this->buildEventsByWeek(
                $cohortSessions,
                $requests,
                $exceptions,
                $cohort !== null ? $this->cohortCode($cohort) : null,
            );
        }

        return $out;
    }
}
