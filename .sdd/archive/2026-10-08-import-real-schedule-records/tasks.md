# Tasks — import-real-schedule-records

Batch 4. Baselines: frozen proposal / design / specs. Each task ≤ 2 h.
Gates after every code task: `php vendor/phpunit/phpunit/phpunit --no-coverage`,
`vendor/bin/phpstan analyse --memory-limit=1G --no-progress`,
`composer run lint:check`. Standing prohibitions: NO `migrate:fresh --seed` on
`class_replacement`; NO `pkill -9 php` (use isolated `pkill -f "[a]rtisan serve"`).

## Phase A — dataset (specs/real-schedule-importer R1.5)

- [x] **T1 — dataset/import/ copies + helpers.** Create `dataset/import/`;
      copy the 3 in-scope CSVs byte-identical (`cmp` verified) from
      `/home/jinglinux/tarumt/CRS/past sem pdf/Schedule ds/dataset csv md/`;
      port `course_titles.py` → `dataset/import/course-titles.php` (22 entries,
      provenance header); port `lecturer_ids.py` →
      `dataset/import/lecturer-ids.php` (14 entries). No out-of-scope copies.
- [x] **T2 — KNOWLEDGE.md copy + DATASET-NOTES.md.** Copy KNOWLEDGE.md verbatim
      into `dataset/`; append the declarative addendum (design §9: counts
      155/101/101 correction, single double-booking, stale MPU34W2 remark,
      3-row holiday DB mapping, is_pl note 5425+5516). Write
      `dataset/DATASET-NOTES.md` with the five pinned contents (design §2/§5,
      per-module observed unions incl. AMIT2014→L,P, MPU-2212→T).

## Phase B — importer core (specs/real-schedule-importer R2–R8)

- [x] **T3a — RealScheduleSeeder skeleton + CSV parsing.** New
      `database/seeders/RealScheduleSeeder.php`: CSV parsing (R1.2–R1.3) with
      loud aborts, zero writes on failure.
- [x] **T3b — Phase 0 preflight assertions + warnings.** All R2.1–R2.5
      assertions; R2.6 lab-rule (11 rows) + missing-title warnings; R2.7
      abort-with-zero-writes semantics.
- [x] **T4 — Phase 1 delete + Phase 2 references.** Live-count-asserted delete
      order (R4.1–R4.3, single transaction); semesters dates, holidays replace
      (3 rows), module upsert with observed-union allowed_session_types (R5).
- [x] **T5 — Phase 3 sessions/links/prune/occupancy.** 101 inserts, 155 links,
      module prune (R6.3), per-session exact-count occupancy with holiday-pair
      exclusion (R6.4). Transaction shared with Phase 2.
- [x] **T6 — Phase 4 verification pass.** All R7.1 checks incl. explicit
      GROUP BY double-booking query; final `crs:db-row-counts` print (R7.2 —
      call wired here; only exercised once T8 lands); R7.4 count/state-level
      scope.
- [x] **T7 — ImportRealScheduleCommand.** `crs:import-real-schedule {--verify}`;
      mode gate per R3.1–R3.5 (command owns the gate; seeder core ungated);
      exit codes per R8.

## Phase C — snapshot gate + chain rewiring

- [x] **T8 — DbRowCountsCommand (build BEFORE T6's R7.2 call is exercised).**
      `crs:db-row-counts`: information_schema enumeration (alphabetical), TSV
      out, non-zero exit on failure (specs/records-intact-gate R1–R2).
- [x] **T9 — DatabaseSeeder chain rewrite.** Order per seed spec R1.1
      (ModulesSeeder left as-is); rewrite `SemestersSeeder` (canonical dates),
      `HolidaysSeeder` (3 canonical rows); DELETE `ClassSessionsSeeder`,
      `ClassExceptionsSeeder`, `ReplacementRequestsSeeder`,
      `CsvTimetableSeeder`, `LecturerScheduleSeeder` (R1.2). Gate + a quick
      test-DB `migrate:fresh --seed` sanity run (tests DB only) proving R1.4
      end-state counts.
- [x] **T10 — Doc generator port.** Rewrite
      `dataset/generate-timetable-doc.php` to plain DB queries (R4.1); keep
      §4.1 format/day-grouping contract. **Regeneration target: the test DB
      freshly seeded in T9** (the only real-data DB pre-T16) — NOT the demo DB
      which still holds hand-made sessions at this point; that regenerated
      doc is the standing committed copy (content-identical to a
      post-T16-demo regeneration if the import is correct; T16 captures the
      post-demo regeneration as evidence). Grep-verify zero references to
      deleted seeders (R4.2).

## Phase D — tests (specs/seed-invariants-rework R2–R3)

- [x] **T11a — TimetableSeedInvariantsTest re-pin (T1–T4).** Constants
      (101/155/3963/14); T1 dataset-derived occupancy; T2 one-tuple cohort
      whitelist; T4 MPU sets from programme CSV.
- [x] **T11b — TimetableSeedInvariantsTest T5 rework + new T6/T7.** T5
      timetable.md parity (101 rows, subsumes position-id re-anchoring);
      T6 (dates/holidays/accounts); T7 (`--verify` exits 0 on fresh seeded
      test DB).
- [x] **T12 — MatrixIntersectionEngineTest re-pin.** Derive a green window for
      4288/DFT2(S1)G1 from the dataset (derivation commented); holiday
      assertion on canonical pairs; chosen week documented (R3.1).
- [x] **T13 — Untouched-test verification.** OCCValidatorTest,
      TimetableWiringTest, NavIdentityTest pass without fixture edits; fix
      only the seed chain if it breaks them (R3.2).
- [x] **T14 — MANUAL-TEST-CASES.md update.** my-request-history step 2 →
      real-data expectation (R5.1).

## Phase E — gates, execution, smoke, docs

- [x] **T15 — Full gate run + fixes.** phpunit (record new count),
      phpstan 0, lint:check; fix any fallout.
- [x] **T16 — Demo DB execution (operator script, design §8).**
      `crs:db-row-counts` before-block → fresh `pg_dump -Fc` →
      `backups/class_replacement-pre-import2-<date>.dump` → import run →
      after-block → verify-mode re-run passes (idempotency proof). Record
      everything in execution notes.
- [x] **T17 — Live smoke.** Safe dev-server restart (isolated pkill pattern +
      **clear compiled views** `rm -f storage/framework/views/*.php`);
      login 5425 → identity + own real sessions; 25RSD0001 → cohort sessions;
      Playwright `tests/nav-identity.spec.ts`.
- [x] **T18 — Changelogs + wrap-up.** `page-changelogs/backend-automated-by-ai.md`
      (new standing counts + records-intact gate) + `page-changelogs/my-timetable-changelog.md`
      (real-data primary domain log); execution notes finalized; `/sdd-verify`;
      conventional commits. **NO push — report and wait for explicit
      authorization.**

## Acceptance (change-level)

- All specs' requirements demonstrably met; acceptance criteria of frozen
  proposal §4 satisfied; snapshot before/after pair stored; importer re-run
  proof captured; all gates green with counts recorded honestly.
