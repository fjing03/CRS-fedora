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
