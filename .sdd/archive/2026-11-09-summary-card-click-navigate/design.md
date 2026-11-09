# Design — summary-card-click-navigate

## D1. The navigate helper (the pattern + the bypass law)

```js
/* SDD summary-card-click-navigate: the card's hop onto the EXACT slot. */
function navigateToSavedBlock(venueKey, weekIdx, block) {
    revertingChange = true;
    if (weekNav.currentWeek !== weekIdx) weekNav.selectWeek(weekIdx);
    if ((currentVenue.code || currentVenue) !== venueKey) {
        venueDropdown.select(venueKey);     /* the label; onVenueChange
                                              early-returns under the pair */
        applyVenueChange(venueKey);         /* the direct apply — the
                                              SAME law the auto-pin uses */
        revertingChange = false;
        if (block) pulseTargetCell(block.day, block.startHour);
        return;
    }
    revertingChange = false;
    if (block) pulseTargetCell(block.day, block.startHour);
}
```

- The `revertingChange` pair = the auto-pin law (commitSlotSelection's
  own bypass): `onVenueChange` early-returns under it, so the
  different-venue hop NEVER raises the discarding modal.
- `weekNav.selectWeek` first (the per-week memory: the current view's
  selection saved via `_beforeNavigate → saveCurrentWeek`), then the
  venue apply — the grid rebuild restores the target block from the
  store (`loadCurrentWeek → renderMergedBlock`), so the "exact slot"
  reads selected, not just visited.
- The pulse = the existing affordance (the consume uses it; animation-
  only).

## D2. The card wiring (the builder's innerHTML + listener)

```js
card.className = 'sel-summary-card';
card.dataset.venue = b.venue;
card.dataset.week = b.weekIdx;
card.setAttribute('data-tip', 'Click to View This Selected Slot on the Timetable.');
card.setAttribute('tabindex', '0');
card.setAttribute('role', 'button');
card.addEventListener('click', function (e) {
    if (e.target.closest('.card-remove')) return;   /* the × owns itself */
    navigateToSavedBlock(b.venue, b.weekIdx, b);
});
card.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    if (e.target.closest && e.target.closest('.card-remove')) return;
    e.preventDefault();
    navigateToSavedBlock(b.venue, b.weekIdx, b);
});
```

- The × button keeps its inline onclick (removeSavedBlock) + its OWN
  data-tip (closest() from the × resolves to the button first — the
  same precedence the cs-badge witness uses).
- Keyboard parity: the card is focusable/Enter/Space-operable (the
  house a11y law — the F-9 pattern).
- Wait — the innerHTML template MUST build the DOM BEFORE listeners
  attach; the existing code already `innerHTML =` then `appendChild` —
  the listeners attach after the assignment (same task); safe.

## D3. The CSS (affordance only)

```css
.sel-summary-card { cursor: pointer; }
.sel-summary-card:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
```

- No size/spacing/copy change; the × keeps its hit area and its own
  hint (`.card-remove` cursor remains whatever it was — pointer comes
  from the button semantics).

## D4. The receipts (falsifiable)

1. Multi-week hop: select a block on week A, jump to week B via the
   tab strip, select another block, navigate by card → the grid lands
   on week A at the block; `updateCounter` unchanged.
2. Cross-venue hop: a block on venue X, a card of venue Y → click → NO
   "Change Venue?" modal, the Y grid shows its block; the reverted
   venue label = Y.
3. The × still removes: click the × of a card → the card disappears
   (`card-removing` class flow), NO navigation, the store loses the
   block.
4. Hover: the card tip text; the × tip stays the Remove message.
- Gates: php -l, pint + phpstan 0, suite ≥266/266, changelog, ONE commit.

## D5. Round-1 fold (declarative)
- Receipt 2's caveat (the reviewer's 🟡4): `loadCurrentWeek`'s
  availability guard may refuse the visual restore if occupancy changed
  between the hops — the pulse then marks the anchor cell while the
  grid shows the slot WITHOUT the block; identical to the manual
  week-hop law, accepted.
- The reviewer's extra receipts: the tooltip layer's delegated
  listening covers rebuilt cards; the ×'s own tip precedes the card's
  (closest resolution); the mobile CSS needs nothing (the mobile block
  overrides padding only).
