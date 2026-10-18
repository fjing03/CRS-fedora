# Spec: notifications (3 queued Mailables + runbook + NFR 1.4 drain)

**Frozen baseline:** proposal items 6 + 7 + the submit-side hook rider; design D8, D9 (and D1's
after-commit rider). **FR anchors:** FR 4.15 (PL on submission), FR 2.13 (lecturer on
outcome), FR 1.9 (students on timetable updates), FR 4.16 (database-backed queue).
**Hard sandbox:** `MAIL_MAILER=log` stays (`.env`); phpunit `MAIL_MAILER=array`; NOTHING
REALLY SENDS in dev/demo (Q7=A; SMTP later = pure `.env` flip, zero code change).

## Requirements

**R1 — The three Mailables (design D8/D9).** All `ShouldQueue` on the `database` connection
(default `QUEUE_CONNECTION=database` — no override), `Mail::…->queue(...)` ONLY, inserts AFTER
COMMIT (design D1 rider — never inside the OCC window):
1. `App\Mail\ReplacementSubmitted` — FR 4.15: queued INSIDE `SubmitReplacementRequest` after
   its successful reserve, recipients = every `lecturers.is_pl` PL (Demo today: 1 — 5425).
   Sendable from the submit side hook (proposal item 6: server-only hand-off; the submit UI
   and OCC semantics untouched).
2. `App\Mail\ReplacementDecided` — FR 2.13: recipient = the proposer; **capture-at-QUEUE-TIME
   (RR1 🟡3): the span min/max, venue, decision, reject reason and PL name are read from the
   PRE-RELEASE row state at enqueue time — the mail body is a snapshot; a later worker render
   never re-reads the (deleted) junction for a rejected row**; Queued by BOTH D1/D2 actions'
   after-commit hooks. Q13: the proposer still receives it when deciding their own request.
3. `App\Mail\ReplacementApprovalNotice` (cohort leg; naming settled here) — FR 1.9: recipient
   set = the affected cohorts' STUDENT emails (`session_cohorts` → `cohorts` → `students`
   → `users.email`); approve-only; queued after commit as its OWN job — no ordering contract
   with #2 unless a test pins one.

**R2 — Content floor (plain Blade views; log mailer readable).** Each Mailable renders a plain
single-file text template: subject line names the request id + subject; body carries the
identity triple (who decided / what slots / why). No design pass, no HTML marketing layout.

**R3 — Test contract (`tests/Feature/EmailQueueTest.php`; `Mail::fake` + `Queue::fake`).**
- submit →ReplacementSubmitted queued to every `is_pl` PL exactly once per submit;
- approve → ReplacementDecided (proposer) + ReplacementApprovalNotice (all affected cohort
  students, de-duplicated across multi-cohort sessions) enqueued;
- reject → ReplacementDecided only (no cohort leg);
- multi-cohort session: every affected cohort's students appear; overlapping cohorts'
  students appear once;
- Q13 case: decide-own-request still queues one copy to the proposer;
- The drain test (NFR 1.4) is a SEPARATE method WITHOUT `Queue::fake` (RR1 🟡4): the real
  `jobs` row is inserted by the after-commit hook; a worker (`queue:work --once`) drains it
  within one cycle, elapsed < 1 minute; `Mail::fake`/`array` mailer still applies. Under
  `Queue::fake` nothing lands in `jobs` — the enumeration tests and the drain test can never
  share a process.

**R4 — Runbook (proposal item 7's doc).** Short `docs/` note: dev/demo = log mailer (emails
land in `storage/logs/laravel.log`, nothing external); queue jobs persist in the `jobs` table
until a worker claims them (they are never lost by a missing worker); `php artisan queue:work`
(drain: `--once`, plus the `--tries`/failure-to-`failed_jobs` behavior = Laravel stock);
the SMTP flip = `.env` MAIL_* keys only.

**R5 — Failure semantics.** No mail construction failure may 500 a decision: Mailable build
errors are after-commit only; a swallowed-and-logged send failure (log mailer) never
re-decides anything (the request row chose; mails are pure notification).

## Scenarios (pinned at tasks = R3's list)
