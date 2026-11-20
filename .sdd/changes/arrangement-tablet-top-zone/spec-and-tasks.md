# Replacement Arrangement: two-column tablet setup

Status: applied and verified. The independent compact review timed out without a verdict; the owner explicitly waived review for this change.

## Scope and design

Use two equal, shrinkable tracks in the Arrangement top zone only above 768px and through 1100px. Keep existing DOM order: Conflict Schedule and Class Details first, then Replacement Schedule and Selection Summary. When no original slot is loaded, follow the desktop precedent and hide the empty Class Details card; Conflict and Replacement share the first row and Selection Summary spans the second. Keep the existing collapse controls and their inline behaviour rather than introduce rails or change defaults. No changes to mobile sheet, layouts above 1100px, cards, grid geometry, booking or submission logic. Leave the unrelated pre-existing 24px document overflow and existing unmodified controls outside this adaptation.

## Baseline and acceptance

- Protected served pinned example (`session=49&week=14&from=my-timetable`): at 1024×768 Chromium touch the grid starts at y=1024; a browser-only two-column preview puts it at y=662 with the header and first day visible. At 1024 mouse, y=1016 → 654. Preview headroom also verified at 769, 820 and 1100; no change in document width or grid cell count. Baseline and preview: `/tmp/crs-arr-tablet*.png` and `/tmp/crs-arr-tablet.jsonl`.
- Verify the served result at 768, 769, 820, 1024 and 1100/1101. At 1024×768 the grid header and part of the first day must be above the fold on initial load, with no new card or page overflow. Verify pinned and unpinned states, a real touch tap on an available cell updating Selection Summary, and existing collapse controls. Do not submit or write DB data. Prove phone and desktop layouts are unchanged, inspect light/dark, run scoped browser and static checks, and record the changelog.

## Verification

The served 1024×768 grid starts at y=662 touch and y=654 mouse, matching preview; document width remains 1048px and the slot count stays 63 for the pinned example. Reviewed the served light and dark captures. The new spec covers both breakpoints, pinned/unpinned layouts, collapse and draft selection by touch and mouse; the existing Selection Summary check passed too. Playwright: 13 passed including authentication. Pint and PHPStan passed. No submission or business DB changes. Evidence: `/tmp/crs-arr-tablet-check.txt` and `/tmp/crs-arr-tablet-results/`.
