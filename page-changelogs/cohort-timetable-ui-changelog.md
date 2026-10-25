# Changelog — Cohort Timetable UI

## [2026-10-25] Hover voice: own classes read ME, status CAPS (the user's directive B + D)

The block tooltip now reads `name · lecturer|ME · status` — the viewer's
OWN blocks show **ME** instead of the staff name (before: `Software
Engineering · Surayaini Binti Basri · Normal`; after: `Software
Engineering · ME · NORMAL`); others' blocks keep the real name. Mechanism:
the shared `buildTimetableGrid` gained an opt-in `cfg.tipLecturerFn(e)`
hook; this page passes the SAME ownership rule its block border uses
(`e.lecturer === MockData.currentUser.name` — `currentUser` injected from
`auth()->user()->name` at :185), so voice and border can never disagree.
Part D: the status word renders CAPS via `.toUpperCase()` in the builder
— it applies to every page sharing the builder, i.e. student-my-timetable
(keeps the staff name, just CAPS status). Amended 2026-10-25 after a live
probe: replacement-arrangement builds the same builder but feeds it
`events: []` (its slot-picker grid), so it renders NO status tooltips at
all — earlier wording here wrongly implied otherwise. The frozen 2-segment shape is
preserved: `ME · NORMAL` still matches `^[^·]+ · [^·]+$` (pages-parity
C3). Legend chips stay title-case (frozen legend contract). Mirror kept in
sync: `CohortTimetable-UI-design-template.blade.php`.
Receipt: live probe as staff 4288 on RSD3(S1)G1 — own Blockchain blocks
render `ME · NORMAL`, others' keep their names; `timetable-wiring`'s
cohort-render + `cohort-venue-replace-now` green (they flaked once under
86-test/3-worker load, pass solo); pint + phpstan clean. BASELINE-PROVEN
pre-existing red (stash-run, unrelated to this change): `pages-parity:29`
+ `venue-db:25/62` + `venue-timetable TC34/TC35` — the venue/cohort
LEGEND specs (the blade now passes a 5th "Replacement" chip vs the
specs' frozen set + multi-line legend text vs `toHaveText`) and
`#sumAvailable` 0 vs 72 — flagged for the user.

## [2026-10-10] Frozen-design parity: 3-item legend, My-Teaching cards, ownership fix (SDD all-pages-design-parity)

- **Legend** 5-item colour swatches → frozen 3 block-class chips (Normal / Pending / Conflict / Public Holiday) + ownership hint.
- **Cards** → frozen 5: Total Classes / Teaching Hours / **My Teaching Classes** / **My Teaching Hours** / Conflicts (Replacements + Pending cards retired; `sumMyClasses`/`sumMyHours` filled by the shared `computeSummary`).
- **Ownership axis fixed (bug)**: the blade tested `e.isMine`, a field no payload ever set — every block rendered as "others'". Now uses the frozen idiom (`lecturer === MockData.currentUser.name`), incl. `replacementNoteFn` ownership checks.
- **PH-day exception**: viewer's own holiday classes render loud `event-conflict` ("your class won't run"); others' stay hairline red.
- **Tooltip bug fixed**: dropped the stale `tooltipExtra` (post-merge builder already appends the lecturer) — tooltips were rendering "Dr. X · Dr. X · Scheduled", now `lecturer · status`.
- **Glue**: print button on week-nav; guide text matches frozen; summary filters cancelled; frozen week persistence key.
- Verified: phpunit 130/130 · phpstan 0 · lint clean · pages-parity 3/3 (legend, `event-mine`, 2-segment tooltip) · records-intact.

---


## [2026-10-09] Page becomes lecturer/PL-only (SDD student-nav-remove-cohort)

`/cohort-timetable-ui` is no longer on the student side: the student nav whitelist drops **Cohort Timetables** (students keep My Timetable + Request History), and the route mw tightens `['auth']` → `['auth', 'role:lecturer']` — students hitting the URL directly get 403 instead of their pinned cohort view. FR 1.2 is unaffected: students still see their own cohort's weekly schedule via **Student My Timetable** (pinned cohort chip). Lecturer and PL nav keep the Cohort Timetables item; the route `nav` key is unchanged so their active-state highlighting still works. The component's student-pinning branch becomes unreachable (left in place, defensive).

---

## [2026-08-31] Wired to real data via Livewire (SDD wire-backend-into-refactored-ui, Slice A)

Page served by `App\Livewire\CohortTimetable`; faculty/cohort selects are JS-owned (`wire:ignore` block) and push `cohortId` to the server (`$wire.set`) — the component re-queries all events per cohort. Students are pinned to their own cohort (FR 1.2): selects preselected + disabled, scoping enforced server-side.

### Mock → real mappings
| Mock | Real |
|---|---|
| `MockData.cohortTimetable.faculties/cohorts` | `faculties → programmes → cohorts` (display code `PROG{Y}(S{S})G{G}`) |
| `rsd3g2Base` + flag overrides | real `ClassSession` × `session_cohorts` + `class_exceptions` + request overlays |
| mine-vs-others colouring (`MockData.currentUser.name` compare) | `lecturer_id === viewer` (`isMine` flag) — same classes `event-mine`/`event-others`/`-pending` |

### Notes
- Empty state copy unchanged; "No classes scheduled" appears for weeks with all sessions cancelled.
- Others' Pending uses the generic pending class in Slice A (mock's `event-others-pending` grey retained via statusClassFn).


---

## [2026-08-31] Sync upstream/fjing UI refactor (merge fd8c403)

Merged `upstream/fjing` (267 commits `cfc1bb1..f44cc5c`) into `fedora-backend`. Policy: **theirs-first for UI**; backend-only files kept local. Verification: `migrate:fresh --seed` green, PHPStan 0, PHPUnit 94/94, smoke 12/12 routes 200.

### Files Changed
- `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` — conflict → **theirs**
- Shared: `theme.css`, `ui-common.js`, `mock-data.js`, `layouts/ui-template`, `partials/ui-nav-bar`, legend/summary/today/empty-state/grid-table partials — **theirs**

---

## [2026-08-16] Today button now persists week selection

The "Today" button now saves the current week to `cohortTimetableState` (via a local `goToday()` wrapper that calls `weekNav.jumpToToday()` + `saveState()`), consistent with arrow/dropdown navigation. Previously, clicking Today would jump the view but not persist — a page refresh would revert to the old week.

### Files Changed

#### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| `goToday()` | Added | New wrapper function that calls `weekNav.jumpToToday()`, syncs `currentWeek`, and calls `saveState()` — same pattern as `prevWeek()`/`nextWeek()`/`selectWeek()`. |
| Today button listener | Updated | Replaced `weekNav.initTodayBtn()` with a single listener that calls `goToday()`, ensuring `cohortTimetableState` is persisted. |

---

## [2026-08-15] Event hover tooltip shows lecturer instead of venue

The event-block tooltip previously showed `name · venue` (venue already on the block). It now shows the **lecturer** (`name · Dr. Christopher Lazarus`) — useful since the cohort is the page context and the lecturer isn't on the block. Uses the new `tooltipExtra(event)` option on the shared `buildTimetableGrid`.

### Files Changed

#### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| `buildTimetable()` | Updated | Passes `tooltipExtra` returning `event.lecturer`. |

#### `public/js/ui-common.js` + `public/css/theme.css`

Shared: `buildTimetableGrid` now sets `dataset.tip2` from `cfg.tooltipExtra`; `.event-block::after` reads `attr(data-tip2)`.

---

## [2026-08-15] Class detail modal redesign: unified detail sheet

The Class Detail modal now uses the shared `DetailModal` "Detail Sheet" via the shared `openClassModal()` (single flat group, definition rows). Cohort extra field preserved. No page-specific changes needed.

---

## [2026-08-15] Page-specific summary card descriptions

Added page-specific `description` text to each summary card (Total Classes / Teaching Hours / Replacements / Pending / Conflicts) instead of relying on the shared generic descriptions. (`CohortTimetable-UI-design-template.blade.php` summary bar.)

---

## [2026-08-13] Phase 3 UX Enhancement: Collapsible Guide Block

### Summary

Added an expandable guide block with page-specific workflow instructions.

### Files Changed

#### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-13 | Lines 41-48 | Added | `@include('partials.ui-guide-block')` with 4 workflow tips |

---

## Files Changed

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-30 | — | Created file | Full Blade template extending `layouts.ui-template` with `activeNav = 'cohort-timetables'`. 1323 lines covering CSS, content, and JS |

#### CSS (`@section('page-styles')`)
| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-30 | — | Semester bar | `.semester-bar` (flex, surface bg, border, gap 8px), `.week-arrow` (28×28, transparent → hover bg, disabled state), `.week-select` (secondary-container bg, custom SVG chevron, min-width 160px), `.session-text` (flex 1, weight 600) |
| 2026-07-30 | — | Faculty/cohort selects | `.semester-bar select:not(.week-select)` — same styling as week-select but larger padding for standalone selects |
| 2026-07-30 | — | Time column | `.time-col` (130px, sticky left, z-15, text-align center), `.today`/`.holiday-col`/`.sunday-col` variants matching MyTimetable, `.hour-header` with `.hour-top`/`.hour-bottom` spans |
| 2026-07-30 | — | Hour cells | `.hour-cell` (80px height, cursor default), `.sunday-slot`/`.holiday-slot` (error-container bg), `.cell-empty` (surface bg) |
| 2026-07-30 | — | Event blocks | `.event-block` (flex col, centered, hover brightness, box-shadow), `.event-normal` (secondary-container), `.event-replacement` (primary-container), `.event-pending` (tertiary-container), `.event-public-holiday` (error-container), `.ev-code`/`.ev-venue`/`.ev-time`/`.ev-note` text styles |
| 2026-07-30 | — | Status badges | `.badge-normal` (secondary bg), `.badge-replacement` (amber/gold), `.badge-pending` (tertiary), `.badge-conflict` (error) — for use in modal and event labels |
| 2026-07-30 | — | Legend bar | `.legend-bar` (50px, primary-container bg, flex row, 28px gap, border-top), `.legend-item`/`.legend-swatch` (18×18, border, border-radius) |
| 2026-07-30 | — | Summary card colors | `.card-total` (border 2px primary + primary-container bg), values: total=secondary, replacement=primary, pending=tertiary, conflict=error |
| 2026-07-30 | — | Modal | `.modal-overlay` (fixed, blur, centered), `.modal` (surface bg, border-radius 16px, slide-in animation), header (title + status badge + close), body (`.modal-field` label/value rows), `.btn-close-modal` |
| 2026-07-30 | — | Search toolbar | `.search-wrapper` (relative for icon), `.search-input` (padding 8-12, 36px left for icon, 420px width), `.filter-select` (secondary-container bg, weight 500) |
| 2026-07-30 | — | Responsive | `@media (max-width: 1024px)` — scrollable grid, stacked toolbar. `@media (max-width: 768px)` — full-width search, hidden user info |

#### Content (`@section('content')`)
| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-30 | — | Page header | Title "Cohort Timetable" + desc "View the weekly timetable for any cohort across all faculties." |
| 2026-07-30 | — | Semester bar | Faculty select → cohort select → prev arrow → week select → next arrow → session text showing "202605 Semester (Monday, 15-Jun-2026 ~ Sunday, 20-Sep-2026)" |
| 2026-07-30 | — | Toolbar | Search wrapper with SVG magnifying glass icon + text input + status filter select (All/Normal/Replacement/Pending/Conflict) + result count |
| 2026-07-30 | — | Grid | `.grid-wrapper` → `.grid-scroll` → `table.timetable` with `#tableHead` + `#tableBody` — populated dynamically by JS |
| 2026-07-30 | — | Summary bar | `@include('partials.ui-summary-bar')` with 4 cards: Total Classes, Replacements, Pending, Conflicts |
| 2026-07-30 | — | Legend bar | 4 legend items with colored swatches using CSS variables (`--color-secondary`/`--color-primary`/`--color-tertiary`/`--color-error`) |
| 2026-07-30 | — | Empty state | Hidden by default (`display:none`), shown via JS. Calendar SVG icon, dynamic title + message |
| 2026-07-30 | — | Event modal | Overlay + modal card: header (course code + status badge + close ×), body fields (Course, Name, Lecturer, Venue, Cohort, Time, Status badge, Remarks), footer with Close button |

#### JavaScript (`@section('page-scripts')`)
| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-30 | — | Helpers | `hours` array (22 slots 08:00–18:30), `add30min(t)`, `dayNames` (MONDAY–SUNDAY) |
| 2026-07-30 | — | weekData | Dynamic 14-week generator (15-Jun → 20-Sep 2026), each with 7-day array (`abbr`, `date`, `sunday`, `today` at week 11 Mon, `holiday` at week 11 Thu) |
| 2026-07-30 | — | facultyData | 4 faculties: FOCS (5 cohorts: RSD2, RSD3 G1/G2, DSF2, DFT2), FOL (4: RSDL2 G1/G2, DLF2, DLM2), FOD (4: RSD2/3 Design, DDM2, DFM2), FCCI (4: RBU2, DMC2, DIT2, DCM2) — 17 cohorts total |
| 2026-07-30 | — | Mock events | ~40 events across 10+ cohorts, indexed by `allEvents[cohortId][weekIdx]`. Includes normal (green), replacement (amber), and pending (blue) events with multi-hour spans. Lecturers, venues, and codes for realism |
| 2026-07-30 | — | populateWeeks() | Fills week select from weekData array |
| 2026-07-30 | — | populateFaculties() | Fills faculty select from facultyData |
| 2026-07-30 | — | onFacultyChange() | Reads faculty select → populates cohort select with matching cohorts; on reset → shows empty state, clears grid, disables arrows |
| 2026-07-30 | — | onCohortChange() | Reads cohort select → resets to week 0 → calls buildTimetable(); on reset → clears grid, shows empty state |
| 2026-07-30 | — | prevWeek()/nextWeek() | Bounds-checked navigation, updates week select UI |
| 2026-07-30 | — | selectWeek(index) | Sets current week from dropdown, rebuilds grid |
| 2026-07-30 | — | buildTimetable() | Main renderer: reads `allEvents[selectedCohortId][currentWeek]`, builds header row + 7 day rows, uses slot-map for multi-hour spanning events, applies CSS classes per status, calls updateSummaries + updateWeekArrows |
| 2026-07-30 | — | applyFilters() | Filters visible events by search query (code/name/lecturer/venue) AND status filter. Rebuilds grid with filtered events, shows "No matching events" when empty |
| 2026-07-30 | — | updateSummaries() | Counts total/replacement/pending/conflict events, updates summary card text, calls updateWeekArrows |
| 2026-07-30 | — | openModal()/closeModal() | View-only modal: fills course/name/lecturer/venue/cohort/time (with day+date+12h range)/status badge/remarks. Status badge uses `.badge-*` classes. Escape key closes |
| 2026-07-30 | — | Init | DOMContentLoaded: populate weeks + faculties, show empty state "Select a cohort", arrows disabled |

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` — UI refinements (search removal, legend reorder, faculty-first flow)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-01 | `@section('page-styles')` | Removed search/filter CSS | Deleted `.search-wrapper`, `.search-icon`, `.search-input`, `.search-input::placeholder`, `.filter-select`, `.filter-select option`, `html.dark .filter-select` — ~50 lines |
| 2026-08-01 | `@section('page-styles')` | Added disabled select CSS | `.semester-bar select:disabled, select:disabled:hover` — opacity 0.5, `cursor: not-allowed`, surface-variant bg — clear visual cue that cohort/week controls are locked until a faculty is chosen |
| 2026-08-01 | `@section('page-styles')` | Responsive cleanup | Removed `.toolbar-left`, `.search-input`, `.filter-select`, `.search-wrapper` rules from both `@media` breakpoints; `.toolbar-right` left-aligned at 1024px |
| 2026-08-01 | `@section('content')` | Removed search bar & status filter | Toolbar-left (search wrapper + filter select) deleted entirely; toolbar now contains only the result-count span in `.toolbar-right` |
| 2026-08-01 | `@section('content')` | Legend above summary bar | Moved `.legend-bar` block to directly after grid-wrapper, before the `@include('partials.ui-summary-bar')` — legend now sits above the summary cards |
| 2026-08-01 | `@section('content')` | Faculty-first disabled states | `#cohortSelect` and `#weekSelect` now render with `disabled` attribute in HTML; prev/next arrows also start disabled — user cannot pick a cohort or week before choosing a faculty |
| 2026-08-01 | `@section('content')` | Guidance empty state | Default empty state text changed from "No classes scheduled" → title "Select a faculty first" + "Choose a faculty, then pick a cohort to view its weekly timetable." |
| 2026-08-01 | `@section('page-scripts')` | Removed applyFilters() | Entire `applyFilters()` (~140 lines) deleted along with its search/status-filter wiring (`oninput="applyFilters()"`, `onchange="applyFilters()"`) — no filtering on this page |
| 2026-08-01 | `@section('page-scripts')` | onFacultyChange() | Now also disables `#weekSelect`; when a faculty is picked, enables `#cohortSelect` only, keeps week locked, shows guidance "Pick a cohort from {faculty name}…". On reset → re-disables cohort + week, restores initial guidance |
| 2026-08-01 | `@section('page-scripts')` | onCohortChange() | Now enables `#weekSelect` only after a cohort is selected; on reset → disables week, shows guidance |
| 2026-08-01 | `@section('page-scripts')` | buildTimetable() | No-cohort branch also disables `#weekSelect` before showing guidance |
| 2026-08-01 | `@section('page-scripts')` | Init | DOMContentLoaded now explicitly disables `#cohortSelect` + `#weekSelect` and shows "Select a faculty first" guidance |

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` — semester chip, today column, maximized FOCS mock data, 2 faculties

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-01 | `@section('page-styles')` | Added `.semester-chip` | Replaced `.session-text` CSS with `.semester-chip` — inline-block pill (secondary-container bg, 12px, weight 600, radius 20px) for the semester info in the page header |
| 2026-08-01 | `@section('content')` | Semester info moved to header | Removed `202605 Semester (Monday, 15-Jun-2026 ~ Sunday, 20-Sep-2026)` `.session-text` span from semester bar; added `<span class="semester-chip">202605 Semester · 15-Jun-2026 ~ 20-Sep-2026</span>` under the page title |
| 2026-08-01 | `@section('page-scripts')` | Fixed today/holiday week bug | weekData generator used `w === 11` (which maps to **Week 19** since label = w+8) — corrected to `w === 3` (Week 11, index 2). Today column + public holiday now render on the correct week |
| 2026-08-01 | `@section('page-scripts')` | Default week → Week 11 | `currentWeek = 2` (was 0) and `onCohortChange()` selects week index 2 — matches MyTimetable, so the today column is immediately visible on cohort select |
| 2026-08-01 | `@section('page-scripts')` | facultyData reduced to 2 | Removed FOL (4 cohorts) and FOD (4 cohorts); kept FOCS (5 cohorts) + FCCI (reduced to 2 cohorts: DMC2, DIT2) = 7 cohorts total (was 17) |
| 2026-08-01 | `@section('page-scripts')` | Maximized FOCS mock data | All 5 FOCS cohorts now have 3 full weeks of data (weeks 0/1/2): RSD2 (6/6/7 events, 6 courses), RSD3 G1 (5/5/5), RSD3 G2 (5/5/5, shifted days), DSF2 (5/5/5), DFT2 (5/5/5). ~80 events total with replacement + pending statuses spread across weeks |
| 2026-08-01 | `@section('page-scripts')` | FCCI DMC2 data | Kept week 0 (3 events incl. pending) + added week 2 (3 events incl. replacement + pending) so the default week shows data; DIT2 left empty to demo the "No classes scheduled" state |

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` — new "Teaching Hours" summary card (staff)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-01 | `@section('page-styles')` | Added `.summary-card.card-hours` | Neutral styling distinct from count cards: `--color-on-surface` value, `--color-surface-variant` background, dashed `--color-outline-strong` border |
| 2026-08-01 | `@section('content')` | New summary card | Added `['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Teaching Hours']` after Total Classes — summary bar now 5 cards (grid already supports 5 columns) |
| 2026-08-01 | `@section('page-scripts')` | updateSummaries() | Computes `hours += (e.end - e.start + 1) * 0.5` per event (slots are 0.5h each); displays integer or 1-decimal (e.g. RSD2 Week 11 = 14h). All 4 reset branches now zero `sumHours` too |

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` — RSD3 G2 maximized to full semester (weeks 1–14, +45%)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-01 | `@section('page-scripts')` | RSD3 (S1) G2 — 5 → 7 courses (+45%) | Added `rsd3g2Base` array: existing 5 courses + BMIT7074 Software Testing (T, Tue 8-11, A106) + BMIT7075 Mobile Application Development (L, Thu 12-15, A106) |
| 2026-08-01 | `@section('page-scripts')` | RSD3 (S1) G2 — weeks 1–3 → weeks 1–14 | Replaced 3 explicit week blocks with a `for` loop generating all 14 dropdown weeks (indexes 0–13 = Week 9…22) → every week now shows 7 events / 14h |
| 2026-08-01 | `@section('page-scripts')` | RSD3 (S1) G2 — status flags | `rsd3g2Flags` map sets realistic replacement/pending statuses per week (e.g. W1 Capstone+Testing replaced, W2 IT Ethics pending, W4/W9/W13 pending, W3/W7/W11 replacement) — visible on non-holiday days; Thu events on the Week 11 public-holiday render as conflicts by design |
| 2026-08-02 | `@section('page-scripts')` | Centralised mock data (Task 9) | Migrated facultyData, allEvents, and weekData to `public/js/mock-data.js` — page now reads from `window.MockData.cohortTimetable`. RSD3 G2 reconstructed from shared data via `rsd3g2Base`/`rsd3g2Flags`. Holiday rule now reads `MockData.holidays` instead of hardcoded map. Semester chip text reads `MockData.semester.chipText`. |
| 2026-08-02 | `@section('page-scripts')` lines 660-683 | Fix RSD3 G2 reconstruction after centralisation | Three bugs: (1) Only flagged weeks created → week 0 (default) had no events. Fix: loop all 14 weeks first. (2) `rsd3g2Base` lacks `status` field → `event-normal` class not applied → transparent background. Fix: default `status: 'normal'`. (3) `rsd3g2Flags` format is `[code, status, date]` tuples but code treated entries as numeric indices → ghost entries with no code/status. Fix: match by `evt.code === flagCode` and set status/remarks in-place |

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` — selection persists across refresh (localStorage)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-01 | `@section('page-scripts')` | `saveState()` | Writes `{ faculty, cohort, week }` to `localStorage` key `cohortTimetableState` (try/catch — safe when storage unavailable) |
| 2026-08-01 | `@section('page-scripts')` | `restoreState()` | On DOMContentLoaded: re-validates saved faculty/cohort against `facultyData` (graceful fallback if a faculty/cohort was removed), re-selects via `onFacultyChange()`/`onCohortChange()`, then applies saved week — else normal empty-state flow |
| 2026-08-01 | `@section('page-scripts')` | saveState() wired in | Called at the end of `onFacultyChange()` (both branches), `onCohortChange()` (both branches), `selectWeek()`, `prevWeek()`, `nextWeek()` — any selection change is persisted |
| 2026-08-01 | `@section('page-scripts')` | Init | `restoreState()` called last in DOMContentLoaded (after empty-state setup, which it overrides). localStorage = longest lifespan (survives refresh + browser restarts) |

### `resources/views/partials/ui-nav-bar.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-02 | JS lines 660-683 | Fix RSD3 G2 reconstruction | (1) Populate all 14 weeks with base events first (was only flagged weeks → week 0 missing). (2) Default `status: 'normal'` on base copies (rsd3g2Base lacks status → event-normal class not applied → no color). (3) Apply flags by matching code instead of numeric index (flags are `[code, status, date]` tuples, not indices → ghost entries with no code) |
| 2026-07-30 | Line 9 | Nav link | Changed `Cohort Timetables` href from `#` → `/cohort-timetable-ui`, active class on `activeNav === 'cohort-timetables'` |

### `routes/web.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-30 | Line 31 (before auth group) | New route | `GET /cohort-timetable-ui` → `view('ui-design-templates.CohortTimetable-UI-design-template')` with `activeNav` |

### `.sdd/changes/cohort-timetable-ui/tasks.md`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-30 | All sections | Task completion | All 8 task sections marked complete with implementation details and verified test results |

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php` — OOP Phase 1 partial extraction

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 | — | Refactored: replaced inline page-header/week-nav/empty-state/grid-table/modal with `@include('partials.…')` (OOP Phase 1) | Page uses `ui-page-header`, `ui-week-nav`, `ui-grid-table`, `ui-empty-state`, `ui-class-detail-modal` partials. |
| 2026-08-13 | `public/css/theme.css` (shared) | macOS table fix | `.timetable`: `border-collapse:collapse` → `separate` + `border-spacing:4px`; removed 1px cell borders; hover uses `var(--color-surface-variant)`; removed zebra striping. `.badge` border-radius 6px→8px. |
| 2026-08-14 | `@section('page-styles')` | Offday-slot today-cell fix | Added `.today-cell.offday-slot { background: transparent; }` override so PH/Sunday empty cells are not red when today. |
| 2026-08-14 | `prevWeek/nextWeek/selectWeek` | WeekNavigator delegation | Local nav logic replaced with `weekNav.prevWeek()/nextWeek()/selectWeek()`; `buildTimetable()` syncs `currentWeek = weekNav.currentWeek`; `saveState()` kept after each nav. |

## [2026-10-03] Disabled print icon on the week-nav toolbar

Shared `ui-week-nav` gained an opt-in `'showPrint' => true` arg rendering a printer icon-button
(inline SVG, `.print-btn` in theme.css), right-aligned at the toolbar edge via `margin-left: auto`.
Enabled stub: click fires the shared `toast.show('Printing is coming soon')` bottom-left toast bar;
`title="Coming soon"` native tooltip on hover. No JS beyond the one-liner onclick.

### Postscript — sweep-fixes-round-1 (2026-10-06, F-11)

Week persistence key namespaced to `cohortTimetableWeek` (was the shared generic
`currentWeek`). Migration + default-week behaviour handled centrally in `WeekNavigator`.

### Postscript — holiday-badge-generic-label (2026-10-07, shared ui-common)

Day-header holiday badges now read generically as **PUBLIC HOLIDAY** (markup
text `Public Holiday`; the badge CSS already uppercases it), with the specific
holiday name — e.g. `Deepavali Holiday (In Lieu)`, `Christmas Eve` — shown on
hover via the shared `data-tip` tooltip system. Label-less holiday flags show
no tooltip. One-line change in `HtmlBuilder.dayHeader` (ui-common.js:2155);
applies to every timetable page via the shared builder.


---

## [2026-10-07] Class modal grouped into tabs (shared DetailModal taxonomy)

Same as my-timetable: `openClassModal` auto-groups rows into Class Information /
Schedule / Status tabs via `renderModalGroups` (ui-common.js). The page's
`extraFields` Cohort row now lives in the Class Information tab.

---

## [2026-10-07] Confirmed replacement modal shows the replaced original class

Same shared enhancement via `openClassModal`: replacement-flagged blocks whose
remarks hold a dd-Mon-yyyy date get an **Original Class** tab (date/weekday,
time, venue — no reason row, cohort flags carry no reason). Replacement flag
remarks now point at the prior week's same-weekday date (coherent with the
red original-conflict flag added there).

---

## [2026-10-07] Conflict blocks now render red (was: blue)

`statusClassFn` mapped conflict-status events into the blue mine/others
branches; added an explicit `.event-conflict` branch — same red as every other
page (§10.0 legend A).

## [2026-10-07] Demo conflict reasons made venue-coherent (follow-up)

Conflict-flag remarks now come from the venue-aware `default_reason` in the
seeder (labs → "Lab equipment failure", else "Lecturer on leave"); a 4th
element on a COHORT_STATUS_DEMO pick overrides it explicitly. Example:
RSD3(S1)G2 BMIT3084 red block (Mon 21-Sep, B006 Cisco Lab) now says
"Lab equipment failure" instead of the unsupported "Clash with another
module".

### Postscript — legend label clarification (2026-10-07)

Legend item "Conflict" relabelled to **"Conflict / Public Holiday"** (color
token and tip unchanged — the tip already read "Scheduling conflict or
public holiday"). Aligns the label with what the error-container color
actually encodes on this page: conflicted classes AND classes falling on
public holidays (both render via the holiday/conflict styling).

### Postscript — cancel-class-enhancement (2026-10-08, SDD change `cancel-class-enhancement`)

Lecturers can now cancel their own classes — status normal with an end time
in the future (real clock) — directly from the cohort grid's class modal via
the shared CancelClassModal (`partials/ui-cancel-class-modal`) with a
mandatory enum reason (6 values incl. Other + detail; OOP: `ClassCancellation`
in `ui-common.js`). A cancelled block vanishes and its slot frees; the
sessionStorage ledger (`classCancellationLedger`) replays the state across
pages, with an undo toast (12 s) on any landing page until undone/arranged.

### Postscript — toast snooze (2026-10-08, SDD-waived micro-fix)

✕-closing the cancellation undo toast now snoozes it for the browser session
(per-entry `toastSnoozed` in the ledger; `ToastManager.close()` in
`ui-common.js`, layout ✕ → `toast.close()`). Cancelled state + chip replay
unchanged; a new cancellation toasts again.

---

## [2026-10-08] Phantom multi-reds / double-pending fixed (reconstruction removed)

`buildAllEvents()` rebuilt RSD3 G2 from `rsd3g2Base` + code-keyed
`rsd3g2Flags`, flagging **every** block of a flagged code — week 0 showed 3 red
BMIT3084 (L+T+P) where the student page shows 1, and week 8 double-pended
BMIT2073. The reconstruction is deleted: the page now renders straight from
`cohortTimetable.events`, whose flags are already slot-keyed snap-to-first
(seeder §2.7, `check_mock` asserts views agree). Live parity: red count per
week now equals the student page for all 14 weeks; `sumHours` also resets to 0
in the faculty-selected branch of `onFacultyChange` (was left at 20).
Other lecturers' pendings no longer offer "View Full Request" (ownership check
in `openClassModal`, ui-common.js).

### Postscript — snooze rule extended (2026-10-08, U3 decision)

The undo toast now shows for **5 s** (was 12 s) and **auto-dismissing after the
full display also snoozes** it — surviving the whole toast counts as "seen",
same as clicking ✕. Only navigating away mid-display (timer killed) leaves the
entry unsnoozed, so the toast legitimately re-shows on the next load.
`ToastManager.show` gained an `onAutoDismiss` hook alongside `onManualDismiss`;
`UNDO_TOAST_MS` 12000 → 5000. Live-verified all three paths, 0 console errors.

### Postscript — undo feedback + grid rebuild (2026-10-08, U1/U2)

Clicking the undo toast's **Undo** now works as it looks: `ToastManager`
dismisses the undo bar **before** running the callback (U1 — the callback's
"Class restored." toast was previously wiped ~0 ms after appearing), and the
callback rebuilds whichever grid is on screen after `ClassCancellation.undo()`
(U2 — every timetable page's `buildTimetable()` / replacement-home's
`buildTable()`; the class reappears without a manual reload). Confirmation
toast shows last, over the rebuilt grid. Live-verified on my-timetable,
student page, and replacement-home; 0 console errors.

### Postscript — My Teaching summary cards (2026-10-08)

Cards 3–4 replaced: **Replacements → My Teaching Classes** and
**Pending → My Teaching Hours** (values `sumMyClasses` / `sumMyHours`).
My Teaching Classes counts each session separately — Subject A (T) and
Subject A (L) are two classes; My Teaching Hours sums those classes'
durations (slot = 30 min). Card colours unchanged (primary / neutral
hours style); Total Classes, Teaching Hours and Conflicts untouched.
Computation lives in the shared `myTeachingStats()` (grid-slotMap
dedupe so merged-cohort twins count once); `computeSummary` writes are
now null-guarded. Verified dft2s1 Week 1 → **3 classes / 4 hours**
(AMCS2093 L 2h + T 1h + P 1h).

### Postscript — conflict blocks made unmistakable (2026-10-08)

`.event-conflict` (global, theme.css) upgraded from a plain red tint to
a **2px solid `--color-error` border + diagonal caution stripes**
(`repeating-linear-gradient` over the container tint, token-only via
`color-mix` — adapts to dark theme). Same meaning, same red per §10.0;
just impossible to mistake for an ordinary block at grid glance.
Applies to every page rendering conflicted classes (cohort, venue,
my timetable, student). No legend or test changes needed; verified live
on cohort (dft2s1 W4 AMCS2093) and venue (B110 W4), suite 124 passed.

### Postscript — loud conflict red is now owner-gated (2026-10-08)

The loud red treatment (stripes + border, `event-conflict`) is reserved for
**the logged-in lecturer's own** conflicted / public-holiday classes.
Other lecturers' conflicted classes and PH-day classes fall back to a
**quiet red tint** (`event-public-holiday`, no stripes/border) — the class
still reads as "won't run / needs attention (someone else's)", just without
hijacking your attention. Legend split into two entries: **Your
Conflict / Holiday** (swatch reuses the real loud `event-conflict` class so
the legend shows the exact styling) and **Others' Conflict / Holiday**
(plain tint). Slot-status hint text updated to match. My Timetable keeps
its existing behaviour (all events shown are the viewer's own, so
semantics are unchanged); student page intentionally untouched (owner-blind
red there). Verified live: cohort dft1s1 W1 Muada's `MPU-2302(T)` quiet,
dft2s1 W4 own `AMCS2093(L)` loud; venue B110 W4 loud + B101 W1 quiet.
TC34/35 → 7 legend items; new TC35b (owner-gating on venue); suite green.

### Postscript — diagonal hatching removed; loud red = 3px border only (2026-10-09)

The 45° caution stripes on `.event-conflict` proved too busy — reverted to
a plain `--color-error-container` tint, with the border thickened
2px → **3px** so own/personal conflicted + public-holiday classes still
read unmistakably (owner-gating unchanged: loud border = yours, quiet
plain tint = others' on cohort/venue; personal pages all loud). Legend
swatches follow automatically. Verified live on all four pages.

### Postscript — block tooltips show lecturer + status (2026-10-09)

- Event-block hover tooltips now read `"Subject · Lecturer · Status"`
  (e.g. `Operating Systems · En. Lim Jia Zheng · Conflict`). The
  lecturer-only `tooltipExtra` hook was removed — the shared builder in
  ui-common now appends lecturer + run-status (`eventStatusLabel()`)
  uniformly on every timetable page.

### Postscript — §10.0 two-axis block language + legend trim (2026-10-09)

- **Colour = status, border = ownership.** Blocks now share one fill per
  status (Normal = success green, Pending = tertiary, Conflict/Holiday =
  error red); ownership moved entirely to border weight — **3px = your
  classes, 0.5px hairline = others'**. The quiet-vs-loud red split is gone:
  everyone's conflicts are the same error red, only the border differs.
- Replacement keeps its blue **only on the personal pages**; on this page it
  folds into Normal (tooltip still says "Replacement").
- Legend trimmed to status chips only (swatches reuse the real block
  classes), plus an ownership hint — *"thick border = your classes ·
  hairline = others'"* — replacing the Your/Others swatch pairs.

### Postscript — card flip-back keywords (2026-10-09, SDD: warning-modal-keywords)

The five summary-card flip-backs use the new keyword language: one bold-uppercase keyword
per description — **THIS COHORT**, **TEACHING HOURS**, **TAUGHT BY YOU**, **YOUR CLASSES** in
the default color, and **SCHEDULING CLASHES** in red (attention card). Secondary emphasis
("each slot = 30 minutes") stays plain bold.

### Postscript — route rename: `/cohort-timetable-ui` → `/cohort-timetable` (2026-10-13)

User decision: drop the `-ui` suffix from this page's URL. Changed in one commit:

- **`routes/web.php`** — the `$uiPages` key renames to `/cohort-timetable`
  (component, legacy template, `nav` key `cohort-timetables`, middleware all
  unchanged). A **temporary safety redirect** `Route::redirect('/cohort-timetable-ui',
  '/cohort-timetable')` keeps old bookmarks and the other session's test notes
  working — removable in a later cleanup.
- **`partials/ui-nav-bar.blade.php`** — the single `$items` source now links the
  desktop bar + mobile drawer to the new URL (lecturer/PL side; the student
  whitelist still omits the entry, so `NavIdentityTest` / `nav-identity.spec.ts`
  assertions follow the new href).
- **Tests** — URL strings updated everywhere they appear: `RouteGateMatrixTest`
  (+ a new guard test: the legacy URL 302s to the new route), `NavIdentityTest`,
  `nav-identity.spec.ts`, `timetable-wiring.spec.ts`, `pages-parity.spec.ts`,
  `cancel-class.spec.ts`, `ui-regression.spec.ts`, `tests/e2e/*.js`, manual
  `tests/Browser/*.py`, `MANUAL-TEST-CASES.md`.
- **`CodingMAIN.md`** page-inventory row updated (source of truth).

Deliberately NOT renamed: the `nav` active-state key `cohort-timetables`,
`pageKey` `cohortTimetable`, the WeekNavigator storage key, and the view name
`livewire.cohort-timetable` — internal identifiers, not URLs. The changelog
filename itself keeps its name (history).

### Postscript — faculty/cohort auto-select + hover full-name tooltips + summary-card hiding (2026-10-13)

User decisions (2026-10-13), implemented as one change (server payload + this
page's script; shared partials and the frozen templates untouched):

- **Hover tooltips (1a).** `facultySelect` / `cohortSelect` now carry
  `data-tip`, refreshed on populate + every change — the shared hover
  tooltip reads the attribute live. Faculty tip = the full `faculty_name`
  ("Faculty of Computer Science and Information Technology"); cohort tip =
  the approved format "RSD2 (S1) G1 — Bachelor in IT (Hons) Software Systems
  Development" (spaced code + programme name) via the new `full` payload
  fields. Native selects can only tooltip the closed control.
- **Faculty auto-select (1b).** The component derives the logged-in
  lecturer's faculty via `lecturers.dept_id → departments.faculty_id`
  (DCIT→FOCS, DSSH→FAFB, DACB→FAFB — the DSSH pointer was re-linked to FAFB
  the same day, see `docs/demo-db-data-changes.md`) and passes
  `defaultFacultyId`; boot preselects + populates cohorts.
- **Cohort auto-select (2).** The component also passes `taughtCohortIds`
  (cohorts with sessions taught by me this semester); boot picks the FIRST
  one in the displayed sorted order within the auto-selected faculty and
  runs the normal `onCohortChange` — grid + week nav live on arrival, zero
  clicks. Fallback (agreed): teaches nothing there → everything stays
  unselected, guidance shows (verified with Sharon 4363's data shape).
- **Faculty switch applies the same rule (follow-up, same day).**
  `onFacultyChange` now auto-picks the FIRST cohort the lecturer teaches in
  the newly selected faculty (displayed order) and runs the normal
  `onCohortChange` — switching faculty behaves exactly like arrival, zero
  clicks; teaches nothing in that faculty → the same guidance fallback.
  Boot now routes through `onFacultyChange`, so the rule lives in one
  place. Live-verified as 5770: FAFB (teaches nothing) → cohort empty +
  guidance + week nav off; back to FOCS → DFT2 (S1) G1 pre-picked, grid
  live; a manual pick of a non-taught cohort (DFT1 (S1) G1) still works.
- **Summary cards hidden when no timetable (3).** `#summarySection` is
  hidden in BOTH empty cases — no cohort picked (guidance) and cohort picked
  but the week has zero classes ("No classes scheduled") — and restored
  whenever the grid builds. Page-local JS; the shared
  `partials/ui-summary-bar` is untouched.

Students unaffected (already pinned + disabled selects; their zero-event
weeks now hide the cards too, consistently). Live-verified as 5770 (DCIT →
FOCS + DFT2 (S1) G1, tooltip hover renders) and 3799 Muada (DSSH → FAFB +
RAF2 (S3) G2); faculty switch → guidance hides the cards. Suite 173/173,
pint clean, phpstan 0.

### Postscript — guide block synced to the new behaviour (2026-10-13)

"How to use this page" was stale on two fronts and is now aligned with the
page as it works today:

- **First bullet** replaced: "Select cohort — choose a faculty, then a
  cohort…" described a manual first step the auto-select removed. Now:
  "Auto-selected — your faculty and the first cohort you teach load on
  arrival; switch the dropdowns to view any cohort's timetable".
- **Slot-status bullet** rewritten in the §10.0 two-axis language — the old
  "Blue: Your classes, Green: Others' classes, Grey: Others' pending…"
  wording predated the 2026-10-09 parity postscript (`event-mine` and
  `event-others` are both success-green; ownership is border weight only).
  Now: Green = scheduled, Amber = pending, Red = conflict/holiday, thick
  3px border = your classes, thin = others' — matching the page's own
  legend chips.
- **New bullet**: "Full names — hover the faculty/cohort dropdowns to see
  them spelled out" (documents the new tooltips).

### Postscript — per-option tooltips on both selects (2026-10-13)

Every `<option>` in `facultySelect` and `cohortSelect` now carries a `title`
attribute with its full name (faculty_name / the "code — programme name"
cohort label) — populated in `populateFaculties` / `populateCohorts`, so the
OPEN dropdown's individual options explain themselves on hover, complementing
the closed-control `data-tip`. Mechanism note: OS-rendered native option
lists don't fire DOM hover events, so the shared `data-tip` tooltip cannot
work inside an open select — the native `title` attribute is the only
per-option lever and keeps the frozen control untouched (native option
titles render in Chrome/Edge/Firefox; the closed-control data-tip covers any
environment that suppresses them). Verified: FAFB/FOCS + all cohort options
titled (Muada's FAFB view: 3/3; 5770's FOCS view uses the same code path).

### Postscript — SelectDd panel: shared-tooltip options + ✓ "my classes" marking (2026-10-13)

User decisions (2026-10-13): make the per-option hover tooltips CONSISTENT
with the rest of the site (the native `<option title>` box looked nothing
like the shared `.data-tip-tooltip`), and mark the cohorts/faculties the
logged-in lecturer teaches (bold + accent colour + leading ✓), with a
user-facing explanation of the marking.

- **SelectDd panel (Option A — panel OVER the native select).** The
  semester-bar `<select>`s stay real, visible, and fully functional (inline
  onchange wiring, keyboard arrows, the 5 Playwright specs' `selectOption`).
  `mousedown` is preventDefaulted (kills the OS popup, keeps focus) and a
  DOM panel — the venue-dd idiom — opens below instead. Rows are rebuilt
  from the LIVE `<option>`s on every open (immune to `populate*()`
  rewrites), each carrying `data-tip` = the option's full name → the
  SHARED hover tooltip renders, pixel-identical to venue-dd room rows and
  every other tip on the site. Row pick sets `select.value` + dispatches
  `change` → the existing handlers fire unchanged. Closes on outside
  click / Escape / page scroll / resize — but NEVER on its own
  scrollbar (user bug 2026-10-13): the old capture-phase listener closed
  the panel on the first wheel tick, so long lists were unscrollable and
  momentum/re-anchor scrolls made reopen clicks look dead until a hard
  refresh. Three guards now: the scroll listener ignores scrolls
  originating inside `.select-dd-panel`; `overscroll-behavior: contain`
  stops a wheel at the list ends from chain-scrolling the page behind
  it; and a 300ms post-open grace absorbs queued scroll-into-view
  events, so the panel can't be killed by its own opening. Trigger gets
  a box-shadow ring while open (the selects are borderless — a border
  would shift layout).
  Note: theme.css already gives these selects `appearance:none` + the
  themed chevron, so the closed look is 100% unchanged.
- **✓ marking (ownership axis).** Taught cohorts/faculties render bold in
  `var(--color-primary)` (#004D98 TARUMT blue) with a leading ✓ —
  deliberately a different colour from the green/amber/red STATUS axis so
  the two can never be confused. Data: the existing `taughtCohortIds`
  payload (no PHP change); faculty marking is derived client-side
  (faculties where the lecturer teaches ≥1 cohort). Native
  `option[data-taught]` CSS covers the keyboard-only Alt+Down popup.
  Students: empty taught list → no marks, selects disabled → no panel.
  List order unchanged (reordering would break the "first taught in
  displayed order" auto-pick).
- **Explanation (user ask).** New 6th guide bullet — "✓ Your cohorts —
  bold blue entries marked ✓ are the cohorts/faculties you teach this
  semester" (label renamed from "✓ Your classes" the same day — "your
  classes" is already the slot-status legend's term for thick-border
  blocks, so the two axes get distinct names) — plus a muted panel-footer
  hint shown only when something is marked ("✓ bold = cohorts you teach" /
  "✓ bold = you teach here"),
  pinned `position: sticky; bottom: 0` with a surface background so it
  stays readable while a long list scrolls under it.
- **Live-verified** as 5770 (FOCS panel: 4 taught cohorts ✓-marked
  DFT2(S1)G1/DSF2(S1)G1/RSD2(S1)G2/RSD2(S1)G3 + hint; FAFB panel: 3 rows,
  none marked, no hint — the fallback shape; hover on a row renders the
  shared tooltip with the full faculty name; picking a taught row builds
  the grid, refreshes the closed-control data-tip; Escape closes) and
  3799 Muada (both faculties ✓-marked). Scroll-fix re-verified with real
  click + wheel: wheeling to the list end and back keeps the panel open
  with the page scroll contained, wheeling on the page closes it,
  reopening always works, and the previously-unreachable bottom row
  (RSD3(S1)G3) is pickable → grid builds. pint clean, phpstan 0.
- Generic `.select-dd-*` class names, page-local for now — promote to a
  shared partial when a 3rd custom dropdown appears (venue-dd = 1st).
