# Apply log — timetable-print-v2

Newest batch first. Nothing here is a claim without a command behind it.

---

## Batch 0 — baseline before any edit (2026-11-16)

Run against `:8030` (`class_replacement_testing`, the user's manual lane) with the
blessed flags: `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`.
All of these are **read-only**.

| Spec | At HEAD before this lane touched anything |
|---|---|
| `timetable-callers-pin.spec.ts` | **6 passed** |
| `venue-status-parity.spec.ts` | 3 passed, 1 skipped |
| `venue-note-privacy.spec.ts` | 3 passed, **3 failed** — see below |
| `timetable-responsive.spec.ts` | **36 passed** |
| `venue-db.spec.ts` | **4 passed** |
| `pages-parity.spec.ts` | **6 passed** |

**Pre-existing red, not this lane's:** `venue-note-privacy.spec.ts` expects a block
reading `(Pending since 02 Nov 2026)` on the venue page and finds none
(`expected ["(Pending since 02 Nov 2026)"] / received undefined`, first at
`tests/venue-note-privacy.spec.ts:52`). The row it wants is no longer pending in
this database — the five-minute auto-reject sweep or a manual decision changed it.
Data-dependent, pre-existing, untouched by this lane.

Also pre-existing (found while checking the venue suite): `venue-timetable.spec.ts`
TC40 looks for a `Subject Code` row in the class pop-up — one of the stale cases the
handover lists as removed by `0e43019`.

**T02 — the contract diff.** All fourteen `DESIGN CONTRACT` subjects are answered by
a section of `design.md`:

| Contract subject | `design.md` |
|---|---|
| `SCOPE` | §1, §2.1 |
| `CHROME IS THE LIVE PRINT BAR` | §7.5 (the app shell is hidden; the prototype's own chrome is not ported) |
| `TOOLTIPS` | §8.1, §7.3 |
| `ONE SHEET` | §7.2, §7.3 |
| `ONE HEADER` | §6.1–6.2 |
| `PRINT READS 12-HOUR TIME` | §4 |
| `BLOCK LAYOUT` | §4.1, §5 |
| `NO INVENTED STATUS WORD` | §4, §5.2 |
| `NORMAL CLASS VIEW` | §8 |
| `VENUE ON PAPER` | §9 |
| `PAPER SHOWS WHAT RUNS` | §3.1, §3.3 |
| `DENSE MODE` | §7.4 |
| `PAGE BREAKS` | §7.3 |
| `PREVIEW-ONLY, NOT FOR THE PORT` | §2.1, §9 (nothing ported) |

---

## Batch A — the real button and the print surfaces (2026-11-16)

### What changed

| Task | File | Change |
|---|---|---|
| T03 | `resources/views/partials/ui-week-nav.blade.php` | the stub became `onclick="window.print()"`, `aria-label`/`data-tip` = `Print this week`; the "coming soon" toast, its tip and its comment are gone. Icon, class and geometry untouched; `$showPrint` untouched |
| T03b | `resources/views/livewire/venue-timetable.blade.php` | the venue block's `.ev-time` now joins with `' ~ '` (was `' - '`) — the contract's own screen change |
| T04 | **new** `resources/views/partials/ui-print-header.blade.php` | title · subtitle · context · semester chip · two-part week line · three-chip key, with the ownership hint on its own line |
| T05 | **new** `resources/views/partials/ui-print-sheet.blade.php` + the four blades | the hidden `.ui-print-sheet` (`#printTimetable`/`#printTableHead`/`#printTableBody`/`#printEmpty`), included **after** each page's own `#timetable` wrapper |
| T06 | `public/css/theme.css` | new print region: the `--p-*` paper palette (once), both surfaces off-viewport but laid out at **1062 px**, the header/key/table skeleton, and the `@media print` block (`@page` A4 landscape + 8 mm, chrome hidden, surfaces swapped, breaks) |
| T07 | `public/css/theme.css` | task 8's print rules withdrawn: the `!important` re-show of `.legend-bar` and `#summarySection`, the forced `.grid-wrapper`/`#timetable` display and the `data-tt-empty-week` print swap, each with the contract subject that overrules it |
| — | `app/Livewire/StudentMyTimetable.php` | one additive view key (`studentNumber`) so the paper can print a person's ID; no query, no schema change |
| — | `tests/timetable-responsive.spec.ts` | T16a item 6 rewritten: it asserted the very rules T07 withdrew |
| — | `tests/venue-timetable.spec.ts` | TC05 rewritten: it pinned the "coming soon" toast |

### The pin's one permitted delta — measured, not asserted

`tests/timetable-callers-pin.spec.ts`: **5 passed, 1 failed**, and the failure is the
venue receipt at the time separator:

```
first differing offset: 6283
baseline @offset: …<span class="ev-time">12:00 PM - 2:00 PM</span>…
actual   @offset: …<span class="ev-time">12:00 PM ~ 2:00 PM</span>…
baseline 29115 bytes · now 29115 bytes (delta +0)
```

A separate read-only pass compared the venue `#timetable.outerHTML` against the
fixture with the pin's own normaliser and reported:

```json
{ "week": "9 Nov 2026", "blocks": 5,
  "identicalWithoutChange": false,
  "onlyTheTimeSeparator": true,
  "differenceCount": 5,
  "diffs": [ "- 2:00 PM" → "~ 2:00 PM", "- 1:00 PM" → "~ 1:00 PM",
             "- 10:00 AM" → "~ 10:00 AM", "- 11:00 AM" → "~ 11:00 AM",
             "- 2:00 PM" → "~ 2:00 PM" ] }
```

Five blocks, five separators, nothing else — the receipt is **not** re-captured yet
(that is T20, after Batch C has settled the sheet); the measurement above proves the
re-capture will contain exactly one kind of change. The synthetic and Cohort
receipts are untouched and pass.

### The rest of the receipts

| Check | Result |
|---|---|
| `timetable-responsive.spec.ts` (T16a item 6 revised) | **36 passed** |
| `pages-parity.spec.ts` | **6 passed** |
| `venue-db.spec.ts` | **4 passed** |
| `venue-status-parity.spec.ts` | 3 passed, 1 skipped |
| `venue-timetable.spec.ts` (TC05 rewritten) | 57 passed, 1 failed — TC40, the pre-existing `Subject Code` stale case |
| `composer run lint:check` | **passed** |
| `vendor/bin/phpstan analyse --memory-limit=1G` | **passed** (0 errors) |

### The print swap, measured on screen and under print media

My Timetable, `< 1280` band, `emulateMedia({ media: 'print' })`:

| | screen | print |
|---|---|---|
| `.grid-wrapper` | `block` | **`none`** |
| `#printSheet` | `hidden`, `absolute`, off-viewport | **`visible`, `static`, full width** |
| `#printHeader` | `hidden` | **`visible`** (58 px tall) |
| `.top-bar` | shown | `none` |
| `#summarySection` | shown | `none` |
| `.legend-bar` | shown | `none` |
| `.print-btn`, `.page-header`, `#emptyState` | shown | `none` |
| `body` background | theme | **`rgb(255,255,255)`** |

And on screen, all four pages: the two paper surfaces exist, report
`visibility: hidden`, and are **off the viewport** (`onScreen: false`) — so they are
invisible *and* have a layout to measure later (design §5.1). No console or page
errors on any of the four.

### Per-page header, as served today

| Page | Title | Subtitle | Context | Key chips | Ownership line |
|---|---|---|---|---|---|
| My Timetable | `My Timetable` | `Weekly class schedule` | `Lim Jia Zheng (5770)` | 3 | no |
| Student My Timetable | `Student My Timetable` | `Cohort: RSD2(S1)G2` | `Student 24RSD0009` | 3 | no |
| Cohort Timetable | `Cohort Timetable` | *(empty — Batch B)* | *(empty — Batch B)* | 3 | yes |
| Venue Timetable | `Venue Timetable` | `Venue occupancy` | `B005 — Computer Lab B005` | 3 | yes |

### Honest interim states (both expected, both temporary)

1. **The button prints an empty sheet.** The paper is fed in Batch B (T11), so until
   then a click yields the finished chrome, the header and no table. This is a step
   back from the toast and is reported rather than hidden.
2. **The Cohort header is blank in two places.** Its centre and `Faculty:` subtitle
   are filled by page JS, because those selects change without a navigation
   (design §6.2) — Batch B.
3. The paper's blocks are unstyled beyond the table skeleton; corners/centre/band are
   Batch C.

### One correction found while verifying

The student context first printed `Student 24RSD0009 (24RSD0009)` — the seeded
name *is* the ID, so appending it blindly printed it twice. The blade now appends the
ID only when the name does not already carry it (the contract's shape is "name +
(ID)", and a real name will get it).

### Commits

| | |
|---|---|
| `acb5d13` | `docs(timetable-print)` — explore, proposal, design, specs, tasks |
| `b61960b` | `ui(timetable-print)` — the real button, the two paper surfaces, the print region, task 8's withdrawn rules, the venue joiner, the student number |
| `65080b9` | `test(timetable-print)` — the two receipts the real button reset |

Unpushed, as always. Nothing of this lane was hunk-staged out of another
session's file: none of the files it touches carries another session's work.

### Rollback

`git revert` of this batch's commits restores the stub button, the hyphen joiner and
task 8's print rules; the four blades lose two includes; `theme.css` loses one
appended region and regains the four withdrawn rules. The screen grid was never
edited, so there is nothing to un-edit.

---

## Next

Batch B — the builder's `print` mode, the print-set derivations and the four pages'
`buildPrintSheet()` wiring. Reported before Batch C starts, as agreed.

---

## Batch B — feed the sheet (2026-11-16)

| Task | Change |
|---|---|
| T08 | `buildTimetableGrid()` gained one opt-in `print` flag: 12-hour hour axis, the `.print-*` block, no status word, no `tabindex`, per-page shape via `cfg.printShape` / `cfg.printClassFn`. Absent flag → today's bytes |
| T09 | `printRunSet()`, `printSemesterSet()`, `printDays()`, `printSurname()`, `printNoteText()`, `printNormalDays()` in `ui-common.js` |
| T10 | `PrintSheet` (`init` / `isNormal` / `rebuild` / `fit`): the header's context, subtitle, semester, two-part week line, the key, the printed empty panel and the density flag |
| T11 | `buildPrintSheet()` on all four pages; Cohort rebuilds on **both** early returns |

Receipted after the batch: 12-hour hours, six rows, per-page corners/centre, Venue's
three cohorts one per line, no console errors, `#timetable` unchanged (pin: synthetic
+ Cohort byte-identical, venue differing only at the separator).

## Batch C — the block layout and the fit (2026-11-16)

Twelve-case CSS for the paper's block (corners absolute, measured band, note out of
flow), the fit pass, dense mode, `normal-view` rules — then printing it for real
found **four layout bugs**, each fixed at the cause:

| Bug | Cause | Fix |
|---|---|---|
| the day column landed on the wrong side and the rest of the table vanished | the paper reused the screen's `.time-col`, which is `position: sticky; width 120px; z-index 15` — sticky cells in a printed table paint against the page box | the builder emits `.print-day` / `.print-cell` under the print flag (the design's own "own vocabulary" rule) |
| the table collapsed to its narrowest content | `.app-container` is a flex container and the paper's `width: auto` made it shrink-to-fit | on paper the shell steps out of the way; the surfaces stretch |
| the app's dark canvas showed through the paper | cells were transparent | the paper paints its own surface |
| screen instructions printed | `.booking-hint-line`, `.lead-time-note`, `.summary-hint` | hidden on paper |

Measured: sheet at **1062 px**, a three-line centre gets a measured band (12 px), a
one-hour block takes the narrow sizes (9 px), every block `scrollHeight ≤ clientHeight`.

## Batch D — the Normal Class view (2026-11-16)

The switch in the shared week row (opt-in, default false), bound by `PrintSheet` (no
inline handler), page-local and reset on reload. On: the sheet is rebuilt from the
deduplicated semester union, `Week 1 ~ 14` + `(Whole Semester)`, dates dropped, days
upper-cased, no holiday trace, the key hidden.

**Measured on My Timetable:** before and after the toggle, `#timetable.outerHTML` is
identical and it still holds 7 blocks. The screen did not move.

Two paper bugs found by printing the normal view: the header's right column sized its
items to content and ran past the page edge (`(Whole Semester)` was cut in half); a
hover pill that happened to be on screen printed with the sheet.

## Batch E — receipts, pin, changelogs (2026-11-16)

- `tests/timetable-print.spec.ts` — **12 cases**, listed in the verify report.
- `tests/venue-timetable.spec.ts` TC05 rewritten (it pinned the coming-soon toast).
- `tests/timetable-responsive.spec.ts` T16a item 6 rewritten (it asserted the print
  rules this lane withdrew).
- The pin's **venue** receipt re-captured. Proven, not asserted: with the pin's own
  `withoutToday()` normaliser, undoing just the time joiner in the new capture
  reproduces the 2026-11-15 capture exactly (5 blocks, 5 separators, nothing else).
  Synthetic and Cohort receipts untouched, both still byte-identical.
- Four page changelogs.

### One scope decision, made from a receipt — and CONFIRMED BY THE USER (2026-11-16)

The switch **stands down below 768 px**. Presented to the user with the three
alternatives (leave it; give it its own line on phones; move it into the Week summary
sheet) and their costs; **the user chose to leave it as it is.** Adding it to the phone week row truncated the
week label, and task 8's own receipt (`T16d` item #3) refuses that: at 390 the widest
label needs 144 px and the box offers 143. Shaving the label again for a reading mode
was not worth it, so the switch is offered from 768 up and
`tests/timetable-print.spec.ts` asserts that boundary at 390 / 768 / 1280 — a decision
with a receipt rather than a silent gap. A phone still prints the week.

Two layout fixes fell out of the same work: the switch's **input** is now the real hit
box (48×48 on touch — a 1×1 visually-hidden input was refused by R7, rightly), and the
venue bar's **five-column grid became six** (the sixth child had fallen into an
implicit column and squeezed the venue dropdown).

## Batch F — verification (2026-11-16)

Twelve receipts, five PDFs, lint and PHPStan. Full table in `verify-report.md`.

---

## Amendment 1 — the Print dialog (2026-11-16, applied the same day)

| Task | What landed |
|---|---|
| T27 | The switch left `ui-week-nav` (flag and markup) and the ≤768 rule was deleted; new `partials/ui-print-modal.blade.php`; included on the four pages; the button now opens it (`PrintSheet ? open() : window.print()`, so the replacement pages that share the partial keep the plain browser print) |
| T28 | `PrintSheet` owns the dialog: `open`/`close`/`print`, the week list from `weeks()`, state `{week, normal}`, the disabled-and-ON rule, `Esc`/backdrop, focus in and back, `canPrint` veto, and the preference memory |
| T29 | The four `build(normal)` → `build({week, normal})`; **the venue's All Weeks became the semester union** (§A6, which also repairs the frozen PR-16 that the applied code had deviated from); cohort's `showGuidance()` now rebuilds the paper (§A11) |
| T30 | The dialog's field/switch CSS and **`.print-modal-overlay` in the `@media print` block** |
| T31 | `timetable-print.spec.ts` 12 → **16 cases**; `venue-timetable` TC05 re-pointed |
| T32 | PDFs: Week 3 chosen from a page showing Week 9, All Weeks, and the dialog itself |
| T33 | This log, the verify report, four changelogs, `sdd.yaml` |

**Two facts found while applying, both recorded rather than smoothed over:**

1. **The old "the switch stands down below 768" decision is moot, and so is part of A1's
   promise.** The switch left the week row, so the row that task 8 tuned now has all of its
   width back. But the *print button itself* is hidden below 1280 on My Timetable and
   Student My Timetable (`theme.css:6163`, task 8's `preview-desktop-only`: "printing from a
   phone is the browser's own menu"). So A1's "the switch is reachable at every width" is
   true only where the button is: verified reachable at 390 on the **Cohort** page, and
   deliberately not on the two My pages, which have no print entry at that width at all —
   a pre-existing ruling of that lane, not this amendment's doing. The receipt asserts
   exactly that (`PR-02`, amended).
2. **The resting paper is still the week on screen.** `PrintSheet.init()` seeds the dialog's
   week from `screenWeek()` on every rebuild *until the reader chooses*, so nothing about the
   page's at-rest paper changed; All Weeks only appears once asked for. The first draft had
   `_week = null` until the dialog was opened, which made the resting paper the semester —
   caught by the existing `PR-06` receipt before any of it was committed.

## Amendment 2 — the dialog's switch wears the house toggle (2026-11-16)

`.print-modal__switch` is **deleted**; the dialog renders the sanctioned
`.toggle-wrapper` / `.toggle-track` / `.toggle-thumb` / `.toggle-label` that the replacement
pages' filter bar and the notification rows use. Measured on the built page: track **36 × 20**,
thumb 16 px, gap 8 px, label 13 px/500, wrapper 189 × 24 — the reference's numbers, to the pixel.

Three rules were added, all scoped to the field: the All-Weeks disabled look (dimmed to .55 —
the reference has no disabled state at all), `min-height: 24px` (the house row is 20 px tall and
WCAG 2.5.8 asks for 24 × 24; A12's 48 px promise is superseded deliberately), and the hint's
indent, which had been aligned to the deleted label. Two test lines moved, exactly as the
amendment predicted: the scoped selector and a wrapper click (the house input is 0 × 0).
