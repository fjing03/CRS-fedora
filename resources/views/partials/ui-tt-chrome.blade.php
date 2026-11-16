{{-- ui-tt-chrome — the Batch A shell of SDD my-timetable-responsive-prompt
     (lane frozen 2026-11-15). This is the lane's ONLY new shared view: it wraps the
     caller's own week row (a STATIC wrap, never a runtime move), then emits the day
     strip, the tools row, the agenda mount, the next-class hint and the Week summary
     sheet — markup contract = design §4.1, byte for byte.

     Every control here is bound by TimetableResponsive (public/js/ui-common.js); there
     is no inline onclick, so nothing needs exporting. Every rule that styles this
     markup lives under `.tt-responsive` in public/css/theme.css, so a page that does
     not carry the scope class renders exactly as it does today (R1).

     NOT WIRED YET: no blade includes this file in Batch A. My Timetable opts in at T11
     (phase A) and Student My Timetable at T14 (phase B). --}}
@php
    /* Blade vars (design §4.1):
       - $ttScope    the class hook the CALLER puts on its page root
                     (`class="lw-page tt-responsive"`, design §1/§7.1). Kept here as a
                     documented default so both timetable blades pass one vocabulary;
                     the partial itself has no root to put it on.
       - $ttReadonly false on the staff page, true for phase B (R9). It changes nothing
                     visible yet — it is the phase-B hook, and Batch B–D read it from
                     `cfg.readonly` once T11/T14 pass it through.
       - $ttWeekNav  the caller's already-rendered partials.ui-week-nav HTML. The include
                     only WRAPS it; ui-week-nav is never edited (design §8). */
    $ttScope = $ttScope ?? 'tt-responsive';
    $ttReadonly = $ttReadonly ?? false;
    $ttWeekNav = $ttWeekNav ?? null;
@endphp

{{-- the caller's week row is wrapped HERE (a static wrap in the blade) so it is
     sticky exactly as the approved preview has it — R5. --}}
<div class="tt-sticky" id="ttSticky">
    @if($ttWeekNav)
        {{-- the caller's own @include('partials.ui-week-nav', …), rendered by the
             caller and passed in: the partial wraps it, it does not re-render it --}}
        {!! $ttWeekNav !!}
    @endif
    <div class="tt-days" id="ttDayStrip" role="group" aria-label="Days of the week"></div>
</div>

<div class="tt-tools" id="ttTools" hidden>{{-- ships hidden; TimetableResponsive.sync() unhides it below 1280 (R6) --}}
    <div class="tt-heading-block">
        <h2 class="tt-day-heading" id="ttDayHeading"></h2>
        <span class="tt-day-count" id="ttDayCount"></span>
    </div>
    <button type="button" class="tt-summary-btn" id="ttSummaryButton"
            aria-haspopup="dialog" aria-controls="ttSheet" aria-expanded="false">
        {{-- bar-chart glyph, 14px, aria-hidden (design §4.1) --}}
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" aria-hidden="true">
            <path d="M4 20h16"/>
            <path d="M7 16v-5"/>
            <path d="M12 16V5"/>
            <path d="M17 16V8"/>
        </svg>
        <span>Week summary</span>
    </button>
</div>

{{-- the module's own transposed grid (768–1279, Batch C) — hidden until then --}}
<div class="tt-window-grid" id="ttWindowGrid" hidden aria-label="Timetable for the visible days"></div>

{{-- the agenda the module builds from the served payload (≤767, Batch B) --}}
<div class="tt-agenda" id="ttAgenda" aria-label="Classes for the selected day"></div>

{{-- next-class hint: empty day / public holiday only (Batch B) --}}
<div class="tt-next-hint" id="ttNextHint" hidden></div>

{{-- Week summary sheet (≤1279, Batch D). Its body is FILLED by the module from the
     page's own nodes (legend bar + the five cards), moved rather than copied, so
     `wire:ignore` keeps Livewire's morphs out of the relocated nodes. The head carries
     a SUBTITLE (user, 2026-11-15, following the prototype: `Week 8 · 202605 Semester ·
     21 Sep 2026 ~ 27 Dec 2026`), which is why no range line sits in the body. --}}
<div class="tt-sheet-overlay" id="ttSheet" hidden>
    <div class="tt-sheet" role="dialog" aria-modal="true" aria-labelledby="ttSheetTitle">
        <div class="tt-sheet-head">
            <h2 class="tt-sheet-title" id="ttSheetTitle">Week summary<span
                    class="tt-sheet-subtitle" id="ttSheetSubtitle"></span></h2>
            <button type="button" class="tt-sheet-close" aria-label="Close week summary">&times;</button>
        </div>
        <div class="tt-sheet-body" id="ttSheetBody" wire:ignore></div>
    </div>
</div>
