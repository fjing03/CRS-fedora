# Design — Frontend Read Wiring (MockData → Backend API)

> **Status:** Frozen (Batch 2). **Frozen:** proposal.md. **Baseline:** explore-brief.md D1–D8.

---

## 1. Technical Approach

A new `routes/api.php` registered via `withRouting(api: ...)` in `bootstrap/app.php`
serves 8 JSON endpoints under `/api/v1`. A single API controller (`ApiReadController`)
queries the Phase 1–4 schema (class_sessions, session_cohorts, time_slots,
replacement_requests, class_exceptions, cohorts, semesters, holidays, venues,
users) and returns responses **shaped exactly like the `MockData` sections each page
consumes** (D4). User identity is resolved server-side per D2. Each of the 6 pages
gets a fetch bootstrap that swaps the real data into `window.MockData` before the
page's render function runs, with mock fallback on API failure (D5).
`/replacement-arrangement` exercises the real `MatrixIntersectionEngine` (D6).

## 2. Architecture Decisions

| ID | Decision |
|----|----------|
| A1 | **Single controller** `app/Http/Controllers/Api/ApiReadController.php` — 8 small read actions; no extra layers needed |
| A2 | **User resolution helper** — private `resolveUser(string $role): ?User`: `Auth::user()` if logged in and `isLecturer()`/`isStudent()`; lecturer fallback = `Lecturer::where('staff_id','5425')->first()?->user`; student fallback = `Student::first()->user`; if still null → `HttpResponseException` 503 (never happens on seeded DB) |
| A3 | **Semester source** — no `Semester` Eloquent model; use `DB::table('semesters')->first()` (seeder inserts exactly 1: id 1, week_count 14). `chipText` computed server-side in the mock's hyphen format: `"{label} · DD-Mon-YYYY ~ DD-Mon-YYYY"` — seeded data yields `202605 Semester · 31-Aug-2026 ~ 06-Dec-2026` (SemestersSeeder: start 2026-08-31, end 2026-12-06) |
| A4 | **Hour index convention** — page grids are 30-min slots starting 08:00: `idx = ((H - 8) * 60 + M) / 30`. 09:00 → 2, 10:00 → 4, 11:00 → 6. `start`/`end` in every event are these indexes; `end` is **inclusive** (pages compute `hours += (end - start + 1) * 0.5`) |
| A5 | **Week key conventions** — `myTimetable.eventsByWeek` keys are **0-indexed** (0..13; MyTimetable builds `for (i = 0; i < weeks; i++)` and `currentWeekIndex()` is 0-based; mock keys {3,5,7,9,11} are 0-indexed). `cohortTimetable.events[].week`, `rsd3g2Flags`, `cancelledFlags` keys also **0-indexed** (0..13, matching CohortTimetable/Student page loops). **`seedWeek` = 11 (0-indexed) and key 11 MUST exist** (MyTimetable reads `weeklyTemplate` from `eventsData[currentWeek]` at seed). The arrangement page's `'Week 11'` → key-11 lookup reproduces the current mock's self-consistent behavior |
| A6 | **Event base fields** — every event object: `{ di, start, end, code, type, venue, lecturer, cohort, cohorts, studentCount(s), status, name, remarks }` with **flat** `requestedAt`/`requestedBy` added when `status === 'pending'` (pages read flat fields, e.g. MyTimetable L316–321). `requestedAt` is **display-formatted** `"d M Y, g:i A"` (mock style `05 Sep 2026, 11:00 AM` — modals render it verbatim); `requestedBy` = proposer name. `remarks` = replacement date **display-formatted** `"d-M-Y"` (mock style `31-Aug-2026`; pages render `(Replaced for ${e.remarks})` verbatim); `''` for normal events. `di` = day_of_week (0=Mon). `type` = session_type. `cohort` = display string (codes joined `' + '`), `cohorts` = array of DB display codes, `studentCount` = single-cohort count (omitted when ≥2 cohorts), `studentCounts` = per-cohort array (only when ≥2) — mirrors mock both-variants pattern |
| A7 | **Status mapping** — timetable events: `normal` (no request), `pending` (pending request for that session/week), `replacement` (approved/rejected/cancelled/completed request). Request list statuses: DB `pending/approved/rejected/cancelled/completed` → Title case `Pending/Approved/Rejected/Cancelled/Completed` |
| A8 | **venueSlots encoding** — triple `[di, hi, statusInt]` for **every cell of the 08:00–18:30 grid (hi 0..21, 22 entries — matches `ui-common.js` `hours` and the page's "missing triple → available+clickable" branch)**: `1`=occupied (not in an engine window), `3`=pending (pending request covers cell), `4`=reserved (approved request covers cell), `0`=available (inside an engine window). Sunday/holiday cells omitted (page paints them occupied itself). Note: the seeded `time_slots` grid ends at 17:30 (TimeSlotsSeeder `hour < 18`) — **hi 20–21 cells (18:00/18:30) are synthesized as occupied (`1`)** since the engine can never return them |
| A9 | **Engine call** — arrangement endpoint: `(new MatrixIntersectionEngine())->findAvailableSlots($lecturerUserId, $cohortIds, $semesterId, $weekNumber, $sessionType, $duration, $venueId)`. Params: `weekNumber` from query, **1-indexed (DB CHECK 1–14)**, default **11**; `sessionType` default `L`; `duration` default 60; `venueId` nullable; `cohort_ids` query param (comma list), **default = the cohort ids of the resolved lecturer's first class_session** (deterministic — the page has no cohort picker). `semesterId` = seeded semester id (1). Occupied cells = every (di,hi) in the venue's 08:00–18:30 grid NOT inside an engine window; pending/reserved overlays from replacement_requests covering that venue+week |
| A10 | **Page fetch bootstrap (classic-script safe)** — NO top-level `await` (SyntaxError in classic inline `<script>`). Each template's **parse-time MockData reads and derivations** (MyTimetable `weekData`/`eventsData` L203–248, CohortTimetable `faculties`/`allEvents` L133–194, Student `weekData`/`eventsByWeek` L65–131, History `mockRequests` L556, Home `conflictedClasses` L234, Arrangement `weekData`/`venueSlotData` L987–988) are **relocated into** that template's existing `document.addEventListener('DOMContentLoaded', ...)` callback (MyTimetable L538, CohortTimetable L580, Student L439, Home L587, History L1141, Arrangement L1695) — the callback becomes **`async`**, its **first statements** are the section fetches (`await loadMockSection(...)` per section — sequential awaits or `Promise.all`, both valid; pages with multiple sections: my-timetable 2, arrangement 3, student 2), and the relocated derivations run after them. Mechanical relocation only — no logic changes. Shared `loadMockSection(url, section, fallback = true)` helper added to `ui-common.js`: on success `Object.assign(window.MockData, payload)`; on failure `console.warn('[api] fallback to mock:', err)` and the bundled mock stays. The layout's shared nav listener is registered before page-scripts, does not read MockData, and is untouched |
| A11 | **Cohort slug + display code conventions** — cohort `id` slugs in `faculties[].cohorts[].id` and `activeCohort` = DB cohort code lowercased, stripped of non-alphanumerics (`RSD3(S1)G2` → `rsd3s1g2`). Display codes = `"{programme_code}{current_year}(S{semester})G{tutorial_group}"` (exact formula from `DatabaseSeeder::cohortCode()`, L189–198). Shared private method `cohortBaseEvents(int $cohortId): array` builds the session-event list for one cohort (used by both the student endpoint `rsd3g2Base` and the cohort endpoint) |
| A12 | **Zero page-logic changes** — the 6 templates' render JS is unchanged EXCEPT: (a) the A10 relocation of parse-time MockData derivations into the async callback (mechanical, no logic change); (b) nothing else. All shape compatibility is the API's responsibility |

## 3. Endpoint Contract

| Endpoint | Page(s) | Response (MockData-shaped) |
|----------|---------|------------------------------|
| `GET /api/v1/semester` | all 6 | `{ semester: {label, startDate, endDate, weeks, chipText}, holidays: [{week, dayIndex, label}] }` — from `semesters` + `holidays` (A3). `week` 1-indexed, `dayIndex` 0-based (mock §2.2 shape) |
| `GET /api/v1/timetable/my` | my-timetable, arrangement | `{ myTimetable: { seedWeek: 11, eventsByWeek: {0..13: [event] } } }` — class_sessions WHERE lecturer_id = resolveUser('lecturer')->id (A6), overlays from replacement_requests (A7) |
| `GET /api/v1/timetable/cohort?cohort_id=<slug>` | cohort-timetable | `{ cohortTimetable: { faculties: [{id, name, cohorts: [{id, name}]}], events: [{cohortId, week, event}], rsd3g2Base: [RSD3G2 cohort's sessions], rsd3g2Flags: null } }` — faculties from `Cohort::with('programme.faculty')` grouped by faculty (A11); events = each cohort's sessions × 14 weeks (0-indexed keys, A5); **`rsd3g2Base` populated** (A11) so RSD3(S1)G2 renders (page unconditionally rebuilds that cohort from base) |
| `GET /api/v1/timetable/student` | student-my-timetable | `{ cohortTimetable: { faculties, events, rsd3g2Base: <active cohort's sessions (A11)>, rsd3g2Flags: {week: [[code, status, remarks]]} }, studentTimetable: { activeCohort: <slug>, cancelledFlags: {weekIdx: [codes]}, notificationCount: N } }` — active cohort = resolveUser('student')->student->cohort; `rsd3g2Flags` from replacement_requests for those sessions (A7, 0-indexed keys); **`cancelledFlags` from `class_exceptions`** joined to the active cohort's sessions (reason-agnostic — any exception row suppresses the session), key = `week_number - 1`; notificationCount = count of pending requests affecting the cohort |
| `GET /api/v1/requests/my` | my-request-history | `{ requests: [...] }` — replacement_requests WHERE proposer_id = resolveUser('lecturer')->id → field map below |
| `GET /api/v1/requests/conflicts` | replacement-home | `{ conflictedClasses: [...] }` — class_sessions of resolveUser('lecturer') with a `class_exceptions` row (reason snake_case → Title case: `public_holiday`→`Public Holiday`, `annual_leave`→`Annual Leave`, `medical_leave`→`Medical Leave`, `official_event`→`Official Event`, `emergency_leave`→`Emergency Leave` — all 5 have `badgeClass` entries in the home page) ∪ sessions whose (week, day) has a `holidays` row (reason = holiday label). **Dedup: one row per (session, week); exception wins over holiday.** Field map below |
| `GET /api/v1/arrangement/slots?cohort_ids=1,2&session_type=L&duration=120&venue_id=&week_number=11` | replacement-arrangement | `{ venueSlots: {code: [[di, hi, statusInt]]}, arrangementWeeks: [{label, days: [{abbr, date, holiday?}]}] x 3 weeks descending from week_number}, venues: [{code, type, capacity, allowedSessions}] }` — engine-backed (A8/A9); arrangementWeeks built from semester start + week_number. **`venues[]` = exactly the venue keys present in `venueSlots`** (only engine-eligible venues — dropdown lists only bookable venues; every listed venue has a full grid, so no venue ever shows a stale fully-available fallback) |
| `GET /api/v1/meta/cohorts` | contract-only (no template reads `MockData.cohorts` today) | `{ cohorts: [{code, programme, year, semester, group, academicYear, intake, faculty, studentCount}] }` — cohorts registry mirroring mock §2.3; kept for future pickers/Phase 5 |

### Request row field map (`/requests/my`)

| Mock field | Source |
|------------|--------|
| `id` | request id |
| `requestedAt` | `submitted_at` ISO-8601 |
| `courseCode` / `courseName` | classSession→module `module_code` / `module_name` |
| `classType` | session `session_type` |
| `classDate` / `classDay` / `timeStart` / `timeEnd` / `duration` | session day_of_week → date from semester start + (week_number−1)*7; times `H:i`; duration = (end−start) hours |
| `venue` | session venue `room_code` |
| `totalStudents` / `cohortCounts` / `cohorts` | session cohorts (A6 count logic) |
| `status` | Title case (A7) |
| `rejectionReason` / `remarks` | columns |
| `replacementDate` / `replacementTime` / `replacementVenue` | `replacementTimeSlot`: week/day → date; time = `"H:i – H:i"` (en dash + spaces, verbatim render style); venue `room_code` |
| `reviewedBy` / `reviewedAt` | `approver` user name / `decided_at` ISO-8601 |

### Conflicted-class row field map (`/requests/conflicts`)

| Mock field | Source |
|------------|--------|
| `id` | class_session id |
| `code` / `name` / `type` | module_code / module_name / session_type |
| `date` (YYYY-MM-DD) / `day` (full name) | session day_of_week → date from semester start + (week_number−1)*7 |
| `timeStart` / `timeEnd` / `duration` | session times `H:i`; duration hours |
| `venue` | venue `room_code` |
| `totalStudents` / `cohorts` | session cohorts (A6) |
| `conflictReason` | class_exceptions reason mapped (Title case) or holiday label |

### Venue contract (`/arrangement/slots` → `venues[]`)

| Field | Source |
|-------|--------|
| `code` | `room_code` (dropdown reads `v.code`) |
| `type` | `room_type` **mapped to page's PascalCase**: `tutorial`→`Tutorial`, `lecture_hall`→`LectureHall`, `lab`→`Lab`, `cisco_lab`→`CiscoLab` (dropdown label check uses `v.type === 'LectureHall'`) |
| `capacity` | `capacity` |
| `allowedSessions` | `allowed_session_types` split on `,` (e.g. `"L,T"` → `['L','T']`) |

## 4. Data Flow

```
[6 templates]  DOMContentLoaded (async): await loadMockSection('/api/v1/...', section)
      │  (A10; on fetch error → console.warn + bundled mock stays)
      ▼
routes/api.php (registered via withRouting(api: ...) in bootstrap/app.php)
      ▼
ApiReadController::… (A1)
      ├─ resolveUser('lecturer'|'student') (A2)
      ├─ DB: class_sessions/session_cohorts/cohorts/modules/venues,
      │       time_slots, replacement_requests, class_exceptions,
      │       semesters, holidays, users
      └─ arrangement: MatrixIntersectionEngine::findAvailableSlots() (A9)
      ▼
JSON (MockData-shaped) → window.MockData[section] → page render JS (unchanged, A12)
```

## 5. Dependencies

- Laravel 13 `bootstrap/app.php` `withRouting(api:)` + `shouldRenderJsonWhen(api/*)` (already present)
- `app/Services/MatrixIntersectionEngine.php` (Phase 3, no changes)
- Models: ClassSession, SessionCohort, Cohort, Programme, Faculty, Module, Venue, ReplacementRequest, TimeSlot, Lecturer, Student, User; `DB::table('semesters'|'holidays'|'class_exceptions')` (no Semester/Holiday models)
- `public/js/ui-common.js` — +1 shared helper `loadMockSection` (A10)
- No new composer deps, no migrations, no schema changes

## 6. Testing

- `tests/Feature/Api/ApiReadEndpointsTest.php` — one test per endpoint: 200 + shape assertions (semester chipText format, event field set + 0-indexed keys, request row map, venueSlots triples incl. hi 0..21, venues type mapping, cohort registry fields)
- Engine endpoint test: known cohort_ids + session_type from seeded data → assert non-empty windows (week 11 has no holidays; demo lecturer has 4 sessions)
- Playwright: existing 9 tests stay green; extend specs to assert a data-dependent element per wired page (timetable cell with real module code, request row count ≥ 3, conflicted card ≥ 1, arrangement green cells ≥ 1)
- Feature tests additionally cover the cancellation-flag path (`cancelledFlags` from `class_exceptions`) and conflict dedupe logic at the controller/query level
- Quality gates: `vendor/bin/pint` (touched PHP), `vendor/bin/phpstan analyse --memory-limit=1G`, `npm run build`, `npm run test:e2e`
- `migrate:fresh --seed` + `ReplacementRequestsSeeder` (registered in `DatabaseSeeder::run()` chain) must produce the fixture rows the e2e specs assert on

## 7. Key Selectors / Fixtures (e2e stability)

- Demo lecturer 5425 (Pn. Surayaini) has 4 seeded class sessions → `/my-timetable-ui` asserts a known module code cell renders
- `ReplacementRequestsSeeder` seeds ≥3 requests for lecturer 5425 (pending/approved/rejected) + a replacement_time_slot each → history page asserts ≥3 rows. **Conflict fixture (per frozen proposal "one conflicting class"):** the seeder also inserts one `class_exceptions` row for one of lecturer 5425's 4 sessions (or relies on a confirmed holiday overlap — holiday rows are seed-fixed) so the home page's "conflicted card ≥ 1" assertion is deterministic and does not depend on an unstated source
- Student fallback = first `Student` (deterministic by id — belongs to **DFT1(S1)G1**, sessions BMIT2020 L/P per ClassSessionsSeeder); `/student-my-timetable-ui` asserts the base BMIT2020 cells render. **No cancellation-flag e2e assertion** — the first student's cohort has no `class_exceptions` rows (cancelledFlags is legitimately empty for the demo student; the API path is covered by unit/feature tests)
- Arrangement: week 11 grid renders ≥1 green (available) cell for the demo lecturer's first session cohort
- Known cosmetic shifts (recorded, not bugs): cohort display codes render as `DFT2(S1)G1` (DB) vs mock's `DFT2 (S1)`; semester chip now shows the seeded calendar (31-Aug-2026 start); student page's hardcoded header chip `RSD3(S1)G2` stays as-is (cosmetic mismatch with the DFT1(S1)G1 demo student — page markup, out of scope)
