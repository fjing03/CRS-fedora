# explore-brief — `arrangement-clear-guards-cross-week` (BUG-08, 2026-10-25)

## The user's bug report (digest)
On `/replacement-arrangement` (staff 5425, `?session=67&week=14&from=
replacement-home`, landing Week 6 — the earliest-bookable default):
pick slot(s) at W6 → `weekSelector` W6→W8 → at W8 a venue change, a
subject change, and a conflicted-slot change ALL proceed SILENTLY — no
"Change Venue?" / "Clear Current Selection?" confirmation modal, even
though selections (made at W6) are still live in the internal store.

The user's expected contract (verbatim):
- venue modal: title "Change Venue?", body "You have selected slots on the
  grid. Changing the venue will clear them. Continue?", buttons
  "No, Keep My Selection" / "Yes, Change";
- "No" = close + preserve the selection + the fields; "Yes" = the change
  proceeds AND the selected slots clear.

## Live reproduction receipts (2026-10-25, /tmp/bug08-probe2/4/5/6/7.cjs)

- W6 pick (Thu 16:00, 4-contig) → counter `4 of 4 slots`, 1 rendered block.
- `weekSelector` 6→8: electrode counter STAYS `4 of 4 slots` (the store
  keeps the selection), grid renders NO block (`loadCurrentWeek` left
  `selectedBlock = null`), `weekNav.currentWeek = 7`.
- At W8: venue change (real 4-column widget walk, B100→B109) → NO modal,
  venue applied; subject change → NO modal, switched; slot change → NO
  modal, the pin swapped — ALL THREE silent.
- Regression (no hop): the SAME changes at W6 → the modals fire
  ("Change Venue?" verified live via real widget clicks).
- Return-to-W6 (W6→W8→W6) → the block RESTORES (loadCurrentWeek) → the
  modals fire again for venue + subject.

## Root cause (live + code cross-referenced)

The three clearing-change guards key on the CURRENT-WEEK pointer alone,
never the cross-week store:

| site | condition (pre-fix) |
|---|---|
| `onVenueChange` ~:1783 | `if (selectedBlock && newVenue !== currentVenue)` |
| `onSubjectChange` ~:2541 | `if (selectedBlock && code !== lastSubject)` |
| `selectSlot` ~:3287 | `if (selectedBlock && index !== lastSlotIndex)` |

`buildTimetable()` → `loadCurrentWeek()` nulls `selectedBlock` on entering
a week whose per-venue/per-week map entry is empty — so after ANY hop away
from the selection week the three guards are disarmed while
`getGlobalTotal()` (the `selectedSlotsByVenue` store sum) still holds the
selection. The correct family — `goBack`/`navigateTo`/`beforeunload`/
`clearAll-enabling` (:2107 / :2127 / :2152 / :1310) — uses
`selectedBlock || getGlobalTotal() > 0` and keeps working after the hop.

## Rejected approaches and why

1. **Widen ONLY the venue guard** — rejected: the subject + slot guards
   share the identical defect shape; the user's report covers all three.
2. **Guard on the VISIBLE block count** (`document.querySelectorAll(...).
   length`) — rejected: the model's truth is the store (`getGlobalTotal`),
   not the grid DOM (the same law the leave guards follow; a DOM read
   would also miss a foreign-week block by design).
3. **Wipe ONLY the current week's map entry in the proceed path** —
   rejected: with a cross-week selection the "Yes, Change" would leave the
   other weeks' blocks alive — the counter would still read "4 of 4" with
   nothing rendered (the BUG-06 disagreement receipt), i.e. the fix would
   cause the very disagreement class it's meant to close.
4. **Extend Clear All's exact toast+undo flow to the modal proceed** —
   rejected: the confirm IS the explicit discard (the 2026-10-09 user
   decision contract already lives in the proceed comments: "confirming
   IS an explicit discard"); no second toast/undo ceremony.

## Final design decisions (the mapping the proposal freezes)

- ONE formula for the three guards: `(selectedBlock || getGlobalTotal()
  > 0)` — the leave-guard family, byte-consistent policy.
- ONE shared proceed helper `discardAllSelections()`: wipe every venue's
  map (`Object.keys(selectedSlotsByVenue).forEach(k => {
  selectedSlotsByVenue[k] = {}; })`) + `deselectBlock()` +
  `updateSelectionSummary()` — the `clearAll()` register minus its
  confirm/toast/undo shell (deselectBlock already pushes its own history
  'deselect' entry and calls updateCounter).
- The modal copy stays VERBATIM (the user's quoted text = the house text:
  venue "Change Venue?" body "…will clear them. Continue?"; subject/slot
  "Clear Current Selection?" family with their labels).
- The cancel paths stay as-is (the revertingChange pair + the continue
  restores) — they already work.
- Session 67 (L, span 4) + session 68 (T, span 2) = the fixture family.

## Known open questions

- Q1 (closed by the user's report): does "Yes, Change" clear across weeks?
  The report's phrasing ("clear the selected slots as intended") + the
  honest-counter law → YES, the whole map. The cancel path preserves
  everything (the counter receipt).
- Q2 (not in scope): BUG-05's same-cell alert — unaffected; the widened
  condition only changes WHEN the modal asks, not the same-cell branch.
