<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Services\ReplacementRequestMapper;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Replacement History (student) — 100% the frozen UI design (week nav +
 * show-past toggle, sortable table / mobile cards, the 5-card summary strip,
 * class-detail modal — all client-side as frozen). This component is the
 * DATA BRIDGE: it emits the mock's `MockData.replacementHistory` row shape
 * from real rows — approved AND pending requests touching MY cohort (FR 1.4;
 * the frozen design shows pending as "Awaiting PL approval" with no new
 * slot). View only: students never act on requests (RBAC). Slice B (SDD:
 * wire-backend-into-refactored-ui). Legacy mock template untouched for the
 * D10 fallback.
 */
class ReplacementHistory extends Component
{
    use ResolvesTimetableTimeline;

    private const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function render(): View
    {
        $semester = $this->timelineSemester();
        $semesterId = $semester !== null ? $semester->id : 1;

        $student = Student::query()->where('user_id', auth()->id())->first();
        $cohort = $student?->cohort;
        $cohortLabel = $cohort !== null ? $this->cohortCode($cohort) : null;

        $rows = [];
        if ($cohort !== null) {
            $rows = ReplacementRequest::query()
                ->with(['classSession.module', 'classSession.venue', 'classSession.cohorts', 'classSession.lecturer', 'replacementTimeSlot.venue'])
                ->whereIn('status', ['pending', 'approved'])
                ->whereHas('classSession.cohorts', fn ($q) => $q->whereKey($cohort->id))
                ->orderByDesc('submitted_at')
                ->get()
                ->map(fn (ReplacementRequest $r): array => $this->historyRow($r))
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
     * The mock's exact row shape (cf. MockData.replacementHistory §).
     *
     * @return array<string, mixed>
     */
    private function historyRow(ReplacementRequest $r): array
    {
        $session = $r->classSession;
        $slot = $r->replacementTimeSlot;
        $approved = (string) $r->status === 'approved';
        $start = $this->slotIndex((string) $slot->start_time);
        $end = $this->slotIndex((string) $slot->end_time) - 1;

        // D4 item-6 (grill Q3=A): the junction's true run for approved rows.
        $span = app(ReplacementRequestMapper::class)->span($r);
        if ($approved && $span !== null && (int) $span['count'] > 1) {
            $start = $this->slotIndex($span['start']);
            $end = $this->slotIndex($span['end']) - 1;
        }

        return [
            'id' => (int) $r->id,
            'code' => (string) $session->module->module_code,
            'name' => (string) $session->module->module_name,
            'type' => (string) $session->session_type,
            'week' => (int) $slot->week_number - 1, // the mock's 0-based week index
            'di' => (int) $slot->day_of_week,
            'start' => $start,
            'end' => $end,
            'originalTime' => Carbon::parse($session->start_time)->format('H:i').' – '.Carbon::parse($session->end_time)->format('H:i'),
            'originalVenue' => (string) $session->venue->room_code,
            'originalDay' => self::DAY_NAMES[(int) $session->day_of_week] ?? '',
            /* frozen semantic: a PENDING request has no confirmed new slot —
               the page renders "Awaiting PL approval" for it */
            'newDay' => $approved ? self::DAY_NAMES[(int) $slot->day_of_week] : null,
            'newDi' => $approved ? (int) $slot->day_of_week : null,
            'newStart' => $approved ? $start : null,
            'newEnd' => $approved ? $end : null,
            'newTime' => $approved
                ? Carbon::parse($slot->start_time)->format('H:i').' – '.Carbon::parse($slot->end_time)->format('H:i')
                : null,
            'newVenue' => $approved ? (string) $slot->venue->room_code : null,
            'lecturer' => $session->lecturer !== null ? (string) $session->lecturer->name : '',
            'status' => $approved ? 'replacement' : 'pending',
            'requestedAt' => app(ReplacementRequestMapper::class)->stampDisplay($r->submitted_at, 'd M Y, h:i A'),
        ];
    }
}
