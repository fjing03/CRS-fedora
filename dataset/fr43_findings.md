# FR 4.3 Findings Report — Multi-Entity Matrix Intersection Engine

> Date: 2026-10-06 · Branch: `fedora-backend` · Engine: `app/Services/MatrixIntersectionEngine.php` (408 lines, `final`)
> Built in SDD phase 3 (archived `.sdd/archive/2026-10-06-phase3-matrix-intersection-engine/`), planner input to phase 4 OCC.

## What FR 4.3 requires

CodingMAIN §7, FR 4.3: "The system shall compute a four-way set intersection of lecturer availability, cohort free schedules (supporting multiple cohorts), room vacancy, and capacity-aware venue filtering."

## Public API

| Method | Returns | Feeds |
|--------|---------|-------|
| `findAvailableSlots(lecturerId, cohortIds[], semesterId, weekNumber, sessionType = 'L', duration = 60, venueId = null)` | maximal green runs, each `{day, start_time, end_time, venue_id, venue_code, time_slot_ids[], run_start, run_end}`, sorted by day → start → venue_code | Replacement-arrangement green grid (FR 2.4–2.7) |
| `validateSlot(timeSlotId, lecturerId, cohortIds, sessionType, weekNumber)` | `bool`; throws on unknown slot | PL dashboard pre-computed "✓ Valid / ⚠ Conflict" (FR 3.4), OCC phase 4 |

## How the intersection works (locked decisions D2–D5, D7, D8)

1. **Base grid** = `time_slots` rows with `status = 'available'` for the requested `(semester, week)` and eligible venues. It respects the slot state machine (FR 4.11): the engine only reads `available`; `pending`/`occupied` cells are naturally excluded.
2. **Vector 1 — lecturer availability:** `class_sessions` where `lecturer_id` matches in that semester/week. `class_exceptions` for the same week subtract their windows (week-aware, S-9).
3. **Vector 2 — cohort free schedules:** sessions joined through `session_cohorts`; multiple cohorts union their busy ranges (overlap counts once via `groupBy(class_sessions.id)`). Same week-aware exception subtraction.
4. **Vector 3 — room vacancy:** covered by the base grid plus the two busy vectors per `(day, venue)` cell.
5. **Vector 4 — capacity + type:** headcount = Σ student_count over the target cohorts, with a live student-table fallback; a venue qualifies when `allowed_session_types` contains the requested type (labs are excluded for L/T — FR 4.8) and capacity suffices.
   `venueId` non-null restricts the grid to that venue (single-venue mode, FR 2.5); `null` = all venues (FR 2.4 default).
6. **Run assembly:** surviving cells are grouped by `(day, venue)`, chained into maximal contiguous 30-minute runs, and a run is kept if `cells × 30 >= duration`.
7. **Holidays** block the whole day (S-10), regardless of slot status.

Input guards: `sessionType ∈ {L,T,P}`, `weekNumber 1–14`, `duration` a 30-min multiple ≥ 30, `cohortIds` non-empty (otherwise `InvalidArgumentException`, S-16).

## Requirements trace

| FR/NFR | Requirement | Satisfied by | Test |
|--------|-------------|--------------|------|
| FR 4.3 | 4-way set intersection | grid-driven base ∩ lecturer ∩ cohorts ∩ venue-eligibility | `test_s1_happy_path_4_vector` |
| FR 4.6 | 3-vector for single cohort | same code path with one cohort id | `test_s4_single_cohort_ignores_other_cohort_sessions` |
| FR 4.7 | Empty result when fully occupied | returns `[]`, never crashes | `test_s7_no_common_slot_returns_empty` |
| FR 4.8 | Session-type venue filtering | `allowed_session_types` filter | `test_s5_lab_excluded_for_lecture_type`, `test_s5_lab_included_for_practical_type` |
| FR 4.4 / NFR 1.1 | results ≤ 500 ms | indexed reads; asserted bound | `test_s17_integration_real_seed_known_scenario` (`assertLessThan(500)` on the engine call) |
| FR 3.4 | pre-computed slot validity | `validateSlot()` | `test_s13_validate_slot_true`, `test_s14_validate_slot_false_each_vector`, `test_s15_validate_slot_week_guard` |
| FR 2.4 / 2.5 | default + venue-recalc | all-venues vs single-venue param | `test_s11_all_venues_mode_ordering`, `test_single_venue_mode_restricts_grid` |
| FR 4.11 | slot state machine | only `status='available'` cells | `test_s4b_venue_occupied_excluded`, S-17 cell-status check |

## Test evidence (fresh run 2026-10-06)

```
vendor/bin/phpunit tests/Unit/MatrixIntersectionEngineTest.php tests/Feature/MatrixIntersectionEngineTest.php
→ OK (37 tests, 238 assertions) in ~7.8 s wall
```

- **Unit (36 tests, factory fixtures, isolated `class_replacement_testing` DB):** vector matrix S-1…S-16 — happy path, both busy vectors, exception/holiday subtraction, lab exclusion both directions, capacity summing, duration filter, empty result, ordering, runs never bleed across venues sharing a start time, and negative cases (`validateSlot` false for each vector, missing slot throws, bad week 15, bad duration 45/0, empty cohorts, `sessionType` ≠ L/T/P).
- **Feature (real `DatabaseSeeder`, 38,640 `time_slots`):** S-17 known scenario — lecturer `4288` × `DFT2(S1)G1`, week 5, duration 60:
  - pinned window asserted: Mon 10:00–11:00 @ B002 (not covered on the holiday day, day 2)
  - every returned cell must still be `status='available'`
  - engine call wall time < 500 ms (NFR 1.1)
  - the pinned slot also passes `validateSlot()` (S-13), and the holiday-day slot fails it
- Suites run against the dedicated `class_replacement_testing` DB (`phpunit.xml`) — the live demo DB is untouched, so re-running tests does not disturb seeded data.

## Fit with the 2026-10-06 constraint migrations

The engine chains cells by `start = previous.start + 30 min`. The new `time_slots_slot_duration_check` CHECK (`end_time = start_time + interval '30 minutes'`) now guarantees at the DB level exactly the invariant the chain logic assumes, so a manually inserted off-grid slot can no longer produce a false green run. `holidays_semester_id_week_number_day_of_week_unique` likewise guarantees the whole-day holiday subtraction reads consistent data.

## Known gaps / limitations

- **No HTTP layer yet** — the engine is service-level; UI wiring is the in-flight `wire-backend-into-refactored-ui` SDD change (Sprint 3).
- **Single cohort id vs the "one lecturer, one target" flow** — multi-cohort search is supported (`cohortIds[]`), but every caller still supplies the cohort list explicitly.
- `validateSlot` trusts the caller's `sessionType`/`weekNumber` arguments (D7: venue-side filtering only); it re-checks the slot's stored week internally.
- The 500 ms bound is measured on seeded demo scale (38,640 slots); real-term data volume is comparable, but the bound is re-asserted implicitly on every integration run.

## Reproduce

```bash
vendor/bin/phpunit tests/Unit/MatrixIntersectionEngineTest.php tests/Feature/MatrixIntersectionEngineTest.php
```
