# Explore Brief — repair-timetable-seed-data

Created 2026-10-07 · Wave 2 · evidence gathered first-hand from live DB (pgsql `class_replacement`) + source.

## 1. Governing context

Wave 2 of the approved 3-wave plan. Locked decision (Wave 1): **patch the existing `ClassSessionsSeeder`**, do not regenerate from scratch. Standing rule (AGENTS.md): demo resets via `php artisan migrate:fresh --seed` are sanctioned.

## 2. Confirmed defects (executed evidence)

### D1 — `markTimeSlotsOccupied()` silent no-op (`database/seeders/ClassSessionsSeeder.php:78`)
The occupancy pass filters `->where('status', 'available')` (:78) — correct pre-condition, **but there is no post-condition check**. When a later template shares venue+day+overlapping time with an earlier one, its update matches 0 rows and the failure is swallowed: the `class_sessions` row is committed but **zero `time_slots` rows reference it** (orphan — invisible to every availability/venue view).

### D2 — Three orphan/double-booked sessions (insertion order = template order; IDs 1..33)
Live DB trace (2026-10-07):

| Session | Class | Venue/day/time | Cause |
|---|---|---|---|
| **#25** | BMIT2020 (DFT1 P) | B005 day4 08–10 | exact dup of #4's venue B005 day4 — but note live clip shows overlap **09:00–11:00** (see D5) |
| **#30** | MPU-3133 (RAF2G4 L) | B110 day2 10–12 | exact dup of #29 (RAF2G2, same slot) |
| **#32** | MPU-3232 (RBU1 L) | B110 day0 14–16 | dup of #12 (BMIT9012 multi DFT2G1+DSF2G1, same slot) |

All three rows contain **zero** occupied time_slots; 30 non-orphan sessions are intact.

### D3 — MPU cohort assignment violates the frozen dataset spec (`BACKEND-TASKS.md:222–223`)
| Module | Spec intends | Live DB has | Delta |
|---|---|---|---|
| MPU-3133 (Falsafah) | cross-faculty (RAF2, RBU1, **RSD3**) — L sessions, venue filter excludes labs | RAF2(S3)G2, RAF2(S3)G4, RBU1(S1)G1 | **no RSD3 cohort at all** |
| MPU-3232 (Kewusah) | cross-year stacking (**RSD2G2, RSD2G3, RSD3G3**) — L+T sessions | RBU1(S1)G1 only | **wrong cohort set entirely** |

### D4 — `dataset/timetable.md` does not exist
BACKEND-TASKS.md Task 2.2 ("Synthesize dataset/timetable.md — 14 cohorts / 14 lecturers / 23 rooms / 20–30 blocks / 08:00–18:00, 1-hour blocks (some 2-hour)" + the two MPU risk cases) was never produced. The current `dataset/` holds `cohorts.md`, `lecturers.md`, ERD artifacts, `fr43_findings.md` — no timetable baseline.

### D5 — Live DB diverges from seeder source
Live overlap window for #4/#25 is 09:00–11:00 while the current code says 08:00–10:00 (a pre-existing stale seed). Consequence: repair ends with a sanctioned `migrate:fresh --seed` **plus a code↔DB parity assertion** so this class of drift is detectable.

## 3. Verified healthy (do not touch)
- All 14 cohorts covered by ≥1 session (`session_cohorts` = 37 rows); total sessions = 33.
- Zero `session_type` vs `venues.allowed_session_types` violations across all 33 rows.
- Time grid is sound: 30-min cells × 14 weeks; 2-hour sessions occupy 4 cells each.
- `DbIntegrityConstraintsTest` (unit) currently covers FK/uniqueness invariants on synthetic fixtures only — extension, not repair, needed.

## 4. Candidate fix directions (for proposal review)
1. **D1**: post-update assertion in `markTimeSlotsOccupied()` — expected slot count = `ceil(duration/30)` per cohort-day-venue match... specifically 12/2-h session → 4 cells; throw `RuntimeException` when affected rows ≠ expectation (fail-fast, kills the silently-orphaned class of bugs).
2. **D2**: resolve clashes at template level, options:
   a. 29+30 merge into one multi-cohort session (RAF2G2+RAF2G4, one B110 row) — mirrors existing multi patterns #12/#22/#31.
   b. 25 re-time (or venue-move) to keep DFT1 P legal without colliding with DFT2 P.
   c. 32 re-time/move away from B110 Mon 14–16 (or merge #12+#32? different cohorts but same venue+time — merging would over-foot DFT2/DSF2 with an unenrolled Business module — merge not semantically valid; re-time instead).
3. **D3**: recast MPU rows to spec cohorts: MPU-3133 gains RSD3 group session(s) (kept venue-filter-clean, B110/B111 only per "exclude labs"); MPU-3232 cohort set becomes RSD2G2+RSD2G3+RSD3G3 (multi or per-group rows), venue L+T-legal.
4. **D4**: synthesize `dataset/timetable.md` mirroring the repaired 33-row baseline (+ the two MPU risk cases), or defer docs as a separate follow-up.
5. **D5**: extend `tests/Unit/DbIntegrityConstraintsTest.php` (or a sibling `SeederInvariantsTest`): no orphans (every session ≥ 4 occupied slots — subcase: exact coverage 4), no venue/day/time overlaps, MPU sets = spec, code↔DB parity for the 33-session count, and venue allowed_session_types conformance. Consider a `DatabaseSeeder`-driven Feature test (pattern exists: OCCValidatorTest S-12 / TimetableWiringTest run full seed).

## 5. Open questions (user/proposal)
- Q1: Approve clashes resolution (2a/2b/2c) exactly as above, or alternate placements?
- Q2: `migrate:fresh --seed` re-run = sanctioned demo reset — confirm (it also wipes the un-run `ReplacementRequestsSeeder`'s demo requests — that seeder is now in-repo and may be chained or skipped for now).
- Q3: `dataset/timetable.md` synthesized in this change, or deferred? (Plan said "dataset/timetable.md sync" — in-scope recommendation: yes, in-scope.)
- Q4: 1-hour blocks (spec says "some 2-hour") vs current all-2-hour: in-scope or documented-debt? (Recommendation: stay all-2-hour this wave — patch-not-regenerate; record as debt for the Wave 3 backend/UI wiring if replacement durations need 1-h granularity.)
- Q5: `LecturerScheduleSeeder.php` (dead code, references external `../CRS/past sem pdf`, never called by DatabaseSeeder): delete in this change, or parked?

## 6. Out of scope (default)
- DB schema/migrations; MatrixIntersectionEngine/OCC logic; UI mock-data cleanup (`public/js/mock-data.js` — Wave 3's concern); `playwright` e2e runtime.
