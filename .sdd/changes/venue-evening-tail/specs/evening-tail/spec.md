# Spec — evening tail

> **Change:** `venue-evening-tail` · 2026-11-14

## R1 — The grid still ends at 19:00
The 22 half-hour columns are unchanged; no column is removed.

## R2 — A day's unoffered tail is painted, not blank
- On a day whose last served offer sits in the grid's final hour, every cell after
  it renders as an available (green) cell.
- Measured on B101 week 9: 18:00–18:30 and 18:30–19:00 are green, not blank.

## R3 — The last half-hour reads "Not Enough Slot"
- 18:30–19:00 carries `cell-no-room` and the "Not Enough Slot" hover.
- It does **not** carry `cell-no-fit` (no red "Too Late to Book").
- 5:30–6:00 carries **neither** class (plain green).

## R4 — The tail counts as Available
- The AVAILABLE summary card counts the tail cells, so it equals the number of
  green cells the grid draws.

## R5 — Mid-day holes stay inert
- A cell with no served row that is followed by served rows keeps its inert
  (spacer) render — A21 is not repealed.
- A day whose last served offer is NOT in the final hour keeps every unoffered
  cell inert (no venue is painted green to 19:00).

## R6 — Served cells keep their rules
- A served free cell still takes the red `cell-no-fit` at the real day end.
- The next-block scan still ends a run at a non-free served row or a live event.

## R7 — The audit's teeth survive the new law
- `roomBeforeNextEvent` is always finite (`≤ hours.length − hi`); the audit no
  longer expects `Infinity`.
- A finite room must be grounded by exactly one of: a live event at `hi + room`,
  a non-free **served** row at `hi + room`, or `hi + room === hours.length`.

## Acceptance

- `tests/venue-book.spec.ts` derivation updated to this law, the `Infinity` arm
  replaced by the day-end ground (R7), the new assertions (R2–R5) added.
- `venue-timetable` / `venue-status-parity` / `venue-book` specs unchanged in
  intent.
- Read-only measurement on the demo lane recorded in the changelog.

## R8 — The evening is genuinely bookable (round 2, user 2026-11-14)

- Every venue/week/day carries 18:00–18:30 and 18:30–19:00 `available` rows.
- A 1-hour booking at 18:00 is accepted by the normal booking path.
- `TimeSlotsSeeder` reproduces the same 42,504 rows (loop `< 19`).

## R9 — One label for "cannot fit"

- No cell emits `.cell-no-fit`; the red "Too Late to Book" day-end state is retired.
- 18:30–19:00 is green with `cell-no-room` and the "Not Enough Slot" hover.
