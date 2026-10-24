# design — `class-modal-affordances`

## D1 — the wiring map (per page; the legacy templates are the port source)

All three pages share the pattern: AT the modal-open hook, after the
shared modal content fills, call

```js
CancelClass.renderButton(
    document.querySelector('#<modalId> .modal-footer'),
    event, daysContext, weekIndex, onCancelled);
```

- **my-timetable** (`classModal`): the open path = the grid's block-click
  handler that fills `currentModalEvent` (the populated footer already
  carries Replace Now via `@section('modal-footer')`); the Cancel call
  goes right beside that fill. The `onCancelled` callback port = the
  legacy MyTimetable template's (re-derive + re-render the grid, mark the
  block cancelled in memory, run `ClassCancellation.showUndoToast()`).
  The footer-right already exists — renderButton appends after Replace
  Now (both bottom-right; the ERROR-colored Cancel + the primary Replace
  Now = the restored Oct 8 pairing).
- **cohort-timetable** (`eventModal`): the pages own the modal-open at
  the cohort grid's block click (`checkOwnership: e.lecturer ===
  MockData.currentUser.name` already feeds its own fields). The wire
  passes `days`/`weekIndex` from the SAME WEEK scoping the cohort grid
  uses (the legacy CohortTimetable template's exact context — the design
  pins during apply with receipts). The footer: the live cohort blade has
  no `@section('modal-footer')` → the wiring gains one (left: Close;
  right: the Cancel button — the directive holds).
- **venue-timetable** (`classModal`): the modal opens from the venue
  grid's cell click (the `dataset.sid`-carrying eventsData rows). Same
  wiring + the `MockData.currentUser` bridge (ONE-LINE, mirrors cohort's
  :169). The footer gains the same page-supplied section (left: Close;
  right: Replace Now [conflicted-own] + Cancel [cancellable-own]).
- ALL THREE pages gain the `partials/ui-cancel-class-modal.blade.php`
  include beside their modal markup. **Blade order note (round-1 🟡):
  page-supplied `@section('modal-footer')` must be written BEFORE the
  `@include('partials.ui-class-detail-modal')` line** — `@hasSection`
  reads after; a footer placed after the include silently stays fallback.

## D2 — the venue Replace Now gate (pinned EARLY, no third invention)

The show/hide at modal-open = the payload's own facts, NOT a re-derivation:

```js
showReplaceNow = (e.lecturer === MockData.currentUser.name)
              && (e.status === 'conflict');                   // the venue grid paints the conflict fact per <slot, viewedWeek>
```

- The week = the VIEWED week (the venue grid is week-scoped — the
  FACT-week-only law by construction); the handoff =
  `goToReplacementFor(sessionId, currentWeek, 'venue-timetable')`.
- Negative rows: others' class → neither button; healthy week → neither.
- This matches the venue page's existing per-slot status paint (the
  `event-replacement` legend row exists; the conflict state comes from
  the payload) — no new derivation. Note (round-1 💡): the payload's
  events already carry a server-computed `mine` boolean
  (`VenueTimetable.php:243`) — the name-compare stays frozen for
  S2-symmetry, but `mine` is the fallback if the bridge hiccups.

## D3 — the fail-closed wiring table

| Page | Cancel guard context | Footer on wire | Bridge |
|---|---|---|---|
| my-timetable | existing (S1–S5 machinery) | the existing section (right group exists) | exists (:122) |
| cohort | `days`/`weekIndex` from the cohort grid scoping | NEW section (left Close / right Cancel) | exists (:169) |
| venue | the venue grid's day/week facts (via the legacy venue template port) | NEW section (left Close / right Replace+Cancel) | **NEW one-liner** |

The `onCancelled` ports (per page, from the legacy templates): my —
re-derive + re-render the grid + the undo toast; cohort — re-render the
cohort grid row (its twin-key events are the shared `eventsData`… the
apply round pins the template's exact callback body); venue — the same
re-derive+re-render family. The apply round carries each ports' body
VERBATIM from the legacy template files before rehoming them.

## D4 — the fixture re-anchoring (the regime, per the frozen proposal)

- The 14 rows re-point to the live deck: staff 5770 (BMIT2013 L — DB
  day_of_week **1 = TUESDAY** @ B009; BMIT2013 P Wed B010; AMCS2093
  rows), 2873 (AMIT2014). Each row's receipt = the actual anchor used
  (repl logs); a missing fact re-points/skips with evidence.
- The PERSISTENCE receipt: RESTORE-FIRST (the user). The cancel stays
  client + ledger-only; reload resurrects; the changelog states it.

## D5 — tests/receipts

- `cancel-class.spec.ts` re-anchored → the 14 rows green on the LIVE page.
- NEW rows: cohort positive/negative; venue: own-cancel / others-none /
  Replace-Now-on-conflict / healthy-week-none.
- `page-changelogs/*` for the three pages (the wiring + the trade-off
  note: the cancel remains visual-until-reload until the DB-persist lane).
- Standing gates: pint + phpstan-1G + phpunit; the spot battery
  `--grep-invert "TC70"`.

## Promoted to shared

Nothing new — the buttons/wiring REUSE `CancelClass.renderButton`,
`CancelClassModal`, the shared footer semantics and the error-token
styles. The only new text = the page-supplied footer sections.
