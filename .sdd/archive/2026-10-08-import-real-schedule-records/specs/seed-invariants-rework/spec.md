# Spec — seed-invariants-rework

Capability: test suite rework + seeder-chain rewiring + doc generator port.
Baselines: frozen proposal.md §2 item 4 / design.md §6, §2 (D10).

## R1 Seeder chain (DatabaseSeeder)

- R1.1 Chain: reference data + users → `SemestersSeeder` (canonical dates 2026-09-21/2026-12-27) → `VenuesSeeder` → `ModulesSeeder` (**left as-is** — it seeds the 36 hand-made reference rows; the shared core's module upsert adds the 42 real codes and R-prune of the importer spec removes the unreferenced ones, ending at 42) → `TimeSlotsSeeder` → `HolidaysSeeder` (rewritten to the 3 canonical rows) → `RealScheduleSeeder`.
- R1.2 `ClassSessionsSeeder`, `ClassExceptionsSeeder`, `ReplacementRequestsSeeder`, `CsvTimetableSeeder`, `LecturerScheduleSeeder` are DELETED (Wave-2 Q5 resolved for LecturerScheduleSeeder).
- R1.3 `RealScheduleSeeder` runs the shared core: on a fresh DB the additive path (R5/R6 of the importer spec); the delete path exists only in the command's replace mode.
- R1.4 `migrate:fresh --seed` (test DBs only) yields the real-data demo: 101 sessions / 155 links / 3963 occupied / 3 holidays / 42 modules.

## R2 TimetableSeedInvariantsTest (rework in place)

- R2.1 Constants re-pinned: SESSIONS 101, SESSION_COHORTS 155, OCCUPIED_SLOTS 3963, COHORTS 14.
- R2.2 T1: per-session occupancy derived from `dataset/import/` CSVs (slotCount × weeks minus holiday weeks) — no magic numbers.
- R2.3 T2: venue/lecturer overlaps zero-tolerance; cohort-overlap violation set must EQUAL the one-tuple whitelist (DFT2(S1)G1, Wed, 11:00–12:00, AMIT2014 × AMIT2034).
- R2.4 T4: MPU-* cohort sets equal the programme-CSV sets.
- R2.5 T5: parses `dataset/timetable.md` (format contract unchanged); anti-truncation: parsed rows == 101. **The proposal's "position-id fixtures 1..35 re-anchored" item is subsumed here**: parity of each doc row against its DB session replaces position-based ids entirely — no id anchors remain.
- R2.6 T6 (new): semester dates; 3 holiday rows with labels; holiday-pair slots all `available`; accounts 5425 (is_pl + name), 5770 (plain), 25RSD0001 intact.
- R2.7 T7 (new): `php artisan crs:import-real-schedule --verify` exits 0 against the freshly seeded test DB (called AFTER the seed chain completes).

## R3 Other seed-consumer tests (inventory per design §6)

- R3.1 `MatrixIntersectionEngineTest`: re-pin to real data — a green window for 4288/DFT2(S1)G1 derived from the dataset (derivation commented in the test); holiday assertion = no window on W8 Mon / W14 Thu / W14 Fri; the chosen week documented.
- R3.2 `OCCValidatorTest`, `TimetableWiringTest`, `NavIdentityTest`: must pass WITHOUT fixture changes; only the seed chain may be touched if it breaks them.

## R4 Doc generator port (D10)

- R4.1 `dataset/generate-timetable-doc.php` renders `dataset/timetable.md` from plain DB queries (class_sessions ⋈ modules/users/venues/session_cohorts) — same §4.1 format/day-grouping contract.
- R4.2 No reference to deleted seeders remains anywhere in the repo.

## R5 Manual docs knock-on

- R5.1 `tests/MANUAL-TEST-CASES.md` my-request-history step 2 no longer promises cards from ReplacementRequestsSeeder — rewritten to the real-data (empty-state) expectation pending Slice B/C.
