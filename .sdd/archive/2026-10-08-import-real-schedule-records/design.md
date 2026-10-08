# Design — import-real-schedule-records

Batch 2. Baselines: frozen `explore-brief.md` + frozen `proposal.md`.
Status: **DRAFT** — round 2 (round 1: 2 🔴 fixed, 6 🟡 folded in; all
reviewer claims verified first-hand — incl. MatrixIntersectionEngineTest
pins, OCCValidatorTest `$this->seed()`, `dataset/timetable.md` being T5's
parity fixture, live table count 26 via psql).

---

## 1. Deliverable form

| Artifact | Path | Role |
|---|---|---|
| Importer | `app/Console/Commands/ImportRealScheduleCommand.php` | `php artisan crs:import-real-schedule {--verify}` — the only writer of real records on the demo DB |
| Shared core | `database/seeders/RealScheduleSeeder.php` | the importer's entire write+verify logic, invokable from the command AND from `DatabaseSeeder` (D1) |
| Snapshot gate | `app/Console/Commands/DbRowCountsCommand.php` | `php artisan crs:db-row-counts` — prints every public table's row count as TSV; the standing "records-intact" output |
| Doc generator (ported) | `dataset/generate-timetable-doc.php` | regenerated-doc authority rule kept: DB → `dataset/timetable.md`; ported off the deleted `ClassSessionsSeeder` (D10) |

`app/Console/Commands` is auto-discovered (Laravel 12); no kernel edits.
**No new composer dependencies.**

## 2. `dataset/` layout (in-repo source + docs)

The directory ALREADY exists with committed docs (Wave-2): `cohorts.md`,
`lecturers.md`, `timetable.md`, `generate-timetable-doc.php`, ERDs,
`schema_*.md/sql`, `fr43_findings.md`, `erd_schema_review_reply.md`. Those
stay; this change adds to and ports within it:

```
dataset/
├── (existing docs unchanged except timetable.md regenerated post-import)
├── import/                          # NEW subdir — durable source data
│   ├── lecturers-schedules-202505.csv    # verbatim in-scope CSV copies
│   ├── venues-schedules-202505.csv
│   ├── programmes-schedules-202505.csv
│   ├── course-titles.php            # port of course_titles.py — 22 title entries
│   └── lecturer-ids.php             # port of lecturer_ids.py — 14 staff ids
├── KNOWLEDGE.md                     # verbatim copy + declarative addendum (§9)
└── DATASET-NOTES.md                 # NEW — 2026-10-08 corrections + lab partition (§5)
```

- CSVs copied **byte-identical**; out-of-scope CSVs NOT copied (never
  imported).
- The PHP helpers are hand-ports of the .py single sources — same keys/values,
  provenance header. `course-titles.php` carries 22 title entries; **20 apply
  to the dataset's 42 codes; 22 dataset codes have no title** (coincidence of
  numbers — see D9).
- `generate-timetable-doc.php` is **ported, not deleted** (D10): it currently
  reflects `ClassSessionsSeeder::getSessionTemplates()` (dies under D7). New
  implementation: plain DB queries over `class_sessions` ⋈ modules/users/
  venues/session_cohorts, rendering the same §4.1 format into
  `dataset/timetable.md`. Authority rule preserved: **doc regenerates from
  DB, never hand-edited** — and T5 keeps parsing it (§6).
- `DATASET-NOTES.md` pins: (i) true counts 155/101/101 vs the stale recheck
  headline (184/121/101 describes the pre-14:14 regeneration), (ii) the single
  surviving double-booking, (iii) the 17-block lab partition (§5), (iv) the
  canonical holiday list, (v) per-module observed session-type unions (D4).

## 3. Importer design

### 3.1 Modes & state detection (idempotency)

Preflight reads live counts and **fails loudly** on anything unexpected:

| class_sessions | state | mode |
|---|---|---|
| 0 (fresh test DB via `migrate:fresh --seed`) | reference-only | **import** (additive path) |
| 35 (demo baseline: hand-made sessions present) | pre-import | **import** (replace path) |
| 101 + dataset fingerprint match (§3.2) | imported | **verify-only** (all checks, no writes, exit 0) |
| anything else | — | **abort** with guidance |

`--verify` forces verify mode (aborts on non-imported state). Re-running
import mode on imported state is deliberately unsupported — **verify mode IS
the idempotent re-run**. No blind re-insert ever happens.

### 3.2 Phase 0 — dataset preflight (all modes; before any write)

1. Parse 3 CSVs; assert row counts **101 / 101 / 155**; total venue hours 147;
   42 distinct course codes; every `session_type` ∈ {Lecture, Tutorial,
   Practical}; **times 30-min aligned and within the grid bounds 08:00–17:30**
   (the dataset's earliest start is 09:00 per recheck A2 — the assertion uses
   the wider grid bounds so valid data never aborts); day ∈ Mon–Sat.
2. Venue code = first token of the venue column (`B009 - Lab 1` → `B009`);
   assert all resolve to `venues.room_code` (23 expected).
3. Lecturer resolution: for every venue row, key `(day, start, end, course)`
   must match **exactly one** lecturer-CSV row, and that row's venue must equal
   the venue row's venue. (`lecturers.staff_id` → `users.id`; all 14 resolve.)
4. Cohort resolution: label `DFT2(S1)G1` = `programme_code + current_year +
   '(S' + semester + ')G' + tutorial_group` (character-for-character the
   `DatabaseSeeder::cohortCode()` derivation; cohorts table has no code
   column). All 14 labels resolve to cohort ids.
5. 3-way consistency: the multiset of `(cohort, day, start, end, course,
   venue)` from venue-CSV cohort lists == programme-CSV rows, element for
   element; cohort-instance totals 155 from every view. Mismatch = abort.
6. **Lab-rule warn (Phase 0 home, §4)**: emit the warning list of the **11
   L/T-in-lab rows kept as printed** (9 non-Networking + 2 AMIT2034@B010);
   titles missing from course-titles.php (the 22 codes) also reported here.
7. Course codes missing titles are seeded with `module_name` = the code itself
   (never invented).

### 3.3 Phase 1 — delete (import mode; single DB transaction)

**Assertion rule (reviewer 🔴-2): delete-phase expectations are computed from
live pre-delete counts, never hardcoded demo numbers** — the shared core runs
identically on the demo DB (35/44/15/3/5 present) and on a fresh test DB
(0/0/0/0/0 present). Each delete asserts *affected == count matching the
filter just before deleting*; occupancy clearing asserts *no slot still
references a session about to be deleted*.

FK-safe order inside `DB::transaction`:

1. `UPDATE time_slots SET class_session_id = NULL, status = 'available',
   version = 1, updated_at = now() WHERE class_session_id IN (SELECT id FROM
   class_sessions WHERE semester_id = ?)` — no-cascade FK: occupancy links
   MUST be cleared first; `version` reset returns the grid to clean OCC state.
2. Delete `class_exceptions` for those sessions (demo: 15).
3. Delete `replacement_requests` for those sessions (demo: 3). Explicit
   deletes (not cascade reliance) so snapshot evidence is unambiguous.
4. Delete the sessions themselves (`session_cohorts` rows go via
   cascadeOnDelete; post-delete assert `session_cohorts` has no orphans).

### 3.4 Phase 2 — reference updates (separate transaction)

Rationale: Phase 1 is destructive and self-contained; Phases 2–3 are additive
and share one transaction — a crash between them leaves an empty-but-valid
timetable that the mode gate refuses to double-import.

1. `semesters`: `start_date = '2026-09-21', end_date = '2026-12-27'` where
   `semester_code = '202605'` (code/label/week_count untouched — user
   decision (b)).
2. `holidays`: delete **all rows present for the semester** (live count; demo
   has 5 stale), then insert the 3 grid rows that fit the `day_of_week 0–5`
   CHECK: `(8, 0, 'Deepavali Holiday (In Lieu)')`, `(14, 3, 'Christmas Eve')`,
   `(14, 4, 'Christmas Day')`. Deepavali Sunday 8-Nov stays docs-only.
3. Modules: for each of the 42 codes — `updateOrInsert` on `module_code`;
   `module_name` from course-titles.php or code-fallback (reported in Phase 0);
   `allowed_session_types` = **observed-union** (D4), `'L,T,P'`-ordered subset.
   MPUs observed Tutorial-only get `'T'` — data-derived, not blanket.

### 3.5 Phase 3 — sessions, cohort links, occupancy (same transaction as Phase 2)

1. Insert **101 class_sessions** from the venue CSV (physical truth): resolved
   `semester_id`, `module_id`, `lecturer_id` (§3.2.3), `day_of_week`
   (Mon=0…Sat=5), `start_time`/`end_time` (HH:MM:00), `venue_id`,
   `session_type` (L/T/P).
2. Insert **155 session_cohorts** links from venue-CSV cohort lists (already
   proven == programme CSV in preflight).
3. **Prune (D5 — runs here, inside the shared core, after inserts)**: delete
   modules with zero `class_sessions` references (demo: the 36 hand-made
   modules all become unreferenced once sessions are deleted; the 42 real
   codes are upserted first, so the table ends at 42 regardless of the
   pre-import count). Live-count assertion: affected == count of unreferenced
   modules just before delete. Post: modules == 42 (asserted).
4. Occupancy per session — house pattern of `ClassSessionsSeeder`:

   ```php
   $expected = $slotCount * (14 - $holidayWeeksOnThisDay);
   $affected = UPDATE time_slots SET class_session_id = ?, status = 'occupied',
               updated_at = now()
               WHERE semester_id = ? AND week_number BETWEEN 1 AND 14
                 AND week_number NOT IN (holiday weeks for this day_of_week)
                 AND day_of_week = ? AND start_time >= ? AND start_time < ?
                 AND venue_id = ? AND status = 'available';
   if ($affected !== $expected) throw (loud, session-identifying message);
   ```

   Holiday grid pairs: `{(week 8, day 0), (week 14, day 3), (week 14, day 4)}`.
   Mon/Thu/Fri sessions occupy 13 weeks; others 14. `status='available'` in
   the WHERE makes re-occupancy impossible instead of silent. **These exact
   per-session counts stay exact in both modes** — they derive from the
   dataset, not from DB state, so the shared core passes them identically on
   demo and test DBs.

### 3.6 Phase 4 — verification pass (read-only, after commit)

- occupied slot rows == **3963** (4116 = 147 h × 2 × 14, minus 153
  holiday-affected: Mon 62 + Thu 54 + Fri 37);
- `time_slots` pointing at missing sessions: **0**; non-`available` slots
  with NULL `class_session_id`: **0**;
- double-booked venue slots: **0** (partial unique index backstops; explicit
  GROUP BY check for the error message);
- session_cohorts == 155, distinct cohort coverage == 14, modules == 42;
- holiday rows == 3 and their (week, day, venue, time) slots all `available`;
- semesters dates == 2026-09-21 / 2026-12-27.
- Print the `crs:db-row-counts` snapshot (after) — the run log holds before
  (operator pre-run) and after together.

Failure after commit = loud error + restore instruction (fresh backup; no
auto-rollback complexity).

## 4. Lab L/T exception — mechanism (reviewer round-1 🟡-1 resolution)

**No DB rows.** The exception is exactly three things:

1. `DATASET-NOTES.md` documents the partition (§5);
2. the importer does **not** enforce `venues.allowed_session_types` during
   import (it imports printed source data) and **warns** the 11 rows (§3.2.6);
3. the future *manual booking* guard keeps using
   `venues.allowed_session_types` unchanged — this change touches no booking
   code, so no test needs a pass-through.

## 5. The 17-block lab partition (venue MD §255–285 → DATASET-NOTES.md)

- **8 subject-allowed (Networking/IoT)**: AMIT2033 ×2, BMIT2154 ×2, BMIT3084 ×2
  in B006; AMIT2034 ×2 in **B010** ← room-level exception (spec §3 names only
  B006).
- **9 kept as printed (non-Networking L/T in labs)**: BMIS2003 L@B006,
  AMCS1013 T@B006, BMIT2013 L@B009, AMSE1003 T@B009, BMIT1173 T@B009,
  AMIS1003 T@B010, AMCS1043 T@B011, BMIT1723 L@B011, BMCS3033 T@B011.
- Warning list = 9 + 2 = **11 rows** (AMIT2034 counted per-row).
- Rationale: the PDFs are the read-only source of truth; re-homing invents
  room assignments. `class_exceptions` stays **0** (user decision).

## 6. Test rework

### Seed-consumer inventory (reviewer 🔴-1 rule)

Every test that seeds the full demo data is inventoried and gets a decided
strategy BEFORE apply:

| Test | Seeds via | Pinned to hand-made data? | Strategy |
|---|---|---|---|
| `TimetableSeedInvariantsTest` | `$this->seed()` | YES (35/44/1988, ids 1..35, timetable.md parity) | **rework** (below) |
| `MatrixIntersectionEngineTest` | `$this->seed()` | YES — lecturer 4288 + DFT2(S1)G1 week 5, pinned window Tue 10:00–11:00 B002, holiday-day-2 assertion (old W5-Wed fixture) | **re-pin to real data**: derive a new pinned green window for 4288 / DFT2(S1)G1 from `dataset/` + the new holiday set; holiday assertion switches to the canonical pairs (no window on W8 Mon / W14 Thu / W14 Fri); week choice = any week with no cohort clash for that pair (design-computed at implementation, hardcoded as a commented derivation) |
| `OCCValidatorTest` | `$this->seed()` | NO — takes an occupied slot generically, asserts OCC version semantics | **verify green, fix only if pinned** (occupied slots still exist: 3963) |
| `TimetableWiringTest` | own fixtures | NO | must stay green untouched |
| `NavIdentityTest` | `$this->seed(DatabaseSeeder::class)` | identity/nav only | must stay green untouched |

### TimetableSeedInvariantsTest (rework in place; names T1–T5 kept, T6–T7 added)

| Constant | old | new |
|---|---|---|
| EXPECTED_SESSIONS | 35 | **101** |
| EXPECTED_SESSION_COHORTS | 44 | **155** |
| EXPECTED_OCCUPIED_SLOTS | 1988 | **3963** |
| EXPECTED_COHORTS | 14 | 14 (unchanged) |

- **T1 no-orphans/exact-occupancy**: per-session expected occupancy derives
  from `dataset/import/` CSVs (slotCount × weeks-minus-holidays), not magic
  numbers.
- **T2 overlaps**: venue & lecturer zero-tolerance; **cohort-overlap whitelist
  of exactly one tuple** — DFT2(S1)G1 Wed 11:00–12:00 AMIT2014 × AMIT2034
  (user decision (c)); asserts violation set == whitelist.
- **T3 all-cohorts-covered**: unchanged logic.
- **T4 MPU cohort sets**: MPU-* cohort sets must equal the programme-CSV sets
  (derived at implementation from `dataset/import/programmes-…csv`).
- **T5 doc-DB parity**: keeps parsing **`dataset/timetable.md`** (regenerated
  by the ported generator from the REAL DB — D10; parser/format contract
  unchanged); anti-truncation: parsed rows == 101. T5 effectively closes the
  loop CSV → DB → doc.
- **T6 (new)**: semester dates; holiday rows == 3 with labels; all slots on
  holiday pairs `available`; login accounts intact (5425 is_pl + name, 5770
  plain, 25RSD0001 exists).
- **T7 (new, reviewer suggestion)**: run
  `php artisan crs:import-real-schedule --verify` against the freshly seeded
  test DB and assert exit 0 — CI exercises the verify branch, not just the
  demo run.

### Seeder-chain rewiring (D1/D7)

- `DatabaseSeeder` chain becomes: reference data + users → `SemestersSeeder`
  (dates fixed to canonical) → `VenuesSeeder` → `ModulesSeeder` →
  `TimeSlotsSeeder` → **`HolidaysSeeder` (rewritten: 3 canonical rows)** →
  **`RealScheduleSeeder`** (replaces `ClassSessionsSeeder`;
  `ClassExceptionsSeeder` + `ReplacementRequestsSeeder` dropped — the demo
  overlays are gone by user decision (a), re-demoed in Slice B/C).
- **Tests seed via `$this->seed(DatabaseSeeder::class)` / `$this->seed()` as
  today — the shared core runs inside the chain.** They do NOT invoke the
  artisan command for seeding (mode gate would misread mid-chain states);
  T7's explicit command call happens only after the chain completes.
- Deleted: `ClassSessionsSeeder`, `ClassExceptionsSeeder`,
  `ReplacementRequestsSeeder`, `CsvTimetableSeeder`,
  `LecturerScheduleSeeder`. This **resolves Wave-2 archived open question Q5**
  (LecturerScheduleSeeder disposition): superseded by the real lecturer
  dataset.
- `SemestersSeeder`/`HolidaysSeeder`/`ModulesSeeder` get the canonical values
  (dates, 3 holidays) so a fresh test DB needs no demo-DB-style fixup pass —
  `RealScheduleSeeder` then only adds sessions/links/occupancy/prune in its
  additive path. On the demo DB the command wrapper performs the replace
  (delete) path first, then the same core.

## 7. Snapshot gate

- `crs:db-row-counts`: enumerates **all tables in the public schema from
  `information_schema`** (self-proving — currently 26; no hardcoded list to
  rot), TSV `table<TAB>rows`, non-zero exit on connection/schema failure.
- Home of the standing record: execution notes of this change hold the
  before/after pair; `page-changelogs/backend-automated-by-ai.md` records the
  new standing counts; future changes cite `crs:db-row-counts` output in their
  own execution notes (the "records-intact" gate the frozen Wave-3b addendum
  queued).

## 8. Execution plan on the demo DB (operator script, in tasks.md)

1. `php artisan crs:db-row-counts` → before block captured.
2. Fresh backup: `pg_dump -Fc` → `backups/class_replacement-pre-import2-<date>.dump`
   (gitignored).
3. `php artisan crs:import-real-schedule` (full output captured).
4. `php artisan crs:db-row-counts` → after block.
5. Re-run `php artisan crs:import-real-schedule` → verify mode passes
   (idempotency proof).
6. Safe dev-server restart (isolated `pkill -f "[a]rtisan serve"`, clear
   compiled views, `php artisan serve --port=8000`).
7. Live smoke: login 5425 → identity + own real sessions; 25RSD0001 → cohort
   sessions; Playwright `tests/nav-identity.spec.ts`.
8. Regenerate `dataset/timetable.md` via the ported generator; update
   `tests/MANUAL-TEST-CASES.md` step 2 (my-request-history: "≥3 cards from
   ReplacementRequestsSeeder" is gone until Slice B/C re-demos — replace with
   the real-data expectation, e.g. empty-state messaging).

## 9. KNOWLEDGE.md repo copy — declarative addendum

Append to `dataset/KNOWLEDGE.md` (verbatim copy then addendum; the source
folder outside the repo is not edited): counts correction (155/101/101 on
disk; recheck headline describes pre-14:14 regeneration), single surviving
double-booking, MPU34W2/empty-session-type remark stale (no empty rows),
holiday DB rows (3 grid rows; Sunday not storable), `is_pl` note (5425 **and
5516** are seed-set PLs — datasets carry no PL data).

## 10. Design decisions summary (numbered for review)

| # | Decision | Alternative rejected |
|---|---|---|
| D1 | Importer core = `RealScheduleSeeder`; command = mode-gate + snapshot wrapper | command-only (tests would exercise a copy) |
| D2 | time_slots: re-link existing grid rows; never delete/rebuild the grid | rebuild (slower, risks week/day drift) |
| D3 | Destructive txn, then additive txn, then read-only verify; delete assertions from live counts | one giant transaction; hardcoded baseline counts |
| D4 | allowed_session_types = observed-union per module | blanket L,T,P; lab-rule-aware seed data |
| D5 | Prune zero-session modules post-insert, inside shared core (36→42) | keep dead modules (pollutes Slice B/C pickers) |
| D6 | Venue CSV = physical truth; lecturer/programme CSVs cross-asserted | programme CSV as truth (per-cohort rows need dedup) |
| D7 | Old schedule seeders deleted; DatabaseSeeder chain rewired; resolves Wave-2 Q5 | keep both paths (drift risk; dead code) |
| D8 | Holiday slots left `available`; occupancy math 3963 | occupy holiday slots (contradicts prompt rule) |
| D9 | module_name fallback = the code itself, reported loudly | invent titles; abort (blocks on 22 known-missing titles) |
| D10 | Port `generate-timetable-doc.php` to plain DB queries → regenerate `dataset/timetable.md`; T5 keeps parsing it | delete generator+doc (breaks the doc←DB authority rule and T5's fixture) |
| D11 | `crs:db-row-counts` enumerates tables from `information_schema` | hardcoded 26-table list (rots on schema drift) |
