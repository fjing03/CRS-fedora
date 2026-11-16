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

     #printEmpty is the paper's own empty panel: when the print set is empty the shared
     builder hides the table and shows this instead, so a week whose only blocks are
     pending prints the truth rather than a blank sheet (PR-11). --}}
<div class="ui-print-sheet" id="printSheet" aria-hidden="true">
    <div class="print-grid-wrap">
        <table class="print-table" id="printTimetable">
            <thead id="printTableHead"></thead>
            <tbody id="printTableBody"></tbody>
        </table>
    </div>

    <div class="print-empty" id="printEmpty">
        <strong id="printEmptyTitle">No classes this week.</strong>
        <span id="printEmptyText"></span>
    </div>
</div>
