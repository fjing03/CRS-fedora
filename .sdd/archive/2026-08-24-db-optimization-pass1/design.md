# Design: db-optimization-pass1

Frozen basis: `proposal.md` (Batch 1, reviewed PASS 2026-08-24, see `review-log.md`).
All constraint/index names below were verified against the live PostgreSQL catalog
(`pg_constraint` on `class_replacement`) on 2026-08-24, not inferred from files alone.

---

## 1. Migration anatomy

File: `database/migrations/2026_08_24_000001_optimize_replacement_requests_and_indexes.php`
(anonymous-class migration, Laravel 13, PG backend). Laravel wraps each migration in a
transaction on PgSQL by default (`$withinTransaction === true`), so any step failing rolls
back the whole pass. Style follows the house precedent
`2026_08_03_000006_create_time_slots_table.php:29-41`: Blueprint for structural changes,
`DB::statement()` for named constraints and hand-named indexes.

### Verified baseline facts driving the DDL

| Fact | Source |
| --- | --- |
| FK names are Laravel defaults, both `ON DELETE CASCADE` | live `pg_constraint`: `replacement_requests_class_session_id_foreign`, `replacement_requests_replacement_time_slot_id_foreign`; declared at `2026_08_03_000009_create_replacement_requests_table.php:19,21` |
| Action CHECK **is named** `audit_logs_action_check` | live `pg_constraint`; declared via `DB::statement` at `2026_08_03_000010_create_audit_logs_table.php:31` — the earlier "name unknown" concern is resolved: fixed-name `DROP CONSTRAINT IF EXISTS` suffices, no `pg_constraint` introspection query needed |
| Only pre-existing secondary index on `replacement_requests` is `(status, submitted_at)` | migration line 30 |
| No application code deletes `time_slots`/`class_sessions` rows | grep over `app/**.php` for `->delete()` / `DELETE FROM`: zero hits — restrict is safe today |

### Ordered steps for `up()`

**(a) FK swap cascade → restrict on `replacement_requests` (P1.4/D3).**
`dropForeign(['col'])` resolves the Laravel-default name; re-add via `foreign()` on the
existing column — **no column drop/re-create needed**:

```php
Schema::table('replacement_requests', function (Blueprint $table): void {
    $table->dropForeign(['class_session_id']);
    $table->dropForeign(['replacement_time_slot_id']);

    $table->foreign('class_session_id')
        ->references('id')->on('class_sessions')->restrictOnDelete();
    $table->foreign('replacement_time_slot_id')
        ->references('id')->on('time_slots')->restrictOnDelete();
});
```

Re-added FKs regenerate the same default names, keeping `down()` symmetric.
`semester_id` and `proposer_id` stay `ON DELETE CASCADE` — deliberate exclusion per frozen
proposal. Documented consequence (in scope, accepted at review): `class_session_id` gets no
standalone index (the frozen scope contains no such item; the partial unique in step (d)
cannot serve FK enforcement because its `WHERE` clause excludes non-active statuses), so
session deletes keep paying a seq-scan on `replacement_requests`. Only `time_slots`
deletes/updates gain the indexed enforcement path via step (b).

**(b)+(c)+(e) Plain hot-path indexes (P0.1, P0.2, P2.5).**

```php
DB::statement('CREATE INDEX idx_replacement_requests_time_slot ON replacement_requests (replacement_time_slot_id)');
DB::statement('CREATE INDEX idx_replacement_requests_proposer_submitted ON replacement_requests (proposer_id, submitted_at)');
DB::statement('CREATE INDEX idx_audit_logs_time_slot ON audit_logs (time_slot_id)');
```

Justification anchors: P0.1 — under restrict, every `time_slots` delete/update must probe
`replacement_requests.replacement_time_slot_id`; plus future reverse lookups. P0.2 — real
queries filter `proposer_id` first: `app/Http/Controllers/Api/ApiReadController.php:138`
and `:378` (both then order/display by `submitted_at`). P2.5 — FR 4.12 audit joins from
`time_slot_id` (`audit_logs.time_slot_id` is nullable FK, Laravel-default NO ACTION,
`2026_08_03_000010_create_audit_logs_table.php:20`).

**(d) Partial unique enforcing D1 — LAST among `replacement_requests` DDL.**

```php
DB::statement("
    CREATE UNIQUE INDEX uq_replacement_requests_active_block
        ON replacement_requests (class_session_id, week_number)
        WHERE status IN ('pending', 'approved')
");
```

Semantics worth stating precisely: "active" = pending OR approved, so at most ONE row may
exist per block occurrence across both statuses. Two consequences, both intended by D1:
(i) a second INSERT with status pending/approved for the same `(class_session_id,
week_number)` violates; (ii) approving one request while another *pending* request exists
for the same occurrence violates on the UPDATE path. Rejected/cancelled/completed rows are
outside the predicate and never conflict. Precedent: `time_slots_no_double_book_idx`
(`2026_08_03_000006_create_time_slots_table.php:37-41`).

**(f)+(g) `cohorts.student_count` + named CHECK + convergence backfill (P2.6).**

```php
Schema::table('cohorts', function (Blueprint $table): void {
    $table->smallInteger('student_count')->nullable();
});

DB::statement('ALTER TABLE cohorts ADD CONSTRAINT cohorts_student_count_check CHECK (student_count > 0)');
DB::statement(
    'UPDATE cohorts c SET student_count = sub.cnt
     FROM (SELECT cohort_id, COUNT(*) AS cnt FROM students GROUP BY cohort_id) sub
     WHERE c.id = sub.cohort_id AND c.student_count IS NULL'
);
```

Decisions, with justification:

- **Signed `smallint`, not `unsignedSmallInteger`.** The task text specifies plain
  `smallint`; unsigned variants historically trigger grammar-generated `>= 0` checks whose
  auto-naming can collide with our manually named `cohorts_student_count_check` across
  Laravel versions. Signed + `CHECK (> 0)` is behaviourally identical here (capacity range
  fits far below 32767) and collision-proof. Live catalog shows current unsigned columns
  carry no auto checks, so this is defensive, not corrective.
- **Nullable, no NOT NULL.** In PostgreSQL a CHECK over NULL evaluates UNKNOWN → passes,
  so the constraint constrains only populated rows. NOT NULL is deliberately avoided:
  rows created outside the seeder path (unit-test fixtures, future admin tooling) may lack
  counts, and §3's fallback semantics require NULL to be representable. Forcing NOT NULL
  combined with `> 0` would additionally make zero-student cohort rows illegal.
- **Backfill ordering resolved (the fresh-vs-existing fork).** Under
  `migrate:fresh --seed`, migrations complete on an empty `cohorts` table first (the
  UPDATE is a no-op), then `DatabaseSeeder` writes authoritative `STUDENT_COUNTS` values
  at cohort-creation time — so the seeder stays the single source of truth for demo
  reproducibility, and no circular migration↔seeder dependency exists. Under plain
  `migrate` on an already-populated database, the UPDATE converges `student_count` to the
  live `COUNT(students)` per cohort, so no manual backfill step is ever required. The
   migration copy is a convergence fallback, never authoritative over the seeder.
- **Loud-fail precondition (up, step g):** on a populated DB where some cohort has ZERO
  student rows, the convergence UPDATE writes `0`, the just-added `CHECK (> 0)` rejects it,
  and the transactional migration aborts. Fresh-seed path unaffected (all 14 seeded
  cohorts have ≥10 students). Inherent to the frozen `CHECK(>0)` decision; stated here as
  an operator precondition, mirroring the rollback hazard in §2.

**(h) `audit_logs.action` CHECK widened to seven values (P2.7 / FR 2.16).**

```php
DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
    CHECK (action IN ('submitted', 'approved', 'rejected', 'cancelled', 'completed', 'occ_conflict', 'class_cancelled'))");
```

Same constraint name is restored, so the pair is re-runnable and `down()`-symmetric.
`IF EXISTS` makes the drop tolerant of environments where the constraint was already
dropped/recreated out-of-band; the name itself is verified, not guessed.

---

## 2. `down()` reversal

Exact reverse order of §1. This migration uses `Schema::table` throughout — it never drops
tables, so the original create-migrations remain the owners of the objects.

```php
public function down(): void
{
    // (h) reverted — HAZARD, see below
    DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
    DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
        CHECK (action IN ('submitted', 'approved', 'rejected', 'cancelled', 'completed', 'occ_conflict'))");

    // (g)+(f) reverted
    DB::statement('ALTER TABLE cohorts DROP CONSTRAINT IF EXISTS cohorts_student_count_check');
    Schema::table('cohorts', fn (Blueprint $table) => $table->dropColumn('student_count'));

    // (d) reverted
    DB::statement('DROP INDEX IF EXISTS uq_replacement_requests_active_block');

    // (e),(c),(b) reverted
    DB::statement('DROP INDEX IF EXISTS idx_audit_logs_time_slot');
    DB::statement('DROP INDEX IF EXISTS idx_replacement_requests_proposer_submitted');
    DB::statement('DROP INDEX IF EXISTS idx_replacement_requests_time_slot');

    // (a) reverted — original CASCADE semantics restored, default names regenerated
    Schema::table('replacement_requests', function (Blueprint $table): void {
        $table->dropForeign(['class_session_id']);
        $table->dropForeign(['replacement_time_slot_id']);

        $table->foreign('class_session_id')
            ->references('id')->on('class_sessions')->cascadeOnDelete();
        $table->foreign('replacement_time_slot_id')
            ->references('id')->on('time_slots')->cascadeOnDelete();
    });
}
```

**Hazard note (also recorded in proposal Risks):** re-adding the original six-value
`audit_logs_action_check` fails with a check-violation error the moment any
`action = 'class_cancelled'` row exists. Assumption, true at freeze time (grep clean):
**zero `class_cancelled` audit rows exist pre-feature.** After real production use, the
operator must reclassify or purge those rows before `migrate:rollback --step=1`; this is
documented here and in the rollback plan rather than handled programmatically (silently
deleting audit history in a migration is worse than a loud failure).

---

## 3. Engine change — `requiredHeadcount()`

Target: `app/Services/MatrixIntersectionEngine.php:292-297`. Current implementation counts
mutable `Student` rows live:

```php
return Student::query()
    ->whereIn('cohort_id', $cohortIds)
    ->count();
```

Replacement (add `use App\Models\Cohort;` beside the existing imports at lines 5-8;
`Student` stays imported for the fallback):

```php
/**
 * Sum of students across the target cohorts (multi-cohort sessions share one room).
 * Reads the denormalized cohorts.student_count when populated; falls back to a live
 * Student count for cohorts predating the column.
 *
 * @param  array<int, int>  $cohortIds
 */
private function requiredHeadcount(array $cohortIds): int
{
    if ($cohortIds === []) {
        return 0;
    }

    $counts = Cohort::query()
        ->whereIn('id', $cohortIds)
        ->pluck('student_count', 'id');

    if ($counts->contains(fn ($count): bool => $count === null)) {
        return (int) Student::query()->whereIn('cohort_id', $cohortIds)->count();
    }

    return (int) $counts->sum();
}
```

Behaviour mapping: empty input → 0 (identical to old `whereIn([])->count()`); any NULL
count in the set → whole-set legacy fallback (one extra query, only in the unpopulated
case); otherwise pure `cohorts` read. **Fallback kept, not dropped**, because dropping it
would make any cohort row with NULL `student_count` report headcount 0 — and 0 passes
every venue-capacity check in `eligibleVenues()` (lines 306-320), silently assigning a
tiny room to a large cohort. The failure mode of the fallback is one extra cheap query;
the failure mode of dropping it is an unsafe venue allocation. Single-query cost profile:
one `WHERE id IN (...)` on the PK instead of the previous aggregate over `students`.

No other engine logic changes — frozen scope ends at the headcount source.

---

## 4. Seeder change — `DatabaseSeeder`

Two touch points, nothing else:

1. **`app/Models/Cohort.php:20`** — add `'student_count'` to `protected $fillable` (and a
   `@property int|null $student_count` docblock line) so the mass assignment below is not
   silently discarded.

2. **`database/seeders/DatabaseSeeder.php:87-96`** — the existing `foreach` over
   `$cohortsData` gains one array key. Insertion point is inside the `Cohort::create([...])`
   payload, after `'intake'`:

```php
foreach ($cohortsData as $c) {
    Cohort::create([
        'programme_id' => $c['programme']->id,
        'current_year' => $c['year'],
        'semester' => $c['sem'],
        'tutorial_group' => $c['group'],
        'academic_year' => $c['academic_year'],
        'intake' => $c['intake'],
        'student_count' => self::STUDENT_COUNTS[sprintf(
            '%s%d(S%d)G%d',
            $c['programme']->programme_code,
            $c['year'],
            $c['sem'],
            $c['group']
        )] ?? 10,
    ]);
}
```

The `?? 10` fallback intentionally mirrors `seedStudents()` line 152 so the column and the
actually generated student population can never diverge on unknown codes. All 14
`$cohortsData` entries have a matching `STUDENT_COUNTS` key today (verified 1:1 against
lines 22-37), so the fallback is dormant.

**Guardrail (per review-log requirement):** `seedUsers()`/`seedLecturers()`
(lines 105-143) and `seedStudents()` (lines 145-180) are untouched — the edit appends one
key to one `Cohort::create` payload; generated `users`/`students`/`lecturers` rows remain
byte-identical (same names, emails, hashes, IDs, counters). `phase1-db-schema` frozen
tables are respected; only the new `cohorts.student_count` column receives writes.

---

## 5. Fixture/test updates

### Audit results

| File | Finding |
| --- | --- |
| `tests/Unit/OCCValidatorTest.php:23-115` | `seedData()` creates exactly ONE `ReplacementRequest` per test with explicit `class_session_id` + `week_number` (lines 105-108); `RefreshDatabase` isolates tests. No intra-suite tuple collisions against the partial unique; **no fixture edits required**. Status transitions performed by `validateAndReserve()` operate on the single row, so even pending→approved updates cannot self-collide. |
| `tests/Unit/MatrixIntersectionEngineTest.php:67-83,122-129,161-176,574-593` | Cohorts are created WITHOUT `student_count`; `requiredHeadcount` would take the fallback path everywhere. Existing assertions would still pass, but the suite would never exercise the new fast path. Updates specified below. |
| `tests/Feature/MatrixIntersectionEngineTest.php:18-122` | Runs full `DatabaseSeeder`; after §4 every cohort carries `student_count` equal to its generated student population (same constant drives both), so pinned-window/capacity expectations (B002, headcount 28 for DFT2(S1)G1) are numerically unchanged. **No edits required**; green run is the confirmation. |

### Unit `MatrixIntersectionEngineTest` updates (exercise the fast path while preserving the invariant)

1. `setUp()` — add `'student_count' => 1` to both `Cohort::create` calls (lines 67-83),
   matching the 1 student per cohort seeded at lines 122-129.
2. `seedStudents()` helper (lines 161-176) — after the insert loop, bump the column once:

```php
DB::table('cohorts')->where('id', $cohortId)->increment('student_count', $count);
```

   With this invariant, `test_s6_capacity_multi_cohort_sum` (expects 45 = 1+24 + 1+19) and
   `test_s6_capacity_re_evaluated_single_cohort` (expects 25) pass through the FAST path,
   proving the switch end-to-end at unit level.
3. New test proving P2.6's core intent (decoupling from mutable student rows):

```php
public function test_required_headcount_reads_denormalized_cohort_counts(): void
{
    DB::table('cohorts')->where('id', $this->cohortOneId)->update(['student_count' => 24]);
    DB::table('cohorts')->where('id', $this->cohortTwoId)->update(['student_count' => 19]);
    DB::table('students')->whereIn('cohort_id', [$this->cohortOneId, $this->cohortTwoId])->delete();

    $this->assertSame(43, $this->invokePrivate('requiredHeadcount', [$this->cohortOneId, $this->cohortTwoId]));
}
```

### New schema-integrity tests

New file `tests/Unit/DbIntegrityConstraintsTest.php` (`RefreshDatabase`), four tests —
these map 1:1 onto Success Criteria 2-4:

```php
// 1. D1 unique guard (SC #2): second active request for same block occurrence fails
$this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
// seed one pending request (factory, week_number 1), then repeat the identical INSERT
// via DB::table('replacement_requests')->insert(...) with same class_session_id/week_number.

// 2. Restrict FK (SC #3): deleting a referenced time_slot raises FK error (SQLSTATE 23503)
$this->expectException(\Illuminate\Database\QueryException::class);
DB::table('time_slots')->where('id', $slotId)->delete();
// plus a sibling assertion deleting the referenced class_sessions row.

// 3. Deliberate-exclusion sanity: proposer user delete STILL cascades (P1.4/D3 scope note)
DB::table('users')->where('id', $proposerId)->delete();
$this->assertDatabaseMissing('replacement_requests', ['id' => $requestId]);

// 4. class_cancelled audit insert succeeds (SC #4)
DB::table('audit_logs')->insert([... 'action' => 'class_cancelled' ...]);
$this->assertDatabaseHas('audit_logs', ['action' => 'class_cancelled']);
```

Fixtures reuse the lightweight seeding pattern already established in
`OCCValidatorTest::seedData()` (raw `DB::table` inserts + factories); no production code
is touched by these tests.

---

## 6. Verification battery — Success Criterion → probe

| # | Criterion (proposal) | Exact command / probe |
| --- | --- | --- |
| 1 | `replacement_time_slot_id` lookup → Index Scan | Seed a request, then: `PGPASSWORD=secret psql -h 127.0.0.1 -U philler -d class_replacement -c "EXPLAIN SELECT * FROM replacement_requests WHERE replacement_time_slot_id = 1;"` → plan must show `Index Scan using idx_replacement_requests_time_slot`. Cross-check `\d replacement_requests` lists all three new indexes. |
| 2 | Duplicate active-block INSERT fails | Automated: new `DbIntegrityConstraintsTest` test 1. Manual probe: same double `INSERT` via psql → `ERROR: duplicate key value violates unique constraint "uq_replacement_requests_active_block"`. |
| 3 | Referenced `time_slots`/`class_sessions` delete raises FK error | Automated: test 2. Manual: `DELETE FROM time_slots WHERE id = <referenced>;` → `violates foreign key constraint "replacement_requests_replacement_time_slot_id_foreign"` (SQLSTATE 23503), row survives. |
| 4 | `action='class_cancelled'` audit insert succeeds | Automated: test 4. Manual: single-row INSERT via psql, expect `INSERT 0 1`; negative control: `'bogus_action'` → check-constraint error. |
| 5 | Headcount reads `cohorts.student_count` | New unit test `test_required_headcount_reads_denormalized_cohort_counts` (student rows deleted, counts survive) + updated s6 tests passing through the fast path. |
| 6 | `migrate:fresh --seed` + `rollback --step=1` clean | `php artisan migrate:fresh --seed && php artisan migrate:rollback --step=1 && php artisan migrate` — all three exit 0; then re-run `migrate:fresh --seed` to restore the demo dataset. Spot-check: `SELECT conname FROM pg_constraint WHERE conrelid='audit_logs'::regclass AND conname='audit_logs_action_check';` returns the six-value definition after rollback. |
| 7 | `composer run lint:check` | Run verbatim (Pint `--parallel --test`). phpstan (`composer run types:check`) stays out of battery — blocked on PHP 8.5 per frozen proposal; compensate with manual line-by-line diff review. |
| 8 | `php artisan test` green | Full suite including the three touched/new test files. |

---

## 7. Documentation update

Update `CodingMAIN.md` §5 (pending-schema list): mark the optimization deltas as applied —
restrict FKs on `replacement_requests.class_session_id`/`replacement_time_slot_id`,
partial unique active-block guard, new indexes, `cohorts.student_count` column, widened
`audit_logs.action` enum. Committed deliverable of this change; verified by tasks.md task.

---

## 8. Open questions

None.
