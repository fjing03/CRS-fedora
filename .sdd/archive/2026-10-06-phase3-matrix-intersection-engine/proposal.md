# Proposal — Phase 3: MatrixIntersectionEngine

> **Status:** Explore complete 2026-08-04 (10 decisions locked in explore-brief.md). This proposal.md is **Batch 1** — pending review.

---

## 1. Why This Change Is Needed

The core value proposition of the system (CodingMAIN §3, Objective 1) is the **Multi-Entity Matrix Intersection Engine**: computing conflict-free replacement windows for a lecturer's cancelled class. It is currently `🔲 Not built` — the arrangement-page UI templates (replacement-arrangement, request-approval) show mock grids, and the PL dashboard mock already displays a "✓ Valid / ⚠ Conflict" slot-validity field that must be computed by the backend (FR 3.4).

The schema (Phase 1, archived) and seed data (Phase 2, committed) are in place: 23 venues, 36 modules, 33 class_sessions, 37 session_cohorts, 38,640 materialized time_slots. The engine is the first consumer of that data and the dependency of Phases 4–6 (OCC validator, FCFS approval, emails).

## 2. In Scope

- `app/Services/MatrixIntersectionEngine.php` — the 4-vector intersection service:
  - `findAvailableSlots(...)` — returns duration-filtered green windows (see design)
  - `validateSlot(...)` — single-slot validity predicate (FR 3.4)
- Domain models (currently **missing** — phase1 was migrations-only): `Venue`, `Module`, `ClassSession`, `SessionCohort` (pivot), `TimeSlot`
- Tests: `tests/Unit/MatrixIntersectionEngineTest.php` (factory fixtures, full logic coverage) + `tests/Feature/MatrixIntersectionEngineTest.php` (real `DatabaseSeeder`, correctness + `<500ms`)
- PHPStan annotations + Pint compliance for all new files

## 3. Explicitly Out of Scope

- HTTP endpoint/controller/route — UI wiring deferred to Sprint 3 (FR 2.4–2.7 pages)
- `ReplacementRequest` / `AuditLog` / `Holiday` / `ClassException` / `Semester` models — belong to Phase 4/5
- OCC validator, request lifecycle, FCFS queue, email notifications (Phases 4–6)
- Conflicted-classes list query (design §6 of phase1) — sibling concern of the replacement-home page
- Any schema/migration changes — frozen phase1 schema is consumed as-is
- Any changes to frozen tables (`users`, `students`, `lecturers`, `cohorts`, `faculties`, `departments`, `programmes`) or their seeders
- `dataset/timetable.md` synthesis — phase2 seeders already carry the timetable inline

## 4. Functional Requirements Applied

| FR/NFR | Requirement | How satisfied |
|--------|-------------|---------------|
| FR 4.3 | 4-way set intersection (lecturer × cohorts × room × capacity) | Engine's grid-driven intersection, D2 |
| FR 4.4 / NFR 1.1 | Results ≤ 500ms | Indexed queries + integration-test bound |
| FR 4.6 | 3-vector for single cohort | Same code path with 1 cohort id |
| FR 4.7 | Empty result when fully occupied | Returns `[]`, never crashes |
| FR 4.8 | Session-type venue filtering (labs excluded for L/T) | Venue `allowed_session_types` filter (seeded data already encodes it) |
| FR 3.4 | Pre-computed slot validity on PL dashboard | `validateSlot()` method |
| FR 2.4 / 2.5 | Venue dropdown default + recalc | Single-venue mode (`venueId` param) + all-venues mode |
| FR 4.11 | Slot state machine (available/pending/reserved/occupied) | Engine reads only `status='available'` cells; `reserved` derived elsewhere |

## 5. Impact Scope

### Files created

| File | Purpose |
|------|---------|
| `app/Services/MatrixIntersectionEngine.php` | The engine |
| `app/Models/Venue.php` | Model |
| `app/Models/Module.php` | Model |
| `app/Models/ClassSession.php` | Model |
| `app/Models/SessionCohort.php` | Pivot model |
| `app/Models/TimeSlot.php` | Model |
| `tests/Unit/MatrixIntersectionEngineTest.php` | Unit tests |
| `tests/Feature/MatrixIntersectionEngineTest.php` | Integration test |
| `database/factories/VenueFactory.php` | Test factory |
| `database/factories/ModuleFactory.php` | Test factory |
| `database/factories/ClassSessionFactory.php` | Test factory |
| `database/factories/TimeSlotFactory.php` | Test factory |

### Files touched

- `page-changelogs/backend-automated-by-ai.md` — append engine entries (existing tracked file)

No other existing files modified. New `app/Services/` directory created.

### Modules/systems affected

- **Reads (7 tables):** `time_slots`, `class_sessions`, `session_cohorts`, `class_exceptions`, `holidays`, `venues`, `students`. (`modules`/`cohorts`/`semesters` appear only via model relationships or scalar params — never queried directly: D6/D7.)
- **Consumed by (future):** Phase 4/5 OCC + approval services, Sprint 3 UI wiring

## 6. Design Decisions Locked (from explore)

| # | Decision | Locked answer |
|---|----------|---------------|
| D1 | Scope | Service + tests only, no HTTP layer |
| D2 | Computation | Grid-driven: base = `time_slots` available cells for (semester, week[, venue]) |
| D3 | Conflicts | Week-aware: `class_exceptions` subtracted from busy vectors; `holidays` block the whole day |
| D4 | Venue | `venueId` nullable; all-venues mode when null; Vector 4 always applies |
| D5 | Output | Maximal green runs ≥ duration, each with constituent `time_slot_ids` |
| D6 | Models | 5 engine-scoped models created now; rest deferred |
| D7 | Type validation | Caller-trusted `sessionType` ∈ {L,T,P}; venue-side filter only |
| D8 | Week | `week_number` required (1–14) |
| D9 | Tests | Unit (factories) + 1 integration (full seed, generous <500ms) |
| D10 | validateSlot | In scope (FR 3.4) |

## 7. Constraints & Conventions

- Follow CodingMAIN.md §10 exactly; house PHPStan `@property` pattern on models (see `User.php`)
- Run `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G` after apply — **no new errors** (19 pre-existing model-generics errors remain untouched, frozen-model constraint)
- Conventional commit prefix: `feat:`
- No migrations, no seeder changes, no config changes

## 8. Risks

| Risk | Mitigation |
|------|-----------|
| `<500ms` bound flaky in CI | Generous bound; widen if flaky (never delete) |
| Full-seed integration test slow under `RefreshDatabase` (38,640 rows) | Single test; seeders chunk inserts |
| All-venues mode scan width (18 L/T venues × 6 days × 20 cells) | Indexed per-venue reads; bounded by status index |
| Missing models surprise implementers | Models in scope (D6) |
