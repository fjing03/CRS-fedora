# Design — notif-jump-flash-family

## D1 The theme family (the ONE addition — the cell arm)

theme.css (beside the existing :2305–2310 rule):

```css
/* the CELL-dimension pulse (notif-jump-flash-family): the today cells
   (the td itself) — the SAME bookable-pulse */
td.bookable-flash {
    animation: bookable-pulse 1.15s ease-in-out 1;
}
```

(The today-column tds animate DIRECTLY — my-timetable/cohort today cells
contain .event-block/.cell-empty, never .cell-content; the div-level arm
`.cell-content.bookable-flash` for the arrangement's pulseTargetCell
belongs to THIS lane too — see D3.)

The full arm set after both lanes land (each lane owns its arm):
- lane A (notif-click-highlight): `.event-block.bookable-flash` (the
  my-timetable s-target flash);
- lane B (this lane): `td.bookable-flash` + `.cell-content.bookable-flash`
  (the today cells + the arrangement's selected-cell target).

## D2 The today button (EVERY week-nav page)

`ui-common.js jumpToToday()` — after the existing build + `scrollIntoView`:

```js
/* notif-jump-flash-family: the today column flashes (the same family as
   #earliestBtn — jump then pulse) */
setTimeout(function () {
    document.querySelectorAll('td.today-cell').forEach(function (td) {
        td.classList.remove('bookable-flash');
        void td.offsetWidth;
        td.classList.add('bookable-flash');
        setTimeout(function () { td.classList.remove('bookable-flash'); }, 1300);
    });
}, 250);
```

(250 ms = after char of the scroll; the per-cell remove mirrors the row
 EEPROM pulse; no change to any other jumpToToday behaviour.)

## D3 The summary-card pulse swap (the arrangement)

SPLIT pulseTargetCell (the SAME-color law — the blocked-cell attention
ring must stay BLUE):

```js
/* the summary-card jump: the SUCCESS family flash (exact earliestBtn look) */
function flashTargetCell(dayIndex, hourIndex) {
    const cell = document.querySelector(`#tableBody td[data-day="${dayIndex}"][data-hour="${hourIndex}"] .cell-content`);
    if (!cell) return;
    cell.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    flashRowPulse(cell);   /* the shared bookable-family pulse */
}
/* navigateToSavedBlock calls flashTargetCell(...) instead of pulseTargetCell */

/* pulseTargetCell stays PRIVATE attention-pulse for the FOUR blocked-cell
   callers (the booking-intent manual-guide :3034, in-the-way :3218,
   manual-reasons :3235, + pulseSubjectSelector :1912) — those cells CANNOT
   be placed: a success-green ring would violate the same-color-same-meaning
   canon (the blue ring = attention). */
```

- The `attention-pulse` keyframes/class stay in the blade (the blocked
  -cell family retained).
- The sel-summary-card navigation (navigateToSavedBlock's selectWeek +
  select) UNTOUCHED.

## D4 The receipts

- :8030 (or :8000 for the read-only pages — the today jump writes NOTHING;
  the :8000 receipt is fine for the timetable pages): on my-timetable and
  venue, click #todayBtn → the first TODAY cell(s) gain `bookable-flash`
  for ~1.15 s then lose it; the grid scrolls to the top; row ordering
  unchanged.
- The arrangement: click a sel-summary-card → the venue+week switch lands →
  the target cell pulses with the success family (NOT the blue ring);
  receipted.
- The regression: the pending-intent path (:3034) also shows the success
  pulse (intended, receipted with the same run).

## D5 Ordering with notif-click-highlight

- notif-click-highlight lands FIRST (its apply adds `flashRowPulse` to
  ui-common). notif-jump-flash-family then consumes it. If the apply order
  reverses, D2/D3 inline the same block — the ordering protects the
  promote law.
