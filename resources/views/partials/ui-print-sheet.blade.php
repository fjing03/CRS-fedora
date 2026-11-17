{{-- ui-print-sheet — the paper's table (SDD timetable-print-v2, 2026-11-16).

     THE PAPER IS ITS OWN TABLE, painted by the SAME buildTimetableGrid() the screen
     uses, through its opt-in `print` flag and its own four ids (design §1/§4). It is
     not a restyle of #timetable: a print CSS pass can only subtract, and the normal
     view prints a semester the screen never shows (proposal D1).

     Where it goes: AFTER the page's own #timetable wrapper, so `.first()` selectors in
     the existing suites keep finding the screen grid, and with its OWN class
     vocabulary (.print-table / .print-block / …) so the page-wide `.event-block` /
     `.timetable` scans in venue-status-parity / venue-note-privacy / venue-db do not
     gain a block (design §2.1).

     Off-viewport but LAID OUT — never `display: none`: the fit pass measures this
     sheet at the paper's content width, so it needs a real layout. `visibility` keeps
     it invisible and unfocusable without costing the measurement (design §5.1).

     The KEY closes the sheet, under the table (user, 2026-11-17): the prototype puts
     it at the bottom, where a reader checks a colour after reading the grid rather
     than above it between the header and the first row. It is the paper's OWN three
     chips — `Available` is screen-only and `Pending` never prints — and in the normal
     view the whole block goes (a key for one status explains nothing).

     #printEmpty is the paper's own empty panel: when the print set is empty the shared
     builder hides the table and shows this instead, so a week whose only blocks are
     pending prints the truth rather than a blank sheet (PR-11). --}}
@php
    /* The paper key. Three chips, always — `Available` is screen-only (blank paper
       also means held / too soon / holiday) and `Pending` never prints. */
    $printKeyItems = $printKeyItems ?? [
        ['cls' => 'normal', 'label' => 'Normal'],
        ['cls' => 'replacement', 'label' => 'Replacement'],
        ['cls' => 'conflict', 'label' => 'Conflict / Public Holiday'],
    ];
    $printOwnership = $printOwnership ?? false;
@endphp
<div class="ui-print-sheet" id="printSheet" aria-hidden="true">
    <div class="print-grid-wrap">
        <table class="print-table" id="printTimetable">
            <thead id="printTableHead"></thead>
            <tbody id="printTableBody"></tbody>
        </table>
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

    <div class="print-empty" id="printEmpty">
        <strong id="printEmptyTitle">No classes this week.</strong>
        <span id="printEmptyText"></span>
    </div>
</div>
