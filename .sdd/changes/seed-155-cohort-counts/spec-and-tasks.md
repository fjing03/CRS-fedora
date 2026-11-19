# Seed the documented 155 students — compact SDD

**Status:** Applied and verified; seeder-only implementation. User approved the change. Independent read-only review passed before apply (no findings, `/tmp/crs-seed155.oDz0bT/review.txt`).

## Scope

Update only the `DatabaseSeeder::STUDENT_COUNTS` data and its stale explanatory comment to match `prompts/for-ai-to-read/CodingMAIN.md` §8 (2026-11-19). Keep all 14 cohort keys, rows, ID generation and the named-student ordering unchanged in code; generated IDs may move from the 175-student seed, but the 42 recorded demo handles should match their documented cohorts. The revised counts are DFT 23/18, DSF 25/15, RSD1 8, RSD2 7/5/9, RSD3 10/5/9, RAF 5/6, RBU 10 in cohort order. One map drives both the stored headcounts and seeded student accounts. Do not edit the approved table in CodingMAIN, the semester seed or its date fixtures. Report that the old mismatch note in CodingMAIN becomes stale after this seeder-only change; do not broaden the approved implementation scope.

## Acceptance and tasks

- [x] Independent review against the table and the seeder's two uses: PASS.
- [x] Updated the constant and its comment; no schema, booking, existing accounts or live data changes.
- [x] Verified database, socket, port (55443) and data directory on a new isolated PostgreSQL cluster. Plain migrate, one seed, then read-only checks: all 14 counts and account totals match, 155 students and 14 staff, MPU-3133 35, MPU-3232 23, all 42 documented student IDs in the right cohorts. Receipts: `/tmp/crs-seed155.oDz0bT/`.
- [x] PHP lint and PHPStan passed. Isolated cluster stopped. No existing databases were reseeded.
