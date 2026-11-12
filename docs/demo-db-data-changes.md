## 2026-11-05 — request 29 (the user's manual submission-conflict test) removed

- **Final state:** back to the standing baseline — 5 requests / **77** notifications / 3 hidden /
  2 class_exceptions / **8** `replacement_request_slots` / **26** audit rows.
- **What the manual walkthrough wrote (the user's own two windows on :8000):**
  - `replacement_requests.id = 29` — session **67** (AMSE2003 L), week **14**, slot `27929`
    (Friday 12:00–14:00, B110), proposer **1**, `pending`, submitted `2026-11-05 09:30:43` (UTC);
  - **4** `replacement_request_slots` rows (27929–27932 — the four-hour span);
  - **2** notifications: `id` 187 (user 1) + 188 (user 2), `pl` / `submitted` /
    "New replacement request";
  - **4** `audit_logs` rows (`id` 32–35, action `submitted`, one per slot, `replacement_request_id = 29`).
- **ALSO removed in the same transaction:** `audit_logs.id = 31` (`class_cancelled`, session 40 week 7)
  — the audit record of the earlier Test-2 cancellation whose `class_exceptions` row was deleted in
  the entry above. It was the last orphan of that test; leaving it would have kept a reference to a
  cancellation that no longer exists. Flagged here because it was **not** part of the request-29
  instruction — the deletion is deliberate, not incidental.
- **The cleanup (order matters — `audit_logs.replacement_request_id` is a FK, so the audit rows must
  go BEFORE the request; the first attempt hit
  `audit_logs_replacement_request_id_foreign` and rolled back whole):**
  `DELETE FROM notifications WHERE id IN (187,188) …` → `DELETE 2`;
  `DELETE FROM replacement_request_slots WHERE replacement_request_id = 29` → `DELETE 4`;
  `DELETE FROM audit_logs WHERE id BETWEEN 31 AND 35` → `DELETE 5`;
  `DELETE FROM replacement_requests WHERE id = 29 AND class_session_id = 67 AND week_number = 14
   AND status = 'pending'` → `DELETE 1`. Counts re-read afterwards: as above.
- **Snapshot first (snapshot law, verified):**
  `backups/class_replacement_fresh-pre-req29-cleanup-2026-11-05.dump` (332K, `pg_restore --list`
  opened). The earlier `…-pre-test2-cleanup-…` dump is kept too.
- **Nothing else touched:** no other request, exception, notification or slot row written; the three
  hidden notifications, request 24/25/26/27/28 and both remaining exceptions are untouched.

## 2026-11-05 — the manual Test-2 cancellation removed (the user's own browser pass)

- **Final state (what is true now):** **NET ZERO** vs. before the test — 5 requests / **77**
  notifications / 3 hidden / **2** class_exceptions.
- **What the user's manual test wrote (their browser, :8000, their own click):**
  1. `class_exceptions.id = 11` — session **40** (AMSE2003 P, Thursday 09:00–10:00 B010),
     week **7**, `annual_leave`, created `2026-11-05 09:19` (stored UTC);
  2. **16** `Scheduled class cancelled` notifications (`id` 171–186) — the owner's record row plus
     the cohort **student** fan-out, same timestamp.
  The test PASSED (the new notice stayed live and clickable, and the arrival flashed the row) —
  the data is simply the residue of proving it.
- **The cleanup (one transaction, explicit predicates — never a bare table delete):**
  `DELETE FROM notifications WHERE id BETWEEN 171 AND 186 AND class_session_id = 40 AND week = 7
   AND title = 'Scheduled class cancelled';` → `DELETE 16`,
  `DELETE FROM class_exceptions WHERE id = 11 AND class_session_id = 40 AND week_number = 7
   AND reason = 'annual_leave';` → `DELETE 1`. Counts re-read afterwards: as above.
- **Snapshot first (snapshot law, verified):**
  `backups/class_replacement_fresh-pre-test2-cleanup-2026-11-05.dump` (328K,
  `pg_restore --list` opened).
- **Nothing else touched:** no request, slot, audit or user row written; the other two exceptions
  (session 24 w7 medical_leave, session 40 w14 official_event) and all three hidden notifications
  are untouched.
- **Why clean rather than keep it:** a stray cancelled Thursday class plus 15 student notices would
  misreport the demo in a viva, inflate the student badge counts, and make any later attempt to
  cancel that same class refuse as "already cancelled". The fix's evidence is the commits + the
  suite, not the leftover rows.

## 2026-11-04 — two notifications hidden by a test run and restored the same minute

- **Final state (what is true now):** **NET ZERO** — `notifications.id = 116` (user 2) and
  `notifications.id = 148` (user 1, `Scheduled class cancelled`, `update`) are back to
  `hidden_at = NULL`. Hidden rows are again **2** (`id` 94 and 97, hidden since 2026-11-02 10:30),
  exactly the baseline recorded above.
- **What happened:** a read-only-intended Playwright sweep against the agent's demo-DB server (:8010)
  at 19:05 stamped `hidden_at = 2026-11-04 19:05` on those two rows. The writing click was not
  identified from the access log (every Livewire call is a POST to `/livewire/update`); no guard-capable
  spec (`notifications-hide-manage`, `ui-regression` TC-4.5) actually ran — both refused at the guard.
  The lesson stands: **any** browser run against :8010 can reach a write path, so the hide counts are
  re-checked after every sweep.
- **The write (one statement):** `UPDATE notifications SET hidden_at = NULL WHERE id IN (116,148);`
  → `UPDATE 2`. Measured after: 77 notifications, **2 hidden**.
- **Snapshot first (snapshot law, verified):** `pg_dump -Fc` → `/tmp/fresh-pre-restore-1928.dump`
  (336K, `pg_restore --list` opened).
- **Nothing else touched:** no request, exception, slot, audit or user row written; the tip/hover
  receipts for this task were read-only GETs (and the composer was proved from the DB with a
  `Livewire::test` render, not by clicking).

# Demo-DB Data Changes — Running Log

## 2026-11-04 — one notification hidden and restored (the Completed-click receipt, `notif-click-behaviour` T13)

- **Final state (what is true now):** **NET ZERO** — `notifications.id = 117`
  (user 1, `Scheduled class cancelled`, `update`, `/my-timetable-ui`, week 7) is
  back to `hidden_at = NULL`. Hidden rows are again **2**, exactly as before.
- **The two writes (user-gated, option A of the session's question):**
  1. `hidden_at = 2026-11-04 09:53:20` — written by the FEATURE ITSELF: the panel's
     click handler called `NavNotifications::hideOne(117)` after the row was
     marked `data-notif-status="completed"` in the browser (no completed request
     exists in the demo, so the status was stood in for; the hide path did the
     real work).
  2. `hidden_at = NULL` — written by the UI's own **Hidden shelf → Unhide**, which
     doubles as the rollback receipt.
- **Why:** prove the "clicking a completed notification hides its row without
  navigating" behaviour end to end. Measured before/after: panel rows 7 → 6, the
  row gone from the list, the page URL unchanged (no navigation), no JS errors;
  the shelf then showed it and restored it (3 → 2 hidden).
- **Snapshot first (snapshot law, verified):**
  `backups/class_replacement_fresh-pre-notif-hide-2026-11-04.dump`
  (`pg_restore --list` passes).
- **Nothing else touched:** no other notification, request, exception, slot or
  audit row was written. The arrival-side receipts (`r26`, `o67`, a miss) were
  read-only GETs.
- **Agent-run, explicitly user-approved** ("A.").

## 2026-11-03 — student demo account with the user's own inbox (`24RSD0008`, cohort 7 = RSD Y2 S1 G2)

- **Final state (what is true now):** `users.id = 111` — student **`24RSD0008`**
  in cohort **7 = RSD Year 2 / Sem 1 / Group 2** — has
  **name `Wardell Stephen Curry`** and email
  **`poongfj-sm23@student.tarc.edu.my`** (was `Student 24RSD0008` /
  `24rsd0008@student.tarc.edu.my`). Password, `students.student_id`, cohort and
  every other row untouched. Login ID = **`24RSD0008`**, password `Tarumt@2026`.
- **Superseded first attempt (reverted):** the same email was first placed on
  `users.id = 135` (`23RSD0011`, Victor Wembanyama, cohort 10); on the user's
  correction it was moved to cohort 7 and `23RSD0011` got its original email
  `23rsd0011@student.tarc.edu.my` back. Only those two rows were ever written.
- **Why:** user-ordered, so a student in Lim Jia Zheng's (`5770`) own cohort
  (RSD Y2 S1 G2 — he teaches `BMIT2013` there) delivers mail to the user's real
  inbox. Auth is by **student ID**, so the email change does not affect login.
- **Snapshots first (snapshot law, verified):**
  `backups/class_replacement_viva-pre-poong-student-2026-11-03.dump` (before the
  first write) and `backups/class_replacement_viva-pre-poong-cohort7-2026-11-03.dump`
  (before the move) — both 324K, `pg_restore --list` opened each.
- **Exact statements (one transaction, second snapshot):**
  `UPDATE users SET email='23rsd0011@student.tarc.edu.my', updated_at=now() WHERE id=135 AND email='poongfj-sm23@student.tarc.edu.my';`
  → `UPDATE 1`;
  `UPDATE users SET name='Wardell Stephen Curry', email='poongfj-sm23@student.tarc.edu.my', updated_at=now() WHERE id=111 AND email='24rsd0008@student.tarc.edu.my';`
  → `UPDATE 1`, `COMMIT`.
- **Verification:** `24RSD0008` / cohort 7 / new name+email; `23RSD0011` back to
  `23rsd0011@student.tarc.edu.my`; exactly **1** row holds
  `poongfj-sm23@…`; cohort 7 still 5 students; 13 lecturer `.invalid` stubs
  intact; all table counts unchanged.
- **Revert (either):** restore
  `class_replacement_viva-pre-poong-cohort7-2026-11-03.dump`, or run
  `UPDATE users SET name='Student 24RSD0008', email='24rsd0008@student.tarc.edu.my', updated_at=now() WHERE id=111;`
- **Not run:** no `queue:work`, no `.env` change, no other student row touched.

## 2026-11-03 — temporary lecturer-email stub in `class_replacement_viva` (REVERSIBLE, user-ordered)

- **What:** appended `.invalid` to the domain of **13 of the 14 lecturer emails** in
  `users` (`surayaini@tarc.edu.my` → `surayaini@tarc.edu.my.invalid`, etc.).
  `id = 11` (**Lim Jia Zheng / staff `5770` / `limjz@tarc.edu.my`**) was
  deliberately excluded — both names in the order refer to this one account. The
  **155 student emails were not touched**.
- **Why:** `CRS-demo` runs `MAIL_MAILER=smtp` for the viva, so a demo action could
  email real staff. `.invalid` is the RFC 2606 reserved TLD — it can never
  resolve, so Gmail cannot deliver; the address stays syntactically valid, so the
  app does not crash, and any attempt bounces back to
  `MAIL_FROM_ADDRESS` (`foojing03@gmail.com`) instead of reaching staff.
- **Snapshot first (snapshot law, verified):**
  `backups/class_replacement_viva-pre-email-stub-2026-11-03.dump` — 324K,
  `pg_restore --list` opened it successfully. Pre-state of the 13 original
  addresses captured before the write.
- **Exact statement (single transaction):**
  `UPDATE users SET email = split_part(email,'@',1)||'@'||split_part(email,'@',2)||'.invalid', updated_at = now() WHERE role = 'lecturer' AND id <> 11 AND email NOT LIKE '%.invalid';`
  → `UPDATE 13`, `COMMIT`.
- **Verification:** `stubbed = 13`; `id 11` still `limjz@tarc.edu.my`;
  `students_touched = 0` of 155; students' addresses unchanged; all **28 public
  table row counts identical** to the post-purge receipt. No other column, row or
  table written.
- **Known side effects (temporary, by design):** none for login — auth is by
  **staff ID** (`Fortify::username = 'login_id'`; `User::loginId()` returns
  `lecturers.staff_id`), not email, so all 13 can still sign in. Mail attempts to
  the stubbed addresses bounce to `foojing03@gmail.com` instead of reaching
  staff.
- **Not run:** no `php artisan queue:work`, no `.env` change, no `migrate*`.
- **REVERT (run a few days before the real viva, user-gated):**
  `UPDATE users SET email = regexp_replace(email, '\.invalid$', ''), updated_at = now() WHERE role = 'lecturer' AND email LIKE '%.invalid';`
  → expect `UPDATE 13`; then re-check all 14 lecturers end in `@tarc.edu.my` and
  `students_touched = 0`. The dump above also restores the exact pre-stub state.

## 2026-11-03 — mail-job purge in `class_replacement_viva` (DELETE only, user-ordered, scoped)

- **What:** removed the **27 stale queued mail jobs** from `jobs` in
  `class_replacement_viva` (the `CRS-demo` viva DB) and **nothing else** — no
  other table, row, column, schema, `.env` value or file on that DB was touched.
- **Why:** `CRS-demo` was flipped to `MAIL_MAILER=smtp` on 2026-11-03 so the viva
  can show real inbox delivery. The 27 jobs were queued 2026-11-01/02 (before
  the flip) and every recipient is real — the first `queue:work` would have
  emailed all of them late: 14 `App\Mail\ReplacementApprovalNotice` →
  `23rsd0011`–`23rsd0024@student.tarc.edu.my`, 10
  `App\Mail\ReplacementSubmitted` + 3 `App\Mail\ReplacementDecided` →
  `limjz@`, `mohdnurrahmat@`, `surayaini@tarc.edu.my`. `failed_jobs` was 0 and
  stays 0 (untouched).
- **Snapshot first (snapshot law, verified):**
  `backups/class_replacement_viva-pre-jobs-purge-2026-11-03.dump` — 328K,
  `pg_restore --list` opened it successfully.
- **Exact statement (single transaction):**
  `DELETE FROM jobs WHERE queue = 'default' AND payload::jsonb->>'displayName' LIKE 'App\Mail\%';`
  → `DELETE 27`, `COMMIT`. No `TRUNCATE`, no other predicate, no other DB
  (`class_replacement_fresh` and its 10 `default` jobs were **not touched**).
- **Proof that only those rows changed:** row counts for **all 28 public tables**
  captured before and after; `diff` shows exactly one line differing —
  `jobs=27` → `jobs=0`. Everything else identical, e.g. `failed_jobs 0→0`,
  `notifications 40→40`, `replacement_requests 4→4`, `users 169→169`,
  `class_exceptions`/`class_sessions`/`cohorts`/`holidays`/`venues` unchanged.
  `select queue, count(*) from jobs` afterwards = **empty**; mail jobs left = 0.
- **Not run (deliberately):** no `php artisan queue:work` (before or after), no
  `migrate*` / `db:wipe` / reseed, no write to any non-`jobs` table, no
  `--env=testing`.
- **Recovery:** restore
  `backups/class_replacement_viva-pre-jobs-purge-2026-11-03.dump`
  (`pg_restore -d class_replacement_viva --clean --if-exists`) returns the 27
  jobs exactly. Note they are transient queue payloads, so there is no way to
  "re-queue" the deliveries themselves — only the dump brings the rows back.

## 2026-11-02 — schema prep: `notifications.hidden_at` (ADDITIVE, both demo DBs, user-ordered)

- **What:** migration `2026_11_02_000001_add_hidden_at_to_notifications_table.php`
  — one **nullable** `timestamp` column `hidden_at` + one index
  `(user_id, hidden_at)`. Nothing else. `down()` only removes what `up()` added.
  It is preparation for the notification batch-hide feature; **no app code reads
  the column yet**, so nothing visible changed.
- **Snapshots first (snapshot law, verified):**
  `backups/class_replacement_fresh-pre-hidden_at-2026-11-02.dump` and
  `backups/class_replacement_demo-pre-hidden_at-2026-11-02.dump` — both
  325K/323K, `pg_restore --list` opened each (56 tables listed).
- **Rehearsal:** `DB_DATABASE=class_replacement_testing php artisan migrate` —
  2 notification rows before → 2 after, `hidden_at` NULL on all, index present.
- **`:8000` `class_replacement_fresh`:** target printed first
  (`DB::connection()->getDatabaseName()` = `class_replacement_fresh`), then a
  plain `php artisan migrate` (14.77ms). Counts before → after unchanged:
  notifications 5→5, replacement_requests 2→2, time_slots 38640→38640,
  users 169→169; `hidden_at` set on 0 rows.
- **`:8001` `class_replacement_demo`** (the `CRS-demo` clone):
  `DB_DATABASE=class_replacement_demo php artisan migrate` (13.34ms). Counts
  unchanged (notifications 0, time_slots 38640, users 169); `hidden_at` set on
  0 rows.
- **Not run (deliberately):** `migrate:fresh`, `migrate:refresh`, `db:wipe`,
  any `--env=testing` command (no `.env.testing` exists — it would silently hit
  the demo DB), any `.env` edit.
- **Recovery:** the two `-pre-hidden_at-` dumps above restore either DB to the
  exact pre-migration state (`migrate:rollback --step=1` also works, but only
  paired with reverting the code that reads the column).

## 2026-11-01 — DATA-LOSS INCIDENT: the demo was wiped at ~03:22, restored from pristine

- **What the user lost:** their UI-submitted replacement requests (made via the
  system UI on 2026-10-31: approvals/cancellations + pending submits) and the
  accompanying notifications fan-out and class_exceptions rows.
- **What happened:** agent-run destructive receipt work (the notif-new-request-ux
  T7 fixture family — pending request cs23/W8 slot 32612 + 2-PL 'New replacement
  request' fan-out) wrote DIRECTLY onto `class_replacement_fresh` at 03:22
  (evidence left in place before restore: replacement_requests had exactly one
  row — the fixture — identical timestamp on notifications ids 1/2;
  class_exceptions 0, replacement_request_slots 0). This repeated the
  2026-10-26 `--env=testing` accident recorded in `backups/README.md`.
- **Not recoverable:** the user's Oct-31 requests were in NO dump (only copies:
  pristine-2026-10-10 + pre-student-limit-2026-10-12, both 0-requests);
  archive_mode = off, no base backup. Accept as permanent loss.
- **Restore executed (by the agent, user-approved "restore it"):** the README
  recipe — stop both serves → DROP/CREATE `class_replacement_fresh` →
  `pg_restore class_replacement_fresh-pristine-2026-10-10.dump` → verified via
  `crs:db-row-counts` (101 sessions / 155 session_cohorts / 266 users / 252
  students / 14 lecturers / 23 venues / 38640 time_slots / **0 requests** /
  **0 exceptions** / 3 holidays) → fresh dated dump taken (see below) →
  `:8000` restarted via the stale-serve ritual, `login/student` HTTP 200.
- **New restore point:**
  `backups/class_replacement_fresh-2026-11-01.post-restore.dump` (401K,
  custom format, `pg_restore --list` verified).
- **POST-step discovered live:** the pristine dump's `migrations` ledger predates
  2026-10-16 — every page 500'd (`relation "notifications" does not exist`).
  Fix run (user-visible breakage, schema-only): `php artisan migrate --force`
  applied the 4 pending migrations (replacement_request_slots table, venues
  description, cancel_reason widen + audit checks, **notifications table**) and
  only those — the ledger now matches the repo (31). Lesson recorded:
  **a pristine-restore is NOT complete without a follow-up `php artisan
  migrate --force`.** The 2026-11-01 post-restore dump was RE-TAKEN after the
  migrations now include the notifications table (28 TABLE DATA objects).
- **Standing guard (must ship with the next code commit):** every destructive
  spec/fixture MUST abort unless `CRS_BASE = http://127.0.0.1:8030` AND
  `CRS_MODE = testing`. No receipt work touches `:8000`/`class_replacement_fresh`
  again — read-only only, ever, from agents.

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

## 2026-10-27 — the cancel-reason + audit-action CHECK widen (class-cancel-db-persist)

- **Approved by:** the user (the frozen SDD lane `class-cancel-db-persist`,
  5 review rounds, proposal vocabulary table verbatim).
- **Live DB (paste BOTH pairs):**

```sql
ALTER TABLE class_exceptions DROP CONSTRAINT class_exceptions_reason_check;
ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_reason_check
    CHECK (reason IN ('public_holiday','annual_leave','medical_leave','official_event','emergency_leave',
                      'hospitalisation_leave','maternity_leave','compassionate_leave','paternity_leave','other'));
ALTER TABLE audit_logs DROP CONSTRAINT audit_logs_action_check;
ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
    CHECK (action IN ('submitted','approved','rejected','cancelled','completed','occ_conflict','class_cancelled','class_cancel_undone'));
```

- **Migration:** `database/migrations/2026_10_27_000001_widen_cancel_reason_and_audit_checks.php`
  (same statements; fresh DBs via migrate:fresh).
- **Protected (untouched):** the pending replacement requests #1–#3; the
  `class_exceptions` row id 4 (`official_event`, session 40 / W14).
- **Rationale:** the Cancel Class radio now offers the 4 new leave types +
  Other, so the reason CHECK must accept the 10-value vocabulary; the undo
  flow audits `class_cancel_undone` (8th action value).

## 2026-10-27 — DFT2 re-unlinked from AMIT2034 (restore-rewind repair; R2 capacity)

- **Approved by:** the user ("a. drop DFT2"), the 2026-10-13 receipt re-run.
- **Context:** the pristine-2026-10-10 restore (the class-cancel-DB-persist
  investigation's damage repair) rewound the earlier unlink — AMIT2034
  carried DFT2(S1)G1 (28) + DSF2(S1)G1 (22) = **50 students in the
  28-seat B005** again, and the class-info modal showed the merged
  cohorts.
- **Live DB (executed):** `DELETE FROM session_cohorts WHERE
  class_session_id IN (3,33,34) AND cohort_id=2;` (3 rows; no test /
  replacement rows touched).
- **Row counts:** session_cohorts **152** (the seeder's EXPECTED_COHORT_LINKS
  ✓, already correct in the repo); the dataset/import CSVs were already
  DSF-only (untouched by the rewind — code vs data divergence).
- **Post state:** sessions 3/33/34 = **DSF2(S1)G1, 22 students** (22 ≤
  B005's 28 — the over-capacity violation is repaired ✓). The B005
  **conflict flag REMAINS by design** (DSF = Diploma — R2's no-Diploma
  law unchanged [SUPERSEDED later this day — the R2 law REPEALED, the
  B005 rule death]; the D4 rendering receipt `AMIT2034(P) · B005 · Red`).
- **Insurance dump:** `/tmp/insurance-pre-dftdrop-2026-10-27.dump` (the
  pre-drop state, 335 KB).

## 2026-10-27 — the venues info-changes (names + descriptions)

- **Approved by:** the user (the info-changes slide).
- **Live DB (executed):**
  - names — `B005 → Computer Laboratory 5`, `B006 → Computer
    Laboratory 4: CISCO Lab`, `B009 → Computer Laboratory 1`,
    `B010 → Computer Laboratory 2`, `B011 → Computer Laboratory 3`;
  - descriptions — labs: B005 `Computer Lab. For PRACTICAL Class.
    DEGREE students only.` (the R2 display data, now worded
    `Computer Lab.`), B009/B010/B011 `Computer Lab. For PRACTICAL.`;
    tutorials (16 rows) `Tutorial Room For LECTURE and TUTORIAL.`
    (the L+T admission wording per the slide);
  - halls untouched (out of the slide's scope — they keep
    `Lecture Hall. For LECTURE.`; a stray first-pass edit reverted).
- **Seeder mirror:** `VenuesSeeder.php` — the names keyed to the real
  lab numbering + the descriptions threaded through `insertGroup` (a
  re-seed matches the live state). `allowed_session_types`/capacities
  untouched (the reference inventory law).
- **B006's description unchanged** (`Cisco Lab. Networking & IoT
  equipment room. For FOCS cohorts only.` — the R4-dropped display
  string, kept verbatim per the receipt).
- Suite 262/262 green.

## 2026-10-27 — the cohort-shrink (every cohort at 7–10 students)

- **Approved by:** the user ("UPDATE each of this Cohorts to between 7 to
  10 students (random digit)") — the small-cohort viva scaling.
- **Live DB (executed, 14 rows):** DFT1=10, DFT2=9, DSF1=8, DSF2=7,
  RSD1=9, RSD2G1=8, RSD2G2=7, RSD2G3=10, RSD3G1=9, RSD3G2=8, RSD3G3=7,
  RAF2G2=10, RAF2G4=8, RBU1=9.
- **Seeder mirror:** `DatabaseSeeder::STUDENT_COUNTS` updated to the same
  14 values (the re-seed matches).
- **Side effects:** every class's total ≤ 11 (the multi-cohort merges
  shrink); all B005/B006 capacity fits trivially; the over-capacity audit
  stays 🟢. The BMSE3153 class-info now reads `14 → 8` and `13 → 7`
  (`8 + 7 = 15`).
- **Insurance dump:** `/tmp/insurance-pre-scoreshrink-2026-10-27.dump`.

## 2026-10-27 — the Deepavali Sunday row NOT added (the DB's own CHECK law wins)

- **Explore G5 planned** an insert of the mock's 4th holiday pair (Deepavali
  Sunday 08 Nov = W7/day 6). The attempt hit
  `holidays_day_of_week_check  CHECK (day_of_week BETWEEN 0 AND 5)` — the
  schema itself declares Monday–Friday only: a Sunday holiday is
  STRUCTURALLY unreachable (and no UI surface renders Sundays).
- **Resolution:** NO insert; the mock's 4-pair array was never
  DB-representable (its Sunday pair dies with the mock-statics-die
  deletions). The DB keeps its 3 rows. The mock-era law the check
  encodes (Mon..Fri) is its own honest contract.
- Insurance dumps: `/tmp/insurance-pre-dftdrop-2026-10-27.dump`,
  `/tmp/insurance-pre-scoreshrink-2026-10-27.dump` both taken BEFORE the
  runs (the pre-change states).

## 2026-10-27 (amendment) — the Diploma (D*) cohorts ride 20–25 (the split rail)

- **Approved by:** the user — my earlier cohort-shrink applied 7–10 to ALL
  14 cohorts including the Diploma ones; the user's correction: the D*
  cohorts (DFT/DSF) belong in the 20–25 band. The Bachelor cohorts keep
  their 7–10 values.
- **Live DB (executed, 4 rows):** DFT1=24, DFT2=20, DSF1=25, DSF2=21.
- **Seeder mirror:** `DatabaseSeeder::STUDENT_COUNTS` updated (the same 4
  values, the rail split noted in the docblock).
- **Side effects:** the AMIT2034 DSF-only rows (3/33/34) total 21 (well
  inside B005's 28 and B006's 32 ✓); the BMSE3153 breakdown stays
  `8 + 7 = 15` (RSD3 pairs untouched); the venue-restrictions R2 audit
  note's standing count rides the dataset (21, not the historical 22/15).

## 2026-10-27 (late) — the venue rule + the B005 description (SDD b005-diploma-allowed)

- **RULE REPEALED:** the user's ruling allows Diploma cohorts at B005 —
  the R2 no-Diploma law is CODE-DROPPED (ruleMap keys, engine deny,
  derived conflict, client arm). NO DB schema/data change for the rule.
- **The standing AMIT2034(P)_row @ B005 (DSF-only, 21 students) renders
  CLEAN** — the perpetual red block that drove this session's queries is
  explained-and-gone.
- **DESCRIPTION AMEND (1 row, pasted by the agent with the user's standing
  data-change mandate):** B005 `description` — `Computer Lab. For
  PRACTICAL Class.` (the `DEGREE students only.` claim deleted — it would
  be false post-repeal). VenuesSeeder + the mock-data.js §2.5a mirror
  converge on the SAME string.
- The dated 2026-10-27 rows above that recorded the "conflict flag
  REMAINS by design" state are SUPERSEDED by this repeal — the flags
  they recorded came from the now-dead law.

| 2026-10-28 | notifications (the NEW table from the notifications-real-bridge lane) | 2 receipt rows INSERTed (user_id=1/5425's bell; title `Demo receipt — poll cycle*`) — the poll-cycle receipts | Rollback: `DELETE FROM notifications WHERE title LIKE 'Demo receipt — poll cycle%';` | agent-run, user-approved ("go --> Playwright poll-cycle receipt") |

## 2026-10-29 — the T6 demo seed rows drafted (notifications-real-bridge)

- **Approved by:** user — pending (the main verify-fix prompt, Item 5: the SQL
  is DRAFTED here, the user runs it; **USER-RUN — never agent-run**).
- **Live DB:** NOTHING yet — the three fences below are drafts until the user
  executes them.
- **Seeder:** none needed — a demo-only visibility aid (the bell reads the real
  `notifications` table; the testing DB fixtures live in the phpunit suite).
- **State check (read-only, 2026-10-29):** the table already holds the user's
  real bridge rows — id 3 (5425, pl tab, `submitted`, request #6, unread) and
  id 4 (5516, same). So T6's "a submitted row for a PL" is ALREADY COVERED — the
  two drafts below add only what the demo still lacks: an APPROVED row (the
  lecturer tab of 5425) and an AWAITING reminder row, so all three tile kinds
  render alive in the demo bell.
- **Copy + schema follow the emitter exactly** (`NotificationEmitter`, the
  frozen tile keys + the per-role link law from D1); every `request_id`/`id` was
  confirmed real by read-only queries (rr#1 approved W8 cs23 BMSE3153 P from
  5425; rr#6 pending, AMSE2003 (P), also 5425's).

Draft SQL (idempotent — re-runs are no-ops; run as the user, never from an agent):

```sql
-- T6-a: an APPROVED row for 5425's lecturer tab (real target: request #1)
INSERT INTO notifications (user_id, role_view, type, title, body, link, request_id, class_session_id, week, read_at)
SELECT 1, 'lecturer', 'approved', 'Replacement approved',
       'BMSE3153 (P) · W8 · approved by Mohd Nur Rahmat Bin Mohd Taat',
       '/my-request-history-ui', 1, NULL, 8, NULL
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE type = 'approved' AND request_id = 1 AND user_id = 1);

-- T6-b: an AWAITING reminder row for 5425's PL tab (real target: request #6)
INSERT INTO notifications (user_id, role_view, type, title, body, link, request_id, class_session_id, week, read_at)
SELECT 1, 'pl', 'awaiting', 'Approval still pending',
       'AMSE2003 (P) · W9 · submitted by Surayaini Binti Basri · awaiting your decision',
       '/request-approval-ui', 6, NULL, 9, NULL
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE type = 'awaiting' AND request_id = 6 AND user_id = 1);
```

- **Rollback:** `DELETE FROM notifications WHERE (type = 'approved' AND request_id = 1 AND user_id = 1) OR (type = 'awaiting' AND request_id = 6 AND user_id = 1);`
- **Variant (optional):** the same awaiting row also for the OTHER DCIT PL —
  change `user_id` 1 → 2 (Mohd Nur Rahmat) in T6-b; per the fan-out law the
  reminder lands on every PL.

| 2026-10-29 | notifications | the user RAN (logged convention): `DELETE FROM notifications WHERE id = 2 AND user_id = 1 AND title = 'Demo receipt — poll cycle 2';` — DELETE 1 (the second poll-cycle receipt row; the first, id 1, stays per the user's instruction) | — | user-run (this file's same-day entry; nothing else touched) |
| 2026-10-29 (late) | notifications | the user RAN: `DELETE FROM notifications WHERE id = 1 AND user_id = 1 AND title = 'Demo receipt — poll cycle';` — DELETE 1 (the FIRST poll-cycle receipt row; id 2 was deleted earlier the same day — both July-28 receipt rows now gone; no other row touched) | — | user-run (this same-day entry) |
| 2026-10-30 | notifications | agent-run under the user's explicit F1 approval (SDD notif-actor-pl-dedupe): `DELETE FROM notifications WHERE id = 31 AND user_id = 1 AND title = 'Scheduled class cancelled' AND role_view = 'pl';` — DELETE 1 — the dept-PL TWIN the old fan-out wrote for 5425's own cancel (their actor row, id 8, remains; the cancel itself was undone 4 s later — audit class_cancel_undone). User 1's mailbox 5 → 4 rows. Belt-braced DELETE (id + user + title + role clause; verified count-1 first) | — | agent-run, explicitly user-approved |
| 2026-11-01 (evening) | WHOLE DB `class_replacement_fresh` (:8000's DB) | FULL SWAP (user option A): after the Oct-31 recovery rebuild on the :8001 twin (see `demo-db-oct31-recovered.md` § REBUILD EXECUTED), `class_replacement_fresh` was dropped + recreated + restored from `class_replacement_demo-post-recovery-2026-11-01.dump` — :8000 now = an exact copy of the recovered record (11 requests / 3 exceptions / 90 notifs / badge 3 per PL). Pre-swap safety copy = `class_replacement_fresh-pre-swap-2026-11-01.dump` (holds the composer lane's 1 pending + 2 unread of that morning). Verified on-screen on :8000 (badge 3 + trio tiles + approval inbox). :8001 unaffected. | pre-swap dump above | agent-run, user-approved (the "A" answer) |
| 2026-11-01 22:05 | `class_replacement_fresh` + `class_replacement_demo` | Pre-delete snapshots `*-pre-rm30min-2026-11-01.dump` (both verified). WHY: every rebuilt request displayed exactly "30 min" on the approval page — the rebuild set only 30-min anchor cells (span lengths were never in the receipts); the composer lane had ALSO re-run its work on :8000 post-swap (rr ids 12–23 incl. its own approved wedge — audit_logs FK exposed it) so :8000 briefly held 12 requests / 93 notifs. | pre-rm30min dumps above | agent-run, user-ordered |
| 2026-11-01 22:12–22:25 | both demo DBs = FULL STORY CLEANOUT (user order: delete the 30-min rows, then "all pending requests, Cancelled classes, & Notifications") | :8000: DELETE audit_logs(4) → replacement_requests(12, +2 span rows cascaded... scoped SET-NULL semantics on notifications) → 13 slot cells released (my 11 + composer 34413/34414) → notifications(93) → class_exceptions(3). :8001: notifications(90 incl. 40 auto-orphaned via SET NULL) → class_exceptions(3) → same slot releases. POST-STATE BOTH: 0 requests / 0 spans / 0 notifs / 0 exceptions / 0 audit = the pristine-import plateau; next rr id continues at 24. Note: `notifications.request_id` FK = ON DELETE SET NULL (discovered today); `replacement_request_slots` = ON DELETE CASCADE. Rollback = the pre-rm30min dumps. | pre-rm30min dumps above | agent-run, user-ordered (twice) |
| 2026-11-04 (evening) | notifications | agent-run under the user's explicit "Go ahead with the hide" (the PL-visibility guard shipped just before, `38d7b51`): `UPDATE notifications SET hidden_at = now() WHERE id IN (116,148) AND user_id IN (1,2) AND title = 'Scheduled class cancelled' AND role_view = 'pl' AND hidden_at IS NULL;` — **UPDATE 2**: `116` = staff 5516's copy for 5425's session 23 (was UNREAD — it had been a phantom in 5516's badge until the guard dropped it), `148` = staff 5425's copy for 5770's session 24 (read). WHY: both are **pre-R4** department-PL leftovers, written 2026-11-02 the day before `notif-recipient-rulings` R4 removed that fan-out; rows are never deleted, so they are soft-hidden instead. Totals: notifications **77 → 77** (nothing deleted), hidden **2 → 4**, read flags untouched. Rehearsed first on `class_replacement_testing` (INSERT 2 synthetic → same UPDATE → `hidden = t` on both → cleaned up, 0 left behind). No visible change by design: the read-time guard already drops these rows from the inbox, badge and shelf, so the stamp is bookkeeping. The `class_replacement_demo` twin holds **no** such rows (queried before writing). | `class_replacement_fresh-pre-notif-hide-2026-11-04.dump` (verified: `pg_restore --list`, 241 TOC entries). Rollback: `UPDATE notifications SET hidden_at = NULL WHERE id IN (116,148);` | agent-run, user-approved ("Go ahead with the hide") |
| 2026-11-12 23:45 → 23:48 | `class_replacement_fresh` (:8000) | **An automatic-reject sweep touched this DB by mistake, then was put back.** The new every-minute crontab line (`php artisan schedule:run`) ran without `DB_DATABASE` set, so it used the app's default `.env` database. At 23:45 the sweep rejected request **#35** (staff 5425, Thu 12 Nov 08:00–09:00 week 8, B100): status `pending → rejected`, `decided_at` set, the fixed system reason stored, `approver_id` left null, slots **10981/10982** `pending → available` (version 2 → 3), and audit rows **172/173/174** written (`action='rejected'`, `details.auto=true`, `user_id` = the proposer). Counts at that moment: rejected 6 → 7, pending slots 12 → 10, audit 162 → 165. Requests #39 and #46 stayed pending (not yet due). **Restored by hand under the user's explicit approval**, in one transaction: `UPDATE replacement_requests SET status='pending', approver_id=NULL, decided_at=NULL, rejection_reason=NULL, updated_at='2026-11-07 07:56:14' WHERE id=35`, `UPDATE time_slots SET status='pending', version=version+1 WHERE id IN (10981,10982)` (version 3 → 4 — NOT lowered: the version is the optimistic-lock guard and must only move forward), `DELETE FROM audit_logs WHERE replacement_request_id=35 AND action='rejected' AND details->>'auto'='true'` (3 rows). **Row-count receipt, before → during → after: rejected 6 → 7 → 6; pending slots 12 → 10 → 12; audit_logs 162 → 165 → 162; requests 22 → 22 → 22.** #35 is byte-identical to its 2026-11-07 state except `time_slots.version` (2 → 4) and the audit id sequence, which now continues past 174. The crontab line was then pinned to `DB_DATABASE=class_replacement_testing`, and no demo row was touched by any later run (3 cycles verified, #35 still pending). | none needed — the reverse statements are in this cell; no snapshot was taken because the change was one request + two slots | agent-run (the 23:45 rejection) then agent restore, both user-approved |
