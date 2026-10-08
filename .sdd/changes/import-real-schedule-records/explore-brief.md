# Explore Brief — import-real-schedule-records

Grounded in first-hand reads on 2026-10-08: `KNOWLEDGE.md` (full, 365 lines), the 3
in-scope CSVs + 3 out-of-scope CSVs + venue MD § Lab sessions, live migrations,
`DatabaseSeeder` + all schedule seeders, `TimetableSeedInvariantsTest` /
`TimetableWiringTest` / `NavIdentityTest`, and live psql counts on
`class_replacement`.

---

## 0. Push state (pre-task, user-authorized)

- `origin/fedora-backend` pushed at `61c42d7` ("Everything up-to-date" — the
  auth-wiring/session work was already committed). `upstream` untouched (rule 4).
- Tree clean; untracked `.commandcode/` ignored per prompt.

## 1. ⚠ Dataset discrepancy — the recheck-report numbers are STALE

File mtimes tell the story: `recheck-report-202505.md` (13:23) predates the
regenerated venue CSV/MD (13:53) and programme CSV/MD (14:14).

| Dataset | recheck/prompt claim | ACTUAL on disk | Out-of-scope on disk |
|---|---|---|---|
| Programme | 184 blocks / 282 h | **155 rows / 229 cohort-hours** (MD §75 total) | 587 (not 558) |
| Venue | 121 blocks / 182.5 h | **101 rows / 147 room-hours** (MD §35 total) | 215 |
| Lecturer | 101 blocks / 147 h | **101 rows / 147 h** ✓ | 62 ✓ |

- The current files are **internally consistent**: programme MD §599 cross-check
  "155/155 in-scope blocks present in the venue dataset with same
  cohort/day/time/room/course/type"; venue cohort-instance sum = 155; lecturer
  cohort-instance sum = 155; programme rows = 155.
- **Decision: import the CURRENT on-disk CSVs.** The importer's evidence and any
  doc references must use 155/101/101 — never 184/121. `KNOWLEDGE.md` §1/§3
  counts + recheck-report headline need a declarative correction (addendum), not
  a rewrite of the files.

## 2. Verified import shape (derived from the current CSVs)

- **101 physical classes** (venue blocks == lecturer blocks == 101; both carry
  147 h — 1:1 lecturer↔venue per class, as a real timetable implies).
- **155 cohort-instances** → `class_sessions` 35→**101**, `session_cohorts`
  44→**155** (each programme-CSV row = 1 link; shared class = 1 session + N links).
- **42 distinct course codes** (list captured in §6 below); none of the 36
  hand-made `modules.module_code` values except `MPU-3133`/`MPU-3232` appear.
- Day split (venue CSV): Mon 31 h, Tue 29.5, Wed 41, Thu 27, Fri 18.5, **Sat 0**.
- Occupied-slot math: 147 h × 2 slots/h × 14 weeks = **4116** if holidays ignored.
  Holidays (W8 Mon, W14 Thu, W14 Fri): 62 + 54 + 37 = **153** slots → expected
  occupied = **3963** when holiday-day slots are left `available` (prompt
  normative rule). Exact number re-derived in design; the invariant test asserts it.
- `session_type` fully populated in all 3 CSVs (L/T/P only; verified by awk on
  the correct columns). **Prompt question (d) is MOOT** — KNOWLEDGE §8's
  `MPU34W2` empty-type remark is stale; no row needs type inference.

## 3. Scope/data quirks confirmed against current files

- **Double-bookings: only ONE survives** (KNOWLEDGE §8 said 2). DFT2(S1)G1 Wed:
  AMIT2014 10:00–12:00 B009 vs AMIT2034 11:00–13:00 B005 — cohort-level clash,
  venue-distinct → does NOT hit `time_slots_no_double_book_idx`. The Thu
  MPU-2212/MPU-2202 clash is GONE: MPU-2202 rows are out-of-scope now
  ("lecturer not in the 14-staff dataset (no staff ID)" — Jamie).
- **Lab L/T check (venue MD §255–285):** 17 in-scope L/T blocks sit in labs;
  8 allowed (Networking/IoT), but **AMIT2034 in B010 still needs a room-level
  exception** (§3 names only B006). **9 true violations** (BMIS2003 L, AMCS1013 T,
  BMIT2013 L, AMSE1003 T, BMIT1173 T, AMIS1003 T, AMCS1043 T, BMIT1723 L,
  BMCS3033 T) — MD instruction: re-home or record explicit per-subject exception
  before seeding. **Design lean: exception-list, don't re-home** (re-homing
  invents room assignments absent from the source; the PDFs are ground truth and
  `venues.allowed_session_types` remains a guard for future *manual* bookings).
- Venue labels: CSV `room_label` has a typo ("Tuorial Room" B002) and label-only
  forms — join on **room_code** (first token), never label. DB `room_name`
  format is "Tutorial Room B002" — untouched.

## 4. Schema facts (first-hand, migrations + psql)

- `class_sessions`: `session_type` CHECK ('L','T','P') NOT NULL; `day_of_week`
  CHECK 0–5; clock times; FKs cascade.
- `time_slots`: 38,640 = 23 venues × 14 wks × 6 days × 25 half-hour slots
  (08:00–17:30 end 18:00); `status` CHECK ('available','pending','occupied');
  partial UNIQUE `(venue_id, day_of_week, start_time, week_number) WHERE status
  IN ('pending','occupied')`; `version` default 1 (OCC). Grid built by
  `TimeSlotsSeeder` all-`available`; holidays do NOT pre-mark slots.
- `holidays`: `day_of_week` CHECK 0–5 → **Deepavali Sunday 8-Nov cannot be a
  row**; table holds the 3 grid holidays only. Unique index
  (semester_id, week_number, day_of_week) added 2026-10-06.
- `semesters`: 1 row `202605`, stale dates 2026-08-31→2026-12-06 → must become
  **2026-09-21 → 2026-12-27** (normative, not a user question).
- `venues`: has `allowed_session_types` ('L,T' tutorial rooms, 'L' halls,
  'P' labs+cisco) + `room_code` + `room_name`; `modules`: `module_code` UNIQUE,
  `module_name`, `allowed_session_types` (format `'L,T,P'`).
- `session_cohorts`: composite PK (session, cohort).
- **module_id FK exists only in class_sessions** → pruning unreferenced modules
  is FK-safe after session replacement.

## 5. Baseline (live psql, matches prompt exactly)

users 266, lecturers 14, cohorts 14, venues 23, modules 36, class_sessions 35,
session_cohorts 44, replacement_requests 3, class_exceptions 15, holidays 5,
time_slots 38640, semesters 1. Backup
`backups/class_replacement-pre-import-20261008.dump` present (restore-verified).

## 6. Seeders / tests — what the replace decision touches

- **Seed-pinned tests to rework:**
  - `TimetableSeedInvariantsTest` (708 lines): frozen `EXPECTED_SESSIONS=35`,
    `EXPECTED_SESSION_COHORTS=44`, `EXPECTED_OCCUPIED_SLOTS=1988`,
    `EXPECTED_COHORTS=14`; T1 no-orphans/exact-occupancy, T2 no venue/lecturer/
    cohort overlaps (**will fail on the 1 kept cohort clash — needs an explicit
    allowance or the clash dropped, per question (c)**), T3 all-cohorts-covered,
    T4 MPU cohort-sets per spec, T5 doc-DB parity + position ids 1..35.
  - **`TimetableWiringTest` is NOT affected** — it builds its own fixtures
    (BMIT1001/B100), contradicts the prompt's knock-on list (stale claim).
  - **`NavIdentityTest` is NOT affected** — pins identity/nav only (5425 PL,
    5770 plain, 25RSD0001 student); no session tuples (prompt's "live tuples"
    claim is stale). Grep over app/ + views/ found no hardcoded BMIT9012/B107/
    BMIT2222/B101 outside tests.
- **Demo overlays** (question a): `ReplacementRequestsSeeder` → session ids 9,
  12, 22; `ClassExceptionsSeeder` → ids 1..20 (+ its own conflict fixture on
  session 9). All die with the 35 hand-made sessions.
- House importer pattern to follow: `ClassSessionsSeeder` — insert session →
  link cohorts → occupy slots → assert affected == expected, throw loudly.
  `CsvTimetableSeeder` (326 lines) is the existing CSV-parsing precedent.
- Login accounts: 5425/5770 staff + 25RSD0001 student, `Tarumt@2026` —
  untouched by scope (users/students tables not written).

## 7. Open questions — ✅ ANSWERED by user 2026-10-08

- **(a)** Demo overlays (3 replacement_requests + 15 class_exceptions):
  **DROP.** Re-create demo requests/exceptions later as part of Slice B/C of
  `wire-backend-into-refactored-ui`, pinned to real sessions.
- **(b)** Semester code: **KEEP `202605`** — only the stale dates are fixed.
- **(c)** The single surviving source double-booking (DFT2 Wed): **KEEP as
  printed** — venue-distinct, no DB violation; invariant test gets an explicit
  allowance.
- **(d)** MOOT — no empty `session_type` rows exist in the current CSVs.
- **(extra, user-confirmed)** The 9 real L/T-in-lab blocks (BMIS2003, AMCS1013,
  BMIT2013, AMSE1003, BMIT1173, AMIS1003, AMCS1043, BMIT1723, BMCS3033):
  **exception-list, keep source rooms** — no re-homing.

## 8. Rejected approaches

- **Coexist with the 35 hand-made sessions** — DECIDED against (prompt §✅):
  guaranteed slot collisions, unrecognizable hybrid demo.
- **migrate:fresh --seed** — forbidden standing rule (demo DB wipe).
- **Re-homing the 9 lab L/T blocks to tutorial rooms** — invents data absent
  from the printed source; exception-listing keeps the dataset faithful. (To be
  confirmed at design; reversible if the user objects.)
- **Importing out-of-scope CSVs** — they exist to prove nothing was dropped.
- **Trusting recheck-report counts (184/121)** — stale; current files win.

## 9. Known open questions (non-user, for design)

- Exact expected occupied-slot count under the holiday rule (3963 pending
  re-derivation from the actual session list).
- `modules.allowed_session_types` for the 42 real codes: `'L,T,P'` blanket vs
  observed-types vs lab-rule-aware (Networking/IoT list). Design decides.
- Fate of the 34 unreferenced hand-made modules (prune vs keep). Design leans
  prune-after-import for a coherent demo; FK-safe per §4.
- Whether `replacement_requests`' conflict-fixture seeding (session 9 +
  class_exceptions insert inside that seeder) is dropped wholesale with (a).
