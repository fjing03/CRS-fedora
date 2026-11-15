# Timetable responsiveness requirements

Frozen `proposal.md` and `design.md` are the authority. Scope: **My Timetable**
(`livewire/my-timetable.blade.php`, phase A) and **Student My Timetable**
(`livewire/student-my-timetable.blade.php`, phase B). **Cohort Timetable, Venue Timetable and
Replacement Arrangement MUST NOT change** — they call the same builder without the opt-in.

## R1 — the opt-in and the blast radius
- Each page MUST opt in with one scope class on its page root (`tt-responsive`) and one call to
  `TimetableResponsive.init(...)`; the chrome partial (`partials/ui-tt-chrome.blade.php`) MUST be the
  only new shared view.
- Every CSS rule this lane adds MUST be scoped under `.tt-responsive` **except** the
  `.badge-cancelled` skin (R4). With the class absent, no rule may apply and the page MUST be exactly
  today's page.
- `buildTimetableGrid()`'s output MUST be **byte-identical** when the `responsive` flag is absent —
  asserted for all three non-opted callers, not named and skipped.

## R2 — breakpoints, modes and state
- **Four** width bands: **≤767** agenda · **768–1023** window of **2** days · **1024–1279** window of **3**
  days · **≥1280** today's full week with **no** strip and no Week summary control.
- The page root MUST carry `data-tt-mode` = `agenda|window|full`, set by the module (three rules key
  on it), and the state MUST clamp: the window starts at `min(selectedDay, 7 - N)` so it never runs
  past Sunday.
- Crossing a breakpoint MUST keep the selected day and re-render in place; a page with stale state
  MUST never show an empty strip.
- The legacy `#timetable` table MUST be hidden in **`agenda`** mode as well as `window` mode, and
  restored only in `full` mode — otherwise a 320px page renders the 22-column table under the agenda
  and the sideways scroll this lane exists to remove comes back.

## R3 — the agenda card (≤767)
- One card per event of the selected day, in time order, as a real `<button>` whose activation runs
  the page's own details handler.
- Three tiers: **primary** = time range + venue code (same size and weight); **secondary** = course
  code + type, smaller and grey; **tertiary** = one right-aligned note line, smallest and grey,
  carrying at most the date note and one status word.
- The subject **name** MUST NOT appear on the card; it MUST remain in `aria-label`, the hover tip and
  the details modal.
- Only **Cancelled** and **Conflict** print a word inside a card, on the note line, in the block's own
  colour. Public Holiday prints none inside a block.
- *(Behaviour-table row 10 in the frozen design reads "one card at a time" — superseded: this
  requirement is the authority, and every card of a clash carries the affordance.)*
- A card in a clash MUST carry `is-overlap` (5px left strip) **and** an overlap line on **every** card
  of that clash, in the two approved forms (`Overlaps 10:30 AM ~ 12:30 PM · BMCS2023 (T)` for one
  other class, `Overlaps N other classes` for several), with the same words appended to `aria-label`.
- A card MUST be sized by its content (no fixed height, no empty time gaps drawn).

## R3b — the window-mode cell (768–1279)
- A window-mode cell MUST carry the **brief §5 tablet sizes**: time + venue 11px/700, code + type
  10px, the note line 9.5px; the venue MAY wrap to a second line when the column is narrow.
- The Cancelled / Conflict word MUST sit on the **note line** (not under the time) below 1280 — the
  same tier as R3 — and the block keeps its own colour.
- The overlap strip and line MUST apply to window-mode cells exactly as they do to cards (every cell
  of a clash, both forms), because two classes sharing time read as unrelated cells there too.
- A cell MUST be the same focusable `<button>` the desktop block is, MUST open the page's details
  modal, and MUST carry the same `aria-label` and `data-tip` as a desktop block (subject name
  included).
- The cell MUST NOT be re-styled by R3's phone sizes: R3 is the ≤767 card only.

## R4 — the one desktop change and the cancelled skin
- At **≥1280** a block MUST print `✕ Cancelled` / `⚠ Conflict` **under the time**, in the block's own
  colour, and nothing else about the desktop block may move (code, venue, time, word, note). This,
  and the tip migration (R5), are the flag's only two effects — both inside the `responsive: true`
  branch, so R1's byte-identical promise holds.
- The details modal's Status row MUST read **`Class Cancelled`**, not `Cancelled`; blocks keep the
  short word (a 60–110px cell cannot carry the long one).
- **The cancelled hover tip stays SHORT (`… · CANCELLED`) — USER RULING, 2026-11-15 ("CANCELLED ."),
  no longer provisional.** Frozen proposal §3.7 and design §6 make the migrated tip byte-identical to today's,
  and today's last segment is `eventStatusLabel()` = `Cancelled`; proposal §10.4's "the hover tip
  takes the long form" contradicts them. The default this lane builds is the frozen pair (short tip),
  and the long form applies to **the modal Status row and the agenda card's spoken status** only.
  Proposal §10.4's "the hover tip takes the long form" is superseded by this ruling and must not be
  implemented.
- The modal wording MUST be **page-scoped**: `openClassModal()` gains a cfg flag (the long word on the
  two timetable pages only), because the modal is shared with Cohort, Venue and Student
  Replacement History and changing their Status badge is not this lane's to take.
- `.badge-cancelled` MUST get the `.badge-reason` token pair (**not** `--color-error`) — the
  documented all-pages exception, in `theme.css`, unscoped.

## R5 — controls: tips, sticky row, next-class hint
- Block hover tips MUST move from the CSS `::after` pair onto the shared engine behind the flag, with
  the rendered sentence **byte-identical** to today's: `data-tip` = the old `data-name` and
  `data-tip2` pieces joined by ` · `, where `data-tip2` = `[tipCtx, tipLect, status].filter(Boolean)`
  joined the same way.
- The week nav MUST gain `Previous week`, `Next week` and `Pick the week to display` — passed from
  each blade through the partial's existing `$prevTip` / `$nextTip` / `$selectTip`; the partial MUST
  NOT be edited.
- Below 1280 the week row plus the day strip MUST stay visible while the page scrolls (`.tt-sticky`,
  `top: var(--top-bar-height)`, no JS measurement). At ≥1280 it MUST stay in flow.
- *(Frozen design §3's strip cells read "one day" / "2-day window" — they describe the *window*, not
  the chip count; this requirement is the authority.)*
- A chip MUST NOT be dimmed for being later in the week: every chip reads at full strength, and only
  the selected outline, the today dot and the public-holiday colour mark one. *(User ruling
  2026-11-15, reversing design §5's ahead-of-today dimming.)*
- Each chip MUST show the day's abbreviation small and its **day of the month** bold (`Mon` /
  `16`), per the approved preview's own renderer; the class count MUST stay in the accessible
  name (R7) and MUST NOT occupy the chip's second line. *(User ruling 2026-11-15 — the frozen
  pair first had this inverted, abbreviation bold with the count beside it.)*
- The day strip MUST show **all seven** days at every width, `aria-pressed` on the selected one and
  `aria-current="date"` only on today while it is selected. In window mode a tap moves the window.
- On an empty day and on a public holiday only, a next-class button MUST appear, jump to that event's
  week and day, then focus the day heading and scroll it into view.

## R5b — three commitments the brief carries that the proposal left implicit
- **The week label re-formats across 768px**: compact above 768 (`Week 9 · 16 Nov ~ 22 Nov`), full
  with the year below it — this is what `populateWeekSelect()`'s 768 re-label already does, so the
  requirement is "do not break it", asserted once.
- **Changing week keeps the same weekday where possible** (a Wednesday stays a Wednesday), clamped at
  the week's edges.
- **Tapping the already-selected day does nothing** — no re-render, no scroll, no focus jump.

## R6 — the Week summary sheet (≤1279 only)
- One button (`Week summary`, `aria-haspopup="dialog"`, `aria-controls`, `aria-expanded`) beside the
  day heading. The tools block ships `hidden` and the module unhides it below 1280, so at ≥1280 the
  receipt asserts **`toBeHidden()`** (not `toHaveCount(0)`) — the node exists, it is not offered.
- The sheet body's order MUST be **legend → the five cards → the built range line**, and the two
  movers MUST preserve it.
  moving the page's own nodes (never copies) with placeholders so their restore is exact.
- Opening MUST lock scroll (`tt-sheet-open`), trap Tab, set the button's `aria-expanded`, and close on
  the ×, the veil and `Escape` — returning focus to the button. `Escape` MUST be a no-op while a
  dialog above is open.
- Below **390px** the cards MUST render in **one** column (the two-column form spills at 320/360/375).
- The two pages MUST stop using `SummarySheet`, and `SummarySheet.init()` MUST bail when the document
  carries the scope class, so the two relocators can never fight over `#summarySection`.
- `beforeprint` MUST restore the moved nodes and `afterprint` MUST relocate them again.

## R7 — accessibility and touch
- The day strip MUST be keyboard operable. Each day's accessible name MUST be
  `<full day name>, <N> classes` with `, selected` appended while it is the selected one, and
  `, public holiday` on a holiday — so the state is testable and not left to the visible chip text.
  The sheet MUST be a labelled `aria-modal` dialog; `prefers-reduced-motion` MUST be respected.
- Under `@media (any-pointer: coarse)` **every** interactive control **inside the scope** MUST be at
  least 48 × 48 CSS px at every width — the day chips, both week arrows, the week select, the Today
  button, the print button, the Week summary button, the sheet's close and footer buttons, the summary
  cards' ⓘ, the next-class button, every agenda card and every window-mode cell, and the page header's
  icons (the header sits inside `.lw-page`). Design §5's enumerated list is **exemplary, not
  exhaustive**; a receipt written only against it would ship a 44px Week summary button.
  The **print button** is included: proposal §5's "does not touch it" is about its function and its
  coming-soon toast, while proposal §4 item 6 is the later, capability-scoped ruling — this is the
  recorded reconciliation. A mouse-only device MUST be unchanged. The shared top bar is outside the
  page root and keeps its shipped sizes.
- No page errors at any width on either page.

## R8 — print
- Printing MUST print the **full week** at paper width, with the legend bar and the summary cards
  visible whatever the on-screen mode, and MUST NOT print the strip, the tools, the agenda, the
  window grid, the hint or the sheet.

## R9 — phase B (Student My Timetable)
- The same chrome and the same flags, with exactly the proposal's §3b deltas: **read-only** (a card
  opens the details modal and nothing else), the cohort chip survives in the header, the summary card
  reads **Class Hours**, and a block's request state (Pending / approved Replacement) shows as colour
  plus its date note with **no new word** (proposal §9d's default).
- Phase B MUST NOT make the page interactive: no cancel wiring, no `modal-footer` section.

## R10 — safety and evidence
- No new dependency, no migration, no backend change, no demo-DB read or write; colours from
  `theme.css` tokens; `partials/ui-week-nav`, `ui-legend-bar`, `ui-summary-bar` and `ui-grid-table`
  MUST NOT be edited.
- Evidence MUST include: a new spec covering the behaviour table at **320 / 360 / 390 / 430 / 768 /
  1024 / 1280 / 1440** in both themes, **keyboard only** arms for R5/R6/R7, `hasTouch` for R7 and a
  print-media emulation for R8; the three-caller markup pin (Cohort / Venue / Arrangement) against a
  captured baseline — the compared root is `#timetable`'s `outerHTML` for a fixed week on the testing
  DB, captured before the change; and the existing geometry specs run with every failure named and
  justified.
- **`tests/summary-cards-button.spec.ts` MUST be amended on purpose, and the list is five tests plus
  the page list**: `FLIP_PAGES` holds My Timetable (and Venue), so that entry goes; T11a test 3
  ("opening moves nothing…"), both T11b close-receipts, the ⓘ-above-the-sheet receipt, the 320/390/768
  card-overflow loop's My Timetable arm and the print receipt (whose afterprint re-relocation cannot
  hold once `SummarySheet` bails) all drive `/my-timetable-ui` + `#summaryButton` and MUST be named and
  justified in the receipt's own comment. The student page's receipts are untouched.
- **`tests/e2e/timetable.spec.js` is RED BEFORE this lane and stays red unless amended**:
  `helpers/page-check.js` asserts that at least one of `#semesterProgress` / `#weekSubtitle` is
  **visible**, and both were removed on 2026-11-13 (zero hits in either live blade). It MUST be added
  to the amended list — the assertion removed or inverted with a dated comment — and MUST NOT be
  "fixed" by resurrecting that DOM.
