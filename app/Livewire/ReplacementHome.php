<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Replacement Home (FR 2.4/2.14/2.15) — the lecturer's own classes, conflicted
 * included, with start-a-replacement from the class details; my request counts
 * by status; access to own request history and venue timetables. The PL
 * inherits everything (FR 3.1). Slice B (SDD: wire-backend-into-refactored-ui).
 * Legacy mock template untouched for the D10 fallback.
 */
class ReplacementHome extends Component
{
    use ResolvesTimetableTimeline;

    /** Live search filter (module code or name). */
    #[Url]
    public string $search = '';

    #[Title('Replacement Arrangement')]
    public function render(): View
    {
        $semester = $this->timelineSemester();

        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
            ->where('lecturer_id', auth()->id())
            ->where('semester_id', $semester !== null ? $semester->id : 1)
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.str_replace('%', '\%', trim($this->search)).'%';
                $query->whereHas('module', fn ($m) => $m->where(fn ($w) => $w
                    ->where('module_code', 'ilike', $term)
                    ->orWhere('module_name', 'ilike', $term)));
            })
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $myRequests = ReplacementRequest::query()
            ->where('proposer_id', auth()->id())
            ->get();

        $exceptions = ClassException::query()
            ->whereIn('class_session_id', $sessions->modelKeys())
            ->get();

        $rows = [];
        $modalEvents = [];
        foreach ($sessions as $session) {
            $event = $this->baseEvent($session);
            $event['duration'] = ($event['end'] - $event['start'] + 1) / 2; // hours
            $modalEvents[(int) $session->id] = $event;

            $cancelledWeeks = $exceptions->where('class_session_id', $session->id)->count();
            $activeRequests = $myRequests
                ->where('class_session_id', $session->id)
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            $rows[] = $event + [
                'timeLabel' => date('g:i A', strtotime((string) $session->start_time)).' – '.date('g:i A', strtotime((string) $session->end_time)),
                'dayLabel' => self::DAY_LABELS[(int) $session->day_of_week] ?? (string) $session->day_of_week,
                'conflicted' => $this->venueRestrictionConflict($session),
                'cancelledWeeks' => $cancelledWeeks,
                'activeRequests' => $activeRequests,
            ];
        }

        $requestCounts = [
            'pending' => $myRequests->where('status', 'pending')->count(),
            'approved' => $myRequests->where('status', 'approved')->count(),
            'rejected' => $myRequests->where('status', 'rejected')->count(),
            'cancelled' => $myRequests->where('status', 'cancelled')->count(),
        ];

        $conflictedCount = $sessions->filter(fn (ClassSession $s): bool => $this->venueRestrictionConflict($s))->count();

        return view('livewire.replacement-home', [
            'rows' => $rows,
            'modalEvents' => $modalEvents,
            'requestCounts' => $requestCounts,
            'conflictedCount' => $conflictedCount,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
            'currentWeek' => $this->currentTimelineWeekIndex(),
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'replacement-arrangement', 'pageKey' => 'replacementHome'])
            ->section('content');
    }

    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
}
