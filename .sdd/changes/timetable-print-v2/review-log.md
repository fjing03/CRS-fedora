# Review Log: timetable-print-v2

## proposal.md Round 1 — 2026-11-12 (Batch 1: proposal only; explore-brief.md is the baseline)

### 🔴 Fixed
- (none — first round)

### 🟡 Addressed
- (none — first round)

### 🔴 Outstanding
- **AC11 is already false — an existing test asserts the stub.** `tests/venue-timetable.spec.ts:69-76` (TC05) clicks `.print-btn` and expects `#toastBar` to contain "Printing is coming soon". Shipping the real action breaks it, yet the files-to-change table lists only `tests/timetable-wiring.spec.ts`. Fix: add that file and reword AC11 to "the suite passes after the stub assertion is replaced".
- **AC6 and AC7 contradict each other, and the printed week/semester has no defined source.** AC7 hides the "progress bar, week controls"; AC6 requires the printout to show "semester, week". Today the week text exists only in `.week-select` (inside `.semester-bar`), `#progressLabel` (inside `.semester-progress`) — both hidden by AC7 — plus `#weekSubtitle` (`my-timetable.blade.php:53`). Semester exists only in `.page-header .semester-chip` (`ui-page-header.blade.php:18,24`). The "Print context" scope row names only cohort/faculty/venue. Fix: state which elements carry semester and week in print (`#weekSubtitle` and `.page-header` stay visible) and/or extend the print line to "semester · week · context", and say how the week part updates on `selectWeek()`.
- **Cohort disabled state has no defined owner and cannot be server-rendered.** `cohort-timetable.blade.php:122` passes `disabled => $pinnedCohortId === null`, but the partial applies `disabled` only to the arrows and the select (`ui-week-nav.blade.php:19-21`). The page then switches cohorts client-side in `onCohortChange()` (`:287-305`) with no re-render. Binding the button to `$disabled` therefore leaves it permanently dead after the user picks a cohort (the common arrival path); not binding it fails AC3. AC3 also ignores clearing the select (`<option value="">Select Cohort</option>`, `:120`). Fix: decide that `onCohortChange()` sets `printBtn.disabled = !selectedCohortId`, and extend AC3 to "disabled whenever no cohort is selected, including after clearing".
- **Responsive rules also fire in print and destroy the grid.** Print-media width is about 1047px for A4 landscape and about 718px for portrait. `@media (max-width: 1024px)` (`theme.css:1672`, plus `cohort-timetable.blade.php:19-21`) and `@media (max-width: 768px)` (`theme.css:3627-3645`) still apply, and the 768px block sets `.timetable tr/td { display: block }` and `.timetable thead { display: none }` — a card stack with no hour header, breaking AC6 and AC9. Fix: require the print block to neutralise the 768px and 1024px grid overrides (or scope them to screen media), and note that landscape A4 still hits the 1024px rules.

### 🟡 Should fix (non-blocking)
- **The real print blockers are not named.** AC8/AC9 depend on overriding `.grid-scroll { overflow-x: scroll }` (`theme.css:996`), `.grid-wrapper { overflow: hidden }` (`:988-995`), `.event-block { position: absolute; inset: 0 }` (`:2525-2530` — `height: auto` cannot expand an absolutely positioned block; it must become static/relative), `.timetable th, td { white-space: nowrap }` (`:1012`), and `.app-container { min-height: 100vh; padding-top: 72px }` (`:876-884`; 100vh risks a blank trailing page). The risk row says only "height auto, overflow visible, wrapped text", which is not achievable as written.
- **Forcing light tokens needs a stated mechanism.** Dark tokens live on `:root` (`theme.css:17`), light on `.light` (`:84`), and the print block is appended at the end of the file (`:4879`). An `html { ... }` selector loses to `:root`; the block must use `:root` inside `@media print` (source-order tiebreak) or toggle `.light` in `printTimetable()` via `beforeprint`/`afterprint`. Duplicating ~60 hex values also sits awkwardly beside the "colours from theme.css tokens only" rule — pick one and say so.
- **AC5 is not verifiable in this repo's setup.** `playwright.config.ts` runs Chromium only, and `emulateMedia({ media: 'print' })` cannot see `@page` size or page breaks; `@page { size: A4 landscape }` is also ignored by Safari and pre-128 Firefox. Restate AC5 as "the print stylesheet requests A4 landscape; Chromium honours it; other browsers fall back to portrait and must stay readable", and mark AC5/AC9 as manual checks (or `page.pdf({ preferCSSPageSize: true })`).
- **"Nowhere else" and "Replacement Arrangement excluded" do not hold in the D10 fallback.** The legacy templates share the partial and four pass `showPrint => true`, including `ui-design-templates/replacement-arrangement-UIdesign-template.blade.php:1008` (`routes/web.php:34+` serves them when `mock_fallback` is on). Fix: scope AC1 to the live Livewire pages (as explore-brief already notes), or set the legacy arrangement template to false.
- **The My Timetable row is non-committal.** "Add/fill the print context line if it is not already covered by the page header" makes the implementer guess. The header already prints `page-title` + `#semesterChip`, and `#weekSubtitle` prints the week — either say "no new element needed" or commit to one shared print line on all four pages.
- **Staleness hook points are unnamed.** The venue page switches venue by full page navigation, so its context cannot go stale; only the cohort page can. Name `onCohortChange()` (`cohort-timetable.blade.php:287`) in the risk table instead of a generic sentence.

### 💡 Minor
- Student My Timetable already prints the cohort chip in its page header (`student-my-timetable.blade.php:18`), so an extra cohort line duplicates it.
- `.print-btn` has no `:disabled` styling (`theme.css:2239-2255`) and still carries `data-tip="Coming soon"`; AC4 covers keyboard reach but not the visible disabled look.
- No AC covers printing an empty grid ("No classes this week", "not assigned to a cohort yet") — state the expected output so tests do not invent one.

## proposal.md Round 2 — 2026-11-12 (Batch 1: proposal only; explore-brief.md is the baseline)

### 🔴 Fixed
- **AC11 was already false — an existing test asserts the stub** → Fixed. AC11 now reads "…after the obsolete 'Printing is coming soon' assertion in `tests/venue-timetable.spec.ts` is replaced", and the file is in both the scope and impact tables. Verified `venue-timetable.spec.ts:69-76` is the only stub assertion in `tests/` (grep over `print-btn|Printing is coming soon|Coming soon|window.print` returns that one file). Scope is now complete.
- **AC6/AC7 contradiction and the missing semester/week source** → Fixed. "Printed identity" now states `.page-header` (title + semester chip + student cohort chip) and `#weekSubtitle` (week and dates) stay visible and `.page-desc` is hidden; AC7 says "page description" and no longer hides the only element carrying the week. Sources verified (`ui-page-header.blade.php:12-30`, `#weekSubtitle` in all four live views). AC6 and AC7 no longer disagree.
- **Cohort disabled state had no owner** → Fixed. Server start via `$pinnedCohortId === null` (the existing `disabled` argument at `cohort-timetable.blade.php:122`, which the shared partial already applies to arrows/select), then `onCohortChange()` on select and clear (`:287-305`, including the programmatic arrival auto-pick at `:281`). AC3 now covers clearing. This solves the "permanently dead after the first pick" failure the round-1 note predicted.
- **Responsive rules fire in print and destroy the grid** → Fixed. The risk table names both breakpoints (A4 landscape ≈1047px hits `max-width: 1024px`; portrait ≈718px hits `max-width: 768px`) and requires the print block to restore `display: table`/`table-row`/`table-cell`, a visible `thead` and normal flow. Confirmed the offending rules (`theme.css:1672`, `:3627-3645`) and that they use no `!important`, so a later `@media print` rule of equal specificity wins without escalating.

### 🟡 Addressed
- Print blockers now named (`.grid-scroll`/`.grid-wrapper` overflow, `.event-block` absolute → static, nowrap, `100vh` blank page).
- Light-token mechanism stated (`:root` inside `@media print`, source-order tiebreak) with `print-color-adjust: exact` and "reuse the light palette, invent nothing".
- AC5 restated as a Chromium request with a portrait fallback, verified manually or via `page.pdf({ preferCSSPageSize: true })`; AC9 gets the same treatment.
- Legacy fallback alignment: legacy arrangement template set to `false`; AC1 scoped to the live pages. Matches the code facts (live `:2250` false, legacy `:1008` true).
- My Timetable and Student My Timetable rows now commit to "no new context element", consistent with the student cohort chip already in the page header.
- Staleness hook named (`onCohortChange()`), and the venue page's full-load switch is credited.
- Empty-grid AC added (AC12) with the empty state kept and the shell hidden — verified `buildTimetableGrid` already sets `tableEl.style.display = 'none'` (`ui-common.js:850-853`), so "as the page already does" is true.
- `.print-btn:disabled` styling and the accessible name are now in the CSS/partial rows.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; fold into the design/specs batch)
- **The 768px card block hides more than grid display.** `theme.css:3703` `.event-block .ev-note { display: none }` and `:3648` `.timetable td.hour-cell:has(.cell-empty) { display: none }` both outrank a plain `.timetable td` / `.event-block` reset, so in the portrait fallback AC5 promises to keep readable, the status note disappears (AC8 requires it) and empty hour cells drop out (columns misalign). `.timetable tbody tr` (`:3652-3659`) also keeps card borders/background once rows are restored. Add these three selectors to the AC8/risk reset list.
- **AC7's "week controls" is narrower than what actually needs hiding.** On Cohort and Venue the faculty/cohort `<select>`s and the venue dropdown sit in the same `.semester-bar` as the week nav, and the new context line is meant to replace them. Say "the whole `.semester-bar` (selects, venue dropdown, week nav, print button) is hidden" so the printed page cannot carry form controls — and so the print button itself is covered by an AC.
- **Venue context line source is unnamed** while the cohort one names `onCohortChange()`. One line in the impact table ("filled from `.venue-dd-label` / the selected `#venueSelect` option when the page script sets the venue") removes the guess.

### 💡 Minor
- The cohort button's *arrival* disabled state is not browser-observable: the staff page auto-selects a cohort on load (`:281`) and immediately enables it. Tests should assert the clear path (already covered by AC3) rather than the initial disabled attribute.
- The print block sets `display: table` — keep it unprefixed (no `!important`), or it will resurrect the empty table shell that `buildTimetableGrid` hides inline, contradicting AC12.

## design.md Round 1 — 2026-11-12 (Batch 2: design only; proposal.md is frozen, explore-brief.md is the baseline)

### 🔴 Fixed
- (none — first design round)

### 🟡 Addressed
- **768px card block hides more than grid display** → Addressed. The print block resets all three: `.event-block .ev-note { display: block }` (beats `theme.css:3703` on source order), `.timetable td.hour-cell:has(.cell-empty) { display: table-cell }` (beats `:3648`), and `.timetable tbody tr { background/border/radius/margin/padding/shadow }` (beats `:3652-3659`).
- **AC7 "week controls" was too narrow** → Addressed. The hide list now names the whole `.semester-bar` (`design.md:143`) plus `.print-btn` (`:155`), so the faculty/cohort/venue selects, week nav and print button cannot print.
- **Stated screen-layout guarantee is verifiable** → Addressed. Every new rule except `.print-context { display: none }` and `.print-btn:disabled` sits inside `@media print`; the block is appended after all breakpoints (`theme.css:4879`), so equal-specificity resets win on source order.
- **Empty-table guard** → Confirmed and kept: `buildTimetableGrid` sets `tableEl.style.display = 'none'` inline (`ui-common.js:850-853`) and hides continuation cells inline (`:1038`); the design deliberately omits `!important` on `display: table`/`table-cell`, so the inline `display:none` still wins. Correct.
- **`.event-block::after`** → Verified it is only the hover pill (`theme.css:3352`, `opacity: 0`), so hiding it in print drops no class content; the venue page's blocks carry `.ev-code/.ev-venue/.ev-time/.ev-note` in flow (`venue-timetable.blade.php:922,964-967`).
- **Venue staleness** → Confirmed: venue switching is `window.location.assign('?venue=' ...)` (`venue-timetable.blade.php:318`), so a server-rendered line cannot go stale.

### 🔴 Outstanding
- **Cohort Timetable has no `#weekSubtitle`.** `design.md:161-163` and the frozen "Printed identity" row assume it does; grep over `resources/views/livewire/` finds it only in `my-timetable.blade.php:53`, `student-my-timetable.blade.php:33`, `venue-timetable.blade.php:174`. Since the design hides `.semester-bar` (the cohort page's only week display), the printed cohort timetable would show no week or dates — AC6 unmet. Fix: append `Week N · <start> ~ <end>` (from `weekData[currentWeek]`) to the cohort `.print-context`, updated in the same place the grid is rebuilt after week changes; state that the cohort page has no `.week-subtitle` and do not add a visible one (that would change screen layout and need a proposal unfreeze).
- **Venue context line reads an array as an object.** `$venue` is `['code','name','short_name','capacity','description','rawRoomType']` (`VenueTimetable.php:706-714,719`; empty-DB path `:157-163`), so `$venue->name ?? $venue->code ?? '—'` silently yields `'—'` and `!empty($venue->code)` is always false — the venue page prints "Venue: —" on every page load, and the "non-empty" test passes on it. Fix: `{{ $venue['name'] ?? '—' }}` with a guarded `({{ $venue['code'] }})` when it differs.
- **Button state and context text are synced only in `onCohortChange()`.** Three paths bypass it: the last-visit restore (`cohort-timetable.blade.php:676-683`) sets `selectedCohortId` and renders the grid without `onCohortChange()`, so the server-rendered `disabled` (`:122`, `$pinnedCohortId === null`) stays on the button — returning staff get a full grid with a dead print button and an empty context line; the student pinned path (`:652-664`) leaves the context empty; `onFacultyChange()` with no taught cohort (`:265-285`) calls `showGuidance()` and leaves the button enabled with no cohort (AC3 inverted). Fix: move both syncs into the two terminal funnels `buildTimetable()` (`:470`) and `showGuidance()` (`:414`), which every arrival/restore/select/clear/faculty-switch path ends in; keep the server `$disabled` for first paint.

## design.md Round 2 — 2026-11-12 (Batch 2: design only; proposal.md is frozen, explore-brief.md is the baseline)

### 🔴 Fixed
- **Cohort Timetable had no `#weekSubtitle`** → Fixed. Verified: `#weekSubtitle` exists only at `my-timetable.blade.php:53`, `student-my-timetable.blade.php:33`, `venue-timetable.blade.php:174`, and in all three it sits OUTSIDE `.semester-bar` (bar closes at `my:50` / `student:30` / `venue:171`), so hiding the bar never hides the week; the cohort page instead carries `Week N · <range>` (fields confirmed on `generateWeekData`, `ui-common.js:85`) in `.print-context`, fed by the same sync funnel. The design also forbids adding a visible `.week-subtitle` to the cohort page, so the frozen screen layout holds. AC6 is now satisfiable on all four pages.
- **Venue context read an array as an object** → Fixed. `$venue` is the array from `renderPayload` (`VenueTimetable.php:706-714`, docblock `:719`) and the blade already consumes it as JSON (`venue-timetable.blade.php:281`); `$venue['name'] ?? '—'` + guarded `({{ $venue['code'] }})` is the correct shape, and `'name' => $venue->room_name ?? $venue->room_code` (`:708`) makes the duplicate-code guard meaningful.
- **Sync lived only in `onCohortChange()`** → Fixed, and the funnels really are terminal. Path audit: student pinned arrival `:652-664` → `buildTimetable`; staff last-visit restore `:676-683` → `buildTimetable`; empty-faculty / no-taught-cohort switch `:283`, `:691`, `:695` → `showGuidance`; default-faculty pick `:685-688` → `onFacultyChange` → auto-pick `:281` → `onCohortChange` → `buildTimetable`; user pick `:304`; clear `:297-300`; week nav `:431-457`; cancel-class re-render `:630`. Nothing reaches a render without passing one of the two funnels, and `currentWeek` is already synced from `weekNav.load()` at `:217` before the first build, so the week label cannot lag.

### 🟡 Addressed
- **768px card block hid more than grid display** → Addressed: `.ev-note { display: block }`, `td.hour-cell:has(.cell-empty) { display: table-cell }` and the full `tbody tr` card-chrome reset are all present and all beat `theme.css:3648,:3652-3659,:3703` on source order.
- **`.semester-bar` / `.print-btn` covered by AC7** → Addressed (`design.md:143,155`), so no form controls can print.
- **Inline sticky on the corner cell** → The design's `!important` is *required*, not defensive: `buildTimetableGrid` writes `cornerTh.style.cssText = 'position: sticky; left: 0; z-index: 40'` (`ui-common.js:864`). Correct as written.
- **`.time-header-col` / `.time-col` width under `table-layout: fixed`** → Sound: the header-row `th.time-header-col` is what fixes the first column width in fixed layout, and the header row is the first row, so 72px propagates.
- **`.booking-hint-line` hidden** → Confirmed real (`venue-timetable.blade.php:80,208`).
- **`.print-btn:disabled` after `:hover`** → Correct: `:disabled` and `:hover` both compute to 0,2,0, so the later rule must win; `.print-btn` is at `theme.css:2239`, `:hover` at `:2252`.
- **A4 landscape premise** → 297mm − 16mm ≈ 1062 CSS px, so Chromium landscape does not hit `max-width: 1024px`; keeping the 1024 resets for the portrait fallback (≈733px, hits both breakpoints) is the right call.
- **`var(--color-bg)`** → Exists in both palettes (`theme.css:18` dark, `:85` light); `.light` (`:84`) is later than `:root` (`:17`) at equal specificity, so a `:root` print override appended at the end wins in **both** themes — the stated mechanism is right.
- **Cohort button starts disabled** → Confirmed `'disabled' => $pinnedCohortId === null` at `cohort-timetable.blade.php:122`, and the partial's `$disabled` defaults to `false` (`ui-week-nav.blade.php:4`), so adding it to the print button is inert on the other three pages.
- **`.legend-hint` hidden but `.legend-ownership-hint` kept** → Correct split: the hint is only "Hover a colour to learn more" (`ui-legend-bar.blade.php:11`), while the thick/thin ownership key (`:37-41`) still prints, which the two-axis borders need.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; fold into the specs/tasks batch)
- **The test plan cannot run in one browser session.** `/student-my-timetable-ui` is `role:student`; `/my-timetable-ui`, `/cohort-timetable` and `/venue-timetable-ui` are `role:lecturer` (`routes/web.php:39,45,51,104`). Test 1's four-route loop and test 3's Student assertions must use a second context via the existing `loginStudent()` (`tests/timetable-wiring.spec.ts:28-34`), or they fail on a role redirect, not on printing.
- **Test 3's `.timetable display: table` assertion is data-dependent.** On the three non-`cellRender` pages `buildTimetableGrid` hides the table with an inline `display:none` when the week is empty (`ui-common.js:850-853`); only the venue page (which passes `cellRender`) always keeps it (`:858`). Pin a week known to have events, or gate the assertion on `#emptyState` being hidden, before asserting the computed display.
- **`.cell-content` and `.cell-empty` stay absolutely positioned in print.** Both are `inset: 0` (`theme.css:2683-2706`), so with `td.hour-cell { height: auto }` a cell has no in-flow child and a day with no events prints as a thin band (row height falls back to the static day label). This hits the Venue page hardest, where most cells are `.cell-content.cell-available`. Add a printed `min-height` on `td.hour-cell` (or reset those two classes to static).
- **The venue context line is empty-string, not null, when no venue is selected.** The empty path passes `'name' => ''` (`VenueTimetable.php:157-159`), so `?? '—'` never fires and the sheet prints "Venue:" with nothing after it. Use an explicit `!== ''` test (or `?:`) so a venue-less arrival reads "Venue: —".
- **The two new helpers' scope is unstated.** `updatePrintState()` / `updatePrintContext()` read page locals (`selectedCohortId`, `facultyData`, `currentWeek`, `weekData` — `cohort-timetable.blade.php:195-205`), so they belong in that page's script, not `ui-common.js`. If `updatePrintContext` lands out of scope, `updatePrintState()` throws on the first `buildTimetable()` and the grid never renders — a silent blank page. Say the file.
- **Frozen-proposal wording must not be re-implemented.** The frozen proposal still names `onCohortChange()` as the sync owner (Impact row + AC3) and AC6 still says "the week subtitle", which the cohort page has no DOM for. Since specs and tasks are next, restate those two to the design's wording (the funnels; "the week line") so `/sdd-verify` cannot fail a correct build on literal wording.

### 💡 Minor
- Place the `updatePrintState()` call after `currentWeek = weekNav.currentWeek` (`cohort-timetable.blade.php:476`) rather than on the literal first line, so the printed week can never lag the grid if a future path drives `weekNav` directly. Today every path sets it first, so this is future-proofing only.
- `.timetable td.time-col.today` (`theme.css:3679`, 0,3,1) outranks the print `background: transparent` (0,2,1), so in the portrait fallback the today column keeps its blue band. Harmless, but the design claims it resets "the exact selectors".
- The cohort week line prints `Week 5 · 5 Oct 2026 ~ 11 Oct 2026` while `#weekSubtitle` prints `Week 5 of 14 · …` (`ui-common.js:296-307`) — two formats across one printed suite. Consider matching.

## specs Round 1 — 2026-11-12 (Batch 3: specs/timetable-print/spec.md; proposal.md + design.md frozen, explore-brief.md is the baseline)

### 🔴 Fixed
- (none — first specs round)

### 🟡 Addressed
- **The two frozen-wording restatements are now explicit.** The header and Terms define "week line" (`#weekSubtitle` on My/Student/Venue; the cohort `.print-context` week part) and "sync funnels" (`buildTimetable()` + `showGuidance()`), and TP-05/TP-07 use them consistently. Resolves the design round-2 items about the cohort page having no `#weekSubtitle` and about `onCohortChange()` not being the only entry point, without touching the frozen proposal.
- **Design round-2 🟡 items folded in.** TP-04 pins `updatePrintState()`/`updatePrintContext()` to the Cohort page script (prevents the silent blank page); TP-10 acknowledges `.cell-content`/`.cell-empty` staying absolute; TP-06 turns the venue empty-name case into a requirement; TP-15 states the `emulateMedia` vs `@page`/page-break boundary, the testing lane and the no-demo-booking rule.
- **Design coverage is complete.** Button (TP-01/02), single `window.print()` (TP-03), palette + `@page` (TP-09), 768/1024 resets incl. `:has(.cell-empty)` and `.ev-note` (TP-10/11), breaks + legend/ownership key (TP-12), empty state (TP-13), hide list incl. the whole `.semester-bar` (TP-08), screen exception list (TP-14). Scenario map maps cleanly onto the requirements.
- **Scope held.** No dependency, no server PDF, no `data-time`, no visible `.week-subtitle` added to Cohort, Replacement Arrangement excluded on the live route with the legacy switch aligned (TP-01).

### 🔴 Outstanding
- **TP-05 contradicts itself and the frozen design on the no-cohort case.** It requires the week line always ("shows … a week line") and then "It is empty when no faculty or cohort is selected." The design's `updatePrintContext()` always pushes the week line when `weekData[currentWeek]` exists, and design's test asserts `.print-context` is non-empty on Cohort. An implementer reading TP-05 literally adds an early return that blanks the line and contradicts both frozen artifacts. Fix: "shows the week line, and the faculty/cohort parts when both are selected; when either is missing only those parts are omitted."

### 🟡 Should fix (non-blocking; all fixable inside this batch)
- **TP-10's cell-collapse fix is under-specified and absent from the design.** The design sets `td.hour-cell { height: auto; padding: 1px }` with no `min-height`, while `.cell-empty`/`.cell-content` are `inset: 0` absolute and the 80px base is overridden to `auto`. On Venue Timetable rows print as thin bands. Pin a value in TP-10 (e.g. `min-height: 40px`) and add the same declaration to the design's `td.hour-cell` rule so the two artifacts cannot disagree.
- **TP-05's week format matches neither artifact nor the live subtitle.** Pick one shape and state it; "same shape as the other pages" is currently unachievable as written.
- **TP-06 states the outcome but not the expression the design gets wrong.** The empty-venues path passes `'name' => ''`, so `$venue['name'] ?? '—'` prints "Venue:" with nothing. TP-06 must give the exact form (`$venue['name'] !== '' ? $venue['name'] : '—'`) so the design snippet is not copy-pasted. Note the `—` case is checked by inspection, not the browser suite.
- **The blank-trailing-page risk has no requirement.** Add the `.app-container` reset (`display: block`, `min-height: 0`, `padding: 0`) to TP-09 or TP-10.
- **The role split is missing from TP-15.** The student page is `role:student`; the other three are lecturer. State that student assertions use `loginStudent()`.
- **No scenario covers the portrait fallback.** Add "Portrait fallback (browser ignores `@page`)" → TP-09, TP-10, TP-11.
- **TP-14's exception list is narrower than the design's.** Add the new `aria-label`/`data-tip` (replacing "Coming soon") to the intentional screen changes.

### 💡 Minor
- `.timetable th, td { white-space: nowrap }` is never reset in print; harmless at 8px, but it constrains the portrait fallback TP-09 promises.
- TP-01 says Venue Timetable's button is "enabled" but does not say what printing with an empty venues table should look like beyond TP-06's `—`.

## specs Round 2 — 2026-11-12 (Batch 3: specs/timetable-print/spec.md; proposal.md + design.md frozen, explore-brief.md is the baseline)

### 🔴 Fixed
- **TP-05 contradicted itself on the no-cohort case** → Fixed. TP-05 now says the week line is always shown when a week is loaded and only the missing faculty/cohort parts are omitted, which is exactly what the frozen `updatePrintContext()` does (it pushes the week whenever `weekData[currentWeek]` exists). The numbers also agree: `weekData.length` is `MockData.semester.weeks` because `generateWeekData()` loops every week unfiltered (`ui-common.js:61-86`), so `Week N of M` matches `#weekSubtitle`'s `N of M` (`:296-307`), and `week.range` is literally `<fmt(mon)> ~ <fmt(sun)>` (`:85`).

### 🟡 Addressed
- **TP-10's cell-collapse fix** → Pinned and correct. Checked in Chromium (Playwright): a `td` with `height:auto; min-height:40px; padding:1px` holding only an `inset:0` absolute child computes 40px, so the Venue page's `.cell-content.cell-available` cells no longer print as thin bands. Matches design's `td.hour-cell` rule. `white-space: normal` is on `.timetable th, td`, which resets `theme.css:1012`.
- **TP-06's venue expression** → Exact shape matches design and the code: `'name' => ''` on the empty-venues path (`VenueTimetable.php:157-159`) is why `!== ''` is required, and `'name' => $v->room_name ?? $v->room_code` (`:170`) is why the parenthesised-code guard is meaningful. `?venue=` is a full render, so "cannot be stale" holds.
- **TP-09's `.app-container` reset** → Present and needed: `theme.css:876-883` sets `min-height: 100vh` and `:1680` re-pads at 768px, so `display:block / min-height:0 / padding:0` covers both the blank trailing page and the portrait fallback.
- **TP-14's intentional screen changes** → Correct and complete: the partial today carries `data-tip="Coming soon"` plus the stub toast (`ui-week-nav.blade.php:35`), and `.print-btn:disabled` with `.print-context { display: none }` are the only design rules outside `@media print`.
- **TP-15's role split** → Confirmed: `/student-my-timetable-ui` is `role:student` (`routes/web.php:47-52`); `/my-timetable-ui`, `/cohort-timetable`, `/venue-timetable-ui` are `role:lecturer` (`:35-46`, `:100-105`); `loginStudent()` exists (`tests/timetable-wiring.spec.ts:28`).
- **Scenario map** → Portrait fallback and the empty-venue row are now present, and each requirement they list is genuinely covered.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; fold into the tasks/code batch)
- **The portrait fallback still bloats the legend.** `@media (max-width: 1024px) { .legend-item { min-height: 40px; min-width: 40px } }` (`theme.css:4058-4069`) carries no media type, so it applies in print too, and A4 portrait (≈718px) hits it. Design's legend reset only sets `font-size: 8px`. Fix: add `min-height: 0; min-width: 0` to the print `.legend-item` rule (a two-property addition to a rule the design already owns), and add TP-12 to the "Portrait fallback" row of the scenario map.
- **TP-05 does not state the part order.** Design prints `Faculty: … · Cohort: … · Week N of M · <range>` while TP-05 described the week line first. No test pins the order; one clause ("joined in that order") removes the mismatch.
- **TP-04 reads two ways.** "runs at the start of `buildTimetable()`" and "placed after `currentWeek` is synced" disagree on placement; the sync is at `cohort-timetable.blade.php:476`, after the `MockData.myTimetable` assignment at `:475`, and the no-cohort branch returns at `:477-480`. Design already says "not before it". Reword to "immediately after `currentWeek = weekNav.currentWeek`, before the `if (!selectedCohortId)` branch".
- **TP-01's legacy sentence is present tense** ("the legacy fallback … passes `showPrint => false`"), but `replacement-arrangement-UIdesign-template.blade.php:1008` currently passes `true`. Say "must pass".
- **TP-13 omits the empty-state icon.** Design hides `.empty-icon` and sizes `.empty-title` / `.empty-text`; the spec only says "readable size".
- **Design round 2's data dependence is still only in design.md.** `.timetable` computed `display` is `table` only when the pinned week has events on the three non-`cellRender` pages (`ui-common.js:850-853`); tasks must pin a week with events or gate on `#emptyState` before asserting it.
- **Two date formats print in one suite.** The cohort line uses `week.range` (`~`, `ui-common.js:85`), `#weekSubtitle` uses `·` between start and end (`:301-305`). Cosmetic but visible when the pages are compared.

## tasks.md Round 1 — 2026-11-12 (Batch 4: tasks.md; proposal.md, design.md and specs/timetable-print/spec.md frozen; explore-brief.md is the baseline)

### 🔴 Fixed
- (none — first tasks round)

### 🟡 Addressed
- **Every spec round-2 🟡 item is now a task.** Pinned-week/`#emptyState` gate (T09), legend `min-height/min-width: 0` (T05), `updatePrintState()` immediately after `currentWeek = weekNav.currentWeek` and before the `if (!selectedCohortId)` branch plus at the start of `showGuidance()` (T06), the venue `!== ''` expression and `$venue`-is-an-array rule (T07), the `.app-container` reset (T03), `.empty-icon`/`.empty-title`/`.empty-text` (T05), the testing lane + "no demo booking / no demo DB" rule (T10), the `emulateMedia` vs `@page`/page-break boundary (T09 → T10).
- **Requirement coverage is complete and two-way.** TP-01/TP-02→T01, TP-03→T02+T09, TP-04/TP-05→T06, TP-06→T07, TP-07/08/09→T03, TP-10/11/12(breaks)→T04, TP-12(legend)/TP-13→T05, TP-14→T01+T05 (+T10 screen suite), TP-15→T09+T10. No TP is orphaned and no task lacks a requirement.
- **The obsolete stub assertion is replaced, not deleted.** T08 stubs `window.print` and counts calls, and keeps the rest of TC05 — the round-1 proposal blocker cannot regress.
- **Scope held.** No `data-time`, no PDF library, no server render, no visible `.week-subtitle` on Cohort; legacy work limited to the single `showPrint` switch (T01), matching the frozen proposal's out-of-scope row.
- **Honest verification boundaries survive into the tasks.** T09 forbids claiming `@page`/page breaks from `emulateMedia()`; T10 does the PDF/portrait/empty-week checks manually and states the `—` venue case as inspection-only (T11).

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; fix inside tasks.md before implementation)
- **T09 as written will trip the Fortify login limiter.** `tests/timetable-wiring.spec.ts` already logs in 3× as staff `4288` (tests at `:37,:65,:86`) + 1× as the student (`:129`), and `FortifyServiceProvider.php:158-164` throttles `login_id|login_type|ip` at `Limit::perMinute(5)`. T09's three separate bullets ("Button presence", "Cohort disabled state", "Print media") each need a staff login → 6 staff logins in one <60 s run → the last one gets a 429 and fails for a non-printing reason. Fix: use `test.use({ storageState: '.auth/staff-5770.json' })` for the lecturer-page print tests (the established pattern in `tests/venue-timetable.spec.ts:7`, refreshed by the `setup` project) or consolidate them into one staff test, and keep exactly one `loginStudent()` test for `/student-my-timetable-ui`.
- **T10's command cannot select the testing lane.** `playwright.config.ts:10` uses `CRS_BASE ?? 'http://localhost:8000'`, and `.env:26` makes `:8000` the dev DB (`class_replacement_fresh`). Setting only `DB_DATABASE=class_replacement_testing` leaves the browser pointed at the dev server, so the "testing lane only" receipt would be false. Fix: name the full invocation — `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030` then `CRS_BASE=http://127.0.0.1:8030 npx playwright test <spec>` (add `CRS_MODE=testing` if any receipt-guarded spec is included).
- **T11 names the wrong changelog first.** `page-changelogs/replacement-arrangement-changelog.md` belongs to the out-of-scope page; the touched pages are My / Cohort / Student / Venue, and Cohort has two candidate files (`cohort-timetable-changelog.md`, `cohort-timetable-ui-changelog.md`). Fix: name `my-timetable-changelog.md`, `student-my-timetable-ui-changelog.md`, `venue-timetable-ui-changelog.md` and the correct cohort file explicitly, and drop the replacement-arrangement file (no existing changelog records the print stub, so there is no precedent pulling it there).
- **T09's "Cohort and Venue `.print-context` is non-empty" cannot fail for Venue.** The server-rendered line always outputs `Venue: ` + something (`—` on the empty path, and possibly on a wrong `$venue['name']`/`??` implementation too), so the exact round-1 design bug ("venue page prints `Venue: —` on every load") passes the gate. Fix: on a page where a venue is loaded, assert the line contains the selected room's name/code (e.g. compare with the `.venue-dd-trigger`/label text); keep the empty-venue `—` case inspection-only as TP-06 says.
- **T04 misses two declarations the 768px block sets on `.event-block`.** `theme.css:3692-3693` sets `min-height: 60px; padding: 10px`; T04 resets position/display/height/overflow but not these, so class blocks stay 60 px tall and padded in the portrait fallback TP-09 promises to keep readable. The design already has `min-height: 0; padding: 2px` — add them to T04.

### 💡 Minor
- T09's print-media assertions run at Playwright's default 1280×720, so the `max-width: 1024px` / `max-width: 768px` resets and the legend `min-height/min-width` reset never apply during the test — those assertions pass trivially. One print-media pass at ~700 px viewport would exercise TP-10/TP-12's portrait path; otherwise state in the task that the resets are manual-check-only.
- T01 should also delete the now-false comment above the button ("Enabled stub — click shows the shared 'coming soon' toast").
- T08 should rename TC05's title ("…shows coming-soon toast") along with removing the assertion.
- The legacy venue template keeps its own stub button (`ui-design-templates/venue-timetable-UI-design-template.blade.php:371`, `data-tip="Coming soon"`); it is unreachable while `APP_MOCK_FALLBACK` is unset and out of the frozen scope — note it so `/sdd-verify` does not count it as a miss.

## tasks.md Round 2 — 2026-11-12 (Batch 4: tasks.md; proposal.md, design.md and specs/timetable-print/spec.md frozen; explore-brief.md is the baseline)

### 🔴 Fixed
- (none — second tasks round)

### 🟡 Addressed
- **Fortify limiter / storage-state pattern** → Correct and verified. `playwright.config.ts:16-25` runs the `setup` project and `tests/auth.setup.ts:30-32` writes `.auth/staff-5770.json`; `test.use({ storageState })` inside a nested describe is the established precedent (`tests/venue-timetable.spec.ts:7`). The file's current logins stay intact (3 staff + 1 student), so the new storage-state staff tests add zero logins — the 429 the round-1 note predicted is gone.
- **T10's invocation can now select the testing lane** → Verified end to end: `scripts/dev-serve.sh:23` documents exactly `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`, `playwright.config.ts:10` reads `CRS_BASE`, and the disposable DB is reproducible (`RealScheduleSeeder::SEMESTER_CODE = '202605'`; `DatabaseSeeder.php:146,154` seeds staff 4288/5770). The lane receipt will now be truthful.
- **T11's changelog names** → All four files exist exactly as written (`my-timetable-changelog.md`, `cohort-timetable-changelog.md`, `student-my-timetable-ui-changelog.md`, `venue-timetable-ui-changelog.md`); `replacement-arrangement-changelog.md` is correctly excluded.
- **T04's `.event-block` reset** → `min-height: 0; padding: 2px` now matches the 768px block it overrides (`theme.css:3692-3693`) and the frozen design, so the portrait fallback no longer prints 60 px padded cards.
- **T01's stale comment / legacy stub** → The deleted comment is real (`ui-week-nav.blade.php:34`) and the untouched legacy button is at `ui-design-templates/venue-timetable-UI-design-template.blade.php:371`; noting it keeps `/sdd-verify` honest without widening the frozen scope.
- **T08's rename** → `venue-timetable.spec.ts:69` still says "coming-soon toast", so the rename is needed and the rest of TC05 survives.
- **Two claims that looked risky but check out.** (a) `.venue-dd-label` exists (`partials/ui-venue-dropdown.blade.php:8`) and `ui-common.js:4283-4305` fills it `CODE — short`, so the room code *is* in the label text — a code-based comparison is implementable. (b) `#emptyState` really is on all four live pages (`partials/ui-empty-state.blade.php:2,11`, included at `my-timetable:57`, `student:37`, `cohort:156`, `venue:178`), so the T09 gate is not vacuous. (c) One `.print-btn` per live page (`my-timetable:49`, `student:29`, `cohort:122`, `venue:170` all `showPrint => true`) and zero on `/replacement-arrangement` (`replacement-arrangement.blade.php:2250` `showPrint => false`).

### 🔴 Outstanding
- **T09's student test cannot log in inside the storage-state describe.** `bootstrap/app.php:38` (`redirectUsersTo(DefaultPage::for(...))`) plus `routes/web.php:14-16` (`->middleware('guest')`) mean a staff-authenticated context that opens `/login/student` is redirected to `/my-timetable-ui` (`app/Support/DefaultPage.php:17`). So with `test.use({ storageState: '.auth/staff-5770.json' })` applied to the whole nested describe, the "Button presence (student)" bullet's `loginStudent()` hits a page with no `input[name="login_id"]` and fails on a timeout — not on printing. Fix: keep the storage state on the lecturer describe only, and give the student bullet its own `test.describe` with `test.use({ storageState: { cookies: [], origins: [] } })` (or call `context.clearCookies()` + `localStorage.clear()` before `loginStudent()`), stating that it uses the guest context.

### 🟡 Remaining (non-blocking; fix inside tasks.md before implementation)
- **T09 never selects a venue, but its Venue assertions demand one.** The live venue page renders from `?venue=<room_code>` (`venue-timetable.blade.php:297-318`) and remembers the last visit (`LastVisit.recall('venuePage', …)`, `:298-303`), so an arrival without the query prints `Venue: —` — which T09 itself forbids as a pass. State it: "open `/venue-timetable-ui?venue=<code>`, taking `<code>` from `.venue-col-item-room[data-code]` (`ui-common.js:4336`) in `.venue-dd-panel`, or from the served list", so the print-media pass is deterministic.
- **The Venue comparison is loose enough to fail on a correct build.** The printed line uses `$venue['name']` (`VenueTimetable.php:170`, the full `room_name`) while `.venue-dd-label` shows `CODE — short_name` (`ui-common.js:4302-4305`), so an equality/"compare with the label text" reading breaks on `B005 — Computer Lab 5` vs `Computer Laboratory 5 (B005)`. Say "assert `.print-context` contains the selected room's **code** (the label's first token)" and drop the equality reading.
- **T10 does not say how the testing lane gets its fixtures.** The new specs assert semester `202605`, staff 4288/5770 and student `25DFT0001`; if `class_replacement_testing` is stale the receipt reads "data missing", not "print broken". Add one clause: re-seed that DB first (`DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed` — allowed there, never on a demo DB), then run the two specs. Also note these two specs are read-only, so `CRS_MODE=testing CRS_ALLOW_SQL=1` are inert here — keep them for lane consistency, but say so.

### 💡 Minor
- T09's `#emptyState` gate and the "pin a week with events" alternative are both fine; the pinned-week variant is the cheaper one, since `#weekSelect` has all 14 weeks (`timetable-wiring.spec.ts:51-52`).
- T09's `~700x900` viewport is below both breakpoints (`1024`/`768`), so TP-10/TP-12's resets are genuinely exercised — worth one comment in the test saying why the size is odd, so a future reader does not "tidy" it back to the default.

## tasks.md Round 3 — 2026-11-12 (Batch 4: tasks.md; proposal.md, design.md and specs/timetable-print/spec.md frozen; explore-brief.md is the baseline)

### 🔴 Fixed
- **T09's student test could not log in inside the storage-state describe** → Fixed. T09 now has two nested describes: the lecturer one carries `test.use({ storageState: '.auth/staff-5770.json' })`, the student one overrides it with `test.use({ storageState: { cookies: [], origins: [] } })` and its single `loginStudent()` bullet is explicitly inside that describe. Verified the premise: `/login/student` is `guest`-only (`routes/web.php:14-16`) and signed-in users are redirected to `App\Support\DefaultPage` (`bootstrap/app.php:38`), so a guest context is required; a nested `test.use` overrides only its own tests (precedent `tests/venue-timetable.spec.ts:7`), and `loginStudent()` exists (`tests/timetable-wiring.spec.ts:28-34`). The three existing staff logins and the student login are untouched, so the file stays inside the 5/min limiter.

### 🟡 Addressed
- **T09 never selected a venue** → Addressed. The print-media pass now opens `/venue-timetable-ui?venue=<code>` with a real code, and a bare `Venue:` / `Venue: —` is explicitly forbidden as a pass.
- **The Venue comparison was loose enough to fail a correct build** → Addressed. It now asserts `.print-context` contains the **code** (the label's first token) instead of equality with `.venue-dd-label` (`CODE — short name`, `ui-common.js:4302-4305`) or the full `$venue['name']`. The code is always present in the printed line: `name = room_name ?? room_code` with a guarded `(code)` when it differs (`VenueTimetable.php:170`).
- **T10 could not select or seed the testing lane** → Addressed. The reseed clause is pinned to `class_replacement_testing` only, then `scripts/dev-serve.sh 8030` + `CRS_BASE`, with "never `--env=testing`" and the honest note that `CRS_MODE=testing CRS_ALLOW_SQL=1` are inert for these read-only specs.

### 🔴 Outstanding
- **T09's print-media test still crosses roles.** "Print media, light and dark" is one test that asserts `#weekSubtitle` visible on My/**Student**/Venue and navigates "On Venue, open …", i.e. it expects to walk four pages in one context — but only the button-presence bullet is assigned to the student describe, so by default it runs in the lecturer describe. A lecturer opening `/student-my-timetable-ui` is not redirected: `CheckRole.php:18-20` calls `abort(403)`, and the 403 page has no `.page-header`, `.legend-bar`, `.timetable` or `#weekSubtitle`, so the test fails at the Student step for a non-printing reason — the same failure mode round 2 removed from the button bullet. Fix: keep My/Cohort/Venue in the lecturer describe and add one guest-student print-media test inside the student describe for `/student-my-timetable-ui` (hide list + `.page-header`/`.legend-bar`/`.timetable` visible + `#weekSubtitle` visible + `table` display), or state explicitly which pages each print-media test owns.

### 🟡 Remaining (non-blocking; fix inside tasks.md before implementation)
- **The room `<code>` is not in the DOM on load.** `.venue-dd-panel` is empty in the blade (`partials/ui-venue-dropdown.blade.php:11`) and `_open()` builds only the Type column (`ui-common.js:4224-4232`); room rows appear only after one parent click in each of the first three columns (`:4135`, `_createRoomItem:4157-4160`). "…or the served list" is also not reachable from the browser: it is a closure `const servedVenues` (`venue-timetable.blade.php:287`), not on `window`. Say "open `.venue-dd-trigger`, click the first parent in columns 1-3, read `data-code`" (same shape as `pickVenue`, `tests/venue-timetable.spec.ts:13-29`) so the venue step is not guesswork.
- **The week gate covers `display: table` but not the visibility line.** T09's gate protects only the computed-`display` assertion, while `.page-header, .legend-bar, .timetable are visible` is unconditional; on My/Cohort/Student an empty week sets `tableEl.style.display = 'none'` inline and shows `#emptyState` (`ui-common.js:849-856`), so a pin on an empty week fails the visibility line first. Widen the gate to both, and say how a week with events is chosen (the pinned/current week for 5770 is not guaranteed to hold a class; scan `#weekSelect` until `.event-block` count is > 0).
- **Duplicate Cohort assertion.** Lines 182 and 188 both assert the Cohort `.print-context` contains `Week`; drop one.

### 💡 Minor
- Consider naming the fixture reason for the odd `700x900` viewport in the test comment (below both breakpoints, so the portrait resets actually apply) — otherwise a future reader "tidies" it back to 1280×720.

## tasks.md Round 4 — 2026-11-12 (Batch 4: tasks.md; proposal.md, design.md and specs/timetable-print/spec.md frozen; explore-brief.md is the baseline)

### 🔴 Fixed
- **T09's print-media test still crossed roles** → Fixed, and the roles are now explicit per describe. The lecturer print-media bullet covers My Timetable, Cohort Timetable and Venue Timetable and states "Do not open `/student-my-timetable-ui` here"; the student page has its own print-media bullet inside the guest student describe with `#weekSubtitle` visible and the same week gate. Verified the premise: `/student-my-timetable-ui` is `role:student` and a lecturer gets `abort(403)` (`app/Http/Middleware/CheckRole.php:18-20`), whose page has none of `.page-header`/`.legend-bar`/`.timetable`/`#weekSubtitle`. No cross-role navigation remains in the plan, so the failure the round-3 note predicted cannot occur. Also confirmed the student page really carries the asserted elements (`student-my-timetable.blade.php:15,29,33,40`).

### 🟡 Addressed
- **The venue room code was not reachable from the DOM as written** → Addressed. The step is now `.venue-dd-trigger` → click the first parent in columns 1–3 → read `data-code` from the first `.venue-col-item-room`, the same idiom as `pickVenue` (`tests/venue-timetable.spec.ts:13-29`); `data-code` is set by `_createRoomItem` (`ui-common.js:4159-4160`), so the step is implementable without guessing.
- **The week gate covered display but not visibility** → Addressed. It now gates both, and says how the week is chosen ("scan `#weekSelect` until the page has at least one `.event-block`"), which removes the empty-week visibility failure on My/Cohort/Student where `buildTimetableGrid` hides the table inline.
- **Duplicate Cohort assertion** → Removed. One remains ("Cohort's `.print-context` contains `Week`"); the other line now asserts only the computed `display` values.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; fixed in this batch after the review)
- **The venue pick must run before print media is emulated.** `.venue-dd-trigger` sits inside `.semester-bar--venue`, which TP-08 hides in print, so a click issued after `emulateMedia({ media: 'print' })` times out on a hidden element. T09 now says to pick the room first, then emulate print.
- **The week-with-events gate has no fallback on Venue.** T09 now prefers B110 (one class every week for 5770) and says to skip block-specific assertions if the room is empty.
- **"call `loginStudent()` exactly once" read two ways.** T09 now says once per student test (two student tests = two logins, still inside the 5/min limiter).
- **The frozen design's older test list loops all four routes in one context.** Tasks correctly splits it; treat `tasks.md` as binding for the role split.

### 💡 Minor
- The odd `700x900` viewport already carries its "portrait fallback" comment.

## Prototype preview — 2026-11-12

- Standalone A4 landscape preview created at
  `ui-prototype/timetable-print-preview.html`.
- It uses a sample week, a page switcher (My Timetable / Cohort Timetable /
  Venue Timetable), a screen-only light/dark toggle and a real `window.print()`
  button.
- Screen capture: `ui-prototype/timetable-print-preview.png`.
- No app files were changed. The preview is awaiting user review before any
  implementation task starts.

## Prototype update — 2026-11-12

User feedback: fill the page better, make the text bigger, and change the
sample timetable.

- Print rows now use 86px and class text is larger (code 9.5px, venue/time
  8.5px, note 7.5px).
- Sample week expanded to 15 classes across Mon–Fri, with normal, replacement,
  pending, conflict and one thin-border "others" case.
- One-page A4 landscape PDF generated for review:
  `ui-prototype/timetable-print-preview.pdf`.
- Updated screen capture: `ui-prototype/timetable-print-preview.png`.
- No app files changed.

## Prototype update 2 — 2026-11-12

User feedback: still too much empty space.

- Print rows reduced to 76px so the full week stays on one A4 landscape page.
- Added a compact print-only week summary strip (classes, teaching hours,
  replacements, pending, conflicts) below the grid. This replaces dead space
  with useful information instead of stretching every row.
- The generated PDF is one page, A4 landscape (verified with `pdfinfo`).
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview.pdf`,
  `ui-prototype/timetable-print-preview.png`.
- No app files changed.

## Prototype update 3 — 2026-11-12

User feedback: remove the summary strip, keep the blank space.

- Removed the week summary strip and its CSS/JS.
- Kept the taller rows and larger text from the previous round.
- Regenerated the PDF: **1 page, A4 landscape** (`pdfinfo` page size
  `841.92 x 594.96 pts`).
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview.pdf`,
  `ui-prototype/timetable-print-preview.png`.
- No app files changed.

## Prototype update 4 — 2026-11-12

User feedback: use the system's time/date format and make the event-block text
larger.

- Class times now use the app's existing `to12h` format: `8:00 AM ~ 10:00 AM`
  (no leading zero, uppercase AM/PM, ` ~ ` separator).
- Dates already match `DateHelper.formatDate`: `5 Oct 2026`.
- Event-block text increased:
  - code 13px screen / 12px print
  - venue and time 11px screen / 10.5px print
  - status note 9.5px screen / 9px print
- The hour header stays 24-hour (`08:00`, `09:00`) because that is what the
  current timetable grid header shows.
- Still one page, A4 landscape (`841.92 x 594.96 pts`).
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview.pdf`,
  `ui-prototype/timetable-print-preview.png`.
- No app files changed.

## Prototype update 5 — 2026-11-12

User feedback: make the hour header 12-hour too, and put the staff/lecturer
name in every event block.

- Hour header now uses `to12h`: `8:00 AM`, `9:00 AM`, `10:00 AM`, ...
- Every event data row carries a `lecturer` field; each block renders
  `Lecturer: <name>` between the class code and the venue.
- Event text remains the larger set from the previous round (code 13/12px,
  lecturer 10/9px, venue+time 11/10.5px, note 9.5/9px).
- Still one page, A4 landscape (`841.92 x 594.96 pts`).
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview.pdf`,
  `ui-prototype/timetable-print-preview.png`.
- No app files changed.

## Prototype update 6 — 2026-11-12

User feedback: staff My Timetable must not show the staff name; other pages
show the name without a "Lecturer:" label.

- My Timetable: event blocks now show code, venue and time only. No lecturer
  line.
- Cohort Timetable and Venue Timetable: event blocks show the lecturer name
  directly (e.g. `Dr Tan`), with no `Lecturer:` prefix.
- The lecturer field remains in the sample data and is used by the Cohort and
  Venue views.
- Header still 12-hour; event text still the larger set.
- Still one page, A4 landscape.
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview.pdf`,
  `ui-prototype/timetable-print-preview.png`.
- No app files changed.

## Prototype update 7 — 2026-11-12

User feedback: make every header format consistent.

- All three pages now use the same header structure:
  - left: page title + one subtitle line
  - right: semester chip + week line
- The page-specific context moved into the subtitle, so there is no extra
  context line on only some pages:
  - My: `Weekly class schedule · Semester 202605`
  - Cohort: `Faculty: FOCS · Cohort: BCS (T) · Semester 202605`
  - Venue: `Venue: B110 (Computer Lab 5) · Semester 202605`
- The week line is now always visible in the same place:
  `Week 5 of 14 · 5 Oct 2026 ~ 11 Oct 2026`.
- Event rules from the previous round stand: My has no lecturer; Cohort/Venue
  show the lecturer name without a label.
- Still one page, A4 landscape.
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview.pdf`,
  `ui-prototype/timetable-print-preview.png`.
- No app files changed.

## Prototype update 8 — 2026-11-12

User feedback: add Student My Timetable to the page picker; remove the repeated
"Semester 202605" text from the subtitle.

- Page picker order: My Timetable, Student My Timetable, Cohort Timetable,
  Venue Timetable.
- Student My Timetable header:
  - title `Student My Timetable`
  - subtitle `Cohort: BCS (T)`
  - same semester chip and week line
- All subtitles no longer repeat "Semester 202605"; the semester stays in the
  chip only:
  - My: `Weekly class schedule`
  - Student: `Cohort: BCS (T)`
  - Cohort: `Faculty: FOCS · Cohort: BCS (T)`
  - Venue: `Venue: B110 (Computer Lab 5)`
- Student event blocks show the lecturer name without a label; no ownership
  hint; same four-item legend as My Timetable.
- My and Student PDFs are each 1 page, A4 landscape.
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview.pdf`,
  `ui-prototype/timetable-print-preview.png`,
  `ui-prototype/timetable-print-preview-student.pdf`,
  `ui-prototype/timetable-print-preview-student.png`.
- No app files changed.

## Prototype update 9 — 2026-11-12

User feedback: mimic the system header/title design, move the Normal-only
toggle into the preview bar, add a centred context title, and remove duplicated
venue/cohort data.

- Screen shell now follows the system:
  - dark top bar with a placeholder crest, nav items, theme toggle and user
    panel (Staff User / Lecturer, or Student User / Student on the student page)
  - page header with 22px title, semester chip and 14px description
  - print-preview toolbar below the page header
- Normal-only toggle moved into the preview bar controls. It hides every
  non-normal event block and every legend item except Normal; ownership hint is
  hidden while the filter is on.
- Added a centred context title in the printed page header:
  - My Timetable: `Kylian Mbappe`
  - Student My Timetable: `Aisyah Rahman`
  - Cohort Timetable: `DSF2(S1)G1`
  - Venue Timetable: `B110 -- Computer Lab 5`
- Removed duplicate info from the print subtitles:
  - Cohort: `Faculty: FOCS` (cohort code is the centre title)
  - Venue: `Venue occupancy` (venue code/name is the centre title)
- Centre title is 20px on screen and 15px in print.
- Header is full-width; centre title is centred on the page.
- Four PDFs regenerated; each is 1 page, A4 landscape.
- Updated files: `ui-prototype/timetable-print-preview.html` plus the
  `-student`, `-cohort`, `-venue` PNG/PDF previews.
- No app files changed.

## Prototype update 10 — 2026-11-12

User feedback: venue `td.slot.empty` cells must be plain white, not green.

- Removed the venue-only `.sheet[data-page="venue"] td.slot.empty` green fill.
- Empty cells now use the same white surface as the other pages.
- The decorative "Available" legend swatch is now white with an outline so it
  matches the cells instead of claiming a green fill.
- Venue PDF regenerated: 1 page, A4 landscape.
- Updated files: `ui-prototype/timetable-print-preview.html`,
  `ui-prototype/timetable-print-preview-venue.pdf`,
  `ui-prototype/timetable-print-preview-venue.png`.
- No app files changed.

## Prototype update 11 — 2026-11-12

User feedback: print icon only, remove the page selector, rename the toggle to
"Normal Class".

- Print control is now a printer icon button with an accessible
  `aria-label="Print / Save PDF"` and a title tip.
- Removed the visible Page label and `<select>` from the preview bar.
  - Navigation is now through the system-style top nav:
    My Timetable, Cohort Timetables, Venue Timetable.
  - Student My Timetable remains reachable at
    `timetable-print-preview.html?page=student`.
- Toggle label is now `Normal Class`.
- Filter behaviour is unchanged: it hides every non-normal block and shows
  only the Normal legend item.
- Venue empty cells remain plain white.
- All four PDFs regenerated; each is 1 page, A4 landscape.
- Updated files: `ui-prototype/timetable-print-preview.html`, the four
  PNG previews and the four PDFs.
- No app files changed.

## Print-flow decision — 2026-11-12

User chose: **keep the browser print dialog only**.

- The real page will call `window.print()` from the printer icon.
- There is no separate print mode, preview screen or Back button.
- Cancel / Esc / closing the browser preview returns to the normal timetable.
- The standalone prototype is only a review artifact; close the tab or use the
  browser Back button to leave it.
- No code change was needed for this decision.

## Prototype update 12 — 2026-11-12

User changed the print-flow decision: add a back button to the prototype only.

- Added an icon-only back control at the left of the system-style top bar
  (`aria-label="Back"`).
- Clicking it goes back in browser history; if there is no history, it returns
  to the default My Timetable preview.
- The real page still uses the browser print dialog only; no separate print
  mode or back button is planned there.
- This is a screen-only change; the printed PDFs are unchanged.
- Updated `ui-prototype/timetable-print-preview.html` and the four PNG
  previews.
- No app files changed.

## Prototype update 13 — 2026-11-12

User asked how the real system goes back and asked to mimic it in the prototype.

Real-system behaviour:
- Only the Replacement Arrangement page has a visible Back button.
- `goBack()` uses `BackNavigator` and the `from` query parameter:
  `my-timetable` → `/my-timetable-ui`, `cohort-timetable` → `/cohort-timetable`,
  `venue-timetable` → `/venue-timetable-ui`, `my-request-history` → `/my-replacements`;
  default is `/my-replacements`.
- If there are unsaved selections, it shows the "Unsaved Changes" confirmation
  before leaving.
- Other timetable pages have no page Back button; users use browser Back or the
  top navigation/logo.

Prototype mimic:
- Back button is now the same pill shape as the real page: 36px, primary
  background, arrow + `Back`, shadow; icon-only at ≤900px.
- It routes via `from`: `my-timetable` → `?page=my`,
  `student-my-timetable` → `?page=student`, `cohort-timetable` → `?page=cohort`,
  `venue-timetable` → `?page=venue`; default is `?page=my` because the prototype
  has no My Replacements page.
- No unsaved-changes confirmation is needed in the prototype.
- Screen-only change; PDFs unchanged. PNG previews updated.
- No app files changed.

## Unfreeze — Prototype delta (2026-11-12)

The standalone prototype changed decision-level scope:
- print header identity (centred context title, shared week line)
- print-only lecturer names on Student/Cohort/Venue
- 12-hour printed header
- plain white venue cells
- a real Normal Class filter (grid + legend + summary + print)

`proposal.md` was extended with a “Prototype delta” section. `design.md`,
`specs/` and `tasks.md` are now stale and must be revised after the proposal is
re-reviewed. No code has been changed.

## proposal.md (Prototype delta) Round 1 — 2026-11-12 (Batch 1b: proposal.md prototype delta; explore-brief.md is the baseline)

### 🔴 Fixed
- (none — first round for the prototype delta)

### 🟡 Addressed
- (none — first round for the prototype delta)

### 🔴 Outstanding
- **AC10 and the out-of-scope row still forbid the screen change the delta requires.** AC10 says "Screen layout and existing timetable behaviour are unchanged" and the out-of-scope table says "Redesigning the screen pages", yet the Normal Class filter needs a visible page-header toggle. Fix: amend AC10 to name the intentional screen changes (filter toggle, print button label/disabled state, hidden print-only elements) and carve out the page-header filter area.
- **The Normal Class filter has no defined behaviour where the code has invariants.** It must state which pages, the mechanism (filtered arrays vs hide-in-place), the empty-result screen and print message, the summary-card behaviour, and the legend rule. Venue's `sumAvailable`/`sumOccupied` are cell-model based, so a hide-in-place filter would make the summary disagree with the grid; My/Student/Cohort's empty branch would show a false "No classes this week".
- **"Venue `td.slot.empty` prints white" names a class that does not exist.** The green element is `.cell-content.cell-available`; no view renders `td.slot*`. Also decide the printed Available legend swatch and whether `.cell-no-fit`, `.cell-too-soon` and `.cell-ph` keep their red status fill.

### 🟡 Remaining (non-blocking; fold into the design/specs rewrite)
- Frozen rows still say My/Student need no new context element, and the print-context row names only cohort/venue; AC13 needs staff/student centre titles. Restate or mark superseded.
- The header's subtitle and centre title have no real element; `.page-desc` is hidden by AC7. Decide a print-only header block or page-header slots.
- `#weekSubtitle` sits outside `.page-header` on three pages and does not exist on Cohort; a print-only header block is the cleanest placement.
- 12-hour hour header needs a mechanism (duplicate spans or `beforeprint` rewrite); the screen must stay 24-hour. Venue's block separator is `' - '`, not `'~'`.
- Lecturer print needs a `cfg` flag, the real lecturer name (not Cohort's "YOU"), and no `'—'` placeholder.
- Filter state lifetime/default is missing from the delta: page-local, survives week changes, not reload; default off.
- "Every printed page" means every route, not repeated `thead` on later sheets.
- Legacy venue template still has a "Coming soon" stub; note it as out of scope.

## proposal.md (Prototype delta) Round 2 — 2026-11-12 (Batch 1b: proposal.md prototype delta; explore-brief.md is the baseline)

### 🔴 Fixed
- **AC10 + out-of-scope forbade the screen change the delta needs** → Fixed. AC10 now names the Normal Class toggle, the print button's label/disabled state and the Venue `' - ' → ' ~ '` separator; the out-of-scope row repeats the carve-out and the delta adds its own "Explicitly out of scope" list, which also parks the legacy venue stub button.
- **The Normal Class filter had no defined behaviour** → Fixed and feasible. The contract states pages, a real page-header toggle, default off, page-local, survives week changes, filtered event arrays (not CSS hide), the Venue cell-renderer flag, summary hidden while filtered, dedicated "No normal classes …" messages, legend status classes, the print note and filter-off restore. Code facts support it: `baseEvent()` stamps `'status' => 'normal'` (`app/Concerns/ResolvesTimetableTimeline.php:494`) and the four other statuses are real, so `status === 'normal'` is a true filter.
- **"Venue `td.slot.empty` prints white" named a class that does not exist** → Fixed. The green element is `.cell-content.cell-available`; the delta names it and lists the other three status cells plus the Available legend.

### 🟡 Addressed
- Print identity now has a real element (`ui-print-header`) instead of "page-header slots".
- 12-hour print gained a mechanism (duplicate spans) with the screen pinned to 24-hour; Venue's `' - '` is named as a deliberate screen change to `' ~ '`.
- Lecturer print got a flag name, the real name, and an explicit "never `YOU`/`—`" rule.
- Week line reconciled to one shape on all four routes, and the cohort page's missing `#weekSubtitle` is answered by the print header.
- Filter default/lifetime, "every route not every sheet", and the legacy venue stub are all now stated.

### 🔴 Outstanding
- **The delta hides `.page-header` in print but leaves the frozen "keep it, and keep `#weekSubtitle`" instruction standing — the sheet prints the week twice.** `#weekSubtitle` sits outside `.page-header` on My/Student/Venue, and nothing hides it, while the new print header has its own week line. Fix: reword the earlier "page header and week subtitle remain visible" text and Printed identity row to say the print-only header replaces the screen identity; add `#weekSubtitle` to AC7's hide list.

### 🟡 Remaining (non-blocking; folded into the delta)
- Subtitle text sources and the print chip's own selector.
- Venue `.cell-no-fit` red, `.cell-too-soon` neutral, `.cell-ph` blank; Available `event-available` class.
- Venue filtered cells: white is a print rule; renderer still needs the unfiltered set.
- AC13 wording "one header at the top of the first sheet".
- Venue hand-rolled renderer named for the lecturer span.
- Guidance/no-venue states win over the filtered-empty message.

## proposal.md (Prototype delta) Round 3 — 2026-11-12 (Batch 1b: proposal.md prototype delta; explore-brief.md is the baseline)

### 🔴 Fixed
- **The sheet printed the week twice** → Fixed. The "What" paragraph now says the print-only header replaces the screen identity and both `.page-header` and `#weekSubtitle` are hidden in print; the Printed identity row uses the same wording; AC7 lists `#weekSubtitle`; the delta's print-header row says the screen `.page-header` is hidden. Verified `#weekSubtitle` sits outside `.page-header` on My/Student/Venue, so hiding both was necessary. The print-only header is now the only week source.

### 🟡 Addressed
- All Round-2 notes are folded: subtitle sources per route, the print chip's own selector, Venue status cells, `event-available`, the unfiltered set for the Venue renderer, AC13 wording, the Venue renderer's lecturer span, and guidance/no-venue precedence over the filtered-empty message.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; fold into the design/specs rewrite)
- Adding `event-available` to the Venue legend must preserve the screen green:
  `.legend-swatch.event-available { background: var(--color-success-container) }`
  on screen; only the white fill + outline is print-scoped. Otherwise AC10’s
  “screen unchanged apart from the named changes” would be violated.
- AC7 says “week controls”; the design resolves this to the whole
  `.semester-bar`. Align the wording during the rewrite.

## design.md Round 3 — 2026-11-12 (Batch 2: rewritten design; proposal.md frozen, explore-brief.md is the baseline)

### 🔴 Fixed
- (none — the rewrite regressed one earlier 🔴; see Outstanding)

### 🟡 Addressed
- **Print header replaces the screen identity (proposal round-3 fix kept).** `.page-header` + `#weekSubtitle` are both hidden and the new `ui-print-header` is the only week source.
- **Cohort week line is real and matches the other routes.** `generateWeekData()` emits `label: Week N` and `range: '5 Oct 2026 ~ 11 Oct 2026'`, and `#weekSubtitle` renders `Week N of M · <range>`, so the print line is exact on all four pages.
- **Lecturer line is implementable.** `e.lecturer` is real on every payload; My passing `false` keeps AC14 honest.
- **Venue white-cell target is the real class.** `.cell-content.cell-available` is the green element; `.cell-no-fit` red is page-local and outranks a plain print rule.
- **Legend change is additive and its screen rule is genuinely required.**
- **Screen-change carve-out matches AC10; print blockers still named.**

### 🔴 Outstanding
- **The Cohort print button is never re-enabled — the round-2 regression is back.** The design binds only the server attribute and never syncs the button in JS. Fix: put `printBtn.disabled = !selectedCohortId` in the two terminal funnels `buildTimetable()` and `showGuidance()`, and move the print context update there too.
- **The filtered-empty state has no working mechanism on Cohort or Venue, and no restore on My/Student.** Cohort's empty branch sets its own title/text and returns before the grid builder, leaving the previous table in the DOM; Venue never shows `#emptyState`; My/Student need the original title/text captured and restored on toggle-off.
- **The 12-hour print header cannot be built from the snippet without breaking AC15 or the screen.** The header emits two labels per group (`.hour-top` and `.hour-bottom`); name all four spans with `.hour-24`/`.hour-12`, hide 12h on screen and 24h in print.
- **Test 5's selector `.event.normal` does not exist.** Use `.event-block.event-normal` plus zero counts for the other status classes.
- **The print week line has no update point on My/Student/Venue.** `#weekSubtitle` updates on every week change, but page `updateWeekSubtitle()` runs once at load. Fill `#printWeekLine` inside each page's `buildTimetable()`.

### 🟡 Remaining (non-blocking; fix in design before specs/tasks)
- Scope `.page-header` flex to `.page-header--with-actions` so other callers stay unchanged.
- Keep the reworded `data-tip`; remove the contradictory “remove data-tip” bullet.
- Name the Venue `cfg.allEvents` key and neutral cell class.
- Venue tests must pin `?venue=B110` before print media.
- Gate `display: table` on `#emptyState` hidden or pin a week with events.
- Add a dark print pass or state AC11 as manual.
- State the print-header parameter contract.
- Add `.ui-print-header`/`.ev-lecturer` screen-hidden rules to the AC10 carve-out.
- Allow `print-context-title` to wrap in portrait.
- Correct the code map: Venue hand-rolls cells, not the header.

## design.md Round 4 — 2026-11-12 (Batch 2: design.md; proposal.md frozen, explore-brief.md is the baseline)

### 🔴 Fixed
- **The Cohort print button was never re-enabled** → Fixed. `updatePrintState()` runs immediately after `currentWeek = weekNav.currentWeek` and before the no-cohort branch in `buildTimetable()`, plus at the start of `showGuidance()`. It sets the button disabled state, centre title, subtitle and week line.
- **Filtered-empty had no workable mechanism on Cohort/Venue and no restore on My/Student** → Fixed. Per-page empty-state mechanisms are specified; My/Student capture and restore the original empty text; Cohort clears the table and hides the shell; Venue hides the shell and restores the table/title when the filter is off or the week/venue changes.
- **The 12-hour header could not be built from the snippet without breaking screen or AC15** → Fixed. All four spans are named (`.hour-top`/`.hour-bottom` × `.hour-24`/`.hour-12`); screen hides 12h, print hides 24h, with `.hour-header` specificity.
- **Test 5 used a selector that does not exist** → Fixed. It uses `.event-block.event-normal` for My/Student/Cohort and the Venue ownership classes separately, with no `event-normal` added to the Venue renderer.
- **The print week line had no update point on My/Student/Venue** → Fixed. It is filled inside each page's `buildTimetable()`, not the load-time subtitle updater.

### 🟡 Addressed
- `.page-header--with-actions` is gated to the four callers.
- `data-tip` wording is consistent.
- Venue `events`/`allEvents` and `.cell-filtered` are named.
- Venue tests pin a room before print media.
- The table-display assertion is gated on the empty state.
- A dark print pass is explicit.
- The print-header parameter contract is stated.
- `.ui-print-header`/`.ev-lecturer` screen-hidden rules are in the carve-out.
- `print-context-title` wraps in portrait.
- The Venue code map now says cells, not header.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; folded into design)
- Venue status-class test wording.
- 12h selector specificity.
- Old `onCohortChange()` sync owner wording.
- Venue empty-title guard.
- Cohort filtered-empty table-shell hide.
- Student `$hasCohort` flag.
- Cohort filtered-empty string aligned to `No normal classes this week`.

## specs (prototype delta) Round 1 — 2026-11-12 (Batch 3: specs/timetable-print/spec.md; proposal.md + design.md frozen, explore-brief.md is the baseline)

### 🔴 Fixed
- (none — first round for the rewritten spec)

### 🟡 Addressed
- **AC13–AC19 are all represented and testable.** AC13 → TP-04/TP-06/TP-07; AC14 → TP-08; AC15 → TP-09; AC16 → TP-17; AC17 → TP-04/TP-10/TP-11/TP-12/TP-14; AC18 → TP-11/TP-13/TP-14; AC19 → TP-13.
- **Every selector and status class checks out.** `.cell-content.cell-available` is the green element; `.cell-available.cell-no-fit` is red and later in document order; `.cell-too-soon` is neutral; `.cell-ph` is blank. Venue normal blocks are `.event-mine`/`.event-others` with no `event-normal`.
- **The week line is exact.** `weekData[w].range` is `5 Oct 2026 ~ 11 Oct 2026`; `weekData.length` is the semester week count.
- **The 12-hour mechanism and screen/print split are unambiguous.**
- **Lecturer scope is right.**
- **The filter contract is complete except two lines.**
- **No scope creep.**

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; fixed in this file)
- TP-14/TP-17 `event-available` placement: item-level `legend-*` classes on `.legend-item`, `event-available` on the swatch, screen green preserved, print white+outline.
- `#printFilterNote` text `Normal Class only` only while the filter is on.
- Legend restore when the filter is off.
- `grid-wrapper`/`grid-scroll` overflow reset in TP-16.
- `.cell-content.cell-filtered` white in print.
- Minor: shared builder used by all four pages including Venue; `.hour-12` added to TP-19; empty venues table wording; empty room name → code alone.

## tasks.md Round 5 — 2026-11-12 (Batch 4: rewritten tasks.md; proposal.md, design.md and specs/timetable-print/spec.md frozen; explore-brief.md is the baseline)

### 🔴 Fixed
- (none — rewrite round; round 4 closed with no outstanding 🔴)

### 🟡 Addressed
- **Round-4 items carried into the rewrite.** Venue room is pinned before print media; “one `loginStudent()` per test”; role split stated.
- **Requirement map is complete and two-way except TP-18.**
- **The named test traps are all handled.**
- **Verified code facts behind the tasks.**
- **Granularity and ordering.**
- **Scope held.**

### 🔴 Outstanding
- **TP-18 has no implementation task (page breaks, legend, empty state).** It requires `thead { display: table-header-group }`, `tbody tr { break-inside: avoid }`, a printed legend with `.legend-hint` hidden, and an empty state with `.empty-icon` hidden and sized `.empty-title`/`.empty-text`. No task names them; `theme.css` has no `break-inside`/`table-header-group` today. Add the rules to T06/T07 (or a short T07b).

### 🟡 Remaining (non-blocking; fixed in this file)
- T13 route ownership per describe.
- T04 depends on T03.
- Header estimate was stale (6h vs 655 min); corrected to ~11h.
- T14 names the four changelogs exactly.
- T11 restores the original empty text on week/selection change too.
- T13 keeps the Venue empty-room fallback.
- Minor: TC05 rename, 700×900 comment, `.summary-section` wording.
- Rounds 1–4 in this log use the old T01–T11 numbering; this file is T01–T14.

## tasks.md Round 6 — 2026-11-12 (Batch 4: tasks.md; proposal.md, design.md and specs/timetable-print/spec.md frozen; explore-brief.md is the baseline)

### 🔴 Fixed
- **TP-18 had no implementation task (page breaks, legend, empty state)** → Fixed. T06 hides `.legend-hint` and owns the printed empty state (`.empty-icon` hidden, `.empty-title`/`.empty-text` sized); T07 adds `thead { display: table-header-group }` and `tbody tr { break-inside: avoid }`. The rules are genuinely new and the print block is appended after the 768px card block, so both win on source order without `!important`. The remaining TP-18 clauses need no new rule.

### 🟡 Addressed
- Round-5 carry-overs all present: route split per describe, T04 depends on T03, estimate matches the task sum (~11h), T14 names the four changelogs, T11 restores empty text on toggle-off and week/selection change, T13 keeps the Venue empty-room fallback and 700×900 comment, `.summary-section` in T06.
- Two-way requirement map is closed: TP-01–TP-20 all map to T01–T14.
- Host facts re-checked: `buildTimetable()` exists on all four pages; legend item classes and swatch class assumptions match the partial.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; folded into tasks)
- Print legend `.legend-item` resets `min-height/min-width` to 0 (T06).
- T13 says “items 1–4 below” instead of forward-reference “bullets”.

## Prototype delta 2 — 2026-11-13 (proposal.md unfrozen for the dense-print addition; design.md, specs and tasks.md re-opened downstream)

### Why

Print rounds R1–R5 in the standalone preview settled the stress case: a very
full week must keep one A4 landscape page without reducing font sizes. The
prototype also gained a page-header help button and dialog, which is prototype
parity only and must not be ported to the real page.

### 🔴 Fixed
- (none — no prior blocking issue; this round adds new scope)

### 🟡 Addressed
- **New decision-level scope needs a proposal entry, not just a design note.** `proposal.md` gained "Prototype delta 2 — 2026-11-13": dense print mode, the post-filter threshold source, the explicit "no screen change" rule, prototype-only help-dialog/sample exclusions, and AC20.
- **The threshold must be computed after filtering.** Counting raw `weekEvents` would switch dense mode on for a week whose non-normal blocks are hidden by the Normal Class filter, printing a sparse week with cramped blocks. The delta now counts only blocks and `.ev-note` lines that will actually print.
- **Dense mode could leak onto the screen.** The prototype applies `.sheet.dense .event` outside `@media print` for preview parity. The delta and TP-19 now state that the real rule lives inside `@media print` only.
- **The prototype help button/dialog could be mistaken for real scope.** They are listed under "Prototype-only", together with `?sample=busy` and the 48-class fixture.
- **No implementation task or acceptance check existed.** T06 owns the dense CSS, T13 owns the PDF/one-page/grid assertions, T14 owns the manual dense export, TP-20 is the requirement, and the scenario map gained a "Very full print week" row.

### 🔴 Outstanding
- (empty — no blocking issues)

### Notes
- No app files, prototypes, tests or databases were changed by this round.
- `design.md`, `specs/timetable-print/spec.md` and `tasks.md` were revised in the same pass; TP-20/TP-21 renumbering is reflected in the scenario map.

## Prototype delta 2 review — 2026-11-13

Reviewer verdict before fixes: **fail / hold**. The delta's shape was sound, but
two decision-level defects would have made a correct-looking page print a false
statement.

### 🔴 Fixed
- **The flat `2px` border would have falsified the ownership legend.** `.timetable .event-mine` is `3px` and `.timetable .event-others` is `0.5px` (`theme.css:2886-2918`), and `ui-legend-bar.blade.php:37-41` prints "thick border (3px) = your classes / thin border (0.5px) = others'". A blanket `border-width: 2px` collapsed both weights, so a dense Cohort/Venue sheet would print a key explaining a distinction the grid no longer drew. Dense mode now leaves the border alone and tightens only padding, gap and child line heights. Proposal, design, TP-20, T07 and AC20 all updated.
- **`line-height: 1.0` on `.event-block` was a no-op.** Every child sets its own (`.ev-code` 1.3, `.ev-venue`/`.ev-time`/`.ev-note` 1.2), so one of the four claimed savings never happened. The design now tightens the children explicitly.
- **AC20 and test 8 were unexecutable.** No shipped fixture reaches 32 blocks in one week (largest cohort 18 sessions, busiest venue 15, staff 4288 has 8), and the only dense fixture is the prototype's banned `?sample=busy`. The verification was rewritten to prove the mechanism (flag set, computed padding, untouched borders, no screen rule) and to keep page count as the existing manual PDF check. Fabricating demo data stays forbidden.

### 🟡 Addressed
- **Dense flag placement.** T07 now says to set `print-dense` before `buildTimetableGrid()`'s empty-week early return (`ui-common.js:849-856`), otherwise an empty week would inherit the previous week's flag.
- **Page-count mechanism.** Counting pages needs a PDF parser and `package.json` ships only `@playwright/test`, so dense page count is manual, consistent with the existing `@page` boundary.
- **Threshold source unchanged and confirmed.** All artifacts agree on `length > 32` plus `pending || replacement > 6` counted after the Normal Class filter; no artifact mentions `.ev-note` counts.
- **Prototype-only fence verified.** `print-dense` appears nowhere under `public/`, and TP-19 permits the class on the screen root with no screen styling.

### 🔴 Outstanding
- (empty — no blocking issues)

### Notes
- No app code, test, database or prototype file was changed by this review round; only the SDD artifacts and this log.
- The prototype's own dense sample still uses a flat `2px` border; that is a review artifact, not real scope, and remains documented as prototype-only.

---

# Rewrite for the 2026-11-16 contract — proposal.md, Round 1 (2026-11-16)

### Why this round exists

The prototype froze its `DESIGN CONTRACT` block on 2026-11-15/16
(`ui-prototype/timetable-print-preview.html:3-205`) — organised by subject, and
authoritative. The lane's papers were written on 12–13 Nov against the older
shape, so `explore-brief.md` and `proposal.md` were rewritten today against the
contract, and the five stale `design.md` statements were verified and recorded
(proposal §6). Nothing was frozen before this round: `sdd.yaml` carries no frozen
flags.

Reviewer: separate read-only pi subprocess
(`commandcode/z-ai/glm-5.3-flash`, tools `read,grep,find,ls`), asked to verify
claims against the real files.

### 🔴 Fixed

- **D5's mechanism would have blanked the screen.** The proposal said pending and
  cancelled "leave the event array before `buildTimetableGrid()` runs" — with one
  build serving both screen and paper, that deletes them from the screen and
  contradicts AC8, the contract's "the SCREEN keeps both exactly as today", the
  standing `cancelled-blocks-stay-visible` law (2026-10-27, re-confirmed
  2026-11-14) and the summary cards, which count pending on purpose. D5 is now a
  **requirement + constraint set**: the screen keeps both, the page computes a
  **print set** from the same array, and every paper-state decision (which blocks
  print, the key's chips, dense mode's count, the printed empty panel) reads that
  set. The contract's "never hide rendered blocks with CSS" wording is answered
  explicitly: its one harm is a sheet claiming "no classes" over a week that only
  looks empty, and the panel/key/density answering from the print set removes it.
  Both wrong options are named and rejected in the paper.
- **Dense mode leaked onto the screen.** The What table said dense "applies on
  screen too, because the sheet must not lie about the paper" — a prototype
  preview-parity argument carried over verbatim onto a page that has no on-screen
  sheet, and a regression of a fix this same log already made on 2026-11-13
  (*Prototype delta 2*, "Dense mode could leak onto the screen"). Dense is now
  **computed** from the print set and **styled inside the print block only**;
  AC13 spells out "no screen rule moves".

### 🟡 Addressed

- **AC1/AC2/AC13 had no instrument.** `@page` size and page count are not
  observable from `emulateMedia`, and the config runs Chromium only. AC1/AC2 now
  carry the round-1 boundary (the stylesheet *requests* A4 landscape; verified by
  a PDF exported with `page.pdf({ preferCSSPageSize: true })` plus a manual
  print), and AC13 says what can be asserted (the flag, computed spacing,
  untouched borders) versus what is seen in the PDF.
- **AC16 misstated its own invariant.** "Cohort/Venue keep their un-flagged block
  shape" was false — they are print targets, so their blocks grow the new hidden
  nodes; that is exactly why D2 re-baselines. Rewritten to: the synthetic capture
  is byte-identical, the two live captures are re-taken with the reason logged,
  the flag-absent proof (`data-tip2` pair, zero `data-tip`) and the
  screen-visible shape survive, and Arrangement is untouched.
- **AC12 did not say which medium each clause binds.** Split: the filter, the PH
  traces, the dropped date, the hidden legend and `No normal classes.` are
  **sheet** clauses; on the **screen** the toggle's own state is the only visible
  change, and the 1440 pass verifies the toggle, not a filtered screen grid.
- **The templates row was factually wrong and hid an inherited change.** There
  are **four** frozen `ui-design-templates/*` blades and **all four** include the
  shared week-nav with `showPrint => true` — including the arrangement template the
  older rounds planned to flip and never did. Editing them stays out of scope;
  the paper now records the inherited effect (a fallback-rendered page would gain a
  working printer) instead of claiming it cannot happen.
- **Cohort's context is client-side and unnamed.** The header row now says
  Cohort's header is filled and kept in step by page JS (its selects change
  without navigation), and what it prints when no cohort is selected.
- **A paper "foot" was implied by `PAGE BREAKS`** while `PREVIEW-ONLY` excludes
  `.paper-foot`. The word is gone; the rule now covers the legend only.
- **The pending-only week had no criterion.** The contract's "a week whose only
  blocks are pending prints the 'no classes' panel" is now part of AC8.

### 💡 Also taken

- The contract's `SCOPE` names three print-only nodes; the proposal carries a
  **fourth** (the block's centre). One paragraph now says why: in the prototype
  the centre is `.ev-main` and the sheet *is* the paper, so on a live page the
  centre has to exist as a node — a mechanism, not a new design.
- `TOOLTIPS` was the one contract subject missing from the What table; added.
- D4 now says the measuring stand-in is laid out **off-screen**, never
  `display: none` (a hidden box has no layout to measure).
- §6's `design.md:36` citation was loose: the "five chips" substance is at
  `:217-218`; `:36` records only the swatch-class fact. Corrected.

### 🔴 Outstanding

- (empty — no blocking issues)

### Notes

- No application file, test, prototype or database was changed by this round;
  only `explore-brief.md`, `proposal.md` and this log.

# Rewrite for the 2026-11-16 contract — proposal.md, Round 2 (2026-11-16)

Reviewer: separate read-only pi subprocess, same mechanism. Verdict:
**passes — no blocking issues**; both round-1 blockers and all seven 🟡 items
verified as landed against the real files, and the reviewer re-checked §6's five
`design.md` citations line by line (all still point at statements that really say
what §6 claims).

### 🔴 Fixed
- (none raised this round)

### 🟡 Addressed (folded in before freezing)
- **Two prototype-worded clauses still lacked a D5-style reconciliation.** The
  `NORMAL CLASS VIEW` row read as if it filtered the screen — the contract says
  "before the grid is built", which is true only where the sheet *is* the screen —
  and `DENSE MODE` did not say why the contract's "applies on SCREEN as well"
  inverts on a live page. Both rows now carry the medium and the reason; the
  dense note points at *Prototype delta 2* (2026-11-13), where this leak was
  fixed once already.
- **D5's decision enumeration omitted day-row suppression.** A Sunday holding only
  a pending or cancelled block would have printed as an empty strip after the
  print rules dropped its blocks — the same class of lie D5 exists to prevent.
  The Sunday rule is now the second of **five** print-set decisions.
- **`design.md` is still the stale 13-Nov paper** while the contract block calls it
  "the full requirement set". §6 now states plainly that its citations name where
  the false statements still live, and that design, specs and tasks must be
  rewritten against this proposal before `/sdd-apply`.
- **"The block's centre" conflated two different nodes.** Only the **new cohorts
  centre** (My, Venue) is print-only; on Student and Cohort the centre is the
  repositioned `.ev-code`, which stays visible on screen. Hiding it would be a
  real screen regression — and D2 re-baselines the pin at the moment of the
  change, so the mistake would be baked in and guarded. The distinction is now
  explicit in the four-screen-changes list.

### 💡 Also taken
- The frozen-template paragraph said the inherited effect "cannot happen", which
  contradicted the sentence before it. It now says the effect cannot be
  *prevented* while those copies keep `showPrint => true`.
- The venue separator change now names its file and line
  (`venue-timetable.blade.php:1715`), like every other fact in the paper — AC14's
  screen guarantee leans on that change list being exhaustive.

### 🔴 Outstanding
- (empty — no blocking issues)

**proposal.md is FROZEN 2026-11-16.** Next batch: `design.md`, rewritten against
this paper and the contract. The reviewer's standing condition is recorded: the
stale 13-Nov `design.md`/`specs/`/`tasks.md` must never reach `/sdd-apply`.

### Notes
- No application file, test, prototype or database was changed by this round;
  only `proposal.md` and this log.

# Rewrite for the 2026-11-16 contract — proposal.md, Round 3 (2026-11-16) — UNFROZEN, decision-level

### Why the freeze was lifted

While drafting `design.md` against the frozen proposal, one of its own acceptance
criteria turned out to be unimplementable by its own chosen mechanism.

- AC12 (frozen) requires the normal view's **sheet** to print the semester's
  normal timetable: `Week 1 ~ 14 (Whole Semester)`, on all four pages.
- D1 (frozen) said the paper is the screen grid restyled — corners/centre as a
  print-time CSS reposition, pending/cancelled dropped by print rules.
- A CSS print pass is **subtraction**. The screen shows **one week**
  (`eventsData[currentWeek]`); the semester's normal timetable is not on it.
  Restyling cannot print blocks that were never built. D5's "print set" was the
  right idea attached to the wrong mechanism: marking blocks to drop works for
  removing pending/cancelled and not at all for adding the semester union.

So the frozen pair (D1 + AC12) could only have been satisfied by quietly breaking
D3/AC12's "the screen is not restructured" — i.e. by rebuilding the screen grid
with the union. That is the screen regression the proposal promises not to make.

**Unfrozen:** `proposal.md` (D1, D2, D4, D5, the four-screen-changes list, the
in-scope rows, AC14/AC16, the risk table). Nothing downstream existed yet —
`design.md` had not been rewritten — so there is no downstream unfreeze.

### The fix, and why it is strictly better

**D1 is now "one builder, two tables":** the paper is its own hidden table
(`.ui-print-sheet`), painted by the **same** `buildTimetableGrid()`. The builder
has taken `tableId`/`headId`/`bodyId`/`emptyId` since it shipped
(`ui-common.js:1034-1037`, verified before deciding), so this is one builder
called twice, **not** a second render path. Consequences, all good:

- The normal view prints the semester union → AC12 is implementable.
- `PAPER SHOWS WHAT RUNS` needs no hiding: the sheet is *built* from the print set.
- The Sunday-row rule, the printed empty panel and dense mode are decided by the
  build rather than by a CSS patch over a rendered grid.
- The 12-hour hour axis and the corners/centre are **rendered**, not re-ordered.
- **The on-screen `#timetable` becomes byte-identical**, so D2 collapses from "a
  re-baseline, honestly" to "**the pin passes unchanged**" — all three receipts,
  no new fixture. Strictly better than what froze.
- D4 simplifies the same way: the print sheet is a real table, so the fit is
  measured on **it**, laid out off-screen at the paper's content width. No
  stand-in, no ratio, no screen-vs-paper geometry gap.

### 🔴 Fixed
- **D1's mechanism could not satisfy AC12** (above). Replaced with the two-table
  design, with the impossibility written down so it cannot be re-proposed as a
  simplification later ("just restyle the grid in print" now has a recorded
  counter-example).
- **D5's mechanism followed D1.** Rewritten as one print set → one build, with the
  rejected alternatives named (filter the screen array = deletes blocks the law
  requires; hide with print CSS = subtraction cannot add the semester union, and
  forces every paper-state decision to be derived twice).

### 🟡 Addressed
- **D2 and AC16 no longer describe a re-baseline.** Both now say the pin must pass
  unchanged, and that any byte movement means a wrong implementation.
- **AC14 now names the on-screen grid** in its guarantee, which is what the
  two-table design actually provides.
- **The risk table's pin row** now reads "there is no re-baseline", and a new row
  covers the one real risk this design introduces — two tables drifting — answered
  by the fact that it is one builder with different inputs.
- **The four-screen-changes list** no longer claims any print node joins the live
  grid; it names the print header and the print sheet, both hidden on screen.

### 🔴 Outstanding
- (empty — no blocking issues)

**proposal.md is RE-FROZEN 2026-11-16 (round 3).** `design.md` is written against
this version. The reviewer's round-2 condition stands and is now easier to meet:
the stale 13-Nov design/specs/tasks must be rewritten before `/sdd-apply`.

### Notes
- No application file, test, prototype or database was changed by this round; only
  `proposal.md` and this log. The one code fact this round leaned on
  (`buildTimetableGrid`'s four id parameters) was read from the file, not assumed.

# Rewrite for the 2026-11-16 contract — proposal.md, Round 4 (2026-11-16) — pre-re-freeze fixes

Reviewer verdict on round 3: **not ready to re-freeze** — the two-table decision
was verified correct against the real builder (`ui-common.js:1034-1037`, and every
other symbol in it is call-local), but the re-freeze had introduced three new
contradictions and the two-table shape broke four things the paper did not
mention. All were folded in.

### 🔴 Fixed
- **D2/AC16's "no byte moves" was falsified by the lane's own screen change #3.**
  The venue `' - '` → `' ~ '` edit lives inside the venue `cellRender`, which
  paints **into `#timetable`**, and the pin's live venue receipt freezes exactly
  that (`withoutToday()` canonicalises today badges and the lead-time date, not
  separators). D2 now scopes the invariant: synthetic and Cohort receipts
  byte-identical, the venue receipt re-captured **once** with the separator as its
  only permitted, diffed-and-reviewed difference. AC16 and the risk table follow.
- **D3's union had no dedup identity, and the payload repeats every class
  fourteen times.** Verified in `ResolvesTimetableTimeline::buildEventsByWeek()`
  (`app/Concerns/ResolvesTimetableTimeline.php:265+`): each week's copy gets its
  own occurrence key, so a naive union prints 14 copies per class and a dedup on
  `eventKey` silently does nothing. D3 now pins the identity as **session + slot**
  (`sessionId`, `di`, `start`, `end`, `code` tie-break), first occurrence wins, and
  states the **collision policy** — two different normal classes sharing a slot
  both print, stacked by the builder's own lane planner, because that is the truth
  about the slot.
- **D4's measurement claim was unachievable with the sheet's rules in `@media
  print`.** Screen media cannot see `@media print`, so an off-screen measurement
  would size against the wrong text metrics with the static fallback masking it.
  D4 now splits the rules: the sheet's structure (layout, sizes, dense, rows,
  columns) lives **outside** any media query scoped to the print surface, and only
  `@page`, chrome hiding, the palette hand-off, breaks and the surface swap stay in
  `@media print`. The in-scope row was corrected to match.

### 🟡 Addressed
- **The print sheet could not be `display: none`** (D4 needs layout) **but must not
  be tabbable.** Screen change #4 now says off-viewport **with `visibility:
  hidden`** — layout preserved and measurable, blocks unfocusable, screen suites'
  `toBeVisible()` unaffected. Otherwise a keyboard user would tab through ~32
  invisible print blocks after the last real control.
- **The 12-hour clause is the same prototype-vs-live inversion.** Under D1 the swap
  belongs to the **print sheet's own header**, rendered 12-hour from the start; the
  live `#tableHead` keeps its 24-hour pair and its pinned bytes. Stated in D1 so
  nobody follows the contract's porting note verbatim into the screen builder.
- **The venue print call would have made AC8 unreachable on Venue.** The builder's
  empty-week branch only runs without `cellRender` (`ui-common.js:1049-1056`), so a
  print set emptied by the filter would have printed a blank table. D1 now pins the
  venue print build to the **default path, no `cellRender`**, ownership via
  `statusClassFn` — which also satisfies AC11 by construction (on paper there are no
  non-class cells, only blocks).
- **Three screen suites scan `.event-block` / `.timetable` page-wide**
  (`venue-status-parity.spec.ts:34`, `venue-note-privacy.spec.ts:27`,
  `venue-db.spec.ts:32`). The print surface now uses its own class vocabulary
  (`.print-block` / `.print-table` / …) and sits **after** `#timetable` in DOM
  order, so no screen suite's `.find()` or `.first()` gains a print block.

### 💡 Also taken
- AC14 now names the venue grid's one permitted delta instead of an ambiguous
  "on-screen `#timetable` included".
- AC12 states that the composite week's `days` input carries no holiday / today /
  Sunday flags, so "every PH trace gone" is a build-input rule, not a CSS patch.
- Recorded for the design batch: the stale paper's print-CSS repositioning
  (`td.hour-cell { height: auto }`, absolute→static block, the 768/1024px resets
  aimed at the *screen* grid) is retired wholesale under two tables.

### 🔴 Outstanding
- (empty — no blocking issues)

**proposal.md is RE-FROZEN 2026-11-16 (round 4).** Fixes were reviewed against the
reviewer's own enumeration; the next review round is spent on `design.md`, which
is the artifact that carries these five mechanisms into code.

### Notes
- No application file, test, prototype or database was changed by this round; only
  `proposal.md` and this log. Both code facts cited above
  (`buildTimetableGrid`'s id parameters and its empty-week branch;
  `buildEventsByWeek()`'s per-week keys) were read from the files, not assumed.

# design.md — Round 1 of the rewrite (2026-11-16)

Batch 2. `proposal.md` frozen (round 4). The 13 Nov `design.md` was replaced
wholesale; the reviewer was asked to check the design against D1–D8, the
acceptance criteria, the real payload shape and the real builder.

### 🔴 Fixed

- **§5.2 claimed the Sunday row would disappear automatically — it would not.**
  `buildTimetableGrid()` iterates `cfg.days` and appends one `<tr>` per day
  unconditionally; rows come from the days, only the blocks come from the events.
  §3.2 had pinned the week-view days as "unchanged" (7 entries), so an empty
  Sunday strip would have printed on every week sheet — failing AC9 and the
  "six rows print, not seven" ruling. §3.2 now owns a `printDays(days, printSet)`
  helper, and it carries the trap that a `filter()` renumbers survivors: **only
  trailing days may be dropped**, so no `di` ever shifts.
- **Cohort's two early returns bypassed `buildPrintSheet()`.** Both
  `showGuidance(); return;` (`cohort-timetable.blade.php:524-526`) and the
  empty-week branch (`:534-547`) exit before the end of `buildTimetable()`, so a
  staff member switching to an empty week would print the **previous** week's or
  cohort's sheet — the exact "sheet that lies about its week" failure D5 exists to
  prevent. Both exits now rebuild the sheet with an empty print set, and Cohort's
  semester source is named (`eventsByCohort[selectedCohortId]`). Venue's absence
  of a semester union is stated so nobody invents one.

### 🟡 Addressed

- **The 1047px constant was wrong** (8 mm = 30.24px at 96 dpi, so the content width
  is 1062, not 1047) — and it broke this design's own "quoted, never re-invented"
  promise. Corrected in the CSS sample and in the derivation comment, with the
  consequence of getting it wrong stated (a short sheet prints a right-hand gap).
- **PH skin vs ownership precedence was unstated.** The venue screen splits its PH
  branch by ownership; reusing that on paper would leave others' blocks off the
  conflict skin. Pinned: on a holiday row the PH skin wins for **every** block, and
  ownership survives only in the border weight.
- **Venue's `$venue` shape traps were dropped.** It is an **array**, and
  `VenueTimetable.php:157-159` passes `'name' => ''`, so `??` never fires. The
  three facts (test with `!== ''`, fall back to the room code, `Select a venue` only
  when both are empty) are recorded so the earlier round's bug is not re-shipped.
- **`printSurname()` would have printed the `—` placeholder.** `baseEvent` sets
  `'lecturer' => '—'` when there is none (`ResolvesTimetableTimeline.php:548`) and
  the contract forbids printing it: a missing or placeholder lecturer now yields an
  **empty corner**.
- **Two data-source gaps closed:** the week line's sources are named (`#weekSubtitle`
  exists on no live page, so it is composed from `currentWeek + 1`,
  `MockData.semester.weeks` and `weekData[currentWeek].range`), and Cohort's
  ownership source is pinned to the name comparison — only the venue payload
  carries `e.mine` (`VenueTimetable.php:383`), so a print shape reaching for it on
  Cohort would paint every block as "others'" and make the key false.
- **The note gate was ambiguous for Cohort.** Each page keeps its own screen gate:
  My/Student ungated, Cohort and Venue ownership-gated, exactly as their screen
  builds are (`cohort-timetable.blade.php:589-593`,
  `venue-timetable.blade.php:1727-1734`).
- **The print button had no home in the design.** §8.1 now specifies the change:
  `window.print()`, the stub toast/tip/comment gone, a real accessible name and a
  shared-pill hover tip, the geometry untouched, `$showPrint` unchanged.

### 💡 Also taken

- `PrintSheet` is introduced as the `ui-common.js` object that owns the toggle
  binding, context, week line, derivations, fit and dense flag (§8.2) — it had been
  used before it was defined.
- The fit's sizes are **paper-only** (there is no on-screen print-shape block, so a
  "screen value" was dead code).
- The venue print cfg names the flags it must **not** receive (`bookableBadge`,
  `tooltipExtra`, `tipLecturerFn`, `responsive`), so the "Bookings open" chip cannot
  ride onto paper.
- The day stack's centring and the normal view's upper-cased day label are folded
  into the sheet-structure rule list.
- Student with no cohort prints `Cohort: —`, the app's own "no data" mark.
- The honorific set for `printSurname()` is pinned in `specs/`, not left as two
  examples.
- A line-number drift (`MockData.currentUser` is at `my-timetable.blade.php:187`,
  not `:213`) is fixed in both `design.md` and `explore-brief.md`.

### 🔴 Outstanding

- (empty — no blocking issues)

### Notes

- No application file, test, prototype or database was changed; only `design.md`,
  one line of `explore-brief.md` and this log.

# design.md (round 2) + specs/ + tasks.md — Round 1 (2026-11-16)

One reviewer round covering the three remaining batches together, because
`specs/` and `tasks.md` are derived from `design.md` and a separate round would
have re-derived the same findings. Verdict: **design round 2 sound** (all seven
round-1 fixes verified against the real files, including the two that were
mechanism gaps), AC1–AC16 and D1–D8 **fully traceable** to PRs, tasks concrete and
batch-ordered. One blocker, three should-fix — all folded in.

### 🔴 Fixed
- **PR-03 had no implementation task.** The venue `' - '` → `' ~ '` change is the
  frozen proposal's screen change #3 and AC14's *only* permitted pin delta, and no
  task edited `venue-timetable.blade.php:1715`. The task list was internally
  incoherent: T20 expected that diff (it would have come back empty — an honest
  "PR-03 never landed") and T21 wrote a changelog entry for a change nobody made.
  Added **T03b** (30m), and it cannot be folded into T12 because T12's whole point
  is that the venue **print** call does not touch the screen `cellRender`.

### 🟡 Addressed
- **PR-13's Sunday rule contradicted the contract's skip condition.** The contract
  skips a Sunday with "no class **and no holiday**"; `printDays()` and PR-13 dropped
  it unless the print set had a block, so a holiday Sunday carrying the `[Hn]` mark
  would have vanished. `printDays()` now keeps a holiday Sunday, and PR-13 gained
  the scenario.
- **Batch A left the print action worse than the toast.** T03 makes the button print
  immediately, but the sheet is not fed until T11 (Batch B) — so between them,
  clicking print yields finished chrome and an empty sheet. Rather than reorder the
  batches, the interim state is now named in T03's entry and in the file header, so
  it is reported at the batch stop instead of surfacing on the user's desk. The same
  note covers the toggle between T15 and T16.
- **T19 broke the file's own ≤2h rule.** Split into **T19a** (week view, all four
  pages) and **T19b** (normal view + dense), which is also the split the design's
  own receipt table already implied.

### 💡 Also taken
- `design.md` §5 now names the mapping between the contract's
  `--ev-main-size` / `--ev-band` variables and this lane's `--print-main-size` /
  `--print-band`, so a porter grepping the prototype for the contract's names finds
  the mapping rather than a missing rule.
- The design quotes `add30min` (the real shared function) where the contract says
  `addHalfHour` — recorded as correct, and explicitly not to be "fixed" to match.
- A line drift on `e.mine`'s home (`VenueTimetable.php:393`, not `:383`) is fixed.

### 🔴 Outstanding
- (empty — no blocking issues)

**design.md, specs/timetable-print/spec.md and tasks.md are FROZEN 2026-11-16.**

### Notes
- No application file, test, prototype or database was changed by this round; only
  `design.md`, `specs/` and `tasks.md`.
- The lane now holds: frozen `proposal.md` (round 4), frozen `design.md`, frozen
  `specs/`, frozen `tasks.md` (25 tasks, ~30h, five batches). **No code has been
  written.** Step 5 of the user's prompt — stop for approval before `/sdd-apply` —
  is where this session ends.

---

## 2026-11-16 — Amendment 1 (the Print modal) — review round 1

Reviewer: `commandcode/z-ai/glm-5.3-flash`, read-only, against the frozen lane + the code.
Verdict **not ready**: 8 × 🟡, 4 × 🔴.

The four blockers, all in the amendment file itself:

1. §3 rewrote **PR-18** (page breaks) instead of **PR-01** (the button) — applied literally
   it would have deleted the page-break requirements while leaving PR-01's
   "calls `window.print()`" standing against A8.
2. PR-04 ("the two hidden print surfaces") and the stale supersession of `proposal.md`
   #2 / AC14 / `design.md` §8.1, §8.2, §3.3 were not named anywhere.
3. **Nothing hid the open dialog on paper** — and under A8 every print happens with it
   open, so the overlay would have printed over the sheet.
4. §4 missed the only receipt that pins the button (`venue-timetable.spec.ts` TC05) and
   mis-attributed a button assertion to `timetable-responsive` T16a-6, which never clicks.

Also taken: A9 now pins PR-11's two titles verbatim; A5 corrects "nothing stops working"
(the venue's switch-on paper is row 3, not row 1); A6 cites frozen PR-16, which already
demanded the union — so the applied venue code, not this amendment, was the deviation;
A12/T30 retarget the switch's ~90 lines of CSS instead of deleting them; A11 became the
`showGuidance()` funnel after the reviewer showed two user-reachable paths never reach
the early returns, and its line numbers were corrected to the applied code.

## 2026-11-16 — Amendment 1 — review round 2

Verdict: **no outstanding blockers.** All four fixes verified against the frozen clauses
and the applied code (including every line number), plus a check that the two round-1
suggestions landed. Four one-clause notes folded in without another round: the overlay
carries both classes, PR-04's pointer is `spec.md:71`, TC05's span starts at `:74`, and
AC14's wording no longer reads as a fifth allowed change.

**Amendment 1 is frozen.** Implementation may begin.
