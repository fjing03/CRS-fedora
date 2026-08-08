# Tasks — Frontend Read Wiring (MockData → Backend API)

> **Status:** Tasks for design.md + specs (frozen, Batches 2+3). This is **Batch 4** — pending review.
> **Baseline:** explore-brief.md D1–D8. Each task ≤ 2 hours.

---

## Task 1 — Infrastructure: `bootstrap/app.php` routing + `routes/api.php` skeleton

**DoD:** `/api/v1/semester` returns 200 JSON `{}` (stub).

- [x] `bootstrap/app.php`: add `api: __DIR__.'/../routes/api.php'` to `withRouting()`
- [x] `routes/api.php` created with `Route::prefix('v1')->group(fn () => ...)` skeleton
- [x] `app/Http/Controllers/Api/ApiReadController.php` created with `semester()` stub returning `response()->json([])`
- [x] Verify `GET /api/v1/semester` returns 200 (curl)
- [x] Confirm `shouldRenderJsonWhen` already present (no change)

## Task 2 — `ReplacementRequestsSeeder` + `DatabaseSeeder` registration

**DoD:** `migrate:fresh --seed` populates `replacement_requests` with ≥3 rows for lecturer 5425.

- [x] `database/seeders/ReplacementRequestsSeeder.php` created: ≥3 requests for lecturer 5425 (pending/approved/rejected) + `replacement_time_slot_id` set to real time_slot (week 11); one `class_exceptions` row or confirmed holiday overlap on a 5425 session for conflict fixture
- [x] `DatabaseSeeder::run()` calls `ReplacementRequestsSeeder` in the chain (after ClassExceptionsSeeder)
- [x] `migrate:fresh --seed` succeeds; `SELECT COUNT(*) FROM replacement_requests` >= 3
- [x] Request statuses for lecturer 5425 include Pending, Approved, Rejected

## Task 3 — `loadMockSection` helper in `ui-common.js`

**DoD:** `loadMockSection(url, section, fallback)` available globally.

- [x] `public/js/ui-common.js`: add `async function loadMockSection(url, section, fallback = true)` — fetches url; on 200+JSON: `Object.assign(window.MockData, data)` directly (API responses already match MockData shape — `{ semester, holidays }`, `{ myTimetable }`, `{ venueSlots, arrangementWeeks, venues }` etc. — no wrapping needed); on error: `console.warn('[api] fallback to mock:', url, error)` if fallback true; returns boolean
- [ ] Verify: calling `loadMockSection('/api/v1/semester', 'semester')` populates `window.MockData.semester`

## Task 4 — `/api/v1/semester` endpoint (design A3)

**DoD:** Returns semester + holidays shaped per spec E-3.

- [x] `ApiReadController::semester()` — DB::table('semesters')->first(); holidays where semester_id = current semester id
- [x] Response: `{ semester: { label, startDate, endDate, weeks (from week_count), chipText }, holidays: [{ week (1-indexed), dayIndex, label }] }`
- [x] chipText format: `"{label} · DD-Mon-YYYY ~ DD-Mon-YYYY"` (same hyphen as mock)
- [x] PHPUnit test: 200 + keys present + chipText matches seeded data

## Task 5 — `/api/v1/timetable/my` endpoint (design A4-A7)

**DoD:** Returns myTimetable with 0-indexed keys 0..13, seedWeek 11.

- [x] User resolution: `resolveUser('lecturer')` private method (A2)
- [x] Query: class_sessions WHERE lecturer_id = user; join module, venue, session_cohorts (cohorts, student counts)
- [x] For each week 0..13: map sessions to event objects (A6 fields: di, start/end 30-min idx, code, type, venue, lecturer name, cohort display, cohorts array, studentCount(s), status 'normal', name, remarks '')
- [x] Overlay replacement_requests where proposer = user: pending -> status 'pending' + requestedAt (d M Y, g:i A) + requestedBy; approved/rejected/cancelled/completed -> status 'replacement' + remarks (d-M-Y replacement date)
- [x] Response: `{ myTimetable: { seedWeek: 11, eventsByWeek: { 0..13: [event] } } }`
- [x] PHPUnit: 200 + key 11 present + events have 'code' field

## Task 6 — `/api/v1/timetable/cohort` endpoint (design A5, A11)

**DoD:** Returns cohortTimetable with faculties, events, rsd3g2Base populated.

- [x] Param: `cohort_id` (slug). Resolve: Cohort where slugified code matches
- [x] Faculties: `Cohort::with('programme.faculty')` grouped by faculty (id = slugified faculty name, name = faculty name; cohorts = [{id: slugified code, name: display code}])
- [x] Events: for each cohort's sessions (via session_cohorts) x 14 weeks (0-indexed); event fields same as timetable/my
- [x] rsd3g2Base: `cohortBaseEvents(rsd3g2_cohort_id)` — shared private method
- [x] Response: `{ cohortTimetable: { faculties: [...], events: [...], rsd3g2Base: [...], rsd3g2Flags: null } }`
- [x] PHPUnit: 200 + faculties array non-empty + events have cohortId/week/event keys

## Task 7 — `/api/v1/timetable/student` endpoint (design A6, A11)

**DoD:** Returns studentTimetable + cohortTimetable for active cohort.

- [x] Resolve student: `resolveUser('student')`; get active cohort via `Student->cohort`
- [x] Use `cohortBaseEvents(active_cohort_id)` for rsd3g2Base
- [x] rsd3g2Flags: replacement_requests for active cohort's sessions (pending -> 'pending', etc.) grouped by week (0-indexed), shape `[[code, status, remarks]]`
- [x] cancelledFlags: `class_exceptions` joined to active cohort's sessions; key = week_number - 1; value = array of module codes
- [x] notificationCount: count of pending replacement_requests affecting the cohort
- [x] Response: `{ cohortTimetable: { ..., rsd3g2Base: [...], rsd3g2Flags: {week: [...]} }, studentTimetable: { activeCohort: slug, cancelledFlags: {...}, notificationCount: N } }`
- [x] PHPUnit: 200 + studentTimetable keys present + activeCohort matches expected slug

## Task 8 — `/api/v1/requests/my` endpoint (design A7, field map)

**DoD:** Returns requests array with all fields per design field map.

- [x] Query: replacement_requests WHERE proposer_id = resolveUser('lecturer')->id; join classSession->module, classSession->venue, classSession->sessionCohorts->cohort, replacementTimeSlot->venue, approver
- [x] Map to mock fields: id, requestedAt (ISO), courseCode/Name, classType, classDate/classDay/timeStart/timeEnd/duration (derived from week_number + day_of_week + semester start), venue (room_code), totalStudents/cohortCounts/cohorts, status (title case), rejectionReason/remarks, replacementDate/replacementTime (`H:i - H:i`)/replacementVenue (room_code), reviewedBy/reviewedAt
- [x] Response: `{ requests: [...] }`
- [x] PHPUnit: 200 + requests array length >= 3 + statuses include Pending

## Task 9 — `/api/v1/requests/conflicts` endpoint (design A8, field map)

**DoD:** Returns conflictedClasses from class_exceptions + holidays, deduped.

- [x] Query: lecturer's sessions joined with class_exceptions (mapped reason) union lecturer's sessions where (week_number, day_of_week) has a holidays row (reason = holiday label)
- [x] Dedup: one row per (session, week_number), exception wins
- [x] Map to mock fields: id, code, name, type, date, day (full name), timeStart, timeEnd, duration, venue, totalStudents, cohorts, conflictReason (mapped Title case)
- [x] Response: `{ conflictedClasses: [...] }`
- [x] PHPUnit: 200 + array non-empty for lecturer 5425 + conflictReason values are badgeClass-valid

## Task 10 — `/api/v1/arrangement/slots` endpoint (design A9, engine)

**DoD:** Calls MatrixIntersectionEngine; returns venueSlots + arrangementWeeks + venues.

- [x] Params: cohort_ids (comma list, default = first session's cohorts), session_type (default L), duration (default 60), venue_id (nullable), week_number (default 11, 1-indexed)
- [x] Call: `(new MatrixIntersectionEngine())->findAvailableSlots(lecturerUserId, $cohortIds, $semesterId, $weekNumber, $sessionType, $duration, $venueId)`
- [x] Build venueSlots: for each eligible venue, grid 08:00-18:30 (hi 0..21); cells inside engine windows -> [di, hi, 0]; others -> [di, hi, 1]; overlay pending/reserved from replacement_requests -> [di, hi, 3/4]; hi 20-21 -> [di, hi, 1]
- [x] arrangementWeeks: 3 weeks descending from week_number; days = semester start + (week-1)*7..+6; holiday flag where holidays row exists
- [x] venues: engine-eligible venues only; `{ code: room_code, type: mapped PascalCase, capacity, allowedSessions: split(',') }`
- [x] Response: `{ venueSlots: {...}, arrangementWeeks: [...], venues: [...] }`
- [x] PHPUnit: 200 + venueSlots has ≥1 key + arrangementWeeks length 3 + venues length > 0

## Task 11 — `/api/v1/meta/cohorts` endpoint (design A10, contract-only)

**DoD:** Returns cohorts registry.

- [x] Query: Cohort::with('programme.faculty') -> map to mock fields (code, programme, year, semester, group, academicYear, intake, faculty, studentCount from STUDENT_COUNTS)
- [x] Response: `{ cohorts: [...] }`
- [x] PHPUnit: 200 + cohorts array non-empty

## Task 12 — Page wiring: MyTimetable template

**DoD:** Page loads real data; existing render logic unchanged.

- [x] `MyTimetable-UI-design-template.blade.php`: relocate parse-time MockData reads (weekData, eventsData, seedEvents, weeklyTemplate etc.) into DOMContentLoaded callback AFTER await loadMockSection
- [x] Make DOMContentLoaded callback async; first statements: `await loadMockSection('/api/v1/semester', 'semester'); await loadMockSection('/api/v1/timetable/my', 'myTimetable');`
- [x] Verify: page renders real data; no JS errors; e2e spec passes

## Task 13 — Page wiring: CohortTimetable template

**DoD:** Page loads real data from API.

- [x] Relocate parse-time reads (facultyData, allEvents from rsd3g2Base/rsd3g2Flags, events) into async DOMContentLoaded callback
- [x] First statements: await semester + cohort timetable endpoints
- [x] Verify: cohort picker populated from faculties; timetable renders

## Task 14 — Page wiring: StudentMyTimetable template

**DoD:** Page loads real data for demo student.

- [x] Relocate parse-time reads (weekData, eventsByWeek from rsd3g2Base/flags, cancelledFlags) into async DOMContentLoaded callback
- [x] First statements: await semester + student timetable endpoints
- [x] Verify: base BMIT2020 cells visible; no JS errors

## Task 15 — Page wiring: MyRequestHistory template

**DoD:** Page loads real requests from API.

- [x] Relocate `let mockRequests = MockData.requests` and any parse-time reads into async DOMContentLoaded callback
- [x] First statements: await semester + requests/my endpoints
- [x] Verify: ≥3 request rows rendered; no JS errors

## Task 16 — Page wiring: ReplacementHome template

**DoD:** Page loads real conflict data.

- [x] Relocate `const conflictedClasses = MockData.conflictedClasses` into async DOMContentLoaded callback
- [x] First statements: await semester + requests/conflicts endpoints
- [x] Verify: ≥1 conflicted card with styled badge

## Task 17 — Page wiring: ReplacementArrangement template

**DoD:** Page loads engine-backed slots.

- [x] Relocate parse-time reads (weekData from arrangementWeeks, venueSlotData from venueSlots, venues) into async DOMContentLoaded callback
- [x] First statements: await semester + arrangement/slots + timetable/my endpoints
- [x] Verify: ≥1 green cell in week 11 grid; venue dropdown populated from engine-eligible venues

## Task 18 — Feature tests

**DoD:** PHPUnit tests cover all 8 endpoints.

- [ ] `tests/Feature/Api/ApiReadEndpointsTest.php`: one test per endpoint (200 + key shape assertions)
- [ ] Engine endpoint test: known params -> non-empty windows
- [ ] cancelledFlags + conflict dedupe tests at controller/query level
- [ ] All tests green

## Task 19 — Playwright e2e extensions

**DoD:** Existing 9 tests + new data-dependent assertions.

- [ ] Extend timetable spec: assert a known module code cell (from lecturer 5425's sessions)
- [ ] Extend student timetable spec: assert base BMIT2020 cell renders (from demo student's cohort DFT1(S1)G1)
- [ ] Extend history spec: assert ≥3 request rows
- [ ] Extend home spec: assert ≥1 conflicted card
- [ ] Extend arrangement spec: assert ≥1 green cell
- [ ] All 9 + new assertions green; no 4xx on any API call

## Task 20 — Quality gates + commit

**DoD:** All gates pass; commit ready.

- [ ] `npm run build` succeeds
- [ ] `vendor/bin/pint` (touched PHP files)
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G`
- [ ] Full Playwright suite green twice consecutively
- [ ] `git status` shows only intended files
- [ ] Commit: `feat(api): wire 6 read-only pages to /api/v1 endpoints with mock fallback`
