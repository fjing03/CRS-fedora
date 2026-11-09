# Tasks — slot-cancel-restores-session-anchor

## T1. The helper + two call sites (≤0.5h)
- [ ] Add `writeSlotTriggerFromAnchor()` beside `anchorSlotFromPinned()`.
- [ ] Boot tail: replace the inline format builder with the helper call.
- [ ] Cancel else arm: the conditional restore (helper-false → the
      placeholder), order-preserving.
- [ ] `php -l` + the stale-serve ritual (2 separate commands) + :8001.

## T2. Receipts (≤0.5h)
- [ ] Playwright: the four design-receipt states (the 68/14 cancel
      restore; the non-handoff reset; the strip/pinned-unchanged asserts;
      the label-format grep receipt).
- [ ] Screenshot + DOM snippets; the before state is already in the
      investigation report (review-log reference).

## T3. Gates + bookkeeping (≤0.5h)
- [ ] lint:check + phpstan 0 + suite ≥266/266 (background).
- [ ] Changelog entry; ONE commit `fix(ui): ...`; lane → applied.
