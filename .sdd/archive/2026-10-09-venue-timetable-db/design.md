# Design — venue-timetable-db

**Date:** 2026-10-08 · **Frozen baseline:** proposal.md (R2 PASS), explore-brief.md (line-48 param erratum corrected at freeze)

## 0. The reuse decision (governs everything)

Slice A promoted a **shared grid engine** (`public/js/ui-common.js`) + a **shared
timeline trait** (`app/Concerns/ResolvesTimetableTimeline.php`) — "promoted on 3rd
duplication per CodingMAIN §10.0.6: MyTimetable / CohortTimetable /
StudentMyTimetable". The venue page MUST reuse both, not build a second renderer:

- **Event contract** (from the trait docblock): each event = `{ di (day index 0–5),
  start/end (30-min slot indexes from 08:00, end INCLUSIVE), code, name, type,
  status, … }`; the engine paints an empty grid as available and overlays events.
- **Engine hooks**: `cfg.statusClassFn(div, e, isConflict)` lets a page map event
  flags to cell classes — exactly the hook the venue legend needs.
- **Shared partials**: `partials/ui-legend-bar` + the summary-card pattern already
  exist in `my-timetable.blade.php`.

New JS is therefore limited to: the venue dropdown wiring (existing factory — the
legacy template already calls it with a `venues` array) + a `statusClassFn`.

## 1. Component — `app/Livewire/VenueTimetable.php`

```php
class VenueTimetable extends Component
{
    use ResolvesTimetableTimeline;                    // semester/weeks/weekData/semesterJs/holidaysForJs

    public string $venueCode = '';                    // room_code, from ?venue= (mount, once — deep link)

    public function mount(): void                     // venueCode = valid ?venue= or first venue; NO week/venue actions
    public function render(): View                    // render-only — ALL interactivity is client-side (Slice A)
}
```

- `?venue=` read in `mount()` via `request()->query('venue')` (read once, like the
  legacy `readUrlParams()`); `code`/`cohort` params deliberately ignored (booking flow).
- **Week nav and venue switching are client-side/render-only** (unfrozen — see
  review-log): week nav = shared WeekNavigator over the all-weeks event map (Slice A
  pattern — the init script runs once on DOMContentLoaded; Livewire morphs do not
  re-execute scripts, so server-side week actions were architecturally wrong);
  venue switching = plain `?venue=<code>` links from the dropdown (full render per
  venue — scripts re-run naturally, deep links native).
- Layout: `->extends('layouts.ui-template', ['activeNav' => 'venue-timetable',
  'pageKey' => 'venueTimetable'])->section('content')` — Slice A convention.

## 2. Queries (render())

| Data | Query |
|---|---|
| Dropdown | `Venue::orderBy('room_code')->get(['id','room_code','room_name','room_type','capacity'])` — 23 rows, mapped server-side to the VenueDropdown contract (`ui-common.js` expects `v.code` + display `v.type`): `code => room_code`, `name => room_name`, `capacity => capacity`, `type => {tutorial→'Tutorial', lecture_hall→'LectureHall', lab→'Lab', cisco_lab→'CiscoLab'}` |
| Events | `TimeSlot::with(['classSession.module','classSession.lecturer','classSession.cohorts.programme']) ->where('venue_id',$venue->id)->whereIn('status',['occupied','pending'])->get()` — **NO week filter**: grouped into an all-weeks `eventsByWeek` map (index `w-1`), so week nav is client-side like Slice A. Busiest venue ≈ 400 rows total |
| Grid totals | same table, no status filter: `selectRaw('week_number, status, count(*)')->groupBy('week_number','status')` → per-week totals JSON (Total/Available/Pending/Occupied per week) for the client-side summary updater — **DB-status-only semantics for v1**: holiday-day rows keep `status='available'` (import rule), so holiday cells are visually annotated in the grid (from the `holidays` table) but NOT re-counted; card 4 is labelled **"Occupied"** (not the legacy "Unavailable" which counted Sunday+holiday) — honest DB-derived naming |
| Semester/holidays/weeks | trait: `timelineSemester()`, `timelineWeeks()`, `semesterJs()`, `holidaysForJs()` |

**N+1 guard:** one events query + one totals query + one venues query per render.
Eager loads cover module/lecturer/cohorts; no per-cell queries.

## 3. Event mapping (into the shared contract)

For each `time_slot` row (t) with class session s:

```
di    = t.day_of_week                      // 0..5 (Mon..Sat) — grid has no Sunday
start = $this->slotIndex(t.start_time)     // trait helper (protected, inherited) — 30-min indexes from 08:00
end   = $this->slotIndex(t.end_time) - 1   // END INCLUSIVE per contract (trait arithmetic, lines 134-139/234)
code  = s.module.module_code
name  = s.module.module_name          // Module model attribute (NOT `title`)
type  = s.session_type
status= t.status === 'pending' ? 'pending' : 'normal'
mine  = s.lecturer_id === auth()->user()?->lecturer?->id   // null-safe (relation nullable)
venue = t.venue->room_code            // baseEvent key — shared modal reads event.venue
+ cohorts (codes) via the baseEvent shape; capacity/room_name as extraFields
```

Emit the **`baseEvent` key set** the shared modal expects (code/name/type/venue/
lecturer/di/start/end + status flags) — no bespoke modal. Modal `extraFields`
(cfg pattern from my-timetable.blade.php): **Cohort / Total Students / Capacity /
Room Name**. Do NOT modify the frozen trait for this change.

## 4. View — `resources/views/livewire/venue-timetable.blade.php`

Structure mirrors the legacy page's information architecture, minus booking chrome:

| Block | Implementation |
|---|---|
| Semester chip + week navigator | same pattern as `my-timetable.blade.php` (`generateWeekData()` + `new WeekNavigator(...)` with `load()`/`save()`, storage key `venueTimetableWeek`); **prev/next/select = client-side** (window.prevWeek/nextWeek/selectWeek), repaint via `buildTimetableGrid` + client-side summary update |
| Venue dropdown | legacy dropdown factory fed with DB venues JSON mapped to the factory contract (§2); `?venue=` preselect; **each item is a `?venue=<code>` link** (full render per venue — scripts re-run naturally) |
| Grid | shared engine `init…` call with `eventsData = @json($events)` and **`statusClassFn`**: `e.mine → 'vt-cell-yours'`, `e.status==='pending' → 'vt-cell-pending'`, else `'vt-cell-others'`; empty cells = engine default (available) |
| Legend | `@include('partials.ui-legend-bar')` with the 4 venue items — **Available, Your Classes, Others', Pending**; hint text reworded: available hint = "Free slot" (the legacy "click to book" tip is dropped with booking) |
| Summary cards | `@include('partials.ui-summary-bar')` with the cards array (my-timetable pattern) — 4 cards (Total / Available / Pending / **Occupied**), DB-status-only semantics (see §2); Pending renders its real value (0 until Slice B) with `title="No pending bookings yet — arrives with the replacement workflow"` |
| Class-details modal | reuse the engine's event-modal (module code + title, lecturer, cohorts, type, room) |
| Booking affordances | NONE rendered — no tooltip, no Book button, no booking banner/lead-time note; one static hint line under the grid: "Slot booking arrives with the replacement workflow (Slice B)." |

## 5. CSS (theme.css append — small)

Only three additions — colors from the §10.0 canonical map, pinned so the legend
swatches and cells agree exactly (same name = same color):
`.vt-cell-yours` = `--color-primary` / `--color-on-primary` (Your Classes);
`.vt-cell-pending` = `--color-tertiary-container` / `--color-on-tertiary-container`
(Pending — §10.0 maps A/C; matches the engine's `.event-pending` and the legend
partial's Pending swatch; NOT warning/amber); `.vt-cell-others` =
`--color-surface-variant` / `--color-on-surface-variant` (Others' — neutral,
distinct from Available). Legend swatches pinned to the same four pairs:
**Available = success, Your Classes = primary, Others' = surface-variant pair,
Pending = tertiary-container pair** (NOT the legacy green Others' swatch — that
would pair a green swatch with neutral cells). Empty cells = engine default
(available). No other CSS changes.

## 6. Tests

**Feature — `tests/Feature/VenueTimetableTest.php`** (testing DB, seeded per test):
- lecturer sees the component; grid totals match seeded DB for a known venue/week
  (Total = 120, Occupied = seeded count);
- occupied cell event carries the right module code; `mine` true only for own sessions;
- `?venue=` preselects that venue; unknown venue code falls back to first venue;
- role gating: student hitting the page → 403 (route `role:lecturer` unchanged).

**Playwright — `tests/venue-db.spec.ts`** (new file; legacy `tests/venue-timetable.spec.ts`
stays untouched per the user's parked-decision):
- login 5425 → venue dropdown lists 23 real venues;
- an occupied cell shows a real module code; modal opens with cohort codes;
- summary cards numeric; week next/prev repaints client-side; venue switch navigates
  with `?venue=` and repaints; zero console errors.

## 7. Verification plan

1. Feature + new Playwright spec green; existing suites unchanged (nav-identity 3/3,
   auth-full ungated set, timetable-wiring).
2. phpunit count grows from 115; phpstan-1G 0; pint adminer-only baseline.
3. Live smoke: `/venue-timetable-ui?venue=B014` shows B014's real week grid; SQL
   spot-audit ≥2 venues × ≥2 weeks (incl. a holiday week — W8 Mon shows no events).
4. Changelog row in `page-changelogs/backend-automated-by-ai.md` + commit.

## 8. Out of scope (frozen)

Booking/OCC write path, other five legacy pages, `/api/*` layer, mock-data.js,
`?code=`/`?cohort=` params, trait modifications.
