# Proposal — arrival-grid-no-pin

## Why (the user's law, 2026-10-20)

Today a pinned arrival (`?session=97&week=8&from=my-timetable`) lands the
calendar on Week 8 — the occurrence's week — because TWO boot writes
position the grid there (the bridge's `weekNav._currentWeek` write +
`selectWeek(pinnedIdx)`, which also saves
`arrangementWeek = 8`). The user observed three of these handoffs and
ruled: "the URL pin shud not PIN any week… doesn't matter where they
came from… it shud go to the Earliest Bookable week."

The design-level distinction the grill resolved: the pin carries the
OCCURRENCE (the strip + the submit pair — untouched, server side
identical), but it must never STEER the calendar. The destination
auto-select (the `?venue=&date=&time=` intents) is NOT a pin — it is the
user's own fresh cell choice — and stays (their A decision).

The landing chain becomes:

| Landing | Stands on |
|---|---|
| Plain, saved week | the saved week (Q2 A — unchanged) |
| Plain, memoryless | Earliest Bookable (3c60977's default — unchanged) |
| **Pinned (any `?session=…&week=N`)** | **Earliest Bookable — ALWAYS** (the pin keeps the data; saved included — "doesn't matter where they came from") |
| Destination intent (`?venue=&date=&time=…`) | the booked date's week (the resolve jump — unchanged; the destination contract, not a pin) |

## In scope

- `replacement-arrangement.blade.php`: the bridge's TWO position writes
  removed/repurposed (the pinned branch joins the memoryless-earliest
  branch; the post-populate `selectWeek(pinnedIdx)` deleted; the
  shipped `arrangementWeek`-saving side effect of that `selectWeek` dies
  with it — the saved memory again = user's own navigation only).
- The retuned rows: `session-arrival-pin-guard` S1 ONLY (the selector
  becomes the earliest-derived; its `arrangementWeek` assert deleted —
  S2/S3 carry no landing asserts; reviewer round 1 🟡1), S1's inline
  T6-B comment rewritten too;
  `arrangement-earliest-week-default` D-row3 inverted + the suite's
  priority-chain header reworded (the new law: the URL pin carries
  data, never the landing).
- A dedicated new row: the pinned arrival + a saved week → STILL the
  earliest (the saved loses on pinned landings).
- Dated changelog entry.

## Explicitly NOT in scope

- `pinnedSessionPayload` / any server PHP (the pin = the strip + submit
  pair — untouched; the cross-week threading fix remains REQUIRED and
  unaffected: `&week=` feeds the pair, never the landing).
- The resolve jump + the auto-select for `?venue=&date=&time=` intents
  (the user's A — the destination contract).
- T18's post-boot auto-nav and T6/B's post-boot gate (the subject-
  switching families — outside arrivals).
- The Q2-A saved-week contract on plain landings.
- The trigger note + the strip (they describe the pinned occurrence —
  mirrored facts, unchanged).

## Impact scope

| File | Change |
|---|---|
| `resources/views/livewire/replacement-arrangement.blade.php` | the bridge's two position writes retired; the earliest-default applied unconditionally on pinned arrivals |
| `tests/session-arrival-pin-guard.spec.ts` | S1/S2's selector + memory asserts retuned |
| `tests/arrangement-earliest-week-default.spec.ts` | D-row3 rewritten |
| `tests/arrival-grid-no-pin.spec.ts` (NEW) | the law's rows (the pinned-memoryless = earliest; the pinned-with-saved = earliest; the pin still drives the strip/submit) |
| `page-changelogs/replacement-arrangement-changelog.md` | dated entry (the reversal + the A decision + the bridge-save cleanup) |

## Success criteria

- The user's three handoffs (`my-timetable`, `replacement-home`, the
  venue [Arrange]) land the calendar on the Earliest Bookable week
  while the strip still reads the pinned occurrence
  (`(P)`/`(L)` facts + Week N lines) and `window.pinnedOccurrence`
  still carries the pair the submit honours.
- A pinned arrival with a saved week stands on the Earliest Bookable
  week regardless.
- `arrangementWeek` gains NO bridge-written values anymore (only
  user-navigation positions).
- The destination intents (`?venue=&date=&time=`) keep the resolve jump
  + the auto-placed block (the A contract).
