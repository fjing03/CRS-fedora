# Review Log — import-real-schedule-records

## proposal Round 1 — 2026-10-08

Reviewer: @sdd-reviewer (no shell; spot-checked migrations 000004/6/7,
DatabaseSeeder, ClassSessionsSeeder, invariants test head, KNOWLEDGE.md §6/§11,
mock-data.js §2.2). Baseline: explore-brief.md.

### 🔴 Outstanding
- (none — batch PASSES, proposal frozen)

### 🟡 Addressed
1. "Exception-list" mechanism undefined; tension with criterion 1
   (class_exceptions=0). → Resolved: the exception is **docs +
   importer/test allowance only, never DB rows** — carried as a REQUIRED
   statement into design.md. (Declarative note added to proposal §3.)
2. Brief §9 design deferrals invisible in proposal (modules
   allowed_session_types strategy; prune/keep of ~34 unreferenced hand-made
   modules). → Declarative line added to proposal §2 so the design decision is
   visible as sanctioned, not scope creep.
3. Criterion 2 "recorded honestly" not verifiable. → Reworded to "phpunit green
   with EXPECTED_* constants matching the importer's post-run counts (diff in
   execution notes)".
4. Delete-phase FK ordering hazard missing from risks. → Added risk row:
   time_slots.class_session_id FK has no cascade → clear occupancy links →
   session_cohorts → sessions, single transaction.
5. Lab-block accounting (17 = 8 allowed + 9 violations + AMIT2034@B010
   room-restricted) implicit. → DATASET-NOTES.md (scope item 1) must pin the
   exact 17-block partition derived from venue MD §255–285. Design input.

### Optional suggestions accepted
- Criterion 1 now shows the occupied-count derivation inputs
  (147 h × 2 × 14 = 4116 − 153 holiday-affected = 3963; 153 re-derived
  programmatically 2026-10-08: Mon 62 + Thu 54 + Fri 37 slots).
- Snapshot gate location: execution notes + a short repo doc pointer — design
  decides the exact home.

## design Round 1 — 2026-10-08

Reviewer: @sdd-reviewer (spot-checked TimeSlotsSeeder, migrations 000006/000007,
ClassSessionsSeeder, DatabaseSeeder, CsvTimetableSeeder, bootstrap/app.php,
tests inventory, dataset/ contents, archived Wave-2).

### 🔴 Fixed
- MatrixIntersectionEngineTest missing from test-impact inventory — it IS
  pinned to hand-made data (4288/DFT2 week 5, pinned Tue 10:00–11:00 B002
  window, old W5-Wed holiday assertion). Verified first-hand. → Added to §6
  inventory with re-pin strategy (real-data window + canonical holiday pairs).
  OCCValidatorTest also inventoried (generic slot pick — verify-only).
- D1 shared core vs hardcoded demo-baseline delete counts (15/3/5/44) would
  throw on fresh test DBs. → §3.3 rule added: delete-phase assertions computed
  from live pre-delete counts; per-session occupancy stays exact (dataset-
  derived, identical on both paths). Dangling "Phase 5" refs fixed.

### 🟡 Fixed (folded into round-2 design)
- dataset/ pre-existing files ignored; D7 breaks generate-timetable-doc.php →
  D10: generator PORTED to plain DB queries, timetable.md regenerated, T5
  keeps parsing it (doc←DB authority rule preserved).
- Preflight window vs grid bounds → §3.2.1 asserts 08:00–17:30 grid bounds
  (dataset earliest start 09:00 per recheck A2, stated).
- Numeric slips → §3.2.6/§5: warning list = 11 rows (9 + 2 AMIT2034@B010);
  warn home = Phase 0; §2 clarifies 22 title entries / 20 apply / 22 missing;
  26 tables confirmed first-hand via psql and D11 makes the count self-proving.
- D5 prune had no phase home → §3.5.3: inside shared core after inserts;
  verify asserts modules == 42.
- §6 stale "tests call the importer command" sentence → tests seed via the
  DatabaseSeeder chain (core inside); only T7's explicit --verify call runs
  the command post-chain.
- MANUAL-TEST-CASES.md:82 knock-on → §8 step 8 adds the doc update task.
- Wave-2 Q5 (LecturerScheduleSeeder) resolution recorded in D7.
- Optional suggestion accepted: T7 runs --verify against a fresh test DB in CI.

### 🔴 Outstanding
- (none pending in this log entry — round 2 review follows)

## design Round 2 — 2026-10-08

Reviewer: @sdd-reviewer. Verified all round-1 fixes; verdict PASS.

### 🔴 Fixed
- (none outstanding — batch PASSES, design frozen)

### 🟡 Addressed
- §3.5.3 prune example was arithmetically muddled (34 vs 36, "36→42" upward) —
  corrected: the 36 hand-made modules all become unreferenced; 42 real codes
  upserted first; table ends at 42; assertion stays live-count based.
- Mode-gate fingerprint cross-ref §3.6 → §3.2 (cosmetic, applied).

### Status
- design.md FROZEN. Next: specs/ (Batch 3), tasks.md (Batch 4).

## specs Round 1 — 2026-10-08

Reviewer: @sdd-reviewer. Traceability verified requirement-by-requirement
against frozen design/proposal/brief. Verdict: PASS conditional on folding
7 🟡 items before tasks.md.

### 🔴 Outstanding
- (none — batch PASSES, specs frozen after folds below)

### 🟡 Fixed (folded)
1. R6.4 SQL drifted from design §3.5.4 (missing semester_id) → aligned, SET
   clause stated; R4.1 gained updated_at=now().
2. "Dataset fingerprint" undefined → pinned: R2.1 assertions pass, NO file
   hashing (R3.1).
3. Mode-gate placement → R3.5: gate lives in the command; RealScheduleSeeder
   invokes the core ungated from the chain.
4. ModulesSeeder ambiguity → R1.1 (seed spec): left as-is seeding 36 reference
   rows; core upserts 42 real codes; prune removes unreferenced → 42.
5. Dataset-copy fidelity had no spec home → importer R1.5 (byte-identical, no
   out-of-scope copies, KNOWLEDGE+DATASET-NOTES contents; lands in tasks.md).
6. T5 position-id re-anchoring orphaned → seed spec R2.5 states subsumption by
   doc↔DB parity (no id anchors remain).
7. Verify depth overpromise → importer R7.4: count/state-level only; content
   parity = T5's doc↔DB check.
Optional folds: gate R1.2 alphabetical (testable); R2.4 cites cohortCode()
provenance.

### Status
- specs/ FROZEN. Next: tasks.md (Batch 4).

## tasks Round 1 — 2026-10-08

Reviewer: @sdd-reviewer (full R-number walk, design §6/§8 walks, proposal §2/§4
walk). Verdict: PASS conditional on 4 🟡 folds.

### 🔴 Outstanding
- (none — batch PASSES, tasks.md frozen after folds below)

### 🟡 Fixed (folded)
1. T10 ran before a real-data DB exists / target ambiguous → regenerate
   against the T9-fresh test DB; committed doc is the standing copy; T16
   captures post-demo regeneration as evidence.
2. T6/T8 dependency inversion → T6 notes the R7.2 call is wired-but-exercised
   after T8; T8 marked "build BEFORE T6's R7.2 call is exercised".
3. 2 h budget overruns → T3 split into T3a (skeleton+parsing) / T3b (assertions
   + warnings); T11 split into T11a (T1–T4 re-pin) / T11b (T5+T6/T7).
4. T17 omitted compiled-view clear → added `rm -f storage/framework/views/*.php`
   to the restart clause.

### Status
- tasks.md FROZEN. All four batches frozen. APPLY may begin (Phase A: T1, T2).

## verify — 2026-10-08 (post-apply)

Implementation checked against frozen proposal/design/specs/tasks:

- [x] Proposal §4 criteria 1–5 all met (evidence: execution/execution-notes.md,
      execution/rowcounts-{before,after}.txt; verify-mode re-run passed)
- [x] Design D1–D11 implemented as written (RealScheduleSeeder core + command
      gate, re-link-not-rebuild, 2-transaction split, observed-union types,
      prune→42, venue-truth cross-assert, chain rewiring + Q5 resolved, holiday
      rule 3963, code-fallback titles, doc-generator port, information_schema gate)
- [x] Spec requirements R1–R8 traceable to code (RealScheduleSeeder/ImportRealScheduleCommand/DbRowCountsCommand)
- [x] Test rework landed: invariants T1–T7 green, Matrix re-pinned green,
      OCC/Wiring/NavIdentity untouched and green — suite 115/115 (809)
- [x] Gates: phpunit 115/115 (809), phpstan 0, lint adminer-only baseline
- [x] Demo DB executed with fresh backup; snapshots + idempotency proof stored
- [x] Changelogs: backend-automated-by-ai.md (primary), my-timetable-changelog.md (domain)
- [ ] push — deliberately withheld (user instruction: stop and wait)
- [ ] archive — awaiting user go-ahead

Verdict: implementation matches frozen artifacts; change COMPLETE except the
explicitly withheld push (and archive, pending user).
