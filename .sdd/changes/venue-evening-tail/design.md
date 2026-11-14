# Design — evening tail

> **Status:** draft · 2026-11-14

## 1. One concept: the served tail

```js
/* the last half-hour of the day that has a real room row */
function lastOfferIndex(di) {
    const week = roomCellStatesByWeek[currentWeek];
    if (!week) return -1;
    let last = -1;
    for (let k = 0; k < hours.length; k++) {
        if (week[di + ':' + k] !== undefined) last = k;
    }
    return last;
}

/* a cell past every served row, on a day whose offers reach the grid's final hour */
function isServedTail(di, hi) {
    const last = lastOfferIndex(di);
    return last >= hours.length - 3 && hi > last;
}
```

`hours.length - 3` = index 19 = 17:30, the hour that today's data ends on. A venue
whose rows stop earlier (a half-day) keeps its inert cells — deliberately.

## 2. `nextActualBlockAfter` stops at the last served row

```js
function nextActualBlockAfter(di, hi) {
    const last = lastOfferIndex(di);
    for (let k = hi + 1; k <= last; k++) {
        if (roomCellState(di, k) !== 'free') return k;
    }
    return Infinity;
}
```

Effect: a **served** cell's run now ends at the grid's end instead of at the
unoffered tail — `roomBeforeNextEvent(19)` (17:30) becomes `22 − 19 = 3 ≥ 2`, so
17:30–18:00 drops the "Not Enough Slot" label exactly as the user asked. A hole
**inside** the served range still ends the run (A21 preserved).

`roomBeforeNextEvent` itself is then expressed against the day end:

```js
function roomBeforeNextEvent(di, hi) {
    const next = Math.min(nextEventStartAfter(di, hi), nextActualBlockAfter(di, hi), hours.length);
    return next - hi;
}
```

(`Math.min(…, hours.length)` keeps every answer finite and equal to the old
`Infinity`-free form: `22 − hi`.)

## 3. Render: the tail joins the available branch

In the final `else` of `cellRender`:

```js
const cellState = roomCellState(di, hi);
const tail = cellState === 'nooffer' && isServedTail(di, hi);
if (cellState === 'free' || tail) {
    countedAvailable++;                       // the user: the tail counts
    …
    if (!tail && hi + MIN_BOOK_SLOTS > hours.length) div.classList.add('cell-no-fit');
    else if (roomBeforeNextEvent(di, hi) < MIN_BOOK_SLOTS) div.classList.add('cell-no-room');
    …
} else { … lane-spacer / cell-held … }
```

- 18:00: `tail`, room `22 − 20 = 2 ≥ 2` → plain green.
- 18:30: `tail`, room `22 − 21 = 1 < 2` → green + "Not Enough Slot" (no red).
- A served cell keeps the red `cell-no-fit` rule untouched.

A mid-day hole (`nooffer` before the last offer) still renders inert.

## 4. Counting

`countedAvailable` is the single point the AVAILABLE card reads, so the tail is
counted there and the card cannot disagree with the grid. This restores the
number the page showed before the A21 change.

## 5. Test contract

`tests/venue-book.spec.ts` derives `roomBeforeNextEvent` independently. Its
derivation is updated to the same law: the block scan is bounded by the day's last
served row, the day end is `hours.length`, and the red "Too Late to Book" arm
applies only to served cells.

Two consequences the review caught, both handled explicitly:

1. **`Infinity` can no longer occur.** `roomBeforeNextEvent` is capped by
   `hours.length`, so every answer is finite (`hours.length − hi` at most). The
   audit's `room === Infinity` branch and its `noNextBlocker > 0` assertion are
   therefore **removed and replaced** by the day-end ground below — not left to
   fail.
2. **A day-end room is grounded by the day end itself.** The old grounding check
   (`matrix[hi + room] !== 'free'`) passes vacuously when `hi + room ===
   hours.length`, because that index is `undefined`. The audit therefore accepts
   three explicit grounds: a live event starting at `hi + room`, a non-free
   **served** row there, or `hi + room === hours.length`.

## 5a. Notes from the review

- A tail cell carries the A19 base-row time label exactly like a served free cell,
  because it renders through the same branch (`if (ctx.base) …`). Where a day has
  no base row at all, the tail is a plain green cell — same as the served cells
  beside it.
- A cancelled **history** event covering the tail renders that history block, not
  a green cell: the history branch is reached before the room-matrix branch.
- `lastOfferIndex` counts a **blocked** (held) row as served on purpose — it is a
  real row, just not free, so it must end the run and must not extend the tail.

New assertions: on the venue page, 17:30 has no `cell-no-room`; 18:00 is available
and not `cell-no-room`; 18:30 is available with `cell-no-room` and not `cell-no-fit`;
all three are counted in the AVAILABLE card; a cell after a mid-day hole stays
inert.

## 6. Rejected alternatives

- **Stop the grid at 18:00** — the user wants 19:00 (already refused).
- **Create evening `time_slots` rows** — a data change, out of scope; offered
  separately.
- **Paint the tail without counting it** — the user ruled it counts, and the
  AVAILABLE card's own law is "what the grid renders bookable".

## 7. Round 2 — the evening becomes real (user, 2026-11-14)

After round 1 shipped, the user clicked 6:00 PM and got "Slot Too Short — runs into an existing
event below" (there was no event; the missing 18:30 row was being read as a blocker). Their
ruling: make the evening genuinely bookable.

**Data.** Two `INSERT … SELECT` statements add 18:00–18:30 and 18:30–19:00 as `available` rows for
every venue/week/day, copied from each day's 17:30 row (`time_slots` 38,640 → 42,504). Snapshot
first, rehearsed in a rolled-back transaction, logged in `docs/demo-db-data-changes.md`.
`TimeSlotsSeeder` now loops `$hour < 19` so a reseed reproduces them.

**Rule.** The red `.cell-no-fit` day-end arm is **retired** (user ruling): with real evening rows,
18:30–19:00 is an ordinary cell with 30 minutes left, and it must read the same as every other
"cannot fit" cell — green + "Not Enough Slot". `roomBeforeNextEvent` already answers
`hours.length - hi`, so the room arm alone produces it; no new arithmetic. The class is kept in CSS
(older cached views and the phone/print layers still reference it) but is no longer emitted.

**Consequence on the tail.** With rows everywhere the tail guard never fires — it stays as the
safety net for any venue/week that still lacks rows.
