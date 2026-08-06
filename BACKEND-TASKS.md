# Backend Development — Full Task Breakdown

> **Scope:** DB schema + seed + core backend services. No UI work.
> **Machine:** Fedora (`/home/jinglinux/tarumt/CRS-fedora`, branch `fedora-jing`)
> **Reference:** Pull from `FjingXR/class-replacement-system` (fjing branch) — read only
> **Source of truth:** CodingMAIN.md, FR&NFR.md
> **DB:** PostgreSQL `class_replacement`, user `philler`, password `secret`
> **Test DB:** PostgreSQL `class_replacement_testing`

---

## Phase 1: Database Schema (migrations + models)

### Task 1.1 — Create `venues` migration
**File:** `database/migrations/xxxx_create_venues_table.php`
```
venues
├── id                  (bigIncrements, PK)
├── room_code           (string, 10, unique)         — e.g. "B002"
├── room_name           (string, 100)                — e.g. "Tutorial Room B002"
├── capacity            (unsignedSmallInteger)        — e.g. 35
├── room_type           (string, 20)                  — CHECK: tutorial/lecture_hall/lab/cisco_lab
├── allowed_session_types (string, 10)                — e.g. "L,T" or "L,T,P" or "P"
├── created_at / updated_at
```
- Index on `room_type`
- Index on `capacity`
- CHECK via `DB::statement()` (matches cohorts convention)

### Task 1.2 — Create `modules` migration
**File:** `database/migrations/xxxx_create_modules_table.php`
```
modules
├── id                  (bigIncrements, PK)
├── module_code         (string, 20, unique)         — e.g. "MPU-3133"
├── module_name         (string, 150)                — e.g. "Falsafah dan Isu Semasa"
├── session_types       (string, 10)                 — e.g. "L,T" or "L,T,P"
├── created_at / updated_at
```
- Simple table, no CHECK needed

### Task 1.3 — Create `class_sessions` migration
**File:** `database/migrations/xxxx_create_class_sessions_table.php`
```
class_sessions
├── id                  (bigIncrements, PK)
├── module_id           (foreignId → modules)
├── lecturer_id         (foreignId → users)
├── day_of_week         (unsignedTinyInteger)        — 0=Mon..4=Fri, CHECK 0–4
├── start_time          (time)
├── end_time            (time)
├── venue_id            (foreignId → venues)
├── session_type        (string, 1)                  — CHECK: L/T/P
├── week_number         (unsignedTinyInteger, nullable) — null = all weeks
├── created_at / updated_at
```
- Composite index: `[lecturer_id, day_of_week, start_time]` — intersection query
- Index: `[venue_id, day_of_week, start_time]` — room vacancy lookups
- CHECK on `day_of_week` (0–4) via `DB::statement()`
- CHECK on `session_type` (L/T/P) via `DB::statement()`

### Task 1.4 — Create `session_cohorts` pivot migration
**File:** `database/migrations/xxxx_create_session_cohorts_table.php`
```
session_cohorts
├── class_session_id    (foreignId → class_sessions, cascadeOnDelete)
├── cohort_id           (foreignId → cohorts, cascadeOnDelete)
├── PRIMARY KEY (class_session_id, cohort_id)
```
- Composite primary key (no auto-increment `id`)
- Two foreign keys with cascadeOnDelete

### Task 1.5 — Create `time_slots` migration
**File:** `database/migrations/xxxx_create_time_slots_table.php`
```
time_slots
├── id                  (bigIncrements, PK)
├── class_session_id    (foreignId → class_sessions, nullable) — null = available slot
├── day_of_week         (unsignedTinyInteger)        — 0–4, CHECK 0–4
├── start_time          (time)
├── end_time            (time)
├── venue_id            (foreignId → venues)
├── status              (string, 20)                 — CHECK: available/pending/reserved/occupied
├── version             (unsignedInteger, default 1) — OCC counter
├── created_at / updated_at
```
- Index: `[day_of_week, start_time, venue_id]` — core intersection query
- Index: `status` — filtering available slots
- CHECK on `day_of_week` (0–4) via `DB::statement()`
- CHECK on `status` via `DB::statement()`

### Task 1.6 — Create `replacement_requests` migration
**File:** `database/migrations/xxxx_create_replacement_requests_table.php`
```
replacement_requests
├── id                  (bigIncrements, PK)
├── time_slot_id        (foreignId → time_slots)
├── proposer_id         (foreignId → users)
├── approver_id         (foreignId → users, nullable)
├── status              (string, 20)                 — CHECK: pending/approved/rejected/cancelled
├── rejection_reason    (text, nullable)
├── submitted_at        (timestamp)
├── decided_at          (timestamp, nullable)
├── created_at / updated_at
```
- Index: `[status, submitted_at]` — FCFS queue ordering
- CHECK on `status` via `DB::statement()`

### Task 1.7 — Create `audit_logs` migration
**File:** `database/migrations/xxxx_create_audit_logs_table.php`
```
audit_logs
├── id                       (bigIncrements, PK)
├── user_id                  (foreignId → users)
├── action                   (string, 30)              — e.g. 'submitted'/'approved'/'rejected'
├── replacement_request_id   (foreignId → replacement_requests, nullable)
├── time_slot_id             (foreignId → time_slots, nullable)
├── old_status               (string, 20, nullable)
├── new_status               (string, 20, nullable)
├── occ_validation_result    (string, 20, nullable)    — 'success'/'conflict'
├── details                  (json, nullable)
├── created_at               (timestamp)
```
- Index: `[replacement_request_id]`
- Index: `[created_at]`

### Task 1.8 — Create `Venue` model
**File:** `app/Models/Venue.php`
- `$fillable`: room_code, room_name, capacity, room_type, allowed_session_types
- Relationships: `hasMany(ClassSession::class)`, `hasMany(TimeSlot::class)`
- PHPStan `@property` annotations (match User.php pattern)

### Task 1.9 — Create `Module` model
**File:** `app/Models/Module.php`
- `$fillable`: module_code, module_name, session_types
- Relationships: `hasMany(ClassSession::class)`

### Task 1.10 — Create `ClassSession` model
**File:** `app/Models/ClassSession.php`
- `$fillable`: module_id, lecturer_id, day_of_week, start_time, end_time, venue_id, session_type, week_number
- `$casts`: start_time => date:H:i, end_time => date:H:i, week_number => integer
- Relationships: `belongsTo(Module::class)`, `belongsTo(User::class, 'lecturer_id')`, `belongsTo(Venue::class)`, `belongsToMany(Cohort::class, 'session_cohorts')`

### Task 1.11 — Create `SessionCohort` model (pivot)
**File:** `app/Models/SessionCohort.php`
- No `$fillable` (pivot — direct DB inserts only)
- Relationships: `belongsTo(ClassSession::class)`, `belongsTo(Cohort::class)`

### Task 1.12 — Create `TimeSlot` model
**File:** `app/Models/TimeSlot.php`
- `$fillable`: class_session_id, day_of_week, start_time, end_time, venue_id, status, version
- `$casts`: start_time => date:H:i, end_time => date:H:i, version => integer
- Relationships: `belongsTo(ClassSession::class)`, `belongsTo(Venue::class)`

### Task 1.13 — Create `ReplacementRequest` model
**File:** `app/Models/ReplacementRequest.php`
- `$fillable`: time_slot_id, proposer_id, approver_id, status, rejection_reason, submitted_at, decided_at
- `$casts`: submitted_at => datetime, decided_at => datetime
- Relationships: `belongsTo(TimeSlot::class)`, `belongsTo(User::class, 'proposer_id')`, `belongsTo(User::class, 'approver_id')`

### Task 1.14 — Create `AuditLog` model
**File:** `app/Models/AuditLog.php`
- `$fillable`: user_id, action, replacement_request_id, time_slot_id, old_status, new_status, occ_validation_result, details
- `$casts`: details => array, created_at => datetime
- Relationships: `belongsTo(User::class)`, `belongsTo(ReplacementRequest::class)`, `belongsTo(TimeSlot::class)`

### Task 1.15 — Add relationships to existing Models
**User.php** — add:
- `hasMany(ClassSession::class, 'lecturer_id')` — lecturer's sessions
- `hasMany(ReplacementRequest::class, 'proposer_id')` — lecturer's requests
- `hasMany(ReplacementRequest::class, 'approver_id')` — PL's acted requests
- `hasMany(AuditLog::class)` — user's audit trail

**Lecturer.php** — add:
- `hasMany(ClassSession::class, 'user_id')` — via user_id FK

**Cohort.php** — add:
- `belongsToMany(ClassSession::class, 'session_cohorts')` — cross-cohort modules

### Task 1.16 — Run `php artisan migrate:fresh`
- Verify all 18 tables created (11 existing + 7 new)
- Verify CHECK constraints work on PostgreSQL

### Task 1.17 — Verify schema with `pgcli`
- Connect: `pgcli -U philler -d class_replacement`
- `\dt` — list all tables
- `\d venues` — verify columns, constraints, indexes
- Run sample INSERT + SELECT on venues to verify CHECK works

### Task 1.18 — Run lint + type check
- `./vendor/bin/pint`
- `./vendor/bin/phpstan analyse` (if configured)

### Task 1.19 — Commit + push
- `git add -A`
- `git commit -m "feat(db): add venues, modules, class_sessions, session_cohorts, time_slots, replacement_requests, audit_logs migrations + models"`
- `git push origin fedora-jing`

---

## Phase 2: Seed Data

### Task 2.1 — Create `VenueSeeder`
**File:** `database/seeders/VenueSeeder.php`
- Seed 23 Block B rooms from `dataset/venues.md`:
  - 16 Tutorial Rooms: B002, B014–B018, B100–B109 (capacity ≤35, allowed: L,T)
  - 2 Lecture Halls: B110, B111 (capacity >35, allowed: L,T)
  - 4 Computer Labs: B005, B009–B011 (capacity 28, allowed: P only)
  - 1 Cisco Lab: B006 (capacity 32, allowed: P, networking priority)

### Task 2.2 — Synthesize `dataset/timetable.md`
**File:** `dataset/timetable.md`
- Create a realistic 14-week repeating weekly timetable baseline
- Must include:
  - All 14 cohorts (DFT1, DFT2, DSF1, DSF2, RSD1, RSD2G1–G3, RSD3G1–G3, RAF2G2, RAF2G4, RBU1)
  - All 14 lecturers (5425, 5516, 4288, 3221, 3825, 4127, 2873, 5514, 5599, 5652, 5770, 3799, 4363, 5254)
  - All 23 rooms
  - 20–30 class blocks per week
  - Time range: 08:00–18:00, 1-hour blocks (some 2-hour)
  - Module codes: realistic Malaysian university naming (BMCS, BACS, MPU, etc.)
- High-risk use cases to include:
  1. **MPU-3133 (Falsafah):** cross-faculty (RAF2, RBU1, RSD3) — L sessions, venue filter = exclude labs
  2. **MPU-3232 (Entrepreneurship):** cross-year stacking (RSD2G2, RSD2G3, RSD3G3) — L+T sessions

### Task 2.3 — Create `ModuleSeeder`
**File:** `database/seeders/ModuleSeeder.php`
- Seed modules from the timetable dataset
- Each module: code, name, session_types

### Task 2.4 — Create `ClassSessionSeeder`
**File:** `database/seeders/ClassSessionSeeder.php`
- Seed class_sessions from the timetable dataset
- Each session links to: module, lecturer, venue, day/time, session_type
- Create pivot records in `session_cohorts` for each session

### Task 2.5 — Create `TimeSlotSeeder`
**File:** `database/seeders/TimeSlotSeeder.php`
- Pre-compute the availability grid:
  - For each (day, time, venue) in the timetable → create row with status = 'occupied'
  - All other (day, time, venue) combos → create row with status = 'available'
  - Set `class_session_id` for occupied slots
- This is the pre-computed availability matrix for the intersection engine

### Task 2.6 — Update `DatabaseSeeder`
**File:** `database/seeders/DatabaseSeeder.php`
- Add calls to new seeders in order:
  1. `$this->call(VenueSeeder::class)`
  2. `$this->call(ModuleSeeder::class)`
  3. `$this->call(ClassSessionSeeder::class)`
  4. `$this->call(TimeSlotSeeder::class)`
- Keep existing seeders (faculties, departments, programmes, cohorts, students, lecturers)

### Task 2.7 — Run `php artisan migrate:fresh --seed`
- Verify all data loads without errors
- Verify 23 venues, modules, class_sessions, time_slots created

### Task 2.8 — Verify seed data with `pgcli`
- `SELECT COUNT(*) FROM venues;` — expect 23
- `SELECT COUNT(*) FROM modules;` — expect N modules
- `SELECT COUNT(*) FROM class_sessions;` — expect 20–30 rows
- `SELECT COUNT(*) FROM time_slots;` — expect ~16,100 rows (14 weeks × 5 days × 10 hrs × 23 venues)
- `SELECT * FROM time_slots WHERE status = 'occupied' LIMIT 5;` — verify occupied slots have class_session_id
- `SELECT * FROM session_cohorts LIMIT 10;` — verify pivot records

### Task 2.9 — Run lint
- `./vendor/bin/pint`

### Task 2.10 — Commit + push
- `git add -A`
- `git commit -m "feat(seed): add venue, module, class_session, time_slot seeders with timetable dataset"`
- `git push origin fedora-jing`

---

## Phase 3: MatrixIntersectionEngine

### Task 3.1 — Create service class skeleton
**File:** `app/Services/MatrixIntersectionEngine.php`
```php
class MatrixIntersectionEngine
{
    public function findAvailableSlots(
        int $lecturerId,
        array $cohortIds,
        ?int $venueId = null,
        string $sessionType = 'L',
        int $duration = 60,
        ?int $weekNumber = null,
    ): array {}
}
```

### Task 3.2 — Implement Vector 1: Lecturer occupied slots
- Query `class_sessions` WHERE `lecturer_id` = $lecturerId
- If `$weekNumber` provided, filter by `week_number` IS NULL OR `week_number` = $weekNumber
- Return array of `['day' => int, 'start' => time, 'end' => time]`

### Task 3.3 — Implement Vector 2: Cohort occupied slots
- Query `class_sessions` via `session_cohorts` pivot WHERE `cohort_id` IN ($cohortIds)
- If `$weekNumber` provided, filter by `week_number` IS NULL OR `week_number` = $weekNumber
- Return array of `['day' => int, 'start' => time, 'end' => time]`

### Task 3.4 — Implement Vector 3: Venue occupied slots
- Query `time_slots` WHERE `venue_id` = $venueId AND `status` = 'occupied'
- If `$weekNumber` provided, filter by matching class_session's week_number
- Return array of `['day' => int, 'start' => time, 'end' => time]`

### Task 3.5 — Implement Vector 4: Venue constraints
- Query `venues` WHERE `capacity` >= cohort_student_count AND `allowed_session_types` CONTAINS $sessionType
- If `$venueId` provided, filter to that specific venue
- Return eligible venue IDs

### Task 3.6 — Implement intersection logic
- For each (day, time, venue) in the venue space:
  - Check lecturer is FREE (not in Vector 1)
  - Check ALL target cohorts are FREE (not in Vector 2)
  - Check venue is FREE (not in Vector 3)
  - Check venue meets constraints (in Vector 4)
- Return matching slots as `['day' => 0, 'start' => '09:00', 'end' => '11:00', 'venue_id' => 12, 'venue_code' => 'B110']`

### Task 3.7 — Handle edge cases
- No common slot → return empty array (never crash)
- Single cohort → 3 vectors (lecturer × 1 cohort × room)
- All-day occupancy → empty result
- Session-type venue filtering (MPU-3133 → exclude labs)
- Duration-aware windows: only return windows ≥ requested duration

### Task 3.8 — Write unit tests
**File:** `tests/Unit/MatrixIntersectionEngineTest.php`
- Test: single cohort, lecturer free, venue free → returns slot
- Test: lecturer occupied → slot excluded
- Test: cohort occupied → slot excluded
- Test: venue occupied → slot excluded
- Test: session-type filtering (lab excluded for L sessions)
- Test: no common slot → empty array
- Test: single cohort (3-vector intersection)
- Test: duration filtering (short window excluded)

### Task 3.9 — Write integration test
**File:** `tests/Feature/MatrixIntersectionEngineTest.php`
- Seed database with timetable data
- Run engine for known lecturer + cohort
- Assert correct slots returned
- Benchmark: assert < 500ms

### Task 3.10 — Run tests
- `./vendor/bin/phpunit --filter MatrixIntersectionEngine`
- Fix any failures

### Task 3.11 — Run lint
- `./vendor/bin/pint`

### Task 3.12 — Commit + push
- `git add -A`
- `git commit -m "feat(engine): add MatrixIntersectionEngine with 4-vector set intersection"`
- `git push origin fedora-jing`

---

## Phase 4: OCCValidator

### Task 4.1 — Create service class skeleton
**File:** `app/Services/OCCValidator.php`
```php
class OCCValidator
{
    public function validateAndReserve(
        int $timeSlotId,
        int $userId,
        int $replacementRequestId,
    ): OCCResult {}
}
```

### Task 4.2 — Create `OCCResult` value object
**File:** `app/Services/OCCResult.php`
```php
class OCCResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $conflictReason = null,
        public readonly ?TimeSlot $timeSlot = null,
    ) {}

    public static function success(TimeSlot $slot): self {}
    public static function conflict(string $reason): self {}
}
```

### Task 4.3 — Implement optimistic locking algorithm
```
BEGIN TRANSACTION
  1. SELECT * FROM time_slots WHERE id = :slotId FOR UPDATE
  2. Check status: if NOT 'available' → ABORT (return conflict)
  3. Read current version number
  4. UPDATE time_slots SET status = 'pending', version = version + 1
     WHERE id = :slotId AND version = :expectedVersion
  5. If affected_rows = 0 → CONFLICT (version mismatch)
  6. If affected_rows = 1 → SUCCESS
  7. Log outcome in audit_logs
COMMIT
```

### Task 4.4 — Implement slot state transitions
| Current status | Action | New status |
|---|---|---|
| available | lecturer submits | pending |
| pending (self) | lecturer cancels | available |
| pending (any) | PL approves | occupied |
| pending (any) | PL rejects | available |
| occupied | — (permanent) | — |

### Task 4.5 — Implement audit logging
- On success: log action='submitted', occ_validation_result='success'
- On conflict: log action='submitted', occ_validation_result='conflict'
- Write to `audit_logs` table inside the transaction

### Task 4.6 — Write unit tests
**File:** `tests/Unit/OCCValidatorTest.php`
- Test: available slot → success, status becomes 'pending', version increments
- Test: occupied slot → conflict
- Test: pending slot (another request) → conflict
- Test: version mismatch → conflict (simulated concurrent update)
- Test: audit log created on success
- Test: audit log created on conflict

### Task 4.7 — Write integration test
**File:** `tests/Feature/OCCValidatorTest.php`
- Seed time_slot with status='available'
- Run OCC validator
- Verify: status='pending', version=2, audit log exists
- Simulate concurrent: two requests for same slot → exactly one succeeds

### Task 4.8 — Run tests
- `./vendor/bin/phpunit --filter OCCValidator`
- Fix any failures

### Task 4.9 — Run lint
- `./vendor/bin/pint`

### Task 4.10 — Commit + push
- `git add -A`
- `git commit -m "feat(occ): add OCCValidator with optimistic locking and audit trail"`
- `git push origin fedora-jing`

---

## Phase 5: FCFS Approval Backend

### Task 5.1 — Create `ReplacementRequestService`
**File:** `app/Services/ReplacementRequestService.php`
```php
class ReplacementRequestService
{
    public function create(int $proposerId, int $timeSlotId): ReplacementRequest {}
    public function approve(int $requestId, int $approverId): ReplacementRequest {}
    public function reject(int $requestId, int $approverId, string $reason): ReplacementRequest {}
    public function cancel(int $requestId, int $userId): ReplacementRequest {}
}
```

### Task 5.2 — Implement `create()` method
- Call `OCCValidator::validateAndReserve()` internally
- If OCC fails → throw `SlotConflictException` with conflict details
- If OCC succeeds → create `ReplacementRequest` with status='pending', submitted_at=now()
- Return the created request

### Task 5.3 — Implement `approve()` method
- Verify request exists and status='pending'
- Verify approver is a PL (is_pl=true)
- Update request: status='approved', approver_id=$approverId, decided_at=now()
- Update time_slot: status='occupied'
- Log audit: action='approved', old_status='pending', new_status='approved'
- Return updated request

### Task 5.4 — Implement `reject()` method
- Verify request exists and status='pending'
- Verify approver is a PL
- Update request: status='rejected', approver_id=$approverId, decided_at=now(), rejection_reason=$reason
- Update time_slot: status='available', version=version+1
- Log audit: action='rejected', old_status='pending', new_status='rejected'
- Return updated request

### Task 5.5 — Implement `cancel()` method
- Verify request exists and status='pending'
- Verify canceller is the proposer (proposer_id=$userId)
- Update request: status='cancelled', decided_at=now()
- Update time_slot: status='available', version=version+1
- Log audit: action='cancelled', old_status='pending', new_status='cancelled'
- Return updated request

### Task 5.6 — Create `SlotConflictException`
**File:** `app/Exceptions/SlotConflictException.php`
- Custom exception with `$timeSlotId`, `$conflictReason` properties
- Controller catches this and shows toast to user

### Task 5.7 — Write service tests
**File:** `tests/Feature/ReplacementRequestServiceTest.php`
- Test: create → request created, time_slot='pending', audit logged
- Test: approve → request='approved', time_slot='occupied', audit logged
- Test: reject → request='rejected', time_slot='available', audit logged
- Test: cancel → request='cancelled', time_slot='available', audit logged
- Test: approve non-pending → exception
- Test: cancel not proposer → exception
- Test: approve not PL → exception

### Task 5.8 — Write FCFS queue test
**File:** `tests/Feature/FCFSQueueTest.php`
- Create 3 requests with different submitted_at times
- Query queue: verify ordered by submitted_at asc
- Approve first → verify removed from pending queue
- Verify remaining 2 still in queue

### Task 5.9 — Run tests
- `./vendor/bin/phpunit --filter ReplacementRequest`
- `./vendor/bin/phpunit --filter FCFS`
- Fix any failures

### Task 5.10 — Run lint
- `./vendor/bin/pint`

### Task 5.11 — Commit + push
- `git add -A`
- `git commit -m "feat(approval): add ReplacementRequestService with FCFS queue + audit trail"`
- `git push origin fedora-jing`

---

## Phase 6: Email Notifications

### Task 6.1 — Create `ReplacementRequestSubmitted` Mailable
**File:** `app/Mail/ReplacementRequestSubmitted.php`
- Recipient: Programme Leader (PL)
- Content: proposer name, module, time, venue, cohort(s)
- Queueable (implement `ShouldQueue`)

### Task 6.2 — Create `ReplacementRequestApproved` Mailable
**File:** `app/Mail/ReplacementRequestApproved.php`
- Recipient: Proposer (lecturer)
- Content: module, time, venue, approver name
- Queueable

### Task 6.3 — Create `ReplacementRequestRejected` Mailable
**File:** `app/Mail/ReplacementRequestRejected.php`
- Recipient: Proposer (lecturer)
- Content: module, time, venue, rejection reason
- Queueable

### Task 6.4 — Wire dispatch into `ReplacementRequestService`
- `create()`: dispatch `ReplacementRequestSubmitted` to PL
- `approve()`: dispatch `ReplacementRequestApproved` to proposer
- `reject()`: dispatch `ReplacementRequestRejected` to proposer
- Use `Mail::to()->queue()` (not `send()`) for background processing

### Task 6.5 — Configure queue
- Verify `QUEUE_CONNECTION=database` in `.env`
- Run `php artisan queue:table` + `php artisan migrate` (jobs table already exists)
- Document: `php artisan queue:work --queue=emails` to process

### Task 6.6 — Write mail tests
**File:** `tests/Feature/EmailNotificationTest.php`
- Test: create request → PL receives Submitted email
- Test: approve → proposer receives Approved email
- Test: reject → proposer receives Rejected email with reason
- Test: emails are queued (not sent immediately)

### Task 6.7 — Run tests
- `./vendor/bin/phpunit --filter EmailNotification`
- Fix any failures

### Task 6.8 — Run lint
- `./vendor/bin/pint`

### Task 6.9 — Commit + push
- `git add -A`
- `git commit -m "feat(email): add replacement request notification mailables + queue"`
- `git push origin fedora-jing`

---

## Summary

| Phase | Tasks | Estimated Time |
|---|---|---|
| 1. DB Schema | 19 subtasks | 2–3 hours |
| 2. Seed Data | 10 subtasks | 2–3 hours |
| 3. MatrixIntersectionEngine | 12 subtasks | 3–4 hours |
| 4. OCCValidator | 10 subtasks | 2–3 hours |
| 5. FCFS Approval | 11 subtasks | 2–3 hours |
| 6. Email Notifications | 9 subtasks | 1–2 hours |
| **Total** | **71 subtasks** | **12–18 hours** |

## Dependency Chain
```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6
   ↓          ↓          ↓          ↓          ↓          ↓
 migrations  seed data  engine    OCC      approval   email
 + models    + verify   + tests   + tests  + tests    + tests
```

## Key Conventions (from existing codebase)
- Migrations: anonymous class `return new class extends Migration`
- CHECK constraints: `DB::statement('ALTER TABLE ... ADD CONSTRAINT ... CHECK ...')`
- Models: `$fillable` array, `casts()` method, `BelongsTo` relationships
- pivot tables: composite primary key, no auto-increment `id`
- Tests: PHPUnit in `tests/Unit/` and `tests/Feature/`
- Branch: `fedora-jing` on `github.com/fjing03/CRS-fedora`
