# Proposal — My Timetable works from a phone up

> **Change:** `my-timetable-responsive-prompt`
> **Created:** 2026-11-14 · **Status:** draft (batch 1) · **Widened 2026-11-15** — the lane now
> carries **phase B, Student My Timetable** (user decision, §3)
> **Brief:** `../ui-prototype/my-timetable-responsive-prompt.md` (written 2026-11-12, updated
> 2026-11-13) · **Visual reference:** `../ui-prototype/my-timetable-mobile-preview.html`
> **Pages:** My Timetable (lecturer landing page) —
> `resources/views/livewire/my-timetable.blade.php` at `/my-timetable-ui`; **phase B:** Student My
> Timetable — `resources/views/livewire/student-my-timetable.blade.php` at `/student-my-timetable-ui`

## 1. Why

My Timetable is the page a lecturer opens first, and it is the one page in the app that has
**no responsive rules of its own**: the blade carries **0 `@media` blocks**, and the grid it
builds is a full week of days × 22 half-hour columns. On a phone that means a wide table the
user has to drag sideways, with type too small to read, and the week controls scroll away.

The user approved the target behaviour on 2026-11-12 (breakpoints below) and had a standalone
prototype built and iterated five times. This lane turns that prototype into the real page.

**The student page is the same page with a student's data** (user, 2026-11-15): 0 `@media`
blocks, the same six partials, the same `buildTimetableGrid()` call. It therefore rides this
lane as **phase B** (§3) instead of a lane of its own, so the two cannot drift.

## 2. What is already shipped, and is therefore NOT in this lane

Measured on the live blade today, not assumed:

| Thing | State |
|---|---|
| Inline `How to use this page` block replaced by the shared guide dialog | **shipped 2026-11-13** — the blade includes `partials/ui-guide-modal` and passes `guideModal` to the header, with no `.guide-block` and no `.page-desc` |
| Week subtitle + semester progress bar | **shipped 2026-11-13** — 0 hits for `week-subtitle` / `semesterProgress`; the calls to `updateWeekSubtitle()` / `updateProgress()` remain and are null-safe |
| Tinted week select | **shipped 2026-11-13** — the page passes `selectTinted => true` to `ui-week-nav` |
| Summary cards flip to three-line explanations + info button | **shipped** — `partials/ui-summary-bar` with `flipCards => true` and each card's `detail` block |

This lane must not redo any of that. It builds the responsive layout around it.

**All four shipped to Student My Timetable on the same day.** Its blade carries `guideToggle` +
`guideModal`, `summaryButton`, `selectTinted => true` and `flipCards => true` with the same five
card `detail` blocks, and `tests/e2e/timetable.spec.js` asserts the same removed elements on both
pages. That is the precedent §3b follows: these two pages change together.

## 3. What this lane changes

**The four breakpoints (user, 2026-11-12):**

| Width | Layout |
|---|---|
| **< 768px** | one day, a vertical agenda of class cards |
| **768–1023px** | two-day time grid |
| **1024–1279px** | three-day time grid |
| **≥ 1280px** | today's full weekly grid, unchanged except the five explicit exceptions in §4 |

**New work, in plain terms:**

1. **A day strip and a fixed week row** below 1280px, so the week arrows, the week select,
   Today and the days stay put while the day scrolls. The visible tablet days follow the
   selected day and clamp at the week edges. **Where the print button goes below 1280px is
   NOT decided here** — see §9.
2. **A phone agenda**: one day at a time, real cards (time + venue, then any status label,
   then course code/type, then course name, then the replacement note), sized to their content,
   with no empty time gaps drawn.
3. **Only Cancelled and Conflict carry a label inside a block, directly below the time** — in
   every view including the desktop grid. No Public Holiday label inside a block; the holiday
   day heading, red treatment and details status stay. (Today no block carries a status label
   at all: the block renders code, venue, time and the replacement note.)
4. **A day heading with the class count beneath it** and a **Week summary** button beside it —
   **below 1280px only**. On desktop the day heading and the summary section stay exactly as they
   are today, because "should Week summary be reachable from the desktop page" is one of the
   brief's §13 questions the user has left open (§9 below).
5. **A next-class hint on empty days and public holidays only**, as a button that jumps to
   that week and day.
6. **A Week summary sheet** on phone and tablet holding what the header hides: the legend bar,
   the five summary cards and the full semester date range — reusing the real markup so colours
   and borders match desktop exactly.
7. **One tooltip mechanism for the whole page.** Today the blocks do **not** use the shared
   engine: the grid writes `data-name` + `data-tip2` and a CSS `::after { content: attr(...) }`
   hack paints them. This lane moves the block tip onto the one shared engine as a rendered
   `data-tip` (same composed words — subject · context · STATUS — so the text does not change),
   and adds the missing tips to the **week arrows** (`Previous week` / `Next week`) and the
   **week select** (`Pick the week to display`). Opt-in per page, so the other three grid
   callers (Cohort, Venue, Arrangement) keep today's behaviour and Student My Timetable joins it
   in phase B (§3b).
8. **Accessibility**: `aria-pressed` and `aria-current="date"` on the day strip, a focus trap in
   the sheet, focus returned on close, and respect for `prefers-reduced-motion`.

### 3b. Phase B — the same adaptation reaches Student My Timetable (user, 2026-11-15)

`student-my-timetable.blade.php` is **252 lines against the staff blade's 352**, includes the
**same partials** (`ui-page-header`, `ui-guide-modal`, `ui-week-nav`, `ui-grid-table`,
`ui-legend-bar`, `ui-summary-bar`, and the shared `ui-class-detail-modal`) and calls the **same
`buildTimetableGrid()`** with a subset of the options. Everything named in §3 and §4 above is
built in that builder, those partials and `theme.css` — not in the blade.

What is genuinely different, and all phase B has to answer:

1. **Read-only.** No Cancel Class modal (the staff page's `@section('modal-footer')` and
   `ui-cancel-class-modal` include), no cancel path off `onEventClick`, no `rarityReplace…`
   handoff. A card still opens the details sheet; nothing else acts.
2. **The block carries the request state**, not only Cancelled / Conflict: a student's block can
   be Pending or an approved Replacement. §3.3 decides which statuses print a label **inside** a
   block, and the brief's one-label rule has to be read against that third family (§9d).
3. **A cohort chip** in the page header, so the heading row is not identical.

Everything else — the four breakpoints, the day strip, the agenda, the Week summary sheet, the
tooltip migration, the accessibility work — is the SAME work, opt-in per page. Phase A must be
able to ship alone: every component is behind a flag, so phase B is one more blade edit on
finished parts, and its QA is the only part that doubles (5 widths × 2 themes, and the student
page is read-only so the interaction paths do not).

## 4. The five desktop exceptions (user, 2026-11-12) — already partly done

The user allowed exactly these, at **≥1280px too**, so desktop is ~97% identical, not 100%:

1. inline guide and page description → **already in the guide dialog** (shipped);
2. no Public Holiday label inside event blocks (in this lane, §3.3);
3. Cancelled / Conflict labels sit **below the time range** (this lane, §3.3);
4. week subtitle and semester progress bar → **already removed** (shipped);
5. summary cards keep their compact size and flip to three lines (already shipped).

Nothing else on desktop changes: same grid, legend, cards and print button.

## 5. Out of scope

- **Printing.** The print button still shows *"Printing is coming soon"* (the toast text; its hover tip reads `Coming soon`); real printing is
  lane `timetable-print-v2`. This lane does not touch it, and must leave the boundary intact.
- **The other two timetable pages.** `cohort-timetable` and `venue-timetable` keep their current
  behaviour: they answer a different question (a cohort's week, a room's week) and the brief names
  neither. Student My Timetable is **in**, as phase B (§3b) — it is the same page and the same
  builder, and leaving it out would ship a responsive staff page beside a phone-hostile student
  page.
- **Backend, database, routes, new endpoints, new dependencies.** Nothing in this lane writes
  data or adds a package.
- **A restyle.** No new colours, no new component skins, no re-drawn icons. Colours come from
  `theme.css` tokens only.
- **Promoting the grid to a second mobile implementation for other pages.** If a third page
  later needs the agenda, that is its own lane (promote-on-3rd-duplication).
- **The demo databases.** This lane never reads or writes them.

## 6. Constraints that this lane must respect

- The grid is **shared by FIVE live pages**: `buildTimetableGrid()` in `ui-common.js` is called
  from My Timetable, Student My Timetable, Cohort, Venue **and Replacement Arrangement** (the last
  through the legacy `cellRender` path). **Cohort, Venue and Arrangement** must stay byte-identical,
  and the pin test must cover all three — a test that only names two would miss the arrangement
  page, whose `cellRender` path touches the same header build, `hours` axis and
  `HtmlBuilder.dayHeader`. The student page is the fourth caller and changes **only** in phase B,
  through the same flag.
- The week row, legend, summary and day cells come from **shared partials**
  (`ui-week-nav`, `ui-legend-bar`, `ui-summary-bar`, `ui-grid-table`). Reuse them; do not fork.
- **Phase B must not make the student page interactive.** `student-my-timetable` is read-only
  today: it has no Cancel Class modal, no `modal-footer` section and no cancel handler. Adopting
  the page's flags must not drag the staff page's cancel wiring across — the agenda card opens the
  details sheet and nothing else.
- **The two timetable blades stay in lockstep on their shared chrome.** Every timetable UI round
  before this one (guide dialog, tinted week select, week subtitle and progress removal, flip
  summary cards) shipped to both pages the same day, and `tests/e2e/timetable.spec.js` asserts the
  same removed elements on both. A phase-A-only change to the shared header or week nav is a
  regression on the student page until phase B lands.
- The class details modal and the Cancel Class modal are shared and were re-sized today
  (680 ceiling; full-width sheet at ≤600px). This lane must not re-pin their widths.
- The page's own script is wrapped in `DOMContentLoaded`; anything an inline `onclick=` in a
  partial needs must be exported on `window`.
- A `data-tip` is text, not HTML, and there must never be a `title` beside it.
- Existing browser specs assert today's geometry (17 tests in `ui-regression.spec.ts`, 13 in
  `timetable-lane-layout.spec.ts`, plus the modal suites). They are the regression net for the
  ≥1280px promise; any that must change has to be named and justified, never quietly weakened.

## 7. Risks

| Risk | Why it matters | Mitigation |
|---|---|---|
| A shared-engine change leaks into the other three pages | Cohort/Venue/Arrangement would move without being asked | every builder change is an opt-in flag; a test pins those three callers as unchanged |
| Moving the block tooltip onto the shared engine changes the words | the user approved exactly one tooltip voice | the composed string is reused verbatim (`tooltipExtra` + `tipLecturerFn` + `eventStatusLabel().toUpperCase()`), and the opt-in flag keeps the other three callers on the CSS `::after` path |
| Phone agenda drifts from the grid | two sources of truth for the same week | the agenda reads the SAME served payload (`eventsData[currentWeek]`), never a second data shape |
| Desktop shifts while chasing a phone layout | the user's ~97% promise | the ≥1280px view is measured against a before/after geometry capture |
| The existing geometry specs fail for a real reason | hides a regression | run them, name the failures, fix or justify — never relax |
| The sheet re-uses the modal shell that was resized today | double-maintenance | it uses the shipped shell and the shared tokens, and pins no width of its own |
| Phase B swells phase A | the student page doubles QA while the prototype was drawn for the staff page | phase B is a blade edit on finished, flagged components; phase A is approved and shipped first, and phase B answers only its three deltas (§3b) |

## 8. What "done" looks like

- 320 / 360 / 390 / 430px: one day, no sideways scrolling, no clipped text.
- 768px: two days; 1024px: three; edges clamp at Monday and Sunday.
- 1280px and up: geometry comparable to today, apart from the five exceptions.
- Every control works at every width; a card opens the details sheet and closing it returns to
  the same scroll position; the next-class button lands on the right week and day.
- Both themes, keyboard only, and no page errors at any width.
- Phase B: the student page matches at the same widths, the cohort chip survives, the block's
  request state is legible on an agenda card, and nothing on that page became interactive.
- Changelog updated; the other two timetable pages proven unchanged.

## 9. Questions the user has left open — this lane must NOT decide them

The brief's §13 says "ask before implementing any of these". All three stay open:

- **a.** whether the print button returns on tablet (the inline guide stays in Help);
- **b.** whether a phone user can reach Cohort / Venue timetables without opening the nav drawer;
- **c.** whether Week summary should also be reachable from the desktop page.

So: below 1280px the lane builds the day strip, the agenda and the Week summary entry point; the
print button's narrow-width fate and any new navigation shortcut are **not** part of it, and the
desktop day heading keeps today's shape.

One more question, **raised by the scope widening and answered in phase B, not here**:

- **d.** what an agenda card says on the read-only student page, where a block's status can be
  Pending or an approved Replacement on top of Cancelled / Conflict. One label under the time (the
  staff rule), or the request state on its own line? Phase A ships the staff answer; phase B
  decides this with the prototype in hand.
