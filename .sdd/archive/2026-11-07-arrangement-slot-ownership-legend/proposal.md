# Proposal — Slot ownership styling & legend for `replacement-arrangement`

- **Change**: `arrangement-slot-ownership-legend`
- **Created**: 2026-11-06
- **Source**: `prompts/UI Update & Bug Fix — Pending Slots, Conflicts & Legend Styling.md` (user brief)
- **Grill decisions**: 1A, 2A, 3A, 4A, 5A, 6A, 7A, 8A (see `explore-brief.md` §3)
- **Page**: `replacement-arrangement` (Laravel 13.17 + Livewire 4.3.3 blade-page script)

## 1. Why

Two problems on the same grid.

**1.1 The grid lies about whose pending slot it is.** A cell can be painted by either of two
independent arms: the *grid arm* (`venueSlotsByWeek`, which tests `proposer_id === auth()->id()`
and is pinned by `ReplacementArrangementTest`) or the *engine arm* (busy time-vectors, where each
cohort make-up row carries `status: 'pending'` but **no owner**). `cellRender()` checks the engine
arm first and colours any all-pending match `cell-pending` — the "Pending (You)" amber — with no
ownership test. Measured on the read-only demo DB: request 28 (proposer **staff 5770**) sits on
B111 week 9 Monday 13:30–15:30, and `cohortBusyRanges(semester 1, [cohort 4], 9)` returns those
four half-hours with `status: pending`. Logged in as **staff 5425** with any subject that shares
cohort 4, those cells render as *my* pending; with any other subject they correctly render
"Reserved by Others". That subject-dependence is the reported "sometimes".

**1.2 Pending and conflict states are not visually delimited, and the legend does not match the
grid.** Today `.cell-pending` (amber), `.cell-occupied` (red) and `.cell-reserved` (grey) are
background-only, so "mine" and "someone else's" look identical. The page's legend also uses flat
colour swatches, so it cannot show a border, and its "Reserved by Others" grey is the same fill as
the lead-time `.cell-too-soon` cells. The cohort and venue timetables already solved the same
problem with a two-axis language (colour = status, border = ownership) plus a legend whose swatches
carry the real block classes.

## 2. What changes (in scope)

### 2.1 Ownership reaches the client as data

`MatrixIntersectionEngine`'s busy ranges gain a `mine` flag, and the busy tuple that the arrangement
page ships to the browser gains a 6th key (`mine: bool`): own-lecturer ranges = `true`;
cohort-side class rows = `false`; cohort-side make-up rows = `proposer_id === auth()->id()`. This is
additive — the first five keys keep today's meaning and no other page consumes `busyByWeek`.

### 2.2 One resolver decides a cell's class

`cellRender()` stops choosing a class per arm and instead classifies the cell once from both arms:
own-pending beats others'-pending, own conflict (3px) beats a mere block (0px), and a cell that is
both mine and someone's pending is mine. Both arms therefore agree, and the styling cannot drift
with the arm order or with the selected subject.

### 2.3 The visual law

- Pending family: `--color-tertiary-container` fill for both; **3px `--color-tertiary`** border when
  the pending request is mine, **no border** when it is another staff member's.
- Conflict family: `--color-error-container` fill for all; **3px `--color-error`** border when the
  blocking row's `mine` flag is `true` — i.e. **only** my own occupied class or my own approved
  make-up (`lecturerBusyRanges()`). Every other cause is **borderless**: a cohort's class (the
  cohort-side rows are `mine = false` by construction), another lecturer's class in the room, an
  overlapping block, and a venue that cannot host the session type/headcount. That is the user's
  law verbatim — own conflicts 3px, cohort conflicts 0px, venue conflicts 0px. A cohort conflict is
  never 3px, even when the cohort is "mine" in the sense of "the cohort I am moving": ownership
  here means *whose calendar the blocking row belongs to*, and the cause itself is already named in
  the cell tooltip (👥 Cohorts conflict).
- No new colour token, no new animation, no `--color-warning` in this page's grid (that token stays
  the just-cancelled pulse).

### 2.4 The legend

The `replacement-arrangement` legend keeps this page's own vocabulary but adopts the cohort/venue
bar wholesale: swatches carrying the **real** cell classes (so fill *and* border show), one
`data-tip` per item, and an ownership hint line worded for this page
(`thick border (3px) = yours · no border = others'`). Six rows: Available · Your Current Selection ·
Pending (You) · Pending (Others) · Conflict (Yours) · Conflict (Others).

## 3. Explicitly out of scope

- `cohort-timetable` and `venue-timetable`: their two-axis language (3px vs 0.5px hairline) and
  their legends are unchanged; the shared partial gains only optional inputs whose defaults keep
  their markup byte-identical.
- The summary cards' labels and meanings (`Pending` still counts **my** pending cells; an
  others'-pending cell stays "unavailable").
- The lead-time rule, public-holiday/Sunday cells (they keep hover labels and stay out of the
  legend, per explore-brief §7), booking restrictions, slot labels, tooltips' wording beyond the
  legend, and the submit/approve/reject write path.
- Any schema change, any seed change, any demo-DB write.

## 4. Impact

| Area | Files |
|---|---|
| Engine | `app/Services/MatrixIntersectionEngine.php` (`makeUpRangesForCohorts()`, `makeUpRangesForLecturer()`, cohort class ranges → `mine`) |
| Page payload | `app/Livewire/ReplacementArrangement.php` (`busyByWeekPayload()` tuple gains the 6th key) |
| Page view | `resources/views/livewire/replacement-arrangement.blade.php` (`cellRender()` resolver, legend items, summary counters stay correct) |
| Shared partial | `resources/views/partials/ui-legend-bar.blade.php` — the hint block today hardcodes **two demo swatches** (`osd-thick` 3px, `osd-thin` 0.5px) around fixed text. This page must say `no border = others'`, and a 0.5px demo swatch beside that sentence would claim a style this grid never renders. The partial gains an optional hint input (own demo variants / own wording, including a borderless `osd-none` demo), with today's markup and text as the default so cohort/venue stay byte-identical |
| CSS | `public/css/theme.css` (`.cell-pending` border, `.cell-reserved` fill + no border, own-conflict modifier, legend swatch sanity at 14px) |
| Tests | `tests/Feature/ReplacementArrangementTest.php` / `tests/Feature/MakeUpBusyVectorTest.php` (ownership flag), one new Playwright spec on the testing DB |
| Docs | `page-changelogs/replacement-arrangement-changelog.md`, `prompts/for-ai-to-read/CodingMAIN.md` (the grid's two-axis rule), `prompts/for-ai-to-read/my-coding-rules.md` (one line) |

No database migration. No new dependency. No change to any other page's rendering.

## 5. Risks

| Risk | Mitigation |
|---|---|
| The 3px border on a 30-minute cell looks heavy or clips the time label | `box-sizing: border-box` is global; both themes are eyeballed; a screenshot receipt per theme |
| A cell's class changes meaning for the summary counters | The counters keep counting by *state*, not by thickness: own-pending still counts as Pending, others'-pending and both red families still count as Unavailable; pinned by a test |
| The shared legend partial regresses cohort/venue | Its new inputs are optional with today's markup + text as the default; a feature test asserts both reference pages' legend markup is unchanged |
| The fix changes visible counter values (an others'-pending cell stops inflating "Pending") | That is the point of the bug fix; the changelog entry says so explicitly so the drop is not read as a regression |
| Multi-cause cells (own class + cohort class + another lecturer's class) become ambiguous | The resolver's precedence is explicit and tested at payload + browser level; the cell tooltip already lists every cause |
| Fixing the colour could hide a real unavailability | Ownership changes the border only — never the fill, never the cursor, never bookability |

## 6. Acceptance

1. As 5425, viewing 5770's pending slot (B111 week 9 Monday 13:30–15:30) with a cohort-4 subject
   selected, the cells read **Pending (Others)** (amber, no border) — never "Pending (You)".
2. As 5770, the same cells read "Pending (You)" (amber, 3px border). Symmetric the other way.
3. Every pending cell is amber and every conflicting cell is red; thickness is decided by ownership
   only; the values are the CSS tokens, not hardcoded colours.
4. The legend's six swatches render exactly what the grid renders; the hint line names both
   thicknesses; cohort/venue legends are unchanged.
5. `ReplacementArrangementTest` / `MakeUpBusyVectorTest` cover the ownership flag, the Playwright
   receipt covers the computed border widths, and the full PHPUnit suite is green.
6. The closed-red cell class keeps `.cell-occupied` as its base (a modifier may be added, never a
   replacement), so the summary counters that count that class stay correct.
