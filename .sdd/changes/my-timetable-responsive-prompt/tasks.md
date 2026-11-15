# Tasks — `my-timetable-responsive-prompt`

**Batch 4.** `proposal.md`, `design.md` and `specs/timetable-responsive/spec.md` are frozen. Every
task is ≤ 2 hours and names its requirement. Order matters: A builds the shell, B–D the three
surfaces, E wires the staff page, F the student page, G proves it, H records it.

**Rules for every task**

- Do not edit `partials/ui-week-nav`, `ui-legend-bar`, `ui-summary-bar`, `ui-grid-table`, the class
  details modal or the cancel modal.
- Every CSS rule lands under `.tt-responsive` — except the `.badge-cancelled` skin (T2).
- `buildTimetableGrid()` output must stay byte-identical when the `responsive` flag is absent (T0a
  captures the baseline **before** anything else); the pin
  test (T14) is the guard, and it must pass before any page looks right.
- Tests run against the testing DB only:
  `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test <file> --reporter=line`.
- After a Blade/JS/CSS edit, restart the dev server before looking: `pkill -f "[a]rtisan serve"`, then
  `rm -f storage/framework/views/*.php`, then serve again. Never `pkill -9 php`.

---

## Batch A — the shell (and the two baselines the rest is measured against, first)

- [ ] **T0a — the baselines, before any page changes.** (i) Capture `#timetable`'s `outerHTML` for a
  fixed week on the testing DB from **Cohort, Venue and Replacement Arrangement** and save it as the
  pin's baseline (T15 compares against it); (ii) capture the two timetable pages' ≥1280 geometry with
  the existing net — `tests/ui-regression.spec.ts` (17 receipts) and `tests/timetable-lane-layout.spec.ts`
  (13) — and record the run. Nothing else may be edited first: with the builder untouched, this is the
  only moment a *before* means anything. *Satisfies R1, R10.*

- [x] **T1 — the chrome partial (`resources/views/partials/ui-tt-chrome.blade.php`, new).** The sticky
  wrapper for the caller's week row, `#ttDayStrip`, `#ttTools` (`hidden`) with the day heading + count +
  `#ttSummaryButton`, `#ttWindowGrid` (`hidden`), `#ttAgenda`, `#ttNextHint` (`hidden`) and the
  `#ttSheet` overlay/dialog/body (`wire:ignore`) exactly as design §4.1. *Satisfies R1, R2, R5, R6.*

- [x] **T2 — the CSS section (`public/css/theme.css`).** One new section, every rule scoped
  `.tt-responsive` (design §5): sticky row and strip, day chips (44×52 base, pressed/today/holiday),
  tools and heading, the agenda card's three tiers with the `.is-overlap` strip and `.tt-overlap`
  line, the window grid's template, the sheet, the two print rules (hide the six blocks, un-hide the
  legend and summary, restore the table), `@media (any-pointer: coarse)` inside the scope with the
  full control list of spec R7, and **the one unscoped rule** `.badge-cancelled` in
  `.badge-reason`'s token pair. *Satisfies R1, R3, R3b, R5, R6, R7, R8.*

- [x] **T3 — `TimetableResponsive`: skeleton, breakpoints, state (`public/js/ui-common.js`).** The
  object shell; three `matchMedia` queries with `change` → `sync()`; `_state { width, day, mode }`;
  `sync()` that clamps the day, **sets `data-tt-mode` on the page root**, hides `#timetable` in
  `agenda`/`window` and restores it in `full`, and calls the builders of B–D. No surface built yet.
  *Satisfies R2.*

## Batch B — the phone

- [x] **T4 — the day strip.** `buildStrip()` + `setDay(i)`: seven chips at every width, `aria-pressed`
  on the selected one, `aria-current="date"` only on today-selected, the dot, the holiday class, the
  accessible name of spec R7 (`Monday, 3 classes[, selected][, public holiday]`), and the "tap the
  selected day is a no-op" rule (spec R5b). In window mode a tap moves the window. *Satisfies R2, R5,
  R5b, R7.*

- [x] **T5 — the agenda card.** `buildAgenda()` per design §4.2: three tiers, the name off the card,
  the status word on the note line (Cancelled / Conflict only, block colour), the overlap strip + line
  in both forms on every card of a clash, the same words in `aria-label`, `event-pending` /
  `event-replacement` borders, and the card wired to the page's `onEventClick`. *Satisfies R3, R7.*

- [x] **T6 — the next-class hint.** On an empty day and on a public holiday only: the button, the jump
  (week → day), the focus on `#ttDayHeading` and the scroll into view; hidden otherwise.
  **The module needs a cross-week source** — `events()` is the *current* week only, so `init()` gains
  one more knob and the page passes it (declarative append to design §6's cfg list, recorded in the
  review log): `findNextClass(fromWeek, fromDay)` → `{ week, day } | null`, implemented by the page over
  its own `eventsData` (the same payload, no second data shape). *Satisfies R5.*

## Batch C — the tablet

- [x] **T7 — the transposed window grid.** `#ttWindowGrid` rendered from `events()`/`days()`:
  `50px repeat(N, 1fr)` × `62px repeat(22, 44px)`, `N` = 2 or 3, window `start = min(day, 7 - N)`,
  each event placed by day index and half-hour span; the legacy table hidden in that mode. *Satisfies
  R2, R3b.*

- [x] **T8 — the window cell.** Design §4.2's tiers at the brief's tablet sizes (time/venue 11px, code
  10px, note 9.5px, venue may wrap), the status word on the note line, the overlap strip + line, the
  same focusable button / details modal / `aria-label` / `data-tip` as a desktop block. *Satisfies R3b.*

## Batch D — the Week summary sheet

- [x] **T9 — the `SummarySheet` gate first, then the sheet.** One line in `SummarySheet.init()`:
  bail when the document carries `.tt-responsive` — **apply this before the mover below**, or the two
  relocators fight for `#summarySection` while you are debugging it. Then `openSheet()` / `closeSheet()`:
  move the legend bar and `#summarySection` with placeholders, build `#ttSheetRange` from
  `MockData.semester.chipText`, order legend → cards → range, lock the **new** body class
  `tt-sheet-open` (not `summary-sheet-open`), trap Tab, Esc (capture, suspended above a dialog), veil
  and × close, focus back to `#ttSummaryButton`; `beforeprint` restores, `afterprint` relocates. The
  one-column-below-390px rule for the cards is one CSS rule in T2's section.
  *Satisfies R6, R8.*

## Batch E — the staff page (phase A)

- [x] **T11 — My Timetable wiring.** Root gets `tt-responsive` **and `data-tt-page="staff"`**; the week-nav include moves inside the
  chrome partial's sticky wrapper; the partial is included after the legend/summary includes;
  `buildTimetableGrid({ …, responsive: true })`; `'summaryButton' => true` removed from the header
  include; the week-nav tips passed through `$prevTip` / `$nextTip` / `$selectTip`;
  `TimetableResponsive.init({ events, days, onEventClick, pageKey: 'staff' })` at the end of
  `buildTimetable()`. *Satisfies R1, R5.*

- [x] **T12 — the builder's two flag-only changes.** Inside the `responsive: true` branch only:
  (a) the tip migration — one `data-tip` = `[data-name, [tipCtx, tipLect, status].filter(Boolean).join(' · ')].filter(Boolean).join(' · ')`,
  and (b) the desktop status word under the time (`✕ Cancelled` / `⚠ Conflict`, block colour). Nothing
  else in the builder. *Satisfies R4, R5.*

- [x] **T13 — `Class Cancelled` in the details modal, page-scoped.** `openClassModal()` gains a cfg
  flag for the long word; only the two timetable pages pass it. *Satisfies R4.*

## Batch F — the student page (phase B)

- [x] **T14 — Student My Timetable wiring.** The same include with `readonly => true`,
  `data-tt-page="student"`, `responsive: true` on its builder call, **`'summaryButton' => true`
  removed** (without it the header icon survives, stays visible at ≤768 and calls the gated-off
  `SummarySheet` — a control that does nothing), no cancel wiring, the cohort chip untouched,
  `Class Hours` untouched, and the request state as colour + date note with no new word. *Satisfies R6,
  R9.*

## Batch G — the tests

- [ ] **T15 — the pin first (`tests/timetable-callers-pin.spec.ts`, new).** Capture `#timetable`'s
  `outerHTML` for a fixed week on the testing DB and compare it across the change for **Cohort, Venue
  and Arrangement**. Run it before any page opt-in so it fails loudly if the builder moved. *Satisfies
  R1, R10.*

- [ ] **T16a — the responsive spec, phone half (`tests/timetable-responsive.spec.ts`, new).** R2, R3,
  R5, R5b, R7 and R8 at 320 / 360 / 390 / 430, both themes, keyboard-only arms, print emulation, both
  page roots. *Satisfies R2, R3, R5, R5b, R7, R8.*

- [ ] **T16b — the responsive spec, tablet + sheet half (same file).** R2, R3b and R6 at 768 / 1024,
  the transposed grid's window and its clamp, the sheet's contents, order, focus/Esc/lock, the
  one-column rule below 390, and the `toBeHidden()` tools receipt at 1280/1440. *Satisfies R2, R3b, R6.*

- [ ] **T16c — the responsive spec, touch + desktop half (same file).** The 48px law under `hasTouch`
  at 390 and 1440 (every control of spec R7's list), and the ≥1280 arm: no strip, tools hidden, the
  full week, the desktop word under the time. *Satisfies R4, R7.*

- [ ] **T17 — the three amended files.** `tests/summary-cards-button.spec.ts` (the FLIP_PAGES entry and
  the **five** receipts of spec R10, each with a comment naming this lane);
  `tests/e2e/timetable.spec.js` — **both page entries** pass
  `['#semesterProgress', '#weekSubtitle']` to the generic `helpers/page-check.js` helper, so the
  assertion is removed or inverted with a dated comment (never resurrect the removed DOM); and any geometry receipt that genuinely fails, named and justified
  in the receipt's own comment. *Satisfies R10.*

## Batch H — evidence

- [ ] **T18 — measure and record.** Before/after geometry for the two pages at ≥1280 (the ~97%
  promise), the four phone widths, both themes, the touch arm; screenshots into `/tmp/mt-resp/shots/`;
  the three-caller receipt; zero page errors. Record the numbers in the apply log. *Satisfies R10.*

- [ ] **T19 — changelogs.** One dated entry each in `page-changelogs/my-timetable-changelog.md` and
  `page-changelogs/student-my-timetable-ui-changelog.md`. *(Evidence of the change, per proposal §8 —
  not a spec requirement.)*
