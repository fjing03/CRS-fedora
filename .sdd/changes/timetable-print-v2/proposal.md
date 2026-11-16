# Proposal: Timetable Print v2

**Date:** 2026-11-16 (rewritten from the prototype's `DESIGN CONTRACT`; supersedes the 13 Nov draft)
**Lane:** `timetable-print-v2` · **Task 9** of the user's list · **Depends on:** task 8 (`my-timetable-responsive-prompt`, archived 2026-11-16)

---

## Why

Four live timetable pages show a printer button, and clicking it says
**"Printing is coming soon."** It has said that since the mock era; one test even
pins the lie (`tests/venue-timetable.spec.ts` TC05). A user who wants a week on
paper — for a meeting, for a viva, for the wall — has nothing.

The visual design for the paper was settled on
`ui-prototype/timetable-print-preview.html` across 2026-11-15/16. That file's
top comment block (`DESIGN CONTRACT`, lines 3–205) is organised by subject and is
**the requirement set**; the comments beside its CSS explain individual numbers.
This proposal does not restate those numbers — it points at them, per the repo
convention that the prototype carries the intent in code comments.

The lane's earlier papers were written on 12–13 Nov, before those rulings. Five
of their statements are now false (listed in §6). They are corrected here rather
than silently applied.

---

## What

Give the four timetable pages a **real** print action — `window.print()`, the
browser's own dialog, no new dependency, no server render, no in-app print
screen — and a print stylesheet that turns the live week into one A4 landscape
sheet a person can pin to a wall.

The four pages: **My Timetable** · **Student My Timetable** · **Cohort
Timetable** · **Venue Timetable**. Replacement Arrangement stays out
(`showPrint => false`).

Everything below is taken from the contract's subjects. Read them there; this is
the checklist, not the specification.

| Contract subject | What this lane delivers |
|---|---|
| `SCOPE` | `window.print()`; screen unchanged apart from four named things (below) |
| `ONE SHEET` | `@page` A4 landscape + 8 mm; a fixed paper palette so paper is light in either screen theme; one page for a normal week |
| `ONE HEADER` | one print-only header: title + subtitle, centred context per page, semester chip + two-part week line, one rule; printed once, `thead` repeats |
| `PRINT READS 12-HOUR TIME` | the hour axis prints start-over-end in 12-hour form; day stack centred; a Sunday row with nothing on it is not printed |
| `BLOCK LAYOUT` | two quiet corners (venue · lecturer surname), one bold centre (course or cohorts, per page), time at the foot, note bottom-centre; the centre measures and fits itself; a measured band clears the corners — all built into the **print sheet** (§What, D1) |
| `NO INVENTED STATUS WORD` | no status word on paper; a public-holiday row paints its blocks conflict-red |
| `VENUE ON PAPER` | only class blocks are marked; every other cell prints bare; the key is three chips stacked above the ownership hint |
| `PAPER SHOWS WHAT RUNS` | pending and cancelled never reach paper: the print sheet is built from the **print set** (each week's events minus pending and minus cancelled), and every paper-state decision reads that set |
| `NORMAL CLASS VIEW` | a real page toggle: **on the sheet**, the semester's normal timetable only, every public-holiday trace gone, week line `Week 1 ~ 14 (Whole Semester)`, whole legend hidden. The contract says the filter "drops every other status before the grid is built" — true in the prototype, where the sheet is the screen; on a live page the one grid serves both media, so the filter binds the **sheet** and the screen keeps its blocks (see D3, AC12) |
| `DENSE MODE` | computed from the blocks that will actually print; the styling lives **inside the print block only**, so a busy week changes nothing on screen; never touches a font size, never flattens the ownership weights. The contract's "applies on SCREEN as well as on paper, because the preview must not lie about the paper" is preview parity — the prototype *has* an on-screen sheet; a live page has none, so the clause inverts to "print only" here (and this leak was fixed once already, *Prototype delta 2*, 2026-11-13) |
| `PAGE BREAKS` | `thead` repeats, day rows avoid breaking, no summary strip, and the legend never splits across pages |
| `TOOLTIPS` | anything this lane adds that has a hover tip rides the shared `data-tip` pill — never a native `title=` and never a second popover |
| `PREVIEW-ONLY` | **not** ported: `.print-notes`, `.paper-foot`, the `.page-chip` picker, `#rolePreviewLine`, the `Busy week sample` button, `?page=` |

### The four screen changes (and nothing else moves)

1. The print button becomes real (a control that works, not a toast).
2. The **Normal Class** toggle appears on the page.
3. The venue block's time separator becomes `' ~ '` (it is `' - '` today,
   `venue-timetable.blade.php:1715` — the one block on any page that does not
   already speak the house joiner; the default path has used `' ~ '` since it
   shipped).
4. Two print-only surfaces are added to the DOM: the print header
   (`.ui-print-header`) and the print sheet (`.ui-print-sheet`, D1). Both are
   **off the viewport with `visibility: hidden`**, never `display: none` — the
   sheet must keep a layout to be measured (D4), and `visibility` keeps its
   blocks out of the tab order, so a keyboard user never tabs through invisible
   print blocks.

Everything else — colours, spacing, the five cards, the legend's five chips on
screen, the phone agenda, the tablet window, the Week summary sheet, **and the
on-screen `#timetable`'s own markup** — is untouched. **This is a print lane, not
a redesign.**

Everything else — colours, spacing, the five cards, the legend's five chips on
screen, the phone agenda, the tablet window, the Week summary sheet — is
untouched. **This is a print lane, not a redesign.**

---

## Scope

### In scope

| Item | Detail |
|---|---|
| The shared button | `partials/ui-week-nav.blade.php`: the real action, its accessible name and its hover tip |
| The print block | one new print region in `public/css/theme.css`, split as D4 requires: the sheet's own structure scoped to the print surface (outside any media query), and inside `@media print` only `@page`, the chrome hiding, the palette hand-off, the breaks and the surface swap |
| The print header | a new `partials/ui-print-header.blade.php`, one include per page. Three pages fill it from the server or their own static payload; **Cohort's context is client-side** (`#facultySelect` / `#cohortSelect` change without navigation), so its header is filled and kept in step by page JS, and prints its `Select a cohort`-style empty state when nothing is selected |
| The builder's print path | `public/js/ui-common.js`: one opt-in flag that makes the **same** builder paint the print sheet (corners, centre, 12-hour hours, notes) instead of the screen block |
| The print sheet | one hidden table per page (`.ui-print-sheet`), built by the existing builder into its own ids, rebuilt whenever the week or the toggle changes |
| The print data filter | pending and cancelled removed before the grid is built, on all four pages' own build paths |
| The Normal Class view | the toggle, its data filter, its semester union, its week line, its legend rule |
| The measured fit | the fit pass and its static fallback |
| The venue tilde | `venue-timetable.blade.php`'s own `cellRender` |
| The student ID | additive view data in `app/Livewire/StudentMyTimetable.php` (no schema change) |
| Receipts | `tests/venue-timetable.spec.ts` TC05 becomes a real-print test; a new print receipt; the pin's one logged venue delta (D2) |
| Changelogs | all four pages |

### Out of scope

- Replacement Arrangement (`showPrint => false`, unchanged).
- **Editing** the frozen `ui-design-templates/*` copies (forbidden by standing
  rule). One inherited effect must be recorded rather than ignored: there are
  **four** of them and all four include the shared week-nav with
  `showPrint => true` (the arrangement template included — the old rounds' planned
  flip to `false` was never done). Because the button and the print block are
  shared, a page rendered through that D10 fallback would gain a working printer
  too. That inherited effect cannot be *prevented* while those frozen copies keep
  passing `showPrint => true` and stay unedited, so verify records it as a known
  effect instead of pretending it is not there.
- `ui-prototype/*` — it is the reference, not a target.
- Any PDF library, server-side render, print route or in-app print screen.
- The screen's own legend (five chips on Venue stays five on screen), the
  summary cards' content, the phone/tablet surfaces, or any colour on screen.
- Any schema, migration, seeder or demo-DB write. None is needed.

---

## Decisions — made, not open

These were settled at explore (§4 of `explore-brief.md`) and by the contract. The
design stage implements them; it does not re-open them.

### D1. One builder, two tables — the print sheet is built, not restyled

The screen grid stays exactly as it is. The paper is its own hidden table
(`.ui-print-sheet`), painted by the **same** `buildTimetableGrid()` into its own
`tableId`/`headId`/`bodyId`/`emptyId` (the builder has accepted those ids since it
shipped) under one new opt-in flag.

This is the decision that makes the rest of the contract possible at all, and it
was found while writing the design: a print pass that only restyles the screen
grid **cannot** print the semester's normal timetable, because those blocks are
not on the screen — the screen shows one week. Hiding is subtraction; the normal
view needs addition.

What it buys, all of it falling out of one decision:

- `PAPER SHOWS WHAT RUNS` — the sheet is built from the print set, so nothing has
  to be hidden.
- The **normal view** — the sheet is built from the semester union.
- The **Sunday row** rule and the printed empty panel — decided by the build, not
  by a CSS patch over a rendered grid.
- The **12-hour hour axis** and the block's corners/centre — rendered, not
  re-ordered by print CSS.
- The on-screen `#timetable` keeps its shape, so the pin's receipts move only where
  this lane deliberately touches the screen (D2).

**The 12-hour clause follows the same inversion.** The contract's porting note —
"`.hour-24` shows on screen and `.hour-12` in print, the swap lives in the print
block, not the builder" — describes a page where one header serves both media.
Under D1 it belongs to the **print sheet's own header**, which is rendered
12-hour (start over end) from the start. The live `#tableHead` is not touched, so
the pinned head keeps its 24-hour `.hour-top`/`.hour-bottom` pair exactly.

**The print surface is its own vocabulary.** The print build paints
`.print-block` / `.print-corner` / `.print-main` / `.print-time` / `.print-note`
in a `table.print-table` — **not** `.event-block` / `.timetable`. Three existing
suites scan `.event-block` and `.timetable` page-wide
(`tests/venue-status-parity.spec.ts:34`, `tests/venue-note-privacy.spec.ts:27`,
`tests/venue-db.spec.ts:32`); a print sheet that reused those class names would
join their results for reasons that have nothing to do with printing. The print
sheet is also placed **after** `#timetable` in DOM order, so `.first()` selectors
keep finding the screen grid.

**The venue print build calls the builder on the default path, without
`cellRender`.** That is what makes the empty print set reach a printed "no
classes" panel (the empty-week branch only runs when `cellRender` is absent,
`ui-common.js:1049-1056`), and ownership still paints correctly through
`statusClassFn` over the payload's own `e.mine`. The venue cell families that
`AC11` lists are therefore satisfied by construction: on paper there are no cells
to blank, only class blocks.

**Rejected:** restyling the screen grid in print (cannot add the semester's
blocks; and the print sheet's own geometry would have to be guessed from a screen
layout at a different width), and a server-rendered print route (the contract's
`SCOPE` forbids it). No layout logic is duplicated — it is one builder called
twice with different inputs, different ids and a different class vocabulary.

### D2. The pin moves in exactly one place, and that place is the venue separator

`tests/timetable-callers-pin.spec.ts` freezes three receipts: a synthetic call
with no flags, and live `#timetable.outerHTML` on **Cohort** and **Venue**.

Because the paper is a separate table with its own class vocabulary, the print
work moves **none** of them. The one movement comes from a screen change the
contract already orders: the venue block's time separator `' - '` → `' ~ '`
(`venue-timetable.blade.php:1715`) is painted **into `#timetable`**, so the venue
receipt changes by exactly that. Its normaliser (`withoutToday()`) canonicalises
today badges and the lead-time date, not separators, so it will read as a real
difference — correctly.

Therefore:

- **Synthetic and Cohort receipts: byte-identical. Any movement = a wrong
  implementation.**
- **Venue receipt: re-captured once,** with the single permitted delta recorded in
  the apply log as that separator, and nothing else allowed to differ in it. The
  re-captured file is reviewed against the old one, not just overwritten.

The pin is never loosened, skipped, softened or widened to a class count, and the
flag-absent proof (the `data-tip2` pair, zero `data-tip`) must survive.

### D3. "Whole semester" = one representative set per class, deduplicated by session and slot

The page already holds the whole semester (`eventsData` is every week). But the
payload is **not** a set of classes: the shared builder stamps **each week's copy**
with its own occurrence identity (`ResolvesTimetableTimeline::buildEventsByWeek()`
loops the weeks and keys every copy separately), so a naive union of the fourteen
weeks would print **fourteen copies of every normal class**, stacked into fourteen
lanes per slot.

So the normal view is built from a **deduplicated** union:

- **Identity = the class session plus its slot** (`sessionId`, `di`, `start`, `end`,
  plus `code` as the tie-breaker) — **never** `eventKey`, which differs per week by
  construction and would make the dedup silently do nothing.
- **First occurrence wins** (lowest week index), so the members are stable between
  rebuilds and the same week is always the one shown.
- **Collision policy: both print.** Two *different* normal classes that share a
  slot across different weeks both survive the filter — a real My-Timetable case —
  and the builder's own lane planner stacks them in the cell, which is the truth
  about that slot. Nothing is silently dropped to make the sheet tidier.
- The composite week's `days` input carries **no** holiday, today or Sunday flags,
  so "every public-holiday trace gone" is a property of the build input rather
  than a CSS patch.

The week line reads `Week 1 ~ 14 (Whole Semester)`. The screen grid is not
restructured by the toggle; the **sheet** is.

### D4. The fit is measured on the print sheet itself, laid out off-screen at the paper's width

The print sheet is a real table, so it can be measured directly — no stand-in and
no ratio. It is built inside a container that is laid out **off-screen** (moved
outside the viewport, never `display: none`, because a hidden box has no layout)
and pinned to the paper's content width, so the geometry measured **is** the
geometry printed. The fit pass shrinks a block's centre until its content stops
overflowing, never below 7px, and writes the result per block; the band's height
comes from the tallest corner that actually rendered.

The pass runs on every rebuild and on resize (debounced), and a **static
per-line-count size is the documented fallback** whenever a measurement cannot
happen — never a half-measured size.

**The sheet's structural rules must live where the measurement can see them.** If
the sheet's fonts, spacing and dense rules sat inside `@media print`, an off-screen
measurement in screen media would size against the wrong metrics and the static
fallback would hide why. So the split is:

- **Outside any media query, scoped to the print surface:** the sheet's own
  structure — block layout, corner / centre / band / time / note sizes, dense
  spacing, row heights, column widths. Safe unconditionally, because the surface is
  never visible on screen.
- **Inside `@media print`:** `@page`, the chrome hiding, the paper palette hand-off,
  the page-break rules and the "print the sheet, not the grid" swap.

**Rejected:** measuring the on-screen block and scaling by a ratio (the
prototype's own method — it is only right when screen and paper share a width,
which the live page never does; at 1440 the grid is wider than the paper, below
1280 the table is not laid out at all), and a static size as the primary path
(exactly what the contract says no line count can catch).

### D5. Paper shows what runs — one print set, one build

**The requirement.** Pending and cancelled blocks never reach paper, and the
screen keeps both exactly as today. Nothing in the contract's
`PAPER SHOWS WHAT RUNS` law may cost the screen a single block.

**The mechanism.** Each page derives a **print set** from the array it already
holds — this week's events minus pending and minus cancelled (or, in the normal
view, the semester union of `normal` events) — and that set is what the print
sheet is built from (D1). Every paper-state decision therefore reads the source of
truth directly: which blocks print, which day rows print (a Sunday holding only a
pending or cancelled block vanishes with its blocks instead of printing as an
empty strip), the paper key's chips, dense mode's count, and the printed empty
panel.

**Why the screen cannot be filtered instead.** Pending and cancelled are on
screen by law (`cancelled-blocks-stay-visible`, 2026-10-27, re-confirmed
2026-11-14), and the summary cards count pending on purpose. Filtering the array
the screen grid is built from would delete both — the exact regression the law
exists to prevent.

**Why not "hide the blocks with print CSS" instead.** Because hiding is
subtraction from a screen grid, and the paper is not always a subset of the
screen: the normal view prints a semester the screen never shows. It also forces
every paper-state decision to be derived twice — once from what is rendered and
once from what should be — which is how a sheet ends up claiming "no classes"
over a week that merely looks empty.

Design pins the exact flag name, the print-set derivation per page, and the
printed empty-panel wording; it does not re-open that the screen keeps both.

### D6. Paper gets its own light palette, declared once

The app's `--color-*` tokens swap with the theme, so paper cannot ride them. One
fixed paper palette in `theme.css`, referenced only from print rules. This is UI
rule 1's sanctioned shape (a token set added once), not a hardcoded colour in a
page.

### D7. The paper key is three chips; the screen key does not move

Paper: **Normal · Replacement · Conflict / Public Holiday**, stacked above the
ownership hint. `Available` is screen-only (blank paper also means held, too soon
and holiday, so an empty box no longer reads as "free"), and `Pending` never
reaches paper. The screen keeps every chip it has today, five on Venue included.

### D8. Print hides the summary strip — task 8's rule is overruled for print

Task 8 deliberately re-showed `#summarySection` in print so a narrow-window
printout kept the cards. The contract says a sheet carries no summary strip. The
print rule changes; nothing about task 8's screen behaviour does.

---

## Acceptance criteria

Each is checked in `verify`. AC1–AC13 are the contract's own; AC14–AC16 keep this
lane honest about the rules it must not break.

| # | Check |
|---|---|
| AC1 | Each of the four pages **requests** A4 landscape at 8 mm, and a normal week lands on **one page**. *Instrument boundary:* the stylesheet asks; Chromium honours it, other engines fall back to a readable portrait. Page count and `@page` size are **not** observable from `emulateMedia` — checked by exporting a PDF with `page.pdf({ preferCSSPageSize: true })` and by a manual print, never asserted from the DOM |
| AC2 | The header prints **once**; the table `thead` repeats on any later page (**same instrument boundary as AC1**: `display: table-header-group` is asserted, the repeat itself is seen in the exported PDF) |
| AC3 | The header carries, per page: staff name + `(ID)` · student name + `(ID)` with `Cohort: <code>` · cohort code with `Faculty: <code>` · `<venue code> — <venue name>` (`Select a venue` when empty). One em dash, spaces either side; an ID prints only where the context is a person |
| AC4 | The week line is two parts (bold count, quiet dates); in the normal view it reads `Week 1 ~ 14 (Whole Semester)` |
| AC5 | The hour axis prints **12-hour**, start over end, in the live `hour-top`/`hour-bottom` shape, and the last column names where it stops |
| AC6 | A block prints corners · bold centre · time · note in the contract's per-page arrangement (course in the centre on Student/Cohort, cohorts on My/Venue, the course in Venue's top-left corner) |
| AC7 | A multi-line centre clears the corners by a measured gap, and the centre shrinks to fit without ever going below 7px |
| AC8 | **No** pending and **no** cancelled block prints, on any of the four pages; both still render on screen. A week left with nothing after the filter prints the "no classes" panel, not a blank table |
| AC9 | A public-holiday row paints every block on it conflict-red, keeps the blocks' content, and a Sunday row with no class and no holiday is not printed |
| AC10 | The printed key is exactly three chips stacked above the ownership hint (none on My/Student, whose screen key has no hint either); `Available` and `Pending` are absent |
| AC11 | Every non-class venue cell prints as bare paper (free, no-room, too-soon, held, holiday, lane spacers) |
| AC12 | Normal Class, **on the sheet**: only `normal` survives; every public-holiday trace goes (the `[Hn]` mark, its note, the off-shading — the composite week's `days` input carries no holiday/today/Sunday flags at all); the date is dropped and the day prints alone, upper-cased; the whole legend is hidden; a week with nothing surviving prints `No normal classes.` **On the screen**: the toggle's own state is the only visible change — the screen grid keeps its blocks and the 1440 browser pass verifies the toggle's on/off state, not a filtered screen grid |
| AC13 | A very full week (>32 printed blocks or >6 replacements) still lands on one page; dense mode tightens spacing only — no font size moves, the ownership border weights stay unequal, and **no screen rule moves** |
| AC14 | Paper is light in **both** themes; and the screen is unchanged apart from the four allowed changes — the on-screen `#timetable` included, whose only permitted delta anywhere in this lane is the venue block's time separator. *Instrument:* the pin's three receipts plus the screen suites that already pass on these pages, named in `verify` |
| AC15 | No new dependency, no new colour outside `theme.css`, no database write, no `ui-prototype/*` or `ui-design-templates/*` edit |
| AC16 | The five-caller pin holds under D2: the **synthetic** and **Cohort** receipts byte-identical; the **Venue** receipt re-captured once with the time separator as its only logged difference; the flag-absent proof (the `data-tip2` pair, zero `data-tip`) alive on both live pages; Arrangement — which passes no print flag — untouched. The print surface uses its own class vocabulary, so no screen suite's `.event-block` / `.timetable` scan gains a block |

---

## Risks

| Risk | Mitigation |
|---|---|
| The pin's receipts hiding a real regression | two receipts must stay byte-identical; the venue's one permitted delta is diffed and reviewed, not overwritten blindly |
| A print-only table drifting from the screen grid (two renderers disagreeing) | it is the *same* builder, called with different inputs — not a second renderer; the print flag is the only difference in the code path |
| The print surface joining a screen suite's scan | its own class vocabulary (D1) and DOM placement after `#timetable` |
| A page rendered through the frozen-template fallback gains printing too | recorded as an inherited effect; those files are not edited |
| A measured fit that only works at one width | the measuring sheet uses the paper's width, not the window's (D4) + a static fallback |
| Two lanes fighting over the print block | task 8's print rules are edited in place with a comment naming which rule wins (D8) |
| Paper colours drifting from the tokens | one paper palette in `theme.css`, referenced by print rules only (D6) |
| The venue `' - '` → `' ~ '` breaking a receipt | named in the venue changelog and in the task that changes it |
| A "semester" sheet that lies | the union, not the current week (D3) |

---

## Traceability to what the user asked to see

The user's own verification list, mapped: A4 landscape / 8 mm / one page → AC1 ·
header once + `thead` repeated → AC2–AC4 · three-chip key stacked over the hint →
AC10 · no pending/cancelled block → AC8 · PH row red → AC9 · no empty Sunday row →
AC9 · corners/centre/note per page → AC6–AC7 · `Week 1 ~ 14 (Whole Semester)` →
AC4/AC12 · paper light in both themes → AC14 · a 1440 browser pass for the toggle
→ AC12 · a wide week on one page → AC13.

---

## §6. The five stale statements this proposal corrects

Recorded so the review log can show they were found, not quietly dropped. **The
citations below name where each false statement still lives in `design.md` — they
are not corrections inside it.** `design.md`, `specs/` and `tasks.md` are the
12–13 Nov papers and must be rewritten against this proposal and the contract
before `/sdd-apply`; the contract block itself points porters at `design.md`, so
a stale design would be read as authoritative.

1. The dense trigger counted `pending || replacement`; pending never prints →
   **replacements only** (`design.md:428`).2. The printed key was said to reuse the screen legend, five chips;
   `Available` is screen-only and `Pending` never prints → **three chips**
   (`design.md:217-218`; `:36` records only the swatch-class fact).
3. "`.cell-no-fit` stays red on paper" — that arm was retired on the live venue
   page on 2026-11-14 → **every non-class cell is bare** (`design.md:405`).
4. Pending and cancelled were described as printing → **neither prints**
   (`PAPER SHOWS WHAT RUNS`).
5. A `print-filter-note` reading `Normal Class only` → **no such note**; the
   filter shows itself in the week line and in the missing legend
   (`design.md:115`, `:160`).
