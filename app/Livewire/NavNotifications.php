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
 * ONE FEED (notif-unified-feed — the user's 2026-10-29 ruling, superseding
 * D2's AD-2 page-category tabs): the panel shows the RECIPIENT-WIDE feed on
 * every page — same rows, order and badge everywhere. The lifecycle law:
 * rows never delete; an inactive link target renders the row disabled + the
 * user's tooltip.
 */
class NavNotifications extends Component
{
    public string $variant = 'bell';

    /** Kept ACCEPTED for mount compatibility (the panel mounts pass the page
     *  key); it no longer selects anything — one feed per recipient, not per
     *  page. */
    public string $pageKey = '';

    /** The unread-only toggle (server-owned so the poll re-render preserves it). */
    public bool $unreadOnly = true;

    public function mount(string $variant = 'bell', string $pageKey = ''): void
    {
        $this->variant = $variant;
        $this->pageKey = $pageKey;
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

            /* ONE FEED: the viewed slice IS the recipient's whole mailbox —
               the badge law (F-2) and the panel count are the same number. */
            $unreadInView = $unreadTotal;

            $rows = $this->resolveRequests(
                (clone $base)->orderByDesc('created_at')->get()
            );

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
