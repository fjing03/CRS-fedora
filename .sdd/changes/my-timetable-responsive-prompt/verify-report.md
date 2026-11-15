# Verify report — `my-timetable-responsive-prompt`

**Verdict: PASS.** Verified 2026-11-15 against the frozen proposal (4 review rounds), design (3),
specs (2) and tasks (2). Nothing was pushed; the lane is ready for `/sdd-archive`.

## 1. Tasks

**20/20 ticked**, and each tick is backed by code (T0a's was ticked late — the fixture and its lone
commit were already there, the checkbox was not).

## 2. Scope

Every requirement R1–R10 was walked against the code by an independent read-only reviewer, one at a
time, and every one has an implementation and a receipt. Two things are worth naming as CSI:

- **The one unscoped rule is the one authorised**: `.badge-cancelled` (`theme.css:1341`) in
  `.badge-reason`'s token pair, not `--color-error`.
- **The shared partials were not edited** — proven by commit, not by eye:
  `git log c8b06ff..HEAD -- <path>` returns **0 commits** for `ui-week-nav`, `ui-legend-bar`,
  `ui-summary-bar`, `ui-grid-table`, `ui-class-detail-modal`, `ui-cancel-class-modal` and
  `ui-page-header`. The week-nav tips ride the partial's pre-existing `$prevTip`/`$nextTip`/`$selectTip`.

## 3. Invariants

**(a) `buildTimetableGrid()` is byte-identical with the flag absent.** Exactly two changes exist inside
`makeEventBlock()`, both behind `cfg.responsive`: the tip migration (`data-tip` instead of the
`data-name` + `data-tip2` pair, same sentence) and `statusWord` (empty string when the flag is off, so
the innerHTML concatenation is unchanged). The pin (`tests/timetable-callers-pin.spec.ts`, 6 receipts)
compares a rebuilt synthetic call and the live Cohort/Venue markup against the **pre-change** capture,
with the first differing offset in the failure message, and separately asserts the flag-absent proof
(`data-tip2` on every block, `data-tip` on none).

**(b) Shared files untouched** — see above.

**(c) CSS scoping** — the whole new section is `.tt-responsive`-prefixed or scoped through
`:has(.tt-responsive)`, with the single authorised exception.

## 4. Findings and what was done with them

### 🔴 Blockers
None.

### 🟡 Fixed during verify

1. **R5b had no receipts.** The behaviour was there (the shared 768 re-label; `_state.day` surviving a
   week change) but nothing asserted it. Two receipts added: **3b** (390 → no year, compact
   `Week N · d Mon ~ d Mon`; 900 → carries the year, longer) and **3c** (a Wednesday tapped, then the
   week changed through the page's own `selectWeek()`: still Wednesday, and the agenda still matches
   that day's chip count).
2. **The cancelled card's word carried a legacy pill.** `.status-cancelled` (theme.css:5049) painted a
   grey pill behind `✕ Cancelled` while `⚠ Conflict` — which has no legacy skin — stayed bare: two
   presentations for one tier. A browser look confirmed it, and also caught the real problem behind it:
   the card read **`(Cancelled) ✕ Cancelled`**, the note duplicating the word that replaced it. R3 caps
   the note line at "the date note and one status word", so the word now suppresses the duplicate
   status note (date notes — pending since, replaced for, proposed — stay), and `.tt-status` is
   `background: transparent`.
3. **T0a's checkbox** was unticked; ticked.
4. **The commit-level partial check** had not run (the reviewer is read-only); ran it — 0 commits on
   all seven shared files.

### 💡 Accepted, not done

- `.tt-day::after` reserves a 4px dot slot on every chip and only `.is-today` fills it — invisible, and
  it is what keeps the chips from shifting when the dot appears.
- A window-mode cell was not *measured* under `hasTouch` (the touch receipt measures cards at 390 and
  the desktop controls at 1440); the CSS sets `min-height: 48px` on those rows and the 1440 arm proves
  the law reaches the page. Recorded rather than papered over.
- The R8 print receipt is staff-only; the student page inherits the same two rules. Cheap to add if the
  print lane ever touches this page.
- Screenshots live in `/tmp/mt-resp/shots/`; the numbers they support are transcribed into the apply log.
- `tasks.md` skips T10 (T9 absorbed the gate and the sheet). Left as written, since the numbers are
  referenced by the apply log.

## 5. Regression readings (recorded in the apply log)

| spec | result |
|---|---|
| `timetable-callers-pin` | 6/6 |
| `timetable-responsive` | 21/21 (19 + the two R5b receipts) |
| `timetable-lane-layout` | 13/13 |
| `filters-button` | 20 pass + 2 skips (its own documented pair) |
| `summary-cards-button` | 15/16 — the red is the documented testing-DB data gap on `/my-replacements` |
| `e2e/timetable.spec.js` | 6/6 — **was red before** the lane, amended, now green |
| `ui-regression` | 19 pass / 9 stale failures, each named in the apply log |
| `class-modal-affordances` | 3/6 — fails inside its own login (storageState **and** a second login); unmodified by this lane |

## 6. What a reviewer should be able to check in five minutes

1. `tests/timetable-callers-pin.spec.ts` green → the other three timetable pages did not move.
2. `git log c8b06ff..HEAD -- resources/views/partials/` → only the new `ui-tt-chrome.blade.php`.
3. At 390: the week row pins, seven chips, one day of cards, `Overlaps …` on both cards of a clash,
   `Week summary` opens a sheet with the legend and the five cards.
4. At 1024: three days, transposed, the selected day first.
5. At 1440: today's page, plus `✕ Cancelled` under the time where a class is cancelled.
