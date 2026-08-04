# Spec — MatrixIntersectionEngine (capability)

> **Status:** Spec for design.md (frozen, Batch 2). This is **Batch 3** — pending review.
> **Capability:** The Multi-Entity Matrix Intersection Engine (CodingMAIN FR 4.3–4.8, NFR 1.1; Objective 1).
> **Component under test:** `app/Services/MatrixIntersectionEngine` + 5 models (D6).

---

## 1. Description

The engine answers one question, in two shapes:

- `findAvailableSlots(...)` → for a cancelled session (lecturer, target cohorts, semester, week, session type, duration, optional venue), which maximal contiguous green windows exist on the materialized `time_slots` grid — where "green" means: cell `status='available'`, not a holiday day, not overlapping the lecturer's busy ranges, not overlapping any target cohort's busy ranges, in a venue whose `allowed_session_types` contains the session type and whose capacity fits the summed cohort headcount, week-aware per `class_exceptions`.
- `validateSlot(...)` → the same predicate for a single cell (FR 3.4 dashboard ✓/⚠).

## 2. Requirements

| ID | Requirement | Source |
|----|-------------|--------|
| E-1 | Returns all maximal green runs of length ≥ `$duration` across eligible venues, each `{day, start_time, end_time, venue_id, venue_code, time_slot_ids, run_start, run_end}` | FR 4.3, D5 |
| E-2 | Lecturer's own sessions for the week are busy ranges (minus exceptions for that week) | FR 4.3, D3 |
| E-3 | Any target cohort's sessions for the week are busy ranges (minus exceptions); union across cohorts, overlap counts once | FR 4.3/4.6, D3 |
| E-4 | Only `status='available'` cells can be green | FR 4.11 (read side), D2 |
| E-5 | Holiday days for (semester, week) are fully blocked | D3 |
| E-6 | Venues filtered by `allowed_session_types` containing `$sessionType` (labs excluded for L/T — MPU-3133 by data) | FR 4.8, D4/D7 |
| E-7 | Venue capacity ≥ SUM of students across target cohorts; multi-cohort sessions share one room | D4, brief fact 4 |
| E-8 | `venueId` given → restrict to that venue, but E-6/E-7 still apply; null → all eligible venues with `venue_id`/`venue_code` in results | D4 |
| E-9 | Runs are maximal (contiguous cells at 30-min steps, same day + venue); runs < `$duration` dropped; `end_time` = run_start + duration, `run_end` = maximal extent | D5 |
| E-10 | No eligible cells → returns `[]` (never throws) | FR 4.7 |
| E-11 | Results ordered by day, then start_time, then venue_code | Step 6 |
| E-12 | `validateSlot($id, ...)`: `false` unless cell available, not holiday, lecturer free, all cohorts free, venue type + capacity valid; returns `false` if the slot's `week_number` ≠ `$weekNumber` (the slot's own week/semester is the evaluation context) | D10, design §4 |
| E-13 | Argument validation: `$sessionType ∈ {L,T,P}`, `$weekNumber ∈ [1,14]`, `$duration` multiple of 30 and ≥ 30; else `InvalidArgumentException` | D7/D8, Q-3 |
| E-14 | Week-aware: `class_exceptions.week_number = W` sessions subtracted from busy vectors; other weeks' exceptions ignored | D3 |
| E-15 | Integration (real seed): known scenario returns expected windows AND wall-clock < 500ms (generous; widen if flaky, never delete) | D9, NFR 1.1 |
| E-16 | 5 models exist with frozen-schema fidelity (`Venue`, `Module`, `ClassSession`, `SessionCohort`, `TimeSlot`), house pattern (`#[Fillable]`, PHPStan `@property`, `casts()`, no `$guarded`) | D6, design §5 |

## 3. Scenarios

> Given a fresh test DB seeded with compact factories (unit) or `DatabaseSeeder` (E-15), semester S, week W, lecturer L, target cohorts C = {c1, c2}, session type T, duration D.

### S-1 4-vector happy path
- Given an eligible venue V (type contains T, capacity ≥ headcount) with a 2-hour available run at (day 1, 09:00–11:00), lecturer free, cohorts free, no holiday
- When `findAvailableSlots(L, C, S, W, T, 60)`
- Then exactly one result: `{day:1, start_time:'09:00:00', end_time:'10:00:00', venue_id:V, venue_code, time_slot_ids:[4 ids], run_start:'09:00:00', run_end:'11:00:00'}`

### S-2 Lecturer busy
- Given L teaches a session overlapping (day 1, 09:00–11:00) in W (no exception)
- Then that run is excluded; earlier/later runs unaffected

### S-3 Cohort busy
- Given c1 has a session overlapping the run (L is free)
- Then the run is excluded even though L is free (union: any cohort blocks)

### S-4 Single cohort (3-vector)
- Given C = {c1} only
- Then results identical to S-1 when only c1's busyness matters; a session of c2 (not in C) does not block

### S-4b Venue-occupied excluded (base-set filter, E-4)
- Given a run of free-looking cells in venue V that are `status='occupied'` because a **different** session (other lecturer, other cohort) uses V at that time
- Then no green result at those cells; runs in other venues/other times unaffected
- (Only path exercising E-4's `status='available'` base-query filter in `findAvailableSlots`)

### S-5 Venue type filter (MPU-3133)
- Given T = 'L' and only a lab (type 'P') is free at that time
- Then no result for the lab; the run is absent (labs excluded for L/T)

### S-6 Capacity — multi-cohort SUM
- Given c1 has 25 students and c2 has 20; venue V capacity 40
- Then V excluded (40 < 45); venue of capacity 45+ included
- And c1 alone (20+... → 25) would fit V — capacity re-evaluated per call with the given cohorts

### S-7 No common slot
- Given every free cell overlaps lecturer or cohort busyness
- Then `[]`

### S-8 Duration filter
- Given a 1-hour free run and D = 90
- Then no result from that run; a 2-hour run is returned with `end_time` = run_start + 90 and `time_slot_ids` = its 4 cells

### S-9 Exception subtraction
- Given L teaches (day 1, 09:00–11:00) but `class_exceptions` has (that session, W)
- Then cells 09:00–11:00 are free for L (subject to other vectors) — the exception opens the time (D3)
- And for W+1 (no exception) the time stays busy

### S-10 Holiday day blocked
- Given a `holidays` row (S, W, day 2)
- Then zero green cells on day 2 across all venues; other days unaffected

### S-11 All-venues mode
- Given `venueId = null` and two eligible venues with free runs at different times
- Then both runs returned, each carrying its own `venue_id`/`venue_code`
- And the results are ordered by `day`, then `start_time`, then `venue_code` (E-11)

### S-12 Single-venue mode — Vector 4 still applies
- Given `venueId = V` where V's type excludes T
- Then `[]` (E-8: provided venue is filtered, not trusted)
- Given V fits type but not headcount → also `[]`

### S-13 validateSlot true
- Given an available cell, not holiday, L free, cohorts free, venue valid
- Then `validateSlot(id, L, C, T, W)` is `true`

### S-14 validateSlot false — each vector
- Given each violation in turn: status ≠ available; holiday day; L busy; c1 busy; venue type excludes T; venue capacity too small
- Then `false` in every case

### S-15 validateSlot week guard
- Given a slot with `week_number = W`, called with `weekNumber = W+1`
- Then `false` (design §4 step 2)

### S-16 Invalid arguments
- Given T = 'X', or W = 0, or W = 15, or D = 45, or D = 0
- Then `InvalidArgumentException` for each

### S-17 Integration — real seed, known scenario (E-15)
- Given `DatabaseSeeder` loaded, lecturer 4288 (DFT2), week 5 (holiday on day 2; the only week-5 exception belongs to session 14 — lecturer 4127 / RSD2(S1)G1, day 3 — so it does not affect this call)
- When `findAvailableSlots(4288, <DFT2 cohorts>, S, 5, 'L', 60)`
- Then the result is **non-empty**, every returned run is green per vectors (none overlaps a 4288/DFT2 session; none on day 2; each run's cells are `available`), and at least one concrete window is asserted — e.g. a run `{day:1, start_time:'10:00:00', end_time:'11:00:00', venue_code:'B002'}` (4288's day-1 busy = [08:00,10:00)+[11:00,13:00); DFT2(S1)G1's day-1 busy = [11:00,13:00); B002 = tutorial L,T cap 35, no sessions; week 5 has no day-1 holiday)
- And wall-clock < 500ms
