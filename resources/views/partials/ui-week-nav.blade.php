@php
    $selectClass = $selectClass ?? 'week-select';
    /* Optional tinted surface for the timetable pages (approved preview). The
       replacement pages keep the default white .week-select. */
    $selectTinted = $selectTinted ?? false;
    $selectClass = $selectTinted ? $selectClass.' week-select--tinted' : $selectClass;
    $showTodayBtn = $showTodayBtn ?? true;
    $disabled = $disabled ?? false;
    $showPrint = $showPrint ?? false;
    /* Optional SECOND action (venue-timetable-bookable-cells, 2026-11-05): the
       "Earliest bookable" button, rendered right after Today. Default false, so
       every existing caller stays byte-identical (the arrangement page uses the
       earliest variant AS its Today button instead). */
    $showEarliestBtn = $showEarliestBtn ?? false;
    /* timetable-print-v2 (2026-11-16): the Normal Class toggle. Opt-in and default
       FALSE, so the arrangement page and the frozen templates render byte-identically.
       It has no inline onclick — PrintSheet binds it (public/js/ui-common.js), because
       a page script lives in a closure and an inline handler would need an export. */
    $showNormalToggle = $showNormalToggle ?? false;
    // Optional hover hints (shared data-tip tooltip, shown ABOVE the element).
    // Null by default → pages that don't pass them render byte-identical.
    $prevTip = $prevTip ?? null;
    $nextTip = $nextTip ?? null;
    $selectTip = $selectTip ?? null;
@endphp

<div class="week-nav">
    <button class="week-arrow" onclick="{{ $prevOnclick }}" aria-label="Previous week" @if($prevTip) data-tip="{{ $prevTip }}" @endif {{ $disabled ? 'disabled' : '' }}>&#8249;</button>
    <select class="{{ $selectClass }}" id="{{ $selectId }}" onchange="{{ $selectOnclick }}" @if($selectTip) data-tip="{{ $selectTip }}" @endif {{ $disabled ? 'disabled' : '' }}></select>
    <button class="week-arrow" onclick="{{ $nextOnclick }}" aria-label="Next week" @if($nextTip) data-tip="{{ $nextTip }}" @endif {{ $disabled ? 'disabled' : '' }}>&#8250;</button>
</div>
@if($showTodayBtn)
    @include('partials.ui-today-btn')
@endif
@if($showEarliestBtn)
    @include('partials.ui-today-btn', [
        'todayIcon' => 'earliest',
        'todayLabel' => $earliestLabel ?? 'Earliest bookable',
        'todayTip' => $earliestTip ?? 'Jump to the earliest bookable slot',
    ])
@endif
@if($showNormalToggle)
    {{-- The semester's normal timetable, as a reading mode for the paper. Its hover
         tip rides the shared data-tip pill (contract TOOLTIPS) and its state never
         leaves the page: on every rebuild, off again on reload. --}}
    <label class="print-normal-toggle" data-tip="Show the semester's normal timetable only">
        <input type="checkbox" id="printNormalToggle" aria-label="Show the semester's normal timetable only">
        <span class="print-normal-toggle__label">Normal Class</span>
    </label>
@endif
@if($showPrint)
    {{-- timetable-print-v2 (2026-11-16): the REAL action. `window.print()` opens the
         browser's own dialog and prints the hidden paper surface this lane adds
         (partials/ui-print-header + ui-print-sheet) — no in-app print screen, no
         PDF library, no server render. The old "Printing is coming soon" toast, its
         tip and its comment are gone with the stub; the icon, the class and the
         32×32 / 48×48 geometry are unchanged, so nothing on the page moves. --}}
    <button type="button" class="print-btn" aria-label="Print this week" data-tip="Print this week" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
    </button>
@endif
