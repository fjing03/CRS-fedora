# Tasks: db-optimization-pass1

Frozen basis: `proposal.md` (Batch 1) + `design.md` (Batch 2, PASS 2026-08-24). Each task cites its design section. Execute T1..T7 in order — later tasks assume earlier schema/code in place.

Scope acknowledgement (per review-log obligation): `app/Models/Cohort.php` ($fillable + docblock) is a **sanctioned affected file** added during design review (design §4) — declared here explicitly so apply-review does not flag it as smuggled scope.

## T1: Optimization migration (design §1 a–h, §2)

File: NEW `database/migrations/2026_08_24_000001_optimize_replacement_requests_and_indexes.php`

- [ ] 1.1 Write `up()` in design order: (a) two separate `$table->dropForeign(['class_session_id'])` / `$table->dropForeign(['replacement_time_slot_id'])` calls, then re-add both via `foreign()` + `restrictOnDelete()` exactly as design §1(a) shows (no column recreation); (b)+(c)+(e) three `DB::statement('CREATE INDEX ...')` — `idx_replacement_requests_time_slot`, `idx_replacement_requests_proposer_submitted`, `idx_audit_logs_time_slot`; (d) partial unique `uq_replacement_requests_active_block` LAST among `replacement_requests` DDL; (f)+(g) `cohorts.student_count` signed nullable `smallInteger` → named CHECK `cohorts_student_count_check (> 0)` → convergence UPDATE from `students`; (h) `DROP CONSTRAINT IF EXISTS audit_logs_action_check` → re-add seven-value CHECK. All names verbatim per design §1.
- [ ] 1.2 Write `down()` per design §2 — exact reverse order (h)→(a): restore six-value action CHECK (rollback hazard documented, zero `class_cancelled` rows assumed), drop CHECK + `student_count` column, `DROP INDEX IF EXISTS` x4, re-add original CASCADE FKs (Laravel default names regenerate symmetrically).

Done criteria:
- [ ] File exists; `php -l` passes; anonymous-class migration matches house precedent style (`2026_08_03_000006_create_time_slots_table.php:29-41`).

Verification: `php artisan migrate --pretend` emits plausible SQL; real execution deferred to T2/T6 batteries.

## T2: Denormalized cohort counts — model + seeder (design §4)

Files: `app/Models/Cohort.php`, `database/seeders/DatabaseSeeder.php`

- [ ] 2.1 `Cohort.php`: append `'student_count'` to `$fillable` (line 20) and add `@property int|null $student_count` docblock line (after `intake`, line 16). Explicitly sanctioned file — see header note.
- [ ] 2.2 `DatabaseSeeder.php`: inside the existing `$cohortsData` foreach (lines 87–96), add `'student_count' => self::STUDENT_COUNTS[sprintf('%s%d(S%d)G%d', ...)] ?? 10` after `'intake'` — key construction mirrors `cohortCode()`; the `?? 10` fallback intentionally mirrors `seedStudents()` line 152.
- [ ] 2.3 Guardrail check (review-log obligation): confirm `seedUsers()`/`seedLecturers()`/`seedStudents()` (lines 99–180) untouched; diff shows ONLY the one payload key + the two model lines; generated `users`/`students`/`lecturers` rows byte-identical (writes only to `cohorts.student_count`).

Done criteria:
- [ ] `php artisan migrate:fresh --seed` exits 0; all 14 cohort rows carry non-null `student_count` equal to `STUDENT_COUNTS` (spot-check DFT2(S1)G1 = 28).

Verification: `SELECT COUNT(*) FROM cohorts WHERE student_count IS NOT NULL AND student_count > 0;` → 14.

## T3: Engine headcount source switch (design §3)

File: `app/Services/MatrixIntersectionEngine.php`

- [ ] 3.1 Add `use App\Models\Cohort;` beside existing imports (lines 5–8); `Student` remains imported for the legacy fallback.
- [ ] 3.2 Replace `requiredHeadcount()` body (lines 292–297) with the dual-path implementation verbatim per design §3: empty input → 0; any NULL `student_count` in set → whole-set live `Student` fallback (single extra query); otherwise PK `pluck('student_count', 'id')` sum. Keep updated docblock.
- [ ] 3.3 Confirm zero other diffs in the file (`git diff` scoped to import + method).

Done criteria:
- [ ] Diff limited to 3.1–3.2; `composer run lint:check` clean on this file.

Verification: `php artisan test --filter=test_s6_capacity_multi_cohort_sum` (green pre-T4 via fallback; proves fast path after T4).

## T4: Unit fixtures + headcount fast-path test (design §5)

File: `tests/Unit/MatrixIntersectionEngineTest.php`

- [ ] 4.1 `setUp()`: add `'student_count' => 1` to both `Cohort::create` calls (lines 67–83), matching the 1 student per cohort created at lines 122–129.
- [ ] 4.2 `seedStudents()` helper (lines 161–176): after the insert loop, append `DB::table('cohorts')->where('id', $cohortId)->increment('student_count', $count);` — preserves the column-vs-population invariant so s6 capacity tests (45 = 1+24+1+19; single-cohort 25) exercise the FAST path.
- [ ] 4.3 Add `test_required_headcount_reads_denormalized_cohort_counts()` verbatim per design §5: force counts 24/19, delete both cohorts' student rows, assert `invokePrivate('requiredHeadcount', [...]) === 43`.
- [ ] 4.4 Fixture-audit record, no edits: `tests/Unit/OCCValidatorTest.php` (one explicit request per test, `RefreshDatabase`) cannot collide with the partial unique; `tests/Feature/MatrixIntersectionEngineTest.php` pinned expectations unchanged post-T2 (B002, headcount 28).

Done criteria:
- [ ] `php artisan test tests/Unit/MatrixIntersectionEngineTest.php` fully green, s6 tests passing through the denormalized path.

Verification: same command.

## T5: Schema-integrity constraint tests (design §5; SC #2–#4)

File: NEW `tests/Unit/DbIntegrityConstraintsTest.php` — `RefreshDatabase`; fixtures follow the lightweight `OCCValidatorTest::seedData()` pattern (raw `DB::table` inserts + factories); no production code touched.

- [ ] 5.1 Test 1 (D1 guard, SC #2): seed one pending request, repeat the identical INSERT (same `class_session_id` + `week_number`, active status) → expect `UniqueConstraintViolationException`.
- [ ] 5.2 Test 2 (restrict FKs, SC #3): delete referenced `time_slots` row and, as sibling assertion, referenced `class_sessions` row → expect `QueryException` (SQLSTATE 23503), request row survives.
- [ ] 5.3 Test 3 (deliberate exclusion sanity): delete the proposer `users` row → request STILL cascades (`assertDatabaseMissing`) — documents `proposer_id` cascade exclusion as intended.
- [ ] 5.4 Test 4 (widened CHECK, SC #4): insert `audit_logs` row with `action='class_cancelled'` → succeeds, `assertDatabaseHas`.

Done criteria:
- [ ] `php artisan test tests/Unit/DbIntegrityConstraintsTest.php` → 4 pass.

Verification: same command.

## T6: Full verification battery (design §6 #1–#8)

Run in order; record outcomes against proposal Success Criteria.

- [ ] 6.1 #6 Round-trip: `php artisan migrate:fresh --seed && php artisan migrate:rollback --step=1 && php artisan migrate` — all exit 0; spot-check `pg_constraint` returns six-value `audit_logs_action_check` after rollback; finish with `migrate:fresh --seed` to restore demo dataset.
- [ ] 6.2 #1 EXPLAIN probe (seeded request): plan shows `Index Scan using idx_replacement_requests_time_slot`; `\d replacement_requests` lists all three new indexes.
- [ ] 6.3 #2/#3/#4 manual psql probes (duplicate INSERT violation; referenced-slot DELETE raises FK 23503; `class_cancelled` INSERT ok + `bogus_action` negative control) — automated equivalents already green in T5; manual run optional confirmation.
- [ ] 6.4 #7 `composer run lint:check` clean. `types:check` stays out of battery per frozen proposal (PHP 8.5 blocker) — compensate with manual line-by-line diff review of T1–T3.
- [ ] 6.5 #8 `php artisan test` — full suite green including the three touched/new test files.

Done criteria:
- [ ] All eight criteria checked off with recorded results; zero regressions.

## T7: Documentation (design §7)

Files: `CodingMAIN.md`, `page-changelogs/backend-automated-by-ai.md`

- [ ] 7.1 Update `CodingMAIN.md` §5 schema tables/notes: mark applied deltas — restrict FKs on `replacement_requests.class_session_id` / `replacement_time_slot_id` (proposer/semester remain CASCADE by design), partial unique active-block guard, three new indexes, `cohorts.student_count` (nullable smallint, CHECK > 0), widened `audit_logs.action` enum (seven values). Factual register only.
- [ ] 7.2 Append dated entry to `page-changelogs/backend-automated-by-ai.md`: change name, migration filename, files touched, verification summary (battery results).

Done criteria:
- [ ] Both docs updated factually; no unrelated prose changes.

---

Decision needed before apply: No
Chained PRs recommended: No
400-line budget risk: Low
Estimated total changed lines: ~360

Basis: migration ~120 (new) + `DbIntegrityConstraintsTest` ~150 (new) dominate; unit-test edits ~20, engine ~15, seeder ~7, model ~2, docs ~45. Contingency: only a substantially verbose T5 file would push past 400 (then revisit as Medium).
