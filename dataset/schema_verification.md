# Schema verification vs. ERD

Date: 2026-10-06
Source: live DB `class_replacement` (PostgreSQL @ 127.0.0.1), read-only queries.

## Deliverable

- **`schema_current_db.sql`** — `pg_dump --schema-only --no-owner --no-privileges` of the real DB (1593 lines, all **26 tables** = 17 domain + 9 Laravel infra: cache, cache_locks, failed_jobs, job_batches, jobs, migrations, password_reset_tokens, passkeys, sessions).
- This is the Postgres equivalent of the requested `mysqldump --no-data`; `mysqldump` does not apply here (DB is PostgreSQL).
- Paired deliverable: **`ERD_current_db.drawio`** — generated from this same DB; FK set verified equal to the DB's 26 foreign keys.

## The 6 proposed fixes — fact-checked against the real DB

| # | Claim | Verdict |
|---|-------|---------|
| 1 | `class_exceptions` needs a unique key on `(class_session_id, week_number)` | **Already exists** — index `class_exceptions_class_session_id_week_number_unique` on exactly those two columns. |
| 2 | `audit_logs` needs a timestamp column | **Already exists** — `created_at` / `updated_at` (timestamps), plus a btree index `audit_logs_created_at_index` on `created_at`. |
| 3 | `holidays` needs a date column | No `date` column — **by design**: it encodes dates as `(semester_id, week_number, day_of_week)` with a supporting index (`holidays_semester_id_week_number_day_of_week_index`). Verified working app-side (week-3 holiday lookup on `/my-timetable-ui` matched). Adding a `date` column is a design change, not a bug fix. |
| 4 | `time_slots` needs a unique key on `(class_session_id, week_number)` | **Would break the DB** — 420 `(class_session_id, week_number)` pairs currently have multiple rows, because one meeting spans several 30-minute slot rows (e.g. a 2-hour class = 4 rows). The real constraint already exists: `time_slots_no_double_book_idx` = `UNIQUE(venue_id, day_of_week, start_time, week_number) WHERE status IN ('pending','occupied')`. |
| 5 | `class_exceptions.reason` should be `varchar(255)` | Factually `varchar(30)`, but the longest value in use is **15 chars** — no data at risk. Widening is a 1-line migration if wanted. |
| 6 | `cohorts` needs a unique key | True that only the PK exists (no natural-key UK), but **0 duplicate cohorts** exist in data today (grouped by `programme_id, current_year, semester, tutorial_group, intake`). Adding a UK is a design change. |

## Summary

- Items 1 and 2: already satisfied — nothing to do.
- Item 4: actively harmful — do not apply.
- Items 3, 5, 6: optional design tweaks; no live bug. Apply only if confirmed, then regenerate `ERD_current_db.drawio`.
- All DB modifications (migrations) await explicit approval — none applied yet.

---

## Update 2026-10-06 — migrations applied (user-approved)

Items 5/6 → applied as Laravel migrations (user-approved, branch `fedora-backend`):

| Migration | What it does | Constraint added |
|-----------|--------------|------------------|
| `2026_10_06_150001_make_holidays_semester_week_day_unique` | promotes the holiday lookup index to a UNIQUE constraint (dupe guard, follows from #3 discussion) | `holidays_semester_id_week_number_day_of_week_unique` on `(semester_id, week_number, day_of_week)` — plain index dropped |
| `2026_10_06_150002_add_cohorts_natural_key_unique` | item #6 — natural-key UK | `cohorts_natural_key_unique` on `(programme_id, academic_year, intake, current_year, semester, tutorial_group)` |
| `2026_10_06_150003_add_time_slots_slot_duration_check` | slot grain guard | `time_slots_slot_duration_check` — CHECK `end_time = start_time + interval '30 minutes'` |

Item 5 (`class_exceptions.reason` → varchar(255)) — **skipped**: longest value in use is 15 chars and no bug; keeping the tighter width is the safer schema.

Pre-checks were clean (0 holiday dupes, 0 cohort dupes, 0 slot-duration violations), so no rows were dropped/altered by the constraints. Post-apply verification: constraints visible in `pg_dump`/`\d*`, and migrations ran without warnings. `dataset/schema_current_db.sql` is now one step behind (pre-migration dump) — re-dump when regenerating `ERD_current_db.drawio`.

ERD regeneration (data verified, layout/label work) is parked — see `ERD_current_db.drawio` blocker note in the chat summary.
