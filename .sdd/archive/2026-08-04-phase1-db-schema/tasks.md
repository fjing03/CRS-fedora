# Tasks: Phase 1 — Database Schema

> Frozen artifacts: proposal.md, design.md, specs/ (7 files). This tasks.md is the implementation checklist.

---

## Task 1: Create `semesters` migration (≈30 min)

- [x] Create `database/migrations/2026_08_03_000001_create_semesters_table.php`
- [x] Columns: id, semester_code (VARCHAR unique), label, start_date (date), end_date (date), week_count (unsignedSmallInt), timestamps
- [x] Verify: `php artisan migrate` runs clean on PostgreSQL

## Task 2: Create `venues` migration (≈30 min)

- [x] Create `database/migrations/2026_08_03_000002_create_venues_table.php`
- [x] Columns: id, room_code (VARCHAR unique), capacity (unsignedSmallInt), room_type (VARCHAR + CHECK), allowed_session_types (VARCHAR), timestamps
- [x] CHECK: `room_type IN ('tutorial', 'lecture_hall', 'lab', 'cisco_lab')`
- [x] Indexes: room_type, capacity
- [x] Verify: `php artisan migrate` runs clean

## Task 3: Create `modules` migration (≈15 min)

- [x] Create `database/migrations/2026_08_03_000003_create_modules_table.php`
- [x] Columns: id, module_code (VARCHAR unique), module_name (VARCHAR), allowed_session_types (VARCHAR), timestamps
- [x] Verify: `php artisan migrate` runs clean

## Task 4: Create `class_sessions` migration (≈30 min)

- [x] Create `database/migrations/2026_08_03_000004_create_class_sessions_table.php`
- [x] Columns: id, semester_id (FK→semesters), module_id (FK→modules), lecturer_id (FK→users), day_of_week (unsignedTinyInt + CHECK 0–5), start_time (time), end_time (time), venue_id (FK→venues), session_type (VARCHAR + CHECK L/T/P), timestamps
- [x] Composite indexes: [lecturer_id, day_of_week, start_time], [venue_id, day_of_week, start_time]
- [x] CHECKs: day_of_week BETWEEN 0 AND 5; session_type IN ('L', 'T', 'P')
- [x] Verify: `php artisan migrate` runs clean

## Task 5: Create `session_cohorts` pivot migration (≈15 min)

- [x] Create `database/migrations/2026_08_03_000005_create_session_cohorts_table.php`
- [x] Columns: class_session_id (FK→class_sessions, cascadeOnDelete), cohort_id (FK→cohorts, cascadeOnDelete), composite PK
- [x] Index: cohort_id
- [x] Verify: `php artisan migrate` runs clean

## Task 6: Create `time_slots` migration (≈45 min)

- [x] Create `database/migrations/2026_08_03_000006_create_time_slots_table.php`
- [x] Columns: id, semester_id (FK→semesters), class_session_id (FK→class_sessions, nullable), week_number (unsignedTinyInt + CHECK 1–14, NOT NULL), day_of_week (unsignedTinyInt + CHECK 0–5), start_time (time + CHECK minute ∈ {0,30}), end_time (time), venue_id (FK→venues), status (VARCHAR + CHECK), version (unsignedInt default 1), timestamps
- [x] Indexes: [venue_id, day_of_week, start_time, week_number], status
- [x] Partial unique index: (venue_id, day_of_week, start_time, week_number) WHERE status IN ('pending','occupied')
- [x] CHECKs: week_number BETWEEN 1 AND 14; day_of_week BETWEEN 0 AND 5; status IN ('available','pending','occupied'); extract(minute FROM start_time) IN (0, 30); extract(minute FROM start_time) IN (0, 30)
- [x] Verify: `php artisan migrate` runs clean

## Task 7: Create `holidays` migration (≈15 min)

- [x] Create `database/migrations/2026_08_03_000007_create_holidays_table.php`
- [x] Columns: id, semester_id (FK→semesters), week_number (unsignedTinyInt), day_of_week (unsignedTinyInt + CHECK 0–5), label (VARCHAR), timestamps
- [x] Index: [semester_id, week_number, day_of_week]
- [x] Verify: `php artisan migrate` runs clean

## Task 8: Create `class_exceptions` migration (≈15 min)

- [x] Create `database/migrations/2026_08_03_000008_create_class_exceptions_table.php`
- [x] Columns: id, class_session_id (FK→class_sessions, cascadeOnDelete), week_number (unsignedTinyInt + CHECK 1–14), reason (VARCHAR + CHECK 5 values), timestamps
- [x] Unique: (class_session_id, week_number)
- [x] CHECK: reason IN ('public_holiday', 'annual_leave', 'medical_leave', 'official_event', 'emergency_leave')
- [x] Verify: `php artisan migrate` runs clean

## Task 9: Create `replacement_requests` migration (≈30 min)

- [x] Create `database/migrations/2026_08_03_000009_create_replacement_requests_table.php`
- [x] Columns: id, semester_id (FK→semesters), proposer_id (FK→users), class_session_id (FK→class_sessions), week_number (unsignedTinyInt + CHECK 1–14), replacement_time_slot_id (FK→time_slots, NOT NULL), approver_id (FK→users, nullable), status (VARCHAR + CHECK 5 states), rejection_reason (text, nullable), remarks (text, nullable), submitted_at (timestamp), decided_at (timestamp, nullable), timestamps
- [x] Index: [status, submitted_at]
- [x] CHECKs: week_number BETWEEN 1 AND 14; status IN ('pending','approved','rejected','cancelled','completed')
- [x] Verify: `php artisan migrate` runs clean

## Task 10: Create `audit_logs` migration (≈20 min)

- [x] Create `database/migrations/2026_08_03_000010_create_audit_logs_table.php`
- [x] Columns: id, user_id (FK→users), action (VARCHAR + CHECK 6 values), replacement_request_id (FK, nullable), time_slot_id (FK, nullable), old_status (VARCHAR, nullable), new_status (VARCHAR, nullable), occ_validation_result (VARCHAR + CHECK nullable), details (json, nullable), timestamps
- [x] Indexes: replacement_request_id, created_at
- [x] CHECKs: action IN ('submitted','approved','rejected','cancelled','completed','occ_conflict'); occ_validation_result IN ('success','conflict')
- [x] Verify: `php artisan migrate` runs clean

## Task 11: Full migration + rollback verification (≈20 min)

- [x] Run `php artisan migrate:fresh` — all 10 tables created
- [x] Run `\d` (or `\dt`) in psql — verify all 10 tables exist
- [x] Run `\d <table>` for each table — verify columns, types, CHECK constraints, indexes, partial unique index
- [x] Run `php artisan migrate:rollback --step=10` — all 10 tables dropped cleanly
- [x] Run `php artisan migrate` again — clean re-creation

## Task 12: Test suite verification (≈20 min)

- [x] Update `phpunit.xml` to use pgsql `class_replacement_testing` (if folded into this change)
- [x] Run `composer run test` — verify tests pass on PostgreSQL
- [x] Confirm no regressions in existing 42 tests
- [x] If test-DB switch is deferred, document in review-log as open item
