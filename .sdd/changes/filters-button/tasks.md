# Tasks — `filters-button`

**Batch 4.** `proposal.md`, `design.md` and `specs/filters-panel/spec.md` are frozen. Every task below is
≤ 2 hours and names the artifact it must satisfy. Order matters: A builds the shared shell and the shared
helpers, B moves the three pages onto it (markup then behaviour, page by page), C fixes chips and the
count, D proves it, E records it.

**Rules for every task**

- **Do not rename an id.** `#searchInput`, `#statusFilter`, `#weekFilter`, `#hideCompleted`,
  `#hideCompletedToggle`, `#filterChips`, `#resultCount` are how the pages' own JS finds its controls.
  A "tidy-up" breaks a page silently.
- **Do not edit** `partials/ui-week-nav.blade.php` (five other live pages share it byte-for-byte) or
  anything under `ui-design-templates/`.
- After every Blade/JS/CSS edit, restart the dev server before looking: `pkill -f "[a]rtisan serve"`,
  then `rm -f storage/framework/views/*.php`, then serve again. Never `pkill -9 php`.
- Tests run against the **testing** DB only:
  `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test <file> --reporter=line`.
  No demo DB, no migration, no reseed.
- If a task seems to need a renamed id, a new backend field or an edit to a template, stop — it is out of
  scope (proposal §3).

---

## Batch A — the shared shell and the shared helpers

- [ ] **T1 — the partial (`resources/views/partials/ui-filter-panel.blade.php`, new).** The row
  (`#filtersRow`: `#filtersButton` with its funnel + `Filters`, `#filterChips`, and `#resultCount` when
  `$filtersCount`), the overlay / panel / head / body exactly as design §4.1, the Blade vars
  (`$filtersCount`, `$filtersCountTip`, `$filtersReset` + `$filtersResetOnclick` + `$filtersResetTip`),
  and `wire:ignore` on `#filtersPanelBody`. No page is opted in yet. *Satisfies R1, R2.*

- [ ] **T2 — the CSS block (`public/css/theme.css`).** One new section after the chips block with the
  full rule set of design §5: `.filters-row`, `.filters-ready .filters-btn`, the inline chips and the
  count pill, `.filters-overlay` / `.filters-panel*`, `.filter-field` / `.filter-label` / `.filter-line`,
  the scoped `.filters-panel-body .toolbar*` neutralising rules, both breakpoint blocks, the phone chip
  sizing and the print block. Tokens only, except the two recorded veil literals. *Satisfies R1, R2, R5,
  R6, R7.*

- [ ] **T3 — `FiltersPanel`: init and the move (`public/js/ui-common.js`, new section after
  `SummarySheet`).** The object shell; `init()` that finds `#filtersPanel` + `.toolbar`, inserts the
  placeholder if needed, `relocate()`, adds `filters-ready` to `<html>`, and wires the button / veil /
  close control and `beforeprint` → `unrelocate()` / `afterprint` → `relocate()`; `relocate()` /
  `unrelocate()` idempotent with the placeholder (design §6). **The handlers they call land in T4** — do
  not invent stubs. No page opts in yet. *Satisfies R1, R3, R7.*

- [ ] **T4 — `FiltersPanel`: open, close, place, lock.** `open()` (close the bell and the summary sheet
  first, remember the opener, unhide, `aria-expanded`, `filters-panel-open`, focus the first control
  excluding `.week-arrow`), `close(restoreFocus)` (the exact reverse, focus back), `_place()` for the
  desktop card with the clamp of design §3, and the resize / `matchMedia` re-placement that never closes.
  *Satisfies R2, R6.*

- [ ] **T5 — `FiltersPanel`: Escape and the trap.** Capture-phase Escape that no-ops while a
  `.modal-overlay` above is visible (`[style*="flex"]`), the Tab/Shift+Tab trap with the same live
  suspension, and the one-line hooks in `openNotifPanel()` + `SummarySheet.open()` that close the panel.
  *Satisfies R2 (one overlay at a time), R6.*

- [ ] **T6 — the arrow helpers (`public/js/ui-common.js`, before any page uses them).**
  `prevWeekFilter(selectId)`, `nextWeekFilter(selectId)` and `updateWeekArrowState(selectId)` gain the
  optional id and scope to that select's own `.week-nav`. **The no-id path stays byte-compatible**
  (`prevWeekFilter` / `nextWeekFilter` null-guard, `updateWeekArrowState` still assumes `#weekFilter`,
  `updateWeekArrows` stays document-order). Without this, every second week nav added in Batch B would
  drive the *original* select. *Satisfies R4.*

## Batch B — the three pages onto the shell

- [ ] **T7 — My Replacements markup (`livewire/replacement-home.blade.php`).** Wrap the toolbar in
  `#filterToolbarHost[wire:ignore]` + placeholder; delete the M15 block (`:106–110`); drop the
  `.search-wrapper` and its magnifier; add every label + `.filter-field` wrapper; status and switch into
  `.filter-line` with the `Hide COMPLETED` label; **add the second week field** (`#weekFilterRequested`
  via the shared include, with its own tips and handlers, design §4.2); remove `#resetFilters`,
  `#resultCount` and `#filterChips`; include the partial after the toolbar with `filtersReset => true`,
  `filtersResetOnclick => 'resetAllFilters()'` and the page's own count tip. **Delete the `#resetFilters`
  boot listener (`:1419`)** — leaving it throws and kills every listener after it. *Satisfies R1, R2, R3,
  R4, R5.*

- [ ] **T8 — My Replacements behaviour.** `populateWeekSelect` for the new select and `allLabel =>
  'All Weeks'` on the existing one; `saveFilters`/`restoreFilters` gain `requestedWeek` (option-guarded);
  `buildTable()` gains the requested-week predicate on `c.replacementDate`;
  `resetAllFilters()`/`clearAllFilters()` clear both weeks and sync **both** navs' arrows; the
  `change` listener for the new select; `requestedWeekFilterChanged()`; `jumpToNewRequest()` clears the
  new week too; `FiltersPanel.init()` in the boot. *Satisfies R4, R6.*

- [ ] **T9 — Request Approval markup (`livewire/request-approval.blade.php`).** The same treatment, with
  the switch keeping `id="hideCompletedToggle"` and `onchange="toggleHideCompleted()"`; the added
  **Original** week field rendering **above** the existing requested one; **the same three removals as
  T7** (`#resetFilters` at `:247`, `#resultCount` at `:250`, `#filterChips` at `:256`); the include placed
  **after** the sort hint (`:254`); and **the `#resetFilters` boot listener deleted (`:1368`)**.
  *Satisfies R1, R2, R3, R4, R5.*

- [ ] **T10 — Request Approval behaviour.** `saveFilters`/`restoreFilters` gain `origWeekFilter`;
  `filterData()` gains the original-week predicate on `r.classDate`; `populateWeekFilter()` populates both
  selects with `All Weeks` and the same `labelFn`; `resetFilters()`/`clearAllFilters()` clear both weeks
  and sync both navs; `onWeekFilterChange()` and the boot call pass `'weekFilter'`;
  `onOrigWeekFilterChange()`; `FiltersPanel.init()`. *Satisfies R4, R6.*

- [ ] **T11 — Class Replacements markup (`livewire/replacement-history.blade.php`).** Toolbar wrapped and
  labelled (week field + switch only), the second week field (`#weekFilterRequested`), `#filterChips`
  deleted from the page (`:151`), the partial included with `filtersCount => false` and **no Reset**.
  *Satisfies R1, R2, R3, R4.*

- [ ] **T12 — Class Replacements behaviour.** `populateWeekSelect` ×2 with `allLabel => 'All Weeks'`;
  `saveView`/restore gain `reqWeek` (option-guarded); `renderUpcoming()` gains the requested predicate
  with `Number(r.req.week)` (1-based, no `-1`); the delegated `data-remove` handler gains the
  `weekFilterRequested` branch; `clearAllFilters()` clears the new week per select; `revealNotifRow()`
  clears it when it would hide the row; `FiltersPanel.init()` from the page's `DOMContentLoaded` closure.
  *Satisfies R4.*

## Batch C — chips and the count

- [ ] **T13 — chip text on all three pages.** Drop the `Active Filters:` prefix from the three builders,
  rename `Exclude COMPLETED Status` → `Completed hidden`, and keep the order `Status · Original ·
  Requested · Search · Completed hidden · Clear all` (**Request Approval's new Original chip goes
  before** the existing Requested one). This task owns **all** chip wording; T8/T10/T12 own only the
  behaviour around it. *Satisfies R5.*

- [ ] **T14 — the count pill.** Confirm the two staff pages render exactly one `#resultCount`, that the
  pill is visible at every width (the M15 rule is gone per T7) and that its text still comes from the
  page's own `updateResultCount()`. *Satisfies R5.*

## Batch D — the tests

- [ ] **T15 — one helper (`tests/helpers/filters-panel-helper.ts`, new).** `openFiltersPanel(page)`,
  `closeFiltersPanel(page)` and a `withFilters(page, fn)` wrapper that opens, runs, closes — so a spec
  never has to know the panel's shape. *Satisfies R8.*

- [ ] **T16 — rewire the two heaviest specs.** `tests/status-tooltips-filter-chips.spec.ts` (23
  references) and `tests/week-filter-preserved.spec.ts` (9): every `click` / `fill` / `selectOption` on a
  moved control goes through the helper; their pinned strings move with the lane (`.filter-chips-label`
  gone, `Completed hidden`, `All Weeks`) and the two comments that record the old wording as a ruling are
  updated. *Satisfies R8.*

- [ ] **T17 — rewire the two light specs and the legacy e2e pair.**
  `tests/request-completed-status.spec.ts` (7 references) and `tests/summary-cards-button.spec.ts` (3),
  plus the two search tests in `tests/e2e/full-suite.spec.js` (`:596–611`, which wait for
  `#searchInput` to be visible — that file **is** collected by `testDir: './tests'`, so it cannot be left
  red). *Satisfies R8.*

- [ ] **T18 — the new spec, part 1 (`tests/filters-button.spec.ts`): the door and the frame.**
  Proposal (a)–(g) and design §8 rows 1–6, 12, 13, 14, 16: nothing filter-shaped visible before opening; the card
  at 769 vs the sheet at 768 with its veil and lock; `aria-expanded` with focus in and back; chips repaint
  with the panel closed; Escape / veil / × close and unlock; the count pill on the two staff pages;
  `#filterChips` node identity across a cycle; one overlay at a time (bell and summary sheet); resize
  across 768 keeps it open; **Escape inside a row modal closes only the modal** (row 16); and **the
  printed page** (row 13) — `page.emulateMedia({ media: 'print' })`: no button, no overlay, and the
  toolbar fields present inline again. **Plus the morph canary (design §8.15):** approve a request on Request
  Approval, reopen, assert exactly one `.toolbar` inside `#filtersPanelBody` with both week selects
  populated and holding their values. *Satisfies R1, R2, R3, R5, R6.*

- [ ] **T19 — the new spec, part 2 (same file): the second week filter, per page.** Proposal (h): on each
  page the added week narrows the list, its chip appears and removes only itself, the page's bulk action
  clears **both** weeks (Reset on the staff pages, Clear all on the student page), and the saved view
  restores both after a reload — including the requested week hiding `Need to Arrange` rows. *Satisfies
  R4.*

- [ ] **T20 — the parity test and the green list.** Amend
  `tests/Feature/StudentReplacementHistoryParityTest.php` **precisely**: `:143` allLabel → `All Weeks`,
  `:146` the now-false `Requested Slot Week:` assertion, `:149` the predicate (the exact new string from
  design §7.3, composed as `(all || r.week === w0) && (req === 'all' || (r.req && Number(r.req.week) === wReq)) &&
  !(hideCompleted && r.status === 'completed')`), docblock `:15`; leave `:144` and `:156` untouched. Then
  run the stay-green specs (`pages-parity`, `ui-regression`, `nav-identity`,
  `replacement-home-status-badges`, `my-replacements-after-submit`, `request-approval`). *Satisfies R8.*

## Batch E — evidence

- [ ] **T21 — the measurement (design §9).** At 390×844, default view, record the first list row's top
  edge on all three pages (first **card** at 390 — the grid is `display: none` there) and the chips row's
  height: ≤400 / ≤400 / ≤375, chips ≤44px. Screenshots at 390 and 1440, light + dark. *Satisfies R8.*

- [ ] **T22 — page changelogs.** One 3-line entry each in
  `page-changelogs/replacement-home-changelog.md`, `request-approval-changelog.md` and
  `replacement-history-changelog.md`, naming what moved behind the door, the second week filter, the
  count pill and the wording changes. *Satisfies R8.*

- [ ] **T23 — the full run.** The new spec, the four amended Playwright specs, the two legacy e2e search
  tests, the parity test and the green list in one pass on the testing DB; zero page errors on the three
  pages. Record the commands and the counts in the apply log. *Satisfies R8.*
