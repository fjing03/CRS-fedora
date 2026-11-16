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
