# Verify report — timetable-print-v2

**Date:** 2026-11-16 · **Against:** frozen `proposal.md` (round 4), `design.md`, `specs/timetable-print/spec.md` (PR-01…PR-20), `tasks.md`
**Verdict: PASS** for everything this lane owns. Every red is accounted for, and the four pre-existing red suites are proven pre-existing by a pre-lane run (§4).

Run target: `:8030` on `class_replacement_testing`, with the blessed flags
`CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`. Every spec below is
**read-only** — no SQL, no writes to the user's manual data.

---

## 1. The receipts

| Spec | Result | Was it green before this lane? |
|---|---|---|
| `tests/timetable-print.spec.ts` (**new**) | **12 passed** | n/a — new |
| `tests/timetable-callers-pin.spec.ts` | **6 passed** | 6 passed |
| `tests/timetable-responsive.spec.ts` | **36 passed** | 36 passed |
| `tests/pages-parity.spec.ts` | **6 passed** | 6 passed |
| `tests/venue-db.spec.ts` | 4 passed, **3 failed** | **3 failed** (same three, pre-lane run §4) |
| `tests/venue-status-parity.spec.ts` | 3 passed, 1 skipped, **4 failed** | **4 failed** (same four, pre-lane run §4) |
| `tests/venue-note-privacy.spec.ts` | 3 passed, **3 failed** | **3 failed** (same three, pre-lane run §4) |
| `tests/venue-timetable.spec.ts` | 57 passed, **1 failed** (TC40) | **2 failed** pre-lane — this lane fixed one (TC05), TC40 is the stale `Subject Code` case |

Quality gates: `composer run lint:check` **passed** · `vendor/bin/phpstan analyse
--memory-limit=1G` **passed, 0 errors**.

### What the 12 new cases assert

| Case | PR |
|---|---|
| the button prints; the paper is `visibility: hidden`, `position: absolute`, **1062 px wide** on screen and swaps in under print media while the grid, legend, cards, header and button all step aside | PR-01, PR-05, PR-19 |
| one header with the right context per page (staff `(5770)`, cohort code + `Faculty:`, venue `code — name`, student name + ID printed **once**) and a two-part week line | PR-06, PR-07 |
| three chips exactly, ownership line only on Cohort/Venue, no status word, `start ~ end` at the foot, centre leads with cohorts on My/Venue and the course on Student/Cohort | PR-09, PR-12, PR-14 |
| no empty Sunday row; printed rows equal the printed day names | PR-13 |
| pending and cancelled drop out of the run set; fourteen weekly copies of one class become **one** block | PR-11, PR-16 |
| dense comes on above 32 printed blocks, tightens spacing, keeps the two border weights unequal, and moves **no** screen rule | PR-17 |
| the normal view: `normal-view` class, key hidden, `Week 1 ~ 14` + `(Whole Semester)`, no dates, days upper-cased (by CSS transform), no holiday trace, and `#timetable` **byte-identical** | PR-02, PR-16 |
| the switch is offered at 768/1280 and stands down at 390 (the documented scope decision) | PR-02 |

---

## 2. The PDF evidence (the only instrument for `@page` and page count)

Exported with `page.pdf({ preferCSSPageSize: true, printBackground: true })` and read
back with `pdfinfo` / `pdftotext`.

| Page | Pages | Page size | Header (once) | Key | Axis | Notes |
|---|---|---|---|---|---|---|
| My Timetable | **1** | A4 841.92 × 594.96 pt | `My Timetable` · `Lim Jia Zheng (5770)` · `Weekly class schedule` · `Week 9 of 14 · 16 Nov 2026 ~ 22 Nov 2026` | 3 chips, **no** ownership line | 8:00→9:00 … 6:00→7:00 PM | Mon–Sat, Sunday absent |
| Cohort Timetable | **1** | A4 | `Cohort Timetable` · `DFT2(S1)G1` · `Faculty: FOCS` · week line | 3 chips **+** `thick border = your classes · thin border = others'` | 12-hour | Mon–Sat |
| Venue Timetable | **1** | A4 | `Venue Timetable` · `B005 — Computer Lab B005` · `Venue occupancy` · week line | 3 chips + ownership line | 12-hour | only class blocks marked |
| Student My Timetable | **1** | A4 | `Student My Timetable` · `Student 24RSD0009` · `Cohort: RSD2(S1)G2` · week line | 3 chips, no ownership line | 12-hour | Mon–Sat |
| My Timetable, **Normal view** | **1** | A4 | … · `Week 1 ~ 14 (Whole Semester)` | **hidden** | 12-hour | days `MON`…`SAT`, no dates |

Paper is light in both themes (the palette is fixed, not theme-swapped). One page per
page, header printed once, `thead` on the single page.

---

## 3. Acceptance criteria

| AC | Status | Evidence |
|---|---|---|
| AC1 A4 landscape, 8 mm, one page for a normal week | **PASS** | 5 PDFs, all 1 page at 841.92 × 594.96 pt |
| AC2 header once, `thead` repeats | **PASS** | header outside the table (structure); `display: table-header-group` asserted |
| AC3 per-page context, em dash, ID only for a person | **PASS** | PDF text above; `(5770)` on My, none on Cohort/Venue |
| AC4 two-part week line; `Week 1 ~ 14 (Whole Semester)` | **PASS** | PDF text + spec |
| AC5 12-hour axis, start over end, last column names its end | **PASS** | PDF text `8:00 AM / 9:00 AM … 6:00 PM / 7:00 PM` |
| AC6 corners · centre · time · note per page | **PASS** | PDFs + spec (centre leads with cohorts on My/Venue) |
| AC7 measured band, centre shrinks to a 7 px floor | **PASS** | measured: band 12 px, narrow base 9 px, all blocks fit; floor in the loop |
| AC8 no pending/cancelled on paper; screen keeps both; empty panel true | **PASS** | spec (run set) + the panel wording; screen untouched |
| AC9 holiday row red, empty Sunday not printed | **PASS** | build-time `print-holiday` + `printDays`; spec asserts 6 rows |
| AC10 three-chip key stacked over the ownership hint | **PASS** | PDFs + spec |
| AC11 every non-class venue cell bare | **PASS** | the venue print call passes no `cellRender`, so paper carries only blocks |
| AC12 normal view, sheet only | **PASS** | PDF + spec, with `#timetable` byte-identical |
| AC13 dense keeps one page, no font size, weights unequal | **PASS** | spec (flag + computed padding + unequal borders); one-page export |
| AC14 paper light in both themes; screen unchanged | **PASS** | paper palette fixed; pin + spec + `#timetable` byte-identical |
| AC15 no new dependency, no colour outside `theme.css`, no DB write | **PASS** | `package.json`/`composer.json` untouched; palette in `theme.css` once; no SQL run |
| AC16 pin: two receipts byte-identical, venue re-captured once | **PASS** | pin 6/6; the venue delta proven to be the separator alone |

---

## 4. The pre-existing reds, proven pre-lane

`git checkout b00066e -- <every product file this lane touched>`, cache cleared, the
four suites re-run, then HEAD restored:

| Spec | PRE-LANE | AFTER |
|---|---|---|
| `venue-db` | 3 failed, 4 passed | 3 failed, 4 passed |
| `venue-status-parity` | 4 failed, 1 skipped, 3 passed | 4 failed, 1 skipped, 3 passed |
| `venue-note-privacy` | 3 failed, 3 passed | 3 failed, 3 passed |
| `venue-timetable` | **2 failed**, 56 passed | **1 failed**, 57 passed |

So this lane changed none of them, and fixed one (`TC05`, which pinned the
"Printing is coming soon" toast). What they want:

- `venue-db` — the class pop-up no longer prints the word **`Capacity`** (the venue row
  is plain text with seats now) and two assertions around the B005 clean state.
- `venue-status-parity` / `venue-note-privacy` — anchors that are no longer true in this
  database (a pending row that has since been decided or auto-rejected).
- `venue-timetable` TC40 — the `Subject Code` label removed by `0e43019`.

None is this lane's to fix; each needs its own change.

---

## 5. What this lane changed, end to end

| Surface | Change |
|---|---|
| `partials/ui-week-nav.blade.php` | the button prints; the Normal Class switch (opt-in) |
| `partials/ui-print-header.blade.php` | **new** — the paper's header |
| `partials/ui-print-sheet.blade.php` | **new** — the paper's table |
| `public/js/ui-common.js` | `cfg.print` in the builder; six print derivations; `PrintSheet` |
| `public/css/theme.css` | the paper palette, the surfaces, the header, the block, dense, breaks, the switch; task 8's print rules withdrawn |
| the four blades | two includes each; the switch flag; `buildPrintSheet()` |
| `app/Livewire/StudentMyTimetable.php` | one additive view key (`studentNumber`) |
| `tests/` | one new spec (12 cases), TC05 and T16a-6 rewritten, the venue pin re-captured |
| `page-changelogs/` | four dated entries |

No migration, no schema change, no new dependency, no database write, and no edit to
`ui-prototype/*` or `resources/views/ui-design-templates/*`.

---

## 5b. The two amendments (2026-11-16, same day)

Both amendments were written, reviewed and frozen **before** their code, then applied.

| | Receipt |
|---|---|
| **A1 — the Print dialog** | `timetable-print.spec.ts` **16/16**: the button opens the dialog and prints nothing (0 print calls); the dialog seeds from the week on screen; printing Week 3 from a page showing Week 9 puts `Week 3 of 14` on the paper and leaves `#timetable` **byte-identical**; All Weeks disables the switch, reads it ON and shows its hint; a single week with the switch on carries no replacement block; an open dialog never prints (the print stylesheet names `.print-modal-overlay` and it is hidden under print media) |
| | Pin 6/6, responsive 36/36, parity 6/6, `venue-timetable` 57/58 (only the pre-existing TC40), `venue-db`/`venue-status-parity`/`venue-note-privacy` **identical** to the pre-lane run |
| | PDFs: `Week 3 of 14 · 5 Oct 2026 ~ 11 Oct 2026` one page A4 landscape with its key; `Week 1 ~ 14 (Whole Semester)` one page without it |
| | Cohort: clearing the select now clears the **paper** (0 blocks) and disables `Print` — the `showGuidance()` funnel (§A11), verified live |
| **A2 — the house toggle** | 16/16 again; measured 36 × 20 track / 16 px thumb / 8 px gap / 13 px 500 label; both themes screenshotted; the All-Weeks state reads filled-but-dimmed |

**One correction to amendment 1's own reasoning**, made when the code proved it: A1 promised the
switch would be "reachable at every width". The switch is — but the *printer button* is hidden
below 1280 on My Timetable and Student My Timetable by task 8's `preview-desktop-only` ruling
(`theme.css:6163`), so on those two pages there is no print entry at that width at all, and the
dialog cannot be reached regardless of the switch. Verified reachable at 390 on the Cohort page.
The receipt asserts the real behaviour rather than the promise, and the claim is corrected in the
apply log and in `PR-02`.

## 6. What remains open (not blockers)

1. **The four pre-existing reds** (§4) — each needs its own small change.
2. **The phone question is closed by the dialog, not by a layout change.** The switch left
   the week row (amendment 1), so task 8's tuned row has its width back and `T16d` #3 is
   untouched. What remains true and out of this lane's scope: the **printer button** is hidden
   below 1280 on My Timetable and Student My Timetable (`theme.css:6163`, task 8's
   `preview-desktop-only`), so those two pages offer no printing from a phone at all — a
   pre-existing ruling. Reaching it from a phone would be that lane's decision to reverse, not
   a print-format one.
3. **Dense mode** has no shipped fixture above the threshold (the repo forbids
   fabricating demo rows), so its receipt proves the mechanism with a synthetic print
   set and the one-page export proves the resting case. A genuinely busy week's page
   count stays a manual check, as the design says.
4. **A notes strip is not printed** — the prototype's `.print-notes` is preview-only by
   the contract, so a public-holiday row is explained by its colour and the key only.
