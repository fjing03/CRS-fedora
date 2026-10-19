# Request Approval — changelog

## 2026-10-18 — SDD `span-aware-approval-flow` slice C+D (the REAL page)

The route `/request-approval-ui` stops serving the D10 mock the moment
`App\Livewire\RequestApproval` exists (`routes/web.php`'s `class_exists`
branch — no route edit). The REAL page is a byte-for-byte port of the frozen
template with only the documented BRIDGE marks:

- **One page, real rows** — FCFS pending (`submitted_at` ASC, FR 3.2) + the
  decided bucket (approved/rejected; the `Completed` filter stays an honest
  EMPTY state — the out-of-scope leg). `reviewedBy = approver.name`,
  `reviewedAt = decided_at` (the decided row's detail modal).
- **ONE shared validity predicate** — the queue's 🟢/⚠️ badge and the
  approve/reject actions read the same `ApprovalValidity::evaluate` (FR 3.4;
  2-case map: 'already decided' / the existing slot_not_available named-time
  copy).
- **The §10.0.6 promotion**: the request→row mapper is now ONE shared
  `App\Services\ReplacementRequestMapper` (was: ApiReadController's
  `myRequests()` closure + MyRequestHistory's `requestRow()` — the third
  consumer is the PL queue). Its span read is DATA-DRIVEN (junction min/max
  for pending AND approved, legacy anchor fallback for junction-empty rows of
  ANY bucket — never bucket-keyed (spec RR1).
- **Approved-bucket junction switch (Q3=A)** — the approved display reads the
  junction's true range everywhere: timeline replacement block,
  MyRequestHistory, API `myRequests()`, the venue-slots API marker block
  (full-run state 4), the PL queue's decided bucket, and student
  `ReplacementHistory` rows. span-1 rows stay byte-identical.
- **The decide surface** — Approve (optional note → `details.approver_note`)
  / Reject (reason mandatory SERVER-side; the frozen chips are UI-only;
  `Other` = any typed text stored verbatim). **NO undo** (user ruling
  2026-10-17): decisions are final; the confirm popups carry the frozen
  copy. Bulk = sequential FCFS via the same real actions; a stale row fails
  politely (its conflict toast) while the rest decide; ONE shared reject
  reason covers every ticked row.
- **The emails** — ReplacementSubmitted (submit-side, FR 4.15) /
  ReplacementDecided (FR 2.13, dispose + reason) / ReplacementApprovalNotice
  (FR 1.9, cohort students) — ALL queued (FR 4.16, database connection),
  capture-at-queue-time (the mail body is a snapshot; Rejected rows never
  re-read a deleted junction). Dev/demo = `MAIL_MAILER=log` — NOTHING really
  sends (user ruling A-now/B-later on the in-app panel: the frozen 🔔 panel
  STAYS mock-fed; its real-data wiring is a planned follow-up mini-SDD).
- **The D7 original-release** — approve stamps
  `class_exceptions(official_event)` for the original occurrence (guard: no
  write when an exception exists — the unique pickets it; a racing stamp
  CONTINUES via savepoint) + frees the original occurrence's OWN booked
  venue rows (`available`, version-checked, identity-pinned — the old
  room+time becomes bookable by others per Objective-3's letter). Visible
  on week pages only post-holiday (the W14 case is a holiday week).

## 2026-10-18 — T18/T19 postscript (the email infrastructure)

- `tests/Feature/EmailQueueTest.php` — T18's two families: the enqueue
  assertions (per-PL submitted copies, decided + herteroés cohort-notice with
  the roster count, decided-only for reject, the Q13 decide-own case) + the
  NFR 1.4 REAL-worker drain (NO Mail::fake / NO Queue::fake; the jobs-table
  row is REAL, `queue:work --once` consumes it inside one cycle, elapsed
  <1 min) — the drain fixture drops the cohort attach so exactly ONE job
  runs.
- `docs/queue-and-emails.md` — the runbook (dev = log mailer, jobs persist
  until the worker claims them, failed_jobs stock behavior, the SMTP flip being
  a `.env` change only).

## 2026-10-18 (user report fix) — STUDENTS "undefined" + the dead detail modal

The live page reported two issues, same root: the queue bucket's rows lacked
the COHORT METADATA (`totalStudents` / `cohorts` / `cohortCounts`) —
`renderRow` printed `undefined` in the STUDENTS column and
`openModalById` threw on `cohorts.join()` inside the detail-modal builder
(silently deadening the click → popup). **Fix:** the shared mapper's
`toRow` now carries the queue bucket's cohort trio (SPACED labels +
values-array counts + the summed total — `ReplacementHistoriesTest`-pinned
byte shapes are untouched for the other buckets). Smoke: new
`U3-T9c` pins STUDENTS numeric + row-click → modal opens/closes.
