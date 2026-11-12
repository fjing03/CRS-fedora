# Explore brief — Replacement Arrangement mobile day view

Date: 2026-11-11. User approved the interactive prototype and the decisions below.
This brief is the completeness baseline for proposal review, not implementation permission.

Apply baseline amendment (2026-11-11): after the source check found the newer
`28f9f4d` user ruling, user explicitly chose option 1: follow the current borderless
design on mobile too. This supersedes the earlier ownership rings/hint in the
prototype and this brief's prior ledger. Blue selection outline stays; ownership
is explained by existing state words and tapped details. Prototype is not edited.

## Problem and references

On phones the weekly table becomes day cards with wrapped time cells and horizontal
scrolling. Day labels scroll out of view, selected-block labels overlap, controls
are far from the choice, and the summary/Submit area lies below the whole week.
Hover-dependent explanations are unsuitable for touch.

- Screenshots: `../ui-prototype/Current Version Mobile Design/` (LEFT and RIGHT PNGs).
- Approved mobile prototype: `../ui-prototype/replacement-arrangement-mobile-preview.html`.
- Scope: `/replacement-arrangement`, mobile at <=768px only; no desktop redesign.
- Prototype data and fixed preview clock are invented fixtures, not production rules.
- The top-zone prototype/prompt is a separate task, not part of this lane.

## Confirmed decision ledger

| Area | Approved decision |
|---|---|
| Selection meaning | One continuous block for one class; not several separate bookings |
| Timeline | One day at a time, vertically ordered 30-minute rows, >=44px touch targets |
| Date visibility | Hide Sundays, public holidays and dates too soon to book |
| Weeks | Hide weeks with no date passing those date rules; fully occupied eligible dates/weeks stay visible |
| Day change on week navigation | Keep the viewed weekday when eligible; otherwise first eligible day; do not select a time automatically |
| Time visibility | Show every time on the chosen day, including conflicts/pending |
| Green rows | All free rows keep the same visual treatment; check full-span fit only after tapping |
| Selection | One tap selects the full required class duration, using the existing logic |
| Selected block | One readable blue block; tap opens details, not immediate removal |
| Selected details | Date, time, duration, venue; explicit Remove selection inside the sheet |
| Bottom bar | Always visible; before selection show Select a time with Submit disabled; after selection show compact date/time and enabled Submit under existing gates |
| Summary tap | Opens the same selected-block details; full data comes from the existing saved selection store |
| Conflicts/pending | Tap opens a readable bottom sheet retaining current reason/holder information; close manually |
| Temporary warnings | Transient selection warnings/errors disappear after 5 seconds; no auto-dismiss for details/confirmations |
| Top context | Original subject and original date/time stay visible; cohorts/student count/venue expand on tap; subject/slot choices remain available |
| Sticky controls | Compact venue/week row with eligible-day picker beneath; original context scrolls away |
| Legend | Colours & borders button opens sheet; Available / Your Current Selection / Pending / Conflict |
| Ownership | Pending/Conflict borderless for everyone; no ownership-border hint. Existing state wording/tapped details explain ownership. Blue current-selection outline stays as shipped |
| Tip | Compact icon + Tip beside Colours & borders above timeline; tap opens state-aware guidance |
| Earliest bookable | Retain existing action; reveal its eligible date in mobile day view |
| No dates remaining | No bookable dates remaining this semester; date navigation and Submit disabled; original class context still accessible |
| Desktop | >768px keeps existing appearance and interactions, including selected-block removal behaviour |
| Rules | No change to booking gates, whole-block budget/duration, OCC, payload permissions or submit confirmations |

## Rejected alternatives and reasons

- Repair weekly scrolling alone: retains sideways navigation and small cells.
- Compact button matrix: a multi-cell class crosses visual rows and becomes unclear.
- Available times only: hides the reasons other times cannot be booked.
- Mark only valid starts: user chose existing same-look green rows plus fit check.
- Action bar appearing only after selection: loses persistent direction.
- Immediate tap-to-remove on mobile: risks accidental removal while inspecting.
- Small floating conflict popup: long holder explanations are cramped on phones.
- Auto-closing details: interrupts reading; only temporary warnings auto-dismiss.
- Permanently expanded original context/legend: delays reaching the timeline.

## Current code connections and risks

In `resources/views/livewire/replacement-arrangement.blade.php`:

- `buildTimetable()` renders real payload/engine facts; `classifyState()` owns state labels/classes.
- `isCellAvailable()` / `canPlaceBlock()` inspect the rendered table. Removing that
  table without preserving this contract would break selection and intent handling.
- `selectBlock()` owns duration/budget checks, store writes, counter refresh and intent consumption.
- `selectedSlotsByVenue` is the saved store; `selectedBlock` is just the viewed week/venue pointer.
- `WeekNavigator.onBeforeNavigate` saves the current block; `loadCurrentWeek()` restores it.
- `removeSavedBlock()` handles a saved block even when another week is being viewed.
- `onVenueChange()` confirms before clearing selection. The fixture prototype does
  NOT copy this fully; the system behaviour wins during implementation.
- Subject/original-slot changes, booking-intent arrivals, pin identity, and leave
  guards must remain on their current paths.
- `proceed()` builds the confirmation and server submission from the saved store.

In `public/js/ui-common.js`, `weekHasBookableSlot()`, `firstBookableDayIn()` and
`isSlotTooSoon()` already express date eligibility; use the live clock/calendar,
not the prototype's fixed date. Date eligibility does not mean room availability.

Shared `theme.css` mobile timetable rules plus page overrides currently conflict
with the desired phone layout. New layout must be opt-in/page-scoped, not a change
to every timetable. Hide inactive layout controls from keyboard and assistive tools.

## Cross-module flow to preserve

Real `PAGE_DATA` -> existing calendar/availability classification -> mobile view
of the same state -> existing full-block selection/store -> existing confirmation
and submission -> existing server gates/OCC. Mobile introduces presentation state
(viewed day, open sheet), not an independent booking store or engine.

## Review constraints and follow-up

- No new dependencies, DB/schema changes, or sample data in production.
- Page CSS/theme tokens/shared components follow CRS conventions; date/time copy
  follows current formats and the grid axis remains 24-hour.
- >=44px hit areas, focus management/return, Escape/Close, safe-area padding,
  viewport-fit, screen-reader labels, reduced motion, and no overlays covering actions.
- Test resize/rotation with a saved selection: no loss/duplication or double handlers.
- Empty semester/date expiry must fail safe without deleting saved data silently.
- Desktop parity plus mobile scenarios need tests; Playwright execution remains
  paused until explicit permission, and future writing receipts use testing DB only.
- Existing unrelated modifications (including `public/js/ui-common.js`) must not
  be overwritten, reverted or swept into this lane's eventual commit.
- Implementation needs explicit user approval after proposal/design/spec/tasks reviews.

No unresolved product question blocks the first proposal. Detailed architecture,
sheet mechanics, date-expiry behaviour and test mappings belong to later batches.
