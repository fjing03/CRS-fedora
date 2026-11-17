{{-- Filters row + panel frame (SDD filters-button, 2026-11-15) — usage:
    @include('partials.ui-filter-panel', [
        'filtersCount' => true,                                // false = no #resultCount (student page)
        'filtersCountTip' => 'Classes matching the current filters',
        'filtersReset' => true,                                // staff pages only
        'filtersResetOnclick' => 'resetAllFilters()',
        'filtersResetTip' => 'Reset all filters to DEFAULT',
    ])

    This partial emits TWO things and owns neither of the page's controls:

    1. the filters row (#filtersRow) — the Filters button, the chip bar and the count
       pill. The button stays hidden until the module adds `.filters-ready` to <html>
       (design §2), so a page whose JS never runs shows today's toolbar inline.
    2. the panel frame (#filtersOverlay > #filtersPanel) — head + an EMPTY body.
       The page's own `.toolbar` is MOVED into #filtersPanelBody by FiltersPanel
       (design §1–2): moved, never rebuilt, so every control id and listener survives.

    #filterChips and #resultCount keep their ids (and classes) because the pages' own
    JS writes into them — the chip repaint and updateResultCount() keep working.
--}}
@php
    $filtersCount = $filtersCount ?? true;
    $filtersCountTip = $filtersCountTip ?? 'Rows matching the current filters';
    $filtersReset = $filtersReset ?? false;
    $filtersResetOnclick = $filtersResetOnclick ?? '';
    $filtersResetTip = $filtersResetTip ?? 'Reset all filters to DEFAULT';
@endphp

<div class="filters-row" id="filtersRow">
    <button type="button" class="filters-btn" id="filtersButton"
            aria-haspopup="dialog" aria-controls="filtersPanel" aria-expanded="false">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        <span>Filters</span>
    </button>
    <div class="filter-chips" id="filterChips"></div>
    @if($filtersCount)
        {{-- 2026-11-17 (P2 pointer contract, phase 2): the tip explains what is being counted, which
             the number cannot say, and it only ever appeared on a hover. The same sentence now sits
             beside the counter for assistive tech. It is a SIBLING on purpose: updateResultCount()
             rewrites #resultCount with textContent, so a child would be wiped on every filter change. --}}
        <span class="result-count" id="resultCount" data-tip="{{ $filtersCountTip }}"></span>
        <span class="sr-only">{{ $filtersCountTip }}</span>
    @endif
</div>

<div class="filters-overlay" id="filtersOverlay" hidden>
    <div class="filters-panel" id="filtersPanel" role="dialog" aria-modal="true"
         aria-labelledby="filtersPanelTitle">
        <div class="filters-panel-head">
            <h2 class="filters-panel-title" id="filtersPanelTitle">Filters</h2>
            @if($filtersReset)
                <button type="button" class="filters-panel-reset" id="filtersResetButton"
                        data-tip="{{ $filtersResetTip }}"
                        onclick="{{ $filtersResetOnclick }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="1 4 1 10 7 10"/>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                    </svg>
                    <span>Reset</span>
                </button>
            @endif
            <button type="button" class="filters-panel-close" aria-label="Close Filters">&times;</button>
        </div>
        <div class="filters-panel-body" id="filtersPanelBody" wire:ignore></div>
    </div>
</div>
