# Design — selection-wipe-spares-holder-tips (one-selector fold)

## D1. The cleaner's domain law

`clearMergedBlock()` (blade :1542) exists to undo what the has-selection
affordance sets: the selection-hint
`'Clear your selection first — click it to remove'` rides ONLY
`.cell-content.cell-available` cells (setter :1538–1540). The cleaner's
selector therefore MUST carry the same predicate:

```js
body.querySelectorAll('.cell-content.cell-available[data-tip]').forEach(c => c.removeAttribute('data-tip'));
```

One line replaces :1546. Byte-level rules:
- Occupied/pending/reserved holder tips (engine arm + grid arm), too-soon
  and PH cells are untouched — their `data-tip`/`aria`/`tabindex`
  survive any selection commit or clear.
- The hint lifecycle is unchanged: set on commit (:1538), stripped on
  clear/selection-render (this line) — the `.has-selection` class and
  the cursor affordance keep their existing flow.

## D2. The guard (the DOM contract assert)

The lane's Playwright receipt asserts, AFTER a selection commit on a
week with occupied cells:
1. ≥1 `.cell-occupied` cell still carries `data-tip` (the holder
   contract survives selection);
2. the selected block's block-cell hints still function (a
   `.cell-available` cell carries the 'Clear your selection first' hint)
   — the original affordance did NOT regress;
3. clearing the selection re-attaches nothing wrongly (the available
   rows keep their normal state, no orphan hints).

Runbook: probe script at `/tmp` receipted in review-log; no repo spec
file needed beyond the changelog note.

## D3. Test plan (≤1h total)

- Playwright before/after receipts on the SAME probe path as the
  investigation (week 9 → click one green cell): orphans MUST stay 0
  AFTER the fix; tipped cells do not collapse.
- `php -l` blade + the stale-serve ritual; full-suite unchanged
  (client-only); gates = lint:check + phpstan 0 + suite ≥266/266.
- `page-changelogs/replacement-arrangement-changelog.md` entry.
- ONE commit `fix(ui): ...` (no push — user pushes manually).

## D2b. Round-1 🟡 folds (declarative)
- The domain invariant gets the fresh-build receipt: on the no-selection
  grid, EVERY `.cell-content[data-tip]` must NOT be `cell-available`
  (cleaner-domain == setter-domain holds today; the grid-arm payload
  pairing "key 3 only for statuses 1/3/4" rides the changelog note).
- The guard's receipt home = this review-log + the changelog (proposal
  wording drift settled).
