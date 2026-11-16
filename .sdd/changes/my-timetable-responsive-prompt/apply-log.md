# Apply log — `my-timetable-responsive-prompt`

Batches A–F applied 2026-11-15 on the testing DB (`class_replacement_testing`, `:8030`), by subagents
under the orchestrating session, each verified before the next began. Batches G–H below.

## T0a — baselines (before any code)

Captured `tests/fixtures/t0a-baseline.json` and committed it **alone**, so the file's history proves it
was taken while the builder was still untouched:

- **synthetic**: `buildTimetableGrid()` driven with a fixed five-event array through `/login` (the only
  route that loads `ui-common.js` without a session), plain non-`cellRender` mode plus the cohort page's
  `tipLecturerFn`/`statusClassFn` shapes. This is the branch T12's two edits land in.
- **live**: `#timetable.outerHTML` from Cohort (`?week=9`, 11 blocks, 19 690 bytes) and Venue
  (`?venue=B005&week=9`, 5 blocks, 30 914 bytes).

**Deviation, recorded:** the Arrangement page paints no grid until a course is picked, and its course
picker did not respond to `selectOption()` through three attempts; a third live capture was therefore
dropped. Arrangement is covered by the synthetic call (same function, same flags), and T15's live pins
are Cohort + Venue. Nothing about the invariant is weaker: the synthetic call is the branch that
actually changes.

## Batch A — the shell (T1, T2, T3)

- T1 `partials/ui-tt-chrome.blade.php` (new) as design §4.1, taking `$ttScope`, `$ttReadonly`,
  `$ttWeekNav`. Nothing calls `init()` yet and no page carried the scope class, so the app was
  unchanged by this batch.
- T2 one new `theme.css` section, every rule prefixed `.tt-responsive`, plus the single unscoped
  `.badge-cancelled` skin (it had never been defined: a cancelled class's Status row printed a bare
  word in a transparent pill; it takes `.badge-reason`'s token pair, not `--color-error`, which is
  already public holiday's and conflict's). Two specificity fixes were needed against the 44px sticky
  rules and the card's `min-height: 0`; both are commented in place.
- T3 `TimetableResponsive` state + three media queries + `sync()`, which sets `data-tt-mode` (the hook
  the mode rules key on) and hides the legacy 22-column table below 1280.

## Batch B/C — the phone and the tablet (T4–T8)

- The strip, the agenda card, the next-class hint, and the transposed window grid. Reused rather than
  re-invented: `to12h()`, `DateHelper.add30min()`, `buildReplacementNote()`, `eventStatusLabel()`, and
  `layoutTimetableDay()` for the side-by-side split inside a window cell. `sync()` gained the
  one-surface-at-a-time switch (the window node sits outside its media block, so CSS alone cannot hide
  it).
- **T6's week change** goes through `window.selectWeek()` — the very function the week `<select>` calls
  today — so no new route and no blade edit. `findNextClass()` may return `{ week, day, event }`; the
  event is read for the hint's label.
- **CSS added, declared:** `.tt-whead`, `.tt-whead small`, `.tt-axis`, `.tt-gcell` — the preview's
  tablet head/axis/cell had no skin in the shell, so the window grid would have rendered unstyled.
- **Four rules ported from the preview's phone block** that the shell's CSS list had missed (found by
  measuring, not by reading): `.week-select` 12px with 6/24px padding, the print button hidden below
  768 (the preview's own `preview-desktop-only`), and — for 320–389 only — a wrapped week row with the
  nav taking the full line. Without them the phone label clipped to `Week 8 · 9 N` at 390 and worse at
  320; with them 320/360/390/430 all show `Week 8 · 9 Nov ~ 15 Nov` complete, zero page-level
  horizontal scroll. The print-button hiding is **prototype parity, not a feature drop**: the approved
  preview marks it desktop-only, and printing from a phone is the browser's menu.

## Batch D — the Week summary sheet (T9)

- The gate first: `SummarySheet.init()` returns early when the document carries `.tt-responsive`, before
  it binds anything — no observer, no print handlers, no `_bound`. The residual `#summarySheet` frame is
  inert (its only opener is the header's `$summaryButton`, which T11/T14 remove).
- `openSheet()`/`closeSheet()` move `.legend-bar` and `#summarySection` with per-node placeholders;
  `#ttSheetRange` is built from `MockData.semester.chipText` and appended last, so the body order is
  legend → cards → range with no reordering code. Esc is capture-phase and suspended while a dialog is
  above (one Escape, one layer); Tab wraps inside the dialog; focus returns to `#ttSummaryButton`; the
  lock is the new `tt-sheet-open`. `beforeprint` restores the nodes, `afterprint` re-relocates.
- The bell ↔ sheet "one overlay at a time" pair is closed both ways, the second half from inside
  `openNotifPanel()` — no blade touched.

## Batch E/F — the two pages (T11–T14)

- Both roots gain `tt-responsive` + `data-tt-page`; the week row is rendered once as a string and passed
  into the chrome partial's sticky wrapper; `responsive: true` on both builder calls; `summaryButton`
  removed on both; `TimetableResponsive.init()` at the end of `buildTimetable()` with the same closures
  the grid gets (so a card's tip and colour cannot disagree with its desktop block).
- **Design §7.1's include placement was wrong and did not ship.** It puts the chrome include after the
  legend/summary includes; that seats the sticky wrapper — which carries the week row — *below* the five
  summary cards at ≥1280, a desktop regression. The include sits in the old `.semester-bar` seat
  instead: measured order at 1440 is header → guide modal → sticky (week row + strip) → tools/agenda
  (hidden) → grid → legend → cards, i.e. today's order exactly, while the phone still reads week row →
  strip → tools → agenda because everything after the agenda is hidden there.
- **One undesigned rule was needed:** `.tt-responsive .event-block[data-tip]::after { display: none }`.
  T12 writes a single `data-tip` on the desktop blocks, so the legacy `::after` pair resolved to
  `"<name> · "` and painted a truncated bubble *under* the shared engine's correct one. The frozen CSS
  list only suppressed it on `.tt-card`.
- **A decision the design left open, taken and recorded here:** the selected day starts as **today's**
  index when the current week contains today, else Monday. It is a `_dayInitialised` flag in `init()`,
  so a later week change or resize never re-defaults it.
- T13's flag is per-call (`longCancelledLabel`), so Cohort / Venue / Replacement History keep
  `Cancelled`; the two timetable pages print `Class Cancelled`.

## Verified live at the end of Batch F (testing DB, zero page errors)

| width | `data-tt-mode` | strip | agenda cards | window cells | table | h-scroll |
|---|---|---|---|---|---|---|
| 390 | agenda | 7 chips | 1–2 on the days that have classes | — | hidden | 0 |
| 900 | window | 7 chips | — | 0 (Sat/Sun) | hidden | 0 |
| 1100 | window | 7 chips | — | 1 (Fri–Sun) | hidden | 0 |
| 1440 | full | none | — | — | restored | 0 |

Also measured: migrated tip `Operating Systems · DFT2(S1)G1 + DSF2(S1)G1 · PUBLIC HOLIDAY` in one
`data-tip` (no `data-tip2`); sheet body order `legend-bar | summarySection | ttSheetRange`; day survives
a week change; card click reaches the page's own details modal; `Class Hours` and the cohort chip intact.

## Known reds, not this lane's

- `tests/e2e/timetable.spec.js` was **red before this lane** (it asserts that `#semesterProgress` /
  `#weekSubtitle` are visible; both were removed 2026-11-13). T17 amends it.
- `tests/summary-cards-button.spec.ts`'s My Timetable arms cannot hold once `SummarySheet` is gated and
  the header opt-in is gone; T17 amends them by name.

## Batch G — the tests (T15–T17)

- **T15 `tests/timetable-callers-pin.spec.ts`** — 6 receipts, green. Rebuilds the captured call
  (the fixture now carries its own `syntheticEvents` / `syntheticDays`) and compares head + body +
  display byte for byte; then Cohort and Venue live against the captured `outerHTML`, failing with
  the first differing offset and both snippets. Also asserts the flag-absent proof: on those two
  pages every block carries `data-tip2` and **none** carries the migrated `data-tip`.
- **T16 `tests/timetable-responsive.spec.ts`** — 21 receipts, green (19 at apply, plus verify's two
  R5b receipts 3b/3c): phone at 320/360/390/430,
  tablet at 768/1024, desktop + touch at 1440. `?week=` is **not** honoured by the two blades
  (both derive the week from the timeline), so the week is fixed through the page's own
  `window.selectWeek()` — the function the week select calls.
- **T17** — `summary-cards-button.spec.ts`: My Timetable out of `FLIP_PAGES` (Venue stays) and its
  six receipts retargeted/removed with reasons in place. `e2e/timetable.spec.js`: the removed pair
  replaced by `#timetable` + two "never resurrected" assertions, **and** the entries given sessions
  — they had none, so the file had been stopping on the login page for months (green now: 6/6).

### Four defects found by the receipts, fixed the same day

1. **The sheet showed only its range line.** The mode rules that hide the legend bar and
   `#summarySection` inline match the *relocated* nodes too (the sheet body is inside the page
   root); they are now re-shown inside `#ttSheetBody`.
2. **Enter/Space on a card opened nothing.** The shared key seam reads `__eventData`, which
   `makeEventBlock()` sets and the built cards did not — the key was swallowed and the native click
   suppressed. Cards now carry it.
3. **Tab escaped the sheet on the sixth press.** The trap's reachable list counted the cards' ⓘ
   buttons (matching the selector, keeping their 44px box while `visibility:hidden`, out of the tab
   order), so its `last` node was unreachable and the wrap never fired.
4. **`_dialogAbove()` was true on every page**, suspending the trap and Escape: it copied a
   predicate that treats "never styled" as "shown" (`#summaryExplainModal` has no inline display).
   It now asks whether the node is actually shown.

The two `test.fail` annotations T16 shipped with came off once 1 and 2 were fixed.

## Batch H — evidence (T18, T19)

Both pages, both themes, six widths, **zero page errors**, page-level horizontal scroll **0**
everywhere:

| page | width | mode | strip | tables | other |
|---|---|---|---|---|---|
| staff | 320 / 390 | agenda | 7 chips | hidden | h-scroll 0, week label complete |
| staff | 768 | window | 7 chips | hidden | 2-day window |
| staff | 1024 | window | 7 chips | hidden | 3-day window, cell 84px |
| staff | 1280 / 1440 | full | none | restored | tools `hidden`, no sheet opener |
| student | 390 | agenda | 7 chips | hidden | 2 cards, cohort chip + `Class Hours` intact |
| student | 768 | window | 7 chips | hidden | 4 cells |
| student | 1440 | full | none | restored | 7 blocks |

Screenshots: `/tmp/mt-resp/shots/t18-*` (both themes, both pages). Changelog entries added to both
pages. **One deviation from spec R7's enumeration, recorded here:** the print button is hidden below
768 (the approved preview marks its own print control `preview-desktop-only`), so the 48px law has
nothing to grow there; every other in-scope control is ≥48×48 under `hasTouch`.

## The regression sweep, and what is red for reasons older than this lane

| spec | result | reading |
|---|---|---|
| `tests/timetable-callers-pin.spec.ts` | 6/6 | the invariant holds |
| `tests/timetable-responsive.spec.ts` | 21/21 | this lane's contract (19 at apply, +2 R5b receipts added in verify) |
| `tests/timetable-lane-layout.spec.ts` | 13/13 | the pure lane planner is untouched |
| `tests/filters-button.spec.ts` | 20 pass + 2 skip (known) | the shared toolbar still behaves |
| `tests/summary-cards-button.spec.ts` | 15/16 | the one red is the documented testing-DB data gap on `/my-replacements` (`statusFilter=none` has no rejected/cancelled rows to filter for 5770), not this lane |
| `tests/e2e/timetable.spec.js` | 6/6 | **was red before** (asserted two elements removed on 2026-11-13, and carried no session at all) — amended, now green |
| `tests/ui-regression.spec.ts` | 19 pass / 9 fail | all nine are stale by earlier decisions: TC-3.x expects a `Subject Code` label that no longer exists anywhere in the modal (removed by `0e43019`), TC-4.x drives the pre-merge replacement flow, TC-6.1 the old history badges. None of the three pages involved carries `.tt-responsive`, and this lane's CSS is scoped to it |
| `tests/class-modal-affordances.spec.ts` | 3/6 | fails inside its own `loginStaff()` — the file sets `storageState: .auth/staff-5770.json` **and** logs in again, so `/login/staff` redirects an authenticated session away and the form never appears. Unmodified by this lane; worth its own fix |

Nothing in the lane's own suite is skipped or `test.fail`-annotated.

## Verify round 2 — three cosmetic/presentational corrections

- **R3's note line is now honest.** A cancelled card read `(Cancelled) ✕ Cancelled`; the word now
  suppresses the legacy status note it duplicates (date notes stay), and `.tt-status` is transparent so
  the two status words present identically. Found by looking at a real cancelled card, not by a test.
- **Two R5b receipts** (3b, 3c) — the week label's 768 re-format, and a Wednesday staying a Wednesday
  across a week change.
- **T0a's checkbox** ticked, and the commit-level proof that the seven shared partials were never
  edited (`git log c8b06ff..HEAD -- <path>` → 0 commits each).

## Post-verify correction — the day chip shows the DATE (user, 2026-11-15)

The user asked why a chip read `Mon 2`: the second line was the class **count**. The approved preview
renders `${d.abbr}<strong>${dayOfMonth}</strong>` — abbreviation small, **day of the month** bold —
and design §4.3 had it inverted (`<strong>Mon</strong>3`). Corrected in `buildStrip()`: `Mon` in the
11px line, `16` in the bold line, count kept in the accessible name (R7). The frozen design line and
the spec's strip requirement both carry the correction with its date, because this is the second thing
the preview would have settled had the design been read against it.

### Same day — the chips are no longer dimmed after today

The user: *"since Tue 17 all are grey, make them all black"*. Design §5 dimmed every day after today to
0.62 opacity, which read as "disabled" rather than "later". The rule is gone, `_isAhead()` and the
`is-ahead` class went with it, and every chip now reads at full strength; today's dot, the holiday red
and the selected outline are the only marks left. Measured after the change: opacity 1 on all seven.

## Post-verify change — the public-holiday panel (user, 2026-11-15)

The user, after the chip correction: *"Public Holiday shud shows this"*, with the approved preview as
the sample. A holiday day used to render its classes as red cards with the next-class row underneath;
it now renders **one panel** — the shipped empty-state shell with a calendar icon,
`Public Holiday · <holidayLabel>`, the date line, and the `⏱ Next class →` hint inside it, with the
venue chip. An empty non-holiday day gets the same shell reading `No classes on <weekday>`. Cards now
only ever render on a day that has classes.

Two defects surfaced while building it, both worth recording because both would have shipped:

1. **The hint could name a day from a different week.** The module's `_currentWeek()` reads
   `window.currentWeek`, which a Livewire week swap can leave a step behind the payload the page's own
   closures point at, so the panel printed `Tue 10 Nov` (week 7) while carrying `data-next-week="8"`.
   The page now wins: `findNextClass()` uses its own closure's `currentWeek` and returns the day's
   `date`/`abbr` with the result, so the label can never be borrowed from the wrong week.
2. **The live pin went red for a clock reason, not a builder reason.** The captures carry the week's
   `Today` markers and the venue grid's "at least 3 working days from today" line, both of which move
   with the calendar; the pin now moves the page to the baseline's own week and canonicalises those
   clock markers before comparing, and says so in the test.

New receipt: T16a #9 (panel, title, date line, hint inside, zero cards, and the jump lands on the day
the button named). 25 spec receipts + 6 pin receipts, green.

One detail settled against the preview after the first look: the date line is on the **holiday** panel
only. An empty weekday already names itself in its title, and the preview's own `emptyMessage()` passes
no text for that case, so `No classes on Sunday` sits alone above the hint.

## Prototype comparison, 2026-11-15 — four items followed, one kept

The user's ruling on the comparison table: **follow the prototype for #1, #4, #5, #6; keep the
current button (#2); the rest TBD.**

| item | what changed |
|---|---|
| #1 | the day heading prints `<Weekday>, <d Mon yyyy>` (`Monday, 16 Nov 2026`) in agenda mode; window mode keeps `Mon ~ Tue` |
| #4 | the sheet's head carries a subtitle `Week 9 · 202605 Semester · 21 Sep 2026 ~ 27 Dec 2026`; the built `#ttSheetRange` line is **gone** from the body, because it repeated the page's own chip |
| #5 | the semester chip reads `202605 Semester` on a phone and the dated range from 768px up (scoped to `.tt-responsive`, so the shared header is untouched on every other page) |
| #6 | the page title is `clamp(20px, 3vw, 22px)` — 20px on a phone instead of 18px |
| #2 | **kept** — the Week summary button keeps its icon **and** the words `Week summary` |

Two things the payload forced, both harmless: the live `MockData.semester.label` is the bare code
(`202605`) while the preview writes `202605 Semester`, so the module adds the word when the data omits
it; and `chipText` is `<code> · <range>`, so the subtitle takes only the range half and never repeats
the code. Receipts: T16a #10 (heading, chip, title at 390 and the wide-width halves at 900) and T16b #3
(body is legend → cards, no range node, subtitle pattern).

Red elsewhere, unchanged and not this lane's: `tests/timetable-wiring.spec.ts` asserts `#weekSubtitle`,
which was removed on 2026-11-13, and its own `loginStaff()` hits the Fortify limiter after the three
setup logins — same stale-test family the lane already recorded for `e2e/timetable.spec.js`.

### #3 answered — the taller panel (option B)

The user chose the prototype's taller panel. `min-height: 83dvh` (with a `vh` fallback) on
`.tt-responsive .tt-sheet`: measured **390×701 at top 143** on an 844-high phone, against the
prototype's 390×698 at 146 — the same panel, within a few pixels. Everything else about the sheet is
unchanged: it still rises from the bottom, still dims the page, still traps Tab, closes on Esc, the ×
or a tap outside, and returns focus to its button.

### #7 answered — the chip's spoken name, merged (option C)

`Monday, 9 Nov 2026, 1 class, Today, Deepavali Holiday (In Lieu)` — the shipped wording's date and
count, the preview's `Today` and holiday NAME. `selected` is gone (aria-pressed carries it), and the
generic "public holiday" became the holiday's real name. The chips also carry `data-count`,
`data-holiday` and `data-today` now, so a receipt reads the facts instead of parsing prose.

### #8 answered — `aria-current="date"` marks today always

The prototype's rule: the mark rides the REAL today whether or not it is selected, while
`aria-pressed` keeps saying which day is chosen. A blind reader can now always find today in the
strip even when looking at another day.

### #9 answered — the status word's glyph is drawn, not typed

`✕` and `⚠` were text characters, so they rendered at different weights and heights across phones. Both
surfaces now carry a 12px inline SVG (card and window cell) or 10px (the desktop block's word), in the
block's colour through `currentColor`. The word itself stays text — `Cancelled` / `Conflict` — so the
accessible name and the receipts never had to change. The desktop twin lives beside `makeEventBlock()`
as `statusIconHtml()`, because that block is built as an HTML string and cannot take a DOM node; the
module's `_statusIcon()` builds the DOM version for the card.

### #10 answered — the desktop block's word is 9px

`.tt-responsive .tt-block-status { font-size: 9px; font-weight: 600 }`, the prototype's size. Measured
after the change: 9px/600, 11px tall. The card's word keeps its 11px tier — only the desktop block
gives way, because that block already carries code, venue and time inside ~90px of height.

### #11 answered — the staff tip names a lecturer only when it is someone else

Option C. The rule lives in `tipLecturerUnlessSelf(e, viewerName)` in ui-common.js (exported), and the
staff blade's `tipLecturerFn` calls it — so a receipt can drive it with synthetic events instead of
needing a fixture where another lecturer teaches one of 5770's classes (the current demo data has
none: every block on this page is the viewer's own, which is exactly why the segment was dropped).
Own classes: `Operating Systems · DFT2(S1)G1 + DSF2(S1)G1 · NORMAL`. Someone else's: the lecturer's
name appears between the cohorts and the status.
