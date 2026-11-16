# Tasks — Timetable Print v2

**Against:** `proposal.md` (frozen 2026-11-16, round 4) · `design.md` (rewritten 2026-11-16) · `specs/timetable-print/spec.md` (PR-01…PR-20)
**Shape:** five batches, matching the plan the user approved. **Each batch is reported before the next one starts** — no batch runs into another.

**No code is written until the user says apply.** Every task is ≤ 2 hours.

**Two interim states are expected and must be reported, not discovered:** after
Batch A the button prints an empty sheet (the sheet is fed in T11), and after T15
the toggle is bound but changes nothing until T16. Both are honest stops at a batch
boundary; neither is allowed to reach a commit that the user is not told about.

**Standing constraints for every task:** no new dependency · colours from `theme.css` tokens (the paper palette is added there once) · never edit `ui-prototype/*` or `resources/views/ui-design-templates/*` · never `migrate:fresh`/`db:wipe`/reseed · never `--env=testing` · never push · commit only this lane's files (hunk-stage; another session's work stays unstaged).

---

## Batch 0 — Before touching anything (baseline + safety nets)

- [ ] **T01 — Capture the baseline, read-only** *(1h)*
  Run the existing receipts against `:8030` exactly as the repo's blessed form says, and save the output: `tests/timetable-callers-pin.spec.ts`, `tests/timetable-responsive.spec.ts`, `tests/venue-status-parity.spec.ts`, `tests/venue-note-privacy.spec.ts`, `tests/venue-db.spec.ts`, `tests/pages-parity.spec.ts`. Record which pass **at HEAD** in the apply log, so a later red can be attributed instead of debated.
  **Done when:** the apply log holds a table of spec → pass/fail at HEAD, with the exact command lines used.

- [ ] **T02 — Read the contract block end to end, and diff it against `design.md`** *(45m)*
  Read `ui-prototype/timetable-print-preview.html:3-205` in full (all fourteen subjects) and confirm each one is answered by a section of `design.md`. Note any subject with no answer in `design.md` §1–§14.
  **Done when:** the apply log names the section that answers each of the fourteen subjects, or a gap is recorded and raised with the user before T03.

---

## Batch A — The real button and the print surfaces exist

- [ ] **T03 — Make the printer button print** *(1h)* — PR-01
  `partials/ui-week-nav.blade.php:34-46`: replace the stub action with `window.print()`; drop the toast call, `data-tip="Coming soon"` and the now-false comment; give it a real `aria-label` and a hover tip on the shared `data-tip` pill. Icon, class, geometry and the `$showPrint` opt-in unchanged.
  **Done when:** all four pages print on click; the arrangement page still shows no button; `grep -rn "Printing is coming soon" resources/` returns nothing.
  **Interim state (report it):** until T11 feeds the sheet, printing from Batch A yields the finished chrome and an empty sheet. That is a real step backwards for the print action and must be said in the Batch A report, not discovered by the user.

- [ ] **T03b — The venue block's time joiner** *(30m)* — PR-03
  `venue-timetable.blade.php:1715`: `' - '` → `' ~ '` in the block's `.ev-time`. One line, plus a comment naming the contract's SCOPE clause. Nothing else in the venue cell renderer moves.
  **Done when:** every venue block reads the house joiner; the pin's venue receipt shows this as its **only** difference (verified in T20); the venue changelog entry (T21) has a matching line.

- [ ] **T04 — Add the print header partial** *(1.5h)* — PR-06, PR-07, PR-14
  New `partials/ui-print-header.blade.php` with the design §6.1 structure (left title+subtitle, centre context, right semester chip + two-part week line, then the three-chip key and the optional ownership line), and its per-page values passed by each caller.
  **Done when:** the partial renders on all four pages with the right static text, and nothing is visible on screen.

- [ ] **T05 — Add the print sheet container to the four blades** *(1h)* — PR-19
  One hidden `.ui-print-sheet` per page (design §2.1) with `#printTimetable` / `#printTableHead` / `#printTableBody` and `#printEmpty`, placed **after** the page's own `#timetable` wrapper, `aria-hidden="true"`.
  **Done when:** the four pages carry the container in the right DOM position and no screen suite's `.first()` selector changes its target.

- [ ] **T06 — The print surface's CSS foundation** *(2h)* — PR-05, PR-19
  In `theme.css`: the paper palette (§7.2, declared once), the `.ui-print-sheet` off-viewport box at the paper's content width with `visibility: hidden`, and the `@media print` block that sets `@page`, hides the chrome, swaps the surfaces and forces the light paper. Include the comment that the sheet's structural rules must stay **outside** media queries.
  **Done when:** `emulateMedia({ media: 'print' })` hides the app shell and `#timetable` and shows the (still empty) sheet and header; the screen is unchanged; `theme.css` gains no colour outside the palette block.

- [ ] **T07 — Withdraw task 8's print rules that now fight this lane** *(45m)* — PR-18
  `theme.css:6789-6820`: remove the `!important` re-show of `.legend-bar` and `#summarySection`, and the forced `.grid-wrapper`/`#timetable` display; keep the agenda/window/sheet/hint hiding and the `data-tt-empty-week` swap. Each removal carries a comment naming this lane and the contract subject.
  **Done when:** no printed page shows a summary strip or a screen legend; the phone/tablet print regressions task 8 fixed stay fixed.

---

## Batch B — The builder's print mode, the header data and the day rows

- [ ] **T08 — `cfg.print` in `buildTimetableGrid()`** *(2h)* — PR-08, PR-12, PR-19
  One opt-in flag that switches: the id set (caller's), the class vocabulary (`.print-*`), the 12-hour hour header (start over end, same fallback for the last column), no status word, no `tabindex`, and the corners/centre/time/note block shape driven by `cfg.printShape`. Absent flag = today's bytes.
  **Done when:** the pin's synthetic receipt is still byte-identical, and a synthetic print build produces the 12-hour header and print-vocabulary blocks.

- [ ] **T09 — The print-set derivations** *(1.5h)* — PR-11, PR-13, PR-16
  `printRunSet()`, `printSemesterSet()` (session+slot identity, first occurrence wins), `printDays()` (trailing Sunday only, and only when the print set has no block that day) and `printSurname()` with the full honorific set and the `—`-placeholder rule.
  **Done when:** a unit-style page evaluation proves the union deduplicates a class present in fourteen weeks, and that a Sunday whose only block is pending yields no day entry.

- [ ] **T10 — `PrintSheet` module: context, week line, key, dense flag** *(2h)* — PR-06, PR-07, PR-14, PR-17
  A `ui-common.js` object owning: the toggle binding, `setContext()`, the two-part week line (week view and normal view), the semester chip copied from `#semesterChip`, the ownership line shown on Cohort/Venue only, and the dense flag computed from the print set.
  **Done when:** each page prints its own context (including Venue's `Select a venue` empty case), the week line has two parts, and the key has exactly three chips.

- [ ] **T11 — Wire Batch B into the four pages** *(2h)* — PR-04, PR-11, PR-13
  `buildPrintSheet()` per page, called at the end of `buildTimetable()` **and on Cohort's two early returns** (design §3.3). My/Student read `eventsData`, Cohort reads `eventsByCohort[selectedCohortId]`, Venue reads its own per-week payload.
  **Done when:** on all four pages the sheet matches the week on screen; on Cohort, switching to an empty week or deselecting a cohort clears the sheet instead of leaving the previous one.

---

## Batch C — The block layout on paper, and the fit

- [ ] **T12 — The per-page block shapes** *(2h)* — PR-09, PR-10, PR-15
  The four `cfg.printShape` implementations (design §4.1) plus each page's own note gate (My/Student ungated, Cohort/Venue ownership-gated) and the Venue print call's flag list (no `cellRender`/`laneLayout`/`baseRowDays`/`bookableBadge`/`tooltipExtra`/`tipLecturerFn`/`responsive`).
  **Done when:** each page's sheet shows the contract's corners/centre arrangement, the surname rule holds, and Centres fall back to the course when cohorts are absent.

- [ ] **T13 — The fit pass and its fallback** *(2h)* — PR-10
  Measure each block's centre on the off-viewport sheet at the paper's content width; insert and size the band from the tallest rendered corner; shrink three-or-more-line centres half a pixel at a time to a 7px floor; reserve the note's strip; re-run on rebuild and on debounced resize; write the static per-line-count size when measurement is impossible.
  **Done when:** a long three-line centre measures smaller than the base size and never below 7px, a two-line centre gets a band, and a forced no-measurement state falls back to the static size.

- [ ] **T14 — Public-holiday rows and hostile empty states** *(1h)* — PR-13, PR-15
  Holiday-row skin at build time (PH wins over ownership; ownership in the border weight), and the printed empty panel's wording for both views.
  **Done when:** a holiday row's every block is conflict-red; a week emptied by the filter prints the panel, not a blank table.

---

## Batch D — The Normal Class view

- [ ] **T15 — The toggle in the shared week-nav** *(1h)* — PR-02
  `$showNormalToggle` (default `false`), the checkbox + label markup, the shared-pill tip, no inline `onclick`, and the page-local state rule (survives a week change, resets on reload).
  **Done when:** the toggle appears on exactly the four pages that opt in, and the arrangement page and the frozen templates are byte-identical.

- [ ] **T16 — The normal view's sheet** *(2h)* — PR-16
  Filter to the semester union, the composite days input (no holiday/today flags, Sunday dropped unless it carries a block), the week line `Week 1 ~ 14 (Whole Semester)`, the date dropped and the day upper-cased, the whole key hidden, and `No normal classes.` when nothing survives.
  **Done when:** a class normal in all weeks prints once; no `[Hn]` trace remains; the screen grid still holds every block.

- [ ] **T17 — Prove the screen did not move** *(1h)* — PR-04, PR-16
  With the toggle on and off, capture `#timetable.outerHTML` and the five summary card values on all four pages and compare.
  **Done when:** the only difference is the toggle's own state, and the comparison is recorded in the apply log.

---

## Batch E — Density, breaks, receipts, changelogs

- [ ] **T18 — Dense mode and page breaks** *(1.5h)* — PR-17, PR-18
  Dense styling in the sheet's own scope (padding/gap/line-height/min-height only, never a font size, never one border weight without the other) and the break rules (`thead` repeats, rows and key avoid breaking, no summary strip).
  **Done when:** a >32-block or >6-replacement print set sets the dense flag, a normal week does not, and no screen rule moves.

- [ ] **T19a — `tests/timetable-print.spec.ts`, week view** *(1.5h)* — PR-05 … PR-15, PR-18, PR-19
  The button calls `window.print()` (stubbed); the sheet is invisible on screen; under `emulateMedia({ media: 'print' })` the screen surface is gone and the sheet + header are present; the hour axis reads 12-hour start-over-end; the key has exactly three chips; no pending/cancelled block is in the sheet; the PH row carries the conflict skin; an empty Sunday row is absent (and a holiday Sunday's is present); the empty panel appears when the print set is empty; no focus stop lands inside the sheet. Page count and `@page` size stay a PDF/manual check, stated as such in the file.
  **Done when:** the spec passes on all four pages and its header comment states the instrument boundary.

- [ ] **T19b — `tests/timetable-print.spec.ts`, normal view** *(1h)* — PR-16, PR-17
  The union is deduplicated (one class normal in every week prints once); no `[Hn]` trace remains; the date is dropped and the day upper-cased; the key is hidden; the week line reads `Week 1 ~ 14 (Whole Semester)`; `No normal classes.` when nothing survives; **the screen grid keeps every block**; the dense flag appears for a print set over the threshold and never on screen.
  **Done when:** every normal-view scenario in PR-16/PR-17 has a case and passes.

- [ ] **T20 — Move TC05 and re-baseline the venue receipt** *(1h)* — PR-01, PR-04
  Rewrite `tests/venue-timetable.spec.ts` TC05 (it pins the "coming soon" toast). Re-capture the pin's **venue** receipt only, diff it against the old one, and log that the single difference is PR-03's separator. Synthetic and Cohort receipts are not touched.
  **Done when:** the diff is attached to the apply log, `tests/timetable-callers-pin.spec.ts` passes, and nothing else in the venue capture moved.

- [ ] **T21 — The four changelogs** *(1h)* — PR-20
  A dated entry in `page-changelogs/` for My Timetable, Student My Timetable, Cohort Timetable and Venue Timetable — each naming the print surface, the toggle, and (for Venue) the `' - '` → `' ~ '` change.
  **Done when:** all four files carry the entry, newest first.

- [ ] **T22 — PHP gate** *(30m)* — PR-20
  `composer run lint:check` and `vendor/bin/phpstan analyse --memory-limit=1G` (never `composer run types:check`), for the one additive view variable in `app/Livewire/StudentMyTimetable.php`.
  **Done when:** both pass and the output is recorded.

---

## Batch F — Verify evidence

- [ ] **T23 — The full receipt run and the PDFs** *(2h)* — all PRs
  Re-run the six existing suites from T01 plus the new spec; export the four pages and the normal view to PDF; check A4 landscape, 8 mm, one page for a normal week, header once, `thead` repeated, three-chip key stacked over the hint, no pending/cancelled block, PH row red, no empty Sunday row, corners/centre/note per page, `Week 1 ~ 14 (Whole Semester)`, paper light in both themes; then a 1440 browser pass on the toggle and a wide week that still lands on one page.
  **Done when:** the evidence table is filled in `verify-report.md` and every red is either explained or fixed.

- [ ] **T24 — `sdd.yaml` and the apply log** *(30m)*
  Close the lane's metadata: batches applied, receipts, the one permitted pin delta, and the open follow-ups (if any).
  **Done when:** `sdd.yaml` reads `implementation_approved`/`band: complete` truthfully and the apply log matches the commits.

---

## Estimate

T01–T24 (with T03b, T19 split into T19a/T19b) ≈ **30 hours** of implementation
work, split across the five batches the user approved. Batch 0 is read-only.
Batches A–E each end with a report and a stop.

## What this lane must **not** do (repeat, because these are the ones that get broken)

- Do not edit `#timetable`'s markup, `makeEventBlock()`, or any screen rule — the pin proves it.
- Do not hide rendered blocks with print CSS to make the paper right (that is what the print sheet is for).
- Do not reuse `.event-block` / `.timetable` / `.legend-item` for the print surface.
- Do not put the sheet's structural CSS inside `@media print` — the fit would silently stop working.
- Do not add a second layout builder; it is the same builder, twice.
- Do not print `—` for a missing lecturer, a participation-style placeholder, or an ID where the context is not a person.
- Do not skip T03b: it is the only task that makes PR-03 real, and T20 expects its diff.
