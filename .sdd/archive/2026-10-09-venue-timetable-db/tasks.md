# Tasks — venue-timetable-db

**Frozen baselines:** proposal.md (R2), design.md (R3), explore-brief.md (line-48
erratum corrected). Specs skipped by declaration (review-log). Execute in order.

## T1 — Component `app/Livewire/VenueTimetable.php`
- [ ] `use ResolvesTimetableTimeline;` — `?venue=` read once via
  `request()->query('venue')` (unknown → first venue by room_code); `code`/`cohort`
  params ignored. **Render-only component — NO setVenue/setWeek (unfrozen design §1).**
- [ ] render(): queries per design §2 — venues (mapped to VenueDropdown contract:
  code/name/capacity/type with display labels Tutorial/LectureHall/Lab/CiscoLab);
  events = `TimeSlot::with(['classSession.module','classSession.lecturer',
  'classSession.cohorts.programme'])` where venue, status IN (occupied,pending),
  **no week filter**, grouped into the all-weeks `eventsByWeek` map (index `w-1`),
  mapped to the `baseEvent` contract per design §3 (`di`, `$this->slotIndex()` with
  end−1 inclusive, `code = module_code`, `name = module_name`, `venue` = room_code,
  `mine` via null-safe `?->lecturer?->id`, status normal/pending); totals per §2:
  `groupBy('week_number','status')` → per-week totals JSON.
- [ ] NO trait modifications.

## T2 — View `resources/views/livewire/venue-timetable.blade.php`
- [ ] `->extends('layouts.ui-template', ['activeNav' => 'venue-timetable',
  'pageKey' => 'venueTimetable'])`.
- [ ] Semester chip + `generateWeekData()` + `new WeekNavigator(...)` with storage
  key `venueTimetableWeek`; **prev/next/select client-side** (window.prevWeek/
  nextWeek/selectWeek — Slice A pattern), repaint via `buildTimetableGrid` +
  client-side summary update from the per-week totals JSON.
- [ ] VenueDropdown factory fed with the mapped venues JSON; `?venue=` preselect;
  each dropdown item = `?venue=<code>` link.
- [ ] Shared grid engine init with `eventsData` + `statusClassFn` per design §4
  (mine → vt-cell-yours; pending → vt-cell-pending; else vt-cell-others).
- [ ] `@include('partials.ui-legend-bar')` — 4 items with swatches per design §5
  (Available/Your Classes/Others'/Pending); available hint "Free slot" (no booking tip).
- [ ] `@include('partials.ui-summary-bar')` — Total/Available/Pending/Occupied;
  Pending real value (0) + honest title.
- [ ] Event modal via engine `baseEvent` + extraFields: Cohort / Total Students /
  Capacity / Room Name.
- [ ] One static hint line: "Slot booking arrives with the replacement workflow
  (Slice B)." — NO tooltip/Book/banner/lead-time elements.
- [ ] `MockData.semester/holidays` seeded from trait (same as my-timetable view).

## T3 — CSS `public/css/theme.css`
- [ ] Append exactly three classes per design §5: `.vt-cell-yours` (primary pair),
  `.vt-cell-pending` (tertiary-container pair), `.vt-cell-others` (surface-variant
  pair). Tokens only; no hex/rgb; no other CSS changes.

## T4 — Tests
- [ ] `tests/Feature/VenueTimetableTest.php`: totals match seeded DB (Total = 120
  for a venue-week; Occupied = seeded count); event carries module_code + module_name;
  `mine` true only for own sessions; `?venue=` preselect + unknown-code fallback;
  student → 403 (route role:lecturer); holiday annotation present in the payload
  (component's holidays-for-JS contains the W8-Monday holiday row).
- [ ] `tests/venue-db.spec.ts` (NEW; legacy venue-timetable.spec.ts untouched):
  login 5425 → dropdown lists 23 real venues in 4 type groups; occupied cell shows a
  real module code; modal opens with cohort codes + extraFields; summary numeric;
  week nav repaints client-side; **venue switch navigates with `?venue=` and
  repaints**; zero console errors.
- [ ] Existing suites untouched (nav-identity, timetable-wiring, auth-full).

## T5 — Gates + ledger
- [ ] `php artisan test` → 115 + new feature tests, all green;
  `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → 0.
- [ ] `npx playwright test tests/venue-db.spec.ts tests/nav-identity.spec.ts
  tests/timetable-wiring.spec.ts tests/auth-full.spec.ts` → all green (3 auth-full
  expiry tests skip without the flag).
- [ ] Live smoke: `/venue-timetable-ui?venue=B014` real grid; SQL spot-audit ≥2
  venues × ≥2 weeks incl. holiday week W8 (Mon: 0 events, holiday annotation).
- [ ] Changelog row in `page-changelogs/backend-automated-by-ai.md`.
- [ ] Wave-3b pre-emption: verify the declarative note already appended to
  `.sdd/changes/wire-backend-into-refactored-ui/explore-brief.md` (2026-10-08
  addendum) is present; reference it from the change's execution notes.
- [ ] Commit the change (conventional `feat:`; `.sdd/` artifacts in a follow-up
  `chore(sdd): archive` commit after `/sdd-verify` + `/sdd-archive`). NO push
  without explicit user authorization.

## Standing rules
- Server restart if needed: isolated `pkill -f "[a]rtisan serve" || true` as its own
  command + `rm -f storage/framework/views/*.php` — NEVER `pkill -9 php`.
- No push without explicit user authorization.
