# Timetable Print — capability specification (v2, 2026-11-16)

**Normative sources:** `proposal.md` (frozen 2026-11-16) and `design.md` (rewritten
2026-11-16) — plus the `DESIGN CONTRACT` block in
`ui-prototype/timetable-print-preview.html:3-205`, which carries the intent and the
numbers. This file states **what must be true**; it does not re-state the
contract's measurements.

**Pages in scope:** `/my-timetable-ui` (lecturer/PL) · `/student-my-timetable-ui`
(student) · `/cohort-timetable` (lecturer/PL) · `/venue-timetable-ui`
(lecturer/PL). **Out:** `/replacement-arrangement` (`showPrint => false`), the
frozen `ui-design-templates/*` copies (not edited), `ui-prototype/*` (reference
only).

## Terms

| Term | Meaning |
|---|---|
| **print set** | The events the paper may show: this week's events minus `pending` and minus `cancelled`; in the normal view, the deduplicated semester union of `normal` events |
| **print sheet** | The hidden `.ui-print-sheet` table the paper is printed from (`#printTimetable`) |
| **print header** | The hidden `.ui-print-header` — title, subtitle, context, semester chip, two-part week line, three-chip key |
| **screen grid** | The existing `#timetable`, which is **not** changed by this lane beyond PR-01, PR-02 and PR-03 |
| **normal view** | The state of the Normal Class toggle: the semester's normal timetable |
| **ownership gate** | "This viewer may see this class's request facts" — ungated on My/Student, gated on Cohort/Venue, exactly as each screen build already does it |
| **paper** | The print media surface, whether printed or saved as PDF |
| **print-surface vocabulary** | `.print-table`, `.print-block`, `.print-corner`, `.print-main`, `.print-time`, `.print-note` — deliberately distinct from `.event-block` / `.timetable` |

---

## PR-01 — The printer button prints

The shared `.print-btn` rendered by `partials/ui-week-nav.blade.php` when
`$showPrint` is true calls `window.print()`. It has a real accessible name and a
hover tip on the shared `data-tip` pill. The "Printing is coming soon" toast, its
tip and its comment are gone. Icon, class and geometry are unchanged.

**Scenarios**

1. **Given** any of the four pages, **when** the print button is clicked, **then**
   `window.print` is called exactly once and no toast appears.
2. **Given** the arrangement page, **when** it renders, **then** it shows no print
   button (it passes `showPrint => false`).
3. **Given** a keyboard user on any of the four pages, **when** they tab to the
   print button, **then** it has a non-empty accessible name.

## PR-02 — The Normal Class toggle exists on the four pages

The shared week-nav partial renders a `Normal Class` toggle behind its own opt-in
flag (`$showNormalToggle`, default `false`), so the arrangement page and the frozen
templates render byte-identically. The toggle has no inline `onclick`; it is bound
by the shared module. Its state is page-local, survives a week change, and resets
on reload.

**Scenarios**

1. **Given** any of the four pages, **when** it renders, **then** one toggle
   labelled `Normal Class` is present beside the print button.
2. **Given** the toggle is on, **when** the reader changes week, **then** it stays
   on; **when** the page is reloaded, **then** it is off.
3. **Given** the arrangement page, **when** it renders, **then** no toggle is
   present.

## PR-03 — The venue block speaks the house time joiner

The venue grid's block time reads `2:00 PM ~ 4:00 PM`, not `2:00 PM - 4:00 PM`
(`venue-timetable.blade.php:1715`). This is the only screen change to any block's
content in this lane.

## PR-04 — The screen is otherwise untouched

Apart from PR-01, PR-02, PR-03 and the two hidden print surfaces, the screen is
unchanged: every block the page renders today still renders, pending and cancelled
included, and `#timetable`'s markup is byte-identical apart from PR-03.

**Scenarios**

1. **Given** a week containing a pending block and a cancelled block, **when** any
   of the four pages renders, **then** both blocks are visible on screen.
2. **Given** the five-caller pin, **when** it runs, **then** the synthetic and
   Cohort receipts are byte-identical and the venue receipt differs only by PR-03.

## PR-05 — One A4 landscape sheet

The stylesheet requests A4 landscape with 8 mm margins, and the sheet keeps its own
paper width so an engine that ignores `@page` still produces a readable table.
Paper is light in **both** screen themes.

**Scenarios**

1. **Given** any of the four pages, **when** it is exported as a PDF with the CSS
   page size preferred, **then** the page is A4 landscape with 8 mm margins.
2. **Given** the app in dark mode, **when** a page is printed, **then** no element
   on paper uses a dark theme fill.

## PR-06 — One header, printed once

The print header prints once, above the table. The table's `thead` repeats on any
later page. The header carries: title, subtitle, context, semester chip, and a
**two-part** week line (bold count, quiet dates).

**Scenarios**

1. **Given** a week that spans two printed pages, **when** both pages are
   inspected, **then** the header appears once and the hour row appears twice.
2. **Given** any page, **when** the header is read, **then** the week line has a
   heavier count part and a quieter date part, never one run-on string.

## PR-07 — The header's context is per page, and correct when empty

| Page | Context |
|---|---|
| My | staff name + `(staff ID)` |
| Student | student name + `(student ID)`, subtitle `Cohort: <code>` (`Cohort: —` when the student has none) |
| Cohort | selected cohort code, subtitle `Faculty: <code>` — kept in step by page JS, because the selects change without navigation |
| Venue | `<venue code> — <venue name>`, or `Select a venue` when both are empty |

The ID prints **only** where the context is a person. One em dash, spaces either
side — never `--`.

**Scenarios**

1. **Given** the venue page with a venue whose name is empty, **when** the header
   prints, **then** it reads `<code> — <code>`, not a bare code and not `—`.
2. **Given** the venue page with nothing selected, **then** it reads
   `Select a venue`.
3. **Given** the cohort page, **when** the reader changes cohort without leaving
   the page, **then** the printed context names the new cohort.
4. **Given** the cohort page with no cohort selected, **then** the paper shows the
   page's own empty wording and no stale grid.

## PR-08 — The hour axis prints 12-hour, start over end

Each printed hour column shows its start above its end in 12-hour form, and the
final column still names where it stops. The screen's 24-hour hour row is
unchanged.

**Scenarios**

1. **Given** any printed page, **when** the hour row is read, **then** every
   column reads e.g. `8:00 AM` above `9:00 AM`, and the last column names its end.

## PR-09 — Block layout is two corners, one bold centre, time, note

On paper each block carries: venue and lecturer-surname corners (or the page's own
arrangement), a bold centre, the time at the foot and the note bottom-centre.

| Page | corner-left | corner-right | centre |
|---|---|---|---|
| Student | venue | surname | course |
| Cohort | venue | surname | course |
| Venue | course | surname | cohorts, one per line |
| My | venue | course | cohorts, one per line |

The lecturer prints as **one surname, no honorific**; the honorific set is
`En. · Pn. · Puan · Cik · Dr · Dr. · Mr · Mr. · Mrs · Mrs. · Ms · Ms. · Prof ·
Prof.` plus a bare `Hj`/`Hjh`. A missing or `—` lecturer prints an **empty corner**.
Cohorts lead one per line, never joined with ` + `. When a cohorts-leading page has
no cohort data, the centre falls back to the course rather than printing empty.

**Scenarios**

1. **Given** a class taught by `En. Lim Jia Zheng`, **when** it prints on Student or
   Cohort, **then** the corner reads `Lim`.
2. **Given** an event with no lecturer, **when** it prints, **then** the corner is
   empty — never `—`.
3. **Given** a Venue block whose class has three cohorts, **then** the centre lists
   three lines and no ` + `.
4. **Given** a My-Timetable block with no cohort data, **then** the centre shows the
   course.

## PR-10 — The centre fits itself

A centre of two or more lines is pushed clear of the corners by a **measured** band.
A centre of three or more lines shrinks until it fits, never below 7px. The measure
happens on the print sheet laid out at the paper's content width; if it cannot
happen, a static per-line-count size is used, never a half-measured one.

**Scenarios**

1. **Given** a block whose centre wraps to three lines, **when** the sheet is
   measured, **then** the centre's computed size is smaller than the base size and
   is `>= 7px`.
2. **Given** a block with a two-line centre, **then** a band of at least the tallest
   corner's height is present and the centre starts below it.

## PR-11 — The paper shows only what runs

No `pending` block and no `cancelled` block appears on paper, on any of the four
pages. Both still render on screen (PR-04). A week left with nothing after the
filter prints the "no classes" panel, never a blank table, and the panel's wording
follows the normal view (`No normal classes.`) or not (`No classes this week.`).

**Scenarios**

1. **Given** a week whose only blocks are pending, **when** it prints, **then** the
   "no classes" panel prints and no block does.
2. **Given** the same week on screen, **then** the pending blocks are still visible.
3. **Given** the venue page with such a week, **then** the printed panel appears
   (not a blank table).

## PR-12 — No invented status word

A printed block carries no `✕ Cancelled` / `⚠ Conflict` word. The block's colour and
the key carry the status.

**Scenarios**

1. **Given** any printed page, **when** the blocks are read, **then** none contains
   a status word span.

## PR-13 — A public-holiday row paints red; an empty Sunday row is not printed

Every block on a public-holiday row carries the conflict skin and keeps its
content. Ownership survives only in the border weight. A Sunday row is printed when
it has something real to say — a block in the print set, **or a holiday** (which
carries its own mark and note) — and never merely because it is Sunday.

**Scenarios**

1. **Given** a holiday row, **when** it prints, **then** every block on it has the
   conflict skin, including another lecturer's.
2. **Given** a Sunday whose only block is pending, **when** the week prints,
   **then** no Sunday row is emitted (six rows, not seven).
3. **Given** a Sunday that is a public holiday but carries no class, **when** the
   week prints, **then** its row prints with the holiday mark — a holiday is not an
   empty strip.

## PR-14 — The printed key is three chips, stacked over the ownership hint

Paper carries exactly **Normal · Replacement · Conflict / Public Holiday**, on one
line, with the ownership hint (`thick border = your classes · thin border =
others'`) hard left on the next line — on Cohort and Venue only. `Available` and
`Pending` never appear. The screen keeps every chip it has today, five on Venue
included.

**Scenarios**

1. **Given** the venue page, **when** the screen is read, **then** its legend shows
   five chips; **when** the paper is read, **then** the key shows three.
2. **Given** the My Timetable page, **then** the paper key has no ownership line.

## PR-15 — Every non-class cell is bare paper

On the venue sheet only class blocks are marked; free, no-room, too-soon, held,
holiday and lane-spacer cells do not exist on paper at all.

## PR-16 — The normal view is the semester's normal timetable

With the toggle on, the sheet shows only `normal` classes, built from a
**deduplicated** union of all weeks (identity = session + slot; first occurrence
wins) so no class prints more than once; two different classes sharing a slot both
print, stacked. Every public-holiday trace is gone (no `[Hn]` mark, no note, no
off-shading; the composite days carry no holiday flag). The date is dropped and the
day prints alone, upper-cased. The whole key is hidden. The week line reads
`Week 1 ~ 14 (Whole Semester)`. The **screen grid keeps its blocks**.

**Scenarios**

1. **Given** a class that is `normal` in all fourteen weeks, **when** the normal
   view prints, **then** it appears exactly once.
2. **Given** the toggle on, **when** the sheet's day cells are read, **then** no
   date is printed and no `[Hn]` mark exists.
3. **Given** the toggle on, **when** the screen grid is inspected, **then** it still
   holds every block it held with the toggle off.
4. **Given** the toggle on and a semester with no normal class, **then** the panel
   reads `No normal classes.`

## PR-17 — Dense mode keeps a very full week on one page

Dense is computed from the **print set**: more than 32 printed blocks, or more than
6 replacements. It tightens padding, gap and child line-heights only — never a font
size — and keeps the two ownership border weights unequal. Its styling applies to
the sheet only, so no screen rule moves.

**Scenarios**

1. **Given** a print set of 33 blocks, **when** the sheet is built, **then** the
   dense flag is set and the measured padding is smaller than a normal week's.
2. **Given** the same week on screen, **then** nothing on the screen changed.
3. **Given** dense mode, **then** the two border weights still differ.

## PR-18 — Page breaks

`thead` repeats, day rows do not split, the key does not split, and no summary strip
prints.

**Scenarios**

1. **Given** a two-page export, **then** no day row is split across pages and the
   key is wholly on one page.
2. **Given** any print, **then** no summary card is on paper.

## PR-19 — One builder, and the pin keeps its meaning

The print sheet is built by the existing `buildTimetableGrid()` through an opt-in
flag; with the flag absent the builder's output is byte-identical to today. The print
surface uses its own class vocabulary and no `tabindex`, so no screen suite and no
keyboard user meets it. The sheet is laid out off-viewport, measurable and
invisible.

**Scenarios**

1. **Given** the synthetic pin receipt, **then** head, body and table display are
   byte-identical to the 2026-11-15 capture.
2. **Given** any of the four pages, **when** the reader tabs through the page,
   **then** no focus stop lands inside the print sheet.
3. **Given** the venue page, **when** a suite scans `.event-block`, **then** it sees
   only screen blocks.

## PR-20 — Nothing outside the lane's promise moves

No new dependency. No colour outside `theme.css`. No demo-DB write, no schema
change, no migration. No edit to `ui-prototype/*` or
`ui-design-templates/*`. The four page changelogs gain a dated entry.

**Scenarios**

1. **Given** the lane's commits, **then** `composer.json`/`package.json` are
   untouched and no migration file exists.
2. **Given** the four pages, **then** each has a changelog entry naming the print
   surface and PR-03.

---

## Scenario → requirement map (for `verify`)

| Scenario family | Requirements |
|---|---|
| Button + toggle | PR-01, PR-02 |
| Screen integrity | PR-03, PR-04, PR-19 |
| Paper geometry | PR-05, PR-06, PR-18 |
| Identity on paper | PR-07, PR-14 |
| Time + layout | PR-08, PR-09, PR-10, PR-12 |
| Content of the sheet | PR-11, PR-13, PR-15, PR-16 |
| Density | PR-17 |
| Housekeeping | PR-20 |
