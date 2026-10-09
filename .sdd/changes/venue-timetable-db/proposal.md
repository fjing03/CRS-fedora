# Proposal — venue-timetable-db

**Date:** 2026-10-08 · **Status:** draft (batch 1) · **Size:** medium (1 Livewire component + view, CSS reuse, tests)

## Why

`/venue-timetable-ui` is the last timetable page still rendering mock data: its route
falls back to the legacy template (no `App\Livewire\VenueTimetable` class exists),
which reads `window.MockData` exclusively — now stale against the imported real
schedule (101 sessions, 3,963 occupied slots, 23 venues, semester 202605 with
canonical dates). The other three timetable pages are already DB-backed (Slice A).
The user ask (2026-10-08): make this page retrieve and use real database data.

This change **pre-empts the venue-timetable page of Wave 3b Slice B**; Wave 3b
itself stays parked (user decision). The Wave-3b explore-brief addendum gets a
declarative note.

## What's in scope

1. **`App\Livewire\VenueTimetable`** — new Livewire component following the Slice A
   house pattern (eager-loaded Eloquent, `render(): View`). The route's single-point
   branch (`routes/web.php:88`) serves it automatically once the class exists; the
   legacy template stays untouched as fallback. Deep links: preserve the `?venue=`
   param. The legacy `code`/`cohort` params belong to the dropped booking-banner
   flow and are intentionally ignored in v1; there is **no `week` URL param** in the
   legacy page (never was) — week selection uses today + saved state as in Slice A.
2. **Real data reads** (per explore-brief mapping table):
   - venue dropdown + venue-type filter over the 23 real `venues` rows
     (`room_type`: tutorial 16 / lab 4 / lecture_hall 2 / cisco_lab 1)
   - slot grid from `time_slots WHERE venue_id AND week_number` with real statuses
     (available / occupied / pending) and holiday annotation from the `holidays` table
   - occupied-slot details from real `class_sessions` (module code + title, lecturer,
     cohort codes, session type); "Your Classes" legend state = session owned by the
     logged-in lecturer
   - summary cards (Total / Available / Pending / **Occupied** — DB-status-only
     naming per design §2) computed from the same query; semester chip from `semesters`
3. **Booking flow: disabled, honestly.** The write path (creating `pending` slots
   under OCC `version` + replacement requests) is Slice B scope. v1 renders booking
   affordances disabled with an honest hint ("Booking arrives with the replacement
   workflow") — no fake actions on real data. Final placement decision in design.
4. **Tests** — feature tests (component renders DB-consistent counts for a known
   venue/week; holiday annotation; role gating intact) + Playwright (real venue
   dropdown, real module code in an occupied cell, numeric summary cards, week nav
   repaint, zero console errors).

## What's explicitly out of scope

- Any write to `time_slots` / `replacement_requests` (booking, OCC, lead-time rules) — Slice B.
- The other five legacy pages (`replacement-arrangement`, `replacement-home-ui`, `replacement-history-ui`, `my-request-history-ui`, `request-approval-ui`).
- The orphaned `/api/*` endpoint layer (stays; separate decision).
- Changes to `mock-data.js` (mock layer retires page-by-page; the other legacy pages still need it).

## Success criteria

- With `APP_MOCK_FALLBACK=false` (default), `/venue-timetable-ui` renders the
  Livewire component; all visible timetable records trace to DB rows.
- Grid counts match SQL (spot-audited for ≥2 venues × ≥2 weeks incl. a holiday week).
- Occupied cell → modal shows the real module/lecturer/cohorts for that slot.
- Summary cards numeric and DB-consistent; week navigation repaints correctly.
- Zero console errors; nav bar unchanged; existing Playwright suites stay green
  (nav-identity 3/3, timetable-wiring, auth-full ungated set).
- Gates: phpunit 115/115 → grows with new feature tests, phpstan-1G 0.

## Risks / mitigations

- **N+1 queries on the grid** — one venue-week is ≈120 slot rows; eager-load
  `classSession.module/lecturer/cohorts` in a single query (Slice A pattern).
- **URL param drift vs legacy links** — preserve `?venue=` exactly; `code`/`cohort`
  intentionally dropped with the booking flow (stated, not implied); week is NOT a
  URL param anywhere in the legacy stack (verified) — no `?week=` claims.
- **Legend semantics drift** ("Your Classes" vs "Others'") — §10.0 rule: same name +
  same color for same meaning; colors come from the existing legend tokens.
- **hideNav-style page quirks** — the component uses `layouts.ui-template` like
  Slice A components; the legacy template's bespoke chrome (booking banner) is not
  carried over.
