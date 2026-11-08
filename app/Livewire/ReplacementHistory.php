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

        /* S10 (student-side-health-check, 2026-11-08): the ORIGINAL slot is the
           class's OWN occurrence — its own week, day and 30-minute block indices.
           The mock's row shape could share one `week`/`di` because its rows were
           invented; a real row moves a class from one week to another, so reusing
           the replacement's week/day here printed the wrong week, day and date
           ("Week 8 · Fri, 13 Nov" for a class that actually sits in week 14,
           Thursday 24 Dec). */
        $originalWeek = (int) $r->week_number - 1;

        /* The NEW slot: D4 item-6 (grill Q3=A) — the junction's true run, not the
           anchor cell. S11 (same pass): the TIME STRING must span that run too;
           reading the anchor cell alone printed "13:00 – 13:30" for a
           13:00–14:00 replacement (the duration said 1 hr, the range said 30 min). */
        $newStart = $this->slotIndex((string) $slot->start_time);
        $newEnd = $this->slotIndex((string) $slot->end_time) - 1;
        $newTime = Carbon::parse($slot->start_time)->format('H:i').' – '.Carbon::parse($slot->end_time)->format('H:i');

        $span = app(ReplacementRequestMapper::class)->span($r);
        if ($approved && $span !== null && (int) $span['count'] > 1) {
            $newStart = $this->slotIndex($span['start']);
            $newEnd = $this->slotIndex($span['end']) - 1;
            $newTime = Carbon::parse((string) $span['start'])->format('H:i').' – '.Carbon::parse((string) $span['end'])->format('H:i');
        }

        return [
            'id' => (int) $r->id,
            'code' => (string) $session->module->module_code,
            'name' => $session->module->displayName(),
            'type' => (string) $session->session_type,
            /* the mock's 0-based week index — the ROW's week (the replacement's:
               it is what the week filter, the Past toggle and the summaries use) */
            'week' => (int) $slot->week_number - 1,
            /* S10: the original occurrence's own week/day/block — the Original Slot
               column and the modal read THESE, never `week`/`newDi` */
            'originalWeek' => $originalWeek,
            'di' => (int) $session->day_of_week,
            'start' => $this->slotIndex((string) $session->start_time),
            'end' => $this->slotIndex((string) $session->end_time) - 1,
            'originalTime' => Carbon::parse($session->start_time)->format('H:i').' – '.Carbon::parse($session->end_time)->format('H:i'),
            'originalVenue' => (string) $session->venue->room_code,
            'originalDay' => self::DAY_NAMES[(int) $session->day_of_week] ?? '',
            /* frozen semantic: a PENDING request has no confirmed new slot —
               the page renders "Awaiting PL approval" for it */
            'newDay' => $approved ? self::DAY_NAMES[(int) $slot->day_of_week] : null,
            'newDi' => $approved ? (int) $slot->day_of_week : null,
            'newStart' => $approved ? $newStart : null,
            'newEnd' => $approved ? $newEnd : null,
            'newTime' => $approved ? $newTime : null,
            'newVenue' => $approved ? (string) $slot->venue->room_code : null,
            'lecturer' => $session->lecturer !== null ? (string) $session->lecturer->name : '',
            'status' => $approved ? 'replacement' : 'pending',
            'requestedAt' => app(ReplacementRequestMapper::class)->stampDisplay($r->submitted_at, 'd M Y, h:i A'),
        ];
    }
}
