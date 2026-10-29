{{-- SDD notifications-real-bridge: the notifications island (TWO mounts of
     ONE component; the top-level wrapper div is the Livewire root).
     variant='bell'  → the bell BUTTON + the unread-TOTAL badge (the frozen
                       button markup; toggleNotifPanel() stays global).
     variant='panel' → the WHOLE #notifPanel inner content server-side (the
                       head, the actions, the filter, the list, the caught-up
                       swap) — the ONE unread total drives badge + pill +
                       mark-all (updateNotifHeaderState's law). The .open
                       shell class stays static OUTSIDE this island, so the
                       open state survives the poll morph. The read path
                       stays the durable POST (the rows are native anchors). --}}
<div {{ $attributes->merge(['wire:poll.10s.visible' => true]) }}>
@php
    $glyphs = [
        'submitted' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'awaiting'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'approved'  => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'rejected'  => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'update'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    ];
    $minutesAgo = function ($at): int {
        return ($at !== null) ? (int) abs(now()->diffInMinutes($at, false)) : 0;
    };
    $relTime = function (int $m): string {
        if ($m < 1) return 'just now';
        if ($m < 60) return $m.'m ago';
        if ($m < 1440) return floor($m / 60).'h ago';
        if ($m < 2880) return 'Yesterday';
        return floor($m / 1440).'d ago';
    };
    $absTime = function (int $m): string {
        $d = now()->subMinutes($m);
        return $d->format('j M, g:i A');
    };
    $userTotal = $unreadTotal > 0;
@endphp
@if($variant === 'bell')
    <button class="notif-btn" onclick="toggleNotifPanel()" aria-label="Notifications" aria-haspopup="true" data-tip="Notifications" data-tip-pos="left">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="notif-badge" id="notifBadge" hidden style="display: {{ $userTotal ? 'flex' : 'none' }}">{{ $unreadTotal }}</span>
    </button>
@else
    <div class="notif-head">
        <span class="notif-head-title">Notifications</span>
        <div class="notif-head-actions">
            <span class="notif-unread-pill" id="notifUnreadPill" hidden style="display: {{ $userTotal ? 'inline-flex' : 'none' }}">{{ $unreadTotal }}</span>
            <button class="notif-markall" id="notifMarkAll" type="button" wire:click="markAll" hidden style="display: {{ $userTotal ? 'inline-flex' : 'none' }}">Mark all as read</button>
            <button class="notif-close" type="button" onclick="closeNotifPanel()" aria-label="Close notifications" data-tip="Close" data-tip-pos="left">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="notif-filters">
        <label class="toggle-wrapper" data-tip="Show only rows you haven't actioned yet">
            <input type="checkbox" id="notifUnreadOnly" wire:model.live="unreadOnly" {{ $unreadOnly ? 'checked' : '' }}>
            <span class="toggle-track"><span class="toggle-thumb"></span></span>
            <span class="toggle-label">Unread only</span>
        </label>
    </div>

    @php $showEmpty = ($unreadOnly && $unreadInView === 0) || $rows->isEmpty(); @endphp
    <div class="notif-list" id="notifList" style="display: {{ $showEmpty ? 'none' : '' }}">
        @foreach($rows as $n)
            @include('partials.notif-row', ['n' => $n, 'glyph' => $glyphs[$n->type] ?? $glyphs['update']])
        @endforeach
    </div>

    <div class="notif-empty" id="notifEmpty" style="display: {{ $showEmpty ? 'flex' : 'none' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="m9 12 2 2 4-4"/>
        </svg>
        <span>{{ $userTotal
            ? 'No unread in this category — '.$unreadTotal.' unread in other tabs'
            : "You're all caught up" }}</span>
    </div>
@endif
</div>
