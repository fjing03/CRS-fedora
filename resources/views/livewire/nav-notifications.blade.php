{{-- SDD notifications-real-bridge: the notifications island (TWO mounts of
     ONE component; the top-level wrapper div is the Livewire root).
     notif-unified-feed (the user's 2026-10-29 ruling): the panel shows the
     RECIPIENT-WIDE feed on every page — no per-page category tabs.
     island='bell'   → the bell BUTTON + the unread-TOTAL badge (the frozen
                       button markup; toggleNotifPanel() stays global).
     island='panel'  → the WHOLE #notifPanel inner content server-side (the
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
    /* notif-abstime-precision (F2, user-approved 2026-10-30): format the
       STORED timestamp directly — no now()−minutes reconstruction, no
       second-truncation drift across poll cycles.
       A/B (2026-11-04): the stored instant is UTC, the user's clock is MYT —
       the conversion is LocalTime's, never a per-view offset. */
    $absTime = function ($at): string {
        return (string) \App\Support\LocalTime::stamp($at, 'j M, g:i A');
    };
    /* SDD notif-new-request-ux T4: the multiline tip's dynamic Requested-at
       line — D8's abs with YEAR (`j M Y, g:i A`) + D9's lowercase verbose
       relative, both recomputed per render (the abstime-precision pattern). */
    $tipAbs = function ($at): string {
        return (string) \App\Support\LocalTime::stamp($at, 'j M Y, g:i A');
    };
    $relLong = function ($at): string {
        if ($at === null) return '';
        $minutes = (int) abs(now()->diffInMinutes($at, false));
        if ($minutes < 1) return 'just now';
        if ($minutes < 60) return $minutes.' minute'.($minutes === 1 ? '' : 's').' ago';
        $hours = (int) floor($minutes / 60);
        if ($minutes < 1440) return $hours === 1 ? '1 hour ago' : $hours.' hours ago';
        if ($minutes < 2880) return 'Yesterday';
        return floor($minutes / 1440).' days ago';
    };
    $userTotal = $unreadTotal > 0;
@endphp
@if($island === 'bell')
    <button class="notif-btn" onclick="toggleNotifPanel()" aria-label="Notifications" aria-haspopup="true" data-tip="Notifications" data-tip-pos="left">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="notif-badge" id="notifBadge" hidden style="display: {{ $userTotal ? 'flex' : 'none' }}">{{ $unreadTotal }}</span>
    </button>
@else
    @php
        $selected = array_map('intval', $selectedIds ?? []);
        $selectedCount = count($selected);
        $undoCount = count($undoIds ?? []);
        $showList = $manageMode ? $rows->isNotEmpty() : $rows->isNotEmpty();
    @endphp

    <div class="notif-head">
        @if($hiddenView)
            {{-- the shelf's head: Back (icon-only, meaning in the tooltip) --}}
            <button class="notif-iconbtn" type="button" wire:click="showInbox"
                    aria-label="Back to inbox" data-tip="Go back to the notification list." data-tip-pos="left">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 19-7-7 7-7"/>
                    <path d="M19 12H5"/>
                </svg>
            </button>
            <span class="notif-head-title">Hidden notifications</span>
        @elseif($manageMode)
            {{-- 2026-11-04 (user): the manage screen had no way back. Same 30px
                 Back family as the shelf, wired to the EXISTING exitManage() —
                 state-only: the ticks are dropped, nothing is written. --}}
            <button class="notif-iconbtn" type="button" wire:click="exitManage"
                    aria-label="Back to notifications" data-tip="Go back to the notification list." data-tip-pos="left">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 19-7-7 7-7"/>
                    <path d="M19 12H5"/>
                </svg>
            </button>
            <span class="notif-head-title">Notifications</span>
        @else
            <span class="notif-head-title">Notifications</span>
        @endif
        <div class="notif-head-actions">
            @unless($hiddenView)
                {{-- notif-inactive-hide-sender (2026-11-05, spec R5): the header's
                     anatomy does not change on a caught-up mailbox — the pill keeps
                     reading `0` and Mark-all stays put, disabled. (The BELL badge
                     keeps its own rule: hidden at zero.) --}}
                <span class="notif-unread-pill" id="notifUnreadPill"
                      data-tip="{{ $unreadTotal }} unread messages" aria-label="{{ $unreadTotal }} unread messages">{{ $unreadTotal }}</span>
                {{-- Mark all: ICON-ONLY at every width (user decision, notif-manage-hide).
                     The 30px square + outline border is the Close/Manage family; the
                     id and the wire:click are load-bearing (specs + behaviour). --}}
                <button class="notif-iconbtn" id="notifMarkAll" type="button" wire:click="markAll"
                        @disabled($unreadTotal === 0)
                        aria-label="Mark all as read" data-tip="Mark every notification as read." data-tip-pos="left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        <path d="m16 19 2 2 4-4"/>
                    </svg>
                </button>
                {{-- Manage: only when there is something to put away --}}
                @if($hideableCount > 0)
                    <button class="notif-iconbtn" id="notifManage" type="button" wire:click="enterManage"
                            aria-label="Manage notifications"
                            data-tip="Put away notifications you have already read.&#10;Nothing is deleted — you can bring them back."
                            data-tip-pos="left">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 17 2 2 4-4"/>
                            <path d="m3 7 2 2 4-4"/>
                            <path d="M13 6h8"/>
                            <path d="M13 12h8"/>
                            <path d="M13 18h8"/>
                        </svg>
                    </button>
                @endif
            @endunless
            <button class="notif-close" type="button" onclick="closeNotifPanel()" aria-label="Close notifications" data-tip="Close" data-tip-pos="left">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    @if($hiddenView)
        <div class="notif-manage-bar">
            <span class="notif-bar-count">{{ $hiddenCount }} hidden</span>
            <div class="notif-bar-actions">
                <button class="notif-bar-btn" type="button" wire:click="unhideAll"
                        @disabled($hiddenCount === 0)>Unhide all</button>
            </div>
        </div>
    @elseif($manageMode)
        <div class="notif-manage-bar">
            <span class="notif-bar-count">{{ $selectedCount }} of {{ $hideableCount }} selected</span>
            <div class="notif-bar-actions">
                <button class="notif-bar-btn" type="button" wire:click="selectAllVisible">Select all</button>
                <button class="notif-bar-btn" type="button" wire:click="clearSelection">Clear</button>
            </div>
        </div>
        <div class="notif-caption">Notifications that still need you can't be hidden.</div>
    @else
        <div class="notif-filters">
            <label class="toggle-wrapper" data-tip="Show only rows you haven't actioned yet">
                <input type="checkbox" id="notifUnreadOnly" wire:model.live="unreadOnly" {{ $unreadOnly ? 'checked' : '' }}>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-label">Unread only</span>
            </label>
            <label class="toggle-wrapper" data-tip="Hide decided/inactive rows — show live items only">
                <input type="checkbox" id="notifActiveOnly" wire:model.live="activeOnly" {{ $activeOnly ? 'checked' : '' }}>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-label">Active only</span>
            </label>
            {{-- the type select spans the whole row (its own line under the two
                 toggles); the native title is GONE — one tooltip system only --}}
            <select class="notif-type-select" id="notifTypeFilter" aria-label="Filter by notification type"
                    data-tip="Filter the list by notification type." wire:model.live="typeFilter">
                <option value="">All types</option>
                <option value="submitted">New requests</option>
                <option value="awaiting">Awaiting decision</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="update">Updates</option>
            </select>
        </div>
    @endif

    @if($hiddenView)
        <div class="notif-list" id="notifList" style="display: {{ $hiddenRows->isEmpty() ? 'none' : '' }}">
            @foreach($hiddenRows as $n)
                @include('partials.notif-row', ['n' => $n, 'glyph' => $glyphs[$n->type] ?? $glyphs['update'], 'variant' => 'hidden'])
            @endforeach
        </div>
        <div class="notif-empty" id="notifEmpty" style="display: {{ $hiddenRows->isEmpty() ? 'flex' : 'none' }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
            <span>Nothing hidden — everything is back in your inbox.</span>
        </div>
        <div class="notif-foot">
            <span class="notif-foot-note">Hidden notifications stay in your mailbox — restore them anytime.</span>
        </div>
    @elseif($manageMode)
        <div class="notif-list" id="notifList" style="display: {{ $rows->isEmpty() ? 'none' : '' }}">
            @foreach($rows as $n)
                @include('partials.notif-row', ['n' => $n, 'glyph' => $glyphs[$n->type] ?? $glyphs['update'], 'variant' => 'pick'])
            @endforeach
        </div>
        <div class="notif-empty" id="notifEmpty" style="display: {{ $rows->isEmpty() ? 'flex' : 'none' }}">
            <span>Nothing to put away — every notification still needs you.</span>
        </div>
        <div class="notif-foot notif-foot--manage">
            <span class="notif-foot-count">{{ $selectedCount }} selected</span>
            <button class="btn-outline notif-hide-btn" type="button" wire:click="hideSelected"
                    @disabled($selectedCount === 0)>Hide selected</button>
        </div>
    @else
        @php $showEmpty = ($unreadOnly && $unreadInView === 0) || $rows->isEmpty(); @endphp
        <div class="notif-list" id="notifList" style="display: {{ $showEmpty ? 'none' : '' }}">
            @foreach($rows as $n)
                @include('partials.notif-row', ['n' => $n, 'glyph' => $glyphs[$n->type] ?? $glyphs['update'], 'variant' => null])
            @endforeach
        </div>

        <div class="notif-empty" id="notifEmpty" style="display: {{ $showEmpty ? 'flex' : 'none' }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
            {{-- ONE FEED (notif-unified-feed): the panel views the WHOLE mailbox on
                 every page, so an empty view is simply a caught-up mailbox — the
                 per-category "unread in other tabs" wording retired with the tabs. --}}
            <span>You're all caught up</span>
        </div>
    @endif

    {{-- The find-back guarantee: rendered whenever something IS hidden — and
         deliberately OUTSIDE the list, so the empty/caught-up state keeps it. --}}
    @if(! $hiddenView && $hiddenCount > 0)
        <div class="notif-foot">
            <button class="notif-foot-link" type="button" wire:click="showHidden"
                    data-tip="See the notifications you put away.&#10;Every one of them can be restored."
                    data-tip-pos="left">Hidden ({{ $hiddenCount }})</button>
        </div>
    @endif

    {{-- Undo lives in COMPONENT state ($undoIds) so the 10 s wire:poll morph
         cannot wipe it; its timer dismisses the toast through the island itself. --}}
    @if($undoCount > 0)
        <div class="notif-undo" x-data x-init="setTimeout(() => $wire.dismissUndo(), 12000)">
            <span class="notif-undo-msg">{{ $undoCount === 1 ? '1 notification hidden' : $undoCount.' notifications hidden' }} · Undo restores them</span>
            <button class="notif-undo-btn" type="button" wire:click="undoHide">Undo</button>
        </div>
    @endif
@endif
</div>
