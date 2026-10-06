# Proposal — Phase 4: OCCValidator

> **Status:** Explore complete 2026-08-04 (10 decisions locked in explore-brief.md). This proposal.md is **Batch 1** — pending review.

---

## 1. Why This Change Is Needed

The Multi-Entity Matrix Intersection Engine (Phase 3, committed `835bc8f`) computes conflict-free replacement windows. But **reserving** a window requires concurrency control — without it, two lecturers clicking the same green cell simultaneously would both succeed, creating a double-booking.

The `time_slots.version` column (frozen phase1 schema) exists exactly for this: Optimistic Concurrency Control (OCC). The OCCValidator is the gatekeeper that atomically checks availability and transitions the slot to `pending` status, guaranteeing at most one winner per slot.

Phase 5 (FCFS Approval) depends on this: `ReplacementRequestService::create()` calls OCCValidator to reserve the slot before creating the request.

## 2. In Scope

- `app/Services/OCCValidator.php` — the optimistic locking service:
  - `validateAndReserve(timeSlotId, userId, replacementRequestId): OCCResult`
  - DB transaction: `SELECT ... FOR UPDATE` → status check → version++ → audit log
- `app/Services/OCCResult.php` — readonly value object (success/failure + reason + slot)
- Domain models (currently **missing**): `ReplacementRequest`, `AuditLog`
- Tests: `tests/Unit/OCCValidatorTest.php` (unit) + `tests/Feature/OCCValidatorTest.php` (integration with real DB)
- PHPStan annotations + Pint compliance for all new files

## 3. Explicitly Out of Scope

- Approve/reject/cancel transitions — belong to Phase 5's `ReplacementRequestService` (D1)
- HTTP endpoint/controller/route — UI wiring deferred to Sprint 3
- Holiday/ClassException/Semester models — deferred; queried via raw DB (Phase 3 pattern)
- `class_session_id` on time_slots when pending — leave null; the request carries the session reference (locked decision, Q-2 resolved in explore)
- Email notifications (Phase 6)
- Any schema/migration changes — frozen phase1 schema consumed as-is
- Any changes to frozen tables (`users`, `students`, `lecturers`, `cohorts`, `faculties`, `departments`, `programmes`) or their seeders
- FCFS queue ordering — Phase 5 concern

## 4. Functional Requirements Applied

| FR/NFR | Requirement | How satisfied |
|--------|-------------|---------------|
| FR 4.9 | OCC via version column on time_slots | `SELECT ... FOR UPDATE` + version++ with affected-rows check |
| FR 4.10 | Exactly one submission wins; conflict alert to the other | Version mismatch → OCCResult.conflict with reason |
| FR 4.11 | Slot state machine (available/pending/occupied) | Submit→pending transition (other transitions in Phase 5) |
| FR 4.12 | OCC validation outcomes logged in audit trail | AuditLog written inside transaction (success + conflict) |

## 5. Impact Scope

### Files created

| File | Purpose |
|------|---------|
| `app/Services/OCCValidator.php` | The OCC service |
| `app/Services/OCCResult.php` | Value object |
| `app/Models/ReplacementRequest.php` | Domain model |
| `app/Models/AuditLog.php` | Domain model |
| `database/factories/ReplacementRequestFactory.php` | Test factory |
| `database/factories/AuditLogFactory.php` | Test factory |
| `tests/Unit/OCCValidatorTest.php` | Unit tests |
| `tests/Feature/OCCValidatorTest.php` | Integration test |

### Files touched

- `page-changelogs/backend-automated-by-ai.md` — append OCC entries

No other existing files modified.

### Modules/systems affected

- **Reads:** `time_slots` (FOR UPDATE + version check), `replacement_requests` (validate request exists/pending/slot matches)
- **Writes:** `time_slots` (status='pending', version++), `audit_logs` (insert)
- **Consumed by (future):** Phase 5 `ReplacementRequestService::create()`

## 6. Design Decisions Locked (from explore)

| # | Decision | Locked answer |
|---|----------|---------------|
| D1 | OCCValidator scope | Submit→pending only; approve/reject/cancel in Phase 5 |
| D2 | Models to create | ReplacementRequest + AuditLog now; Holiday/ClassException/Semester deferred |
| D3 | Interface | `validateAndReserve(timeSlotId, userId, replacementRequestId): OCCResult` |
| D4 | Transaction | `DB::transaction()` + `lockForUpdate()` + raw UPDATE with version check |
| D5 | OCCResult | `readonly` class: success, conflictReason?, timeSlot?; static factories |
| D6 | Audit on conflict | Log action='submitted', occ_validation_result='conflict' |
| D7 | Request validation | Check exists + status='pending' + slot matches |
| D8 | Concurrency testing | Manual version corruption + sequential OCC calls |
| D9 | AuditLog model | Create now (house convention, OCC writes to it) |
| D10 | ReplacementRequest model | Create now (Phase 5 dependency, OCC reads it) |

## 7. Constraints & Conventions

- Follow CodingMAIN.md §10 exactly; house PHPStan `@property` pattern on models (see `User.php`)
- `vendor/bin/phpstan analyse --memory-limit=1G` after apply — **no new errors** (19 pre-existing remain)
- Conventional commit prefix: `feat:`
- No migrations, no seeder changes, no config changes
- Use `DB::transaction()` + `lockForUpdate()` (Eloquent) + raw UPDATE for version check

## 8. Risks

| Risk | Mitigation |
|------|-----------|
| Version-check test not truly concurrent | Manual version corruption tests the same code path; true concurrency requires separate DB connections (impractical in unit tests) |
| Audit log write fails inside transaction | Audit log INSERT is the last step — if it fails, the whole transaction rolls back (slot stays available). Acceptable: audit is for observability, not correctness. |
| Phase 5's approve/reject bypasses OCC | Approve/reject are simple status+version updates with no contention — they don't need OCC. Phase 5 handles them in its own transaction. |
