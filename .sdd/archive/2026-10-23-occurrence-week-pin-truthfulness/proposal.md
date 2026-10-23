# Proposal — occurrence-week-pin-truthfulness

## Why
A MY-Timetable popup's "Replace Now" handed off the VIEWED week instead of
the conflicted occurrence's own week; the arrangement accepted any `?week=`,
and `submitBlocks` carried a silent `$occurrenceWeek ?? $week` fallback. The
live incident: request #11 booked `week_number = 5` while the replaced
occurrence lived in W14 (holiday); its approval then stamped a WRONG
`class_exceptions (67, 5)` and freed the legit W5 block(s). Truthfulness =
every submit must pin the REAL conflicted occurrence.

## In scope (F1–F6, per the handoff inventory)
- **F1** `SubmitReplacementRequest`: occurrence-conflict validation — reject
  `<session, week>` pairs that carry NO holiday/exception/venue-restriction
  fact with the loser `occurrence_not_conflicted` (+ polite conflictText),
  borrowing the SAME fact derivation the home list uses
  (`LectureSessionCatalog::conflictMapFor` family) — ONE source. Within
  the file, the hard-coded week bound 14 aligns to `$semester->week_count ?? 14`.
- **F2** `ReplacementArrangement::submitBlocks`: the silent fallback dies —
  a missing `window.pinnedOccurrence` → loser `no_occurrence_pin`.
- **F3** the arrangement blade: client-side gate — submits blocked with the
  same polite copy when no pin is hydrated; the slot-picker pick re-pins
  (existing code); Replace-Now URLs hydrate the pin (existing code).
- **F4** the my-timetable Replace-Now gate + handoff: ONLY occurrences with
  a live conflict fact (holiday / class_exceptions / venue-restriction) get
  the button, and the handoff week = THAT occurrence's week (the fact's
  OWN week — never `window.currentWeek`; its server-render initializer
  pair stays, and after this change it has zero stale-state readers on
  my-timetable).
- **F5** `VenueTimetable`'s replacement events gain `originalStartTime /
  originalEndTime / originalVenue / originalVenueName` (tokens only).
- **F6** tests `tests/Feature/OccurrencePinTest.php`: no-pin → rejected;
  normal-week occurrence → `occurrence_not_conflicted`; the holiday week →
  accepted; the positive control (approve keys D3 onto the ORIGINAL week;
  frees nothing since no W14 rows exist); F5 payload keys.

## Explicitly NOT in scope
- span-aware-approval-flow leftovers (the D3 QueryException catch + the
  bulk tests) — a LATER mini-SDD (user ruling Q1).
- Route C's demo-data repair runs ONLY as the handoff §8's user-run pgAdmin
  script (the demo DB is NEVER written by application code); no
  `(67,14)` exception stamp gets inserted here (the user redoes the approve
  through the fixed UI).
- No legend/colour/copy re-design; the `(Replaced for …)` note text stays
  verbatim (user-voted).

## Impact scope
| File | Change |
|---|---|
| `app/Actions/SubmitReplacementRequest.php` | F1's occurrence-fact validation |
| `app/Livewire/ReplacementArrangement.php` | F2's pin-mandatory + F4-shared fact reading (server side) |
| `resources/views/livewire/replacement-arrangement.blade.php` | F3's client gate |
| `resources/views/livewire/my-timetable.blade.php` | F4's gate + the occurrence-week handoff |
| `app/Livewire/VenueTimetable.php` | F5's four payload keys |
| `tests/Feature/OccurrencePinTest.php` | F6's new suite |
| changelogs | `my-timetable`, `replacement-arrangement`, `venue-timetable` |

## Success criteria
- Replace Now from the my-timetable popup hands off the fact's own week
  (e.g. `?session=67&week=14&from=my-timetable`, NOT the stale viewed week)
  and the popup shows the button ONLY when a live fact exists for that
  exact `<session, week>`.
- A picked-but-unpinned arrange submit is rejected server-side and blocked
  client-side with polite copy (no silent fallback anywhere).
- (Where the user-run Route-C repair SQL lives: run-file §8
  `prompts/run-occurrence-week-pin-truthfulness.md` — /sdd-verify cites it.)
- After Route C's user-run repair + the fixed-UI redo, request #11's
  replacement records week 14 and its approval stamps `(67,14)`, freeing nothing.
- Gates: phpunit 222 + new ✓, phpstan 0, Playwright in-scope suites (TC70
  never runs).
