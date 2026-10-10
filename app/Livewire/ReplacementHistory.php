<?php

namespace App\Livewire;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ReplacementRequest;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Replacement History (FR 1.4, student view-only) — approved replacements
 * whose original class covers MY cohort, upcoming weeks only (replacement
 * slot week ≥ current). Read-only: students never act on requests (RBAC).
 * Slice B (SDD: wire-backend-into-refactored-ui).
 */
class ReplacementHistory extends Component
{
    use ResolvesTimetableTimeline;

    #[Title('Replacement History')]
    public function render(): View
    {
        $student = Student::query()->where('user_id', auth()->id())->first();

        $currentWeek = $this->currentTimelineWeekIndex() + 1;

        $rows = [];
        $cohortLabel = null;

        if ($student !== null) {
            $cohort = $student->cohort;
            $cohortLabel = $cohort !== null ? (string) $this->cohortCode($cohort) : null;

            $rows = ReplacementRequest::query()
                ->select('replacement_requests.*')
                ->join('time_slots', 'time_slots.id', '=', 'replacement_requests.replacement_time_slot_id')
                ->where('replacement_requests.status', 'approved')
                ->whereHas('classSession.cohorts', fn ($q) => $q->whereKey($cohort->id))
                ->where('time_slots.week_number', '>=', $currentWeek)
                ->with(['classSession.module', 'classSession.venue', 'replacementTimeSlot.venue', 'approver'])
                ->orderBy('time_slots.week_number')
                ->orderBy('time_slots.day_of_week')
                ->orderBy('time_slots.start_time')
                ->get()
                ->map(fn (ReplacementRequest $r): array => [
                    'id' => $r->id,
                    'code' => $r->classSession->module->module_code,
                    'name' => $r->classSession->module->module_name,
                    'originalSlot' => $r->classSession !== null
                        ? (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$r->classSession->day_of_week] ?? '').' '
                            .Carbon::parse($r->classSession->start_time)->format('g:i A')
                            .' – '.Carbon::parse($r->classSession->end_time)->format('g:i A')
                        : '—',
                    'originalWeek' => (int) $r->week_number,
                    'replacement' => $r->replacementTimeSlot !== null ? [
                        'week' => (int) $r->replacementTimeSlot->week_number,
                        'day' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$r->replacementTimeSlot->day_of_week] ?? '',
                        'time' => Carbon::parse($r->replacementTimeSlot->start_time)->format('g:i A').' – '.Carbon::parse($r->replacementTimeSlot->end_time)->format('g:i A'),
                        'venue' => $r->replacementTimeSlot->venue->room_code,
                    ] : null,
                    'decidedBy' => $r->approver !== null ? $r->approver->name : '—',
                ])->all();
        }

        return view('livewire.replacement-history', [
            'rows' => $rows,
            'cohortLabel' => $cohortLabel,
            'currentWeek' => $currentWeek,
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'replacement-history', 'pageKey' => 'replacementHistory'])
            ->section('content');
    }
}
