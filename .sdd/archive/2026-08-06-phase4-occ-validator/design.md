# Design — Phase 4: OCCValidator

> **Status:** Design for proposal.md (frozen, Batch 1). This design.md is **Batch 2** — pending review.

---

## 1. Technical Approach

Optimistic Concurrency Control (OCC) via PostgreSQL row-level locking + version column. The `time_slots.version` column (frozen phase1 schema, default 1) is the OCC counter. On submit, the validator:

1. Locks the slot row (`SELECT ... FOR UPDATE`) — blocks concurrent transactions until this one commits
2. Checks status = 'available' — if not, conflict
3. Reads current version
4. Updates with `WHERE version = :expected` — if affected_rows = 0, a concurrent transaction committed first → conflict
5. Writes audit log (success or conflict) inside the same transaction
6. Returns `OCCResult`

This guarantees exactly one winner per slot (FR 4.10). The loser gets a descriptive conflict reason.

## 2. Classes

### `app/Services/OCCResult.php`

```php
final readonly class OCCResult
{
    public function __construct(
        public bool $success,
        public ?string $conflictReason = null,
        public ?TimeSlot $timeSlot = null,
    ) {}

    public static function success(TimeSlot $slot): self
    {
        return new self(success: true, timeSlot: $slot);
    }

    public static function conflict(string $reason): self
    {
        return new self(success: false, conflictReason: $reason);
    }
}
```

### `app/Services/OCCValidator.php`

```php
final class OCCValidator
{
    /**
     * Attempt to reserve a time slot via optimistic concurrency control.
     *
     * Prerequisites (caller's responsibility):
     *   - ReplacementRequest exists with status='pending' and replacement_time_slot_id = $timeSlotId
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException if request not found
     */
    public function validateAndReserve(
        int $timeSlotId,
        int $userId,
        int $replacementRequestId,
    ): OCCResult;
}
```

## 3. Algorithm — `validateAndReserve()`

All steps inside `DB::transaction()`:

```
Step 1 — Validate replacement request (READ)
  $request = ReplacementRequest::findOrFail($replacementRequestId)
  Assert: $request->status === 'pending'
  Assert: $request->replacement_time_slot_id === $timeSlotId
  If either fails → return OCCResult::conflict('request_not_pending_or_slot_mismatch')
  (Do NOT delete the request — let Phase 5 handle cleanup on conflict)

Step 2 — Lock the slot row (FOR UPDATE)
  $slot = TimeSlot::query()->whereKey($timeSlotId)->lockForUpdate()->firstOrFail()
  (PostgreSQL: acquires an exclusive row lock; other transactions block here)

Step 3 — Check slot status
  If $slot->status !== 'available' → return OCCResult::conflict('slot_not_available')

Step 4 — Version check + atomic update (raw UPDATE per locked decision D4)
  $expectedVersion = $slot->version
  $affected = DB::update(
      'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
      ['pending', $expectedVersion + 1, $timeSlotId, $expectedVersion]
  )
  If $affected === 0 → return OCCResult::conflict('concurrent_reserve_conflict')

Step 5 — Refresh the slot model
  $slot->refresh()  (now has status='pending', version=expectedVersion+1)

Step 6 — Write audit log
  AuditLog::create([
      'user_id' => $userId,
      'action' => 'submitted',
      'replacement_request_id' => $replacementRequestId,
      'time_slot_id' => $timeSlotId,
      'old_status' => 'available',
      'new_status' => 'pending',
      'occ_validation_result' => 'success',
      'details' => ['version' => $expectedVersion],
  ])

Step 7 — Return success
  return OCCResult::success($slot)
```

**On any conflict path** (steps 1, 3, 4): write audit log with `occ_validation_result = 'conflict'` before returning. The conflict audit log does NOT set `old_status`/`new_status` (slot wasn't changed).

**On exception** (e.g. TimeSlot not found): let `ModelNotFoundException` propagate — caller handles it. Audit log is NOT written (the transaction rolls back).

## 4. Models (D9/D10) — match frozen phase1 schema exactly

### `app/Models/ReplacementRequest.php`

| Column | Type | Fillable | Casts |
|--------|------|----------|-------|
| id | PK | — | — |
| semester_id | FK → semesters | semester_id | — |
| proposer_id | FK → users | proposer_id | — |
| class_session_id | FK → class_sessions | class_session_id | — |
| week_number | tinyint | week_number | int |
| replacement_time_slot_id | FK → time_slots | replacement_time_slot_id | — |
| approver_id | FK → users, nullable | approver_id | — |
| status | string(20) | status | — |
| rejection_reason | text, nullable | rejection_reason | — |
| remarks | text, nullable | remarks | — |
| submitted_at | timestamp | submitted_at | datetime |
| decided_at | timestamp, nullable | decided_at | datetime |

Relationships: `belongsTo(User, 'proposer_id')`, `belongsTo(User, 'approver_id')`, `belongsTo(TimeSlot, 'replacement_time_slot_id')`, `belongsTo(ClassSession)`.

### `app/Models/AuditLog.php`

| Column | Type | Fillable | Casts |
|--------|------|----------|-------|
| id | PK | — | — |
| user_id | FK → users | user_id | — |
| action | string(30) | action | — |
| replacement_request_id | FK, nullable | replacement_request_id | — |
| time_slot_id | FK, nullable | time_slot_id | — |
| old_status | string(20), nullable | old_status | — |
| new_status | string(20), nullable | new_status | — |
| occ_validation_result | string(20), nullable | occ_validation_result | — |
| details | json, nullable | details | array |
| created_at | timestamp | — | datetime |

Relationships: `belongsTo(User)`, `belongsTo(ReplacementRequest)`, `belongsTo(TimeSlot)`.

House pattern: `#[Fillable]` attribute, PHPStan `@property` docblocks, `casts()` method, no `$guarded`.

## 5. Query Performance

- `SELECT ... FOR UPDATE` on `time_slots` by PK: O(1) — single row lock. Under high contention, transactions queue at this lock (PostgreSQL handles this efficiently).
- `ReplacementRequest::findOrFail()` by PK: O(1).
- `AuditLog::create()`: single INSERT with indexes on `replacement_request_id` and `created_at`.
- No full-table scans. All queries hit indexes.

## 6. Data Flows

1. **Phase 5 → OCCValidator**: `ReplacementRequestService::create(proposerId, timeSlotId)` creates ReplacementRequest (status='pending', submitted_at=now), gets its ID, then calls `OCCValidator::validateAndReserve(timeSlotId, proposerId, requestId)`. If OCC returns conflict → delete the request (or set status='cancelled'), return error.
2. **OCCValidator → time_slots**: FOR UPDATE lock → check status → version++ → update to pending.
3. **OCCValidator → audit_logs**: INSERT on both success and conflict paths.
4. **OCCValidator → replacement_requests**: READ only (validate existence + status + slot match). Does NOT write.
5. **Phase 5 → time_slots (approve/reject/cancel)**: NOT in OCCValidator. Phase 5 does `UPDATE time_slots SET status='occupied'|'available', version=version+1` in its own transaction.

## 7. Dependencies

- Runtime: Eloquent models (created here), PostgreSQL 14+, PHP 8.3. No new packages.
- Test: existing PHPUnit + `RefreshDatabase` + factories (UserFactory exists; new factories for ReplacementRequest, AuditLog).
- Depends on: Phase 3 (TimeSlot model with version column, committed `835bc8f`).
- Consumed by: Phase 5 (ReplacementRequestService).

## 8. Files

| File | Action |
|------|--------|
| `app/Services/OCCValidator.php` | New |
| `app/Services/OCCResult.php` | New |
| `app/Models/ReplacementRequest.php` | New |
| `app/Models/AuditLog.php` | New |
| `database/factories/ReplacementRequestFactory.php` | New (tests) |
| `database/factories/AuditLogFactory.php` | New (tests) |
| `tests/Unit/OCCValidatorTest.php` | New |
| `tests/Feature/OCCValidatorTest.php` | New |
| `page-changelogs/backend-automated-by-ai.md` | Append |

## 9. Open Questions (carried from brief)

- **Q-3:** `replacement_requests.status` CHECK includes 'completed' — what triggers it? Not in Phase 4/5 scope. Likely Phase 7. Leave for later.
