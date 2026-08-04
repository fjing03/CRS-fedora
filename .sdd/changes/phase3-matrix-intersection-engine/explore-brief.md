# Explore Brief — Phase 3: MatrixIntersectionEngine

> **Status:** Explore complete 2026-08-04. User ran `/sdd-explore MatrixIntersectionEngine`. 10 decisions grilled & locked. No conflicts with frozen artifacts — this is a clean forward change (service + models + tests, no schema changes).
> **Location:** `/home/jinglinux/tarumt/CRS-fedora` (Fedora dev machine), branch `fedora-jing`.

---

## 1. What Was Explored

- `CodingMAIN.md` — FR 2.4–2.7 (venue dropdown, grid, green-slot click), FR 3.4 (pre-computed slot validity), FR 4.3–4.8 (4-vector intersection, ≤500ms, 3-vector single cohort, empty-result, MPU-3133 labs-excluded), NFR 1.1 (500ms), Sprint 1 roadmap (engine service + API/testable service)
- Frozen phase1 artifacts: `design.md` §2.6 (time_slots grid, indexes, partial unique), §6 (conflicted-classes query), specs/timetable (engine vectors), specs/venues (session-type rules)
- `BACKEND-TASKS.md` Phase 3 (skeleton signature, Vector 1–4, edge cases, unit + integration tests)
- **Actual schema reality** (verified against migrations + DB): `class_sessions` has **no `week_number`** (weekly template; variation via `class_exceptions`); `time_slots` rows are per (semester, venue, week, day, start) with `status`/`version`/`class_session_id`
- **Actual seed state**: 23 venues (16 tutorial `L,T`, 2 lecture_hall `L`, 4 lab `P`, 1 cisco_lab `P`), 36 modules (MPU-3133/MPU-3232 = `L,T`), 33 class_sessions, 37 session_cohorts, 38,640 time_slots, 14 cohorts, 14 lecturers
- **Domain models: DO NOT EXIST** — phase1 was migrations-only (task-file model tasks 1.8–1.15 never executed)

## 2. Key Grounding Facts

1. **MPU-3133/FR 4.8 is already satisfied by data**: labs are `P`-only; any L/T request filters them out via `venues.allowed_session_types`. No hardcoded MPU rule needed.
2. **Seeded occupied cells carry `class_session_id`** and are marked occupied for all 14 weeks; cancelled weeks' cells remain `occupied` in the grid (conservative — never offers the cancelled session's own room×time).
3. `time_slots` has **no `reserved` status** — `reserved` is derived (`pending` + different proposer). Engine only ever reads `status='available'` cells.
4. Capacity: multi-cohort sessions share one room → required capacity = **SUM of students** across all target cohorts (derived from `students` via `cohort_id`).
5. Conflicts come from exactly two sources (design §6): `class_exceptions` (per-session) and `holidays` (global per week×day).

## 3. Grilled Decisions (all 10 — the baseline)

| # | Decision | Locked answer |
|---|----------|---------------|
| D1 | Scope boundary | **Service + tests only** — no HTTP endpoint/controller (UI wiring deferred to Sprint 3) |
| D2 | Computation model | **Grid-driven**: base set = `time_slots` rows `WHERE semester_id=? AND week_number=? AND status='available'` (+ venue filter if given); busy vectors from `class_sessions` exclude overlapping cells |
| D3 | Week & conflict handling | Week-aware. `class_exceptions` for the week **subtracted** from lecturer+cohort busy vectors (cancelled sessions open the time); `holidays` **block the whole (week, day)** — no green cells on a public holiday |
| D4 | Venue scope | `venueId` **nullable**: given → single-venue grid; null → scan all eligible venues, results carry `venue_id`/`venue_code`. Vector 4 (capacity + allowed types) applies **always**, even to a provided venue |
| D5 | Output shape | **Duration-filtered windows**: maximal contiguous green runs per (day, venue), runs < `$duration` dropped; each result = `{day, start_time, end_time, venue_id, venue_code, time_slot_ids[], run_start, run_end}` |
| D6 | Domain models | **Create now**: `Venue`, `Module`, `ClassSession`, `SessionCohort` (pivot), `TimeSlot` — matching frozen schema. `ReplacementRequest`/`AuditLog`/`Holiday`/`ClassException`/`Semester` models deferred to Phase 4/5. `semester_id` passed as scalar |
| D7 | Session-type validation | **Trust the caller**: `$sessionType ∈ {L,T,P}` validated; venue filter = `allowed_session_types` contains type. No `module_id` param |
| D8 | week_number | **Required** `int` (1–14). No null "all weeks" sentinel |
| D9 | Test strategy | Both tiers: unit tests (compact factories, full logic coverage) + ONE integration test with the real `DatabaseSeeder` (38,640 rows) asserting correctness **and** `<500ms` (generous bound — widen if flaky, never delete) |
| D10 | Slot-validity method | **In scope**: `validateSlot(int $timeSlotId, int $lecturerId, array $cohortIds, string $sessionType, int $weekNumber): bool` — same vectors, one-cell predicate (FR 3.4 PL dashboard) |

## 4. Rejected Approaches (and why)

| Approach | Rejected because |
|---|---|
| HTTP endpoint/controller in this change | Sprint 3 wires UI; endpoint shape depends on UI decisions (FR 2.4 default venue); pure service is trivially testable; auth/RBAC belongs to integration change |
| Set-driven in-memory intersection (4 raw sets from class_sessions, ignoring the grid) | The 38,640-row materialized grid + `(venue_id, day_of_week, start_time, week_number)` index + `status` index exist exactly for single-table reads (design §2.6); re-implements in PHP what the seeder materialized; loses real `time_slot` ids needed by Phase 4/5 |
| Week-agnostic busy vectors (ignore exceptions/holidays) | Engine is invoked for a conflicted week; ignoring exceptions would falsely mark the cancelled class's time busy and miss freed time from other cancelled sessions (medical-leave case) |
| Permissive holiday handling (subtract holidays from vectors → all-green on holiday days) | A global holiday means nobody is on campus; suggesting green cells there is misleading UX |
| Raw-cell output (consumer does window logic) | Duration-awareness is an explicit requirement; leaking it into every consumer duplicates logic (UI grid + Phase 5 dashboard) |
| Query-builder-only engine (no models) | Breaks house convention (all tables have models); tests get ugly; Phase 4/5 need `TimeSlot` model anyway — debt just moves |
| Module-side type validation (module_id param) | Session type is intrinsic to the class_session the caller acts on; FR 4.8 already handled by venue-side filter with seeded data |
| Nullable week_number sentinel | No consumer asks "all weeks"; undefined-code path |
| `validateSlot()` deferred to Phase 5 | Same three vectors, ~20 lines; FR 3.4 is explicit; UI mock already promises the field; deferring makes Phase 5 rebuild it |
| Hard `<500ms` assertion in CI | Timing asserts are flaky; kept generous and only in the integration test |

## 5. Final Solution — Public API

### `app/Services/MatrixIntersectionEngine.php`

```php
final class MatrixIntersectionEngine
{
    /**
     * @param  int  $lecturerId       users.id (role=lecturer)
     * @param  array<int, int>  $cohortIds  target cohorts (1+)
     * @param  int  $semesterId
     * @param  int  $weekNumber       1–14, required
     * @param  string  $sessionType   L|T|P (validated), caller-resolved
     * @param  int  $duration         minutes, multiple of 30, default 60
     * @param  int|null  $venueId     null = all eligible venues
     * @return array<int, array{day:int, start_time:string, end_time:string,
     *   venue_id:int, venue_code:string, time_slot_ids:array<int,int>,
     *   run_start:string, run_end:string}>
     */
    public function findAvailableSlots(
        int $lecturerId,
        array $cohortIds,
        int $semesterId,
        int $weekNumber,
        string $sessionType = 'L',
        int $duration = 60,
        ?int $venueId = null,
    ): array {}

    public function validateSlot(
        int $timeSlotId,
        int $lecturerId,
        array $cohortIds,
        string $sessionType,
        int $weekNumber,
    ): bool;
}
```

### Algorithm (findAvailableSlots)

1. **Vectors 1+2 (busy ranges)** — one query each, from `class_sessions` (weekly templates):
   - Lecturer: `class_sessions WHERE semester_id=? AND lecturer_id=?` minus `class_exceptions(week_number=W)` → list of (day, start, end) busy ranges
   - Cohorts: `class_sessions JOIN session_cohorts WHERE cohort_id IN (…)` minus exceptions → union of busy ranges
2. **Vector 3+4 (base set)** — `time_slots WHERE semester_id=? AND week_number=? AND status='available'` (+ `venue_id=?` if given); join `venues` for `allowed_session_types`/capacity (Vector 4: type contains `$sessionType`; `capacity >= SUM(students of target cohorts)` — derived via `students.cohort_id IN (…)`)
3. **Holiday block**: fetch `holidays` for (semester, week) → drop all (day) cells
4. **Intersection**: drop base cells overlapping any busy range (lecturer or any cohort); group survivors into contiguous runs per (day, venue); keep runs ≥ `$duration`
5. Output: maximal runs ≥ duration, each with its constituent `time_slot_ids`

### `validateSlot()`

Fetch the one cell → status must be `available` (or occupied by nothing) → not on a holiday day → lecturer free at (day,start) → all cohorts free → venue type/capacity valid. Reuses the same three vector queries.

### Models (new, matching frozen schema)

| Model | Table | Relationships |
|---|---|---|
| `Venue` | venues | `hasMany(ClassSession)`, `hasMany(TimeSlot)` |
| `Module` | modules | `hasMany(ClassSession)` |
| `ClassSession` | class_sessions | `belongsTo(Module)`, `belongsTo(User,'lecturer_id')`, `belongsTo(Venue)`, `belongsToMany(Cohort,'session_cohorts')` |
| `SessionCohort` | session_cohorts | `belongsTo(ClassSession)`, `belongsTo(Cohort)` |
| `TimeSlot` | time_slots | `belongsTo(ClassSession)`, `belongsTo(Venue)` |

PHPStan `@property` annotations per house pattern (User.php). Casts: `start_time/end_time => 'datetime'` (or string, matching seed format `H:i:s`), `version => integer`.

### Tests

- `tests/Unit/MatrixIntersectionEngineTest.php` (factories, `RefreshDatabase`): 4-vector happy path; lecturer-occupied excluded; cohort-occupied excluded; venue-occupied excluded; type filter (lab excluded for L); capacity (multi-cohort sum, small room excluded); no common slot → `[]`; duration filter (short run dropped); exception subtraction (D3); holiday-day fully blocked (D3); single-cohort 3-vector; all-venues mode (D4); `validateSlot()` true/false cases
- `tests/Feature/MatrixIntersectionEngineTest.php`: real `DatabaseSeeder`; known scenario (e.g. lecturer 4288 / DFT2 session, week 5); assert returned windows; assert `<500ms` (generous)

## 6. Key Cross-Module Data Flows

1. **Arrangement page → engine**: (lecturer, cohorts from conflicted session's pivot, semester, week, session_type, duration = session length, venue = dropdown default original venue) → green cells for the grid (FR 2.5 recalc on venue change).
2. **Engine → Phase 4/5**: results carry `time_slot_ids` → OCC submit locks the chosen cell (`time_slots` `FOR UPDATE` + version++); `replacement_requests.replacement_time_slot_id` references it.
3. **PL dashboard (FR 3.4)**: pending request → `validateSlot(slot_id, proposer, cohorts, type, week)` → "✓ Valid / ⚠ Conflict" display (wired in Phase 5; engine method available now).
4. **Conflicted-classes (replacement-home)**: sibling query (design §6), NOT part of this engine.

## 7. Known Open Questions

- **Q-1:** A 2-hour replacement spans 4 cells, but `replacement_requests.replacement_time_slot_id` is a single FK. Phase 4/5 must decide: reserve all 4 cells (mark pending) or only the start cell. Engine returns the full run's `time_slot_ids` so either is possible.
- **Q-2:** `start_time` cast format — seed data uses `H:i:s` (`09:00:00`); cast to `datetime` vs keep string. Decide in design (string comparisons in queries work either way).
- **Q-3:** `duration` default 60 — the caller (arrangement page) will pass the conflicted session's actual length (60/120 min). Engine assumes multiples of 30 (grid granularity); invalid values → `InvalidArgumentException`.

## 8. Transition

Scope is clear. Next: run `/sdd-propose phase3-matrix-intersection-engine` to write proposal + design + specs, then review.
