<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lecturer My Timetable (FR 2.2/2.3) — real data per timetable-data-wiring
 * spec. Legacy mock template stays untouched for the D10 fallback.
 */
class MyTimetable extends Component
{
    use ResolvesTimetableTimeline;

    public int $week = 1;

    #[Title('My Timetable')]
    public function mount(): void
    {
        $this->week = $this->currentTimelineWeekIndex() + 1;
    }

    public function render(): View
    {
        $semester = $this->timelineSemester();

        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
            ->where('lecturer_id', auth()->id())
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

        $eventsByWeek = $this->buildEventsByWeek($sessions, $requests, $exceptions);

        return view('livewire.my-timetable', [
            'eventsByWeek' => $eventsByWeek,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
            'currentWeek' => $this->week - 1,
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'my-timetable', 'pageKey' => 'myTimetable'])
            ->section('content');
    }
}
