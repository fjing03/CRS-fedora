# Viva demo runbook — TAR UMT Class Replacement System

**Status: DRAFT (2026-10-28) — the plan, not the rehearsal.**
**Anchor:** FYP2 deadline week = week of 7 Dec 2026 (UNCONFIRMED — update this
line the moment the real date is known). **Machine: MSI (this one)** — the
demo DB `class_replacement_fresh` (PostgreSQL, user `philler`) lives here;
snapshot/restore recipe = `/home/philler/Desktop/tarumt/backups/README.md`.

## The demo story (user-ruling 2026-10-28)

The walkthrough shows the FULL replacement loop. Auth is explained verbally
in one short slide of words — no login demo beyond the surface.

| # | Beat | What the examiner sees | Login / page |
|---|------|------------------------|--------------|
| 1 | The world | Timetables are real imported data: venue page (B010 etc.), my-timetable, cohort view | staff 5425 |
| 2 | The conflict | Click a class in conflict → the class detail modal → "Arrange Replacement" | staff 5425 |
| 3 | The booking | Conflict-schedule strip → grid → the honest cell states (occupied/pending/available, the why-tooltips, holiday `-ph` days) → pick a free block → submit (Budget law: one occurrence-length slot) | staff 5425 |
| 4 | The PL decision | Approve (or reject with reason) — FCFS dashboard, span-aware; the bell badge grows in real time (after the notifications lane ships) | staff PL login |
| 5 | The clocks watch | The approved make-up class now occupies its new slots across the timetable pages (the double-book law: makeup-busy-vector) | staff |
| 6 | The student sees truth | The affected cohort view + student timetable show the replacement class in place (FR 1.3/1.9 read path) | student login |
| 7 | Cancel lane | Cancel a scheduled class with a reason → its slots free (read-time discriminator), pending cancel ≠ cancelled class | staff |
| 8 | The paper trail | Emails: PL-on-submit / lecturer-on-outcome / cohort-on-approval — queued, then delivered; queue-table receipts | terminal + inbox |

## Demo-pack week checklist (the week before the anchor)

- [ ] **SMTP flip** (user/IT: real TAR UMT credentials) — `MAIL_MAILER=smtp`
      in `.env` + `queue:work` receipts; otherwise the log-mailer demo stays
      (nothing sends, receipts render in `logs/laravel.log`).
- [ ] **NFR benchmark receipts** — OCC <100 ms overhead (NFR 1.2) + the
      engine <500 ms criterion; a tinker timing script + the numbers table
      into this doc. NO number written until measured.
- [ ] **Notifications lane applied** — the bell shows REAL events
      (submit/approve/reject); cancel-event inclusion = the user's ruling
      inside the lane.
- [ ] **Snapshot backup** of the demo DB (`backups/README.md`) before the
      rehearsal; restore before the FINAL demo so the book appears in an
      honest pre-set state.
- [ ] **BUG round verdicts** (BUG-01/02/03/05/06) — fix / accept / WONTFIX
      per bug; anything unfixed gets a "known limitation" one-liner here.
- [ ] Push (user does it manually) — keep origin current.
- [ ] Slide of words: auth in 3 sentences (Fortify staff-ID login, 3 RBAC
      tiers, per-role redirects).

## Standing do-nots during demo weeks

- The demo DB is never `migrate:fresh`; the book's pending requests
  #1–#4 keep their real state; any intentional DB edit gets an entry in
  `docs/demo-db-data-changes.md`.
- No `git push` from the agent; user pushes manually.
- TEMP-HOLDER-LABEL stays until AFTER the final viva (teardown task).
