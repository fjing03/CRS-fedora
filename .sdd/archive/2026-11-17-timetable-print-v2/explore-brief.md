# Explore Brief — Timetable Print v2

**Date:** 2026-11-16 (supersedes the 2026-11-12 brief in this file)
**Status:** explore only. No application file, test, prototype or database was changed.

**Why this brief was rewritten.** The 2026-11-12 brief was measured against the
prototype as it stood on 12–13 Nov. The prototype settled its design on
2026-11-15/16 and now carries a `DESIGN CONTRACT` block
(`ui-prototype/timetable-print-preview.html`, lines 3–205) that is organised by
subject and is authoritative. Four rulings in it contradict nothing in the old
brief but **add** what it never had (paper shows only what runs, the 12-hour hour
axis, one header, the block corners/centre, the Normal-Class-as-semester view,
the three-chip paper key, PH-row paint). Five lines of the lane's `design.md` are
now factually wrong, verified below.

**The old brief's still-true findings** (kept, re-verified today): the target is
four Livewire blades, not the legacy templates; the parameter is `showPrint`, not
`showPrintBtn`; the arrangement page is out by `showPrint => false`; the button is
a stub; `buildTimetableGrid()` is the one grid engine; `window.print()` + one
`@media print` block is the approach (no library, no server render, no new
dependency); there must be a print-only context line because the page title alone
does not say which cohort/venue is on the sheet.

---

## 1. What exists today, per page

### 1.1 The button — one shared stub, four callers

`resources/views/partials/ui-week-nav.blade.php:37-46`:

```blade
@if($showPrint)
    {{-- Enabled stub — click shows the shared "coming soon" toast; printing ships post-mock --}}
    <button type="button" class="print-btn" data-tip="Coming soon" onclick="toast.show('Printing is coming soon')">
```

`$showPrint = $showPrint ?? false` (line 9). Callers passing `true`:

| Page | Blade | Line |
|---|---|---|
| My Timetable | `livewire/my-timetable.blade.php` | 63 (inside a rendered string for `ui-tt-chrome`) |
| Student My Timetable | `livewire/student-my-timetable.blade.php` | 52 (same mechanism) |
| Cohort Timetable | `livewire/cohort-timetable.blade.php` | 123 (inline in `.semester-bar`) |
| Venue Timetable | `livewire/venue-timetable.blade.php` | 395 (inline in `.semester-bar--venue`) |

Arrangement passes `'showPrint' => false` (`replacement-arrangement.blade.php:2609`)
— the contract's `SCOPE` requires it stays false. Two frozen
`ui-design-templates/*` copies pass `true`; they are the D10 fallback branch,
render only when the component class is missing, and are **not** in scope.

**One test pins the stub and must move with this lane:**
`tests/venue-timetable.spec.ts` TC05 (`:75-81`) clicks `.print-btn` and asserts
`#toastBar` contains `Printing is coming soon`.

### 1.2 The existing print CSS — four blocks, no page size

`public/css/theme.css` holds exactly four `@media print` blocks today:

| Line | What it does | Lane relevance |
|---|---|---|
| 2211 | hides the shipped summary sheet + its opener, drops `body.summary-sheet-open` | keep |
| 5510 | hides the filters overlay + button | keep |
| 6065 | `.ui-footer { display: none }` | keep |
| 6789 | **from task 8**: hides `.tt-days`, `.tt-tools`, `.tt-sheet-overlay`, `.tt-next-hint`, `#ttWindowGrid`, `.tt-agenda`; **re-shows** `.legend-bar` and `#summarySection`; restores `.grid-wrapper`/`#timetable`; swaps the empty-week panel | partly conflicts — see §3.4 |

There is **no `@page` rule anywhere in the app** (a grep for `@page` in
`public/css` and `resources/views` returns nothing), so today a browser prints
this app at the default paper size and margin. `ui-print-header` appears nowhere
either — it is a new node.

`.print-btn` is styled at `theme.css:2989-3006` (32×32 icon button, rightmost slot
of the toolbar row) and gets 48×48 on coarse pointers (`theme.css:6854`).

### 1.3 The grid markup that print has to work with

**Shell** — `partials/ui-grid-table.blade.php`:

```
.grid-wrapper > .grid-scroll > table.timetable#timetable
                                   > thead#tableHead + tbody#tableBody
```

**Hour header** — `buildTimetableGrid()` (`public/js/ui-common.js:1058-1066`)
emits one `th.hour-header[colspan=2]` per hour pair with two spans:

```js
'<span class="hour-top">'   + hours[i] + '</span>' +
'<span class="hour-bottom">' + (hours[i + 2] || add30min(hours[i + 1])) + '</span>'
```

`hours` is the 08:00–18:30 constant (`ui-common.js:799-804`), so **both lines are
24-hour**. `.hour-24` / `.hour-12` do not exist in the app — the contract's
"`.hour-24` shows on screen, `.hour-12` in print" describes nodes that must be
added, and the swap itself must be a print-media rule (CSS cannot convert
24-hour text, so the 12-hour string has to be rendered).

**Day cell** — `HtmlBuilder.dayHeader()` (`ui-common.js:3934-3949`) into a
`td.time-col`:

```
.day-label (Mon) + .date-label (5 Oct 2026) + .holiday-badge | .today-badge | .off-badge
```

Note the live sheet puts the day in a **`td`**, the prototype in `th.day-col`.

**Event block, default path** — `makeEventBlock()` (`ui-common.js:1220-1260`),
used by My / Student / Cohort:

```html
<div class="event-block span-N event-…" tabindex=0 data-name data-venue data-sid [data-tip|data-tip2]>
  <span class="ev-code">AMSE2003(L)</span>
  <span class="ev-venue">B100</span>
  <span class="ev-time">2:00 PM ~ 4:00 PM</span>   <!-- already 12-hour: to12h() -->
  [<span class="ev-note tt-block-status">✕ Cancelled | ⚠ Conflict</span>]  <!-- responsive flag + ≥1280 CSS only -->
  <span class="ev-note">(Replaced for 25-Dec-2026)</span>
</div>
```

Two facts the old papers did not record: **block time is already 12-hour on
screen** (`to12h()`), so only the hour *axis* needs a print arm; and the
status word is `cfg.responsive`-only and additionally hidden below 1280 by CSS,
so it never reaches a ~1047px printed page anyway (the contract's
`NO INVENTED STATUS WORD` holds without new work).

**Event block, venue path** — its own `cellRender`
(`venue-timetable.blade.php:1613-1730`) builds the same four spans and the same
shared note, but joins the time with **`' - '`** (line ~1712), not `' ~ '`. The
contract makes the tilde a **screen** change on this lane.

**Venue non-class cells** — all must print bare paper (`VENUE ON PAPER`):
`.cell-sun`, `.cell-content.cell-ph`, `.cell-content.cell-held`,
`.cell-content.cell-too-soon`, `.cell-content.cell-available` (optionally
`.cell-no-room`), `.cell-content.lane-spacer`; continuation cells are `td`s with
inline `display:none`. The red `.cell-no-fit` day-end arm the old `design.md:405`
still mentions **does not exist** — it was retired 2026-11-14 by
`venue-evening-tail`, whose comment is still in the blade
(`venue-timetable.blade.php:1786-1800`).

### 1.4 Legend, summary, chrome

- **Legend** — `partials/ui-legend-bar.blade.php`. Default items are **4**
  (Normal Class · Replacement · Pending · Conflict / Public Holiday); the status
  class sits on the `.legend-swatch`, not on the `.legend-item`. Cohort passes its
  own 4 + `ownershipHint => true`; **Venue passes 5** — `Available` first, then
  Normal, Replacement, Pending, Conflict/PH — plus the ownership hint
  (`venue-timetable.blade.php:400-410`). My / Student pass nothing, so they get
  the default 4 and **no** ownership hint.
- **Summary** — `partials/ui-summary-bar.blade.php` → `#summarySection`
  (`.summary-section`), five flip cards.
- **Screen chrome to hide on paper** — `partials/ui-nav-bar` (`.top-bar`,
  `.nav-drawer`), `ui-session-countdown`, `ui-footer` (already hidden),
  `.page-header`, `.semester-bar` / `.tt-sticky`, plus the task-8 furniture
  already hidden in `.tt-responsive` scope.

### 1.5 The data the print header needs — measured, not assumed

| Page | Contract's header context | Available today? |
|---|---|---|
| My | staff name + `(staff ID)` | **Yes** — `MockData.currentUser = {name, staffId}` (`my-timetable.blade.php:187`) |
| Student | student name + `(student ID)`, subtitle `Cohort: <code>` | name **yes**; **student ID is not passed to the view** — `student-my-timetable.blade.php:169` sends `{name}` only. `StudentMyTimetable::render()` already loads `$student` (`app/Livewire/StudentMyTimetable.php:44`), so exposing `student_id` is additive view data, no schema change |
| Cohort | cohort code, subtitle `Faculty: <code>` | **yes, client-side** — `selectedCohortId` + `#facultySelect`/`#cohortSelect` values (`cohort-timetable.blade.php:252-345`) |
| Venue | `<venue code> — <venue name>`, `Select a venue` when both empty | **yes** — `$venue = {code, name, …}` served to the blade (`venue-timetable.blade.php:574`, from `app/Livewire/VenueTimetable.php:869-881`) |
| all | semester chip | `#semesterChip`, filled from `MockData.semester.chipText` (server `semesterJs`; the live string is `202605 · 21 Sep 2026 ~ 27 Dec 2026`) |
| all | week line | **`#weekSubtitle` does not exist in any live blade.** `updateWeekSubtitle()` (`ui-common.js:307-320`) no-ops on a null element. The real sources are `weekData[currentWeek]` (`{label, range, rangeShort, days[7]}` from `generateWeekData()`, `ui-common.js:64-104`) and `MockData.semester.weeks` (14) |

---

## 2. The delta to the prototype's `DESIGN CONTRACT`

Ordered by contract subject. "Screen" = the live page today; "paper" = what the
contract requires.

| Contract subject | Paper requires | Live screen today | Delta |
|---|---|---|---|
| `ONE SHEET` | A4 landscape, 8 mm, one page for a normal week, paper light in both themes | no `@page`; page prints at default size | new `@page` + a fixed paper palette (the app's `--color-*` tokens are theme-swapped, so paper needs its own light values — never `var(--color-*)` for paper) |
| `ONE HEADER` | one header: name+`(ID)` / cohort code / `code — name`; two-part week line; printed once, `thead` repeats | `.page-header` (title + chip + guide icon) | new print-only header; the screen header is hidden |
| `PRINT READS 12-HOUR TIME` | `.hour-24` on screen, `.hour-12` in print; **start over end** in `hour-top`/`hour-bottom`; day stack centred; empty Sunday row dropped | `hour-top`/`hour-bottom` are 24-hour; block times already 12-hour; Sunday row always built | new `.hour-12` spans + print swap; a Sunday-row rule |
| `BLOCK LAYOUT` | corners (venue · surname) + bold measured centre + time at foot + note bottom-centre; per-page centre (course vs cohorts); centre fits itself (≥7px); measured `.ev-band` | `.ev-code` / `.ev-venue` / `.ev-time` / `.ev-note`, stacked, no centre, no surname node | the biggest delta — see Q1 |
| `NO INVENTED STATUS WORD` | no status word on paper; a PH row paints every block conflict-red | status word `cfg.responsive`-only + ≥1280 CSS (never matches paper); PH red is per-block via `event-conflict` / `event-public-holiday` | PH **row** paint is new (per-block ownership red exists) |
| `VENUE ON PAPER` | only class blocks marked; every non-class cell bare; 3-chip key stacked over the ownership hint | venue paints `.cell-available` green, `.cell-ph`, `.cell-too-soon`, `.cell-held`, lane spacers | new print arm for the venue cell families |
| `PAPER SHOWS WHAT RUNS` | pending **and** cancelled do not print; filter the DATA before the grid is built | both print (and must keep printing on screen) | the central data change |
| `DENSE MODE` | `printedBlocks > 32` **or** `replacements > 6`, after the filter; toggles `.sheet`; tightens padding/gap/line-height; never a font size; never flattens the ownership weights | nothing | new |
| `PAGE BREAKS` | `thead` repeats, rows avoid breaking, no summary strip, legend + foot `break-inside: avoid` | `.ui-footer` hidden only | new |
| `NORMAL CLASS VIEW` | filter to `normal`; every PH trace gone; week line `Week 1 ~ 14 (Whole Semester)`; whole legend hidden; state class `normal-view`, never `normal-only` | nothing | new toggle + filter (board-wide decision, see Q2) |
| `PREVIEW-ONLY` | do **not** port `.print-notes`, `.paper-foot`, `.page-chip` picker, `#rolePreviewLine`, `Busy week sample`, `?page=` | — | explicitly out |
| `CHROME IS THE LIVE PRINT BAR` | top bar, header row and picker describe the **prototype's own** chrome | live pages keep their real nav/top bar, which print hides | live equivalent = hide the app shell; nothing is ported |

### 2.1 The five stale lines in `design.md` — verified, not quoted

| `design.md` | Says today | Measured truth |
|---|---|---|
| `:428` (dense trigger) | `printed.length > 32 \|\| printed.filter(e => e.status === 'pending' \|\| e.status === 'replacement').length > 6` | pending never prints (contract `PAPER SHOWS WHAT RUNS`), so the term counts blocks that cannot exist → must be **replacements > 6** only |
| `:36` + `:217` (key) | "the printed key simply reuses the screen `ui-legend-bar`"; legend gets `legend-available` too | the screen key is **4** chips by default and **5** on Venue (`Available`); paper carries **3** (Normal · Replacement · Conflict / Public Holiday) stacked above the ownership hint. `Available` is screen-only |
| `:405` | "`.cell-no-fit` stays red" | that arm was retired 2026-11-14 (`venue-evening-tail`); on paper every non-class cell is bare |
| (pending/cancelled described as printing) | both print | **neither prints**; the screen keeps both |
| `:115` + `:160` | a `print-filter-note` reading `Normal Class only` | the prototype has no such note; the filter shows itself by the week line `Week 1 ~ 14 (Whole Semester)` and by dropping the legend |

---

## 3. The five-caller risk

`buildTimetableGrid()` has five live callers:

| Caller | Path | Passes `cellRender`? | In print scope? |
|---|---|---|---|
| `my-timetable` | default | no (flag `responsive: true`) | **yes** |
| `student-my-timetable` | default | no (flag `responsive: true`) | **yes** |
| `cohort-timetable` | default | no | **yes** |
| `venue-timetable` | `cellRender` + `laneLayout` + `baseRowDays` | yes | **yes** |
| `replacement-arrangement` | `cellRender` (legacy path, no `laneLayout`) | yes | no (`showPrint => false`) |

**The pin.** `tests/timetable-callers-pin.spec.ts` freezes
`tests/fixtures/t0a-baseline.json`, captured 2026-11-15 *before* task 8's first
edit. It asserts three things byte-for-byte:

1. `#tableHead.innerHTML`, `#tableBody.innerHTML` and `#timetable`'s inline
   `display` for a synthetic call **without** the `responsive` flag;
2. live `#timetable.outerHTML` on **Cohort** (`?week=9`) and **Venue**
   (`B005&week=9`);
3. on those live pages, that blocks carry the `data-tip2` **pair** and zero
   `data-tip` (the flag-absent proof).

**Consequence for this lane:** three of the four print targets — Cohort, Venue,
and (already flagged) nothing else — are inside that pin. Any node this lane adds
to the builder's output for Cohort/Venue (a surname span, an `.hour-12` span, a
centre) **moves a pinned byte string**. Options are in Q1; whichever is chosen,
re-baselining the two live captures must be an explicit, logged step (the
fixture's own header says a capture only means something taken "before the lane's
first edit"), and the synthetic capture must keep proving the un-flagged shape.

Related machinery that constrains the same files: `makeEventBlock()`'s
`cfg.responsive` flag exists precisely so the other four callers stay identical
(`ui-common.js:1130-1138`, `:1195-1210`), and `tests/timetable-responsive.spec.ts`
(33 cases) drives the two flagged pages.

---

## 4. The three questions the prompt left open — answered with evidence

### Q1. Print markup vs screen markup: how does a live page print the corners/centre block?

**Measured.** The prototype's block is `corners + .ev-band + .ev-main + .ev-time +
.ev-note`, built by `buildBody()` (`ui-prototype/timetable-print-preview.html:1979-2045`).
The live block is `.ev-code / .ev-venue / .ev-time / .ev-note` and nothing else.
Mapping the contract's four page shapes onto those existing nodes:

| Page | corner-left | corner-right | centre |
|---|---|---|---|
| Student | `.ev-venue` (exists) | surname (**new**) | `.ev-code` (exists) |
| Cohort | `.ev-venue` (exists) | surname (**new**) | `.ev-code` (exists) |
| Venue | `.ev-code` (exists) | surname (**new**) | cohorts (**new** — the payload has them, the block does not) |
| My | `.ev-venue` (exists) | `.ev-code` (exists, moves) | cohorts (**new**) |

So **three of the four page shapes are a print-time CSS reposition of nodes that
already ship**, plus two genuinely new nodes: `.ev-lecturer` (the surname — and
the contract names exactly this node) and a cohorts centre for My/Venue, plus the
`.hour-12` hour spans. This is not a coincidence: the contract's own `SCOPE` says
the print-only nodes are `.ui-print-header`, `.ev-lecturer`, `.hour-12` and that
they are "the ONLY things print reveals".

**Options**

| | Approach | Cost | Risk |
|---|---|---|---|
| A | **Print-only spans hidden on screen** (`display:none`), added by the shared builder behind a new opt-in flag; the corners/centre are a `@media print` flex-order change; `.ev-main` measures itself | one builder edit + one print CSS block; the pin's two live captures need a logged re-baseline | the re-baseline weakens a frozen receipt unless it is done honestly (see above) |
| B | **Build a separate sheet at print time** (`beforeprint` rebuilds a second table) | a second render path in JS | duplicates the layout logic (breaks promote-on-3rd/DRY), and `beforeprint` fires *before* the print layout exists, so the build cannot be measured |
| C | **Server-rendered print route** | new route + view | forbidden by the contract (`SCOPE`: "no in-app print screen, no server render") |

**Recommendation: A.** It matches the contract's own node list, keeps one builder
and one layout language, and keeps the screen byte-identical (every new node is
`display:none` outside `@media print`). The pin is then re-baselined once, with
the reason recorded, and its synthetic half still proves the un-flagged shape.

### Q2. What does "whole semester" mean on a live page?

**Measured.** The page holds **one week** (`eventsData[currentWeek]`) but the
payload it received is **all fourteen** (`eventsData = @json($eventsByWeek)`,
`my-timetable.blade.php:222`). The prototype's normal view prints one week's
events filtered to `normal` and relabels the week line
(`ui-prototype/timetable-print-preview.html:2075-2088`), because in its sample
data a normal class is the same every week.

**Options**

| | Meaning | Risk |
|---|---|---|
| A | Filter the **current week** to `normal`, print it, label `Week 1 ~ 14 (Whole Semester)` | the label can lie: a class that is cancelled/conflicted only in *this* week would be printed as the semester's pattern, and a class that is normal in other weeks but conflicted here would be missing |
| B | Build the semester view from the **union of all fourteen weeks' normal events** (available client-side; no server change) | more JS; a class whose weeks differ can appear once where it truly recurs — but it can never hide a class |
| C | Keep one week on screen and **only relabel** the paper | simplest, but prints a lie for any week that is not representative |

**Recommendation: B**, with the week line reading exactly
`Week 1 ~ 14 (Whole Semester)` and the screen grid untouched by the toggle (the
contract puts the toggle's effect on the **data** and the **sheet**, and keeps
`normal-only` off the sheet root because that class is the toggle's own). The
semester union is honest for every week, and it is cheap because the whole
semester is already in the page.

### Q3. Where does the measured fit happen?

**Measured.** `beforeprint`/`afterprint` fire *around* the print render, not
inside a laid-out print document — page JS cannot read print metrics, and the
task-8 module already relies on that boundary (`_sheetBeforePrint()` only
un-relocates nodes, it measures nothing). The live sheet at 1440px is **wider**
than the paper (A4 landscape − 16 mm ≈ 1047 CSS px), and at <1280 the table is
`display:none`, so "measure the block on screen" is measuring the wrong geometry
in two of the three bands. The prototype sidesteps this because its screen **is**
the paper: `fitBlockCentres()` (`:1835-1890`) measures the real sheet and derives
`--ev-main-size-print` by a fixed ratio.

**Options**

| | Approach | Risk |
|---|---|---|
| A | Measure the screen block, apply a fixed paper ratio (prototype's own method) | right only when the screen geometry resembles the paper — wrong at 1440 and unmeasurable below 1280 |
| B | Measure inside a **hidden off-screen measuring sheet** built at the paper's exact content width (1047px), then write `--ev-main-size-print` | one extra hidden DOM node, rebuilt per week; deterministic, band-independent, and it is a real measurement |
| C | No JS fit; set a static size per line count (1 / 2 / 3+) | smallest, but cannot catch a long code wrapping or a seven-cohort list, which is exactly what the contract says no line count can |

**Recommendation: B**, with C as the documented static fallback when the
measuring pass cannot run (no layout yet, print invoked before the week is
built): the printed size is then the static size for that line count, never a
mid-pass size. The pass runs on every rebuild and is debounced on resize, exactly
as the prototype does — it just measures a stand-in with the paper's width.

---

## 5. Open questions for the *design* stage (not for the user yet)

1. **Where the Normal Class toggle lives on a live page.** The prototype's
   toggle sits on its own preview bar, which is preview-only. The live page
   header is `ui-page-header` (title + chip left, guide + summary right) and has
   no centre slot; the week row is the natural neighbour of the print button.
   Design must pin one selector and one place.
2. **How paper gets a light palette without breaking UI rule 1** (tokens only).
   The app's tokens swap with the theme, so paper needs a fixed light set. The
   prototype solves it with `--p-*` variables declared once. Design must decide
   whether those become `theme.css` print-scoped tokens (a new token block) or
   `@media print` overrides of the existing tokens — the rule allows adding a
   token to `theme.css` once, so either is legal; the second is smaller.
3. **The legend-item print hook.** The 3-chip paper key needs to drop Pending (and
   Available on Venue) from a shared partial that currently carries no per-status
   class on `.legend-item`. Design must choose an additive marker (a modifier
   class or a `data-` attribute) and confirm no screen pixel moves.
4. **Task 8's print block re-shows `#summarySection`** (`theme.css:6801-6806`)
   on purpose, so a narrow-window printout keeps the cards. The contract says
   "no printed summary strip". Design must overrule that rule *for print only*
   and say so in the lane papers, or the two lanes will fight on every rebuild.
5. **Empty-week and Sunday rules interact** (`data-tt-empty-week`,
   `tt-responsive` print rules) — a week that is empty only because pending /
   cancelled were filtered must print the "no classes" panel, not a blank table.
6. **The `' - '` → `' ~ '` venue change is a screen change.** It is the contract's
   own list, so it ships, but it must be called out in the venue changelog and in
   any spec that reads the venue block's time text.
7. **Student ID for the printed header** needs one additive view variable (no
   schema change) — confirm the exact label (`student_id` vs the login handle).

## 6. What this lane will need to touch (first estimate, for the proposal)

| Surface | Why |
|---|---|
| `partials/ui-week-nav.blade.php` | the real button (`window.print()`), and the toggle's home if design puts it here |
| `public/css/theme.css` | one print block: `@page`, paper palette, chrome hiding, header, key, dense, page breaks |
| a new `partials/ui-print-header.blade.php` | the print-only header, one include per page with that page's own values |
| `public/js/ui-common.js` | opt-in print nodes (`.hour-12`, `.ev-lecturer`, centre), the print data filter, the semester union, the measuring pass |
| the four blades | include the header, pass the context, add the toggle; venue's `' ~ '` |
| `app/Livewire/StudentMyTimetable.php` | expose the student ID (view data only) |
| `tests/venue-timetable.spec.ts` | TC05 pins the stub and must become a real-print receipt |
| `tests/fixtures/t0a-baseline.json` + one logged re-baseline | Q1's consequence |
| `page-changelogs/*` | four pages change |

No migration, no schema change, no new dependency, no demo-DB write.

---

## 4a. Post-explore correction (2026-11-16) — two answers above were overtaken

Written while drafting `design.md`, after this brief, and recorded here rather than
silently rewritten so the reasoning trail stays honest.

- **Q1's recommendation (option A) was overtaken — the answer became "a second
  table, built by the same builder".** While designing the normal view it became
  clear that a print-CSS restyle can only **subtract**: the screen shows one week,
  and the semester's normal timetable is not on it, so AC12 was unimplementable that
  way. `buildTimetableGrid()` already accepts `tableId`/`headId`/`bodyId`/`emptyId`
  (`ui-common.js:1034-1037`), so the paper is a **separate hidden table** painted by
  the same function. Consequences: no re-baseline of the pin (only the venue
  separator moves), no print-only nodes on the live grid, and the fit is measured on
  the print sheet itself instead of on a stand-in. See `proposal.md` D1/D2/D4.
- **Q3's `1047px` was wrong arithmetic.** A4 landscape is 1123 CSS px and 8 mm is
  30.24 px at 96 dpi, so the paper's content width is **1062 px**, not 1047. The
  current figure lives in `design.md` §5.1.

The rest of this brief stands and is still the baseline the reviewer checks against.
