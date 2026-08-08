# Spec — API Read Wiring (capability)

> **Status:** Spec for design.md (frozen, Batch 2). This is **Batch 3** — pending review.
> **Capability:** MockData-shaped JSON API serving the 6 read-only UI pages + page fetch bootstrap.
> **Component under test:** `routes/api.php` + `ApiReadController` + per-page `loadMockSection` bootstrap.

---

## 1. Description

Eight `/api/v1` JSON endpoints (registered via `withRouting(api:)` in
`bootstrap/app.php`) serve MockData-shaped data from the Phase 1–4 schema. Each of
the 6 read-only pages relocates its parse-time MockData derivations into an async
DOMContentLoaded callback that awaits `loadMockSection(...)` (ui-common.js helper),
falling back to the bundled mock on API failure. The arrangement page's slot search
is backed by `MatrixIntersectionEngine`.

## 2. Requirements

| ID | Requirement | Source |
|----|-------------|--------|
| E-1 | `routes/api.php` created and registered via `withRouting(api: __DIR__.'/../routes/api.php')` in `bootstrap/app.php` (no RouteServiceProvider in repo); `shouldRenderJsonWhen(api/*)` already present | proposal §2, design §4 |
| E-2 | 8 endpoints exist with exact paths/params: `GET /api/v1/semester`, `GET /api/v1/timetable/my`, `GET /api/v1/timetable/cohort?cohort_id=<slug>`, `GET /api/v1/timetable/student`, `GET /api/v1/requests/my`, `GET /api/v1/requests/conflicts`, `GET /api/v1/arrangement/slots?cohort_ids=&session_type=&duration=&venue_id=&week_number=`, `GET /api/v1/meta/cohorts`; all JSON, public, user resolved server-side | D3, design §3 |
| E-3 | `/semester` returns `{semester: {label, startDate, endDate, weeks, chipText}, holidays: [{week, dayIndex, label}]}`; chipText = `"{label} · DD-Mon-YYYY ~ DD-Mon-YYYY"` from seeded dates (2026-08-31 → 2026-12-06); holidays from `holidays` table | A3, §3 |
| E-4 | `/timetable/my` returns `{myTimetable: {seedWeek: 11, eventsByWeek}}`; keys **0-indexed 0..13**; key 11 present; events have full field set `{di, start, end, code, type, venue, lecturer, cohort, cohorts, studentCount(s), status, name, remarks}` with `start`/`end` 30-min indexes (08:00 base, end inclusive), flat `requestedAt` (`d M Y, g:i A`)/`requestedBy` when pending, `remarks` `d-M-Y` for replacement events; status mapping normal/pending/replacement | A4, A5, A6, A7 |
| E-5 | `/timetable/cohort` returns `{cohortTimetable: {faculties: [{id, name, cohorts: [{id, name}]}], events: [{cohortId, week, event}], rsd3g2Base: [RSD3G2 sessions], rsd3g2Flags: null}}`; faculties grouped from `Cohort::with('programme.faculty')`; cohort ids = slugified display codes; events = each cohort's sessions × 14 weeks, 0-indexed week keys; `rsd3g2Base` populated via shared `cohortBaseEvents()` | A5, A11, §3 |
| E-6 | `/timetable/student` returns `{cohortTimetable: {faculties, events, rsd3g2Base: [active cohort sessions], rsd3g2Flags: {week: [[code, status, remarks]]}}, studentTimetable: {activeCohort: <slug>, cancelledFlags: {weekIdx: [codes]}, notificationCount}}`; active cohort = resolved student's cohort; `rsd3g2Flags` from replacement_requests (A7 statuses); `cancelledFlags` from `class_exceptions` (key = week_number − 1); notificationCount = pending requests affecting cohort | §3, A11 |
| E-7 | `/requests/my` returns `{requests: [...]}` for proposer = resolved lecturer; full row field map (id, requestedAt ISO, courseCode/Name, classType, classDate/classDay/timeStart/timeEnd/duration, venue, totalStudents/cohortCounts/cohorts, title-case status, rejectionReason/remarks, replacementDate/replacementTime `H:i – H:i`/replacementVenue, reviewedBy/reviewedAt) | §3 field map, A7 |
| E-8 | `/requests/conflicts` returns `{conflictedClasses: [...]}` = lecturer's sessions with `class_exceptions` (reasons mapped to the 5 badgeClass-valid Title-case values) ∪ sessions on holiday (week, day); dedup one row per (session, week), exception wins | §3, §7 |
| E-9 | `/arrangement/slots` calls `MatrixIntersectionEngine::findAvailableSlots($lecturerUserId, $cohortIds, $semesterId, $weekNumber, $sessionType, $duration, $venueId)`; defaults: week_number 11 (1-indexed), session_type L, duration 60, cohort_ids = resolved lecturer's first session's cohorts; returns `{venueSlots: {code: [[di, hi, statusInt]]}, arrangementWeeks: 3 weeks descending, venues: [...]}`; every cell hi 0..21 present; statusInt 0/1/3/4 (hi 20–21 synthesized 1); `venues[]` = exactly venueSlots keys with `{code, type (PascalCase mapped), capacity, allowedSessions}` | A8, A9, §3 |
| E-10 | `/meta/cohorts` returns `{cohorts: [...]}` registry (code, programme, year, semester, group, academicYear, intake, faculty, studentCount) — contract-only endpoint | §3 |
| E-11 | User resolution: `Auth::user()` when logged in with matching role, else lecturer `5425` / first student; deterministic; 503 on null (never on seeded DB) | A2 |
| E-12 | `ReplacementRequestsSeeder` new + registered in `DatabaseSeeder::run()`; seeds ≥3 requests for lecturer 5425 across statuses with replacement_time_slots + one conflict source (`class_exceptions` row or holiday overlap on a 5425 session) | proposal §2, design §7 |
| E-13 | Per-page bootstrap: parse-time MockData derivations relocated into the page's existing DOMContentLoaded callback (made async), section fetches awaited first, then derivations; `loadMockSection(url, section)` in ui-common.js with `Object.assign` on success / `console.warn('[api] fallback to mock:', err)` + bundled mock on failure; no top-level `await` | A10 |
| E-14 | No page-logic changes beyond the A10 relocation (no render logic, sorting, or filter changes) | A12 |
| E-15 | Feature tests: one per endpoint (200 + shape assertions incl. chipText format, 0-indexed keys, venueSlots hi 0..21, venues type mapping, request row map); engine endpoint returns non-empty windows; feature tests cover `cancelledFlags` and conflict dedupe | design §6 |
| E-16 | Quality gates: existing Playwright 9 tests green; new data-dependent e2e assertions (module code cell, ≥3 history rows, ≥1 conflicted card, ≥1 green arrangement cell, BMIT2020 student base cells); `npm run build`; pint; phpstan | design §6, §7 |

## 3. Scenarios

> Test DB = seeded `class_replacement` (migrate:fresh --seed incl. ReplacementRequestsSeeder). Server = `php artisan serve` via Playwright webServer.

### S-1 Fresh seed — all endpoints return 200 with shape
- Given `migrate:fresh --seed` ran
- When each of the 8 endpoints is hit
- Then 200 + response matches its E-row shape contract

### S-2 User resolution fallbacks
- Given no logged-in user
- When `/timetable/my` and `/requests/my` are hit
- Then data belongs to lecturer 5425 (staff_id)
- And when logged in as a lecturer with sessions
- Then data belongs to that lecturer (Auth::user() wins)

### S-3 Mock fallback on API failure
- Given the API is down (server stops responding to /api/v1/*)
- When a wired page loads
- Then page renders from bundled mock-data.js and `console.warn('[api] fallback to mock:', err)` appears (no pageerror, no blank page)

### S-4 Arrangement engine windows
- Given week 11, session_type L, default cohort
- When `/arrangement/slots` is hit
- Then venueSlots contain ≥1 `[di, hi, 0]` triple; hi 20–21 are `1`; venues match venueSlots keys; arrangementWeeks has 3 descending weeks

### S-5 Seeded history rows
- Given the seeder fixture
- When `/requests/my` returns
- Then ≥3 rows, statuses include Pending and Approved (title case), replacementTime format `H:i – H:i`

### S-6 Full Playwright suite green
- Given all pages wired
- When `npm run test:e2e` runs
- Then existing 9 tests + new data-dependent assertions pass; no 4xx on any API call (E-16 response listener)

### S-7 ESM/quality gates
- Given the changes
- Then `npm run build` succeeds; pint + phpstan pass on touched PHP

## 4. Field-format appendix (verbatim-render facts)

| Field | Format | Rendered by |
|-------|--------|-------------|
| `semester.chipText` | `202605 Semester · 31-Aug-2026 ~ 06-Dec-2026` | all page chips (verbatim) |
| `requestedAt` (pending events) | `05 Sep 2026, 11:00 AM` (`d M Y, g:i A`) | modal |
| `remarks` (replacement events) | `31-Aug-2026` (`d-M-Y`) | `(Replaced for …)` |
| `requests[].requestedAt` | ISO-8601 (`Y-m-d\TH:i:s`) | history list |
| `requests[].replacementTime` | `09:00 – 11:00` (`H:i – H:i`, en dash) | history list |
| `requests[].status` | Title case: Pending/Approved/Rejected/Cancelled/Completed | badge |
| `conflictedClasses[].day` | full name `Monday` (page slices 3 chars) | badge/tooltip |
| `conflictedClasses[].conflictReason` | Public Holiday/Annual Leave/Medical Leave/Official Event/Emergency Leave only | badgeClass map |
| `venues[].type` | Tutorial/LectureHall/Lab/CiscoLab | dropdown `v.type === 'LectureHall'` |
