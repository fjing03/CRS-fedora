# Apply log — summary-card-detail-copy

## Applied and verified — 2026-11-14

**Completion:** T1–T8 complete, **55/55 boxes ticked**. Authority: the user ran
`/sdd-apply summary-card-detail-copy` on 2026-11-14 after the lane was described
and its four open items were confirmed. No commit, push, archive or demo-DB
write was authorised by that instruction.

**Scope.** Presentation only. No summary value, calculation, filter, `valueId`,
card colour, database, route or dependency changed.

## What was built

### T1 — back spacing (`public/css/theme.css`)

- `.summary-card-back` → `justify-content: center` (was `flex-start`).
- `.summary-card-back .summary-description` → `line-height: 1.45` (was `1.15`),
  `padding-right: 38px` (was `30px`).
- `.summary-points` → `gap: 4px` (was `0`).
- `.summary-info` → `top: 0; bottom: auto` (was `bottom: 0`), keeping the 44×44px
  target and 16px glyph.
- New `@media (max-width: 389px)` rule: the flip summary bar becomes one column.

**Placement is load-bearing.** The 389px rule sits *after* the existing 768px
two-column rule. Both selectors are specificity 0,2,0, so had it been placed
earlier the 768px rule would have won and the spill would have returned
(+14px at 320px, +5px at 360/375px). This is commented in the CSS so a later
tidy-up cannot silently reintroduce it.

Baseline measured before the change: **7px above / 48px below** the text block at
both 1360px and 390px — the dead band the user reported. After: **0px
difference** at all eight widths.

### T2 — explanation styles (same file)

`.explain-formula`, `.explain-chip` (+ `.is-blue` / `.is-pending`),
`.explain-plus`, `.explain-list`, `.explain-list li`, `.explain-list li span`,
`.explain-foot`. Every colour, radius and border is an existing `theme.css`
token — zero literals, so SD-08 holds.

**These rules are deliberately unscoped.** `#summaryExplainModal` is a *sibling*
of the closing `.summary-section` div, so nesting them under
`.summary-section--flip` would style nothing while still passing a
"content exists" check — a silent failure. Commented in the CSS.

### T3 — shared partial (`resources/views/partials/ui-summary-bar.blade.php`)

Reads optional `$card['detail']` with `formula` / `items` / `foot`, rendered into
`.summary-explanation-source`. Falls back to the existing `explanation`
paragraph when `detail` is absent, so no card can ever render blank.

- `items` emits `term<br><span>meaning</span>`; the `<span>` is the only thing
  that makes the meaning muted (theme.css has no default colour for a bare text
  node). Both halves use `{!! !!}`, matching the pre-existing `{!! $explanation !!}`.
- Chips: `'v'` label, optional `'c'` colour class, `'op'` operator.
- `explanation` was **kept on every card** even where `detail` is present. It is
  what `SummaryCards.open()` prints and what the fallback uses; deleting it would
  make `$card['explanation'] ?? $desc` fall through to `$descriptions[$card['class']]`,
  which is wrong for `card-total` and `card-replacement` — the exact class
  collision the design warns about.
- Nothing is keyed on `$card['class']`; `card-total` carries four different
  meanings and appears twice on Request Approval.
- `$descriptions` untouched.

**No JS change was required.** `SummaryCards.open()` already sets
`body.innerHTML = '<div class="summary-explanation">' + source.innerHTML + '</div>'`,
so structured markup survives intact. One renderer, no drift. `ui-common.js` was
therefore **not** edited by this lane — important, because a concurrent session
owns uncommitted changes in that file.

### T4 / T5 — detail data on six pages

Five cards each, **30 cards total**: `my-timetable`, `student-my-timetable`
(student voice), `cohort-timetable`, `venue-timetable`, `replacement-home`,
`request-approval`. Copy read from the approved prototype, with the prototype's
wrong `class` values corrected (Venue My Teaching Classes / Occupied and Cohort
My Teaching Classes keep their live classes — taking the prototype's would have
recoloured them).

Four bullets shortened, full wording preserved in the popup:

| Page | Before | After |
|---|---|---|
| My Replacements | `Replacement confirmed` | `Confirmed` |
| Request Approval | `Replacement confirmed` | `Confirmed` |
| Request Approval | `Lecturer must re-arrange` | `Arrange again` |
| Request Approval | `Approved or rejected` | `Approved/rejected` |

Shortened bullets stay **plain** (no `<strong>`), matching how they already
rendered. The `items` expand the shipped bullets rather than restating a
different rule.

### T6 — formulas checked against real arithmetic

- Chips carry **no live numbers** — verified against the code: `computeSummary()`
  sets `total = evs.length - cancelled`, `hrs` from `(e.end-e.start+1)*0.5`, and
  `updateSummary()` writes every front value at runtime. The only digit in any
  chip is the fixed `30 min` slot constant.
- **Total Classes has no `= total` chip** — conflict blocks are counted in
  `total` but in none of its parts, so a sum would be false on any week with a
  red block.
- Teaching/Class Hours state 30-minute slots and exclude cancelled classes
  (`if (e.status !== 'cancelled')`).
- Request Approval's Total Reviewed excludes Pending — confirmed at
  `request-approval.blade.php`: `['Approved','Rejected','Completed']`.
- My Replacements' hours chip says `sum of class lengths = time to replace`,
  not "slots × 30 min", because that page sums `c.duration`.
- Venue reads precomputed `totalsData` and has no `updateSummary()` — no formula
  was invented for it.

## Verification receipts

Static: `php -l` clean on all six views and the partial; `php artisan view:cache`
succeeded; `git diff --check` clean; `theme.css` braces balanced (928/928);
`ThemeCssIntegrityTest` **3/3, 12 assertions**.

Browser (headless Chromium, staff `5425`, student `24DFT0001`):

- **48 page+width combinations** — six pages × 320/360/375/390/414/768/1024/1360px.
  Every card: **≤3 rendered lines**, **0 spill** below the back face, **0 text
  rects intersecting the info button**, and above/below space differing by
  **max 0px** (SD-01 asks ≤2px).
- Column count: **1** at 320/360/375, **2** at 390 and above (SD-02).
- **35 modal checks** — all 30 flip cards plus five repeated in dark theme.
  Every title matches its card label; every modal shows ≥2 items; **13** show a
  formula strip; every item has its own direct `<span>`; dark theme renders
  correctly.
- **0 page errors** across every page and width.

Method note, recorded so the numbers are not misread later: two early runs
reported false failures. The first counted the container `<div>` as a fourth
text line; the second clicked `.summary-info` while it was still
`visibility: hidden` (it only shows on card hover), so no modal opened at all.
Both were measurement faults, not layout faults. The final run hovers first and
measures per-line `Range` rects only, excluding the block container.

## Files changed

`public/css/theme.css` · `resources/views/partials/ui-summary-bar.blade.php` ·
`resources/views/livewire/{my-timetable, student-my-timetable, cohort-timetable,
venue-timetable, replacement-home, request-approval}.blade.php` · six
`page-changelogs/*.md` entries · `prompts/for-ai-to-read/PI_Chat_Context_Handover.md`
· this lane's `tasks.md`, `sdd.yaml`, `apply-log.md`.

`public/js/ui-common.js` was **not** touched — no JS change was needed, and a
concurrent session owns uncommitted changes there.

`ui-prototype/summary-card-back-preview.html` was **verified only, never
modified**, per the standing instruction.

## Not committed

Working tree only. `theme.css` also carries the separate `nav-right-align`
change (2026-11-14), which is unrelated to this lane and must not be swept into
it. No push, no archive.

## Known open item (not this lane's to fix)

Recorded in `design.md` §8 and unchanged: `computeSummary()` counts conflict
blocks in Total Classes while the shipped bullet says "No red blocks" and the
three parts exclude them. The `= total` chip was removed so the modal cannot
assert the false sum, but the underlying contradiction is pre-existing and
needs a product decision — either the card excludes conflicts (a value change,
separate lane) or its copy stops implying the sum. Left alone deliberately.
