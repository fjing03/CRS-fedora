{{-- ─── Notifications panel (overlay + dialog shell; self-contained) ───
    SDD notifications-real-bridge: the shell (overlay + .notif-panel with the
    `.open` class) stays STATIC — the island below (wire:poll.10s.visible —
    it polls only while open; while closed the shell is hidden so the island
    idles and the BELL island keeps running) renders the
    WHOLE panel body server-side (the head pill, the mark-all visibility, the
    filter, the list, the caught-up swap) from the ONE unread total. The read
    path stays the durable POST (the rows are native anchors). --}}
<div class="notif-overlay" id="notifOverlay"></div>

<div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifications">
    @livewire(NavNotifications::class, ['variant' => 'panel', 'pageKey' => $pageKey ?? ''], key('notif-panel'))
</div>
