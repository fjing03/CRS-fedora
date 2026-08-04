# Design: Phase 1 — Database Schema

> **Frozen baseline:** proposal.md (Round 3, 2026-08-04). This design.md is the technical implementation contract for 10 PostgreSQL migration files.

---

## 1. Technical Approach

### 1.1 Migration Framework

- All 10 migrations use **anonymous migration classes** (Laravel 12 default): `return new class extends Migration`
- Filenames: `2026_08_03_000001_create_semesters_table.php` through `…000010_create_audit_logs_table.php`
- Timestamp prefix orders by dependency: semesters (no deps) → venues (no deps) → modules (no deps) → class_sessions (→ semesters, modules, venues, users) → session_cohorts (→ class_sessions, cohorts) → time_slots (→ semesters, class_sessions, venues) → holidays (→ semesters) → class_exceptions (→ class_sessions) → replacement_requests (→ semesters, users, class_sessions, time_slots) → audit_logs (→ users, replacement_requests, time_slots)
- Rollback: `php artisan migrate:rollback --step=10` drops in reverse order

### 1.2 PostgreSQL-Specific Syntax

- CHECK constraints: raw `DB::statement("ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)")` — consistent with existing 11 migrations (frozen `users` and `cohorts` use this pattern)
- Partial unique indexes: raw `DB::statement("CREATE UNIQUE INDEX ... WHERE ...")`
- `unsignedTinyInteger` → PG `smallint` (Laravel maps this); `unsignedSmallInteger` → PG `integer`; `unsignedInteger` → PG `bigint`
- `time` columns: Laravel `$table->time()` maps to PG `time`
- `date` columns: Laravel `$table->date()` maps to PG `date`
- `json` column: Laravel `$table->json()` maps to PG `jsonb` (Laravel 12 default on PG)

### 1.3 Existing Table References (FROZEN — no ALTERs)

All new tables reference existing tables by FK only:
- `class_sessions.lecturer_id` → `users.id` (role=lecturer enforced at app layer)
- `session_cohorts.cohort_id` → `cohorts.id`
- No new columns on `users`, `students`, `lecturers`, `cohorts`, `faculties`, `departments`, `programmes`

---

## 2. Schema Design — Per Table

### 2.1 `semesters`

```php
Schema::create('semesters', function (Blueprint $table) {
    $table->id();
    $table->string('semester_code', 20)->unique();   // '202605'
    $table->string('label');                          // '202605 Semester'
    $table->date('start_date');                       // '2026-08-31' (Week-1 Monday)
    $table->date('end_date');                         // '2026-12-06'
    $table->unsignedSmallInteger('week_count');       // 14
    $table->timestamps();
});
```
- Indexes: `semester_code` (unique)
- CHECK: none needed (week_count validated at app layer; range 1–14 is implicit)

### 2.2 `venues`

```php
Schema::create('venues', function (Blueprint $table) {
    $table->id();
    $table->string('room_code', 20)->unique();       // 'B002', 'B110'
    $table->unsignedSmallInteger('capacity');         // 35, 80, 28, 32
    $table->string('room_type', 30);                  // CHECK at DB level
    $table->string('allowed_session_types', 10);      // 'L,T' or 'P'
    $table->timestamps();
});

DB::statement("ALTER TABLE venues ADD CONSTRAINT venues_room_type_check
    CHECK (room_type IN ('tutorial', 'lecture_hall', 'lab', 'cisco_lab'))");
```
- Indexes: `room_type`, `capacity` (for venue-type and capacity filtering)
- 23 seeded rows (block B only)

### 2.3 `modules`

```php
Schema::create('modules', function (Blueprint $table) {
    $table->id();
    $table->string('module_code', 30)->unique();      // 'BMIT5555', 'MPU-3133'
    $table->string('module_name');                     // 'Software Engineering'
    $table->string('allowed_session_types', 10);       // 'L' or 'L,T'
    $table->timestamps();
});
```
- No CHECK needed (session types validated at app layer)

### 2.4 `class_sessions`

```php
Schema::create('class_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('semester_id')->constrained('semesters');
    $table->foreignId('module_id')->constrained('modules');
    $table->foreignId('lecturer_id')->constrained('users');
    $table->unsignedTinyInteger('day_of_week');        // 0–5 CHECK
    $table->time('start_time');                        // '09:00'
    $table->time('end_time');                          // '11:00'
    $table->foreignId('venue_id')->constrained('venues');
    $table->string('session_type', 5);                 // CHECK L/T/P
    $table->timestamps();

    $table->index(['lecturer_id', 'day_of_week', 'start_time']);
    $table->index(['venue_id', 'day_of_week', 'start_time']);
});

DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_day_of_week_check
    CHECK (day_of_week BETWEEN 0 AND 5)");
DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_session_type_check
    CHECK (session_type IN ('L', 'T', 'P'))");
```
- NO `week_number` column — runs all weeks; per-week variation via `class_exceptions`

### 2.5 `session_cohorts`

```php
Schema::create('session_cohorts', function (Blueprint $table) {
    $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
    $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
    $table->primary(['class_session_id', 'cohort_id']);
});
```
- Index: `cohort_id` (engine cohort-availability vector)
- cascadeOnDelete on both FKs — delete session → remove all cohort links

### 2.6 `time_slots`

```php
Schema::create('time_slots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('semester_id')->constrained('semesters');
    $table->foreignId('class_session_id')->nullable()->constrained('class_sessions');
    $table->unsignedTinyInteger('week_number');        // 1–14 CHECK, NOT NULL
    $table->unsignedTinyInteger('day_of_week');        // 0–5 CHECK
    $table->time('start_time');
    $table->time('end_time');
    $table->foreignId('venue_id')->constrained('venues');
    $table->string('status', 20);                      // CHECK available/pending/occupied
    $table->unsignedInteger('version')->default(1);    // OCC counter
    $table->timestamps();
});

DB::statement("ALTER TABLE time_slots ADD CONSTRAINT time_slots_week_number_check
    CHECK (week_number BETWEEN 1 AND 14)");
DB::statement("ALTER TABLE time_slots ADD CONSTRAINT time_slots_day_of_week_check
    CHECK (day_of_week BETWEEN 0 AND 5)");
DB::statement("ALTER TABLE time_slots ADD CONSTRAINT time_slots_status_check
    CHECK (status IN ('available', 'pending', 'occupied'))");
DB::statement("ALTER TABLE time_slots ADD CONSTRAINT time_slots_start_time_check
    CHECK (extract(minute FROM start_time) IN (0, 30))");

// Composite index for grid queries
DB::statement("CREATE INDEX time_slots_venue_week_idx
    ON time_slots (venue_id, day_of_week, start_time, week_number)");

// Status index for filtering
DB::statement("CREATE INDEX time_slots_status_idx ON time_slots (status)");

// Partial unique index: DB-level no-double-booking guard
DB::statement("CREATE UNIQUE INDEX time_slots_no_double_book_idx
    ON time_slots (venue_id, day_of_week, start_time, week_number)
    WHERE status IN ('pending', 'occupied')");
```

- `class_session_id` nullable: occupied cells reference the occupying session; available cells have NULL
- `version` default 1: incremented on every OCC transition (FR 4.9)
- `reserved` status is NEVER stored — derived at query time by comparing `proposer_id` with current user
- Grid volume: 22 cells × 5 days × 14 weeks × 23 venues ≈ **35,420 rows**
- Partial unique index: prevents two rows for the same (venue, day, start, week) both being pending/occupied — DB-level guard against concurrent double-booking

### 2.7 `holidays`

```php
Schema::create('holidays', function (Blueprint $table) {
    $table->id();
    $table->foreignId('semester_id')->constrained('semesters');
    $table->unsignedSmallInteger('week_number');
    $table->unsignedTinyInteger('day_of_week');
    $table->string('label');                           // 'Public Holiday'
    $table->timestamps();

    $table->index(['semester_id', 'week_number', 'day_of_week']);
});

DB::statement("ALTER TABLE holidays ADD CONSTRAINT holidays_day_of_week_check
    CHECK (day_of_week BETWEEN 0 AND 5)");
```
- Index: `[semester_id, week_number, day_of_week]`
- Global holidays — affect ALL sessions on that day/week

### 2.8 `class_exceptions`

```php
Schema::create('class_exceptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
    $table->unsignedTinyInteger('week_number');
    $table->string('reason', 30);                      // CHECK
    $table->timestamps();

    $table->unique(['class_session_id', 'week_number']);
});

DB::statement("ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_week_number_check
    CHECK (week_number BETWEEN 1 AND 14)");
DB::statement("ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_reason_check
    CHECK (reason IN ('public_holiday', 'annual_leave', 'medical_leave', 'official_event', 'emergency_leave'))");
```
- Unique on `(class_session_id, week_number)` — one exception per session per week
- cascadeOnDelete: delete session → remove exceptions

### 2.9 `replacement_requests`

```php
Schema::create('replacement_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('semester_id')->constrained('semesters');
    $table->foreignId('proposer_id')->constrained('users');
    $table->foreignId('class_session_id')->constrained('class_sessions');
    $table->unsignedTinyInteger('week_number');
    $table->foreignId('replacement_time_slot_id')->constrained('time_slots');
    $table->foreignId('approver_id')->nullable()->constrained('users');
    $table->string('status', 20);                      // CHECK 5 states
    $table->text('rejection_reason')->nullable();
    $table->text('remarks')->nullable();
    $table->timestamp('submitted_at');
    $table->timestamp('decided_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'submitted_at']);
});

DB::statement("ALTER TABLE replacement_requests ADD CONSTRAINT replacement_requests_week_number_check
    CHECK (week_number BETWEEN 1 AND 14)");
DB::statement("ALTER TABLE replacement_requests ADD CONSTRAINT replacement_requests_status_check
    CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled', 'completed'))");
```
- Index: `[status, submitted_at]` (FCFS queue ordering, FR 3.2)
- `submitted_at` = `created_at` (no draft state — request is submitted the moment it's created)
- `replacement_time_slot_id` NOT NULL — a request must pick a replacement slot to submit

### 2.10 `audit_logs`

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->string('action', 30);                      // CHECK
    $table->foreignId('replacement_request_id')->nullable()->constrained('replacement_requests');
    $table->foreignId('time_slot_id')->nullable()->constrained('time_slots');
    $table->string('old_status', 20)->nullable();
    $table->string('new_status', 20)->nullable();
    $table->string('occ_validation_result', 20)->nullable();  // CHECK
    $table->json('details')->nullable();
    $table->timestamps();

    $table->index('replacement_request_id');
    $table->index('created_at');
});

DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
    CHECK (action IN ('submitted', 'approved', 'rejected', 'cancelled', 'completed', 'occ_conflict'))");
DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_occ_validation_result_check
    CHECK (occ_validation_result IN ('success', 'conflict'))");
```
- Indexes: `replacement_request_id`, `created_at`
- App-layer immutability: no UPDATE/DELETE on rows (enforced by service, not DB trigger)

---

## 3. Slot State Machine (FR 4.0–4.11)

```
                    ┌───────────────────────────────────────────────┐
                    │          OCC VERSION CHECK                     │
                    │  SELECT ... FOR UPDATE → version check →       │
                    │  UPDATE SET version=version+1 WHERE version=?  │
                    └───────────┬───────────────────┬───────────────┘
                                │                   │
                 ┌──────────────▼──┐        ┌───────▼──────────────┐
                 │   SUBMIT        │        │  CONFLICT             │
                 │   (version++)   │        │  (affected_rows = 0)  │
                 └──────────┬──────┘        └───────┬──────────────┘
                            │                       │
               ┌────────────▼────────────┐          │
               │      status = 'pending' │          │  → audit_logs
               │      (version: N+1)     │          │    (action='occ_conflict',
               └──────────┬──────────────┘          │     occ_validation_result='conflict')
                          │                         │
           ┌──────────────┼──────────────┐          │
           ▼              ▼              ▼          │
    ┌──────────┐  ┌──────────┐  ┌──────────┐       │
    │ APPROVE  │  │ REJECT   │  │ CANCEL   │       │
    │ version++│  │ version++│  │ version++│       │
    └────┬─────┘  └────┬─────┘  └────┬─────┘       │
         │              │              │             │
    ┌────▼─────┐  ┌────▼─────┐  ┌────▼─────┐       │
    │ occupied │  │ available│  │ available│       │
    │ (slot    │  │ (slot    │  │ (slot    │       │
    │  locked) │  │  freed)  │  │  freed)  │       │
    └──────────┘  └──────────┘  └──────────┘       │
                                                    │
    ┌────────────────────────────────────────────────┘
    │
    │  No stored 'reserved' status
    │  reserved = pending + different proposer (derived at query time)
    │
    └─→ Grid display:
        - available cells → green
        - pending (self)  → yellow
        - pending (other) → grey (derived, labeled "reserved")
        - occupied        → red
```

**State transitions:**

| Current | Action | New | Version | Notes |
|---------|--------|-----|---------|-------|
| `available` | lecturer submits | `pending` | version+1 | OCC check; conflict → audit log |
| `pending` | PL approves | `occupied` | version+1 | Slot locked permanently |
| `pending` | PL rejects | `available` | version+1 | Slot freed |
| `pending` | lecturer cancels | `available` | version+1 | Slot freed |
| `occupied` | — | (permanent) | — | No further transitions |

---

## 4. Date Derivation Formula

```
date = semesters.start_date + (week_number − 1) × 7 + day_of_week
```

- `semesters.start_date` = Week-1 Monday (e.g. `2026-08-31`)
- `week_number` ∈ 1–14
- `day_of_week` ∈ 0–5 (Mon–Sat)
- Example: Week 3, Wednesday (day_of_week=2) = `2026-08-31 + (3-1)×7 + 2 = 2026-09-14`

This formula is used by:
- **MyTimetable** page (grid dates)
- **CohortTimetable** page (per-week view)
- **Replacement-home** page (conflicted class dates)
- **Arrangement** page (slot selection grid)
- **Request-history** page (classDate display)

---

## 5. OCC Transaction Pattern (FR 4.9–4.12)

```php
// Pseudocode — App\Services\OCCValidator::validateAndReserve()
DB::beginTransaction();

$slot = DB::select('SELECT * FROM time_slots WHERE id = ? FOR UPDATE', [$slotId]);

if ($slot->status !== 'available') {
    // Someone else got it
    AuditLog::create([
        'user_id' => $userId,
        'action' => 'occ_conflict',
        'time_slot_id' => $slotId,
        'occ_validation_result' => 'conflict',
        'details' => ['reason' => 'slot_not_available', 'current_status' => $slot->status],
    ]);
    DB::rollBack();
    return OCCResult::conflict($slot);
}

$expectedVersion = $slot->version;
$affected = DB::update(
    'UPDATE time_slots SET status = ?, version = version + 1, updated_at = NOW()
     WHERE id = ? AND version = ?',
    ['pending', $slotId, $expectedVersion]
);

if ($affected === 0) {
    // Version mismatch — concurrent OCC conflict
    AuditLog::create([
        'user_id' => $userId,
        'action' => 'occ_conflict',
        'time_slot_id' => $slotId,
        'occ_validation_result' => 'conflict',
        'details' => ['reason' => 'version_mismatch', 'expected_version' => $expectedVersion],
    ]);
    DB::rollBack();
    return OCCResult::conflict($slot);
}

// Success
AuditLog::create([
    'user_id' => $userId,
    'action' => 'submitted',
    'replacement_request_id' => $requestId,
    'time_slot_id' => $slotId,
    'occ_validation_result' => 'success',
]);
DB::commit();
```

---

## 6. Conflicted Classes Query (FR 3.1)

```sql
-- Replacement-home: find all sessions with conflicts for a given week
SELECT
    cs.id AS class_session_id,
    cs.day_of_week,
    cs.start_time,
    cs.end_time,
    cs.session_type,
    m.module_code,
    m.module_name,
    v.room_code,
    ce.reason AS conflict_reason
FROM class_sessions cs
JOIN modules m ON cs.module_id = m.id
JOIN venues v ON cs.venue_id = v.id
JOIN session_cohorts sc ON cs.id = sc.class_session_id
LEFT JOIN class_exceptions ce ON cs.id = ce.class_session_id AND ce.week_number = :week
LEFT JOIN holidays h ON h.semester_id = cs.semester_id
    AND h.week_number = :week
    AND h.day_of_week = cs.day_of_week
WHERE cs.semester_id = :semesterId
  AND (ce.id IS NOT NULL OR h.id IS NOT NULL)
GROUP BY cs.id, COALESCE(ce.reason, 'public_holiday'), m.module_code, m.module_name, v.room_code;
```

- `class_exceptions` rows: lecturer-specific (annual_leave, medical_leave, etc.)
- `holidays` rows: global (all sessions on that day/week)
- Either source creates a conflict for the replacement-home page

---

## 7. Grid Population Strategy

### Pre-seed approach (D3)

- **Grid volume:** 22 cells/day × 5 days/week × 14 weeks × 23 venues ≈ **35,420 rows**
- **Initial status:** all `available`, `class_session_id = NULL`, `version = 1`
- **Occupied marking:** after `class_sessions` + `session_cohorts` are seeded, run an UPDATE that sets `status = 'occupied'` and `class_session_id` for each session's time range
- **30-min cell boundaries:** `start_time` at `:00` or `:30` only (enforced by CHECK in §2.6 `time_slots_start_time_check`)
- A 2-hour session (09:00–11:00) occupies 4 contiguous cells: 09:00, 09:30, 10:00, 10:30

### Cell boundary enforcement

```php
DB::statement("ALTER TABLE time_slots ADD CONSTRAINT time_slots_start_time_check
    CHECK (extract(minute FROM start_time) IN (0, 30))");
```

### Sunday handling

- Sunday cells (`day_of_week = 6`) are **NOT** rows in `time_slots`
- The UI hard-forces Sunday cells as `cell-occupied` (replacement-arrangement template line 1104)
- Sunday is excluded from scheduling; CHECK 0–5 on all tables

---

## 8. Dependency Graph

```
semesters
    │
    ├── venues
    ├── modules
    │
    ├── class_sessions ──→ users, modules, venues, semesters
    │       │
    │       ├── session_cohorts ──→ class_sessions, cohorts
    │       │
    │       └── class_exceptions ──→ class_sessions
    │
    ├── time_slots ──→ semesters, class_sessions, venues
    │
    ├── holidays ──→ semesters
    │
    └── replacement_requests ──→ semesters, users, class_sessions, time_slots
            │
            └── audit_logs ──→ users, replacement_requests, time_slots
```

---

## 9. Open Design Questions (defer to later phases)

| # | Question | Owner | Resolution |
|---|----------|-------|------------|
| Q-2 | Test DB switch: fold into this change or sibling? | User + orchestrator | User approved PG switch; decision deferred |
| Q-4 | `completed` semantics: set when? By whom? | Service layer (later phase) | Schema stores status; lifecycle deferred |

---

## 10. Files Modified

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
