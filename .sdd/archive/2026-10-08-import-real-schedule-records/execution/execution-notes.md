# Execution notes — import-real-schedule-records (2026-10-08)

## Pre-run state

- Demo DB baseline captured → `rowcounts-before.txt` (35/44/5/3/36…, 26 tables)
- Fresh pre-import backup: `backups/class_replacement-pre-import2-20261008.dump`
  (pg_dump -Fc, 301 806 B, 26/26 TABLE DATA entries verified via `pg_restore --list`;
  byte-size identical to the Step-0 restore-verified drill dump)

## Import run — `php artisan crs:import-real-schedule`

Mode: **import-replace** (35-session baseline detected).

- Phase 0 preflight: datasets OK — 101/101/155 rows, 147.0 h, 42 codes,
  155 cohort-instances; warnings 18 (11 documented lab L/T rows + 7
  code-fallback titles: AMSE2002/2003/2013, MPU-2212/2302/3103/3302)
- Phase 1 (delete, live-count asserted): class_exceptions 15, replacement_requests 3,
  class_sessions 35; 0 orphans after
- Phase 2: semester dates → 2026-09-21…2026-12-27; holidays 5 → 3 canonical;
  modules upserted 42
- Phase 3: 101 sessions, 155 cohort links; pruned 34 unreferenced modules → 42
- Phase 4 verify: 3963 occupied, 0 orphans, 0 double-bookings, 155 links,
  14 cohorts, 42 modules, 3 holidays — PASSED
- Exit 0

## Post-run evidence

- `rowcounts-after.txt` — diff vs before touches exactly 6 tables:
  class_sessions 35→101, session_cohorts 44→155, holidays 5→3, modules 36→42,
  class_exceptions 15→0, replacement_requests 3→0. Users 266 / students 252 /
  lecturers 14 / cohorts 14 / venues 23 / time_slots 38 640 untouched.
- **Idempotency proof**: immediate re-run auto-detected imported state →
  verify-only mode → PASSED, exit 0 (R3.4: verify IS the re-run).
- `dataset/timetable.md` regenerated from the demo DB: SELF-CHECK 101/155/3963 —
  content-equivalent to the test-DB regeneration (D10 parity).

## Gates at execution

- phpunit **115/115, 809 assertions** (was 113/550; +T-6/T-7)
- phpstan-1G **0**
- pint `lint:check` adminer-only baseline (unchanged; our files pass)

## Live smoke

- Safe restart (isolated `pkill -f "[a]rtisan serve"` + compiled-views clear)
- Playwright `tests/nav-identity.spec.ts` **3/3**
- DB-level: 5425 owns 10 real sessions / 350 occupied slots (dataset "Su 10");
  25RSD0001's cohort RSD1(S1)G1 → 11 session links

## Standing records-intact gate

`php artisan crs:db-row-counts` — future changes capture its output before/after
and diff; only intended tables may change. Canonical imported counts:
**101 / 155 / 3963 / 3 holidays / 42 modules**, everything else per
`rowcounts-after.txt`.
