<?php

namespace App\Livewire;

use App\Models\Notification;
use App\Models\ReplacementRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The notifications island (SDD notifications-real-bridge): ONE component,
 * TWO mounts (design D2) —
 *   variant 'bell'  → the unread TOTAL badge (the updateNotifHeaderState law)
 *   variant 'panel' → the WHOLE panel body server-side (the head pill + the
 *                     mark-all visibility + the filter + the list + the
 *                     caught-up swap), from the SAME unread total.
 * Both `wire:poll.10s` (updates without a page refresh — the user's ruling);
 * the read path stays the durable POST (D3) — the rows are NATIVE anchors.
 * The AD-2 viewed category derives from the page (the per-page map, never the
 * row's type). The lifecycle law: rows never delete; an inactive link target
 * renders the row disabled + the user's tooltip.
 */
class NavNotifications extends Component
{
    public string $variant = 'bell';

    public string $pageKey = '';

    /** The AD-2 viewed category — server equivalent of NOTIF_ROLE_BY_PAGE. */
    public string $roleView = 'lecturer';

    /** The unread-only toggle (server-owned so the poll re-render preserves it). */
    public bool $unreadOnly = true;

    public function mount(string $variant = 'bell', string $pageKey = ''): void
    {
        $this->variant = $variant;
        $this->pageKey = $pageKey;
        $this->roleView = $this->resolveRoleView($pageKey);
    }

    public function render(): View
    {
        $userId = (int) Auth::id();
        $rows = collect();
        $unreadTotal = 0;
        $unreadInView = 0;

        if ($userId > 0) {
            $base = Notification::query()->where('user_id', $userId);

            $unreadTotal = (clone $base)->whereNull('read_at')->count();

            $batch = $this->resolveRequests(
                (clone $base)->orderByDesc('created_at')->get()
            );

            $rows = $batch->filter(fn (Notification $n) => $n->role_view === $this->roleView);

            $unreadInView = $rows->filter(fn (Notification $n) => $n->read_at === null)->count();

            $rows = $this->unreadOnly
                ? $rows->filter(fn (Notification $n) => $n->read_at === null)
                : $rows->values();
        }

        return view('livewire.nav-notifications', [
            'rows' => $rows,
            'unreadTotal' => $unreadTotal,
            'unreadInView' => $unreadInView,
        ]);
    }

    /** Mark-all the user's mailbox read (the ZERO-badge path; the frozen law). */
    public function markAll(): void
    {
        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * The AD-2 map: the tab follows the PAGE, never the row's type.
     */
    private function resolveRoleView(string $pageKey): string
    {
        return match ($pageKey) {
            'studentMyTimetable', 'replacementHistory' => 'student',
            'requestApproval' => 'pl',
            default => 'lecturer',
        };
    }

    /**
     * The lifecycle batch-resolve (round-2 🟡 N+1 law: NEVER a per-row find()
     * on a 10 s poll): one keyed map of the rows' request statuses.
     *
     * @return Collection<int, Notification>
     */
    /**
     * @param  Collection<int, Notification>  $rows
     * @return Collection<int, Notification>
     */
    private function resolveRequests(Collection $rows)
    {
        $requestIds = $rows->filter(fn (Notification $n) => $n->request_id !== null)
            ->map(fn (Notification $n) => (int) $n->request_id)
            ->unique()->values();

        if ($requestIds->isEmpty()) {
            return $rows;
        }

        $statuses = ReplacementRequest::query()
            ->whereIn('id', $requestIds)
            ->pluck('status', 'id');

        foreach ($rows as $row) {
            // D4's written law: the request missing OR status = 'cancelled'
            // renders the row disabled. (The missing status is reachable only
            // by data drift — the FK is nullOnDelete — the branch is the law
            // made defensive.)
            $status = $statuses->get((int) $row->request_id);
            $row->setAttribute('inactive_row', $row->request_id !== null
                && ($status === null || $status === 'cancelled'));
        }

        return $rows;
    }
}
