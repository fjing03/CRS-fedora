{{-- ui-print-header — the paper's header (SDD timetable-print-v2, 2026-11-16).

     The requirement set is `ui-prototype/timetable-print-preview.html`'s DESIGN
     CONTRACT block (ONE HEADER / ONE SHEET / the key). This partial only PLACES the
     parts; the numbers live in theme.css's print region and are quoted from the
     contract's own comments.

     Structure (design §6.1): title + subtitle left · context centre · semester chip
     and the TWO-PART week line right · then the three-chip key, with the ownership
     hint on its own line under it.

     WHO FILLS WHAT. Title, subtitle, context and the ownership flag are this page's
     own values, passed in by the caller. The three nodes with ids are filled by the
     page at build time (PrintSheet, design §8.2) so the paper can never disagree
     with the screen:
       #printSemester   copied from #semesterChip
       #printWeekCount  `Week 5 of 14` — or `Week 1 ~ 14` in the normal view
       #printWeekDates  `· 21 Sep 2026 ~ 27 Sep 2026` — or `(Whole Semester)`

     ONE HEADER, PRINTED ONCE: this sits outside the table, so it prints on the first
     sheet only while the table's `thead` repeats (PR-06).

     Print-only: `.ui-print-header` is laid out off-viewport and invisible on screen
     (theme.css), which is also why the key is the paper's own three chips rather than
     the screen's `.legend-bar` — the screen key keeps every chip it has today, five
     on Venue included (PR-14). --}}
@php
    $printTitle = $printTitle ?? '';
    $printSub = $printSub ?? '';
    /* the centre owns the identity: a person's name + ID, a cohort code, or
       `<venue code> — <venue name>`. An em dash, spaces either side (never `--`). */
    $printContext = $printContext ?? '';
    $printOwnership = $printOwnership ?? false;
    /* The paper key. Three chips, always — `Available` is screen-only (blank paper
       also means held / too soon / holiday) and `Pending` never prints. */
    $printKeyItems = $printKeyItems ?? [
        ['cls' => 'normal', 'label' => 'Normal'],
        ['cls' => 'replacement', 'label' => 'Replacement'],
        ['cls' => 'conflict', 'label' => 'Conflict / Public Holiday'],
    ];
@endphp

<div class="ui-print-header" id="printHeader" aria-hidden="true">
    <div class="print-head-row">
        <div class="print-head-left">
            @if($printTitle)
                <h1 class="print-title">{{ $printTitle }}</h1>
            @endif
            @if($printSub)
                <p class="print-sub" id="printSub">{{ $printSub }}</p>
            @else
                {{-- a page whose subtitle is decided at runtime (Cohort's faculty) still
                     needs the node, or PrintSheet would have nothing to fill --}}
                <p class="print-sub" id="printSub"></p>
            @endif
        </div>

        <div class="print-head-centre">
            <span class="print-context" id="printContext">{{ $printContext }}</span>
        </div>

        <div class="print-head-right">
            <span class="semester-chip print-semester" id="printSemester"></span>
            <span class="week-line" id="printWeekLine"><span class="week-count" id="printWeekCount"></span><span class="week-dates" id="printWeekDates"></span></span>
        </div>
    </div>

    {{-- The key stacks: the chips on one line, the ownership hint hard left on the
         next. As a space-between row the two halves were flung to opposite edges of
         the page and read as unrelated (contract ruling, 2026-11-16). --}}
    <div class="print-key" id="printKey">
        @foreach($printKeyItems as $item)
            <span class="print-key-item"><span class="print-swatch print-{{ $item['cls'] }}"></span>{{ $item['label'] }}</span>
        @endforeach
        @if($printOwnership)
            <span class="print-ownership">thick border = your classes&nbsp;&nbsp;·&nbsp;&nbsp;thin border = others'</span>
        @endif
    </div>
</div>
