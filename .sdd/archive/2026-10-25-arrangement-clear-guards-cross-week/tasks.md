# tasks — `arrangement-clear-guards-cross-week`

(≤ 2 h each; the frozen design.md D1–D5 = the acceptance surface.)

- [x] **T1 — the helper + the three guards** (design D1–D3):
  `discardAllSelections()` after `discardSelection` (~:2862); the venue
  guard widening at `onVenueChange` (~:1783) with its proceed body swapped
  to `discardAllSelections();`; `onSubjectChange`'s (~:2541) + `selectSlot`'s
  (~:3287) guards widened; `confirmChangeWithSelection`'s (~:1935)
  `discardSelection();` → `discardAllSelections();` (verify its other
  callers keep pointer semantics).
- [x] **T2 — the spec** (design D4): NEW `tests/clear-guards-cross-week.spec.ts`
  with the 8 rows (D4, incl. the hop-stays-silent row 8).
- [x] **T3 — gates** (design D5): the new spec ×2, confirm-guards ×1
  tripwire, pint, phpstan-1G, phpunit 258, the changelog entry, the
  pending-bugs doc BUG-08 row, ONE conventional commit. **NEVER push.**
