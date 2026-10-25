# proposal — `arrangement-clear-guards-cross-week` (BUG-08, 2026-10-25)

## Why

BUG-08 (the user's live report, verified by Playwright): after selecting
slots at Week 6 and hopping `weekSelector` to Week 8, a venue change, a
subject change, AND a conflicted-slot change all proceed silently — no
confirmation modal — even though the internal store still holds the
selection (the counter reads "4 of 4 slots" throughout). The cause
(evidence receipts in explore-brief): the three clearing-change guards key
on the CURRENT-WEEK pointer (`selectedBlock`) only; the multi-week store
(`selectedSlotsByVenue[venue][week]`) never entered their condition, so
any hop away from the selection week disarms them.

## Scope

`resources/views/livewire/replacement-arrangement.blade.php` — THREE guard
conditions + ONE shared proceed helper. NO other page, NO backend, NO
payload change, NO new UI chrome (the modals exist; only their FIRING
gates + their proceed semantics move).

### The guard formula (ONE policy, three sites)

```js
if ((selectedBlock || getGlobalTotal() > 0) && <the change>)
```
- `onVenueChange` (~:1783): `(selectedBlock || getGlobalTotal() > 0) && newVenue !== currentVenue`
- `onSubjectChange` (~:2541): `(selectedBlock || getGlobalTotal() > 0) && code !== lastSubject`
- `selectSlot` (~:3287): `(selectedBlock || getGlobalTotal() > 0) && index !== lastSlotIndex`

### The proceed semantics ("Yes, Change" = the full discard)

A shared `discardAllSelections()` helper (the `clearAll()` register minus
its confirm shell + toast/undo): wipe every venue's map, `deselectBlock()`
(its own pushHistory + updateCounter ride along), and
`updateSelectionSummary()` (the strip drops to "0 of N" with the staleness
the deselect path already handles). All three modals' proceed bodies call
it instead of the pointer-only `deselectBlock()` / `discardSelection()`.

- the booking-intent capture (`intentMatchesBlock` reading
  `currentVenue`/`weekNav.currentWeek`) runs BEFORE the wipe — the
  existing code order (receipt: the venue proceed's `const isBooking =
  …` line sits before `deselectBlock()`); the helper call inherits the
  same ordering, the other proceeds already do this in the same shape.
- the cancel paths stay byte-identical (the revertingChange pair already
  preserves store + fields; the counter receipt proves the preservation).

### The copy stays verbatim

The venue modal (title "Change Venue?", body "…will clear them.
Continue?", labels "No, Keep My Selection" / "Yes, Change") and the
subject/slot family ("Clear Current Selection?") keep their text — the
user's expected wording IS the existing house copy; only the FIRING and
the PROCEED change.

## Out of scope

- BUG-01…07 (separate lanes per docs/replacement-arrangement-pending-
  bugs.md) — no trigger-label, strip-anchor, BackNavigator, URL-week work.
- BUG-05's same-cell branch (untouched).
- The week-change itself stays silent-by-design (the multi-week model:
  selections persist per week; the hop alone asks nothing).

## Acceptance (ONE sentence)

After any week hop away from a week holding selections, changing the
venue, the subject, or the conflicted slot pops the matching confirm
modal; "No" restores everything (field + the hidden selection + the live
counter receipt); "Yes" applies the change AND leaves the counter honestly
at "0 of N" (no residue across weeks — the BUG-06 disagreement class dies
with the proceed).

## Blast radius + gates

- Tests: NEW `tests/clear-guards-cross-week.spec.ts` (rows below in the
  tasks); `tests/confirm-guards.spec.ts` re-run as the tripwire (its
  no-hop rows must stay green — only gating ADDITIONS, never removals).
- Gates: the new spec ×2, confirm-guards ×1, pint, phpstan-1G, phpunit
  (baseline 258), changelog entry, ONE conventional commit, NEVER push.
