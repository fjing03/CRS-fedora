# Tasks — Phase 4: OCCValidator

> **Status:** Tasks for design.md + specs (frozen, Batches 2+3). This is **Batch 4** — pending review.
> **Working tree:** `fedora-jing` at `/home/jinglinux/tarumt/CRS-fedora`. Frozen artifacts: proposal.md, design.md, specs/occ-validator/spec.md.
> Each task ≤ 2 hours. Quality gate after each task: PHPUnit targeted run stays green; Pint + PHPStan at task 8.

---

## Task 1 — Domain models (D9/D10, design §4, O-11)

**DoD:** 2 models + 2 factories exist; `php artisan tinker` can load both tables; models mirror frozen schema exactly.

- [ ] `app/Models/ReplacementRequest.php` — fillable: semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id, approver_id, status, rejection_reason, remarks, submitted_at, decided_at; casts: week_number→int, submitted_at→datetime, decided_at→datetime; relationships: `belongsTo(User, 'proposer_id')`, `belongsTo(User, 'approver_id')`, `belongsTo(TimeSlot, 'replacement_time_slot_id')`, `belongsTo(ClassSession)`
- [ ] `app/Models/AuditLog.php` — fillable: user_id, action, replacement_request_id, time_slot_id, old_status, new_status, occ_validation_result, details; casts: details→array; relationships: `belongsTo(User)`, `belongsTo(ReplacementRequest)`, `belongsTo(TimeSlot)`
- [ ] Factories: `ReplacementRequestFactory`, `AuditLogFactory` (compact, permissive)
- [ ] House pattern: `#[Fillable]` attribute, PHPStan `@property` docblocks, `casts()` method, no `$guarded`

**Verify:** `php artisan tinker --execute="echo App\Models\ReplacementRequest::count().'|'.App\Models\AuditLog::count();"` (→ 0|0 on seeded DB — no requests/logs exist yet); `vendor/bin/phpstan analyse app/Models --memory-limit=1G --no-progress` (0 new errors).

## Task 2 — OCCResult value object + OCCValidator skeleton (D3/D5, design §2)

**DoD:** OCCResult compiles; OCCValidator compiles with signature and request validation (Step 1).

- [ ] `app/Services/OCCResult.php` — `final readonly class` with `bool $success`, `?string $conflictReason`, `?TimeSlot $timeSlot`; static factories `success($slot)`, `conflict($reason)`
- [ ] `app/Services/OCCValidator.php` — `final class`, no constructor deps
- [ ] `validateAndReserve(timeSlotId, userId, replacementRequestId): OCCResult` — full PHPStan docblock
- [ ] Step 1 only: `ReplacementRequest::findOrFail($replacementRequestId)` → check status='pending' + slot matches → else `OCCResult::conflict('request_not_pending_or_slot_mismatch')`
- [ ] `throw new LogicException('Not implemented')` after Step 1 (later tasks fill Steps 2–7)
- [ ] `vendor/bin/phpstan analyse app/Services --memory-limit=1G --no-progress` — 0 errors

## Task 3 — OCC core: FOR UPDATE + status check + version++ (D4, design §3 Steps 2–5, O-1–O-3)

**DoD:** `validateAndReserve` handles the happy path end-to-end (minus audit log).

- [ ] Step 2: `TimeSlot::query()->whereKey($timeSlotId)->lockForUpdate()->firstOrFail()` inside `DB::transaction()`
- [ ] Step 3: check `$slot->status !== 'available'` → conflict 'slot_not_available'
- [ ] Step 4: `DB::update('UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?', ['pending', $expectedVersion + 1, $timeSlotId, $expectedVersion])` — check affected_rows=0 → conflict 'concurrent_reserve_conflict'
- [ ] Step 5: `$slot->refresh()`
- [ ] Wrap Steps 1–5 in `DB::transaction()`
- [ ] Still throw LogicException('Not implemented') after Step 5 (audit log is Task 4)

## Task 4 — Audit logging + conflict paths (O-5/O-6/O-7, design §3 Step 6 + conflict note)

**DoD:** `validateAndReserve` complete — both success and conflict paths write audit logs.

- [ ] On success path (after Step 5): `AuditLog::create([...])` with action='submitted', occ_validation_result='success', old_status='available', new_status='pending', details=['version' => $expectedVersion]
- [ ] On conflict paths (Steps 1, 3, 4): `AuditLog::create([...])` with action='submitted', occ_validation_result='conflict', old_status=null, new_status=null (slot wasn't changed)
- [ ] Remove LogicException — method is now complete
- [ ] Ensure ModelNotFoundException propagates for missing slot/request (no audit log, transaction rolls back)

## Task 5 — Unit tests (O-1–O-10, S-1..S-11)

**DoD:** All spec scenarios S-1..S-11 have test methods; all pass.

- [ ] `tests/Unit/OCCValidatorTest.php` — `RefreshDatabase`, extends `Tests\TestCase`
- [ ] setUp: create semester (DB::table), lecturer user, cohort, class_session, replacement_request (status='pending', slot=V), time_slot (status='available', version=1), venue
- [ ] Helper: `occ()` returning fresh `OCCValidator` instance
- [ ] Tests (snake_case per Pint):
  - `test_s1_happy_path_available_slot` — assert success, slot status='pending', version=2, audit log success
  - `test_s2_slot_occupied_returns_conflict` — slot status='occupied' → conflict 'slot_not_available', no status change
  - `test_s3_slot_pending_returns_conflict` — slot status='pending' → conflict
  - `test_s4_version_mismatch_returns_conflict` — manually update version to 2 → conflict 'concurrent_reserve_conflict'
  - `test_s5_request_not_found_throws` — ReplacementRequest id=999 → ModelNotFoundException
  - `test_s6_request_not_pending_returns_conflict` — request status='approved' → conflict
  - `test_s7_request_slot_mismatch_returns_conflict` — request.slot = different slot → conflict
  - `test_s8_audit_log_on_conflict` — slot occupied → audit_log occ_validation_result='conflict'
  - `test_s9_slot_not_found_throws` — TimeSlot id=999 → ModelNotFoundException
  - `test_s10_version_corruption_simulates_concurrency` — same as S-4 but explicit manual corruption (D8)
  - `test_s11_cancelled_request_returns_conflict` — request status='cancelled' → conflict

## Task 6 — Integration test (S-12)

**DoD:** Real-seed acceptance gate passes.

- [ ] `tests/Feature/OCCValidatorTest.php` — `RefreshDatabase` + real `DatabaseSeeder`
- [ ] `test_s12_integration_real_seed`: find a time_slot with status='available'; create a replacement_request pointing to it; call `validateAndReserve`; assert success + status='pending' + version=2 + audit log; call again on same slot → assert conflict
- [ ] Sequence reset before seeding (same pattern as Phase 3: `SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)`)
- [ ] Run: `php vendor/phpunit/phpunit/phpunit --no-coverage tests/Feature/OCCValidatorTest.php`

## Task 7 — Full suite green

**DoD:** All unit + integration scenarios pass together.

- [ ] Run full `php vendor/phpunit/phpunit/phpunit --no-coverage`
- [ ] Cross-check each spec scenario S-1..S-12 mapped to a test method

## Task 8 — Quality gates + changelog + commit

**DoD:** House rules satisfied; commit ready.

- [ ] `vendor/bin/pint --test` (run `vendor/bin/pint` on new files first) — Pint clean on new/touched files
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G` — 0 new errors (pre-existing frozen-model errors untouched)
- [ ] Full suite: `php vendor/phpunit/phpunit/phpunit --no-coverage` — all green
- [ ] Append OCC entries to `page-changelogs/backend-automated-by-ai.md`
- [ ] Commit: `feat(occ): add OCCValidator with optimistic locking and audit trail`
