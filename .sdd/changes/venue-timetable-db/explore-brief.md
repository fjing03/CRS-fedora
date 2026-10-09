# Explore brief — venue-timetable-db

Generated 2026-10-08. User ask: make `/venue-timetable-ui` retrieve and use real
database data (currently a legacy mock template). Runs as a standalone SDD change —
**pre-empts the venue-timetable page of Wave 3b Slice B** (to be recorded
declaratively in the Wave-3b explore-brief addendum; Wave 3b itself stays parked).

## Current state (verified)

- Route serves the legacy template because `class_exists(App\Livewire\VenueTimetable)`
  is false (single-point branch, routes/web.php:88; `mock_fallback=false`).
- Legacy template consumes `window.MockData` exclusively (10 refs; no fetch/api calls
  anywhere in views or public/js — the 8 `/api/*` routes are orphaned).
- DB (real, post-import): `time_slots` 38,640 rows = 23 venues × 14 weeks × 6 days ×
  20 slots/day (08:00 grid), status **available 34,677 / occupied 3,963 / pending 0**;
  `class_session_id` links to real sessions; `version` column for OCC; partial UNIQUE
  index `(venue_id, day_of_week, start_time, week_number)` WHERE status IN
  (pending, occupied) enforces no-double-book.
- Semester: 202605, 2026-09-21 → 2026-12-27, 14 weeks. Holidays (DB, canonical):
  W8 Mon in-lieu, W14 Thu + W14 Fri (+ W7 Sun Deepavali row, no grid cell — Sunday
  isn't in the 6-day grid).
- Slice A house pattern: Livewire `Component` + `render(): View`, eager loads
  `with(['module','venue','cohorts.programme','lecturer'])`, ordered by day/start.

## Scope decision (the big one)

**READ path only.** The legacy page has two functional layers:

| Layer | In scope? | Why |
|---|---|---|
| Timetable display: grid, slot states (available/occupied/pending), class-details modal (module, lecturer, cohorts, type), summary cards, legend, week nav, venue dropdown + venue-type filter, semester chip, favourites/recent (localStorage) | ✅ YES | Pure reads from `time_slots`/`class_sessions`/`venues`/`semesters` — exactly the user ask |
| **Booking flow**: available-slot tooltip "Book…", Book button, booking banner/hint, lead-time note, pending-slot creation | ❌ NO | Write path — creates `pending` slots under OCC (`version`), belongs to Slice B's replacement-request workflow. A fake Book button on real data would be misleading; v1 disables it with an honest hint |

## Final solution mapping (mock → DB)

| Legacy (MockData) | New (DB) |
|---|---|
| `MockData.venues` (dropdown, type filter, favourites) | `venues` table (23 rows) + `type` column filter |
| `MockData.semester` chip / `WeekNavigator` weeks | `semesters` row (202605, start 2026-09-21, 14 weeks); week = 1–14 |
| Mock slot states per venue/week | `time_slots WHERE venue_id AND week_number` (≈120 rows/query) |
| Mock "class at slot" details | `class_session.-> module, lecturer, cohorts` eager-loaded; modal shows real module code/title, lecturer, cohort codes, session_type |
| "Your Classes" legend state | `class_session.lecturer_id === auth user's lecturer id` |
| Mock summary cards (Total/Available/Pending/Unavailable) | computed from the same slot query (occupied 3,963 real) |
| Holiday handling | holiday days (from `holidays` table) render as holiday cells; no bookings exist there by import rule |

Serving: `App\Livewire\VenueTimetable` — the single-point branch flips the route
automatically once the class exists; legacy template untouched as fallback.
Deep link: the `?venue=` param is preserved (the legacy `code`/`cohort` params belong to the dropped booking flow; there is no `week` URL param — week uses today + saved state as in Slice A).

## Rejected approaches and why

- **Consume the orphaned `/api/*` endpoints from JS** — Slice A precedent is direct
  Eloquent in Livewire; adding an API client would create a second data path to
  maintain. (API layer stays orphaned; Slice B may use it later — separate decision.)
- **Also implement booking in this change** — write path with OCC + request records
  is Slice B's core; mixing a read feature with the booking workflow would balloon
  scope and review surface.
- **Regenerate mock-data.js from DB** — treats the symptom; the goal is retiring the
  mock layer for this page, not re-synchronising it.

## Known open questions (for proposal/user)

- (a) Booking affordances in v1: hidden entirely vs visible-but-disabled with hint
  (lean: disabled + honest hint "Booking arrives with the replacement workflow").
- (b) Venue-type filter labels: reuse the legacy 3 types mapping onto `venues.type`
  values as they exist in DB (verify column/values during design).

## Test plan sketch

- Feature: component renders real counts for a known venue/week (e.g. total = 120
  slots, occupied matches DB); holiday weeks show holiday annotation; guest redirect.
- Playwright: venue dropdown lists 23 real venues; occupied cell shows real module
  code; summary cards numeric and DB-consistent; week nav repaints.
