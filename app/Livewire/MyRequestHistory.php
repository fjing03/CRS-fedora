<?php

namespace App\Livewire;

use App\Actions\CancelPendingRequest;
use App\Concerns\ResolvesTimetableTimeline;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Services\ReplacementRequestMapper;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * My Request History — 100% the frozen UI design (sortable/filterable table,
 * bulk-select cancel bar, request-age colours, detail modal with timeline,
 * summary cards — all client-side as frozen). This component is the DATA
 * BRIDGE: it emits the mock's `MockData.requests` row shape from real rows
 * (own requests only, server-scoped — FR 2.15) and carries the REAL cancel
 * (FR 2.10): the frozen confirm modals call cancelRequest/cancelRequests →
 * CancelPendingRequest (D6 release + audit). The mock's undo-toast callbacks
 * are dropped — the frozen modal copy itself says the action cannot be
 * undone. Slice B (SDD: wire-backend-into-refactored-ui). Legacy mock
 * template untouched for the D10 fallback.
 */
class MyRequestHistory extends Component
{
    use ResolvesTimetableTimeline;

    public function render(): View
    {
        $semester = $this->timelineSemester();
        $start = Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());

        $requests = ReplacementRequest::query()
            ->with([
                'classSession.module', 'classSession.venue', 'classSession.cohorts.programme',
                'classSession.lecturer', 'replacementTimeSlot.venue', 'proposer', 'approver',
            ])
            ->where('proposer_id', auth()->id())
            ->orderByDesc('submitted_at')
            ->get();

        $rows = $requests
            ->map(fn (ReplacementRequest $r): array => $this->requestRow($r, $start))
            ->all();

        return view('livewire.my-request-history', [
            'payload' => [
                'semester' => $this->semesterJs(),
                'holidays' => $this->holidaysForJs(),
                'currentUser' => ['name' => (string) auth()->user()->name],
                'requests' => $rows,
            ],
        ])
            ->extends('layouts.ui-template', ['homeUrl' => '/my-timetable-ui', 'activeNav' => 'replacement-history', 'pageKey' => 'myRequestHistory'])
            ->section('content');
    }

    /**
     * The mock's exact row shape (cf. MockData.requests §).
     *
     * @return array<string, mixed>
     */
    private function requestRow(ReplacementRequest $r, Carbon $semesterStart): array
    {
        // SDD span-aware-approval-flow T1: the row computations are the
        // shared `ReplacementRequestMapper` (§10.0.6 promotion). The mapper's
        // span read is DATA-DRIVEN (junction min/max — now approved rows read
        // it too, Q3=A; legacy anchor-only rows fall back to the anchor byte-
        // identically). History keeps its own byte keys (requester/spaced
        // labels/int duration) and no badge keys.
        $mapper = app(ReplacementRequestMapper::class);
        $session = $r->classSession;
        $identity = $mapper->classRow($r, $semesterStart);
        $span = $mapper->replacementStrings($r, $semesterStart);

        return [
            'id' => (int) $r->id,
            'requester' => $r->proposer !== null ? (string) $r->proposer->name : '',
            'requestedAt' => $r->submitted_at->format('Y-m-d\TH:i:s'),
            'reviewedAt' => $r->decided_at !== null ? $r->decided_at->format('Y-m-d\TH:i:s') : null,
            'reviewedBy' => $r->approver !== null ? (string) $r->approver->name : null,
            'status' => ucfirst((string) $r->status),
            'rejectionReason' => $r->rejection_reason,
            'courseCode' => $identity['courseCode'],
            'courseName' => $identity['courseName'],
            'classType' => $identity['classType'],
            'classDate' => $identity['classDate'],
            'classDay' => $identity['classDay'],
            'timeStart' => $identity['timeStart'],
            'timeEnd' => $identity['timeEnd'],
            'duration' => $identity['duration'],
            'venue' => $identity['venue'],
            'totalStudents' => (int) $session->cohorts->sum('student_count'),
            'cohorts' => $session->cohorts
                ->map(fn ($c): string => $this->spacedCohortLabel($c))
                ->values()
                ->all(),
            'cohortCounts' => $session->cohorts
                ->map(fn ($c): int => (int) $c->student_count)
                ->values()
                ->all(),
            'replacementDate' => $span['date'],
            'replacementTime' => $span['time'],
            'replacementVenue' => $span['venue'],
            'remarks' => $r->remarks,
        ];
    }

    /**
     * FR 2.10 — cancel own pending request (D6 release + audit inside the
     * action, which also guards ownership + pending-ness).
     */
    public function cancelRequest(int $id): bool
    {
        return app(CancelPendingRequest::class)(auth()->user(), $id);
    }

    /**
     * Batch cancel (the frozen bulk bar) — returns the ids that actually
     * cancelled, so the page only removes those rows.
     *
     * @return array<int, int>
     */
    public function cancelRequests(string $idsJson): array
    {
        $ids = json_decode($idsJson, true);
        if (! is_array($ids)) {
            return [];
        }

        $action = app(CancelPendingRequest::class);
        $ok = [];
        foreach ($ids as $id) {
            if ($action(auth()->user(), (int) $id)) {
                $ok[] = (int) $id;
            }
        }

        return $ok;
    }

    /**
     * The mock's spaced cohort convention ('DFT2 (S1) G1').
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
