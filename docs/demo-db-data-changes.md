# Demo-DB Data Changes — Running Log

Intentional, user-approved edits to the demo PostgreSQL database
(`class_replacement_fresh`) that diverge from what the seeders would produce.
**Every entry lists both the live-DB change and the seeder update** so the
testing DB (re-seeded per test run) never disagrees with the demo DB.

> **Do not "repair" apparent DB-vs-seeder drift** — check whether an entry for
> it exists below first. Until the matching seeder commit lands, drift listed
> as pending is known and intentional.

## Snapshot convention

Any existing backup snapshot predates **all** of today's edits (2026-10-13:
this log + the main session's dataset edits). A restore from an old snapshot
silently reverts everything logged here. Going forward the convention is: the
`pg_dump` snapshot is taken **once, fresh, after the last logged edit** —
planned as part of the presentation-DB setup.

---

## 2026-10-13 — DSSH department re-linked FOCS → FAFB

- **Approved by:** user (2026-10-13, this session).
- **Live DB:** `UPDATE departments SET faculty_id = 2 WHERE dept_code = 'DSSH';`
  (verified after: DCIT→1, DSSH→2, DACB→2).
- **Seeder:** `database/seeders/DatabaseSeeder.php` — DSSH `Department::create`
  now uses `'faculty_id' => $fafb->id` (was `$focs->id`).
- **Rationale:** the cohort-timetable faculty auto-select derives a staff
  member's faculty via `lecturers.dept_id → departments.faculty_id`
  (DCIT→FOCS, DSSH→FAFB, DACB→FAFB). User decided DSSH sits under FAFB.
  Nothing else in `app/` reads this pointer today.
- **Effect:** Muada Bin Ojih (3799, DSSH) auto-opens FAFB on the cohort
  timetable; he teaches cohorts in both faculties, so he still lands on a
  faculty he teaches in.
- **Tests:** no full-seed test asserts department/faculty structure (verified
  by grep before the change) — suite unaffected.

### Entries from the main session follow

## 2026-10-13 — Cohort `student_count` sync (14 cohorts → 0 over-capacity)

- **Approved by:** user (2026-10-12/13, main session).
- **Live DB:** direct `UPDATE cohorts SET student_count = …` — three seeded
  transactions (`setseed(0.42)` range 15–25; `setseed(0.87)` ranges 7–10 /
  5–7) plus direct SQL for the RSD set. Final values:
  DFT1 23, DFT2 18, DSF1 25, DSF2 15, RSD1 8, RSD2 G1 7, G2 5, G3 9,
  RSD3 G1 10, G2 5, G3 9, RAF2 G2 5, G4 6, RBU1 10.
  (The five RSD values were changed by the user via SQL **before this log
  existed** — confirmed intentional 2026-10-13, author: user.)
- **Seeder:** `DatabaseSeeder.php` `STUDENT_COUNTS` — all 14 values now match
  the live DB (was: 30/28/24/22/18/16/16/15/14/14/13/12/10/20).
- **Rationale:** the imported cohort sizes overflowed their rooms — 7 sessions
  over capacity at peak (AMIT2034 50-in-28, MPU-3133 56-in-35, …). After the
  sync: **0 over-capacity sessions** in the whole timetable. Seed simulation
  with the OLD seeder values would have reintroduced 23 over-capacity
  sessions — this entry is what keeps a reseed presentation-safe.
- **Fresh-seed proof (2026-10-13):** scratch DB seeded from these seeders —
  101 sessions / 152 links / 0 orphans / 0 double-bookings, over-capacity
  audit **0 rows**, AMIT2034 3/33/34 DSF-only, all 14 counts exact.
- **Side note:** `RSD3G2_STUDENT_NAMES` (14 names) now exceeds the cohort's 5
  students; the seeder applies names in sequence (`$i < count(names)`), so
  only the first 5 are used — harmless, left as-is.
- **Tests:** no test reads `STUDENT_COUNTS` (fixtures hardcode their own
  cohort counts) — verified by grep before the change.

## 2026-10-13 — DFT2(S1)G1 unlinked from AMIT2034 (sessions 3 / 33 / 34)

- **Approved by:** user (2026-10-13, main session): "for these 3 rooms, keep
  only DSF, remove DFT."
- **Live DB:** `DELETE FROM session_cohorts WHERE class_session_id IN (3, 33, 34)
  AND cohort_id = 2;` (3 rows; no replacement_requests referenced them).
- **Seeder:** `dataset/import/programmes-schedules-202505.csv` — the 3
  `DFT2(S1)G1` AMIT2034 rows deleted; `dataset/import/venues-schedules-…csv` —
  the 3 matching rows' `cohorts` column now `DSF2(S1)G1` only;
  `RealScheduleSeeder.php` — `EXPECTED_COHORT_LINKS` **and**
  `EXPECTED_PROGRAMME_ROWS` both 155 → 152 (R2.5 keeps both CSVs equal,
  element for element; both constants guard the same row count).
- **Rationale:** the DFT+DSF combined class (33 > 28-seat rooms) could not fit
  any legal room; DSF-only (15) fits. Session 3 stays conflict-flagged via the
  B005+Diploma rule (DSF = Diploma) — intended.

## 2026-10-27 — the cancel-reason + audit-action CHECK widen (class-cancel-db-persist)

- **Approved by:** the user (the frozen SDD lane `class-cancel-db-persist`,
  5 review rounds, proposal vocabulary table verbatim).
- **Live DB (paste BOTH pairs):**

```sql
ALTER TABLE class_exceptions DROP CONSTRAINT class_exceptions_reason_check;
ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_reason_check
    CHECK (reason IN ('public_holiday','annual_leave','medical_leave','official_event','emergency_leave',
                      'hospitalisation_leave','maternity_leave','compassionate_leave','paternity_leave','other'));
ALTER TABLE audit_logs DROP CONSTRAINT audit_logs_action_check;
ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
    CHECK (action IN ('submitted','approved','rejected','cancelled','completed','occ_conflict','class_cancelled','class_cancel_undone'));
```

- **Migration:** `database/migrations/2026_10_27_000001_widen_cancel_reason_and_audit_checks.php`
  (same statements; fresh DBs via migrate:fresh).
- **Protected (untouched):** the pending replacement requests #1–#3; the
  `class_exceptions` row id 4 (`official_event`, session 40 / W14).
- **Rationale:** the Cancel Class radio now offers the 4 new leave types +
  Other, so the reason CHECK must accept the 10-value vocabulary; the undo
  flow audits `class_cancel_undone` (8th action value).

## 2026-10-27 — DFT2 re-unlinked from AMIT2034 (restore-rewind repair; R2 capacity)

- **Approved by:** the user ("a. drop DFT2"), the 2026-10-13 receipt re-run.
- **Context:** the pristine-2026-10-10 restore (the class-cancel-DB-persist
  investigation's damage repair) rewound the earlier unlink — AMIT2034
  carried DFT2(S1)G1 (28) + DSF2(S1)G1 (22) = **50 students in the
  28-seat B005** again, and the class-info modal showed the merged
  cohorts.
- **Live DB (executed):** `DELETE FROM session_cohorts WHERE
  class_session_id IN (3,33,34) AND cohort_id=2;` (3 rows; no test /
  replacement rows touched).
- **Row counts:** session_cohorts **152** (the seeder's EXPECTED_COHORT_LINKS
  ✓, already correct in the repo); the dataset/import CSVs were already
  DSF-only (untouched by the rewind — code vs data divergence).
- **Post state:** sessions 3/33/34 = **DSF2(S1)G1, 22 students** (22 ≤
  B005's 28 — the over-capacity violation is repaired ✓). The B005
  **conflict flag REMAINS by design** (DSF = Diploma — R2's no-Diploma
  law unchanged; the D4 rendering receipt `AMIT2034(P) · B005 · Red`).
- **Insurance dump:** `/tmp/insurance-pre-dftdrop-2026-10-27.dump` (the
  pre-drop state, 335 KB).

## 2026-10-27 — the venues info-changes (names + descriptions)

- **Approved by:** the user (the info-changes slide).
- **Live DB (executed):**
  - names — `B005 → Computer Laboratory 5`, `B006 → Computer
    Laboratory 4: CISCO Lab`, `B009 → Computer Laboratory 1`,
    `B010 → Computer Laboratory 2`, `B011 → Computer Laboratory 3`;
  - descriptions — labs: B005 `Computer Lab. For PRACTICAL Class.
    DEGREE students only.` (the R2 display data, now worded
    `Computer Lab.`), B009/B010/B011 `Computer Lab. For PRACTICAL.`;
    tutorials (16 rows) `Tutorial Room For LECTURE and TUTORIAL.`
    (the L+T admission wording per the slide);
  - halls untouched (out of the slide's scope — they keep
    `Lecture Hall. For LECTURE.`; a stray first-pass edit reverted).
- **Seeder mirror:** `VenuesSeeder.php` — the names keyed to the real
  lab numbering + the descriptions threaded through `insertGroup` (a
  re-seed matches the live state). `allowed_session_types`/capacities
  untouched (the reference inventory law).
- **B006's description unchanged** (`Cisco Lab. Networking & IoT
  equipment room. For FOCS cohorts only.` — the R4-dropped display
  string, kept verbatim per the receipt).
- Suite 262/262 green.

## 2026-10-27 — the cohort-shrink (every cohort at 7–10 students)

- **Approved by:** the user ("UPDATE each of this Cohorts to between 7 to
  10 students (random digit)") — the small-cohort viva scaling.
- **Live DB (executed, 14 rows):** DFT1=10, DFT2=9, DSF1=8, DSF2=7,
  RSD1=9, RSD2G1=8, RSD2G2=7, RSD2G3=10, RSD3G1=9, RSD3G2=8, RSD3G3=7,
  RAF2G2=10, RAF2G4=8, RBU1=9.
- **Seeder mirror:** `DatabaseSeeder::STUDENT_COUNTS` updated to the same
  14 values (the re-seed matches).
- **Side effects:** every class's total ≤ 11 (the multi-cohort merges
  shrink); all B005/B006 capacity fits trivially; the over-capacity audit
  stays 🟢. The BMSE3153 class-info now reads `14 → 8` and `13 → 7`
  (`8 + 7 = 15`).
- **Insurance dump:** `/tmp/insurance-pre-scoreshrink-2026-10-27.dump`.

## 2026-10-27 — the Deepavali Sunday row NOT added (the DB's own CHECK law wins)

- **Explore G5 planned** an insert of the mock's 4th holiday pair (Deepavali
  Sunday 08 Nov = W7/day 6). The attempt hit
  `holidays_day_of_week_check  CHECK (day_of_week BETWEEN 0 AND 5)` — the
  schema itself declares Monday–Friday only: a Sunday holiday is
  STRUCTURALLY unreachable (and no UI surface renders Sundays).
- **Resolution:** NO insert; the mock's 4-pair array was never
  DB-representable (its Sunday pair dies with the mock-statics-die
  deletions). The DB keeps its 3 rows. The mock-era law the check
  encodes (Mon..Fri) is its own honest contract.
- Insurance dumps: `/tmp/insurance-pre-dftdrop-2026-10-27.dump`,
  `/tmp/insurance-pre-scoreshrink-2026-10-27.dump` both taken BEFORE the
  runs (the pre-change states).

## 2026-10-27 (amendment) — the Diploma (D*) cohorts ride 20–25 (the split rail)

- **Approved by:** the user — my earlier cohort-shrink applied 7–10 to ALL
  14 cohorts including the Diploma ones; the user's correction: the D*
  cohorts (DFT/DSF) belong in the 20–25 band. The Bachelor cohorts keep
  their 7–10 values.
- **Live DB (executed, 4 rows):** DFT1=24, DFT2=20, DSF1=25, DSF2=21.
- **Seeder mirror:** `DatabaseSeeder::STUDENT_COUNTS` updated (the same 4
  values, the rail split noted in the docblock).
- **Side effects:** the AMIT2034 DSF-only rows (3/33/34) total 21 (well
  inside B005's 28 and B006's 32 ✓); the BMSE3153 breakdown stays
  `8 + 7 = 15` (RSD3 pairs untouched); the venue-restrictions R2 audit
  note's standing count rides the dataset (21, not the historical 22/15).
