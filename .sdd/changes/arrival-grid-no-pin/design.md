# Design — arrival-grid-no-pin

## D1 — the boot edit (the two position writes retired)

Shipped today (post-3c60977):

```js
if (window.pinnedOccurrence) weekNav._currentWeek = window.pinnedOccurrence.week - 1;
else if (!hadSavedWeek) {
    const eb = firstBookableDay(weekData);
    if (eb) weekNav._currentWeek = eb.week;
}
populateWeekSelect('weekSelector', { ranges: false, selected: weekNav.currentWeek, … });
/* BRIDGE PATCH: now that the weekSelector is populated, rebuild the
   grid onto the pinned week (same call a user week-change makes). */
if (window.pinnedOccurrence) weekNav.selectWeek(window.pinnedOccurrence.week - 1);
```

After:

```js
/* ARRIVAL-GRID-NO-PIN (user law 2026-10-20): ?session=…&week= carries
   the occurrence PAIR (the strip + the submit target) — never the
   landing. Pinned arrivals land on the Earliest Bookable week ALWAYS
   ("doesn't matter where they came from"), even over a saved week.
   Plain landings keep the Q2-A chain (a saved week wins; earliest
   fills the memoryless void). The destination intents
   (?venue=&date=&time=) keep their resolve jump + auto-select (the
   user's A — the destination is a fresh choice, not a pin). The bridge
   also STOPS paying rent: selectWeek(pinnedIdx) used to persist
   arrangementWeek = the pinned week; the saved memory again = the
   user's own navigation only. */
if (!window.pinnedOccurrence && hadSavedWeek) {
    /* load() already restored the saved week (the plain working-week resume) */
} else {
    const eb = firstBookableDay(weekData);
    if (eb) weekNav._currentWeek = eb.week;
}
populateWeekSelect('weekSelector', { ranges: false, selected: weekNav.currentWeek, … });
/* (the post-populate selectWeek(pinnedIdx) call is DELETED with its
   BRIDGE PATCH comment — the grid now rebuilds at the landing rule's
   week via the boot's own buildTimetable; the pinned data flows on
   without steering the calendar) */
```

- The collapsed branch = `pinned-or-memoryless ⇒ earliest` — one
  `firstBookableDay` guard, no duplicated bodies.
- The pinned branch had NO other load-bearing uses of the bridge
  `_currentWeek` write (the strip's anchor reads the PAGE pin + local
  urlParams; the trigger note mirrors the pinned pair; the submit sends
  `window.pinnedOccurrence` — all independent of the nav position).
- The deleted `selectWeek(pinnedIdx)` also carried the
  `arrangementWeek` persistence — its death is the cleanup the T6/B
  work wanted ("pollution-free saved memory").
- The pinned-arrival TRIGGER note (:3544-family, "mirror the pinned
  occurrence's slot in the picker trigger") = untouched — the
  cosmetic anchor is exactly what the pin still owns.

## D2 — the receipts table

| Suite/row | Verdict | Action |
|---|---|---|
| `venue-cross-week-handoff` S1/S2 | survives | none (URL + strip + pin asserts; no landing asserts) |
| `session-arrival-pin-guard` S1 | retunes | the selector ⇔ earliest-derived via `firstBookableDay`; the `arrangementWeek` assert DELETED (the bridge save is dead) |
| `session-arrival-pin-guard` S2 | retunes | the same selector-family retune |
| `session-arrival-pin-guard` S3 | survives | pin + strip asserts only |
| `arrangement-earliest-week-default` D-row1/2 | survive | the plain chain unchanged |
| `arrangement-earliest-week-default` D-row3 | REWRITTEN | the pinned-memoryless arrival ⇔ the selector = the earliest + `arrangementWeek` untouched |
| `venue-intent-modal` A6 | survives | its arrival = same-week (the destination jump covers the landing; re-baselined at the run) |
| `replace-now` S5a/S7 | survives | URL asserts |
| `default-pin` T18-S1–S3 | survives | post-boot switches ≠ pinned arrivals; T6/B's gates untouched |
| `ui-regression` TC-2.5 | survives | the plain saved-week contract |

## D3 — the destination family (the A decision, encased)

`resolveBookingIntent` keeps: (1) the jump when the booked date lives in
another week; (2) the auto-placed block at the booked cell; (3) the
fallback prefill's toast family. NOTHING in this change touches it —
but the CHANGELOG's entry must record the exact boundary: **session
pins = data only; destination dates = landings** (the user's A).
AND on COMBINED pinned+intent URLs the resolve jump wins the landing
(the pinned pair rides the strip/submit regardless — the strip's anchor
= the PAGE pin + local urlParams, position-independent).

## D4 — the new spec (the law's receipts)

`tests/arrival-grid-no-pin.spec.ts` (staff-5770; the same add-init
clean-context discipline as the earliest suite):

1. **The pinned-memoryless arrival** (`?session=49&week=14`, fresh):
   the selector = the earliest-derived (`firstBookableDay`), the strip
   still reads 49's own facts (`(P)` · Week 14 · Dec 25 · B011), the
   pin = `{49, 14}`, and `arrangementWeek` = null (no bridge rent).
2. **The pinned-with-saved arrival**: seed `arrangementWeek` (a
   runtime bookable idx ≠ the earliest) → the pinned arrival ⇒ the
   selector = the EARLIEST (the saved loses on pinned landings).
3. **The destination keeps its jump** (the A contract's receipt):
   `?venue=B110&date=<W8 Tue>&time=11:00` memoryless → the grid's week
   = the booked date's week (not the earliest) — a light assert keyed
   to the resolve family's existing behavior (no C2/toast dependence).
4. **The plain rows** (re-run of the earliest suite's D-row1/2): the
   saved wins / earliest fills — unchanged.

## D5 — gates

Blade-JS only → `php -l` + the Playwright battery EXACTLY:
`arrival-grid-no-pin`, `session-arrival-pin-guard` (retuned),
`arrangement-earliest-week-default` (rewritten row), `default-pin`,
`replace-now`, `venue-cross-week-handoff`, `venue-intent-modal`
(C2 = the documented noise), `venue-book` — all
`--grep-invert "TC70"`. NO phpunit delta (no PHP edits —
`pinnedSessionPayload` untouched). Stale-cache ritual + re-auth if
needed.

## D6 — skipped batches

Condensed single-round batch review (the explore-brief baseline — the
pattern of the last five frozen rounds). `specs/` skip: the landing
chain + the receipt inventory live here; ≤2h single-boot edit + spec
retunes.
