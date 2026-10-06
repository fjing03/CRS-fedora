# RE: ERD vs Schema Review — Fedora Current DB (status update)

Date: 2026-10-06
To: ERD vs Schema Review (2026-10-06)
Re: `ERD_current_db.drawio` / `schema_current_db.sql` verdict table

---

Good news first: your action plan items 1–4 are **already executed** — applied and committed today (`3ec2a73`) before this review arrived; we independently reached the same 4 decisions.

## 1. Migrations — applied to the live DB, verified

| Your action | Status | Actual constraint name (verified in `pg_constraint`, not just docs) |
|---|---|---|
| 1. `holidays` plain index → UNIQUE | ✅ applied | `holidays_semester_id_week_number_day_of_week_unique` — unique added first, *then* the plain index dropped, so there's no window without the guardrail; `down()` reverses exactly |
| 2. cohorts natural-key UK | ✅ applied | `cohorts_natural_key_unique` on `(programme_id, academic_year, intake, current_year, semester, tutorial_group)` — same column set you proposed, `current_year`+`semester` included for the in-place / year-progression reason |
| 3. `time_slots` 30-min CHECK | ✅ applied | `time_slots_slot_duration_check`: `CHECK (end_time = start_time + interval '30 minutes')` |
| 4. skip #5 | ✅ skipped | agreed — the CHECK enum caps `reason` at 15 chars in use; widening buys nothing |

Pre-conditions were checked on real data before apply: **0 holiday dupes** on (semester, week, day), **0 cohort dupes** on the natural key, **0 off-grid slots** — so nothing was rejected or dropped by the new constraints. Pint + PHPStan clean on the migrations.

Migration files:

- `database/migrations/2026_10_06_150001_make_holidays_semester_week_day_unique.php`
- `database/migrations/2026_10_06_150002_add_cohorts_natural_key_unique.php`
- `database/migrations/2026_10_06_150003_add_time_slots_slot_duration_check.php`

## 2. ERD redraw — done in substance, 1 polish item parked

The full-redraw generator already implements every point of your §5:

- all **18 domain tables** incl. `passkeys`; `created_at`/`updated_at` on every domain table; `users` full 2FA (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) + `email_verified_at` columns
- `class_exceptions` composite UK drawn as **one** shared key — member columns carry a `UK₁` subscript marker instead of two separate UKs (your review flagged the old PNG as drawn wrong)
- notes box documents the **30-min cell grain** of `time_slots`, both partial unique indexes verbatim (`uq_replacement_requests_active_block`, `time_slots_no_double_book_idx`), and the CHECK enums: `users.role ∈ {student, lecturer}` (no admin — authority is `lecturers.is_pl`), `time_slots.status ∈ {available, pending, occupied}`, `venues.room_type`, `replacement_requests.status`, `class_exceptions.reason`, `class_sessions.session_type ∈ {L, T, P}`, week 1–14, `day_of_week 0–5`
- Laravel framework tables omitted (cache, sessions, jobs, failed_jobs, job_batches, cache_locks, password_reset_tokens, migrations)
- all **27 domain FK edges** verified equal to the DB's 27 foreign keys (0 dangling), with crow's-foot notation and nullable FKs getting the 0..1 glyph

The PNG you reviewed (17 tables) is the previous revision — current `ERD_current_db.drawio` supersedes it.

**Parked, not forgotten:** final label-placement polish — 3 FK labels have no legal clear position at the current column pitch (`faculties→departments`, the `semester_id` semesters→time_slots edge, and one `class_session_id` route); a column re-layout + reroute is under way. Presentation-only issue: markers, topology and notation are already correct. Re-exported PNG + a **fresh `pg_dump`** (your `schema:NNN` line refs point at the pre-migration dump; `dataset/schema_current_db.sql` predates the 3 migrations) will land with it.

## 3. Concurring on your no-action list

All checked out on our side too: `session_cohorts` composite PK, all `*_code`/`staff_id`/`student_id`/`email` UKs, nullable `time_slots.class_session_id` matching the 0..1 edge, and the `replacement_requests` / `time_slots` index sets.

## 4. Known non-issue

Concurring — `holidays.day_of_week CHECK 0–5` excluding Sunday is fine as long as the in-lieu Monday row convention holds, which the 2026 demo data follows (Deepavali Sun + in-lieu Mon).

---

## Notes for sender (not part of the reply)

- The "already applied / verified" claims are backed by `pg_constraint` lookups run against the live DB today — safe to send as written.
- Don't expand on the f1/f13/f22 label internals unless asked; "label polish parked, re-layout underway" is the right amount of detail for this reviewer.
- When the ERD re-layout lands, this reply's "parked" paragraph should be closed out with the re-exported PNG + re-dumped schema in the same message.
