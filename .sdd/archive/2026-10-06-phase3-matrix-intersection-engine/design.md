# Design — Phase 3: MatrixIntersectionEngine

> **Status:** Design for proposal.md (frozen, Batch 1). This design.md is **Batch 2** — pending review.

---

## 1. Technical Approach

Grid-driven deterministic set intersection (D2). The engine treats the materialized `time_slots` grid as its universe, computes two busy-vector sets from `class_sessions` (+ week-aware `class_exceptions`), applies venue/capacity constraints and holiday blocks, then groups surviving cells into contiguous runs ≥ requested duration.

All reads are indexed single-table queries (frozen phase1 design §2.6): `time_slots_venue_day_start_week_idx (venue_id, day_of_week, start_time, week_number)` + `time_slots_status_idx (status)`; `class_sessions` `[lecturer_id, day_of_week, start_time]` and `[venue_id, day_of_week, start_time]`; `session_cohorts` `cohort_id` index.

## 2. Class: `app/Services/MatrixIntersectionEngine.php`

```php
final class MatrixIntersectionEngine
{
    /**
     * @param  array<int, int>  $cohortIds
     * @return array<int, array{
     *   day: int,
     *   start_time: string,
     *   end_time: string,
     *   venue_id: int,
     *   venue_code: string,
     *   time_slot_ids: array<int, int>,
     *   run_start: string,
     *   run_end: string,
     * }>
     */
    public function findAvailableSlots(
        int $lecturerId,
        array $cohortIds,
        int $semesterId,
        int $weekNumber,
        string $sessionType = 'L',
        int $duration = 60,
        ?int $venueId = null,
    ): array;

    public function validateSlot(
        int $timeSlotId,
        int $lecturerId,
        array $cohortIds,
        string $sessionType,
        int $weekNumber,
    ): bool;
}
```

- Constructor-injected or static? **No dependencies** — plain `final` class with private query helpers; the app container resolves it in later phases. `sessionType` validated ∈ {L,T,P}; `weekNumber` ∈ [1,14]; `duration` ∈ multiples of 30 and `>= 30` — else `InvalidArgumentException`. Output times are strings in the seed format `H:i:s` (e.g. `09:00:00`).

## 3. Algorithm — `findAvailableSlots()`

### Step 1 — Busy vectors (lecturer + cohorts), week-aware (D3)

Lecturer busy ranges:
```
class_sessions WHERE semester_id = ? AND lecturer_id = ?
  AND id NOT IN (SELECT class_session_id FROM class_exceptions WHERE week_number = ?)
→ [day, start_time, end_time]
```

Cohort busy ranges (union across all target cohorts — an overlap among cohorts counts once):
```
class_sessions cs
JOIN session_cohorts sc ON sc.class_session_id = cs.id
WHERE cs.semester_id = ? AND sc.cohort_id IN (?, …)
  AND cs.id NOT IN (SELECT class_session_id FROM class_exceptions WHERE week_number = ?)
→ [day, start_time, end_time]   (GROUP BY cs.id to dedupe multi-cohort sessions)
```

### Step 2 — Eligible venues (Vector 4)

- Required headcount: `SELECT COUNT(*) FROM students WHERE cohort_id IN (?, …)` → **SUM** across target cohorts (multi-cohort sessions share one room).
- Query `venues` WHERE `allowed_session_types` contains `$sessionType` (CSV parse in PHP after fetching, or `LIKE` — PHP-side parse is deterministic) AND `capacity >= headcount`. If `$venueId` given → still apply both checks to that venue (D4 — a defaulted dropdown is a filter, not an override).

### Step 3 — Holiday block (D3)

`holidays WHERE semester_id = ? AND week_number = ?` → set of `day_of_week` values to fully exclude (no green cells on a global holiday).

### Step 4 — Base set (grid)

```
time_slots ts
JOIN venues v ON v.id = ts.venue_id
WHERE ts.semester_id = ? AND ts.week_number = ? AND ts.status = 'available'
  AND ts.venue_id IN (eligible venue ids)          -- Step 2
  AND ts.day_of_week NOT IN (holiday days)         -- Step 3
  [AND ts.venue_id = ?]                            -- single-venue mode (D4)
ORDER BY ts.day_of_week, ts.start_time, v.room_code
```

### Step 5 — Intersection + run grouping (D5)

For each base cell: drop if `(day, start)` overlaps the lecturer busy ranges **or** any cohort busy range (interval overlap test: `busy.start < cell.end AND busy.end > cell.start` — busy ranges are half-open `[start, end)`, and interval form holds regardless of boundary alignment).

Group survivors into maximal contiguous runs per (day, venue): two cells are contiguous iff consecutive `start_time` at 30-min increments and identical day + venue. Discard runs shorter than `$duration` (in minutes; a 60-min run = 2 cells).

Result per surviving run:
```
{day, start_time: run_start, end_time: run_start + duration, venue_id, venue_code,
 time_slot_ids: [cells of the run], run_start, run_end}
```
`end_time` = `run_start + $duration` (the requested window the caller will use); `run_end` = maximal run extent (a 3-hour run at 60-min duration still reports `run_end` 3h later).

### Step 6 — Ordering

Sort results by `day`, then `start_time`, then `venue_code`.

## 4. Algorithm — `validateSlot()` (D10)

```
1. $slot = TimeSlot::findOrFail($timeSlotId)          -- need semester/week/day/time/venue
2. guard: $slot->week_number === $weekNumber AND $slot->semester_id === semester of $lecturerId's context → else return false (evaluate against the slot's own week, never a mismatched one)
3. status must be 'available'
4. day must not be a holiday day for (semester, week)
5. lecturer busy ranges (Step 1 query, slot's week) must not overlap (day, start)
6. cohort busy ranges must not overlap (day, start)
7. venue: allowed_session_types contains $sessionType AND capacity >= headcount (Step 2)
→ bool
```
Reuses the same private vector helpers as `findAvailableSlots()`. Note: `validateSlot` reads the **start** cell only; the full-run guarantee is the caller's (dashboard) concern — FR 3.4 shows ✓/⚠ per pending request, and the slot was already run-validated at submission time (Phase 5 concern).

## 5. Models (D6) — match frozen phase1 schema exactly

| Model | Table | Fillable | Casts | Relationships |
|---|---|---|---|---|
| `Venue` | venues | room_code, capacity, room_type, allowed_session_types | — | `hasMany(ClassSession)`, `hasMany(TimeSlot)` |
| `Module` | modules | module_code, module_name, allowed_session_types | — | `hasMany(ClassSession)` |
| `ClassSession` | class_sessions | semester_id, module_id, lecturer_id, day_of_week, start_time, end_time, venue_id, session_type | start_time/end_time → none (raw string `H:i:s`, per Q-2), day_of_week → int | `belongsTo(Module)`, `belongsTo(User,'lecturer_id')`, `belongsTo(Venue)`, `belongsToMany(Cohort,'session_cohorts')` |
| `SessionCohort` | session_cohorts | — (pivot) | — | `belongsTo(ClassSession)`, `belongsTo(Cohort)` |
| `TimeSlot` | time_slots | semester_id, class_session_id, week_number, day_of_week, start_time, end_time, venue_id, status, version | week_number/day_of_week/version → int | `belongsTo(ClassSession)`, `belongsTo(Venue)` |

House pattern: `#[Fillable]` attribute (per `User.php`), PHPStan `@property` docblocks, `casts()` method, no `$guarded` reliance. Pivot `SessionCohort` has no fillable (direct DB inserts only — matches `session_cohorts` composite PK design).

## 6. Query Performance (NFR 1.1 ≤ 500ms)

- Base set: `status` index filters to ~36,764 available cells (38,640 total − ~1,876 occupied: 33 sessions × 4 cells/week × 14 weeks, one 6-cell session); venue IN-list + `time_slots_venue_day_start_week_idx` narrows to ≤ 20 cells/venue/day → few hundred rows worst-case.
- Busy vectors: ≤ 33 session rows total; 2 indexed lookups.
- All-venues mode = 18 eligible L/T venues → still bounded by per-venue index reads; no full-table scans.
- Headcount: `students` indexed by PK; cohort IN-list narrows immediately.
- Integration test asserts wall-clock `<500ms` (generous; widened if flaky) **and** known-scenario correctness (lecturer 4288 / DFT2, week 5, per brief §5) — D9.

## 7. Data Flows

1. **Arrangement page (Sprint 3)** → `findAvailableSlots(lecturerId, cohortIds, semesterId, weekNumber, sessionType, duration, venueId?)` → green cells + runs for grid render (FR 2.5 recalc passes `venueId` from dropdown).
2. **Engine → Phase 4/5**: `time_slot_ids` per run → OCC `FOR UPDATE` + version++ on the chosen start cell; `replacement_requests.replacement_time_slot_id` references it (open Q-1: multi-cell reservation semantics).
3. **PL dashboard (Phase 5, FR 3.4)** → `validateSlot(timeSlotId, lecturerId, cohortIds, sessionType, weekNumber)` → ✓/⚠ per pending request.
4. **Replacement-home conflicted list**: NOT this engine — sibling query (phase1 design §6), out of scope.

## 8. Dependencies

- Runtime: Eloquent models (created here), PostgreSQL 14+, PHP 8.3. No new packages.
- Test: existing PHPUnit + `RefreshDatabase` + factories (UserFactory exists; new factories for Venue/Module/ClassSession/TimeSlot — plain `HasFactory` model factories under `database/factories/`; `SessionCohort` needs no factory, it's a direct-insert pivot).

## 9. Files

| File | Action |
|------|--------|
| `app/Services/MatrixIntersectionEngine.php` | New |
| `app/Models/Venue.php`, `Module.php`, `ClassSession.php`, `SessionCohort.php`, `TimeSlot.php` | New |
| `database/factories/VenueFactory.php`, `ModuleFactory.php`, `ClassSessionFactory.php`, `TimeSlotFactory.php` | New (tests) |
| `tests/Unit/MatrixIntersectionEngineTest.php` | New |
| `tests/Feature/MatrixIntersectionEngineTest.php` | New |
| `page-changelogs/backend-automated-by-ai.md` | Append |

## 10. Open Questions (carried from brief)

- **Q-1:** Multi-cell reservation semantics for 2-hour replacements — Phase 4/5 decision; engine returns full `time_slot_ids` run so either path works.
- **Q-2:** Time cast format — `datetime:H:i` (renders `09:00`) vs raw string (`09:00:00`). Engine compares via string on the DB side; cast only affects PHP-side display. Decide at implementation; pick `string` (no cast) to keep DB-form exact, per seed format.
