# Design — Replacement Arrangement mobile day view

Batch 2, 2026-11-11. Baselines: frozen `proposal.md` and `explore-brief.md`.
This document specifies implementation, but does not authorise code changes.
Baseline amendment: user-approved borderless arrangement rule from `28f9f4d`
supersedes prototype ownership rings/hint; full-chain re-review recorded in log.

## 1. Chosen architecture

Use a **page-local mobile presentation adapter**. Keep the current full table
rendering as the canonical client-side availability representation, including on
mobile, but hide/inert its wrapper there. The mobile timeline reads its resolved
cell states after `buildTimetable()` finishes. It never classifies raw payloads
or decides availability independently.

```
PAGE_DATA / shared calendar -> existing buildTimetable + classifyState
                                      |
                             canonical table cells
                                      |
          existing selection store -> mobile snapshot -> timeline/sheets/bar
                                      |
                     existing selection/remove/proceed functions
                                      |
                           existing server validation/OCC
```

This avoids a risky availability-engine extraction during a mobile-only change.
Hidden table rendering costs no more cells than the page already builds. It is
intentional compatibility infrastructure, not a second user-visible timetable.
No decision may depend on its bounding boxes or visibility. Existing hover preview
is not run for mobile buttons. Selected spans are drawn from the saved store,
not by copying the desktop overlay or its colspan geometry.

Alternatives rejected: rewrite availability in the mobile adapter (drift); deeply
refactor desktop rendering now (scope/risk); CSS-only restyling of the weekly table
(does not provide an accessible one-day interaction).

## 2. File boundaries and module interface

| File | Responsibility |
|---|---|
| `resources/views/livewire/replacement-arrangement.blade.php` | Page hooks; snapshot builder; semantic mobile mount points; narrowly scoped responsive CSS; preserve existing logic |
| `public/js/arrangement-mobile.js` (new) | Mobile controller: view/day state, DOM rendering, node relocation, sheets, focus/resize/clock lifecycle |
| `resources/views/partials/ui-arrangement-mobile.blade.php` (new) | Mobile-only context, controls/tools/timeline/action hosts and one information-sheet shell |
| `tests/arrangement-mobile.spec.ts` (new) | Mobile integration, breakpoints, guards and parity receipts |
| `tests/arrangement-mobile-unit.test.cjs` (new, dependency-free Node test) | Pure date-choice/snapshot adapter utilities as appropriate; no fabricated browser receipts |
| `page-changelogs/replacement-arrangement-changelog.md` | Dated change and verification limits |

No backend/schema changes. No shared `theme.css`, `ui-common.js`, layout, icon or
other-page change is planned. Reuse existing shared tokens/helpers/icons without
altering their contracts. Existing unrelated shared-file edits stay untouched.

One `ArrangementMobile` controller instance is created inside the existing page
DOMContentLoaded closure **after** its boot/URL/intent resolution. It receives
ports into that closure; no new booking state becomes a global singleton:

| Port / lifecycle | Contract |
|---|---|
| `readSnapshot()` | Return current canonical cells, real dates/eligibility, original context, venue/week, saved selections and Tip wording; read-only |
| `chooseWeek(index)` | Existing `weekNav.selectWeek(index)`; existing save/rebuild hooks run |
| `chooseAvailable(dayIndex, hourIndex)` | Call existing `toggleCell()` using the actual canonical cell, not synthetic availability |
| `removeSelection(venueKey, weekIndex)` | Existing `removeSavedBlock()`; preserves booking-intent consumption |
| `submit()` | Existing `proceed()` via its existing button/callback; no new backend call |
| `refresh(reason)` | Re-read current snapshot and reconcile view; safe/idempotent; never write booking store |
| `destroy()` | Restore nodes, close sheet, disconnect observers/listeners/timers, remove mobile insets |

Pure date-choice utilities can be exported under the module's single namespace
for Node tests. Do not add a library or a second module framework.

## 3. Canonical snapshot and refresh ordering

Page snapshot identifies cells by the **original** `dayIndex`/`hourIndex` in
`weekData` and `hours`; filtering never renumbers days or time slots. Week indexes
remain 0-based internally; displayed week number is index + 1.

Each cell snapshot contains `{dayIndex, hourIndex, stateClasses, stateLabel,
detailText, ariaText}` read from `.cell-content`, its existing `.cell-state-label`,
`data-tip` and accessibility text. Copy text, not HTML or desktop event handlers.
Only whitelisted existing state classes are applied to mobile buttons. Missing
or unrecognised canonical cells fail closed (unavailable; never assume green).
Conflict/pending cells without holder text still show their real state label and
date/time/venue; do not invent a lecturer/class or disclose more than the payload.

Saved selection entries are derived from `selectedSlotsByVenue`, keyed by venue
and week, with original day/start/end indexes. The visible grid pointer
`selectedBlock` is not the global summary source. Selected canonical cells may
have `cell-selected` instead of `cell-available`; the adapter draws the selected
span from the store before rendering ordinary cells, and skips only its covered
rows in the current day/venue/week. No mutation of hidden cells by the adapter.

Refresh after: end of `buildTimetable()` (after `loadCurrentWeek()` and counter
refresh), selection/removal counter refresh, original-summary changes and Tip
updates. Use a re-entrancy guard and a queued/coalesced read to avoid recursive
`buildTimetable`/`updateCounter` loops. Before adapter initialisation hooks are
no-ops; initial mount performs one final refresh of the fully hydrated state.

All change/selection commands are serialized through existing callbacks. Pending
confirmation does not optimistically overwrite the displayed committed context;
confirm commits/rebuilds, cancel re-reads the restored original values. The
adapter does not write the subject, original slot, venue or occurrence pin itself.

## 4. Responsive composition without duplicated controls

Use one `matchMedia('(max-width: 768px)')` owner. Desktop DOM/CSS remains the
default. Mobile-specific CSS is rooted under a page-only class and the media
query; do not alter global `.timetable` responsive rules.

Use comment placeholders to relocate the **same existing DOM nodes**, not copies:

| Node | Mobile location | Exit to desktop |
|---|---|---|
| Subject select + slot picker (including panels) | Expanded original context | Original `.toolbar-primary` position/order |
| Venue dropdown and existing venue-side chips | Sticky venue host; compact presentation | Original toolbar centre |
| Existing week navigation/select/arrows | Sticky week host | Original toolbar filters |
| Earliest bookable control | Tools row or immediately below controls | Exact original week-nav position |
| `#summaryTip` | Mobile tools row | Original summary action row |
| `#submitTip` including `#submitRequestBtn` | Fixed mobile actions | Original summary action row |

IDs, node identity, handlers, labels and shared dropdown instance remain intact.
Use explicit references/anchors, not broad selectors guessing the first primary
button. Keep necessary source host containers mounted for existing selectors;
hide empty desktop hosts in mobile CSS. Restore nodes before revealing desktop.
Moving an open dropdown closes its panel first, with no value/selection change.

Mobile compact context summary is text rendered from the same original context
as `renderTitleSummary()`. Do not copy/rewrite the original occurrence resolution
or status precedence. No subject -> existing guidance, with selectors reachable.
Original slot data may be past/holiday: **never filter the original-slot picker**;
only replacement date navigation is filtered.

Hide the desktop-only context strip, visible weekly grid, legend and selection
card on mobile (display:none plus inert on those inactive containers). The full
canonical table stays mounted and is still rebuilt for selection checks. Mobile
roots use `hidden`/inert on desktop. Relocated controls are outside hidden/inert
ancestors. No duplicate tabbable controls, IDs, forms or live regions.

Mobile overrides the page's fixed top header to normal document flow, scoped to
<=768px, so it cannot overlap the timeline. Back/navigation actions and guards
stay the same. Original details scroll away; only compact replacement controls
stick. Do not hide an existing functional action without an accessible mobile
equivalent. Preserve guide access and venue metadata through expanded/tap details.

## 5. Date and navigation state

Controller-only state: active layout, viewed day index, original-context expansion,
open-sheet type/identity, focus return target and transient rendering lifecycle.
No selection or booking ledger is owned by the controller.

Eligible day = present real day, not Sunday, not holiday, and not
`isSlotTooSoon(weekData, weekIndex, dayIndex)`. Derive from the existing helpers;
do not use the prototype clock, calculate notice periods again, or filter by
occupancy. Existing `WeekNavigator` date-only filter remains authoritative.

| Trigger | Viewed-day resolution |
|---|---|
| First mobile mount after boot | Eligible saved/prefilled selection in the current week/venue -> its day; else first eligible day in resolved week |
| Ordinary day tap | Requested eligible original day index; no booking-store writes |
| User week change | Same weekday if eligible, else first eligible day |
| Return from desktop | If current view has a saved selection, reveal it; otherwise retain prior weekday if eligible, else first eligible day |
| Existing summary jump / booking-intent reveal | Resolve exact saved/prefilled target day after existing navigation; explicit target wins over weekday retention |
| Earliest bookable | Existing action resolves destination week; reveal `firstBookableDayIn()` there, including if already in that week; existing flash family highlights the visible mobile day/row |
| Subject/slot/venue commit | Keep resulting existing week/venue defaults/guards; retain viewed weekday if eligible, otherwise first eligible day; reveal a resulting auto-prefilled selection when present |

Whole-semester eligibility count zero -> exact empty message from proposal,
disabled day/week/arrows/Earliest and Submit; hide timeline, retain context/tools
and saved selection inspection/removal. Do not clear saved entries. If current
week becomes ineligible but another is eligible, use existing week navigation to
resolve the first next eligible week, else earliest eligible week. Never leave
an interactive stale hidden-date timeline.

Recheck on tab visibility return, window focus, before any selection/Submit and
at the next local date boundary while mobile is active. Use the existing calendar
clock source and recompute eligibility; no midnight booking-store cleanup or DB
write. If eligibility expires while a sheet is open, refresh data and prevent a
stale booking action without auto-closing readable information. Original date
choices remain available. A saved selection whose own date expires stays
inspectable/removable, but mobile Submit is disabled even if a later eligible
date exists; show a clear request to remove it and choose a bookable date.

## 6. Timeline interactions and saved summary

One row = 30 minutes, with >=44px touch target (48px nominal). Left time scale
uses the existing `hours`/24-hour convention. Data/details use current CRS date
and 12-hour time format with `~`. Selected block spans the equivalent row height,
shows readable start/end plus venue, and never overlaps adjacent rows.

| Tap | Action |
|---|---|
| Green, no subject/original context | Existing gate/guidance; no selection |
| Green, no selection anywhere | Recheck eligibility; call canonical `toggleCell`; existing full-span/budget check, state save and intent consumption |
| Green, selection exists anywhere | Five-second warning to inspect/remove it first; do not replace or clear selection |
| Blue selection | Open selected details; no immediate mutation |
| Conflict/pending | Open existing reason text in details sheet; no select attempt |
| Summary in fixed bar | Open saved selection details even if another day/week is displayed |
| Remove selection in sheet | Close sheet, invoke `removeSavedBlock(venue,week)` once, refresh timeline/summary; restore focus to summary or current day |
| Submit Request | Recheck mobile date state then use unchanged button/proceed confirmation path |

For full-span failure, use the existing shared toast, explicitly 5000ms for mobile
transient feedback; page integration must keep desktop timings/wording unchanged.
Do not use a modal just for a transient mobile selection failure. No optimistic
blue block appears before canonical selection succeeds.

Summary shows short day/date and start/end, plus venue/duration as space permits;
full facts always accessible. Read from all saved entries. The normal invariant
is one occurrence-length block; if legacy store has more than one entry, show a
count and all saved entries in the sheet rather than silently picking one or
discarding data. Each Remove action is keyed to that exact venue/week entry.
This is defensive display, not permission to create multiple new bookings.

## 7. Sheets, legend and Tip

Use one page-local native `<dialog>` information-sheet shell with mobile bottom
sheet styling, heading and >=44px Close. Reuse shared modal visual tokens/CRS
classes where appropriate. Native modal focus containment/inert background is
supplemented by explicit initial-focus/return handling. Escape and Close dismiss;
backdrop tap may dismiss information only. No sheet auto-dismiss timer.

Openers: selected block, summary, conflict/pending, Colours & borders, Tip. All
content uses escaped/text nodes, including plain multiline holder text; no
parsing server text as executable markup or new holder data requests. Sheet
content is bounded by viewport height and scrolls, keeping heading/Close visible.

Legend draws current four swatches using real state classes: free, blue selected,
borderless pending and borderless conflict. Body explains both own/others variants
using current state wording and tap-open details; omit the obsolete ownership
border hint. Pending/conflict stay borderless for everyone, including yours.
Keep canonical ownership classes/data for labels and permissions, not styling
rings. Blue selection keeps its existing outline and is not an ownership cue.
Tip uses current state-aware message. In mobile mode suppress only this relocated
trigger's shared hover/focus `data-tip` so a floating tooltip does not appear on
top of its sheet; keep its accessible name and controller message. On desktop
restore its current (not boot-stale) `data-tip` and existing hover/focus behaviour.
All other pages' tooltips remain unchanged.

Before opening an existing booking confirmation or critical alert, close the
information dialog first. Existing modal and handlers stay authoritative; use
mobile-scoped sheet-style sizing/44px actions if needed, never replace its
confirmation or cancel callbacks. Critical errors/confirmations do not time out.
Prevent multiple visible modal layers and accidental submit on information
buttons (`type=button`). The current source has no submit busy latch; add the
page-owned mobile-origin submission guard specified in §12.3. Mobile refresh
must never re-enable a busy Submit button.

## 8. Geometry and accessibility

| Element | Design constraint |
|---|---|
| Breakpoint | Mobile <=768px; desktop >768px |
| Touch targets | All controls >=44×44px, including removal, days, arrows and Close |
| Timeline rows | 48px nominal; allow growth for text, never clip long reasons |
| Sticky control cluster | Venue/week row then eligible-day row; day picker alone can scroll within its container if necessary |
| Fixed bar | Summary + original Submit; wrap to two lines if needed at narrow width/large text |
| Bottom sheet | Maximum 85dvh, internal scroll, safe-area padding |
| Safe areas | Account for `env(safe-area-inset-top/bottom)` without changing shared desktop layout |
| Page inset | Measured action-bar height + safe area + breathing room, not a guessed fixed pixel footer |
| Scroll targets | `scroll-margin-top` based on sticky height and bottom inset; no row behind overlays |
| Toast | Page-local mobile offset above measured fixed bar; no shared other-page position change |
| Focus | Visible ring; named buttons; selected day `aria-pressed`; slot name includes state/date/time |
| Announcements | One polite selection/status message after user action; avoid announcing every refresh/row |
| Motion | Respect reduced motion; no reliance on animation for state |

Use ResizeObserver to measure sticky/action heights and update page-root custom
properties; no global document variables affecting other pages. The current
`resources/views/layouts/ui-template.blade.php:5` already provides
`viewport-fit=cover`; reuse it without a layout change. Safe-area behaviour still
needs device/browser verification before being reported as verified.

## 9. Lifecycle and resize safety

- Bind once, with owned listener/observer cleanup; do not rebind on each rebuild.
- Enter mobile: clear transient hover preview, record/close open panel, relocate
  nodes, hide/inert inactive hosts, resolve day, render. Do not rebuild booking
  data or select a block just because of resize.
- Exit mobile: close information sheet, remove mobile overlays/insets, restore
  exact controls/Tip attributes, reveal original desktop hosts. Selection store,
  week, original occurrence and venue remain intact; no save/load navigation
  side effects solely due to breakpoint changes.
- Sheet focus return uses a surviving opener or suitable visible fallback; if
  an opener was replaced by timeline refresh, focus the corresponding rebuilt
  button by identity, else the current day/summary.
- UI refresh while modal confirmation is open must not steal focus or reset its
  callbacks. Avoid geometry/focus work on inactive canonical cells.
- Tie destroy/remount to existing page teardown if navigation replaces this
  page. No global listener leak across visits; existing Livewire guards retained.

## 10. Verification plan for later specs/tasks

| Area | Required evidence |
|---|---|
| Calendar | Sunday/holiday/too-soon hidden; exact notice boundary; retained occupied dates; missing weeks; no-eligible-semester and midnight expiry |
| Navigation | Same weekday/fallback; Earliest same/different week; original-slot options not filtered; URL/prefill target revealed |
| Canonical parity | Mobile/desktop cell classes, borderless pending/conflict, ownership wording and holder text agree for venue/engine overlap and pending; blue selection outline retained |
| Selection | 1h/2h spans; short gaps/day end refuse; no second block; no-subject gate; selected details; off-view summary/removal |
| Guards | Venue/subject/slot confirm and cancel restore; intent consumption; navigation/leave protections; Submit uses exact saved identity |
| Accessibility | >=44px geometry; keyboard day/slot/sheet use; focus trap/return; no hidden tab stops; no floating Tip duplicate |
| Responsive | 320/390/768/769px + desktop baseline; rotation both directions with selection; text zoom; themes; safe area; overlays not obscuring controls |
| Errors | Five-second transient feedback; readable persistent details and server errors; failure preserves selection; in-flight Submit cannot re-enable |
| Regression | Existing auto-select, original-venue sync, entry/pin guards, ownership, week/summary selection receipts; other-page CSS unaffected |

Current baseline `28f9f4d` already has four legend rows without ownership rings or
hint. Use its borderless assertions (including SlotBorderAndReasonBadgeTest), not
superseded six-row/3px expectations. Record any other baseline drift separately;
do not attribute it to this lane or silently change frozen requirements. Automated browser execution still
requires explicit permission and guarded testing DB/port. Static Node/Blade/PHP
gates and code review do not prove visual/accessibility browser outcomes.

## 11. Promoted to shared

None planned. This is one page's mobile presentation. A shared helper change
requires a demonstrated need, isolated diff and regression evidence, not a
convenience refactor. Reuse existing icons, calendar helpers, toasts, modal skin
and booking methods without duplicating their implementations.

## 12. Integration safeguards (Batch 2 review refinements)

These complete the earlier architecture. They change no frozen proposal decision.
Where a current function needs the narrow hooks below, it is an explicit page
integration change, not a promise to leave that function byte-identical.

### 12.1 Keyboard ownership

The page's document-level `handleKeyDown()` must return immediately, without
`preventDefault`, when mobile mode is active. Its legacy hidden-grid focus,
arrows, Enter/Space, undo, help and week/venue shortcuts must not run on mobile.
Mobile controls are real buttons/selects and keep their native keyboard actions;
their page-local handlers are the only mobile selection/navigation entry points.
Information dialog uses native Escape/keyboard handling. Critical existing modal
Escape handling remains available through a dedicated page-modal branch before
the mobile shortcut early-return; do not route it into hidden-grid commands.

While an information dialog is open, the desktop shortcut handler also returns
without background commands, even during a breakpoint transition before dialog
closure completes. Day/week/venue/undo shortcuts cannot change the booking behind
a sheet. Desktop with no mobile information dialog follows the existing handler
unchanged. Tests exercise Enter/Space on every opener and bracket/undo keys while
a sheet is open, asserting no hidden selection or week change.

### 12.2 Clock reconciliation and retained saved selections

`saveCurrentWeek()` currently saves null when the current pointer is null. A
date-expired saved entry therefore needs explicit preservation metadata. Add a
page-owned `retainedUnrenderableKeys` map keyed by venue/week with the saved
day/start/end identity. This is reconciliation metadata only; selection values
remain solely in `selectedSlotsByVenue`.

On mobile clock reconciliation:

1. Save a currently rendered valid pointer before changing the rendered week.
2. Mark saved entries whose date is no longer eligible as retained/unrenderable.
   Existing store values are not changed. Eligibility uses live helper results.
3. Invalidate the current visual pointer without calling `deselectBlock()` or
   `clearVisualSelection()` (both repaint old selected cells green). Rebuild the
   canonical table from real payload/calendar state. `loadCurrentWeek()` may
   restore only still-available, eligible selections; retained entries leave
   `selectedBlock` null and render their genuine too-soon/blocked cell states.
4. Add a narrow `saveCurrentWeek()` protection: if pointer is null and the saved
   identity matches its retained marker, preserve that store entry instead of
   writing null. If pointer exists, normal save proceeds; do not resurrect a
   missing entry from metadata. This protection persists through resize into
   desktop so navigating there cannot silently erase an already-retained entry.
5. Explicit `removeSavedBlock()`/discard-all/confirmed subject-slot-venue clears
   and successful submission remove matching metadata. Removal of an unrendered
   saved entry uses the existing null-pointer store-removal path, never paints
   expired cells green. Re-eligibility clears the marker once canonical loading
   successfully restores its identity; explicit removal never gets undone by a
   stale marker. Undo requests must pass existing availability and date gates.

The protection only handles entries retained by mobile expiry reconciliation;
ordinary desktop saves, ordinary valid selection behaviour and existing explicit
discard semantics remain unchanged. A discarded pointer must not accidentally
set a marker. Tests cover same-week rebuild -> navigate away/back -> resize to
desktop -> explicit removal, proving expired saved data is retained until an
explicit action and that expired cells never become selectable.

The page calendar-reconciliation hook also owns rebuilding the **existing same
week select's options**, using `weekHasBookableSlot()` on absolute week indexes;
`WeekNavigator._updateSelect()` alone cannot do this. Rebuild options and arrows
on clock changes, retaining the committed eligible week when possible, otherwise
resolve forward then earliest as in §5. With zero options, show a disabled
non-navigable placeholder, disable arrows/Earliest, and do not call a navigator
fallback that exposes an ineligible date. Do not run arbitrary selector change
handlers when rebuilding options. Rebuild only for this arrangement page; no
shared navigator modification. A retained stale selection remains in the summary
and disabled-for-submit across all these paths.

### 12.3 Mobile-origin submission lifecycle

Page closure owns `mobileSubmissionInFlight` and a mobile confirmation identity
(saved entries + current subject/original-slot/pinned occurrence identifiers).
These are command-lifecycle metadata, not another selection store. Existing
desktop requests are not put through new mobile date guards. A request started
on mobile retains its protection if the viewport changes before completion.

| Boundary | Required behaviour |
|---|---|
| Mobile Submit tap | Ignore if mobile-origin request busy; reconcile calendar; reject absent/expired saved entries or missing original gates; call existing `proceed()` to show confirmation |
| Confirmation opened | Snapshot exact presented saved identities and original pin; do not submit yet or start a network timeout |
| Existing Yes callback, immediately before Livewire call | If mobile-origin flow, check busy flag again, reconcile current live date and compare current identity with presented identity; on expiry/change do not send, preserve saved data and show persistent guidance/review again |
| All checks pass | Set busy flag synchronously before call, disable Submit and confirmation Yes, then invoke the existing `submitBlocks` path with the current validated identical arguments once |
| Re-render/counter/viewport change while busy | `updateCounter()`/mobile bar must keep Submit disabled; duplicate Yes/proceed callbacks cannot issue another mobile-origin request |
| Resolved business rejection | Clear busy flag, retain existing failure details/selection and reapply current eligibility gates; no auto-retry |
| Promise rejection or synchronous transport error | Clear busy flag; show persistent request-failure information with explicit recovery, retain selection, no blind auto-resubmit; an unknown server outcome is not described as a confirmed failure |
| Full success | Existing cleanup/redirect runs; keep busy disabled through redirect, then teardown clears lifecycle state |

Implement with a narrow optional mobile-context argument/captured context on
`proceed()` and its existing Yes callback; default desktop context is absent.
The adapter cannot merely guard the button before confirmation. Latch release
belongs to the existing submit promise branch (including catch) and counters
apply both normal gate AND `!mobileSubmissionInFlight`. No backend/OCC rule or
desktop success/rejection wording is changed. An in-flight protected request
does not lose its latch because the controller unmounts on resize.

### 12.4 Explicit reveal/flash port

Add `revealTarget({weekIndex, dayIndex, hourIndex, venueKey, tone})` on the mobile
controller. Page `pulseTargetCell()` / `flashTargetCell()` and existing saved-block
jump completion call it when mobile is active, after the existing navigation
commits; desktop keeps its original element-target path. The target must match
the now-current week/venue and pass date eligibility; otherwise no hidden-date
selection or silent venue switch is performed by this presentation port.

This sets the viewed eligible day, renders, scrolls the visible mobile row with
sticky/footer offsets and uses existing `flashRowPulse` on that visible element.
Same-week/same-venue explicit reveals call it directly (no rebuild is required).
Earliest calls it with the destination from `firstBookableDayIn()`, even when
week does not change. Boot/prefill invokes it only after canonical selection is
resolved; summary jumps use the exact saved day/start identity. The port never
creates a selection or bypasses `navigateToSavedBlock()`'s existing guards.

### 12.5 Additional review-derived receipts

- Mobile Enter/Space remains native; desktop grid shortcuts cannot activate
  hidden cells or navigate/undo behind a sheet.
- Expiry rebuild and same-week selection do not turn null visual pointers into
  silent saved-selection deletion; explicit deletion does not reappear.
- Clock refresh actually removes expired week options, including final option.
- Date expiry while confirmation is open blocks the outgoing call at Yes.
- Repeated Yes/Submit, refresh and rotation cannot duplicate a mobile-origin
  request; resolved rejection and transport error release the latch safely.
- Explicit same-week intent/summary/Earliest reveals target visible mobile rows,
  not the hidden canonical table; no extra booking state is written.
