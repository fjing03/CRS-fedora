# Tasks — Phase 3: MatrixIntersectionEngine

> **Status:** Tasks for design.md + specs (frozen, Batches 2+3). This is **Batch 4** — pending review.
> **Working tree:** `fedora-jing` at `/home/jinglinux/tarumt/CRS-fedora`. Frozen artifacts: proposal.md, design.md, specs/intersection-engine/spec.md.
> Each task ≤ 2 hours. Quality gate after each task: PHPUnit targeted run stays green; Pint + PHPStan at task 10.

---

## Task 1 — Domain models (D6, design §5, E-16)

**DoD:** 5 models + 4 factories exist; `php artisan tinker` can load every table; models mirror frozen schema exactly.

- [x] `app/Models/Venue.php` — fillable: room_code, capacity, room_type, allowed_session_types; `hasMany(ClassSession)`, `hasMany(TimeSlot)`
- [x] `app/Models/Module.php` — module_code, module_name, allowed_session_types; `hasMany(ClassSession)`
- [x] `app/Models/ClassSession.php` — semester_id, module_id, lecturer_id, day_of_week, start_time, end_time, venue_id, session_type; no time casts (raw `H:i:s` per Q-2); day_of_week → int; `belongsTo(Module)`, `belongsTo(User, 'lecturer_id')`, `belongsTo(Venue)`, `belongsToMany(Cohort, 'session_cohorts')`
- [x] `app/Models/SessionCohort.php` — no fillable (direct-insert pivot); `belongsTo(ClassSession)`, `belongsTo(Cohort)`
- [x] `app/Models/TimeSlot.php` — semester_id, class_session_id, week_number, day_of_week, start_time, end_time, venue_id, status, version; week/day/version → int; `belongsTo(ClassSession)`, `belongsTo(Venue)`
- [x] Factories: `VenueFactory`, `ModuleFactory`, `ClassSessionFactory`, `TimeSlotFactory` (compact, permissive — unit tests override per scenario)
- [x] House pattern: `#[Fillable]` attribute, PHPStan `@property` docblocks, `casts()` method, no `$guarded`

**Verify:** `php artisan tinker --execute="echo App\Models\Venue::count();"` (→ 23 on seeded DB); `vendor/bin/phpstan analyse app/Models --memory-limit=1G --no-progress` (0 new errors).

## Task 2 — Engine skeleton + argument validation (D7/D8, Q-3, S-16, E-13)

**DoD:** class + signature compile; validation tests pass.

- [x] `app/Services/MatrixIntersectionEngine.php` — `final class`, no deps, both public methods with PHPStan `@param`/`@return` shapes (design §2, brief §5)
- [x] `validateSessionType()`, `validateWeekNumber()`, `validateDuration()` privates: `{L,T,P}` / [1,14] / multiple of 30 and ≥ 30 → else `InvalidArgumentException`
- [x] Unit test S-16 (each invalid arg)
- [x] `vendor/bin/phpstan analyse app/Services --memory-limit=1G --no-progress` — 0 new errors

## Task 3 — Busy vectors, week-aware (D3, E-2/E-3/E-14, S-2/S-3/S-4/S-9)

**DoD:** private helpers return range lists per design Step 1.

- [x] `lecturerBusyRanges(semesterId, lecturerId, weekNumber)` — class_sessions minus exceptions of that week → [(day, start, end)]
- [x] `cohortBusyRanges(semesterId, cohortIds, weekNumber)` — join session_cohorts, `IN` filter, GROUP BY class_session_id (dedupe), minus exceptions
- [x] Exception subtraction: `class_exceptions` only for the given week (other weeks ignored) — and only `class_session_id`s belonging to semester/lecturer/cohorts scope
- [x] Unit tests S-2, S-3, S-4, S-9 (incl. W+1 re-blocked)

**Verify:** `php vendor/phpunit/phpunit/phpunit --no-coverage tests/Unit/MatrixIntersectionEngineTest.php`.

## Task 4 — Eligible venues (Vector 4, D4, E-6/E-7, S-5/S-6/S-12)

**DoD:** venue filtering returns correct venue set.

- [x] `requiredHeadcount(cohortIds)` — `SELECT COUNT(*) FROM students WHERE cohort_id IN (...)` (SUM semantics, brief fact 4)
- [x] `eligibleVenues(headcount, sessionType, venueId?)` — `allowed_session_types` CSV contains type (PHP-side parse) AND capacity ≥ headcount; provided `venueId` still filtered (S-12), never trusted
- [x] Unit tests S-5, S-6, S-12 (type-excludes + capacity-excludes variants)

**Verify:** `php vendor/phpunit/phpunit/phpunit --no-coverage tests/Unit/MatrixIntersectionEngineTest.php` (S-5, S-6, S-12 pass).

## Task 5 — Base set + holiday block (D2/D3, E-4/E-5, S-4b/S-10)

**DoD:** base cells correct.

- [x] `holidayDays(semesterId, weekNumber)` → set of blocked day_of_week (design Step 3)
- [x] `baseCells(semesterId, weekNumber, venueIds, venueId?, holidayDays)` — time_slots status='available' + venue IN + day NOT IN holidays, ordered (day, start_time, room_code); join venues for code
- [x] Unit tests S-4b (venue-occupied excluded), S-10 (holiday day fully blocked, other days unaffected)

**Verify:** `php vendor/phpunit/phpunit/phpunit --no-coverage tests/Unit/MatrixIntersectionEngineTest.php` (S-4b, S-10 pass).

## Task 6 — Intersection + run grouping + ordering (D5, E-1/E-9/E-10/E-11, S-1/S-7/S-8/S-11)

**DoD:** `findAvailableSlots` returns correct results end-to-end.

- [x] Overlap test (design Step 5): drop cell if overlaps any lecturer or cohort range — interval form `busy.start < cell.end AND busy.end > cell.start`
- [x] Run grouping: maximal contiguous per (day, venue), 30-min steps; drop runs < duration; `time_slot_ids` = maximal run's cells
- [x] Result shape: `{day, start_time, end_time, venue_id, venue_code, time_slot_ids, run_start, run_end}`; `end_time` = run_start + duration; `run_end` = maximal extent; times as `H:i:s` strings
- [x] Ordering: day → start_time → venue_code
- [x] Empty result → `[]` (never throws)
- [x] Unit tests S-1, S-7, S-8, S-11 (incl. ordering)

**Verify:** `php vendor/phpunit/phpunit/phpunit --no-coverage tests/Unit/MatrixIntersectionEngineTest.php` (S-1, S-7, S-8, S-11 pass).

## Task 7 — validateSlot (D10, E-12, S-13/S-14/S-15)

**DoD:** one-cell predicate correct; reuses Task 3–5 helpers.

- [x] Steps per design §4: findOrFail → week guard (`slot.week_number !== $weekNumber` → false) → status available → holiday day → lecturer free → cohorts free → venue type + capacity
  - Note: design §4 step 2's semester clause is not implementable via the public API (no semester param) — superseded by spec E-12; implement the week guard only
- [x] Unit tests S-13, S-14 (each vector), S-15

**Verify:** `php vendor/phpunit/phpunit/phpunit --no-coverage tests/Unit/MatrixIntersectionEngineTest.php` (S-13..S-15 pass).

## Task 8 — Full unit suite green

**DoD:** all unit scenarios pass together.

- [x] Run full `tests/Unit/MatrixIntersectionEngineTest.php`
- [x] Cross-check each spec scenario S-1..S-16 mapped to a test method (name each `test_Sx_*` matching scenario ids)

## Task 9 — Integration test (D9, E-15, S-17)

**DoD:** real-seed acceptance gate passes.

- [x] `tests/Feature/MatrixIntersectionEngineTest.php` — `RefreshDatabase` + real `DatabaseSeeder`
- [x] Known scenario: lecturer 4288 / DFT2 cohorts, week 5, 'L', 60 — non-empty, green-per-vectors, pinned window (`day:1, 10:00:00, B002`), no day-2 windows
- [x] Timing assert `< 500ms` (generous; widen if flaky — never delete)
- [x] Run: `php vendor/phpunit/phpunit/phpunit --no-coverage tests/Feature/MatrixIntersectionEngineTest.php`

## Task 10 — Quality gates + changelog + commit

**DoD:** house rules satisfied; commit ready.

- [x] `vendor/bin/pint --test` (run `vendor/bin/pint` non-parallel on new files first) — Pint clean
- [x] `vendor/bin/phpstan analyse --memory-limit=1G` — 0 new errors (pre-existing frozen-model errors untouched)
- [x] Full suite: `php vendor/phpunit/phpunit/phpunit --no-coverage` — all green
- [x] Append engine entries to `page-changelogs/backend-automated-by-ai.md`
- [x] Commit: `feat(engine): add MatrixIntersectionEngine with 4-vector set intersection`
