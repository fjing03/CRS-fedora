# Tasks — selection-wipe-spares-holder-tips

## T1. The one-line fix (≤0.25h)
- [ ] blade :1546: `.cell-content[data-tip]` → `.cell-content.cell-available[data-tip]`; `php -l`.

## T2. The guard + receipts (≤0.5h)
- [ ] Playwright probe: week 9 → select a green cell → assert 0 orphans
      (`data-tip-pos` present without `data-tip`) AND ≥1 `.cell-occupied`
      still tipped AND the selection hint still on an available cell;
      screenshots.
- [ ] The user's original URL flow receipt.

## T3. Gates + bookkeeping (≤0.5h)
- [ ] lint:check + phpstan 0 + suite ≥266/266 (background run).
- [ ] Stale-serve ritual (2 separate commands) + :8001 sibling.
- [ ] Changelog entry; ONE commit `fix(ui): ...`; lane → applied.
