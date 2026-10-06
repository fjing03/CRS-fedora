# Spec — OCCValidator (capability)

> **Status:** Spec for design.md (frozen, Batch 2). This is **Batch 3** — pending review.
> **Capability:** Optimistic Concurrency Control for time-slot reservation (CodingMAIN FR 4.9–4.12).
> **Component under test:** `app/Services/OCCValidator` + `OCCResult` + 2 models (D9/D10).

---

## 1. Description

The OCCValidator answers one question: "Can this time slot be reserved by this request right now?" It guarantees exactly one winner per slot under concurrent access (FR 4.10). On success, the slot transitions from `available` to `pending` and the version increments. On conflict, the caller gets a descriptive reason and the slot is unchanged.

## 2. Requirements

| ID | Requirement | Source |
|----|-------------|--------|
| O-1 | Uses `SELECT ... FOR UPDATE` to lock the slot row before checking status | FR 4.9, design §3 Step 2 |
| O-2 | Checks `status = 'available'`; any other status → conflict | FR 4.9, design §3 Step 3 |
| O-3 | Reads version, updates with `WHERE version = :expected`; affected_rows=0 → conflict | FR 4.9, design §3 Step 4 |
| O-4 | Validates replacement request exists, status='pending', and `replacement_time_slot_id` matches the slot | D7, design §3 Step 1 |
| O-5 | Writes audit log on success: action='submitted', occ_validation_result='success', old_status='available', new_status='pending' | FR 4.12, design §3 Step 6 |
| O-6 | Writes audit log on conflict: action='submitted', occ_validation_result='conflict', no old/new_status | FR 4.12, design §3 conflict note |
| O-7 | All operations inside a single DB transaction; on failure the transaction rolls back (slot unchanged) | design §3 |
| O-8 | Returns `OCCResult::success($slot)` on success with refreshed slot model (status='pending', version incremented) | D5, design §2 |
| O-9 | Returns `OCCResult::conflict($reason)` on failure with descriptive string | D5, design §2 |
| O-10 | `ModelNotFoundException` propagates for missing slot or request (no audit log, transaction rolls back) | design §3 exception handling |
| O-11 | 2 models exist with frozen-schema fidelity (`ReplacementRequest`, `AuditLog`), house pattern | D9/D10, design §4 |

## 3. Scenarios

> Given a fresh test DB (RefreshDatabase), semester S, a replacement request R with status='pending' and replacement_time_slot_id=V, a time slot V with status='available' and version=1.

### S-1 Happy path — available slot
- Given V status='available', version=1; R status='pending', slot=V
- When `validateAndReserve(V, userId, R)`
- Then returns `OCCResult(success=true, timeSlot=V)` where V now has status='pending', version=2
- And audit_log row: action='submitted', occ_validation_result='success', old_status='available', new_status='pending', version=1 in details

### S-2 Slot already occupied
- Given V status='occupied' (another session uses it)
- When `validateAndReserve(V, userId, R)`
- Then returns `OCCResult(success=false, conflictReason='slot_not_available')`
- And V unchanged (status='occupied', version unchanged)
- And audit_log row: occ_validation_result='conflict'

### S-3 Slot already pending (another request)
- Given V status='pending' (another request reserved it)
- When `validateAndReserve(V, userId, R)`
- Then returns conflict with reason 'slot_not_available'
- And V unchanged

### S-4 Version mismatch (concurrent reserve)
- Given V status='available', version=1
- When a concurrent transaction commits first (version becomes 2)
- When `validateAndReserve(V, userId, R)` runs with expectedVersion=1
- Then `DB::update(... WHERE version=1)` affects 0 rows → conflict 'concurrent_reserve_conflict'
- And V remains status='available', version=2 (the other transaction's update)

### S-5 Request not found
- Given no replacement request with id=999
- When `validateAndReserve(V, userId, 999)`
- Then `ModelNotFoundException` thrown (no audit log, transaction rolls back)

### S-6 Request not pending
- Given R status='approved' (already decided)
- When `validateAndReserve(V, userId, R)`
- Then returns conflict with reason 'request_not_pending_or_slot_mismatch'
- And R unchanged, V unchanged

### S-7 Request slot mismatch
- Given R status='pending' but R.replacement_time_slot_id = V2 (different slot)
- When `validateAndReserve(V, userId, R)`
- Then returns conflict with reason 'request_not_pending_or_slot_mismatch'

### S-8 Audit log on conflict
- Given V status='occupied'
- When `validateAndReserve(V, userId, R)`
- Then audit_log row exists with: action='submitted', occ_validation_result='conflict', old_status=null, new_status=null

### S-9 Slot not found
- Given no time slot with id=999
- When `validateAndReserve(999, userId, R)`
- Then `ModelNotFoundException` thrown

### S-10 Concurrent test — version corruption
- Given V status='available', version=1
- When a test manually updates V to version=2 (simulating concurrent commit)
- When `validateAndReserve(V, userId, R)` runs (expectedVersion=1)
- Then returns conflict 'concurrent_reserve_conflict'
- And V remains version=2, status='available'

### S-11 Request exists but slot column doesn't match
- Given R.replacement_time_slot_id = V (correct)
- But R has been cancelled (status='cancelled') between creation and OCC call
- Then returns conflict 'request_not_pending_or_slot_mismatch' (O-4: status check)

### S-12 Integration — real seed
- Given `DatabaseSeeder` loaded, a time_slot with status='available'
- When OCCValidator reserves it
- Then status='pending', version incremented, audit log written
- And sequential second call on same slot returns conflict
