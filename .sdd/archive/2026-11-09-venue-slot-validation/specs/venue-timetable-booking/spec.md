# Spec — venue-timetable-booking (slot validation)

Delta on the existing `venue-timetable-ui` behaviour. Only the statements below change; every
other rule of the page (grid geometry, lead-time cells, occupied/pending/reserved/PH rendering,
tooltip design, handoff URL contract) is unchanged.

## Requirement R1 — Hover floor is one continuous hour

- A free cell whose remaining room before the next existing event on the same day is **less than
  60 minutes** MUST render `cell-no-room` (cursor `not-allowed`, hover words "Not Enough Slot",
  normal available background) and MUST NOT open the arrange pop-up.
- A free cell starting later than **day end − 60 minutes** MUST render `cell-no-fit`
  ("Too Late to Book", error container) and MUST NOT open the arrange pop-up.
- The floor is a constant of the page. It MUST NOT be derived from any class in the arrange pool.
- Scenario: B002, Week 9, Monday, next class 14:00 → 13:30 is `cell-no-room`; 13:00, 12:30 and
  12:00 open the tooltip.

## Requirement R2 — No duration verdict before a class is chosen

- Opening the pop-up, or changing the subject, MUST NOT display any duration/overlap warning and
  MUST NOT hide the pickers.
- Every session of the selected subject MUST be listed in the session dropdown, whatever its
  duration (one row per flagged occurrence, as today).
- When at least one listed session fits the clicked cell, that session MUST be pre-selected; when
  none fits, the first session MUST be pre-selected and the Arrange button MUST stay enabled.
- Scenario: 13:00, subject `AMSE1003` (2-hour lecture only) → the modal shows no notice, the
  pickers are visible, the Arrange button is enabled.

## Requirement R3 — Validation after selection

When the user presses **Arrange** with a picked session:

1. `start + duration > day end` → day-end warning, no navigation.
2. the span `[start, start + duration)` overlaps an existing event that day → span warning, no
   navigation.
3. otherwise → the existing handoff MUST run unchanged
   (`/replacement-arrangement?venue=…&date=…&time=…&from=venue-timetable&code=…&session=…&cohort=…&week=…`).

The duration used MUST be the picked session's own `durationSlots`.
Scenarios: 2-hour class at 12:00 → navigates; at 12:30 → warning; at 13:00 → warning;
1-hour class at 13:00 → navigates.

## Requirement R4 — Warning modal

- The failure surface MUST be an in-page warning modal (`.modal-overlay`, warning icon), not a
  browser alert, and MUST NOT navigate.
- Span copy: head `None of your classes can run in <venue> at this cell`; body
  `<start> plus the selected class duration (<duration>) runs into an existing event below` and,
  when a next class is known, ` — the class starting at <next>.`; guide
  `NOT ENOUGH FREE TIME BELOW`; tail `— pick a Free Cell.`
- Day-end copy: head `This class does not fit before the day end (<dayEnd>)`; body
  `<start> plus the selected class duration (<duration>) runs past <dayEnd>.`; guide
  `PICK AN EARLIER CELL`; tail `— the grid ends at <dayEnd>.`
- `<start>`, `<next>` and `<dayEnd>` MUST be the grid's own `HH:MM` labels; `<duration>` MUST be
  the picked session's real duration (`1 hour`, `1.5 hours`, `2 hours`, …).
- The modal MUST offer a clear close (footer action + header ×) that returns the user to the
  timetable with the intent modal closed.

## Requirement R5 — Unchanged surfaces

- The room-type notice (`This venue's <type> hosts only …`) MUST still appear when the arrange
  pool is empty for the venue.
- The advisory block, the tooltip wording, the printed week, the earliest-bookable button, the
  `Bookings open` chip and the red-cell toast MUST keep their existing content except the toast's
  number, which becomes the fixed 60-minute floor.
