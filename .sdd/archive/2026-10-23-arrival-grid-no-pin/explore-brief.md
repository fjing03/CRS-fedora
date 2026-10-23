# Explore brief — arrival-grid-no-pin

Source: the user's law (2026-10-20, verbatim): "i think the URL pin
(?session=…&week=N) shud not PIN any week tho. i meant, doesn't matter
where they came from, …/replacement-home-ui or
…/venue-timetable-ui?venue=B014 or …/my-timetable-ui or
…/replacement-arrangement (no other params). it shud goes to the Earliest
Bookable week." + the ONE grill decision (2026-10-20): **A — the
destination auto-select stays** (the `?venue=&date=&time=` intents keep
their jump to the booked date's week + auto-select; the destination =
the cell the user picked seconds ago — not a stale pin).

## The decoded law

The occurrence pin (`?session=…&week=N`) is demoted from a
GRID-POSITIONING instruction to a pure DATA carrier:

- **The pin still owns**: `pinnedSessionPayload`'s week (the strip's
  "Week N · day, Mon DD" line + the submit pair target — the occurrence
  WEEK truth). No server change.
- **The pin no longer touches**: the GRID's landing position. Every
  entry path — my-timetable, replacement-home (both pin-carrying), the
  plain URL, even a URL whose ONLY job would have been the week — opens
  the calendar on the **Earliest Bookable week**.
- **Plain landings keep the Q2-A chain**: a saved week still wins on a
  plain no-pin visit (the working-week promise); earliest fills the
  memoryless void (the arrangement-earliest-week-default change, APPLIED
  `3c60977`, survives — this change amends its D-row3's landing meaning
  and nothing more of its shape).
- **Intent-less pinned arrivals land on Earliest Bookable ALWAYS**
  (saved week included — "doesn't matter where they came from")
  AND **combined pinned+intent URLs → the resolve jump wins the
  landing** (booked-date week) — the destination is the fresher of the
  two user-instructions (the A decision; A6's own URL family).
- Degenerate edge (end-of-semester): no bookable week ⇒ the pinned
  arrivals stand on week 1 (the same fall-through the memoryless family
  uses — `if (eb)`'s guard).
- **The destination auto-select (the intent family with date/time) is
  EXEMPT**: the destination is NOT a pin — it is the choice the user
  made seconds ago (the user's A decision; the resolve jump + the
  auto-placed block keep their contracts).

## What the current bridge does (receipts, live file post-3c60977)

`replacement-arrangement.blade.php` (reviewer-verified live anchors):

1. `:3534` (the bridge patch, in the boot): 
   `if (window.pinnedOccurrence) weekNav._currentWeek = pinned.week - 1;`
   — writes the pinned week over ANY landing rule.
2. `:3550` (after `populateWeekSelect`):
   `/* BRIDGE PATCH: now that the weekSelector is populated, rebuild the
   grid onto the pinned week… */ if (window.pinnedOccurrence)
   weekNav.selectWeek(window.pinnedOccurrence.week - 1);`
   — the second position writer; via `selectWeek` it ALSO
   `weekNav.save()`s (`arrangementWeek` = the pinned week — the working-
   week storage receives a pinned value the user never chose).
3. The `arrangement-earliest-week-default` D-row3 currently asserts this
   bridge behavior ("the pinned arrival wins even memoryless: the
   selector = 13 …").

## The specs' receipt inventory (what survives / what retunes)

| Suite/row | Verdict |
|---|---|
| `venue-cross-week-handoff` S1/S2 | **survives** — no selector/grid asserts; the URL + the strip + the pin pair stay truthful (the threading keeps feeding pinnedSessionPayload) |
| `session-arrival-pin-guard` S1/S2 | **retune** — the selector asserts (13 / the pinned week) + the `arrangementWeek`ノ boasts become the new law's opposite (the selector = the earliest-derived; no bridge save) |
| `session-arrival-pin-guard` S3 (click-through) | **survives** — pin + strip asserts only |
| `arrangement-earliest-week-default` D-row1/D-row2 | **survive** (plain = saved/earliest unchanged) |
| `arrangement-earliest-week-default` D-row3 | **rewrite** — the row's contract inverts: the pinned arrival lands EARLIEST (the pin keeps the data) |
| `venue-intent-modal` A6 | **survives by architecture** — its arrival = same-week (the destination = the picked cell's week = the pinned row's week in that flow), the destination jump covers the landing; re-baselined at the run |
| `replace-now` S5a/S7 | **survive** — URL asserts only |
| `default-pin` T18-S1/S2/S3 | **survive** — post-boot subject switches are NOT pinned arrivals; T6/B's auto-nav gates untouched |
| `ui-regression` TC-2.5 | **survives** — the saved-week contract is plain-landing-only |

## Rejected approaches

1. **Keep the bridge landing** — the user's explicit rejection (their
   W8 observation = the meet the law kills).
2. **Kill the resolve jump too** — rejected via the user's A (the
   destination = a fresh choice; killing it degrades the venue handoff
   to manual placement).
3. **A conditional pin landing** (pin lands only when it equals
   earliest — the coincidence-respect) — the T18-clobber disease in new
   clothes: the user wants certainty, not coincidences. REJECTED.
4. **An opt-out URL flag** — the law is unconditional; a flag would
   rebuild double-duty. REJECTED.
5. **Server-side removal of `week` from the payload** — would kill the
   strip/submit truth the pin must carry. REJECTED.

## Known open questions

None — the grill resolved the only fork (the destination), and the
saved/pinned interaction was encoded from the user's literal words
(pinned = earliest ALWAYS). The changelog + spec receipts make the
reversal auditable.
