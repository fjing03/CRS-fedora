# Proposal — summary-card-click-navigate

## The user's ruling (2026-10-28, verbatim intent)

1. **Click to navigate**: clicking a `.sel-summary-card` navigates to the
   EXACT selected slot — restoring the correct week, venue, day and time
   on the timetable.
2. **Hover tooltip**: reuse the existing data-tip layer with a clear
   instruction — `Click to View This Selected Slot on the Timetable.`
3. **Preserve existing behaviour**: the `.card-remove` × still removes
   the selection WITHOUT triggering the card navigation.

## Existing shape (the exploration facts)

- The cards are built in `updateSelectionSummary()` (blade ~:1412):
  `card.dataset.venue` / `card.dataset.week` (the 0-based weekIdx) +
  the block facts (dayIdx/startHour/endHour) ride the same closure
  iteration; the × is an inline `onclick="removeSavedBlock(...)"` with
  its own data-tip.
- Both navigation primitives exist: `weekNav.selectWeek(weekIdx)` — the
  per-week memory law restores the saved block visually via
  `loadCurrentWeek()` → `renderMergedBlock` — and the consume's
  venue-jump pair (`venueDropdown.select(...)` guarded by the
  `revertingChange` pair, the commitSlotSelection auto-pin law) with
  `applyVenueChange(venue)`.
- The ×-must-not-navigate risk: the card's click listener would receive
  the button's bubbled click — the handler therefore early-returns on
  `e.target.closest('.card-remove')`.
- The venue-guard risk: a click on a DIFFERENT-venue card while a
  selection is live would otherwise raise the *"Will Change Venue?"*
  discarding modal — the navigation MUST bypass it (the
  revertingChange pair, exactly the auto-pin law).

## The change

1. **A shared `navigateToSavedBlock(venueKey, weekIdx, block)` helper**:
   the `revertingChange` pair; `weekNav.selectWeek(weekIdx)` when needed;
   `venueDropdown.select(venueKey)` + `applyVenueChange(venueKey)` when
   the venue differs; then `pulseTargetCell(block.day, block.startHour)`
   (the existing pulse affordance, no layout change) — the grid lands on
   the exact slot with the block restored from the store.
2. **Per-card wiring** in the builder: the card gains a click listener
   (early-return on `.card-remove` closes), the tooltip attr
   `Click to View This Selected Slot on the Timetable.`, and
   `cursor: pointer` (+ the × keeps its own cursor/affordance).
3. **No data/state changes** — the store, the budget, the counter, the
   submit flow untouched.

## Out of scope

- The remove flow (`removeSavedBlock`) itself; the summary tip texts;
  the modal machinery; any server code; layout/styling beyond the
  cursor.

## Success criteria

- Click a card of ANOTHER week (same venue): the grid jumps to that
  week, the selected block renders, the pulse marks its anchor cell.
- Click a card of ANOTHER venue: same, WITHOUT the venue-discard modal,
  and the × path still removes (probe: the remove survives the new
  listener; the tooltip on the × stays the Remove message).
- Gates: `php -l`, pint + phpstan 0, suite ≥266/266, changelog entry,
  ONE commit (no push).
