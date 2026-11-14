# Proposal — the evening tail of the venue grid

> **Status:** draft · 2026-11-14 · user report with a live screenshot (B101, week 9)

## Why

The venue grid draws half-hour columns 08:00–19:00 (22 cells), but the room's real
offer rows stop at a **17:30 start** (last slot 17:30–18:00). After the
`cancelled-slot-reuse` A21 rule ("no served row ⇒ inert cell") the two tail cells,
18:00–18:30 and 18:30–19:00, render **blank** — a white hole at the right edge of
every day.

The user's ruling (2026-11-14), in their words:

- `5:30–6:00` → plain green, **no** "Not Enough Slot" hover;
- `6:00–6:30` → plain green, like before;
- `6:30–7:00` → green with the "Not Enough Slot" hover, like the 5:30 cell is today;
- all three **count as Available**.

So the grid keeps ending at 19:00, the tail is painted and counted, and only the
final half-hour (30 minutes left in the day) reads "Not Enough Slot".

## What changes

1. A day's **tail** = the cells after its last served offer row, and only when
   that last offer sits in the grid's final hour (so a half-day venue is never
   painted green to 19:00).
2. Tail cells render through the normal available branch: green, counted, with
   the usual time label on a base row.
3. The "next block" scan stops at the last served offer, so a served cell no
   longer counts the unoffered tail as a blocker — that is what removed the false
   "Not Enough Slot" from 17:30–18:00.
4. A tail cell takes "Not Enough Slot" when fewer than MIN_BOOK_SLOTS half-hours
   remain before 19:00 (18:30 = 1 left), and **never** the red "Too Late to Book"
   (which stays the real-offer day-end rule).

## Known limitation (stated, not hidden)

The room still has no offer row after 17:30, so 18:00–19:00 cannot actually be
booked: the pop-up opens, and the submit is refused. This change restores the
grid's previous look and count; it does not create evening bookings. Making the
evening genuinely bookable is a data change (new `time_slots` rows) and a
separate decision.

## Out of scope

Booking rules, `time_slots` data, working hours, auto-reject, other pages' grids,
the arrangement page's mobile block.

## Risks

| Risk | Mitigation |
|---|---|
| A half-day venue painted green to 19:00 | Tail requires the last offer in the final hour |
| Summary `Available` grows | It is the same number the grid showed before A21; asserted |
| A green cell promises a booking it cannot take | Stated in the changelog; the existing "not available" refusal is unchanged |
| The independent derivation test in `venue-book.spec.ts` models the old law | Its derivation is updated to the new law and keeps every grounding assertion |
