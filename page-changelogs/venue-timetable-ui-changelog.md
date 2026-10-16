# Changelog — Venue Timetable UI

## [2026-10-10] Event-blocks rewrite: two-axis language, 4-item legend, PH own-class red (SDD venue-event-blocks-db)

After merging `upstream/fjing` (`f8b35a2`, 12 commits — the frozen venue design), the DB-backed Livewire page (`App\Livewire\VenueTimetable` + `venue-timetable.blade.php`) was rewritten from status cells (`.vt-cell-*`) to the cohort-style **event blocks**. READ-only v1 unchanged: no Book button, no cancel modal (write path = Slice B).

### Page changes
- **Two-axis block language (§10.0)**: colour = status, border = ownership (3px thick = viewer's own classes, 0.5px hairline = others'). The page now drives the shared grid engine in `cellRender` mode (upstream mock pattern) — free slots stay honest green, occupied continuation slots collapse, blocks carry `event-mine` / `event-others` / pending & conflict pairs.
- **Legend 7 → 4 items** (frozen design): Available / Normal (`event-normal`) / Pending (`event-pending`) / Conflict / Public Holiday (`event-conflict`) + the ownership hint line ("thick border (3px) = your classes · thin border (0.5px) = others'"). Guide-block slot-colour bullet rewritten to the same language.
- **PH-day own-class exception**: the viewer's OWN classes on public-holiday days render loud red (`event-conflict` — "your class won't run"); everyone else's stay empty `PH` cells. Sundays always empty. Reachable against real data (W8 Mon, W14 Wed/Thu).
- **Tooltip contract**: `data-tip2` = `lecturer · status` (`eventStatusLabel`, holiday-aware); theme.css `::after` composes the rendered tooltip `name · lecturer · status`.
- **Summary semantics fix**: Available now **excludes Sunday and public-holiday slots** (they can't be booked — the old totals counted holiday rows as Available while the legend tip said otherwise). Cards kept: Total (120) / Available / My Teaching Classes / My Teaching Hours / **Occupied** (`#sumOccupied` — deliberate deviation from upstream's "Unavailable"; read-only page counts what's booked). **Deviation note**: My-Teaching counts the viewer's own PH-day blocks (they render as red blocks), unlike upstream's offday-skip — grid-faithful by design.
- **Venue dropdown**: B006 (CiscoLab) now reachable under **Lab** (3 categories — merged `_typeOf` folds CiscoLab into Lab); room options carry full-name `data-tip`s.
- Booking stays out: the Slice-B hint line remains; no fabricated pending/conflict data (conflict stays derived — B005 AMIT2034).

### Verification
- phpunit 130/130 · phpstan 0 · lint clean · venue-db 5/5 (4-item legend, block classes, PH cells, B005 conflict both sides, dropdown cascade) · timetable-wiring · nav-identity · records-intact (101 sessions / 38640 slots unchanged across the merge).

---

## [2026-10-09] B005 diploma-cohort conflict — derived flag, owner-gated (SDD b005-diploma-conflict)

First enforcement of `VENUE-RESTRICTIONS.md`: the audit's single violation (AMIT2034 P, Wed 11:00–13:00, B005, combined DFT+DSF diploma lecture) now renders as a **schedule conflict needing replacement**. Derived at render time — no DB mutation, the 3-state slot machine (FR 4.11) is untouched.

- **Conflict derivation** (`venueRestrictionConflict()`): venue `B005` AND any cohort's programme starts with `D` → event `status: 'conflict'` on every page.
- **Owner-gated rendering**: the owner's block is loud striped red (`event-conflict` — "needs YOUR action"); other viewers see quiet red (`event-public-holiday`), matching the legend's Your/Others Conflict split. The shared modal shows "Scheduling conflict — needs attention". No replace button — the write path is Slice B.
- Venue eager-load gained `classSession.venue` (N+1 avoidance); conflict outranks slot-pending/normal per the twin-merge severity map.
- Verified: phpunit 129/129 (845 assertions; +6 conflict tests) · phpstan 0 · lint clean · venue-db 4/4 (live Daniel-5652 owner view) · timetable-wiring 5/5 · nav-identity 3/3.

---

## [2026-10-09] Post-merge design parity: 7-item legend + ownership cards (SDD merge-upstream-ui-2026-10)

After merging `upstream/fjing` (`e7f8036`, 21 commits — the 2026-10-09 UI batch), the DB-backed Livewire page (`App\Livewire\VenueTimetable`) was adapted to the post-merge venue design. READ-only v1 unchanged: no Book button, no cancel modal (write path = Slice B).

### Page changes
- **Legend 4 → 7 items** (upstream parity): Available / Your Classes / Others' Classes / Others' Pending / Your Pending / Your Conflict (`event-conflict` swatch) / Others' Conflict. Swatch colours match the real grid cells (`.vt-cell-*`); Available tip stays honest ("Free slot…", no "click to book").
- **Summary cards**: Pending card (always 0 in v1 data) replaced by **My Teaching Classes** (`#sumMyClasses` — viewer's session rows, each class counts separately) and **My Teaching Hours** (`#sumMyHours` — viewer's occupied slots × 0.5 h, trailing `.0` stripped). Total / Available / Occupied kept.
- **Twin-merge (defensive)**: same venue+day+start events collapse into one block by severity (conflict > pending > replacement > normal), cohorts joined, students summed — impossible in v1 data (partial unique index); unit-tested via reflection.
- **`statusClassFn` gains the `conflict → event-conflict` contract** (unreachable in v1 — no fake data).

### Verification
- phpunit 123/123 (829 assertions; +ownership-cards and twin-merge tests) · phpstan 0 · lint clean · venue-db 3/3 (live `4288` ownership check: B006 W1 → 8 classes / 14 h) · timetable-wiring 5/5 · nav-identity 3/3.
- Upstream auth-free mock specs are N/A on fedora's auth-first real-data routes (see SDD review-log erratum).

---

## [2026-08-31] Sync upstream/fjing UI refactor (merge fd8c403)

Merged `upstream/fjing` (267 commits `cfc1bb1..f44cc5c`) into `fedora-backend`; brings the full upstream SDD implementation (4becd3f) of this page to this PC. **New page on fedora-backend** — route `/venue-timetable-ui` smoke-tested 200. Playwright e2e spec arrived too; `@playwright/test` added as devDependency — browsers not yet installed on this PC (`npx playwright install` pending, deferred to Phase 2 wiring).

### Files Changed
- `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php` — **theirs**
- `tests/venue-timetable.spec.ts` — **theirs** (575-line Playwright spec)
- Shared: `theme.css`, `ui-common.js`, `mock-data.js`, `layouts/ui-template`, `partials/ui-nav-bar` — **theirs**

---

## [2026-08-15] Detail modal: status description as its own row

Added a **Status Description** row ("Replacement request awaiting approval" / "Class booked for this venue") separate from the Status badge.

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| `openModal()` | Updated | Split `Status` and `Status Description` rows. |

---

## [2026-08-15] Detail modal refinements: gray × close, split rows, status badge

### Summary

- **Close button** — normal gray `×`; removed header `modal-status-badge`.
- **Split rows** — Subject Code/Name, Start/End Time split; status as badge row with brief description.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| Modal shell | Updated | Removed header `modal-status-badge`. |
| `openModal()` | Updated | Split rows, badge status + description. |

---

## [2026-08-15] Detail modal redesign + fix (was never opening)

### Summary

- **Fix**: the Booked-Class detail modal opened with `.classList.add('open')` but only `.modal-overlay.show` is styled — the modal never displayed. Now uses `DetailModal` (`.show`).
- **Redesign**: converts the static `modal-field` rows to the shared `DetailModal` "Detail Sheet" (identity header, single flat group, definition rows without per-row borders). Same data (Course/Name/Lecturer/Venue/Cohort/Time/Status/Remarks).

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| Modal shell | Updated | Body emptied (JS-generated now). |
| `openModal()` | Rewritten | Uses `DetailModal.render`; fixed `.open` → `.show`. |
| `closeModal()` | Updated | Uses `DetailModal.close()`. |

---

## [2026-08-15] Page-specific summary card descriptions

### Summary

Added page-specific `description` text to each summary card (Total Slots / Available / Pending / Unavailable) instead of relying on the shared generic descriptions in `ui-summary-bar`.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| Summary bar cards | Updated | Each card now passes a page-specific `description` (e.g. Unavailable = "booked class, Sunday, or public holiday"). |

---

## [2026-08-15] Summary: Sunday + Public Holiday folded into "Unavailable"

### Summary

The venue summary previously had an "Occupied" card while Sunday (`.cell-sun`) and Public Holiday (`.cell-ph`) cells were not counted at all — so `Total` did not cover the whole grid and the labels were misleading. `Occupied` + `Sunday` + `Public Holiday` are all "cannot book this slot", so they're now grouped under a single **Unavailable** card.

New cards: **Total Slots**, **Available**, **Pending**, **Unavailable** (= Occupied + Sunday + Public Holiday). `Total = Available + Pending + Unavailable` now exactly equals the grid cells. Legend updated to Available / Pending / Unavailable with a tooltip explaining the grouped reasons.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| Summary bar cards | Updated | Replaced "Occupied" card with "Unavailable"; `valueId` `sumOccupied` → `sumUnavailable`. |
| Legend bar items | Updated | "Occupied" → "Unavailable" with tip `Cannot book — slot is booked, Sunday, or public holiday`. |
| `updateSummaries()` | Updated | `unavailable = occupied + sunday + ph`; `total = available + pending + unavailable`. |

---

## [2026-08-15] Summary stats now match the rendered grid (accurate counts)

### Summary

The summary cards were inaccurate in two ways:
1. **Occupied/Pending** counted event objects, not hour slots — a 3-hour booking showed as "1" instead of the 3 cells it occupies on the grid.
2. **Available** only counted Monday–Friday (`di < 5`), while the grid also renders Saturday as a working day, so `Total`/`Available` under-counted.

Now `updateSummaries()` counts exactly the cells the grid renders (`.cell-occupied`, `.cell-pending`, `.cell-available`), so the cards always match the timetable — including overlapping bookings from multiple cohorts that share an hour slot. Falls back to 0 when no venue is selected.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| `updateSummaries()` | Rewritten | Counts rendered `.cell-content` cells (`occupied`/`pending`/`available`) directly from the `.timetable` DOM instead of re-deriving from `events`; this handles overlapping bookings and all working days (incl. Saturday). |

---

## [2026-08-13] Legend bar background fix

### Summary

Legend bar now has `background: var(--color-surface)` so swatches render on the same surface as timetable cells, ensuring color consistency.

### Files Changed

#### `public/css/theme.css`

| Timestamp | Location | Change | Detail |
|---|---|---|---|
| 2026-08-13 | `.legend-bar` | Updated | Added `padding`, `background: var(--color-surface)`, `border`, `border-radius` |

---

## [2026-08-13] OOP Refactor: Align cell-available with shared theme.css

### Summary

Removed duplicate `.cell-available` + hover CSS (now in `theme.css`). Simplified `.cell-content.cell-occupied` to remove redundant positioning (inherited from `.cell-content` in theme.css). Updated JS to wrap available cells in `.cell-content` class for consistency with replacement-arrangement.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-13 | Lines 470-482 | Removed | `.cell-available` + hover (now in theme.css via `.cell-content` + `.cell-available`) |
| 2026-08-13 | Lines 485-495 | Simplified | `.cell-content.cell-occupied` — removed `position: absolute; top/left/right/bottom: 0;` (inherited from `.cell-content`) |
| 2026-08-13 | Line 1208 | Changed | `div.className = 'cell-available'` → `'cell-content cell-available'` |

---

## [2026-08-13] Phase 3 UX Enhancement: Legend Tooltips

### Summary

Added hover tooltips to all legend items describing each status.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-13 | Lines 634-639 | Added | `tip` property to each legend item |

---

## [2026-08-13] Phase 3 UX Enhancement: Shared Legend Bar

### Summary

Replaced inline legend with shared `ui-legend-bar` partial using `$items` parameter.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-13 | Lines 632-649 | Replaced | Inline legend → `@include('partials.ui-legend-bar', ['items' => [...]])` |
| 2026-08-13 | Line 634 | Changed | Legend swatch: `var(--color-primary)` → `var(--color-success)` |
| 2026-08-13 | Line 476 | Changed | `.cell-available` background: `var(--color-primary-container)` → `var(--color-success-container)` |

---

## [2026-08-13] Phase 3 UX Enhancement: Collapsible Guide Block

### Summary

Added an expandable guide block with page-specific workflow instructions.

### Files Changed

#### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-13 | Lines 546-555 | Added | `@include('partials.ui-guide-block')` with 5 workflow tips |

---

## Files Changed

### `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-09 | — | Created page | New Blade template with venue dropdown, week picker, timetable grid, legend bar, summary cards, modal |
| 2026-08-09 | `@section('page-styles')` | Page-specific CSS | Venue dropdown, filter bar, segment toggle, venue type filter, booking banner, tab toggle, history panel, hint text, error banner, toast, print button, available tooltip, booked modal, keyboard hints, no-match banner, responsive styles |
| 2026-08-09 | `@section('content')` | HTML structure | Page header with print button, booking banner, error banner, no-match banner, venue + week picker, tab toggle, filter bar (time range + venue type), history panel, grid wrapper, hint text, legend bar, summary bar, empty state, mobile card list, detail modal, available tooltip, toast |
| 2026-08-09 | `@section('page-scripts')` | Render logic | State variables, week data builder, URL params reader, init function, venue dropdown builder (with recent/favourites), venue change handler, week nav, tab toggle, filter logic, venue events getter, timetable grid builder, mobile card builder, summary updater, modal (booked class), available tooltip, keyboard navigation, favourites localStorage, recent venues localStorage, state persistence, toast notification |

### `routes/web.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-09 | After `request-approval-ui` route | New route | Added `Route::get('/venue-timetable-ui', ...)` returning view `ui-design-templates.venue-timetable-UI-design-template` |

### `resources/views/partials/ui-nav-bar.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-09 | `$items` array | Nav item | Added "Venue Timetable" as 6th nav item with key `venue-timetable` and href `/venue-timetable-ui` |

### Design Updates (SDD enhancements)

| Timestamp | Change | Detail |
|-----------|--------|--------|
| 2026-08-08 | URL params support | Read `code` and `cohort` from URL when coming from My Timetable flow |
| 2026-08-08 | Booking banner | Show "Booking for: BMIT5555 — RSD3G2" when `code`+`cohort` present |
| 2026-08-08 | Code/cohort passthrough | Pass `code`+`cohort` through when "Book Now" is clicked |
| 2026-08-08 | Advanced features | Added: venue favourites, booking history, time/venue type filters, quick book shortcut, recent venues |
| 2026-08-09 | Skeleton loading | `withSkeleton()` on venue change, standard pattern |
| 2026-08-09 | Empty states | Hint text for no classes, no-match banner for filter |
| 2026-08-09 | Error handling | Error banner for MockData failure, toast for slot taken |
| 2026-08-09 | Print button | Disabled printer icon in header with "Coming soon" tooltip |
| 2026-08-09 | Booking history | Tab toggle for Current Week / Past 4 Weeks with history panel |
| 2026-08-09 | Quick book (B3) | Keyboard shortcut B on focused available cell |
| 2026-08-09 | Keyboard nav | Arrow keys, Enter, Escape navigation on grid |
| 2026-08-09 | Mobile view | Card layout for booked/available slots on ≤768px |
| 2026-08-09 | Tablet view | Reduced grid density on 769px–1024px |
| 2026-08-09 | UI Polish (A1) | Print icon moved to rightmost of semester-bar |
| 2026-08-09 | UI Polish (A2) | Timetable design matched to replacement-arrangement |
| 2026-08-09 | UI Polish (A3) | Favourite star changed to separate button (36px) with better touch target |
| 2026-08-09 | UI Polish (A4) | Morning/Afternoon labels now show time ranges (8AM–12PM / 1PM–6PM) |
| 2026-08-09 | UI Polish (A5) | Added booking hint banner: "Click any green slot to book this venue" |
| 2026-08-09 | Bug Fix (B1) | Venue type filter now actually filters venues in dropdown |
| 2026-08-09 | Bug Fix (B2) | Morning/Afternoon filter now hides all cells outside range (including holiday/Sunday) |
| 2026-08-09 | Bug Fix (B3) | Holiday/Sunday cells now show lock icon + "Holiday"/"OFF" label |
| 2026-08-09 | Bug Fix (B4) | Today button now works (initTodayBtn() called in DOMContentLoaded) |
| 2026-08-09 | Bug Fix (B5) | Tooltip only shows for cells matching current time filter |
| 2026-08-09 | Bug Fix (B6) | Mock data made more distinct with events in B014, B015, B016 |
| 2026-08-09 | UX (C1) | Past 4 Weeks changed from tabs to collapsible details element |
| 2026-08-13 | `@section('page-styles')` | macOS-style update (Phase 4) | Removed ~48 lines of duplicate modal CSS (`.modal`, `.modal-header`, `.modal-title`, `.modal-status-badge`, `.modal-close`, `.modal-body`, `.modal-field`, `.field-label`, `.field-value`, `.modal-footer`, `.btn-close-modal`, `#eventModal.modal-overlay`) — all now served by `theme.css`. Standardized border-radius: `fav-btn`, `segment-toggle`, `venue-type-btn`, `print-btn`, `booking-hint`, `no-match-banner button` 6px→8px; `history-status`, `venue-event-status`, `btn-book`, `error-banner button` 4px→6px. Standardized shadows: `venue-type-dropdown`, `toast-notification`, `available-tooltip` → layered `0 4px 16px rgba(0,0,0,0.12), 0 1px 4px rgba(0,0,0,0.06)`. Fixed hardcoded `#fff` → `var(--color-on-primary)` in `segment-toggle button.active`, `btn-book`, `no-match-banner button`. Added `:focus-visible` rings to `fav-btn`, `segment-toggle button`, `venue-type-btn`, `print-btn`, `btn-book`, `error-banner button`, `no-match-banner button`. |
| 2026-08-13 | `public/css/theme.css` (shared) | macOS table fix | `.timetable`: `border-collapse:collapse` → `separate` + `border-spacing:4px`; removed 1px cell borders; hover uses `var(--color-surface-variant)`; removed zebra striping. `.badge` border-radius 6px→8px. |
| 2026-08-13 | `public/css/theme.css` (shared) | CSS tooltip + legend refactor | Added `[data-tip]` CSS tooltip system (above element, inverse-surface bg, arrow, opacity transition). Legend bar: `flex-direction: column`, hint moved to top-left above swatches. Legend swatches use container tokens matching cell backgrounds. Legend bar has `background: var(--color-surface)` for consistent rendering. |
| 2026-08-14 | `buildTimetable()` | OOP refactor | Hand-built `<table>` replaced with shared `buildTimetableGrid({cellRender})` from ui-common.js. cellRender handles holiday/Sunday (cell-ph/cell-sun), event/occupied (cell-pending/cell-occupied), available cells + booking tooltip + mobile available cards. |
| 2026-08-14 | `prevWeek/nextWeek/selectWeek`, today btn | WeekNavigator delegation | Local nav logic replaced with `weekNav.prevWeek()/nextWeek()/selectWeek()` + `currentWeek` re-sync. Today button now uses `weekNav.initTodayBtn()` (class jumpToToday) + persist listener. |

## [2026-10-03] Disabled print icon on the week-nav toolbar

Shared `ui-week-nav` gained an opt-in `'showPrint' => true` arg rendering a printer icon-button
(inline SVG, `.print-btn` in theme.css), right-aligned at the toolbar edge via `margin-left: auto`.
Enabled stub: click fires the shared `toast.show('Printing is coming soon')` bottom-left toast bar;
`title="Coming soon"` native tooltip on hover. No JS beyond the one-liner onclick.

Venue Timetable renders its own identical hand-placed button (`.print-btn`, inline
`margin-left:auto`, same toast onclick) instead of the partial arg — the page's `showPrint` stays
off so exactly one button renders per page.

Venue Timetable renders its own identical hand-placed button (`.print-btn`, inline
`margin-left:auto`) instead of the partial arg — the page's `showPrint` stays off so exactly one
button renders per page.

## [2026-10-03] Book offered only when the slot can fit a booking

The venue page advertised "Book" on cells too late in the day for the arrangement page's
4-slot booking span (e.g. 18:00) — users landed straight into the conflict state. Cells where
start + BOOK_SPAN (= 4 half-hour slots, matching the arrangement's default MAX_SELECTION) would
run past day end now carry .cell-no-fit: hover label "Not bookable" + not-allowed cursor, and
clicking shows an explanatory toast ("A booking needs 120 minutes — not enough time left in the
day.") instead of the Book tooltip. Fitting cells behave exactly as before. Also: the page's
week position moved to its own localStorage key ('venueTimetableWeek'), so browsing the
replacement-arrangement grid no longer jumps this page's week. 0 console errors.

### Follow-up: a fresh Book click resets that booking's sticky memories

If a slot's auto-select was previously discarded (bookingIntentCancelled) or its reminder
dismissed (bookingIntentDismissed), re-booking the SAME slot from the venue page now clears
those keys before navigating — a fresh Book click is a fresh intent, so the banner shows and
the auto-select fires again. Other bookings' memories are untouched.

## [2026-10-03] Round-2 fixes: Class Details on desktop + tooltip staleness + keyboard

1. **Class Details modal was unreachable on desktop**: occupied/pending cells had no click
   listener (openModal was wired only to the mobile cards). Desktop cells now open the modal
   (cursor pointer, focusable, aria-label), keyboard focus tracked.
2. **Book tooltip went stale across week/venue switches**: the tooltip survived grid rebuilds
   still offering the old date/venue. buildTimetable now dismisses it on every rebuild.
3. **Keyboard B shortcut needed arrow-key navigation first**: clicking or focusing a cell now
   sets the tracked cell, so B works after click/Tab too.

Verified: occupied/pending modals on desktop, tooltip dismissal on week + venue change,
B-after-click, plus the venue-side regression set — 0 console errors.

## [2026-10-03] Round-3 fixes: mobile card list finally visible + keyboard reaches booked cells

1. **Mobile card list was built but never shown**: buildTimetable hid #mobileCardList and nothing
   re-showed it — 100+ cards (booked classes AND bookable slots) were dead markup at phone widths
   while the 7×22 desktop grid squeezed into 356px with no scroll. The list now reveals after
   every rebuild when cards exist (media-query aware), #timetable hides on ≤768px via
   body[data-page] scoped CSS, and a resize listener keeps both honest without a rebuild.
2. **Keyboard dead-end on booked cells**: occupied/pending cells are focusable (round 2) but
   arrows/Enter did nothing on them. Arrow navigation now walks ALL focusable cells; Enter on a
   booked cell opens its Class Details modal (Enter on available cells still opens the Book
   tooltip; B stays available-only).

Verified: card list on mobile (B103 booked cards + B002 bookable cards), week change re-render,
desktop unaffected, Enter/arrow/B keyboard paths, resize both directions — 0 console errors.

## [2026-10-04] Round-4 fixes: stale keyboard cell + venue guard

1. **B shortcut used a detached cell**: focusedCell survived grid rebuilds — B after a week/venue
   switch opened the Book tooltip from the old cell. buildTimetable now clears it (mirroring the
   tooltip dismissal).
2. **Unknown venue codes could clobber the selection**: onVenueChange assigned
   `currentVenue = MockData.venues.find(...)` BEFORE its guard — a stale favourite code left
   currentVenue undefined (trigger stale, page half-broken). VenueDropdown.select() now validates
   against the master list (ignores unknown codes), and onVenueChange resolves into a temp before
   committing. (Not user-reachable in the mock — the panel only lists real venues — hardening.)
3. Shared: week-filter keyboard fallback ([ / ]) no longer crashes on pages without #weekFilter.

Verified star sync across venue switches, favourites cap tooltip, B-after-rebuild, and the
regression set — 0 console errors.

## [2026-10-04] Lead-time rule: venue booking blocked < 3 working days out

Same rule as the arrangement page (shared `isSlotTooSoon`, MOCK_NOW anchor — see that page's
changelog entry): otherwise-free cells inside the 3-working-day window (and past days) render
read-only (`cell-too-soon`, hover "Min. 3 working days ahead") — **no Book tooltip, no
keyboard, no mobile bookable card**. Booked/pending classes still render normally, including in
past weeks (read-only browsing of history stays intact).

The summary bar counts too-soon cells as Unavailable (Unavailable = booked + Sunday + holiday +
too soon) so Total stays consistent; the legend tooltip documents the new reason.

Verified: Week 11 Mon/Tue blocked + Wed–Sat bookable, booked classes visible inside blocked
days, past weeks read-only with classes shown, summary arithmetic, no tooltip on too-soon
cells, mobile card list reduced to informational cards — 0 console errors.

**Follow-up:** the lead-time rule is announced in the UI — a notice under the venue header
("Bookable from Wednesday, 07 Oct 2026 onward — replacement requests need at least 3 working
days' notice."), date computed from MOCK_NOW + holidays via the shared
`leadTimeCutoff()`/`renderLeadTimeNote()` helpers.

**Rework:** the lead-time notice moved above the grid and became a contextual banner (booking-
banner family, neutral info tone) — shown while the viewed week has blocked days, hidden on
fully open weeks, copy adapts (passed week / current week / partially blocked).

**Tweak:** the lead-time banner's date ("Wednesday, 07 Oct 2026") uses the success/green
token so the opening date reads as the focal point.

**Fix ("Today" consistency):** the venue template's local `getTodayMs()` shadow (pre-refactor
leftover) was deleted — it silently overrode the shared mock-anchored helper, making this page
read the REAL clock while the arrangement read `MockData.mockNow`. Both pages now use the one
shared anchor.

**Note (no visible change):** the shared `ui-today-btn` partial and `WeekNavigator` gained an
"earliest bookable" variant used by the replacement-arrangement page (where unbookable weeks
are hidden and "Today" would point off the visible range). The venue page keeps its full week
list and "Today" — it's the browsing/history page, so no change applies here.

**Note (no visible change):** the "Book from" grid chip is opt-in (cfg.bookableBadge) — only
the replacement-arrangement page renders it; this page keeps its full week list + "Today"
button (id todayBtn, unchanged).

**Note (no visible change):** the BOOKINGS OPEN badge (success-green) remains opt-in on the
replacement-arrangement page; this page keeps its today-badge (primary) and full week list.

**Note (shared anchor move):** `MockData.mockNow` → Mon 5 Oct 2026 — the venue's current week
is now Week 10 (05 Oct ~ 11 Oct) with the TODAY badge on the Mon column; the lead-time
blackout boundary becomes Thu 08 Oct on both pages.

**Tweak (label parity):** the venue's week select drops its custom labelFn and uses the shared
default — "Week 10 · 28 Sep 2026 ~ 04 Oct 2026" (· separator, both years) — now identical in
format to the arrangement's selector. Nothing else changes.

**Tweak (summary cards commented out):** the four summary cards (Total Slots / Available /
Pending / Unavailable) are Blade-commented on this page — the grid already shows the same
state colour-coded and the legend explains it, so the counts added little value to the booking
journey. The shared `ui-summary-bar` partial is untouched (8 other pages use it); the include
is restored by uncommenting, and `updateSummaries()` is null-guarded + keeps its counting so
the page runs without the cards.

**Tweak (dropdown arrow consistency):** the venue dropdown trigger now uses the SAME design as
the week-select family — the label and the chevron are split (label span + shared 10×6
stroke-1.5 chevron SVG, currentColor/token-toned), so the filled "▾" text glyph is gone;
typography aligned to the week select (13px/600, height 36px, radius-md — was 14px/500,
radius-sm). The old stale-green arrow hex (#3d5a48, an orphan of a previous palette) is
replaced across ALL selects by per-theme strokes mirroring the tokens
(dark/on-surface-variant #9EAAB8, light #5A6978; semester-bar selects mirror
on-primary-container #6BA3E0 / #003366) since SVG data-URIs cannot use var().

### Postscript — sweep-fixes-round-1 (2026-10-06, F-4)

**Status badges no longer leak raw lowercase enums.** New shared `StatusText.label()`
promoted to ui-common (3 sites on this page = the duplication threshold): history card
(:660), event card (:846), modal Status row (:914) now print
"Normal" / "Replacement" / "Pending" (Title case §10.0 vocabulary) while the
`badge-*` CSS keys stay raw-enum — zero visual/color change, text only.

### Postscript — sweep-fixes-round-2 (2026-10-06, F-6 + token fix)

1. **F-6 — mobile slot list grouped by day**: the builder emits an italic `.m-slot-day`
   header ("Thu · 08 Oct 2026") before each day's run of cards (event cards AND bookable
   cards), so the 60+ button wall becomes day-sectioned. Headers re-emit with every
   builder pass (verified Week 11 → 5 headers, Week 12 → 6 fresh, no duplicates/stale),
   card flow unchanged (click → Book tooltip → arrangement).
2. **Token fix (static scan T9):** `color: #fff` ×2 (booking banner, `.venue-available-card`)
   → `var(--color-on-primary)` — the live pass on this page in the sweep skipped the token
   scan; the static grep across all 9 templates caught them. Zero visual change (on-primary
   = white on primary in both themes).

### Postscript — venue-event-blocks (2026-10-07, SDD change `venue-event-blocks`)

**Booked/pending classes now render as cohort-style merged event blocks** (this page was the
last timetable surface still drawing one flat red square per half-hour). Only the
occupied-state branches of this page's `cellRender` changed — booking branches
(available / too-soon / Sunday / holiday) are untouched:

1. **Event blocks** — head half-hour emits `div.event-block span-{N}` (span = half-hours,
   `td.colSpan` when span > 1; continuation half-hours hidden). Mine/others ± pending
   classes via `e.lecturer === MockData.currentUser.name` (same pairing as Cohort);
   `ev-code`/`ev-venue`/`ev-time` content, `.event-block::after` tooltip
   (`name · lecturer`), click/Enter → the existing Booked-Class detail modal. Mobile
   booked-card emission unchanged (runs from the rewritten head branch).
2. **Keyboard parity** — the venue keydown roving now accepts `.event-block[tabindex="0"]`
   alongside `.cell-content[tabindex="0"]`; Enter on a block opens the modal; `B` shortcut
   stays available-cells-only.
3. **Legend 4 items** — Available / Your Classes / Others' Classes / Pending (was 3 items).
   Accepted trade-off (documented in the proposal): Available cells and Others' Classes
   blocks share `--color-success-container`; the legend tips + the reworded booking hint
   disambiguate.
4. **Booking hint copy** — "Click any green **empty** slot to book this venue" (the old line
   became literally false once green event blocks render).
5. **Summary cards restored** (were commented out) — Total Slots / Available / Pending /
   Unavailable, counted span-weighted from the week's deduped event heads
   (`<di>:<start>` last-write-wins = grid slotMap semantics, non-offday guard) so the
   cards always match what the grid renders. Default view (B002, current week):
   154 / 66 / 0 / 88.
6. **Tests** — `tests/venue-timetable.spec.ts`: TC32/TC40 generic subject-code regex;
   TC35 new legend labels; TC36/TC58 4 cards; TC37 new ids; TC41 venue-row locator
   disambiguated (`hasText: /\(\d+ seats\)/` — `:has-text("Venue")` also matched the
   Status Description row); TC39/TC42–44 went live unchanged. Suite: 36 → 45 passing; the
   16 remaining failures are pre-existing macos-ui-refactor staleness (native
   `#venueSelect`, venue-type filter, print button) — out of scope here, tracked for a
   follow-up change.

No shared files touched (`theme.css`, `ui-common.js`, `mock-data.js`, partials). Block
builder duplicated a 2nd time (cohort = 1st) — promote-to-shared deferred to a 3rd
occurrence per house rule.

### Postscript — holiday-badge-generic-label (2026-10-07, shared ui-common)

Day-header holiday badges now read generically as **PUBLIC HOLIDAY** (markup
text `Public Holiday`; the badge CSS already uppercases it), with the specific
holiday name — e.g. `Deepavali Holiday (In Lieu)`, `Christmas Eve` — shown on
hover via the shared `data-tip` tooltip system. Label-less holiday flags show
no tooltip. One-line change in `HtmlBuilder.dayHeader` (ui-common.js:2155);
applies to every timetable page via the shared builder.


---

## [2026-10-07] Class Details modal grouped into tabs

Venue class-detail modal now splits into 2 tabs: **Session** (code, name,
lecturer, cohort, start/end) / **Venue & Status** (venue, status badge,
description, remarks). Blade: `venue-timetable-UI-design-template.blade.php`.

### Postscript — cancel-class-enhancement (2026-10-08, SDD change `cancel-class-enhancement`)

Lecturers can now cancel their own classes — status normal with an end time
in the future (real clock) — directly from the venue grid's class modal via
the shared CancelClassModal (`partials/ui-cancel-class-modal`) with a
mandatory enum reason (6 values incl. Other + detail; OOP: `ClassCancellation`
in `ui-common.js`). A cancelled block vanishes and its slot frees; the
sessionStorage ledger (`classCancellationLedger`) replays the state across
pages, with an undo toast (12 s) on any landing page until undone/arranged.

### Postscript — toast snooze (2026-10-08, SDD-waived micro-fix)

✕-closing the cancellation undo toast now snoozes it for the browser session
(per-entry `toastSnoozed` in the ledger; `ToastManager.close()` in
`ui-common.js`, layout ✕ → `toast.close()`). Cancelled state + freed slot
replay unchanged; a new cancellation toasts again.

---

## [2026-10-08] Conflict blocks render red + twin merge + 6-item legend (bug audit + venue-legend-parity)

Four fixes, applied together because they interlock:

- **Combined-lecture twins were last-win.** Grid `slotMap` overwrote per event,
  so for a same-slot twin pair (e.g. B110 week 3 AMCS2093: DFT2 conflict first,
  DSF2 normal second) the normal twin won and the slot rendered green.
  `getVenueEvents()` now merges duplicates: one block, cohort joined
  ("DFT2(S1) + DSF2(S1)"), status combined by severity
  (conflict > pending > replacement > normal). This also stops the modal showing
  the cohort slug (`dsf2s1`) — the display name is kept.
- **Conflict branch in `cellRender`.** `status === 'conflict'` now gets
  `.event-conflict` (red, owner-agnostic, §10.0 parity with cohort) instead of
  falling into the mine/others greens. Live: B006 week 0 BMIT3084 red;
  B110 week 3 AMCS2093 red.
- **Legend grew 4 → 6 items** per the frozen `venue-legend-parity` design §1:
  Available, Your Classes, Others' Classes, Others' Pending (grey
  `--color-surface-variant`), Your Pending, Conflict / Public Holiday. Spec
  TC34/TC35 updated to the 6-item exact-text assertions.
- **Modal**: Status Description for conflicts now reads "Scheduling conflict —
  needs attention" (was "Class booked for this venue"); a Total Students row was
  added, resolved from the course registry per merged cohort (B110 AMCS2093 →
  50, B006 BMIT3084 → 14) since `cohortTimetable.events` carry no per-event
  count.

Known collision intentionally left: Available and Others' Classes share the
same green (user decision, 2026-10-07; frozen design keeps it).

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

### Postscript — resize listener de-duped (2026-10-08, exempted from Batch-7 rejection)

`buildTimetable()` re-registered an anonymous `resize` listener on every
venue/week change, so N rebuilds stacked N copies (each resize ran N redraws
of the mobile-card-list toggle). The handler is now stored on
`window.__venueResizeHandler` and the previous one is removed before the new
one is added — exactly one live listener regardless of rebuild count.
Instrumented live check: 3 adds / 3 removes across rebuilds, mobile card list
still toggles correctly at 375 px / 1440 px; 0 console errors. (The rest of
Batch 7 — column/Sunday/span cosmetics — remains REJECTED.)

### Postscript — venue-legend-parity shipped (2026-10-08)

Legend bar rebuilt to the cohort page's exact five class-status items,
preceded by the venue-only **Available** item (6 total, in order):
Available · Your Classes · Others' Classes · Others' Pending · Your
Pending · Conflict / Public Holiday. Item 6's tip carries the venue
nuance (public-holiday slots show as empty 'PH' cells — not red).

To keep the new legend truthful, the venue `cellRender` gained the
missing conflict branch: `e.status === 'conflict'` now renders
`event-conflict` (red, owner-agnostic — parity with the cohort builder)
before the mine/others fallback. Previously a conflicted class (e.g.
B110 Monday `AMCS2093`, "Lecturer on leave") silently rendered as an
ordinary green block on this page while rendering red everywhere else.

Branch order is unchanged (sunday/holiday cells still win — a booked
class on an offday still shows the offday cell), booking affordances
untouched, `.event-conflict` was already global in theme.css (no new
CSS). Live-verified: 6-item legend desktop + mobile, B110 Week 4
AMCS2093 red / AMIS1012 green, 130 available cells intact. This
supersedes venue-event-blocks' 4-item legend expectations (TC34/TC35
rewritten to 6 items during the spec de-stale). Implementation landed
in `b47221e` (parallel session); artifacts in
`.sdd/changes/venue-legend-parity/`.

### Postscript — My Teaching summary cards, Pending removed (2026-10-08)

**Pending** card removed (held slots still count toward Total Slots);
added **My Teaching Classes** + **My Teaching Hours** — same semantics
and labels as the cohort page, scoped to this venue. Final bar:
Total Slots · Available · My Teaching Classes · My Teaching Hours ·
Unavailable (red card last, mirroring cohort). Counting is
grid-equivalent: offday events excluded (they render as PH cells),
merged-cohort twins deduped. Verified B110 → **1 class / 2 hours**
(AMCS2093(L)). Spec TC36 4→5 cards, TC37 ids, TC58 mobile count,
new TC38b (B110 weekly pattern).

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
TC34/35 → 7 legend items; new TC35b (owner-gating on venue); TC45–48
tooltip tests now scroll-settle before clicking (auto-scroll race with the
by-design scroll-hide, exposed by the taller 7-item legend); suite green.

### Postscript — diagonal hatching removed; loud red = 3px border only (2026-10-09)

The 45° caution stripes on `.event-conflict` proved too busy — reverted to
a plain `--color-error-container` tint, with the border thickened
2px → **3px** so own/personal conflicted + public-holiday classes still
read unmistakably (owner-gating unchanged: loud border = yours, quiet
plain tint = others' on cohort/venue; personal pages all loud). Legend
swatches follow automatically. Verified live on all four pages.

### Postscript — own public-holiday classes now render on the venue grid (2026-10-09)

Holiday days used to flatten the whole day into empty 'PH' cells. Now the
logged-in lecturer's OWN classes on a public-holiday day still render — as
loud red `event-conflict` blocks (3px border, parity with my-timetable:
"your class won't run"), with full modal interaction. Other lecturers'
holiday classes and Sundays stay empty `cell-ph`/`cell-sun` cells.
Summary cards unchanged (offday events still excluded from My Teaching /
counts — visible-but-not-counted is intentional). Legend 'Your Conflict'
tip updated. Verified: B011 W14 own `AMCS2093(P)` Fri loud (Thu Christmas
Eve cells empty), B110 W8 own `AMCS2093(L)` Mon loud, B006 W8 all-others
day fully suppressed (22 PH cells).

### Postscript — venue dropdown: B006 under Lab + full-name tooltips (2026-10-09)

- **CiscoLab (B006) now groups under the Lab category** in the cascading
  VenueDropdown (Type → Block → Floor → Room) — Type column shows 3
  categories; B006 sits with B005/B009/B010/B011 under Block B → Ground
  Floor. Registry `type: 'CiscoLab'` untouched (dropdown-only
  normalization, `_typeOf()`); the room tooltip still reveals its true
  "Cisco Lab" identity.
- **Every dropdown option now carries a hover tooltip** (shared data-tip
  utility — fixed, shown above the item, viewport-clamped): room options
  show the full name (`B006 · Cisco Lab · Ground Floor, Block B`); parent
  options (Favourites/Recent/type/block/floor rows) show descriptive
  counts (`Lab — 5 venues`, `Block B — 5 venues`, …).
- TC23 → 3 categories; new TC23b (Lab cascade includes B006, tips present,
  hover reveals true name above the item).

### Postscript — hairline block borders + status tooltips (2026-10-09)

- **Venue page only:** regular event blocks get a `0.5px` hairline border
  (`--color-outline-strong`) for definition — scoped via the blade's
  `page-styles` (`#timetable .event-block:not(.event-conflict)`); the
  viewer's own conflict / public-holiday blocks are excluded and keep their
  loud 3px border. (Renders as a 1px hairline at 1× DPR.)
- **Block tooltips now end with the run-status:** the venue cellRender tip
  is `"Subject · Lecturer · Status"` — the status label comes from the new
  shared `eventStatusLabel()` helper in ui-common (Normal / Pending /
  Replacement / Conflict / Cancelled, with a public-holiday day outranking
  the event's own status as "Public Holiday").

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

### Postscript — tooltip parity + card keywords (2026-10-09, SDD: warning-modal-keywords)

The print button's native `title=` tooltip moved to the shared `data-tip` bubble system
(same text), and the venue modal's close ✕ now shows a "Close" tooltip like every other
modal. The five summary-card flip-backs carry the new keyword language — one bold-uppercase
keyword per description (`FREE TIME SLOTS`, `YOU TEACH`, …), with the "Cannot book" card's
keyword in red (`CANNOT BOOK`) since it's an attention card.

### Postscript — toolbar row parity (2026-10-10, SDD: venue-toolbar-row-parity)

- **Venue + week selectors back on ONE toolbar row** (frozen fjing layout,
  `f8b35a2` template): the two separate `.semester-bar` rows left by the
  2026-10-09 DB wiring (`adc95bb`) are merged — venue dropdown (with capacity
  span) first, then week nav (arrows · week select · Today · print stub),
  matching the cohort-timetable pattern. No SDD had authorized the split; it
  was collateral restructure from the first DB wiring that later parity passes
  (colour/legend/cards only) never re-checked.
- **Print button restored** via the shared `ui-week-nav` `showPrint` stub
  ("Coming soon" tooltip + toast on click) — silently dropped in the same
  DB-wiring commit.
- **Fav-star ☆ stays dropped** (documented divergence): mock localStorage
  feature with no DB-page justification; the capacity span stands where
  fjing's fav-btn sits.
- Week subtitle stays under the merged bar (shared `ui-common.js` element;
  carries the week date range our select options don't show — unlike fjing,
  which has no subtitle).

### Slice B postscript — read path unchanged, write path landed alongside (2026-10-10)

Slice B shipped the booking write path (arrangement + submit through OCC).
This page stays read-only by design (design row: "select venue/week"); no
booking affordance was added here — start replacements from My Timetable or
Replacement Arrangement home. No code change in this file for Slice B.

## 2026-10-13 — Pending replacement blocks now render (SDD venue-pending-and-type-labels, U2b/U3)

Reserved replacement slots (time_slots with `class_session_id NULL`, status `pending`)
now emit events: class facts from the request's ORIGINAL session (the class being
replaced occupies the new room), `status 'pending'`, `mine` = proposer. The blade
renderer/legend were already pending-aware — this was a PHP data-source fix only;
zero UI/CSS changes. Live-verified as 5770: req #1's slot (B110 · label-Week 4 · Thu
12:00) renders an amber block, 3px border (mine), tooltip "Lim Jia Zheng · Pending".
NOTE: the venue weekSelect is 0-based (option value 3 = label "Week 4") — probe by
option LABEL, not value. Others' reservations render hairline amber (feature-tested;
no live foreign fixture exists).

### U2a postscript — arrange affordance port (SDD venue-page-book-affordance, 2026-10-15)

The frozen template's booking affordance was ported onto the live page: free cells are
bookable, structure byte-faithful to `venue-timetable-UI-design-template.blade.php`
(scope: `.sdd/changes/venue-page-book-affordance/proposal.md`, frozen ×4 rounds; design
round-confirmed ×2 independent rounds; tasks round-confirmed). Zero PHP changes.

#### User-decision copy changes (verbatim, decision date 2026-10-13 — wording deviations recorded as user decisions)
| Where | Frozen template text | Shipped text (user-approved) |
|---|---|---|
| Legend tip (Available entry) | *Free slot — click to book this venue (Sunday, holiday and lead-time slots can't be booked)* | *Free slot — click to arrange a replacement in this venue (Sunday, holiday slots and slots less than 3 working days away can't be booked)* |
| Free-cell hover label | `Book ?` | `Arrange ?` |
| Tooltip sentence | *Book {code} on {day}, {date} at {time}?* | *Arrange a replacement in {code} on {day}, {date} at {time}?* |
| Tooltip button | `Book` | `Arrange` |
| No-fit hover label | `Not bookable` | `Too late to book` |
| Hint line | *Slot booking arrives with the replacement workflow (Slice B).* | *Click any green empty slot to arrange a replacement in this venue.* |

#### Declared behavior change (Reviewer round-1 🔴 fix, D1)
- **The page now opens on the REAL current week.** `MockData.mockNow = null` joins the
  four in-repo precedents — the too-soon branch (`isSlotTooSoon`, 3-working-day window)
  and week-open/Today now run on the real clock, not the mock demo anchor (Oct 5).
  Week arrows/dropdown unchanged.
- **Frozen box-shadow literal exception** (`.available-tooltip`,
  `0 4px 16px rgba(0,0,0,0.12), 0 1px 4px rgba(0,0,0,0.06)`): kept byte-faithful per the
  99.9%-UI rule; arrangement-page precedent (`:64/:68`).
- **Dead CSS removed**: the page-inline `.cell-empty` tint rules (:16–22) — no `.cell-empty`
  is ever painted here again (free cells are `.cell-content.cell-available`; too-soon
  cells `.cell-too-soon` with the shared "Min. 3 working days ahead" label from theme.css).

#### Test reconciliation (design D5; facts recorded in the SDD review-log)
- **Login scaffolding** (one login per run): `tests/auth.setup.ts` (setup project) →
  `.auth/staff-5770.json`; `playwright.config.ts` gains the `setup` project +
  chromium `dependencies`/`testIgnore`; per-file opt-in only — `venue-timetable.spec.ts`
  never logs in per test (Fortify 5/min limiter). `.auth/` gitignored.
- **Authenticated baseline** (pre-change blade, TC70 excluded): 65 tests → 47 passed /
  18 failed; **TC49/TC51 attribution settled**: `#bookingBanner`/`#errorBanner` are genuinely
  absent from the live blade (not auth-redirect).
  (prose-precision note: baseline arithmetic counted the file at 66 tests.)
- **Dispositions (all with decision notes in the spec):**
  TC46 reworded to the Arrange sentence + button; **TC49–TC51 retired** (banners don't
  exist; the real handoff banner lives on the arrangement page, covered by
  `venue-book.spec.ts` U2a-T3); **TC70 retired WITHOUT EVER EXECUTING** — mock-era fixture
  whose selector-repair would drive the real destructive `CancelClass` write against the
  demo DB; **TC12/TC13/TC61 retired** (`#favStar` — documented divergence, never modify
  the page to satisfy a test); **TC39–TC44 re-pointed** `#eventModal`→`#classModal`
  (TC41's "CODE — Type (N seats)" → live plain venue-code row); **TC35b retired**
  (mock fixture; live owner-gated conflict coverage stays in venue-db.spec.ts);
  **TC60 re-pointed to URL-as-state** (venue switching is a `?venue=` full render);
  **TC37 re-pointed** `#sumUnavailable`→`#sumOccupied` (documented deviation);
  **TC10/TC25 re-pointed** to the trigger label (closure-scoped dropdown);
  **TC69 retired** (duplicate of TC11); TC03/TC04 text re-points (live header/chip copy).
- **Result**: venue-timetable.spec.ts 56/56 green; venue-book.spec.ts (new, 6 tests:
  hover overlay, exact tooltip sentence, end-to-end handoff with `#bookingIntent`
  banner, too-soon read-only, B/Escape/scroll keys, no-fit toast) 6/6; venue-db
  incl. re-pointed holiday assertion green; true full-suite gate result recorded in the
  SDD review-log (pending user decision on the never-gated dormant layer).

#### Honest DB note (2026-10-15)
`replacement_requests` in the demo DB (`class_replacement_fresh`) is currently empty —
the handover-era pending fixtures (reqs #1–#3 incl. slot 27309 B110 W4 Thu 12:00) are
not present; slot 27309 is `available` v1. Nothing was modified by this change.

### Session-choice modal postscript (SDD arrange-intent-session-choice, 2026-10-15)

The tooltip's **Arrange** button now opens the **"Select a Session"** modal (new partial `partials/ui-intent-session-modal.blade.php` — purely shared shell: `.modal-overlay`/`.modal`/`.modal-footer` + bottom-sheet ≤768px from theme.css) instead of navigating directly:

- **Subject + session dropdowns list ONLY vids/classes that may legally run in the venue** (raw rule data from `App\Services\LectureSessionCatalog::ruleMap()` — P→lab∪cisco_lab, T→tutorial, L→lecture_hall; B005 hides sessions whose cohorts are `D*`; capacity intentionally NOT re-filtered here — the engine still rejects honestly at submit). Preselected: first subject in dropdown order with the first fitting session (Occurrence grain — mixed-type modules bind their compatible occurrence, not the module's first).
- **Session labels byte-frozen (user decision 2026-10-15):** `{module} {name} ({type}) · W{n}, {day abbr}, {DD Mon YYYY} · {HH:MM}–{HH:MM}` — the date/week facts come from the server (one source).
- **`[Cancel]`/Escape = nothing happens** (focus restored to the cell). **`[Arrange]`** → the 8-param handoff `/replacement-arrangement?venue&date&time&from=venue-timetable&code&session&cohort&week` — the frozen template's original `code/cohort` contract finally genuinely filled; `session`+`week` ride the consumer's native pinned-occurrence contract (server-resolved, ownership-checked).
- **Timing:** the day-end fit is checked per-session client-side (`hour + durationSlots ≤ hours.length`) — durations >2h can now be offered; sessions that don't fit before the day end are simply not offered. If NOTHING fits: the modal shows (a) *"None of your classes can run in {venue} — {room type} hosts {type} sessions only."* and, when durations are the only miss, (b) *"…and none of them fit before the day end ({last slot})."* — user-frozen copies (2026-10-15).

Supporting: `VenueTimetable` gains the wire-callable `intentSessionOptions(week1)` (owner-pinned) + the shared `App\Services\LectureSessionCatalog` (rule map + enriched courses payload — the OOP promotion; `coursesPayload` delegates, shape preserved + additive `order` array). Consumer-side honest-copy + fallback documented in the arrangement page's changelog.

#### Zero-fit "Jump to Week" hint (user-reported gap, fixed 2026-10-16)
The conflicted-only policy means most weeks show *"None of your classes can run in…"* — with
no pointer to where the conflicts ARE. The modal's wire model now carries `conflictWeeks`
(all weeks whose conflicted occurrences' types this venue admits — `LectureSessionCatalog::conflictMapFor`),
and both zero branches append *"Your conflicted classes are in Week X and Week Y."* with one-click
**Jump to Week X** buttons (closes the modal, jumps the week; the user then clicks any free-span cell
and the modal offers that week's conflicted sessions). Root-cause fix included: the hint's type-derivation
must use the lecturer's session collection (at a healthy week the week-mapped payload's session map is
empty by design) — the initial `array_intersect`-over-keyed-map bug reported `ok:false` for the valid W8.

#### Week-agnostic candidate pool (USER DIRECTIVE 2026-10-16, ~17:30)
User corrected the conflicted-only semantics: "**a week 14 conflict class can be arranged at week 6**"
— a conflicted class may be arranged at ANY week past the lead time; the conflict week is where the
need LIVES, not the only week the arrangement may target. The old message "None of your classes can
run in B011 … Your conflicted classes are in Week 14" at W6/B011 was a restriction bug, not a rule.

- **Producer pool now week-agnostic** (`VenueTimetable::intentSessionOptions` via new
  `LectureSessionCatalog::conflictedFor()`): candidates = every lecturer session with ≥1 conflicted
  occurrence anywhere in the semester whose type this venue admits (venue-entry + B005 rules) —
  regardless of the viewed week. W6/B011 now offers AMCS2093 (P); W5/B110 offers AMCS2093 (L).
- **Session label** carries the class's own recurring slot — `CODE (Type) Day · HH:MM–HH:MM` — no
  occurrence week/date (that belonged to the incidence-view; the arrangement week is the clicked cell).
  `dayAbbr` is now emitted in facts-only mode (occurrence-independent).
- **"Jump to Week N" buttons unchanged** — pure navigational hints to where conflicts live.
- **Zero-fit message** now only fires when NO conflicted session type is eligible for the venue at
  all (semester-wide) — e.g. the L-only lecturer on a lab.
- **Consumer fallback parity**: `ReplacementArrangement::intentConflictsPayload()` is semester-wide
  (service-defined), so a hand-typed W6 intent picks the W14-conflicted class the same way.
- **SDD note**: this is a user-directed semantics amendment to the frozen 99.9%-UI contract, recorded
  here with date; tests A1/A2 label regexes re-pointed to the recurring-slot format.

#### Occurrence-grain session rows + hover details tooltip (USER REQUEST 2026-10-16, ~18:30)
The session picker becomes an **occurrence-grain custom dropdown**: each option is one FLAGGED
occurrence and shows exactly `({type}) W{week}, {Day}, {Mon D} . {HH:MM} ~ {HH:MM}` (e.g.
`(P) W14, Thu, Dec 24 . 13:30 ~ 14:30`). Native `<option>` elements cannot host hover tooltips,
so the picker follows the shared venue-dd trigger/panel pattern: a trigger button + floating
panel (`.session-dd` family added to theme.css — token-only geometry/typography mirroring
.venue-dd), rows highlight on hover and on selection, outside-click closes, [Arrange] books the
picked session at the clicked cell (the 8-param handoff contract is unchanged).

Every row carries the **shared data-tip** (ui-common `initDataTipTooltips` — the same system as
the legend/table headers/modal close button), showing the full details: module code + name ·
student count + cohort · the class's usual venue/day/time · the conflict reason.

- Service: `conflictedFor()` now returns per-session occurrence facts (week, dayAbbr, `d M Y`
  date, `M j` label, reason) via the cached weekContext Mondays; the component decorates entries
  with `conflictOccurrences`; the week stays selectable (see the same-day week-agnostic amendment).
- Tests: A1/A2 re-pointed to the label format + a data-tip presence/content assertion.

#### Honest zero-reason split — span vs day end (user report 2026-10-16)
Landing on a free cell whose span runs into an existing event below (e.g. B002 Monday 13:30 with
the recurring 14:00–15:00 class beneath it) used to blame the day end ("they do not fit before the
day end (18:30)") even though the actual blocker was the occupied 14:00–15:00 cells. The
`refreshSessions` filter now counts the two failure kinds separately:
- span-blocked → *"None of your classes can run in {venue} at this cell — {HH:MM} plus the class
  length runs into an existing event below. Pick a cell with enough free room beneath it."*
- truly past-the-day-end → the original day-end sentence.
The conflict-week hint sentence stays on both branches.

#### Conflict-week hint + jump buttons REMOVED (USER REQUEST 2026-10-16, ~19:30)
User review: "why do we need this tho?" — the *"Your conflicted classes are in Week 8 and Week 14."*
sentence and the **Jump to Week N** buttons are gone. Rationale (user): the pool is week-agnostic —
the hints implied a restriction that does not exist. The notice sentences are now closed:
- venue-incompatible → *"None of your classes can run in {venue} — {room type} hosts {types} sessions only."*
- span-blocked → *"None of your classes can run in {venue} at this cell — {HH:MM} plus the class
  length runs into an existing event below. Please book with at least a {n}-hour gap between this
  block and the next event — pick a cell with enough free room beneath it."* ({n} = the smallest
  candidate class length, in hours)
- past-the-day-end → the original day-end sentence.
Server-side: `VenueTimetable::intentSessionOptions` no longer emits `conflictWeeks` (consumer removed);
`conflictedFor()` still drives the week-agnostic candidate pool.

#### Rich notice typography (user-approved 2026-10-16)
Approved treatment applied to ALL THREE modal notices for consistency:
- **Bold CAPS error-red head** (`var(--color-error)`, `text-transform: uppercase`)
- **Muted reason line** (`var(--color-on-surface-variant)`, 13px)
- **Bold CAPS amber guidance** (`var(--color-warning)`) + muted remedy (span-blocked case only)
- Line-height 1.5 / 1.55, 4–8px gaps; token-only `.intent-notice .notice-*` block in theme.css.
Sentences split:
1. span-blocked: `NONE OF YOUR CLASSES CAN RUN IN {V} AT THIS CELL` / `{HH:MM} plus the class length
   runs into an existing event below.` / `PLEASE BOOK WITH AT LEAST A {n}-HOUR GAP` / `Between this
   block and the next event — pick a cell with enough free room beneath it.`
2. day-end: `THEY DO NOT FIT BEFORE THE DAY END ({18:30})` / `None of your classes can run in {V} —
   pick an earlier cell.`
3. venue-incompatible: `NONE OF YOUR CLASSES CAN RUN IN {V}` / `{room type} hosts {types} sessions only.`
