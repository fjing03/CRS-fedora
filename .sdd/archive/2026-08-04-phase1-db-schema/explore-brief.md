# Explore Brief — Phase 1: Database Schema (Unfreeze & Align)

> **Status:** Explore complete 2026-08-03. User ran `/sdd-explore phase1-db-schema` to re-validate the frozen proposal against the full frontend/dataset evidence. Result: 11 decisions grilled & locked; **4 conflict with the frozen proposal.md — user approved UNFREEZE & REWRITE** (decision 2026-08-03).
> **Location:** `/home/jinglinux/tarumt/CRS-fedora` (Fedora dev machine), branch `fedora-jing`. Evidence repo (pull-only): `/home/jinglinux/tarumt/CRS/class-replacement-system`.

---

## 1. What Was Explored

- All 60 blade files + routes; 6 UI design templates + `request-approval` (planned)
- `public/js/mock-data.js` (1080 lines, `window.MockData` — throwaway by design, Sprint 3 replaces it)
- `public/js/ui-common.js` — **shared hour grid**: 30-min cells, `08:00 … 18:30` = **22 cells/day**
- All 11 existing migrations, 7 models, `DatabaseSeeder`, RBAC middleware (`CheckRole`, `CheckPl`)
- `CodingMAIN.md` §5 (planned tables), §7 (FR&NFR), §10 (conventions)
- `FR&NFR.md`, `AGENT-KNOWLEDGE.md`, `dataset/cohorts.md`, `dataset/lecturers.md`
- Existing frozen artifacts: `.sdd/changes/phase1-db-schema/proposal.md` + `review-log.md`

## 2. Hard Constraint (user-mandated, 2026-08-03)

**`users`, `students`, `lecturers` tables are FULLY FROZEN — schema AND data.** No ALTERs, no seeder edits, no re-runs that change their rows. Phase 1 creates only NEW tables, which may reference existing ones via FKs (`users.id`, `cohorts.id`, `faculties.id`…).

## 3. Grilled Decisions (all 11 — the new baseline)

| # | Decision | Locked answer |
|---|----------|---------------|
| D1 | Scope boundary | users/students/lecturers fully frozen (schema + data) |
| D2 | Class instance model | **Weekly `class_sessions` template + derived dates** (rejected materialized `class_instances`) |
| D3 | time_slots population | **Pre-seed full grid per week** (rejected lazy/on-demand rows) |
| D4 | venues columns | **4 columns, NO `room_name`** |
| D5 | modules columns | **3 columns, no `faculty_id`** |
| D6 | class_sessions shape | Template + `session_cohorts` pivot, **no week column**, no stored student_count |
| D7 | Conflicts model | **`class_exceptions` + `holidays` tables** (two tables) |
| D8 | replacement_requests | FK-normalized, **5 statuses incl. `completed`** |
| D9 | audit_logs | Full columns incl. `occ_validation_result` (FR 4.12) |
| D10 | Test strategy | **Switch tests to PostgreSQL** (`class_replacement_testing`) — where it lands TBD (open Q-2) |
| D11 | Semester anchor | **`semesters` table** with `semester_id` FKs (rejected config constant) |

## 4. Rejected Approaches (and why)

| Approach | Rejected because |
|---|---|
| Materialized `class_instances` (session × week rows) | ~700+ redundant rows; dates derivable from `semesters.start_date + (week-1)*7 + day_of_week`; frontend shape is (session, date), not instance IDs |
| Lazy time_slots rows (only contested slots) | FR 4.9/4.11 mandate materialized `version` column + 4-state machine on `time_slots`; grid queries become single-table reads; user picked pre-seed |
| `venues.room_name` | UI never displays names — only codes (B002, B110…); CodingMAIN §5 lists no name field; user chose 4 columns |
| `modules.faculty_id` | Module codes already encode faculty (BMIT/MPU…); MPU modules are cross-faculty by nature |
| `class_sessions.week_number` (nullable = all weeks) | Per-week variation belongs to `class_exceptions` (cancellations) — single source of truth, avoids duplicate template rows |
| One exceptions table only (holidays as per-session rows) | A public holiday cancelling 5 sessions = 5 redundant rows; loses global holiday concept; MockData has global `holidays` section |
| Skip conflict tables in Phase 1 | `conflictedClasses` is the entry point of the replacement flow (replacement-home page) — schema must support it |
| 4 request statuses (no `completed`) | Frontend `requests`/`approvalRequests` use 5 states: Pending/Approved/Rejected/Cancelled/**Completed** (mock-data.js verbatim) |
| Minimal 5-column audit_logs | FR 4.12: "log all OCC validation outcomes" — failed concurrent attempts must be rows, not json blobs |
| Keep SQLite test runner | Existing `users`/`cohorts` migrations emit raw PG `ALTER TABLE … ADD CONSTRAINT CHECK` — now frozen, cannot be made portable; 42 tests, ~1 passing |
| Semester dates in config constant | Dataset pattern is table-driven (`dataset/*.md`, `DatabaseSeeder`); cross-semester integrity via FK |
| status `reserved` stored | Viewer-relative label — derived from `pending` + proposer identity (frozen proposal ruling, kept) |

## 5. Final Solution — Complete Mapping (10 tables)

All tables: `id` bigIncrements PK, `created_at`/`updated_at`. Existing-table FKs: `users.id`, `cohorts.id` (FROZEN — reference only, no ALTER).

### 5.1 `semesters` *(NEW vs frozen proposal)*
| Column | Type | Notes |
|---|---|---|
| `semester_code` | VARCHAR unique | e.g. `202605` |
| `label` | VARCHAR | e.g. `202605 Semester` |
| `start_date` | date | 2026-08-31 (Week-1 Monday) |
| `end_date` | date | 2026-12-06 |
| `week_count` | smallint | 14 |

### 5.2 `venues` *(changed vs frozen: drop room_name)*
| Column | Type | Constraints |
|---|---|---|
| `room_code` | VARCHAR unique | B002, B110… |
| `capacity` | unsignedSmallInt | |
| `room_type` | string CHECK | `tutorial` / `lecture_hall` / `lab` / `cisco_lab` |
| `allowed_session_types` | string | comma-joined, e.g. `"L,T"`, `"P"` |
Indexes: `room_type`, `capacity`. 23 seeded rows (16 tutorial cap 35, 2 lecture hall cap 80, 4 lab cap 28, 1 cisco lab cap 32) — Block B only.

### 5.3 `modules`
| Column | Type | Constraints |
|---|---|---|
| `module_code` | VARCHAR unique | BMIT5555, MPU-3133… |
| `module_name` | VARCHAR | |
| `allowed_session_types` | string | `"L"` / `"L,T"` (MPU-3133 = L/T per FR 4.8) |

### 5.4 `class_sessions` (weekly template)
| Column | Type | Constraints |
|---|---|---|
| `semester_id` | FK → semesters | *(NEW)* |
| `module_id` | FK → modules | |
| `lecturer_id` | FK → users.id | role=lecturer |
| `day_of_week` | unsignedTinyInt CHECK | 0–5 (Mon–Sat; Sunday excluded — see Open Q-1) |
| `start_time` | time | hour-aligned (`:00`), per ui-common grid |
| `end_time` | time | hour-aligned |
| `venue_id` | FK → venues | |
| `session_type` | string CHECK | `L` / `T` / `P` |
Composite indexes: `[lecturer_id, day_of_week, start_time]`, `[venue_id, day_of_week, start_time]`.

### 5.5 `session_cohorts` (pivot)
| Column | Type | Constraints |
|---|---|---|
| `class_session_id` | FK → class_sessions | cascadeOnDelete, part of composite PK |
| `cohort_id` | FK → cohorts | cascadeOnDelete, part of composite PK |
Index: `cohort_id` (engine cohort-availability vector).

### 5.6 `time_slots` (materialized grid, OCC target)
| Column | Type | Constraints |
|---|---|---|
| `semester_id` | FK → semesters | *(NEW)* |
| `class_session_id` | FK → class_sessions | nullable — occupied cells point at occupying session |
| `week_number` | unsignedTinyInt CHECK | 1–14, NOT NULL |
| `day_of_week` | unsignedTinyInt CHECK | 0–5 |
| `start_time` | time | :00 / :30 cell boundaries (see Open Q-3) |
| `end_time` | time | |
| `venue_id` | FK → venues | |
| `status` | string CHECK | `available` / `pending` / `occupied` (`reserved` DERIVED, never stored) |
| `version` | unsignedInt default 1 | OCC counter (FR 4.9) |
Indexes: `[venue_id, day_of_week, start_time, week_number]`, `status`. **Partial unique index**: `(venue_id, day_of_week, start_time, week_number) WHERE status IN ('pending','occupied')` — DB-level no-double-booking guard.
Grid volume: 22 cells × 5 days × 14 weeks × 23 venues ≈ **35,420 rows**.

### 5.7 `holidays` *(NEW vs frozen proposal)*
| Column | Type | Notes |
|---|---|---|
| `semester_id` | FK → semesters | *(NEW)* |
| `week_number` | unsignedTinyInt | 1–14 |
| `day_of_week` | unsignedTinyInt | |
| `label` | VARCHAR | `Public Holiday` |

### 5.8 `class_exceptions` *(NEW vs frozen proposal)*
| Column | Type | Notes |
|---|---|---|
| `class_session_id` | FK → class_sessions | cancelled instance |
| `week_number` | unsignedTinyInt CHECK 1–14 | |
| `reason` | string CHECK | `public_holiday` / `annual_leave` / `medical_leave` / `official_event` / `emergency_leave` |
Unique: `(class_session_id, week_number)` — one exception per instance.

### 5.9 `replacement_requests` *(changed vs frozen: 5 statuses, +semester_id)*
| Column | Type | Notes |
|---|---|---|
| `semester_id` | FK → semesters | *(NEW)* |
| `proposer_id` | FK → users | lecturer |
| `class_session_id` | FK → class_sessions | ORIGINAL session |
| `week_number` | unsignedTinyInt CHECK 1–14 | which week's occurrence |
| `replacement_time_slot_id` | FK → time_slots | OCC target; NOT NULL — a request must pick a slot |
| `approver_id` | FK → users, nullable | PL |
| `status` | string CHECK | `pending` / `approved` / `rejected` / `cancelled` / **`completed`** *(+completed vs frozen)* |
| `rejection_reason` | text, nullable | mandatory on reject (FR 3.6) |
| `remarks` | text, nullable | matches MockData |
| `submitted_at` | timestamp | = created_at, no draft state |
| `decided_at` | timestamp, nullable | |
Index: `[status, submitted_at]` (FCFS queue order, FR 3.2).

### 5.10 `audit_logs`
| Column | Type | Notes |
|---|---|---|
| `user_id` | FK → users | actor |
| `action` | string CHECK | `submitted` / `approved` / `rejected` / `cancelled` / `completed` / `occ_conflict` |
| `replacement_request_id` | FK, nullable | |
| `time_slot_id` | FK, nullable | |
| `old_status` | string, nullable | |
| `new_status` | string, nullable | |
| `occ_validation_result` | string CHECK nullable | `success` / `conflict` (FR 4.12) |
| `details` | json, nullable | rejection reason, conflict info |
Indexes: `replacement_request_id`, `created_at`.

### 5.11 Canonical label sets (ALL — pin in specs)
- `room_type`: tutorial, lecture_hall, lab, cisco_lab
- `session_type`: L, T, P
- `slot status` (stored): available, pending, occupied — + `reserved` (derived viewer label)
- `request status`: pending, approved, rejected, cancelled, completed
- `conflict reason`: public_holiday, annual_leave, medical_leave, official_event, emergency_leave
- `audit action`: submitted, approved, rejected, cancelled, completed, occ_conflict
- `day_of_week`: 0=Mon … 5=Sat (Sunday excluded); `week_number`: 1–14
- Stored lowercase; UI title-case is presentation (house ruling, kept)

## 6. Key Cross-Module Data Flows

1. **Date derivation** (all pages): `date = semesters.start_date + (week_number − 1) × 7 + day_of_week`. `classDate`, holiday dates, grid dates all derive here.
2. **Request → original class**: `replacement_requests.class_session_id` + `.week_number` → resolves courseCode/Name (module), classType (session_type), timeStart/End (session), venue, cohorts (pivot) — the FCFS queue row (FR 3.3).
3. **Request → replacement**: `replacement_time_slot_id` → `time_slots` (semester, week, day, start, end, venue) → UI `replacementDate/Time/Venue`. OCC lock happens on this row.
4. **OCC (FR 4.9–4.12)**: `SELECT … FOR UPDATE` on time_slots → status check → `UPDATE … SET status='pending', version=version+1 WHERE id=? AND version=?` → affected_rows=0 ⇒ conflict → `audit_logs(action='occ_conflict', occ_validation_result='conflict')`. Exactly one winner (FR 4.10).
5. **Conflicted-classes list** (replacement-home): sessions on a `holidays` day OR with a `class_exceptions` row for that week → `conflictReason`.
6. **Slot state transitions**: available → pending (submit, version++) → occupied (PL approve). Pending→available on reject/cancel. `completed` set on approved request at a later phase (when? — Open Q-4).
7. **Grid query**: `time_slots WHERE semester_id=? AND venue_id=? AND week_number=?` → 22-cell rows; timetable pages read occupied via session templates + exceptions.

## 7. Frozen-Proposal Conflicts Requiring Unfreeze (user approved)

1. `venues.room_name` — DROP (D4)
2. `replacement_requests.status` — add `completed` (D8)
3. Tables `semesters`, `holidays`, `class_exceptions` — ADD (D7, D11) → proposal becomes **10 migrations**
4. `semester_id` FKs on class_sessions / time_slots / holidays / replacement_requests — ADD (D11)

## 8. Open Questions (carry into propose/design)

- **Q-1:** MockData has conflicted classes on Saturday *and Sunday* (`day: 'Saturday'`, `day: 'Sunday'` rows) vs frozen ruling "Mon–Sat only, Sunday excluded". Ruling stands for schema (CHECK 0–5); Sunday = grid-hard-forced occupied (no rows). Verify during `dataset/timetable.md` synthesis (phase 2 seed change).
- **Q-2:** D10 (PostgreSQL test switch) — fold into THIS change or sibling change (frozen proposal scoped it out). User approved switching; brief recommends folding in since new CHECKs re-break SQLite tests.
- **Q-3:** 30-min cell granularity — enforce `start_time`/`end_time` at `:00`/`:30` boundaries with CHECK? (Partial unique index covers the start cell; end-cell coverage is a design decision.)
- **Q-4:** `completed` semantics — set when replacement occurs? By whom (app layer, later phase)? Schema stores it; lifecycle wiring is out of Phase-1 scope.
- **Q-5:** Seeders, models, services, test-DB migrate: deliberately OUT of scope (separate changes per frozen proposal: `phase2-db-seed`, etc.) — unchanged.

## 9. Transition

Scope is clear and re-baselined. Next: run `/sdd-propose phase1-db-schema` to UNFREEZE and rewrite `proposal.md` (10 migrations) + design + specs per this brief, then review.
