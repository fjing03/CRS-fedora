<?php

namespace App\Livewire;

use App\Actions\ApproveRequest;
use App\Actions\RejectRequest;
use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ReplacementRequest;
use App\Services\ApprovalValidity;
use App\Services\ReplacementRequestMapper;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Request Approval — 100% the frozen UI design (the D10 mock template is
 * REPLACED by this real component per `class_exists` — wire-backend
 * pattern). The DATA BRIDGE emits the mock's `MockData.approvalRequests`
 * row shape from REAL rows (SDD span-aware-approval-flow, design D5/D7):
 *
 *  - pending rows FCFS by `submitted_at` (FR 3.2) with the shared validity
 *    predicate (FR 3.4 — proposal item 8's one-source rule);
 *  - decided rows (approved/rejected; `completed` stays an honest empty —
 *    the out-of-scope leg) feed the summary cards + detail modal
 *    (`reviewedBy` = approver identity, `reviewedAt` = decided_at);
 *  - the decide surface: approve(reject) → ApproveRequest/RejectRequest REAL
 *    actions (two-phase + D7 release + audits + mails) — NO undo (frozen
 *    ruling), bulk = per-row sequential FCFS (Q1).
 */
final class RequestApproval extends Component
{
    use ResolvesTimetableTimeline;

    public function render(): View
    {
        $start = $this->semesterStart();

        return view('livewire.request-approval', [
            'payload' => [
                'semester' => $this->semesterJs(),
                'holidays' => $this->holidaysForJs(),
                'urgencyReferenceDate' => now()->toDateString(),
                'lecturers' => $this->lecturerDirectory($start),
                'requests' => $this->rowsPayload(),
            ],
        ])
            ->extends('layouts.ui-template', ['homeUrl' => '/my-timetable-ui', 'activeNav' => 'request-approval', 'pageKey' => 'requestApproval'])
            ->section('content');
    }

    /**
     * The queued FCFS rows + the decided bucket.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsPayload(): array
    {
        $start = $this->semesterStart();
        $validity = app(ApprovalValidity::class);
        $mapper = app(ReplacementRequestMapper::class);

        $requests = ReplacementRequest::query()
            ->with([
                'classSession.module', 'classSession.venue', 'classSession.cohorts.programme',
                'replacementTimeSlot.venue', 'proposer.lecturer', 'approver',
            ])
            ->get();

        $pending = $requests->filter(fn ($r) => (string) $r->status === 'pending')
            ->sortBy('submitted_at')->values();
        $decided = $requests->filter(fn ($r) => in_array((string) $r->status, ['approved', 'rejected'], true))
            ->sortByDesc('decided_at')->values();

        $rows = [];
        foreach ($pending as $r) {
            $rows[] = $mapper->toRow($r, $start, 'queue', $validity->evaluate($r));
        }
        foreach ($decided as $r) {
            $rows[] = $mapper->toRow($r, $start, 'queue');
        }

        return $rows;
    }

    /**
     * The lecturers directory the template's `lookupLecturer` consults —
     * real profiles for the proposers THAT APPEAR IN THE PAYLOAD.
     *
     * @return array<int, array<string, string>>
     */
    private function lecturerDirectory(Carbon $start): array
    {
        $requests = ReplacementRequest::query()
            ->with(['proposer.lecturer'])
            ->get();

        $out = [];
        foreach ($requests as $r) {
            $u = $r->proposer;
            if ($u !== null && ! isset($out[$u->name])) {
                $out[$u->name] = [
                    'name' => (string) $u->name,
                    'staffId' => (string) ($u->lecturer !== null ? $u->lecturer->staff_id : ''),
                    'email' => (string) $u->email,
                ];
            }
        }

        return array_values($out);
    }

    /** JSON-encoded ids — the component's wire boundary (house pattern). */
    /**
     * @return array{results: array<int, array<string, mixed>>, requests: array<int, array<string, mixed>>}
     */
    public function approve(string $idsJson, ?string $note = null): array
    {
        $ids = array_map('intval', json_decode((string) $idsJson, true) ?: []);

        $results = [];
        foreach ($ids as $id) {
            $result = app(ApproveRequest::class)(auth()->user(), (int) $id, $note);
            $results[] = [
                'id' => (int) $id,
                'ok' => (bool) $result['ok'],
                'conflict_reason' => $result['conflict_reason'] ?? null,
            ];
        }

        return $this->decisionPayload($results);
    }

    /**
     * @return array{results: array<int, array<string, mixed>>, requests: array<int, array<string, mixed>>}
     */
    public function reject(string $idsJson, ?string $reason = null): array
    {
        $ids = array_map('intval', json_decode((string) $idsJson, true) ?: []);
        // FR 3.6 — ONE shared reason for the whole batch; empty never passes.
        $reason = trim((string) $reason);

        if ($reason === '') {
            return [
                'results' => array_map(fn ($id) => ['id' => $id, 'ok' => false, 'conflict_reason' => 'A rejection reason is required.'], $ids),
                'requests' => $this->rowsPayload(),
            ];
        }

        $results = [];
        foreach ($ids as $id) {
            $result = app(RejectRequest::class)(auth()->user(), (int) $id, $reason);
            $results[] = [
                'id' => (int) $id,
                'ok' => (bool) $result['ok'],
                'conflict_reason' => $result['conflict_reason'] ?? null,
            ];
        }

        return $this->decisionPayload($results);
    }

    /**
     * Fresh queue rows + the per-row results (the wire return shape).
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array{results: array<int, array<string, mixed>>, requests: array<int, array<string, mixed>>}
     */
    private function decisionPayload(array $results): array
    {
        return [
            'results' => $results,
            'requests' => $this->rowsPayload(),
        ];
    }

    private function semesterStart(): Carbon
    {
        $semester = $this->timelineSemester();

        return Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());
    }
}
