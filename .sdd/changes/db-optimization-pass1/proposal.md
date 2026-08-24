# Proposal: Database Optimization Pass 1 — OCC Indexes & Integrity Guards

## Intent
PostgreSQL does not auto-index FK columns: FR 2.15 history and FR 4.12 audit queries seq-scan; once D3 restrict lands, every `time_slots`/`class_sessions` delete/update pays an unindexed FK-enforcement scan on `replacement_requests`. No DB guard enforces D1 (max ONE active request per block occurrence). Cascade on `class_session_id`/`replacement_time_slot_id` can silently erase request history (audit rows are delete-blocked, NO ACTION — not erased). Headcount from mutable student rows breaks reproducible demos. FR 2.16 cancellations cannot be audited (`'class_cancelled'` missing from action CHECK).

## Scope

### In Scope
- One migration: `database/migrations/2026_08_24_000001_optimize_replacement_requests_and_indexes.php`, full `down()`
- Index `replacement_requests.replacement_time_slot_id` (P0.1)
- Composite index `(proposer_id, submitted_at)` (P0.2)
- Partial unique `(class_session_id, week_number) WHERE status IN ('pending','approved')` (P0.3/D1)
- FK cascade → restrict on `class_session_id` AND `replacement_time_slot_id`; `proposer_id`/`semester_id` stay cascade deliberately (P1.4/D3)
- Index `audit_logs.time_slot_id` (P2.5)
- `cohorts.student_count` smallint CHECK(>0) + backfill (P2.6)
- `audit_logs.action` CHECK += `'class_cancelled'` (P2.7)
- Engine headcount switch + seeder backfill + test-fixture audit. Seeder guardrail: generated `users`/`students`/`lecturers` rows byte-identical — write ONLY `cohorts.student_count`.

### Out of Scope
- Frozen `users`/`students`/`lecturers`; new tables; UI; queue/email wiring
- Engine query logic beyond headcount source; archived phase1 specs

## Capabilities
New: db-optimization-pass1 (schema integrity + hot-path indexes).
Modified: none — phase1-db-schema specs stay historically accurate; constraint deltas documented here.

## Approach
Single idempotent transactional migration: dropForeign → restrict re-add on both FKs; plain CREATE INDEX; drop/re-add action CHECK; add column + CHECK then backfill; partial unique last after fixture audit. `down()` re-adds ORIGINAL action CHECK — safe only while zero `'class_cancelled'` rows exist (true today); purge before rollback after real use.

## Affected Areas
| Path | Change |
| --- | --- |
| `database/migrations/2026_08_24_000001_optimize_replacement_requests_and_indexes.php` | New |
| `app/Services/MatrixIntersectionEngine.php:292-298` | headcount reads `cohorts.student_count` |
| `database/seeders/DatabaseSeeder.php:22-37` | backfill `student_count` |
| `tests/Unit/OCCValidatorTest.php` | fixtures vs unique guard |
| `tests/{Unit,Feature}/MatrixIntersectionEngineTest.php` | headcount fixture updates |
| `CodingMAIN.md` §5 | schema note |

## Risks
- Unique guard vs duplicate-request fixtures → audit tests first
- restrict FK vs delete-based flows → none exist today
- phpstan blocked (PHP 8.5) → manual verification

## Rollback Plan
`php artisan migrate:rollback --step=1`; code changes revert via git.

## Dependencies
Local PostgreSQL green baseline: `migrate:fresh --seed`.

## Success Criteria
- [ ] EXPLAIN probe `SELECT * FROM replacement_requests WHERE replacement_time_slot_id = ?` → Index Scan
- [ ] Second INSERT of same active block occurrence fails (unique violation)
- [ ] Deleting referenced time_slot/session raises FK error instead of cascading
- [ ] Audit insert with `action='class_cancelled'` succeeds
- [ ] Headcount reads `cohorts.student_count`
- [ ] `migrate:fresh --seed` + `rollback --step=1` clean
- [ ] `composer run lint:check` passes
- [ ] `php artisan test` green
