<?php

namespace App\Livewire;

use App\Actions\CancelPendingRequest;
use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ReplacementRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * My Request History (FR 2.15) — the lecturer's own replacement requests,
 * newest first (idx proposer_submitted), with cancel-own-pending (FR 2.10,
 * confirm modal). Slice B (SDD: wire-backend-into-refactored-ui).
 */
class MyRequestHistory extends Component
{
    use ResolvesTimetableTimeline;

    /** Status filter: all|pending|approved|rejected|cancelled. */
    public string $status = 'all';

    public ?int $confirmCancelId = null;

    #[Title('My Request History')]
    public function render(): View
    {
        $requests = ReplacementRequest::query()
            ->with(['classSession.module', 'classSession.venue', 'replacementTimeSlot.venue'])
            ->where('proposer_id', auth()->id())
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (ReplacementRequest $r): array => [
                'id' => $r->id,
                'code' => $r->classSession->module->module_code,
                'name' => $r->classSession->module->module_name,
                'originalSlot' => $r->classSession !== null
                    ? (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$r->classSession->day_of_week] ?? '').' '.Carbon::parse($r->classSession->start_time)->format('g:i A')
                    : '—',
                'originalWeek' => (int) $r->week_number,
                'replacement' => $r->replacementTimeSlot !== null ? [
                    'week' => (int) $r->replacementTimeSlot->week_number,
                    'day' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$r->replacementTimeSlot->day_of_week] ?? '',
                    'time' => Carbon::parse($r->replacementTimeSlot->start_time)->format('g:i A').' – '.Carbon::parse($r->replacementTimeSlot->end_time)->format('g:i A'),
                    'venue' => $r->replacementTimeSlot->venue->room_code,
                ] : null,
                'requestedAt' => $r->submitted_at->format('d M Y, h:i A'),
                'status' => (string) $r->status,
                'remarks' => (string) ($r->remarks ?? ''),
            ])->all();

        return view('livewire.my-request-history', [
            'requests' => $requests,
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'replacement-history', 'pageKey' => 'myRequestHistory'])
            ->section('content');
    }

    public function cancelConfirm(int $id): void
    {
        $this->confirmCancelId = $id;
    }

    public function cancelDismiss(): void
    {
        $this->confirmCancelId = null;
    }

    /**
     * FR 2.10 — cancel own pending request: slot back to available (D6),
     * request cancelled, audited (CancelPendingRequest guards ownership +
     * pending-ness; a non-pending row simply yields false here).
     */
    public function cancel(CancelPendingRequest $action): void
    {
        if ($this->confirmCancelId === null) {
            return;
        }

        $ok = $action(auth()->user(), $this->confirmCancelId);

        $this->confirmCancelId = null;
        $this->dispatch('toast', message: $ok
            ? 'Request cancelled — the slot is free again.'
            : 'This request can no longer be cancelled.');
    }
}
