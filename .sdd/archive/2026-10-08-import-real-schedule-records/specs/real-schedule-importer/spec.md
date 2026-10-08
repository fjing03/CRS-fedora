# Spec — real-schedule-importer

Capability: the re-runnable importer (`RealScheduleSeeder` + `crs:import-real-schedule`).
Baselines: frozen proposal.md / design.md §3–§5.

## R1 Dataset inputs (single source, in-repo)

- R1.1 The importer reads ONLY `dataset/import/*.csv` + `dataset/import/course-titles.php` + `dataset/import/lecturer-ids.php`. No absolute paths, no `../CRS/...` escapes, no inline data.
- R1.2 CSV parsing handles the documented column orders:
  venue: `venue,room_label,day,start,end,duration_hours,course,session_type,lecturer,cohorts,source`;
  lecturer: `staff_id,lecturer,day,start,end,duration_hours,course,session_type,venue,cohorts,source`;
  programme: `cohort,day,start,end,duration_hours,course,session_type,lecturer,venue,source`.
- R1.3 Venue code = first token of the venue cell (`B009 - Lab 1` → `B009`).
- R1.4 Missing module titles fall back to the code itself; every fallback is reported by name at run end.
- R1.5 Dataset-copy fidelity: the 3 in-scope CSVs are copied **byte-identical** into `dataset/import/`; NO out-of-scope CSV is copied; `KNOWLEDGE.md` is a verbatim copy + declarative addendum (design §9); `DATASET-NOTES.md` carries the five pinned contents (design §2). (Execution of the copies lands in tasks.md.)

## R2 Preflight (Phase 0 — every mode, before any write)

- R2.1 Assert: venue rows == 101, lecturer rows == 101, programme rows == 155; venue hours total 147; 42 distinct course codes; session_type ∈ {Lecture, Tutorial, Practical} everywhere; times 30-min aligned inside grid bounds 08:00–17:30; day ∈ {Monday…Saturday}.
- R2.2 Assert all venue codes resolve to `venues.room_code` rows (23).
- R2.3 Assert every venue row's `(day,start,end,course)` matches exactly one lecturer row and that row's venue equals the venue row's venue; every `staff_id` resolves via `lecturers.staff_id` → `users.id`.
- R2.4 Assert every cohort label (14) resolves — via the `DatabaseSeeder::cohortCode()` derivation character-for-character (`programme_code+year+'(S'+sem+')G'+group`) — to a `cohorts` row.
- R2.5 Assert the multiset of `(cohort, day, start, end, course, venue)` derived from venue-CSV cohort lists equals the programme-CSV rows element-for-element; cohort-instance total 155 from every view.
- R2.6 Print the lab-rule warning list (11 rows per design §5) and the missing-title list; warnings never abort.
- R2.7 ANY preflight failure aborts with zero writes performed.

## R3 Mode gate

- R3.1 States (by live `class_sessions` count + fingerprint): 0 → import-additive; 35 → import-replace; 101 + dataset fingerprint match → verify-only; else abort with guidance. **"Dataset fingerprint" = the R2.1 dataset assertions pass — no CSV file hashing.**
- R3.2 `--verify` forces verify-only; aborts if state is not the imported state.
- R3.3 Verify-only performs Phases 0 + 4 and writes NOTHING.
- R3.4 Import mode on the imported state is refused (verify IS the re-run).
- R3.5 The mode gate lives in `ImportRealScheduleCommand`; `RealScheduleSeeder` invokes the shared core directly (ungated) when chained from `DatabaseSeeder`.

## R4 Delete phase (import-replace only; one transaction)

- R4.1 Order: clear `time_slots` occupancy (`class_session_id=NULL, status='available', version=1, updated_at=now()`) for the semester's sessions → delete `class_exceptions` → delete `replacement_requests` → delete `class_sessions`.
- R4.2 Every delete asserts affected == live pre-delete count of rows matching its filter (never hardcoded 35/44/15/3/5).
- R4.3 Post-delete: zero `session_cohorts` rows for the semester; zero `time_slots` rows referencing deleted sessions.

## R5 Reference phase (import-additive + import-replace; one transaction with R6)

- R5.1 `semesters`: set `start_date=2026-09-21`, `end_date=2026-12-27` for `202605`; do not touch code/label/week_count.
- R5.2 `holidays`: delete all semester rows (live count), insert exactly `(8,0,'Deepavali Holiday (In Lieu)')`, `(14,3,'Christmas Eve')`, `(14,4,'Christmas Day')`.
- R5.3 Modules: upsert all 42 codes; `module_name` from course-titles.php else code-fallback; `allowed_session_types` = observed-union string in L,T,P order (e.g. `AMIT2014` → `L,P`, `MPU-2212` → `T`).

## R6 Session phase (same transaction as R5)

- R6.1 Insert 101 `class_sessions` from the venue CSV (semester, module, lecturer per R2.3, day_of_week Mon=0…Sat=5, clock times HH:MM:00, venue, type L/T/P).
- R6.2 Insert 155 `session_cohorts` links from venue-CSV cohort lists (dedup within a session).
- R6.3 Prune modules with zero session references AFTER inserts (live-count assertion); post-condition modules == 42.
- R6.4 Occupy slots per session: `UPDATE time_slots SET class_session_id=?, status='occupied', updated_at=now() WHERE semester_id=? AND week_number BETWEEN 1 AND 14 AND week_number NOT IN (holiday weeks for that day) AND day_of_week=? AND start_time>=? AND start_time<? AND venue_id=? AND status='available'`; assert affected == slotCount × (14 − holidayWeeksOnThatDay) exactly; throw a session-identifying error otherwise. Holiday pairs: (8,0), (14,3), (14,4).

## R7 Verification pass (Phase 4 — read-only, after commit; runs in every mode)

- R7.1 occupied == 3963; orphan `class_session_id` == 0; non-available slots with NULL session == 0; venue double-bookings == 0 (explicit GROUP BY check); session_cohorts == 155; cohort coverage == 14; modules == 42; holidays == 3; holiday (week,day) slots all `available`; semesters dates canonical.
- R7.2 Print the `crs:db-row-counts` snapshot at the end of the run.
- R7.3 Post-commit failure: loud error + restore instruction; no auto-rollback.
- R7.4 Verification is **count/state-level only** — session content parity against the CSVs is out of scope (T5's doc↔DB parity is the content check that exists).

## R8 Output & exit codes

- R8.1 Human-readable phase log with counts; warnings distinct from errors.
- R8.2 Exit 0 on success/verify-pass; non-zero on any abort.
