# Design — venue-cross-week-handoff

## D1 — producer edits (2, `venue-timetable.blade.php`)

`confirmIntentSession` (:552–562) reads the picker's active row like it
already reads sid/cohort, and passes the row's own occurrence week:

```js
const pickedRow = dd.querySelector('.session-dd-item.active');
bookVenueWithSession(venueInfo.code, intentContext.dateStr, intentContext.timeStr, moduleLabel,
    pickedRow ? pickedRow.dataset.sid : '',
    pickedRow ? (pickedRow.dataset.cohort || '') : '',
    pickedRow ? (pickedRow.dataset.week || '') : '');
```

`bookVenueWithSession` (:565) gains the 7th arg and writes it, keeping the
legacy fallback verbatim-equivalent:

```js
function bookVenueWithSession(venueCode, date, time, code, sessionId, cohortLabel, occurrenceWeek) {
    …
    + '&week=' + encodeURIComponent(occurrenceWeek ? occurrenceWeek : currentWeek + 1);
```

- Rows WITHOUT `dataset.week` (the builder's `week: ''` rows) keep today's
  behaviour byte-for-byte: the fallback `currentWeek + 1`.
- Everything else in the builder (sessionStorage booking-intent hygiene,
  the param set order) untouched.

## D2 — the consumer is deliberately NOT edited (reviewer round 1 🔴)

The `:2590` pin-bridge sits behind `armIntentFallbackPrefill`'s
short-circuits (`:2539–2542`), including `if (urlParams.session) return;`
at `:2541` — mutually exclusive with a hydrated pin — so no venue
`?session=` flow reaches it; a "same-session pin-keep" guard there is
unreachable dead code with a comment describing an impossible event.
The venue path's genuine re-pin risk is the T18 clobber at `:2966`
(boot `:3523` `applyUrlParams` → `:3409–3413` sel.value +
`onSubjectChange()`) — owned by `session-arrival-pin-guard`. After BOTH
changes land: the threaded `&week=8` pin `{97, 8}` survives the boot
untouched, no longer rescued by the 97-is-earliest coincidence.

## D3 — param semantics after (the meaning table)

| Param | Before | After |
|---|---|---|
| `&week=` | overloaded: "the viewed slot week" AND "the occurrence week" | **the pinned occurrence's week** (what `pinnedSessionPayload` always assumed) |
| `&date`/`&time` | the slot's landing pad (`resolveBookingIntent` early-returns without them) | unchanged — the slot's own facts |
| `&session`/`&code`/`&cohort` | unchanged | unchanged |

`resolveBookingIntent` keeps landing the ARRIVAL grid on the slot's week
(if `date` lives in another week, it jumps) — unchanged; only the
pinned OCCURRENCE (strip + submit) follows the picked week.

## D4 — byte-parity matrix (verified shapes + reviewer round 1)

| Flow | Today | After | Why identical |
|---|---|---|---|
| Same-week venue pick (slot week N, occurrence week N) | `&week=N` | `&week=N` | row.week == currentWeek + 1 |
| The other real `?session&week` producers — `replacement-home.blade.php:533` (the occurrence's `week_number`) and the arrangement's own `:3217` (`slot.week + 1`) | `&week=N` (honest already) | untouched | different sites, already truthful |
| A6 (`venue-intent-modal.spec.ts:74`) | `&week=8` shape | same shape | its pick = the W8 row; assert is shape-only |
| C2 fallback prefill (`?venue&date&time`, no session) | byte-identical | byte-identical | no producer edit in its flow |
| Legacy `week:''` rows | `currentWeek + 1` | `currentWeek + 1` | the explicit fallback |

## D5 — interplay with `session-arrival-pin-guard` (honest residual)

Independent mechanisms. With THIS change alone and a cross-week pick of a
NON-earliest occurrence (e.g. the W14 `(P)` row from that same W7 slot), the
URL + server pin = the picked `{49, 14}` (the phantom base pair is gone)
but the T18 clobber still rewrites the pin to `{the earliest, 8}` — the
strip's slot line reads the picked W14 while the pin files the earliest:
the silent divergence exists until `session-arrival-pin-guard` lands.
Full end-to-end truth for non-earliest cross-week picks = BOTH changes.
T6's changelog entry must say exactly this (no "total truth-keeping"
claim from this change alone).

## D6 — verification vehicle

NEW `tests/venue-cross-week-handoff.spec.ts` (staff-5770 storage state;
reuse the venue-intent-modal helpers' shape):

1. Repro row: `?venue=B111` → W7 view (`#weekSelect` '6'); pick the free
   Tue 13:00 cell; the intent modal's session dd; click the
   `(L) W8, Mon, Nov 9` row — anchor the locator regex to the A1
   byte-frozen label form (`Aug|Nov 9`, no leading zero) to stay flap-free;
   `#intentArrangeBtn`; assert the URL `session=97&…&week=8`; assert the
   strip's `#subjectInfo` contains `Week 8 · Mon, Nov 9 2026 · 11:00
   AM–1:00 PM @ B110`; keep the pin assert as a secondary receipt
   (non-discriminating by design D5's coincidence note — do NOT rely on
   it alone to prove the fix).
2. Same-week control: pick a slot + its same-week occurrence row → the
   URL equals today's composed value (byte-parity guard).

Gates: blade-JS only → `php -l` on the venue blade + the Playwright run
(`npx playwright test tests/venue-cross-week-handoff.spec.ts
tests/venue-intent-modal.spec.ts tests/venue-book.spec.ts
tests/default-pin.spec.ts --grep-invert "TC70"` — A6 + venue-book's
8-param assert + default-pin's hydrated-pin boot rows = the twin
surfaces); phpunit not in the delta (no PHP file touched). Stale-cache
ritual before runs.

## D7 — skipped batches

Single-round condensed batch review (explore-brief is the baseline;
proposal/design/tasks reviewed together; round 1 verdict folded in —
see review-log.md) — 2-edit bug fix, each task ≤2h; `specs/` skip (the
URL contract is written out in D3/D4; no cross-module spec surface).
