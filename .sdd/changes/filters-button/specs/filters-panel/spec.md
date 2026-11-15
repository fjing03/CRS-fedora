# Filters panel requirements

Frozen `proposal.md` and `design.md` are the authority. Scope: exactly **three** pages —
My Replacements (`livewire/replacement-home.blade.php`), Request Approval
(`livewire/request-approval.blade.php`) and Class Replacements
(`livewire/replacement-history.blade.php`). `partials/ui-week-nav.blade.php` is shared with five other
live pages and MUST NOT be edited; the nine `ui-design-templates/*` reference pages MUST NOT be edited.

## R1 — one labelled door, and nothing filter-shaped on the page until it opens
- Each of the three pages MUST render **one** Filters button, id `filtersButton`, as the first control of
  the filters row: a funnel glyph (`aria-hidden`) plus the visible word `Filters`, which is its
  accessible name. It MUST carry `aria-haspopup="dialog"`, `aria-controls="filtersPanel"` and
  `aria-expanded="false"` at rest.
- The row MUST sit **where the chips row sits today** — below the page header and, on Request Approval,
  below the sort hint, which stays on the page above it — at **every** width.
- The row MUST be, on the two staff pages, `[Filters button] [#filterChips] [#resultCount]`; on the
  student page it MUST be `[Filters button] [#filterChips]` (no count there).
- With the panel closed, **no filter control may be visible on the page** — search box, status select,
  both week selects, the Hide COMPLETED switch and Reset included — and none of them may be reachable
  by keyboard or announced, because they live inside a `hidden` overlay.
- The button MUST be hidden until the module has moved the toolbar into the panel (design §2,
  `.filters-ready`), so a page whose JavaScript never runs shows today's toolbar inline and no door
  rather than a dead button.
- The button MUST be the **only** way in: no funnel may be added to the page header.

## R2 — the panel: one frame, two shapes, one shared owner
- The frame MUST be one shared partial (`partials/ui-filter-panel.blade.php`) included by all three
  pages: an overlay (`#filtersOverlay`, `hidden` at rest) containing a `role="dialog"`
  `aria-modal="true"` `#filtersPanel` labelled by a heading that reads `Filters`, a sticky head, and a
  scrollable body `#filtersPanelBody` that ships **empty**.
- At **≥769px** the panel MUST render as a card anchored under the **left edge of the Filters button**:
  width `min(460px, calc(100vw - 16px))`, `left = clamp(buttonLeft, 8, viewportWidth - width - 8)`,
  `top = buttonBottom + 8`, `max-height = viewportHeight - top - 8`, veil `rgba(0,0,0,.32)` (the
  prototype's value, not a theme token).
- At **≤768px** it MUST render as a bottom sheet: full width, `max-height: 88dvh`, sticky head, body
  scrolls with `overscroll-behavior: contain`, veil `rgba(0,0,0,.45)`, safe-area padding.
- The head MUST carry a close control whose accessible name is `Close Filters`, and — **on the two
  staff pages only** — a Reset button reading `Reset` with a circular-arrow glyph, wired to that page's
  own reset function. The student page's head MUST be title + close only (proposal decision 1).
- Opening MUST close the bell panel and the summary sheet; opening the bell or the summary sheet MUST
  close the panel. Two overlays MUST never be open on one page at the same time.
- The panel MUST be a plain overlay at `z-index: 1200` (not a native `<dialog>`), with the Filters
  button lifted above the veil while open, and every `data-tip` inside MUST remain readable above it.

## R3 — the controls are moved, never rebuilt
- `.toolbar` MUST be the same DOM node before and after the move, with every id and value intact:
  `#searchInput`, `#statusFilter`, `#weekFilter`, the switch (`#hideCompleted` on My Replacements and
  Class Replacements, `#hideCompletedToggle` on Request Approval). No id may be renamed and no control
  may be re-created.
- Each control MUST carry a visible label above it: `Search`, `Status`, `Original slot week`,
  `Requested slot week`, `Hide COMPLETED`. The switch MUST sit on one line to the right of `Status`.
- Controls that gain a visible label MUST lose their `aria-label` (so the visible label is the
  accessible name); their `data-tip` MAY remain. `#statusFilter` keeps its options and its
  page default untouched.
- The switch's inline word MUST be hidden inside the panel (the label above names the state) and the
  chip MUST read `Completed hidden`.
- My Replacements' search MUST drop its magnifier glyph and its `.search-wrapper` box inside the panel;
  the shared `.search-wrapper` / `.search-icon` CSS MUST stay (the design templates still render them).
- The old `#resetFilters` button MUST leave the toolbar **and its boot listener MUST be deleted in the
  same commit** (`replacement-home:1419`, `request-approval:1368`) — leaving the listener makes
  `getElementById` return `null` and throws before every listener wired after it.
- The toolbar host and the panel body MUST be `wire:ignore`, so a Livewire morph (Approve/Reject) can
  neither duplicate nor delete the moved toolbar. After such a round-trip there MUST still be exactly
  one toolbar, inside the panel, with both week selects populated and holding their values.

## R4 — the second week filter, on all three pages
- Each page MUST gain one more week select in the panel, populated by the shared builder with the "no
  filter" option reading `All Weeks`:

  | page | existing (kept) | added | rows filtered by |
  |---|---|---|---|
  | My Replacements | `#weekFilter` — Original slot week | `#weekFilterRequested` | `c.replacementDate` |
  | Request Approval | `#weekFilter` — Requested slot week | `#weekFilterOriginal` | `r.classDate` |
  | Class Replacements | `#weekFilter` — Original slot week | `#weekFilterRequested` | `r.req.week` (1-based) |

- Field order in the panel MUST be: Search · Status + Hide COMPLETED · Original slot week · Requested
  slot week (so Request Approval's added field renders **above** its existing one).
- The added field MUST reuse `partials/ui-week-nav` (with `selectId`, `prevOnclick`, `nextOnclick`,
  `selectOnclick` and its own tips), and the shared partial MUST stay byte-identical. The two arrow
  helpers MUST accept an optional select id and, when given one, scope to that select's own `.week-nav`.
  Called with **no** id they MUST stay byte-compatible with today — which is three different behaviours
  today: `prevWeekFilter()` / `nextWeekFilter()` null-guard their select, `updateWeekArrowState()` assumes
  `#weekFilter` exists, and `updateWeekArrows()` is the only genuinely "first pair in document order"
  helper. None of that may change for a page that does not use the new id.
- Every place that re-syncs arrows MUST name the select it means: Request Approval's
  `onWeekFilterChange()` and boot call MUST pass `'weekFilter'`; reset/clear and both reveal paths MUST
  sync both ids.
- The two week filters MUST compose (AND). A specific **requested** week MUST hide rows with no
  arrangement — including every `Need to Arrange` row on My Replacements and every unarranged row on
  the student page. The student predicate MUST compare numerically (`Number(r.req.week)`) and MUST NOT
  reuse the original-week `-1` (that base is the 0-based `r.week`).
- Each page MUST render a chip for its added filter (`Requested Slot Week: …` / `Original Slot Week: …`)
  with its own removal path, following the panel's field order in **both** the chips and the fields:
  `Status · Original slot week · Requested slot week · Search · Completed hidden · Clear all`. On Request
  Approval that means the added **Original** chip is inserted **before** the existing Requested chip, not
  after it.
- Reset (two staff pages) MUST clear **both** weeks to `All Weeks`; Clear all (all three pages) MUST
  clear both; the saved view MUST remember both and restore them on reload, ignoring a value the select
  does not have (legacy saved views that predate the field MUST restore as `All Weeks`).
- Request Approval's Reset MUST also restore status `Pending`, rows-per-page 10 and the exclusion ON;
  My Replacements' Reset MUST restore exclusion ON and status `all`. Reset MUST NOT become "everything
  off" (that is the chips' Clear all).
- The two "show me this row" arrival paths — `jumpToNewRequest()` on My Replacements and
  `revealNotifRow()` on Class Replacements — MUST also clear the added week (no silent skip) and
  re-sync its arrows.

## R5 — chips and count
- `#filterChips` MUST stay the same node, repainted by the pages' own builders, inside the row — visible
  with the panel closed — and MUST keep its id and class so the page's `innerHTML` repaint keeps working.
- The `Active Filters:` prefix MUST be gone from all three builders; the row is named by the button.
- The count MUST be a pill at the right of the row (`#resultCount` on the two staff pages, empty and
  filled by the page's own `updateResultCount`), visible at **every** width: My Replacements' old
  `@media (max-width:1499px) { #resultCount { display: none } }` rule MUST be deleted (proposal
  decision 1b). Request Approval's count tip MUST stay `Rows matching the current filters`; My
  Replacements' MUST stay `Classes matching the current filters`.
- Exactly **one** `#resultCount` node per staff page; the student page MUST have none.
- `.filter-chips:empty { display: none }` MUST be **kept** (proposal §5.2's soft-frozen note): the row no
  longer depends on it — the button and the pill are siblings — and an empty chip bar collapses instead
  of leaving a gap.
- On a phone the chip ✕ MUST be 26px inside a pill of at least 32px (the pill is the touch target) so
  the row measures ≤44px in the default view, and the rest of the row's 48px touch floor MUST be
  untouched elsewhere.

## R6 — keyboard, focus, lock, Escape
- The button MUST open on click, `Enter` and `Space`; `aria-expanded` MUST follow the open state.
- On open, focus MUST move into the panel — the first control in the body, never a `.week-arrow`.
- While open, `Tab` and `Shift+Tab` MUST cycle inside the panel only.
- `Escape`, the close control and a tap on the veil MUST each close the panel exactly once, release the
  scroll lock, set `aria-expanded="false"` and return focus to the button.
- The scroll lock MUST be the panel's own class (`filters-panel-open`), never a shared one.
- `Escape` MUST be a no-op while a dialog above is open (the guide dialog, a row modal,
  `#summaryExplainModal`): one Escape means one layer, and a row modal opened over the page closes
  without closing the panel underneath.
- A breakpoint crossing while open MUST keep the panel open and re-place it; the trap MUST survive the
  change.

## R7 — print and degraded mode
- Printing MUST NOT capture the panel or the button, MUST relocate the toolbar back inline for the
  paper (so the fields still print, as they do today), MUST print the chips and the count, and MUST NOT
  leave `body.filters-panel-open` with `overflow: hidden`. After printing the toolbar MUST return into
  the panel.
- If the module never boots, the page MUST show today's toolbar inline with its new labels, no door,
  and no console page error beyond the absent module.
- No new dependency, no migration, no schema change, no demo-DB write, no reseed; every colour, radius,
  shadow and spacing from `theme.css` tokens, with one recorded exception — the phone veil is the
  `.modal-overlay` literal `rgba(0,0,0,.45)` and the desktop veil is the prototype's `rgba(0,0,0,.32)`.

## R8 — evidence
- **Amended in the same commit — four Playwright files and one PHP test.** The four specs that drive
  the moved controls (`#statusFilter`, `#searchInput`, `#weekFilter`, the switch as
  `#completedToggle`/`#hideCompleted`; the old `#resetFilters` becomes `#filtersResetButton` in the
  panel head) MUST be rewired through one shared helper
  (`tests/helpers/filters-panel-helper.ts`: open the panel, interact, close it), because those controls
  now sit inside a `hidden` overlay and Playwright's `click` / `fill` / `selectOption` require
  visibility: `tests/status-tooltips-filter-chips.spec.ts` (23 such references),
  `tests/week-filter-preserved.spec.ts` (9), `tests/request-completed-status.spec.ts` (7) and
  `tests/summary-cards-button.spec.ts` (3). Their pinned **strings** change as well:
  `.filter-chips-label` = `Active Filters:` (`status-tooltips-filter-chips.spec.ts:415`), the
  `Exclude COMPLETED Status` chips (`:171`, `:205`, `:421`), the option `'All Requested Slot Weeks'`
  (`:380`), `'All Original Slot Weeks'` (`week-filter-preserved.spec.ts:141`), plus the two comments
  that record the old wording as a ruling (`week-filter-preserved.spec.ts:137–140`,
  `status-tooltips-filter-chips.spec.ts:113–116`).
  `tests/Feature/StudentReplacementHistoryParityTest.php` MUST be amended **precisely, not wholesale**:
  `:143` (`allLabel: 'All Original Slot Weeks'` → `All Weeks`), `:146` (the assertion that the student
  source does not contain `Requested Slot Week:` — which this lane makes false), `:149` (the exact
  single-week predicate, which gains the requested-week clause) and the docblock `:15`. `:144`'s
  `Original Slot Week:` chip text and the toggle test at `:156` MUST stay green — both survive this
  lane, so editing them would be a false amendment.
- Evidence MUST include, per page and at 320 / 390 / 768 / 769 / 1024 / 1440 in both themes: the door
  and the empty page surface (R1); the card vs sheet shape with its veil and lock (R2); every label,
  the status+switch line and the field order (R3); both week filters narrowing, composing, chipping,
  resetting and surviving a reload, including the `Need to Arrange` disappearance (R4); the pill at
  every width and the chip texts with no `Active Filters:` (R5); focus in, trap, Escape, veil and focus
  return (R6); the printed page (R7); and the §9 acceptance numbers — at 390×844, default view, the
  first list row's top edge at **≤400px** on the two staff pages (from 519 and 541) and **≤375px** on
  the student page (no regression), with the chips row ≤44px. At 390px the grid is `display: none` and
  the card view renders, so "first row" means the first **card**; the table-row form applies at ≥769px.
- A new Playwright spec MUST pin proposal (a)–(h): nothing filter-shaped visible before opening · the
  panel's shape at 768 vs 769 · `aria-expanded` with focus in and back · chips repaint after a change
  with the panel closed · Escape/veil close and unlock · the count pill text on the two pages that have
  one · `#filterChips` node identity across open/close · the added week filter per page (narrowing, its
  chip, both weeks cleared by the bulk action, both restored after reload).
- The specs that MUST stay green **unamended**, because they never drive a moved control with a
  visibility-gated call: `pages-parity`, `ui-regression`, `nav-identity`,
  `replacement-home-status-badges` (`$$eval` / `toHaveValue`), `my-replacements-after-submit`
  (`inputValue`) and `request-approval` (`toContainText` on `#resultCount`). Zero page errors on all
  three pages.
- **Soft-frozen correction (2026-11-15):** an earlier draft of this clause claimed
  `tests/e2e/full-suite.spec.js` sits outside `testDir` and never runs. It does not — `testDir:
  './tests'` collects subdirectories and nothing ignores `tests/e2e/**`, so its two search tests
  (`:596–611`, which wait for `#searchInput` to be **visible** and then fill it) MUST be rewired through
  the same helper in the same commit.
- One entry in each of the three page changelogs MUST record the change.
