# Design: Timetable Print v2

**Date:** 2026-11-16 · **Against:** `proposal.md` (frozen 2026-11-16, round 4) and the `DESIGN CONTRACT` in `ui-prototype/timetable-print-preview.html:3-205`
**Supersedes:** the 13 Nov `design.md` wholesale — its five false statements (§11), its print-CSS repositioning of the screen grid (§12), and its 74-task shape all go.

> **How to read this file.** The contract carries the intent and the numbers in its
> own code comments; this file says **where** each rule lives, **who** produces the
> data and **what** may move. Where a number matters, it is quoted from the
> prototype, never re-invented. Every mechanism here is the proposal's decision
> implemented, not re-opened.

---

## 1. The shape of the solution

Two surfaces, one builder.

```
                    ┌──────────────────────────────────────────────┐
   screen ────────► │ #timetable  (unchanged: .event-block family)  │
                    └──────────────────────────────────────────────┘
                                     ▲
                                     │ buildTimetableGrid(cfg)   ← the SAME function
                                     ▼
                    ┌──────────────────────────────────────────────┐
   paper  ────────► │ .ui-print-sheet  table.print-table           │
   (off-viewport)   │  .print-block / .print-corner / .print-main  │
                    └──────────────────────────────────────────────┘
                                     ▲
                    ┌────────────────┴───────────────┐
                    │ .ui-print-header               │  title · context · semester
                    │  (title, week line, key)       │  week line · 3-chip key
                    └────────────────────────────────┘
```

- The screen grid is built by `buildTimetableGrid()` exactly as it is today, from
  the **full** event array (pending and cancelled included — the standing law).
- The paper is built by the **same** `buildTimetableGrid()`, pointed at a second
  set of ids and given the **print set** (proposal D1, D5).
- `window.print()` prints the paper and hides everything else.

The whole design rests on one property the builder already has: it takes its ids
from `cfg` (`public/js/ui-common.js:1034-1037`) and every other symbol in it is
call-local, so a second call paints a second table with no shared state.

---

## 2. DOM contract

### 2.1 What is added to each page

```blade
{{-- one include, this page's own values (§6) --}}
@include('partials.ui-print-header', [ 'printTitle' => …, 'printSub' => …, 'printContext' => …, 'printOwnership' => …, 'pageKey' => 'cohort' ])

{{-- the paper: off the viewport, laid out (never display:none — D4), invisible --}}
<div class="ui-print-sheet" id="printSheet" aria-hidden="true">
    <div class="grid-wrapper">
        <table class="print-table" id="printTimetable">
            <thead id="printTableHead"></thead>
            <tbody id="printTableBody"></tbody>
        </table>
    </div>
    <div class="print-empty" id="printEmpty">
        <strong id="printEmptyTitle">No classes this week.</strong>
        <span id="printEmptyText"></span>
    </div>
</div>
```

- Placed **after** the page's own `#timetable` wrapper (proposal D1): `.first()`
  selectors in existing suites keep finding the screen grid.
- Carries **its own class vocabulary** — `.print-table`, `.print-block`,
  `.print-corner`, `.print-main`, `.print-time`, `.print-note` — so the page-wide
  `.event-block` / `.timetable` scans in `tests/venue-status-parity.spec.ts:34`,
  `tests/venue-note-privacy.spec.ts:27` and `tests/venue-db.spec.ts:32` are not
  perturbed.
- `aria-hidden="true"` on the container, and every control inside it is
  non-focusable (`visibility: hidden` on screen; no `tabindex` is emitted in print
  mode). No keyboard user ever lands on paper.

### 2.2 What is removed from the page

Nothing. Not one node. **The screen DOM keeps every block it has**, including
pending and cancelled.

---

## 3. The print set — one derivation, four call sites

Each page already holds the data it needs: this week's array and, for My/Student,
the whole semester (`eventsData`). The print set is derived, never rebuilt from a
server round-trip.

### 3.1 The two derivations

```js
/* what runs: a request awaiting approval and a cancelled history row are not
   classes that will take place (PAPER SHOWS WHAT RUNS). Screen keeps both. */
function printRunSet(events) {
    return (events || []).filter(function (e) {
        return e.status !== 'pending' && e.status !== 'cancelled';
    });
}

/* the semester's normal timetable (D3): a deduplicated union, NOT a flat concat.
   The payload carries every class once per week with its OWN occurrence key, so a
   flat union would print 14 copies and a dedup on eventKey would silently do
   nothing. Identity is the class session plus its slot. */
function printSemesterSet(eventsData) {
    var seen = {}, out = [];
    (eventsData || []).forEach(function (week) {
        (week || []).forEach(function (e) {
            if (!e || e.status !== 'normal') return;
            var key = [e.sessionId ?? e.id, e.di, e.start, e.end, e.code].join('|');
            if (seen[key]) return;          /* first occurrence wins — stable */
            seen[key] = 1;
            out.push(e);
        });
    });
    return out;
}
```

Both live in `ui-common.js` beside `buildReplacementNote()` — they are pure, unit
-testable from the page, and identical on all four pages. **Collision policy:** two
*different* normal classes sharing a slot across weeks both survive and are stacked
by the builder's own lane planner; nothing is dropped to make the sheet tidier.

### 3.2 The days input — and the Sunday row is a days decision, not a blocks one

**Rows come from `cfg.days`, not from the events** (`buildTimetableGrid` walks
`cfg.days.forEach(...)` and appends one `<tr>` per day, unconditionally). So a
Sunday row is suppressed by filtering the **days input**, never by hoping the
blocks' absence removes the row:

```js
/* a Sunday with nothing on it is a dead strip of paper (contract ruling): drop it
   unless the PRINT SET really has a block that day. Measured against the print
   set, not the rendered grid, so a Sunday holding only a pending or cancelled
   block vanishes with its blocks (proposal D5). */
function printDays(days, printSet) {
    return (days || []).filter(function (day, di) {
        if (di !== 6) return true;
        /* a Sunday stays when it has SOMETHING REAL to say — a block that will
           print, or a holiday (which carries its own mark and note). The
           contract's skip condition is "no class AND no holiday". */
        if (day && day.holiday) return true;
        return printSet.some(function (e) { return Number(e.di) === 6; });
    });
}
```

- **Week view** — `printDays(weekData[currentWeek].days, printSet)`. The days are
  the page's own (so a holiday row and a Sunday are known); only a trailing Sunday
  is dropped.
- **Normal view** — `printDays(compositeDays, printSet)`, where the composite array
  carries **only** `abbr`, plus `date: ''`, `holiday: false`, `sunday: d === 6`,
  `today: false`. That is what makes "every public-holiday trace gone" a property
  of the build input rather than a CSS patch (AC12), and what lets `normal-view`
  drop the date line and print the day alone, upper-cased.
- **Warning for the implementer:** a `filter()` renumbers the survivors, which would
  break the `di` match between days and events. Sunday is the **last** index (6), so
  the rule is pinned as: **only trailing days may be dropped** — no entry before it
  ever moves.

### 3.3 Where it is called — every exit, not just the happy one

One new function per page, `buildPrintSheet()`, called at the end of that page's
existing `buildTimetable()` (so it re-runs on every week change) and once on
toggle change. It is the only entry point; nothing else knows about the paper.

**On Cohort it must also be called on both early returns**, because both exit
before the end of `buildTimetable()` and would otherwise leave the **previous**
week's or cohort's sheet in place — a sheet that lies about its week:

| Early return | Line | What the paper does |
| no cohort selected (`showGuidance(); return;`) | `cohort-timetable.blade.php:524-526` | rebuild with an empty print set → the printed empty panel carrying the page's "select a cohort" wording, never a stale grid |
| the week is empty (`weekEvents.length === 0`) | `:534-547` | rebuild with the empty print set → the "no classes" panel (unless the normal view is on) |

Cohort's semester source is `eventsByCohort[selectedCohortId]` — that page's only
whole-semester array. My and Student read `eventsData`. **Venue has no semester
union**: its normal view prints the current week's normal classes, because on a
room sheet one teaching week *is* the semester's pattern — stated here so nobody
invents a union for Venue.

---

## 4. The builder's print mode

One new optional `cfg.print` flag. **Absent = byte-identical to today**, which is
what keeps the pin's synthetic receipt and Cohort's receipt honest (D2).

| Aspect | Default (screen) | `cfg.print: true` |
|---|---|---|
| ids | `tableId`/`headId`/`bodyId`/`emptyId` defaults | the caller's print ids |
| class vocabulary | `.event-block`, `.ev-*` | `.print-block`, `.print-*` |
| hour header | `hour-top` = `hours[i]` (24-hour) | `hour-top` = `to12h(hours[i])`, `hour-bottom` = `to12h(hours[i+2] || add30min(hours[i+1]))` — start over end, and the final column still names where it stops because it uses the same fallback |
| block content | `.ev-code`, `.ev-venue`, `.ev-time`, note, status word | `.print-corner-left`, `.print-corner-right`, optional `.print-band`, `.print-main > .print-main-line…`, `.print-time`, `.print-note` (§5) |
| status word | `✕ Cancelled` / `⚠ Conflict` under a ≥1280 rule | **none ever** — a shape that has no arm at all, rather than an arm to hide (`NO INVENTED STATUS WORD`) |
| focusability | `tabindex="0"` | none |
| click handler | `cfg.onEventClick` | still called, but the sheet is `aria-hidden` and not visible to a pointer |
| empty week | hides the table, shows the caller's `emptyId` | same branch — which is why the venue print call must not pass `cellRender` (§9) |

The **live screen header is not touched anywhere**: the contract's ".hour-12 swap"
is the print sheet's own rendering, because under two tables there is no shared
header to swap (proposal D1).

### 4.1 The per-page block shape is config, not a second builder

The four shapes are data, supplied by the page through `cfg.printShape`:

| Page | corner-left | corner-right | centre lines | note |
|---|---|---|---|---|
| Student | venue | lecturer surname | course | `buildReplacementNote()`, ungated |
| Cohort | venue | lecturer surname | course | same, gated on the lecturer being the viewer |
| Venue | course | lecturer surname | cohorts (one per line) | same, gated on the payload's `e.mine` |
| My | venue | course | cohorts (one per line) | same, ungated |

**Each page keeps its own screen note gate.** My and Student read the note
ungated; Cohort and Venue gate it on ownership, exactly as their screen builds do
(`cohort-timetable.blade.php:589-593`, `venue-timetable.blade.php:1727-1734`) —
another lecturer's request facts ("Pending since …", "Proposed ⏳") must not print
on the cohort sheet or the room sheet either.

`centre lines` returns an array. **Fallback:** when a cohorts-leading page has no
cohort data on the event, the centre falls back to the course — never an empty
block (the prototype does the same for an older shared link).

**Surname rule** — one shared helper, `printSurname(lecturer)`: strip a leading
honorific, take the first remaining token (`En. Lim Jia Zheng` → `Lim`,
`Dr Tan` → `Tan`). Malaysian Chinese names put the surname first, which is why the
first token wins; that reason lives in the prototype's comment and is not repeated
here. The honorific set is pinned in `specs/`. **A missing or `—` lecturer prints an
empty corner, never the placeholder** — `baseEvent` sets `'lecturer' => '—'` when
there is none (`ResolvesTimetableTimeline.php:548`), and the contract forbids
printing it.

**Ownership's source differs per page, and the key depends on it.** Only the venue
payload carries `e.mine` (`VenueTimetable.php:393`); Cohort and the two personal
pages test `e.lecturer === MockData.currentUser.name`. AC10 gives the Cohort paper
key an ownership hint, so its border weights must come from that comparison — a
print shape that reached for `e.mine` on Cohort would paint every block as
"others'" and make the key false.

---

## 5. Block layout and the fit

Structure per block (the prototype's `buildBody()`, `:1979-2045`):

```
.print-block
  .print-corner.print-corner-left     venue | course      (clips with an ellipsis)
  .print-corner.print-corner-right    surname | course    (clips with an ellipsis)
  .print-band                         empty; height measured, only when ≥2 centre lines
  .print-main > .print-main-line…     the centre, bold
  .print-time
  .print-note
```

- **The band** is inserted at build time only when the centre has two or more
  lines. Its height is set by the fit pass from the tallest corner that actually
  rendered **plus 3px**, so a wrapped course label opens its own gap instead of a
  guessed padding.
- **The fit** shrinks a centre with **three or more** lines half a pixel at a time
  until its content stops overflowing, never below **7px**, and reserves the note's
  strip by hand (the note is positioned out of flow, so it adds nothing to
  `scrollHeight`). Two-line centres keep the base size; they only get the band.
- Sizes are written as **custom properties on the block**, and the fit owns the
  numbers — **paper-only**. There is no on-screen print-shape block, so no screen
  counterpart is computed: the stylesheet consumes `--print-main-size` and
  `--print-band`, and nothing else reads them.

### 5.1 Where the measurement happens (D4)

The sheet is laid out **off the viewport at the paper's content width**:

```css
.ui-print-sheet {            /* screen media — the paper's own geometry */
    position: absolute;
    left: -10000px;
    top: 0;
    width: 1062px;           /* A4 landscape 1123 − 2 × 8 mm (8 mm = 30.24px at 96dpi) */
    visibility: hidden;      /* laid out, measurable, unfocusable, invisible */
    pointer-events: none;
}
```

`1062px` is derived in one comment from `@page` (1123 − 2 × 8 mm, i.e. 1123 −
60.5), which matches the prototype's own basis (`.sheet { width: 1123px }` plus
`margin: 8mm`), and the same constant is used by the CSS and by the measuring
pass, so they cannot drift. A short sheet would print with a right-hand gap; a
narrower measure would silently over-shrink the centres.

- `beforeprint` cannot measure: it fires around the print render, and page JS never
  sees print metrics. So the fit runs **on rebuild** and on **resize** (debounced
  120 ms), against the off-viewport sheet in screen media.
- That is only faithful because the sheet's **structural rules are outside any
  media query** (§7). If they sat in `@media print`, the measurement would size
  against screen metrics and the static fallback would hide why.
- **Static fallback:** if the sheet is missing, or its width measures `0`, the fit
  writes the static per-line-count size for that centre (1 / 2 / 3+ lines) and
  stops. Never a half-measured size.

### 5.2 Public-holiday rows

A row whose day is a holiday paints **every** block on it with the conflict skin
and keeps the blocks' content (`NO INVENTED STATUS WORD`; the key explains the
colour). **The PH skin wins over ownership on such a row** — the venue screen
splits its PH branch by ownership
(`venue-timetable.blade.php:1668-1676`: mine → `event-conflict`, others' → the
`event-public-holiday` hairline), and that split must **not** be reused on paper:
on a public holiday nothing runs, so every block on the row is conflict-red and
ownership survives only in the border weight (which the dense rule's "2px vs 1px"
already assumes). Implemented as a build-time class (`print-holiday`) derived from
the `days` input, not as a post-hoc DOM walk.

A **Sunday** row is emitted only when the print set has a block on it — a
**days-input** decision (§3.2), because rows come from the days and not from the
blocks.

---

## 6. The print header

### 6.1 Markup

One new partial, `partials/ui-print-header.blade.php`, included once per page:

```
.ui-print-header
  .print-head-left     .print-title  + .print-sub
  .print-head-centre   .print-context            (em dash where the context is not a person)
  .print-head-right    .semester-chip + .week-line
  .print-key           .print-key-item × 3  +  .print-ownership (cohort/venue only)
```

- The **week line is two parts**, always: `.week-count` (bold — `Week 5 of 14`, or
  `Week 1 ~ 14` in the normal view) and `.week-dates` (quiet — `· 5 Oct 2026 ~ 11 Oct 2026`,
  or `(Whole Semester)`). One em dash, spaces either side, never the ASCII `--`.
- The **key** is the paper's own: `.print-swatch` + label, three items
  (**Normal · Replacement · Conflict / Public Holiday**), stacked above the
  ownership hint. The screen's legend and its `ui-legend-bar` partial are **not**
  reused — the paper key is a print surface, and it needs no per-item hook on the
  shared partial (which was an open question at explore; two tables dissolved it).
- `Available` never appears (blank paper also means held, too soon and holiday);
  `Pending` never appears (it never prints).
- The ownership hint prints on **Cohort** and **Venue** only — the two pages whose
  screen legend carries one. My and Student have no ownership line on screen, so
  the paper does not invent one.
- The whole key is hidden in the normal view (a key for one status explains
  nothing).

### 6.2 Per-page context — who fills what

| Page | Title / subtitle | Context | Filled by |
|---|---|---|---|
| My | `My Timetable` / `Weekly class schedule` | staff name + `(staff ID)` | blade, from `MockData.currentUser` (`my-timetable.blade.php:187`) |
| Student | `Student My Timetable` / `Cohort: <code>` (`Cohort: —` when the student has none — the em dash is the app's "no data") | student name + `(student ID)` | blade, from a new `studentNumber` view var (§10) + `$cohortCode` |
| Cohort | `Cohort Timetable` / `Faculty: <code>` | selected cohort code | **page JS** — the selects change without navigation, so `PrintSheet.setContext()` runs on every rebuild and on cohort/faculty change; prints the page's own empty wording when nothing is selected |
| Venue | `Venue Timetable` / `Venue occupancy` | `<code> — <name>` | blade, from `$venue` |

**Venue's context has three traps an earlier draft of this design hit, recorded so
they cannot be re-hit.** `$venue` is an **array** (`const venueInfo = @json($venue)`,
`venue-timetable.blade.php:574`), and `VenueTimetable.php:157-159` passes
`'name' => ''` on the empty-venues path — so:

1. test with `!== ''`, never `??`, or a missing name prints a bare `Venue:`;
2. fall the name back to the room code (`room_name ?? room_code`) before the em
   dash, so a room with no name still identifies itself;
3. print `Select a venue` only when **both** are empty — never a bare `—`.

The **week line** has no existing source to copy: `#weekSubtitle` exists on no live
page (explore §1.5), so `PrintSheet` composes it — the bold count from
`currentWeek + 1` and `MockData.semester.weeks`, the quiet part from
`weekData[currentWeek].range`; in the normal view `Week 1 ~ 14` +
`(Whole Semester)`.

The semester chip's text is **copied from `#semesterChip`** at build time, so the
paper can never disagree with the screen about which semester it is showing.

---

## 7. CSS architecture

### 7.1 Where each rule lives — the split that D4 requires

| Rule family | Where | Why |
|---|---|---|
| paper palette (`--p-*`), sheet structure, block layout, corner/centre/band sizes, row heights, column widths, dense spacing, header, key | **outside any media query**, scoped to `.ui-print-sheet` / `.ui-print-header` | the sheet must be measurable in screen media (D4) and it is invisible on screen anyway |
| `@page`, chrome hiding, the surface swap (`#timetable` out, sheet in), page-break rules, `print-color-adjust` | **inside `@media print`** | these are about the sheet of paper, not about the sheet's own layout |

This is the one structural rule a future editor must not break: **move the sheet's
sizes into `@media print` and the fit silently stops working.**

### 7.2 The paper palette

One fixed light set, declared **once** in `theme.css`, referenced only by print
rules: `--p-bg`, `--p-surface`, `--p-on-surface`, `--p-on-surface-variant`,
`--p-outline`, `--p-surface-variant`, plus the three status fills/borders. Paper is
light in either screen theme, so it cannot ride the theme-swapped `--color-*`
tokens (UI rule 1's sanctioned shape: a token set added to `theme.css` once — not a
hardcoded colour in a page's `page-styles`).

### 7.3 The print block

```
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    /* the surface swap */
    … chrome hidden …  .ui-print-header/.ui-print-sheet { position: static; visibility: visible; }
    /* breaks */
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
    .print-key { break-inside: avoid; }
    /* colour */
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
```

**Two sheet-structure rules that must not be lost:** the **day stack is centred**
in its day cell, and in the normal view the date is dropped and the day prints
alone, upper-cased at the contract's size. Both are sheet structure, so they live
in the §7.1 scope (outside media queries), not inside `@media print`.

**Variable names differ from the prototype on purpose.** The contract's
`--ev-main-size` / `--ev-main-size-print` / `--ev-band` / `--ev-band-print` are
**this lane's** `--print-main-size` / `--print-band`, because on a live page there
is no screen counterpart to carry. Anyone grepping the prototype for the contract's
names should land here and find the mapping, not a missing rule.

The sheet keeps its own size as well as the `@page` rule, because an engine that
ignores `@page` falls back to portrait and the sheet must stay a readable table
(the prototype's comment says exactly this).

### 7.4 Dense mode

Computed by `PrintSheet` from the **print set** — `> 32` printed blocks or `> 6`
replacements — and applied as `data-print-dense` on the sheet root. It tightens
padding, gap and the children's line-heights and drops `min-height`. It **never**
touches a font size, and it never flattens the ownership border weights: the two
weights move together or not at all, or the key would be false. Because the styling
lives in the sheet's own scope (§7.1), a busy week changes **nothing** on screen
(the contract's "applies on screen too" was preview parity; a live page has no
on-screen sheet).

### 7.5 Withdrawing task 8's print rules

`theme.css:6789-6820` currently re-shows `.legend-bar` and `#summarySection` in
print and brings `#timetable` back. Under two tables that is exactly wrong:

| Task 8's rule | This lane |
|---|---|
| `.tt-responsive .legend-bar { display: flex !important }` | **removed** — the paper key lives in the print header, and the screen legend must not print |
| `.tt-responsive #summarySection { display: block !important }` | **removed** — no printed summary strip (proposal D8) |
| `.grid-wrapper`/`#timetable` forced visible | **replaced** — the screen grid is hidden in print; the sheet is shown |
| `.tt-days`/`.tt-tools`/`.tt-sheet-overlay`/`.tt-next-hint`/`#ttWindowGrid`/`.tt-agenda` hidden | **kept**, and joined by the rest of the screen chrome |
| empty-week panel swap (`data-tt-empty-week`) | **kept for the screen**; the paper's empty panel is the print sheet's own `#printEmpty` |

Each removal carries a comment naming this lane and the contract subject it obeys,
so the next reader sees a decision rather than a deletion.

---

## 8. The Normal Class toggle and the print button

### 8.1 The print button

`partials/ui-week-nav.blade.php:34-46` loses the stub and gains the real control:

- the action becomes `window.print()`; the toast call, the `data-tip="Coming soon"`
  and the now-false "printing ships post-mock" comment go with it;
- a real accessible name (`aria-label`) and a hover tip that says what the control
  does, on the shared `data-tip` pill (`TOOLTIPS`) — never a native `title=`;
- the icon, the class and the 32×32 / 48×48 geometry stay, so nothing on the page
  moves (a control that starts working, not a new control);
- the `$showPrint` opt-in stays exactly as it is, so the arrangement page and the
  frozen templates keep today's behaviour.

### 8.2 The toggle, and the module behind it

`PrintSheet` is the `ui-common.js` object this lane adds: it owns the toggle
binding, the print header's context and week line, the print-set derivations (§3),
the fit pass (§5.1) and the dense flag (§7.4). Pages call
`PrintSheet.rebuild(cfg)` from `buildPrintSheet()`; nothing else touches the paper.

- **Home:** inside the shared `partials/ui-week-nav.blade.php`, beside the print
  button, behind its own opt-in `$showNormalToggle` (default `false`). One control,
  one position on all four pages; the arrangement page and the frozen templates
  pass nothing and stay byte-identical.
- **State:** page-local, survives week changes, resets on reload (nothing is
  persisted — it is a reading mode, not a preference).
- **Effect:** `buildPrintSheet()` reads it. The **sheet** filters to the semester
  union, relabels the week line, drops the date line from the day cells, hides the
  key, and prints `No normal classes.` when nothing survives. **The screen grid is
  untouched** — a toggle whose only visible screen change is its own state (AC12).
- The sheet's state class is **`normal-view`**, deliberately not the toggle's own
  `.normal-only` (that label sets weight, cursor, `nowrap` and flex — the prototype
  records the bug where a sheet carrying it turned bold).

---

## 9. Venue specifics

- The venue print build calls `buildTimetableGrid()` on the **default path** — no
  `cellRender`, no `laneLayout`, no `baseRowDays`, and **no** `bookableBadge`,
  `tooltipExtra`, `tipLecturerFn` or `responsive` either: the "Bookings open" chip,
  the hover tips and the ≥1280 status word are screen affordances and must not
  ride onto paper. That is deliberate:
  1. the empty-week branch only runs without `cellRender` (`ui-common.js:1049-1056`),
     so a print set emptied by the filter reaches the printed "no classes" panel
     instead of a blank table (AC8);
  2. AC11 is satisfied by construction — on paper there are no free / no-room /
     too-soon / held / holiday / lane-spacer cells, only class blocks;
  3. ownership still paints through the print shape's own `statusClassFn` over the
     payload's `e.mine`, which is the server's truth.
- A public-holiday row paints its blocks conflict-red; a day with no classes prints
  nothing extra — the "remaining holiday slots show as empty PH cells" behaviour is
  a **screen** rule and does not travel.
- **The `' - '` → `' ~ '` change** (`venue-timetable.blade.php:1715`) is a screen
  change the contract orders. It is one line, it is called out in the venue
  changelog, and it is the single permitted movement in the pin's venue receipt
  (proposal D2).

---

## 10. Per-page wiring

| Page | `buildPrintSheet()` added to | Print shape | Context source | Notes |
|---|---|---|---|---|
| My | `buildTimetable()` (`my-timetable.blade.php:361+`) | venue / course / cohorts | `MockData.currentUser` | semester union is available (`eventsData`) |
| Student | same (`student-my-timetable.blade.php`) | venue / surname / course | new `studentNumber` var + `$cohortCode` | read-only: no cancel wiring anywhere near it |
| Cohort | `buildTimetable()` (`cohort-timetable.blade.php:546+`) | venue / surname / course | page JS (`selectedCohortId`, faculty select) | already returns early with `showGuidance()` when no cohort is picked — the print sheet follows the same empty state |
| Venue | `buildTimetable()` (`venue-timetable.blade.php:1595+`) | course / surname / cohorts | blade `$venue` | default path (§9) |

**Server change, additive only:** `app/Livewire/StudentMyTimetable.php` passes one
new view value, the student's own number, alongside the `name` it already sends.
`render()` already loads `$student` (`:44`), so this is one array key — no query, no
schema, no migration. It exists because the contract prints the ID only where the
context is a person, and the student page currently sends the name alone.

**Prints once / repeats:** the header is outside the table, so it prints once; the
`thead` repeats because of `display: table-header-group`.

---

## 11. The five stale statements this design replaces

The 13 Nov `design.md` is superseded. For the record, the statements it carried
that are now false, and what this design says instead:

| 13 Nov design.md | Now |
|---|---|
| `:428` dense counts `pending \|\| replacement` | replacements only (`>6`), plus blocks `>32`, both from the print set |
| `:36`, `:217-218` the printed key reuses the screen legend, five chips | the paper key is the print header's own, three chips |
| `:405` `.cell-no-fit` stays red on paper | no non-class cell exists on paper at all |
| (pending/cancelled print) | neither prints; the screen keeps both |
| `:115`, `:160` a `print-filter-note` reading `Normal Class only` | no such note; the week line and the missing key say it |
| the whole print-CSS repositioning of the screen grid (`td.hour-cell { height: auto }`, blocks absolute→static, 768/1024 px resets) | **retired** — under two tables those rules either move to the sheet's own scope or die |

---

## 12. Promoted to shared

| What | Where | Why it qualifies |
|---|---|---|
| the print surface classes + paper palette | `theme.css` (one print region) | four pages, identical |
| the print header | `partials/ui-print-header.blade.php` | four pages, per-page values only |
| the print build path, `printRunSet()`, `printSemesterSet()`, `printSurname()`, the fit pass, dense | `ui-common.js` | four pages, one implementation |
| the Normal Class toggle | `partials/ui-week-nav.blade.php` (opt-in) | it is a control on four pages and it belongs beside the print button |

No page keeps a local copy of any of them.

---

## 13. Receipts the implementer must land

| Receipt | What it proves |
|---|---|
| `tests/timetable-print.spec.ts` (new) | on all four pages: the button calls `window.print()` (stubbed); the sheet exists and is not visible on screen; under `emulateMedia({ media: 'print' })` the screen chrome and `#timetable` are hidden and the sheet + header are visible; the hour axis reads 12-hour start-over-end; the key has exactly three chips; no pending/cancelled block is in the sheet; a PH row's blocks carry the holiday skin; a Sunday row with nothing on it is absent; the empty panel appears when the print set is empty |
| the same spec, normal-view cases | the week line reads `Week 1 ~ 14 (Whole Semester)`; the union is deduplicated (no duplicate block for one class); no `[Hn]` mark and no holiday note; the key is hidden; `No normal classes.` when nothing survives; **the screen grid keeps every block** |
| `tests/venue-timetable.spec.ts` TC05 | rewritten — it pinned the "coming soon" toast |
| `tests/timetable-callers-pin.spec.ts` + fixture | synthetic and Cohort byte-identical; the venue receipt re-captured once, diffed and logged with the separator as its only delta |
| dense receipt | `data-print-dense` from a print set over the threshold, computed spacing changes, border weights still unequal, **no screen rule moved** |
| existing screen suites | all still green — the pin, `timetable-responsive` (33), `venue-status-parity`, `venue-note-privacy`, `venue-db`, `pages-parity` |
| manual PDFs | four pages + normal view: A4 landscape, 8 mm, one page for a normal week, header once, `thead` repeated, paper light in both themes, a wide week still on one page |

Instrument boundary, stated once: `emulateMedia` can prove **visibility**; it cannot
see `@page` size or page count — those come from a PDF exported with
`page.pdf({ preferCSSPageSize: true })` and from a manual print.

---

## 14. Risks this design carries

| Risk | Answer in this design |
|---|---|
| Measurement in screen media silently wrong | the sheet's structural rules are outside media queries (§7.1), and the width constant is shared with `@page` |
| The off-viewport sheet polluting a screen suite | own class vocabulary + DOM placement after `#timetable` (§2.1) |
| The off-viewport sheet stealing keyboard focus | `visibility: hidden` + no `tabindex` in print mode (§2.1) |
| Two tables drifting | the same builder, one flag; the print surface has no layout code of its own |
| A print-set term drifting into the screen path | the derivations are pure functions called only by `buildPrintSheet()`; the screen build never sees them |
| The venue page's rich cell grid leaking onto paper | the venue print call passes no `cellRender` (§9) |

**Rollback:** every piece is additive behind one opt-in flag, one opt-in partial
argument and one new CSS region. Reverting the lane's commits restores today's page
with no residue — and the screen grid was never edited, so there is nothing to
un-edit.
