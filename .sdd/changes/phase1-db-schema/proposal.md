# Proposal: Phase 1 — Database Schema (10 Migrations)

## Why This Change Is Needed

The system currently has **no domain tables** beyond users/org structure (faculties → departments → programmes → cohorts → students → lecturers). Every FYP objective depends on schema that doesn't exist yet:

- **Objective 1** (Matrix Intersection Engine) needs venues + modules + class sessions + time slots to find conflict-free slots
- **Objective 2** (OCC) needs a versioned `time_slots` table for optimistic locking
- **Objective 3** (FCFS Approval Dashboard) needs `replacement_requests`
- **Auditability** (FR 3.7: PL identity/timestamp/action/slot/reason; FR 4.12: OCC validation outcomes logged) needs an event trail
- **Conflict model** — `conflictedClasses` on the replacement-home page is the entry point of the replacement flow; schema must support it
- **Semester anchor** — all dates derive from semester start; table-driven to match existing dataset pattern (cohorts.md, lecturers.md)

This change creates the full domain schema (10 migrations) as the contract everything downstream builds on. No seeders, models, or services yet — those are separate changes (`phase2-db-seed`, etc.).

## Hard Constraint (user-mandated, 2026-08-03)

**`users`, `students`, `lecturers` tables are FULLY FROZEN — schema AND data.** No ALTERs, no seeder edits, no re-runs that change their rows. This change creates only NEW tables, which may reference existing ones via FKs (`users.id`, `cohorts.id`, `faculties.id`…).

## Scope

### In Scope — 10 new migration files in `database/migrations/`

| # | File | Table | Key columns / constraints |
|---|------|-------|---------------------------|
| 1 | `2026_08_03_000001_create_semesters_table.php` | `semesters` | `semester_code` (VARCHAR unique, e.g. `202605`), `label` (VARCHAR), `start_date` (date), `end_date` (date), `week_count` (unsignedSmallInt) |
| 2 | `2026_08_03_000002_create_venues_table.php` | `venues` | `room_code` (VARCHAR unique), `capacity` (unsignedSmallInt), `room_type` (CHECK: tutorial/lecture_hall/lab/cisco_lab), `allowed_session_types` (string, e.g. `"L,T"`, `"P"`); indexes: `room_type`, `capacity` |
| 3 | `2026_08_03_000003_create_modules_table.php` | `modules` | `module_code` (VARCHAR unique), `module_name` (VARCHAR), `allowed_session_types` (string, e.g. `"L"`, `"L,T"`) |
| 4 | `2026_08_03_000004_create_class_sessions_table.php` | `class_sessions` | `semester_id` (FK→semesters), `module_id` (FK→modules), `lecturer_id` (FK→users), `day_of_week` (CHECK 0–5, Mon–Sat), `start_time` (time), `end_time` (time), `venue_id` (FK→venues), `session_type` (CHECK: L/T/P); composite indexes: `[lecturer_id, day_of_week, start_time]`, `[venue_id, day_of_week, start_time]` |
| 5 | `2026_08_03_000005_create_session_cohorts_table.php` | `session_cohorts` | pivot: `class_session_id` (FK→class_sessions, cascadeOnDelete) + `cohort_id` (FK→cohorts, cascadeOnDelete), composite PK; index: `cohort_id` |
| 6 | `2026_08_03_000006_create_time_slots_table.php` | `time_slots` | `semester_id` (FK→semesters), `class_session_id` (FK→class_sessions, nullable — occupied cells reference occupying session), `week_number` (1–14 CHECK, NOT NULL), `day_of_week` (CHECK 0–5), `start_time` (time), `end_time` (time), `venue_id` (FK→venues), `status` (CHECK: available/pending/occupied), `version` (unsignedInt, default 1 — OCC counter); indexes: `[venue_id, day_of_week, start_time, week_number]`, `status`; **partial unique index**: `(venue_id, day_of_week, start_time, week_number)` WHERE `status IN ('pending','occupied')` — DB-level no-double-booking guard |
| 7 | `2026_08_03_000007_create_holidays_table.php` | `holidays` | `semester_id` (FK→semesters), `week_number` (unsignedSmallInt), `day_of_week` (unsignedSmallInt), `label` (VARCHAR, e.g. `Public Holiday`); index: `[semester_id, week_number, day_of_week]` |
| 8 | `2026_08_03_000008_create_class_exceptions_table.php` | `class_exceptions` | `class_session_id` (FK→class_sessions), `week_number` (1–14 CHECK), `reason` (CHECK: public_holiday/annual_leave/medical_leave/official_event/emergency_leave); unique: `(class_session_id, week_number)` — one exception per instance |
| 9 | `2026_08_03_000009_create_replacement_requests_table.php` | `replacement_requests` | `semester_id` (FK→semesters), `proposer_id` (FK→users), `class_session_id` (FK→class_sessions — the **original** session), `week_number` (1–14 CHECK — which week's occurrence), `replacement_time_slot_id` (FK→time_slots — OCC target, NOT NULL), `approver_id` (FK→users, nullable), `status` (CHECK: pending/approved/rejected/cancelled/completed), `rejection_reason` (text, nullable, mandatory on reject per FR 3.6), `remarks` (text, nullable), `submitted_at` (timestamp = created_at, no draft state), `decided_at` (timestamp, nullable); index: `[status, submitted_at]` (FCFS queue order, FR 3.2) |
| 10 | `2026_08_03_000010_create_audit_logs_table.php` | `audit_logs` | `user_id` (FK→users), `action` (string CHECK: submitted/approved/rejected/cancelled/completed/occ_conflict), `replacement_request_id` (FK, nullable), `time_slot_id` (FK, nullable), `old_status` (nullable), `new_status` (nullable), `occ_validation_result` (string CHECK nullable: success/conflict — FR 4.12), `details` (json, nullable); indexes: `replacement_request_id`, `created_at` |

All tables: `id` bigIncrements PK, `created_at`/`updated_at` timestamps. All CHECK constraints and FKs use PostgreSQL-native syntax consistent with existing migrations.

### Domain Rulings (pinned)

- **Day-of-week:** classes are scheduled Mon–Sat (`dayIndex 0–5`); Sunday is excluded from scheduling. CHECK 0–5. Sunday grid cells hard-forced occupied in the UI (replacement-arrangement template).
- **Slot state machine (FR 4.0–4.11):** stored statuses are `available` → `pending` (self) → `occupied`. `reserved` (other-viewer label) is **derived** from `pending` + different `proposer` — never stored; pinned in specs.
- **Enum encoding:** statuses stored lowercase (`'pending'`, `'approved'`…); UI title-case rendering is a presentation concern, not schema.
- **No draft state:** `submitted_at` = `created_at`; a request is submitted the moment it's created.
- **Audit immutability:** enforced at app layer (no DB trigger); pinned in specs.
- **`completed` semantics:** set on approved request at a later phase (app layer wiring); schema stores the status; lifecycle is out of Phase-1 scope.
- **Reserved derivation:** viewer-relative label — never a stored row; derived from `pending` status + comparing `proposer_id` with current user (FR 4.11).

### Out of Scope

- **Seeders** (venues, modules, class sessions, time slots, holidays, exceptions) → separate change `phase2-db-seed`
- **Eloquent models** → separate change
- **Services** (MatrixIntersectionEngine, OCCValidator, FCFS queue) → later changes
- Controllers, routes, views, UI
- Changing the 11 existing migrations
- **Test DB / phpunit.xml** — user approved switching to PostgreSQL; to be handled in this change or a sibling (open decision)

## Specs to Create (contract for specs batch)

- `semesters`: semester catalog, date derivation formula
- `venues`: venue catalog — room types, capacity, session-type constraints (no `room_name`)
- `timetable`: modules + class_sessions + session_cohorts — weekly schedule model (Mon–Sat, week 1–14)
- `time-slots`: week-scoped availability grid, status semantics (available/pending/occupied, `reserved` derived), OCC `version` semantics, partial-unique no-double-booking guard
- `replacement-requests`: lifecycle states (5 statuses), original-session ↔ proposed-slot linkage, FCFS queue fields, no-draft ruling
- `conflict-model`: holidays + class_exceptions — the entry point of the replacement flow
- `audit-log`: app-layer immutability contract, FR 3.7/4.12 field coverage

## Impact Scope

| File | Action |
|------|--------|
| `database/migrations/2026_08_03_000001_create_semesters_table.php` | **New** |
| `database/migrations/2026_08_03_000002_create_venues_table.php` | **New** |
| `database/migrations/2026_08_03_000003_create_modules_table.php` | **New** |
| `database/migrations/2026_08_03_000004_create_class_sessions_table.php` | **New** |
| `database/migrations/2026_08_03_000005_create_session_cohorts_table.php` | **New** |
| `database/migrations/2026_08_03_000006_create_time_slots_table.php` | **New** |
| `database/migrations/2026_08_03_000007_create_holidays_table.php` | **New** |
| `database/migrations/2026_08_03_000008_create_class_exceptions_table.php` | **New** |
| `database/migrations/2026_08_03_000009_create_replacement_requests_table.php` | **New** |
| `database/migrations/2026_08_03_000010_create_audit_logs_table.php` | **New** |

No other files are modified.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| PG-only CHECK constraints break SQLite test runner (known issue, frozen existing migrations) | High | Test-DB switch to PostgreSQL (approved); fold into this change or sibling — open decision |
| FK cascade direction wrong for pivot | Low | `cascadeOnDelete` on both pivot FKs; reviewed in design |
| Enum strings drift from frontend `MockData` | Med | Lowercase canonical set fixed in this proposal; specs pin the mapping |
| Duplicate bookings (race) despite OCC | Low | Partial unique index on occupied/pending slot cells (DB-level guard) |
| Sunday class needs schema change | Low | CHECK 0–5 is explicit; widening = one future migration |
| `completed` semantics undefined at schema time | Low | Schema stores the status; lifecycle wiring deferred to later phase |
| 30-min cell boundary enforcement | Low | CHECK on start_time/minute ∈ {0,30} — design decision for design.md |

## Rollback Plan

`php artisan migrate:rollback --step=10` drops all 10 tables in reverse dependency order. No data is at risk (fresh tables, no seeders run).

## Success Criteria

- [ ] `php artisan migrate` runs clean on PostgreSQL `class_replacement`
- [ ] `php artisan migrate:rollback --step=10` drops all 10 tables cleanly
- [ ] All 10 tables + indexes + CHECK constraints + partial unique index verified via `\d` / information_schema
- [ ] Review passes: no 🔴 issues in review-log for proposal batch
