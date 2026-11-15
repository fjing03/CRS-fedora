# Design — `my-timetable-responsive-prompt`

**Batch 2.** `proposal.md` is **frozen** (review round 4, 2026-11-15). This document pins the
mechanisms the proposal left to the design batch: the opt-in scope, the module, the markup, every
selector, the CSS location, the phase split, and the one thing the proposal promised to settle here
(how phase A's shared-file work stays out of Cohort / Venue / Arrangement).

Line numbers are as of **2026-11-15** and are cited to be *found*, not trusted.

---

## 1. Shape

```
my-timetable.blade.php          adds:  <div class="lw-page tt-responsive" id="ttPage">
                                        @include('partials.ui-tt-chrome', [...])        ← new
student-my-timetable.blade.php  the same include, with its four deltas (§8)

partials/ui-tt-chrome.blade.php (new)  day strip · tools row (day heading + count + Week summary) ·
                                        agenda mount · next-class hint · the Week summary sheet

ui-common.js                    var TimetableResponsive = { init, sync, setDay, buildAgenda,
                                  buildStrip, buildSheet, nextClass, openSheet, closeSheet }
                                buildTimetableGrid(): + `responsive` flag → the tip migration and
                                  the tablet day window; default callers untouched

theme.css                       one new section, every rule prefixed `.tt-responsive` or
                                `@media (any-pointer: coarse)` inside that scope, PLUS the
                                unscoped `.badge-cancelled` skin (the recorded exception)
```

The scope class is the whole safety story: **no rule this lane adds may bite a page that does not
carry `.tt-responsive`**, except `.badge-cancelled`, which is a defect fix (proposal §3b).

## 2. Decision log

| decision | why |
|---|---|
| one opt-in class on the page root, not per-partial flags | the proposal's promise ("phase A ships alone") needs a switch that covers CSS as well as markup; a class set by the blade is the only thing that can scope a media query. |
| the agenda is **built by JS from the served payload**, not from a second data shape | the proposal's own risk row, and the page already owns `eventsData[currentWeek]`. |
| the day strip is **buttons with `aria-pressed`**, not tabs | a tablist implies one panel per tab; here one day is shown at a time from the same grid, and the brief asks for `aria-pressed` + `aria-current="date"`. |
| **the tablet grid is transposed and built by the module**, not the desktop table with rows hidden | measured against the real builder: days are **rows** and half-hours are **22 columns** in `buildTimetableGrid()` today (11 `colSpan=2` hour headers), so hiding day rows would give ~32px-wide columns — nothing like the approved preview, whose tablet is `grid-template-columns: 50px repeat(N, 1fr)` with 22 time rows. A CSS transpose of a table with `colSpan` blocks is not possible, so the module renders a small grid from the same payload: **days as columns, time as rows**, `N` = 2 at 768–1023 and 3 at 1024–1279, from the selected day, clamped at Mon/Sun. It lives under the scope class, so no other page and no builder output changes. |
| the Week summary sheet is **new**, and `SummarySheet` is **gated off** on these pages — one line in its `init()` | it is not enough to drop the button: `SummarySheet.init()` runs on every page and its `sync()` relocates `#summarySection` into `#summarySheetBody` at ≤768 **regardless** of the button (the button only gates visibility). With both alive the two relocators would fight for one node, its MutationObserver and print handlers would yank it back out of `#ttSheetBody`, and `unrelocate()`'s parent guard would leave two placeholders. So `SummarySheet.init()` gains `if (document.querySelector('.tt-responsive')) return;` — on these pages `TimetableResponsive` is the only owner. The `#summarySheet` frame that `ui-summary-bar` always renders stays in the DOM, unreachable and harmless. |
| `data-tip2` → `data-tip` behind the flag, words byte-identical | proposal §3.7 and its risk row; the other four grid callers keep the CSS `::after` path. |
| `.badge-cancelled` is unscoped | it is the one deliberate all-pages defect fix (proposal §3b), in `.badge-reason`'s token pair — not red (proposal §10.4). |
| the touch rule lives **inside the scope**, and therefore covers **page content, not the shared top bar** | proposal §4 item 6 + §3b: a bare `any-pointer: coarse` block would resize Cohort / Venue / Arrangement on a touch screen. The top bar is rendered by `partials/ui-nav-bar` **outside** the page root, so no scope class can reach it, and growing its icons on every touch device is an all-pages change nobody asked for. The law is therefore honoured for every control **inside `.tt-responsive`** (week arrows, select, day chips, cards, the sheet, the next-class button, the summary info button, the footer buttons); the top bar keeps its shipped sizes on every page. If the user wants the top bar at 48px too, that is one unscoped rule and an all-pages decision — recorded here, not taken. |

## 3. Breakpoints and state

| width | strip | grid | agenda | sheet |
|---|---|---|---|---|
| ≤ 767 | one day, `aria-pressed` | hidden | shown, one day | bottom sheet |
| 768–1023 | all seven day chips; the *window* shows 2 from the selected day | 2-day transposed grid | — | bottom sheet |
| 1024–1279 | all seven day chips; the window shows 3 | 3-day transposed grid | — | bottom sheet |
| ≥ 1280 | **not rendered** | today's full week | — | not available (proposal §9c) |

State lives in one object (`TimetableResponsive._state`): `{ width, day, mode }`, where `mode` is
`'agenda' | 'window' | 'full'` and `day` is `0..6` (Monday first), clamped on every resize so a
7-day week never leaves a stale index. The breakpoints are three `matchMedia` queries
(`(max-width: 767px)`, `(min-width: 768px) and (max-width: 1023px)`, `(min-width: 1024px) and
(max-width: 1279px)`), each with a `change` listener calling `sync()`. There is **no measurement of
the top bar**: it is fixed at `var(--top-bar-height)`, so `.tt-sticky { top: var(--top-bar-height) }`
needs no JS, and a width change already fires the media queries — no separate `resize` listener.

## 4. Markup contract

### 4.1 `partials/ui-tt-chrome.blade.php` (new)

Blade vars: `$ttScope` (the class hook, default `tt-responsive`), `$ttReadonly` (false for the staff
page, true for phase B), `$ttWeekNav` (already rendered by the caller — the include only wraps it).

```blade
{{-- the caller's own week row is WRAPPED by this block in the blade (a static wrap, never a
     runtime move), so it is sticky exactly as the preview has it --}}
<div class="tt-sticky" id="ttSticky">
    {{-- caller: @include('partials.ui-week-nav', …) sits here --}}
    <div class="tt-days" id="ttDayStrip" role="group" aria-label="Days of the week"></div>
</div>

<div class="tt-tools" id="ttTools" hidden>{{-- unhidden below 1280 by the module --}}
    <div class="tt-heading-block">
        <h2 class="tt-day-heading" id="ttDayHeading"></h2>
        <span class="tt-day-count" id="ttDayCount"></span>
    </div>
    <button type="button" class="tt-summary-btn" id="ttSummaryButton"
            aria-haspopup="dialog" aria-controls="ttSheet" aria-expanded="false">
        {{-- bar-chart glyph, 14px, aria-hidden --}} <span>Week summary</span>
    </button>
</div>

<div class="tt-window-grid" id="ttWindowGrid" hidden aria-label="Timetable for the visible days"></div>
<div class="tt-agenda" id="ttAgenda" aria-label="Classes for the selected day"></div>
<div class="tt-next-hint" id="ttNextHint" hidden></div>

<div class="tt-sheet-overlay" id="ttSheet" hidden>
    <div class="tt-sheet" role="dialog" aria-modal="true" aria-labelledby="ttSheetTitle">
        <div class="tt-sheet-head">
            <h2 class="tt-sheet-title" id="ttSheetTitle">Week summary</h2>
            <button type="button" class="tt-sheet-close" aria-label="Close week summary">&times;</button>
        </div>
        <div class="tt-sheet-body" id="ttSheetBody" wire:ignore></div>
    </div>
</div>
```

The sheet body is **filled by the module** from the page's existing nodes (legend bar + the five
cards' section + the semester range line), moved rather than copied, with a placeholder per node —
the `SummarySheet` discipline, which the house has used twice.

### 4.2 The agenda card

One card per event in the selected day, in time order, built by `buildAgenda()`:

```html
<button class="event-block tt-card is-overlap" data-id="…" data-sid="…" aria-label="…">
  <span class="tt-card-top">
      <span class="tt-time">10:00 AM ~ 11:00 AM</span>
      <span class="tt-venue">B103</span>
  </span>
  <span class="tt-course">AMSE2003 (L)</span>
  <span class="tt-note">
      <span class="ev-note">(Pending since 12 Nov)</span>
      <span class="tt-status status-cancelled">✕ Cancelled</span>
  </span>
  <span class="tt-overlap">Overlaps 10:30 AM ~ 12:30 PM · BMCS2023 (T)</span>
</button>
```

- Primary: `.tt-time` + `.tt-venue` at **14px / 700**; secondary `.tt-course` at 12px/500 grey;
  tertiary `.tt-note` at 11px/500 grey, right-aligned, carrying **at most** the date note and one
  status word (proposal §3.2/§3.3).
- The status word keeps the block's own colour (`.status-cancelled` etc.) at note size.
- `.tt-overlap` (11.5px/600 grey, dashed top border) appears **only** on a clash, on **every** card
  in that clash, in two forms: `Overlaps 10:30 AM ~ 12:30 PM · BMCS2023 (T)` when it names one other
  class, `Overlaps 2 other classes` when it names several. The card also takes `.is-overlap`, which
  draws the 5px left strip; the same words are appended to `aria-label`.
- The subject **name** is not on the card; it stays in `aria-label`, the hover tip and the sheet.
- **The desktop block gains the same status word, under the time, at ≥1280** (proposal §3.3 /
  exception 3): `makeEventBlock()` prints `✕ Cancelled` / `⚠ Conflict` under the time, in the block's
  own colour, and nothing else about the desktop block moves (code, venue, time, word, note). Public
  Holiday still prints no word inside a block. That is the only desktop change besides the six
  exceptions, and it rides the same builder edit as the tip migration — **inside the
  `responsive: true` branch**, so the three-caller byte-identical pin still holds.
- **The details pop-up's Status row prints `Class Cancelled`**, not `Cancelled` — a wording change in
  `openClassModal()`'s status line, because the bare word can be read as the *request* being
  cancelled. Blocks keep the short word (a 60–110px cell cannot carry the long one); the spoken
  label and the **agenda card's spoken status** take the long form; **the hover tip stays the short
  `CANCELLED`** — superseded by spec R4, because frozen §3.7/§6 make the migrated tip byte-identical to
  today's. The `.badge-cancelled` skin makes that row legible.
  **`Close` needs no work**: the shared footer already ships the grey `.btn-close-modal`.
- The card is a `<button>` that calls the page's own `onEventClick` — the page's existing handler,
  so a card opens the same details modal a desktop block does.

### 4.3 The day strip

One `<button class="tt-day" aria-pressed="…" aria-current="date"|absent>` per day:
`Mon<strong>16</strong>` — the abbreviation small, the **day of the month** bold, exactly as the
preview's own renderer writes it (`${d.abbr}<strong>${date}</strong>`) — a dot for today
(`.is-today::after`), `.is-holiday` on a public-holiday day. *(This line first read
`<strong>Mon</strong>3`, i.e. the abbreviation bold and the class COUNT beside it — inverted
against the preview. Corrected 2026-11-15 on the user's word: the chip answers "which day is
this?". The class count stays in the accessible name, R7.)*
`setDay(i)` re-renders the strip and the agenda; `aria-current="date"` only on the real today **and**
only while that day is the selected one. In **window mode** a tap moves the window so that day is its
first column (clamped at Sunday) — there is no agenda to scroll there.

### 4.4 Next-class hint

On an empty day and on a public holiday only (proposal §3.5): one button reading
`Next class: Wed 10:00 AM · AMCS1033` that calls `goToNextClass()` — sets `currentWeek` to that
event's week, calls the page's `prevWeek/nextWeek` equivalent, `setDay(thatDay)`, then **focuses
`#ttDayHeading` and scrolls it into view** (preview behaviour).

## 5. CSS (`public/css/theme.css`, one new section)

Every selector below is prefixed `.tt-responsive`, with one exception noted at the end.

- *(The strip's "ahead-of-today dimmed" state is **retired** — user, 2026-11-15: a Tuesday after a
  Monday looked grey and disabled. Only the today dot, the holiday red and the selected outline mark
  a chip now.)*
- **Layout/state**, all under `@media (max-width: 1279px)` except where noted: `.tt-sticky`
  (`position: sticky; top: var(--top-bar-height)` — **not** applied at ≥1280, where the preview keeps
  its controls in flow), `.tt-days`
  (flex, scrollable, `-webkit-overflow-scrolling: touch`), `.tt-day` (44×52 base, pressed and today
  states, ahead-of-today dimmed), `.tt-tools`, `.tt-day-heading` (15px/600), `.tt-day-count` (12px
  grey), `.tt-summary-btn` (44px), `.tt-agenda` (grid, 12px gap), `.tt-next-hint`, the sheet block.
- **Card**: the `.tt-card` block copied from the preview's `.preview-class` rules (three tiers, the
  5px `::before` strip with `.is-overlap`, `.tt-overlap` line, `.event-block::after { display: none }`
  so the legacy CSS tip cannot paint under the real one), plus `.tt-card.event-pending`
  (`border-style: dashed`) and `.tt-card.event-replacement` (`border-left-width: 6px`).
- **Window mode** (768–1279): the module renders a **transposed grid into `#ttWindowGrid`** (a
  container the chrome partial emits right under the strip). It is a small CSS grid —
  `grid-template-columns: 50px repeat(N, minmax(0, 1fr))`, `grid-template-rows: 62px repeat(22, 44px)`
  — built from the same `events()`/`days()` payload: a time axis down the left, one column per
  visible day, and each event placed with `grid-column`/`grid-row` from its day index and half-hour
  span. The legacy `#timetable` table is `display: none` in this mode (and back in `mode: 'full'`).
  This is the only second renderer this lane adds, it is flag- and scope-gated, and it replaces the
  impossible "hide day columns" idea — the real table has **22 half-hour columns** and days as rows.
- **Print**: `@media print` hides `.tt-days`, `.tt-tools`, `.tt-sheet-overlay`, `.tt-next-hint`,
  `#ttWindowGrid`, **`.tt-agenda`**; **un-hides the legend bar and `#summarySection`** whatever the `data-tt-mode`
  attribute says (below 1280 they are hidden on screen, and `SummarySheet`'s print unrelocate is
  gated off here, so without this rule a printout from a narrow window would lose them — today they
  print); and restores the legacy table's inline `display` so the full week prints at paper width.
  The print lane owns the rest; this lane only must not make printing worse.
- **`@media (any-pointer: coarse)` inside the scope**: the preview's block verbatim, i.e.
  `.tt-day { flex: 1 0 48px; min-width: 48px }`, `.week-arrow { width/height: 48px }`,
  `.week-select { height: 48px }`, the tablet grid rows `62px + 22×48px`, the summary card's info
  button 48px, the sheet footer buttons 48px, `.tt-next-hint` 48px.
- **`@media (prefers-reduced-motion: reduce)`**: no transitions (the app already ships a global
  rule; this lane adds none that bypasses it).
- **The one unscoped rule**: `.badge-cancelled { background: var(--color-surface-variant); color:
  var(--color-on-surface-variant) }` — the `.badge-reason` pair, no red (proposal §10.4).

## 6. JS

```
TimetableResponsive = {
    _state, _mq: [m1,m2,m3], _placeholders,
    init(cfg)     // cfg: { scope, pageKey, readonly, onEventClick, events(), days(),
                  //        findNextClass(fromWeek, fromDay) -> {week, day}|null }  (added 2026-11-15:
                  //        events() is the current week only, and the next-class hint must reach a
                  //        future week from the SAME payload). `weekNav` is optional and unused by
                  //        the tips, which come from the blade.
    sync()        // mode + day clamp; SETS data-tt-mode on the page root (the CSS keys on it),
                  // then buildStrip/buildAgenda, or the transposed window, or nothing
    setDay(i)
    buildStrip()
    buildAgenda() // the card builder of §4.2; reads cfg.events() (the SAME eventsData)
    nextClass()
    openSheet() / closeSheet()
    _relocate(node) / _restore(node)
}
```

- `init()` is called by the page's own `buildTimetable()` (after the grid is built), so the agenda
  and the grid always read the same week. `sync()` runs on each of the three media
  queries and after `buildTimetable()`; no `resize` listener is needed.
- **Window mode is the module's own transposed grid** (§5), rendered into `#ttWindowGrid` from the
  page's payload: `sync()` computes the window (`start = min(day, 7 - N)`, `N` = 2 or 3), hides
  `#timetable`, and renders one column per visible day and 22 time rows. Nothing in
  `buildTimetableGrid()` is involved in it. The `responsive: true` flag does exactly two things:
  the tip migration and the desktop status word (both below). With the flag absent — Cohort, Venue,
  Arrangement — the output is byte-identical, which the pin test asserts.
- **Tip migration**: with `responsive: true`, `makeEventBlock()` stops splitting the words across the
  legacy pair (`attr(data-name) ' · ' attr(data-tip2)`) and writes **one** `data-tip` whose text is
  those two pieces joined the same way — the exact expression is
  `data-tip = [dataset.name, [tipCtx, tipLect, status].filter(Boolean).join(' · ')].filter(Boolean).join(' · ')`,
  with `tipLect` empty on this page (the viewer is the lecturer) but kept in the expression so the
  student page and phase B compose identically — so the
  rendered sentence is byte-identical to today's. Without the flag the old attributes and the CSS
  path stay. The **week-nav tips are not a builder concern**: `partials/ui-week-nav` already takes
  `$prevTip` / `$nextTip` / `$selectTip` and is byte-identical when they are null, so the two blades
  pass `Previous week` / `Next week` / `Pick the week to display` — no shared file edited.
- **Sheet**: `openSheet()` moves `ui-legend-bar` and the `summarySection` block into `#ttSheetBody`
  with placeholders, and **builds** a semester-range line (`#ttSheetRange`, 12px grey) from
  `MockData.semester.chipText` — there is no range node to move, and the header's `#semesterChip`
  keeps its own copy, so the line is generated, not relocated. locks scroll with `tt-sheet-open`, traps Tab, honours Esc
  (capture, suspended while a modal above is open), returns focus to `#ttSummaryButton`. `closeSheet()`
  restores the nodes. `beforeprint` restores them; `afterprint` re-relocates.
- **One overlay at a time**: opening the sheet closes the bell (`closeNotifPanel()`); opening the
  bell closes the sheet.

## 7. The pages

### 7.1 My Timetable (phase A)

- The root `<div class="lw-page">` gains `tt-responsive` and `data-tt-page="staff"`.
- The week nav moves **inside** `.tt-sticky` in the blade (a static wrap); the legend bar and the
  summary section stay where they are and are hidden below 1280 by a scoped rule
  (`.tt-responsive[data-tt-mode="agenda"] .legend-bar, …[data-tt-mode="window"] .legend-bar`, and the
  same for `#summarySection`), so the phone shows the week row, the strip, the tools, then the
  agenda — the preview's order. At ≥1280 nothing is hidden: legend and cards stay inline as today,
  because the Week summary sheet does not exist there (proposal §9c).
- The chrome include (strip, tools, agenda, hint, sheet) sits **after** the legend/summary includes,
  so the desktop order is unchanged; below 1280 the two hidden blocks leave no gap.
- `buildTimetableGrid({ …, responsive: true })`.
- The page's `summaryButton` opt-in is **removed** (proposal §3.4 owns the Week summary button below
  1280; the header icon would duplicate it). `SummarySheet` then has nothing to open here.
- `TimetableResponsive.init({...})` at the end of `buildTimetable()`; the partial's controls are bound
  by the module (no inline `onclick`, so nothing needs exporting). The page passes
  `events: () => eventsData[currentWeek] || []` and `days: () => weekData[currentWeek].days`, so the
  agenda always reads the week the grid just drew.
- Changelog entry.

### 7.2 Student My Timetable (phase B)

The same include with `$ttReadonly => true`, plus exactly the proposal's §3b deltas: no
`modal-footer`/cancel wiring reaches a card (the card calls the page's details handler only), the
cohort chip stays in the header, the summary card keeps `Class Hours`, and the block's request state
(Pending / approved Replacement) is **colour + date note, no new word** (proposal §9d's default).
`responsive: true` on its `buildTimetableGrid()` call. Changelog entry.

## 8. What must not move

- `buildTimetableGrid()` output for **Cohort, Venue, Arrangement**: with no `responsive` flag, byte
  identical. The pin test compares the served markup of the three before/after.
- The class-details modal and the cancel modal: reused, no width pinned, no footer changes here.
- The print button: untouched **in function** (toast, tip, placement), while the `any-pointer: coarse`
  48px hit-box law still reaches it (spec R7); below 1280 the strip and the tools hide on paper only.
- `partials/ui-week-nav`, `ui-legend-bar`, `ui-summary-bar`, `ui-grid-table`: **not edited**; the
  chrome partial wraps them.

## 9. Behaviour table (the contract to verify)

| # | at width | expected |
|---|---|---|
| 1 | 320 / 360 / 390 / 430 | one day, agenda cards, no horizontal scroll, nothing clipped |
| 2 | ≤1279 | the week row stays visible while the agenda scrolls (sticky under the top bar; at ≥1280 it is in flow, as today) |
| 3 | 768 / 1023 | two days from the selected day; edges clamp at Mon / Sun |
| 4 | 1024 / 1279 | three days |
| 5 | 1280 / 1440 | today's full week, no strip, no Week summary button, geometry comparable |
| 6 | any | selecting a day re-renders the agenda; `aria-pressed` follows; today keeps its dot |
| 7 | empty day / holiday | the next-class button shows, jumps to the right week and day, focuses the heading |
| 8 | any | a card opens the details modal (`openClassModal`); closing returns to the same scroll position |
| 9 | ≤1279 | the Week summary sheet shows legend + five cards + semester range, one column below 390px |
| 10 | any | one card at a time can carry `Overlaps …` + the left strip; the words are in `aria-label` |
| 11 | any | block hover tips use the shared engine, same words as today; the arrows and the select have tips |
| 12 | touch, any width | controls ≥48×48; mouse-only unchanged |
| 13 | both themes | colours from tokens, no page errors |
| 14 | keyboard | strip reachable and operable, sheet traps and returns focus, Esc closes one layer |

## 10. Failure modes

| failure | what the user sees |
|---|---|
| the module never boots | the page keeps today's grid and the week row; the strip, agenda and tools stay empty and hidden by CSS (`#ttAgenda:empty`) — no blank page |
| `.tt-responsive` missing | every rule this lane adds is inert; the page is exactly today's |
| a media query crossing mid-scroll | the day is clamped, not reset; the agenda re-renders in place |
| the sheet's nodes are missing on a page | `openSheet()` no-ops and the button is hidden |
| a second sheet | impossible: these two pages stop opting into `SummarySheet` (§7.1) |

## 11. Tests and evidence

- New `tests/timetable-responsive.spec.ts`: the behaviour table above at **320 / 360 / 390 / 430 / 768 /
  1024 / 1280 / 1440**, both themes, keyboard-only arms, plus the touch rule under `hasTouch: true`.
- The three-caller pin (`tests/timetable-callers-pin.spec.ts` or a receipt inside the new spec):
  Cohort / Venue / Arrangement markup byte-identical — asserted by comparing the grid's `outerHTML`
  against a captured baseline, not by eyeballing.
- Existing geometry specs (`ui-regression` 17, `timetable-lane-layout` 13, the modal suites): run,
  and any failure named and justified (proposal §6/§7).
- `tests/e2e/timetable.spec.js` is **red before this lane** — its helper asserts that
  `#semesterProgress` / `#weekSubtitle` are *visible*, and both were removed on 2026-11-13 — so it is
  **amended** (assertion removed or inverted, dated comment), never "kept green", and never "fixed" by
  resurrecting that DOM.
- **`tests/summary-cards-button.spec.ts` is amended on purpose, and it is more than a page list.**
  Its `FLIP_PAGES` list contains **My Timetable** (the student timetable is not in it), so that page
  loses the `#summaryButton` receipts; and T11a pins *My Timetable's* `inSheet === true` at 390 /
  inline at 1440, which cannot hold once `SummarySheet` is gated off there. Both are named and
  justified in the receipt's own comment (proposal §6: never quietly weakened); the student page's
  receipts are untouched.
- Changelogs: `page-changelogs/my-timetable-changelog.md` and
  `page-changelogs/student-my-timetable-ui-changelog.md`.
- Phase split: phase A ships first (staff page only); phase B is the student blade's opt-in, and the
  same spec loops over both page roots.

## 12. Rollback

Remove `tt-responsive` from the blade, delete the include, and restore `'summaryButton' => true` on
that page (plus its spec receipts): every rule and the module go inert, `SummarySheet` resumes, and
the grid returns to today's rendering. The `responsive` flag can stay in the builder harmlessly; the
`.badge-cancelled` skin is a defect fix and stays.
