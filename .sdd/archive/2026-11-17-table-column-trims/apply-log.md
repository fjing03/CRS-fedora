# Apply log — `table-column-trims`

Applied 2026-11-17. Spec frozen after one review round (`spec-and-tasks.md`); three product files
touched, no `theme.css` line, no new class, no backend change.

## What landed

| file | change | evidence |
|---|---|---|
| `resources/views/livewire/request-approval.blade.php` | Staff cell → the name alone in its wrapper; Course cell → one `cell-code` div with the guarded type | live at 1024 / 1280 / 1600, both themes |
| `public/js/ui-common.js` | `replacementHomeRow`, **`if (opts.merged)` branch only** → `CODE (TYPE)` | live on My Replacements |
| `resources/views/livewire/replacement-history.blade.php` | `courseCell()` → same two spans, guarded; the comment above it rewritten | live on the student page |
| `replacement-home.blade.php` | **not touched** — its cell comes from the shared builder | `git status` proves it |

## Receipts

**Static**
- `tests/Feature/CourseColumnTipTest.php` — **4/4, 17 assertions** (was expected to stay green; it did,
  including the regex that pins the `html += '<td><div class="cell-code">' + r.courseCode` prefix and
  the one that pins the three-line `cls: 'col-code'` object shape).
- The legacy nine-cell body in `ui-common.js` is **byte-identical** (`cell-name` still at `:4137`).
- `node --check public/js/ui-common.js` clean; `php -l` clean on both blades.

**Live, measured**
- Request Approval, 1024: Staff cell reads `Lim Jia Zheng` / `Surayaini Binti Basri`, `600` weight,
  `13px`, **no `(5425)`**, **no email line**. Course cells read `AMCS2093 (L)`, `AMSE2003 (P)`.
- My Replacements: `BMIT2013 (L)`, `AMCS2093 (L)`, `AMCS2093 (P)` — 5 of 5 rows carry one
  `.cell-type-label`, `cell-name` count **0**.
- Student page: `BMIT2013 (L)` with 10 of 10 rows labelled, `cell-name` count **0**.
- Pop-up (PL): strip `Staff (Proposer) — Lim Jia Zheng` (name only), **Class Info** holds the
  `(staffId)` and the click-to-copy email chip. So the cell lost nothing that isn't one click away.
- Search: typing `Software` (a name no cell prints) still returns the 3 rows — `Showing 3 of 3`.
- No console error, no page error on any of the three pages.

**The table relaxes at 1024** (proposal §8's claim, measured rather than asserted):

| column | at HEAD | with the trim |
|---|---|---|
| STAFF (PROPOSER) | 188px | **146px** |
| COURSE | 250px | **118px** |
| table width | 1451px | **1277px** |

Row height is unchanged at 122px — the slot columns, not the Staff column, are what sets it at this
width. My Replacements is `table-layout: fixed`, so its widths are identical by design (`200 / 125 /
236 / 165 / 236 / 212 / 75`), and its row stays 101px; only its cell text got shorter.

## The four DOM specs — before and after

Run complete at HEAD (the three product files stashed, view cache cleared) and again with the trim:
**12 failed / 27 passed both times, the same twelve names.** Nothing new broke; two were repaired:

- `tests/type-labels.spec.ts` — **was red long before this lane**: it clicked
  `#quickViewModal .btn-close-modal`, but the quick-view footer's Close has been `.btn-outline` since
  `6856577` (the tabbed modal). `git log -S"btn-close-modal"` proves the last change predates this
  session. Re-pointed; **4/4 green**, which means the spec that pins `.cell-type-label` now actually
  guards this lane's cells.
- `tests/replacement-home-status-badges.spec.ts:149` — **task 10's drift, mine**: it demanded the tab
  label `Original Slot Schedule`, which task 10 shortened to `Original`. Re-pointed; **12/12 green**.

The ten that remain are other surfaces, unchanged by this lane and identical before it:

| red | belongs to |
|---|---|
| `ui-regression` TC-3.x ×3 | the timetable class pop-up, which now says `Class Information` / `Session ID` instead of `Subject Code` (pre-existing since `0e43019`) |
| `ui-regression` TC-4.1–4.5 | the arrangement flow's slot count (`0 of 4 slots` where the test wants more) |
| `ui-regression` TC-7.2 | the summary cards' own-records scope |
| `request-cancel-pending:66` | the same slot-count data |

## Honest gaps

- The PL queue has no pending rows, so the RA table only shows its 3 handled rows (Rejected /
  Completed) after clearing the status chip. The trim was therefore measured on handled rows; the
  pending row shape is unchanged by this lane (the cell is built by the same two statements).
- `typeLabel` is now unused by the merged branch but **must stay** — the legacy nine-cell body still
  reads it.
- Design 2c prints the raw `c.type` while 2b/2d route through `UiLabels.classType`; identical
  behaviour (passthrough), recorded in the artifact so verify does not read it as an oversight.
