# Explore Brief — Phase 4: OCCValidator

> **Status:** Explore complete 2026-08-04. User ran `/sdd-explore OCCValidator`. All decisions made by agent per user directive ("go with whatever you recommend").
> **Location:** `/home/jinglinux/tarumt/CRS-fedora` (Fedora dev machine), branch `fedora-jing`.

---

## 1. What Was Explored

- `BACKEND-TASKS.md` Phase 4 (lines 360–446): OCCValidator skeleton, OCCResult value object, optimistic locking algorithm, state transitions, audit logging, unit+integration tests
- Frozen Phase 3 artifacts: `MatrixIntersectionEngine.php` (TimeSlot model with `version` column, `status` CHECK: available/pending/occupied)
- Migration schemas: `time_slots` (version int default 1, status CHECK), `replacement_requests` (status CHECK: pending/approved/rejected/cancelled/completed, replacement_time_slot_id FK), `audit_logs` (action CHECK: submitted/approved/rejected/cancelled/completed/occ_conflict, occ_validation_result CHECK: success/conflict)
- Seed state: 36,932 available / 1,708 occupied time_slots. No pending slots exist yet.
- Phase 3 design §7 data flows: "Engine → Phase 4/5: `time_slot_ids` per run → OCC `FOR UPDATE` + version++"
- Existing models: TimeSlot (Phase 3), no AuditLog/ReplacementRequest models yet

## 2. Key Grounding Facts

1. **OCC = Optimistic Concurrency Control** — the `version` column on `time_slots` prevents two lecturers from simultaneously reserving the same slot. The winning transaction sees the expected version; the loser sees a mismatch and gets a conflict.
2. **Slot state machine** (3 states, from migration CHECK):
   - `available` → submit → `pending`
   - `pending` (self) → cancel → `available`
   - `pending` (any) → approve → `occupied`
   - `pending` (any) → reject → `available`
   - `occupied` → terminal (permanent)
3. **`reserved` does NOT exist** as a DB status. It's a UI-only derivation (Phase 3 explore brief fact 3).
4. **`replacement_requests` has richer fields** than BACKEND-TASKS.md assumed: `semester_id`, `class_session_id`, `week_number`, `remarks`, `replacement_time_slot_id` (not just `time_slot_id`), `submitted_at`/`decided_at` timestamps.
5. **`audit_logs` CHECK constraints**: action ∈ {submitted, approved, rejected, cancelled, completed, occ_conflict}; occ_validation_result ∈ {success, conflict}.
6. **Phase 5 calls OCC** during `ReplacementRequestService::create()`: create request (pending) → OCC validateAndReserve → if conflict, clean up request.

## 3. Grilled Decisions (all made by agent)

| # | Decision | Locked answer | Rationale |
|---|----------|---------------|-----------|
| D1 | OCCValidator scope | **Submit→pending only**. Approve/reject/cancel handled by Phase 5's ReplacementRequestService. | OCC is the concurrency-critical hot path. Approve/reject/cancel are simple status+version updates with no contention — they belong in the service that owns the request lifecycle. |
| D2 | Models to create now | **ReplacementRequest + AuditLog**. Holiday/ClassException/Semester deferred. | OCCValidator receives `replacementRequestId` (task file interface) and writes audit logs. Phase 5 creates/approves/rejects requests. Holiday/ClassException are queried via raw DB (Phase 3 pattern) — no model needed yet. |
| D3 | OCCValidator interface | `validateAndReserve(timeSlotId, userId, replacementRequestId): OCCResult` — final class, no constructor deps. | Matches frozen BACKEND-TASKS.md task 4.1 exactly. `userId` for audit log user_id; `replacementRequestId` for audit log FK + request status check. |
| D4 | Transaction approach | `DB::transaction()` + `TimeSlot::query()->whereKey(id)->lockForUpdate()` + raw UPDATE with version check. | Eloquent `lockForUpdate()` + raw UPDATE in the same transaction matches the frozen design §4.3 algorithm exactly. The version check (`WHERE version = :expected`) is the OCC core — raw UPDATE with affected_rows check is clearest. |
| D5 | OCCResult value object | `readonly` class: `bool $success`, `?string $conflictReason`, `?TimeSlot $timeSlot`. Static factories: `success($slot)`, `conflict($reason)`. | Clean API for Phase 5 caller. `timeSlot` returned on success so caller can read updated version/status without re-querying. |
| D6 | Audit log on conflict | **Log on conflict too** (action='submitted', occ_validation_result='conflict'). | The frozen task 4.5 explicitly requires it. Provides a complete audit trail of all reservation attempts, not just successes. |
| D7 | Request validation | OCCValidator checks: request exists AND status='pending' AND request.replacement_time_slot_id matches the slot. | Guards against stale/orphaned requests. If request is already approved/rejected/cancelled, OCC returns conflict. |
| D8 | Concurrency testing | Unit test: manually corrupt version before second OCC call → conflict. Integration test: sequential OCC calls within a transaction → second fails (RefreshDatabase wraps in a transaction, so first call's changes aren't visible to the second — version check catches it). | True concurrent testing requires separate DB connections/processes (impractical in unit tests). The version-check test covers the same code path. |
| D9 | Audit log model | Create `AuditLog` model now (Phase 4 writes to it). House pattern: `#[Fillable]`, PHPStan `@property`, `casts()` (details → array). | House convention: all tables have models. OCC writes to audit_logs inside the transaction — needs a model for clean inserts. |
| D10 | ReplacementRequest model | Create now. Fillable: semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id, status, rejection_reason, remarks, submitted_at, decided_at. Casts: timestamps. | Phase 5 creates/approves/rejects requests. OCCValidator checks request status. Model needed before Phase 5 starts. |

## 4. Rejected Approaches (and why)

| Approach | Rejected because |
|---|---|
| OCCValidator owns all 4 state transitions | Approve/reject/cancel are simple status+version updates with no contention. Putting them in OCCValidator couples it to request lifecycle — over-engineered for a concurrency guard. |
| Pure Eloquent (no raw queries) for OCC | The version-check UPDATE with affected_rows inspection is clearer as a raw query. Eloquent's `update()` returns affected rows but the semantics are less explicit for OCC. |
| OCCValidator creates the ReplacementRequest internally | Violates single responsibility. Phase 5 creates the request first (to get an ID for the audit log), then calls OCC. If OCC fails, Phase 5 cleans up. |
| Log audit only on success | Frozen task 4.5 explicitly requires logging on conflict too. Conflict logging enables debugging "why did my request fail?" scenarios. |
| No models for audit_logs/replacement_requests | House convention: all tables have models. OCC writes to audit_logs inside the transaction — raw DB inserts work but violate the pattern. Phase 5 needs ReplacementRequest model for Eloquent relationships. |

## 5. Final Solution — Public API

### `app/Services/OCCResult.php` (value object)

```php
final readonly class OCCResult
{
    public function __construct(
        public bool $success,
        public ?string $conflictReason = null,
        public ?TimeSlot $timeSlot = null,
    ) {}

    public static function success(TimeSlot $slot): self;
    public static function conflict(string $reason): self;
}
```

### `app/Services/OCCValidator.php`

```php
final class OCCValidator
{
    /**
     * Attempt to reserve a time slot via optimistic concurrency control.
     *
     * Flow inside DB::transaction():
     *   1. Fetch replacement_request → must exist, status='pending', replacement_time_slot_id matches
     *   2. SELECT time_slots WHERE id = :slotId FOR UPDATE (lock the row)
     *   3. Check slot status: must be 'available'
     *   4. UPDATE time_slots SET status='pending', version=version+1 WHERE id=:id AND version=:expected
     *   5. If affected_rows=0 → CONFLICT (version mismatch — concurrent reserve happened)
     *   6. Write audit log (action='submitted', occ_validation_result='success'|'conflict')
     *   7. Return OCCResult
     */
    public function validateAndReserve(
        int $timeSlotId,
        int $userId,
        int $replacementRequestId,
    ): OCCResult;
}
```

### `app/Models/ReplacementRequest.php`

| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| semester_id | FK → semesters | cascadeOnDelete |
| proposer_id | FK → users | cascadeOnDelete |
| class_session_id | FK → class_sessions | cascadeOnDelete |
| week_number | tinyint | CHECK 1–14 |
| replacement_time_slot_id | FK → time_slots | cascadeOnDelete |
| approver_id | FK → users, nullable | set on approve/reject |
| status | string(20) | CHECK: pending/approved/rejected/cancelled/completed |
| rejection_reason | text, nullable | |
| remarks | text, nullable | |
| submitted_at | timestamp | |
| decided_at | timestamp, nullable | |
| timestamps | | |

### `app/Models/AuditLog.php`

| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| user_id | FK → users | |
| action | string(30) | CHECK: submitted/approved/rejected/cancelled/completed/occ_conflict |
| replacement_request_id | FK → replacement_requests, nullable | |
| time_slot_id | FK → time_slots, nullable | |
| old_status | string(20), nullable | |
| new_status | string(20), nullable | |
| occ_validation_result | string(20), nullable | CHECK: success/conflict |
| details | json, nullable | |
| created_at | timestamp | |

## 6. Key Cross-Module Data Flows

1. **Phase 5 → OCCValidator**: `ReplacementRequestService::create(proposerId, timeSlotId)` creates ReplacementRequest (status='pending', submitted_at=now), then calls `OCCValidator::validateAndReserve(timeSlotId, proposerId, requestId)`. If OCC returns conflict → delete/update request status, return error to caller.
2. **OCCValidator → time_slots**: `SELECT ... FOR UPDATE` → check status → `UPDATE SET status='pending', version=version+1 WHERE version=expected`. If version mismatch → conflict.
3. **OCCValidator → audit_logs**: INSERT with action='submitted', occ_validation_result='success'|'conflict', user_id, time_slot_id, replacement_request_id.
4. **OCCValidator → replacement_requests**: READ to validate (exists? status='pending'? slot matches?). Does NOT write to it (Phase 5 owns request lifecycle).
5. **Phase 5 → time_slots (approve/reject/cancel)**: NOT in OCCValidator scope. Phase 5 does: `UPDATE time_slots SET status='occupied'|'available', version=version+1 WHERE id=...` inside its own transaction.

## 7. Known Open Questions

- **Q-1 (resolved):** Should OCCValidator own all transitions? → No, submit→pending only.
- **Q-2 (resolved):** Should OCCValidator set `class_session_id` on time_slots when moving to pending? → **No.** Leave null; the request carries the session reference. Pending is a transient state; the session ID belongs on `replacement_requests.class_session_id` where it's permanently stored. This keeps OCC minimal.
- **Q-3:** `replacement_requests.status` CHECK includes 'completed' — what triggers it? Not in Phase 4/5 scope. Likely Phase 7 (email/notification confirms the replacement happened). Leave for later.

## 8. Transition

Scope is clear. Next: run `/sdd-propose phase4-occ-validator` to write proposal + design + specs, then review.
