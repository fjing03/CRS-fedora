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
