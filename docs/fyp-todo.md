# FYP daily to-do — Poong Foo Jing (RSD3 G2)

> **Open this first every day.** Pick **1–3 tasks** from *Today / this week*; tick a
> box only when you have **verified** it (a green test, a live screen, a commit).
> Never re-plan from scratch — adjust the week, keep the backlog.
> **Last re-planned: 2026-11-02 (Mon).** Re-plan every Monday (15 minutes).

**Status key:** `- [ ]` not done · `- [x]` verifiably done · 🔴 high · 🟡 medium · 🟢 low
**Sources of truth (do not duplicate tasks here):**
`docs/replacement-arrangement-pending-bugs.md` · `.sdd/changes/<lane>/tasks.md` ·
`docs/viva-demo-runbook.md` · `prompts/extra tasks/README.md` · `prompts/general&reusable prompt references/PI-PROMPT-TEMPLATE.md`.
This file only decides **when** and **in what order**.

---

## 1. Where the project stands (verified 2026-11-02)

| Area | State |
|---|---|
| App | Working: auth, timetables (staff/cohort/student), venue grid, booking + approval, cancel, notifications (mark/hide/shelf), summary cards, lead-time banner |
| Demo environment | ✅ `CRS-demo` on `:8001` with its own DB `class_replacement_viva` (snapshot of `class_replacement_fresh`); original untouched |
| Repo | `fedora-backend`, **60 unpushed commits** (you push manually). A parallel session has uncommitted WIP — never stage its files |
| Report | Ch1, Ch2, 3.1, 3.2, 3.4 content-complete (2026-08-28 review); **§3.3 ≈ 60%**, its survey results blocked on data; Ch4 status unconfirmed |
| SDD | 24 lanes `applied` (unarchived), 5 `active`, 1 `frozen` (`ui-footer-global`), 1 `draft` (`receipt-target-guard`) |
| Open bugs | 1 approved but unfixed (notification timestamp skew); 5 arrangement bugs awaiting **your** verdicts; 1 parked test (reload hang) |

**Key dates**

| When | What | Status |
|---|---|---|
| **Tue 3 Nov 2026** | **Face-to-face progress demo to SV** | confirmed |
| Week of 9 Nov | Resume full working weeks | — |
| **Week of 7 Dec 2026** | Viva/demo week in the runbook | ⚠ **unconfirmed — confirm with SV on 3 Nov** |
| Week of 21 Dec 2026 | FYP1 portfolio + viva per the 14-week profile | ⚠ conflicts with the line above; resolve it |
| Sun 27 Dec 2026 | Semester 202605 ends | fixed |

> **First thing to confirm on 3 Nov:** the real portfolio/viva week. Everything in
> §5 shifts by it. Until then the plan uses *week of 7 Dec* with a 2-week buffer.

---

## 2. How to run a day

1. Open this file → **Today** section (or the top 🔴 item if the day is unscheduled).
2. Take **one main task** (60–120 min) and **one small task** (15–30 min). Stop at three.
3. Work → verify (test / live screen / commit) → tick the box **and add the receipt**
   (commit hash, screenshot path, test count).
4. If a task blocks on someone (SV, IT, respondents), write the blocker and move on —
   never sit on it.
5. **Sunday = light or off.** Only survey follow-up if it is behind.

**If you only have 30 minutes:** do the smallest unchecked 🔴 task, or the survey
follow-up — survey data has the longest lead time of anything on this list.

---

## 3. Today and this week (2026-11-02 → 2026-11-08)

### Mon 2 Nov (tonight, 30–45 min) — demo prep only
- [ ] Start the Viva server (`:8001`) and log in as **5425**; open the notifications panel once
      — `cd "…/CRS-demo" && php artisan serve --host=127.0.0.1 --port=8001`
- [ ] Click through the demo beats once (venue → class modal → arrange → submit → approve → student view)
- [ ] Pack: charger, screenshots in `/tmp/opencode/shots/`, a fallback plan if `:8001` dies (restart command is in `docs/viva-demo-runbook.md`)

### Tue 3 Nov — **your plan: demo → timestamp fix → SMTP**

- [ ] **Task 0 — sanity check + SV demo (morning).**
      08:00: `:8001` up, login as **5425**, demo DB sane (169 users / 3 requests / 7 notifications).
      Run the story (`docs/viva-demo-runbook.md` beats 1–8), then **confirm the portfolio/viva date**
      and write the SV feedback into §7 (10 min).
- [ ] **Task 1 — the timestamp fix A+B+C** (`prompts/completed prompt (Archieve)/pi-bug-notification-requested-at-timezone.md`).
      Implement → verify live (Request #26 tooltip `2 Nov 2026, 7:00 PM`; history modal Requested
      **and** Reviewed in MYT) → commit. Cross off §4 H2.
      *If this slips past ~16:00, move it to Wednesday morning — never rush it between meetings.*
- [x] **Task 2 — Gmail SMTP flip** (recipe + the corrected 10-row table:
      `docs/queue-and-emails.md` § "Real sending — the Gmail SMTP flip").
      Order: fill `.env` → `config:clear` → send one `Mail::raw` **to yourself** →
      only then test the queued path. **Decide the recipient-safety posture before flipping**
      (real `tarc.edu.my` addresses vs revert to `log` for the demo). App Password never pasted anywhere.
      Cross off §4 M2 once a real message lands in your inbox.
      **Done 2026-11-03 (~02:05):** 8 rows flipped (`MAIL_SCHEME` stays `null`) → `config:clear`
      → direct `Mail::raw` landed in `foojing03@gmail.com` → queued dummy `ReplacementDecided`
      (`onQueue('m2-test')`) ran through `queue:work --queue=m2-test --once` (4 s, `failed_jobs` 0)
      and landed in `poongfj-sm23@student.tarc.edu.my`; the isolated queue kept the 10-job
      `default` backlog untouched. **M2 item 3 (posture) was decided the same day, split by repo: dev = `log`, viva = `smtp`**
      (nothing can send from dev; `CRS-demo` was flipped for real demo mail); the App Password was
      exposed in a chat paste and rotation was declined.
      Receipt: `docs/queue-and-emails.md`. **Posture decided 2026-11-03: B — `MAIL_MAILER=log`**
      (the SMTP block stays configured for a one-line flip later; the 10 queued mails can no longer send).

### Wed 4 Nov — frozen lane day (or the timestamp fix, if it moved)
- [ ] 🔴 Apply `ui-footer-global` from `prompts/pi-ui-footer-global.md` (5 tasks, ~2 h)
- [ ] Verify: bottom-pinned, 390 px no clipping, light + dark, print hidden, login strip legible
- [ ] Changelog + commit; cross off §4 H4

### Thu 5 Nov — decisions + long-lead work
- [ ] 🟡 Read `docs/replacement-arrangement-pending-bugs.md` and give a verdict per bug (fix / accept / WONTFIX) — 30–45 min
- [ ] 🔴 Survey status: if responses are still not collected, distribute both forms this week (longest lead time)
- [ ] Weekly review: re-plan next week (15 min)

### Fri 6 Nov — writing (1–2 h)
- [ ] 🟡 Report §3.3.6 domain-model write-up + §3.3.1 overview paragraph (see `knowledge/Draft Review …md` §3)

### Sat 7 – Sun 8 Nov — rest / buffer
- [ ] Only if behind: survey follow-up, finish the bug verdicts, or catch up the timestamp fix

---

## 4. Backlog by priority

### 🔴 High — do these first

**H1 · SV demo (Tue 3 Nov)**
- [ ] Prep, run, capture feedback (see §3)
- [ ] Confirm the viva date and update this file + `docs/viva-demo-runbook.md`

**H2 · Notification timestamp fix (approved A+B+C)**
- [ ] Implement per `prompts/completed prompt (Archieve)/pi-bug-notification-requested-at-timezone.md`
- [ ] Verify + commit; then refresh `CRS-demo` (fast-forward, no DB work)
- **Depends on:** nothing. **Blocks:** nothing, but it is a visible correctness bug in the demo story.

**H3 · Survey results (§3.3.7) — longest lead time**
- [ ] Confirm no responses exist yet (last recorded state: blank forms, `[PENDING]` cells)
- [ ] Get/confirm SV sign-off for student distribution; send both forms
- [ ] Chase to n ≥ 20 (students) / n ≤ 14 (lecturers)
- [ ] Then: tables 3.12–3.27 + Figure 3.4 + the FR traceability sentence
- **Blocks:** §3.3 completion → Ch3 completeness → portfolio quality.

**H4 · `ui-footer-global` (frozen lane, apply ready)**
- [ ] 5 tasks per `prompts/pi-ui-footer-global.md` + gates + changelog + commit
- [ ] Refresh `CRS-demo` afterwards

**H5 · Report sync (small but graded)**
- [ ] Add **FR 2.17** to Table 3.5 and change "48 FR" → **49** wherever the count is quoted
      (source: `prompts/for-ai-to-read/FR&NFR copy.md`, change log `v2026-11-02`)
- [ ] Re-check the draft-review "48 FR / 26 NFR" sentence

**H6 · Test lane (parked, needs your word)**
- [ ] `tests/submission-conflict-refresh.spec.ts:122` reload hang — apply the designed fix,
      reseed `class_replacement_testing`, rerun solo on `:8030`
- [ ] Decide `receipt-target-guard` (draft lane, 5 open tasks): apply or close

**H7 · Arrangement bug verdicts (blocks fixes)**
- [ ] BUG-01 (stale slot-trigger label) — verdict
- [ ] BUG-02 (Back bypasses history) — verdict
- [ ] BUG-03 (blank-subject strip identity) — verdict
- [ ] BUG-05 (re-click selected cell) — verdict
- [ ] BUG-06 (counter after over-return) — verdict
- **Note:** BUG-04 and BUG-07 are closed by your decision — do not reopen.

**H8 · §3.3 report write-up (what is not blocked)**
- [ ] §3.3.1 overview paragraph
- [ ] §3.3.6 conceptual domain model (drawio → PNG 300 dpi + 2-sentence explanation)
- [ ] Port the activity-diagram description into §3.3 (`figures/AD/…v1.0.md`)
- [ ] §3.3 references (only cited works)

### 🟡 Medium — after the high items, one per day max

**M1 · NFR benchmark receipts** (needed for the report + demo)
- [ ] OCC overhead < 100 ms (NFR 1.2) — tinker timing script, record the numbers
- [ ] Engine < 500 ms (NFR 1.1) — same
- [ ] Paste the table into `docs/viva-demo-runbook.md`; no number before it is measured

**M2 · Email/SMTP pack (demo week)**
- [x] Flip `.env` per the corrected 10-row table in `docs/queue-and-emails.md` (`MAIL_SCHEME` stays `null` — there is no `MAIL_ENCRYPTION` in Laravel 12) — **done 2026-11-03**: 8 rows set, re-verified via `config()` after `config:clear`; `QUEUE_CONNECTION` stayed `database`, `APP_NAME` untouched
- [x] One direct `Mail::raw` to yourself lands in the inbox, then one queued message after `queue:work --once` — **done 2026-11-03**: direct → `foojing03@gmail.com`; queued dummy `ReplacementDecided` on the isolated `m2-test` queue → `queue:work --queue=m2-test --once` → `poongfj-sm23@student.tarc.edu.my`; `failed_jobs` 0; the `default` backlog untouched
- [x] Decide the recipient-safety posture for the demo (real `tarc.edu.my` addresses vs revert to `log`) — **DECIDED 2026-11-03 (split by repo)**: `CRS-fedora-backend` (dev) = **`log`** — its **10** stale jobs stay in `jobs` and can never send (a worker only renders them into `storage/logs/laravel.log`; no DB write, no purge). `CRS-demo` (viva, DB `class_replacement_viva`) = **`smtp`** — flipped 2026-11-03 for real demo mail; its **27-job backlog** (14 `ReplacementApprovalNotice` → `23rsd0011–0024@student.tarc.edu.my`, 10 `ReplacementSubmitted` + 3 `ReplacementDecided` → `limjz@`/`mohdnurrahmat@`/`surayaini@`) **must be purged before the first worker run** (demo-DB gate: snapshot → DELETE → log).
- **Depends on:** the App Password (you), not code. — done + proven 2026-11-03; it appeared in a chat/error paste, and the user **declined rotation** the same day (residual risk noted; if reversed: revoke → new → `.env` no spaces → `config:clear` → one `Mail::raw`).

**M3 · Ch4 System Design**
- [ ] Confirm what exists vs the Chapter-4 requirements (`knowledge/FYP1 Ch4 System Design Requirements.md`)
- [ ] Draft the missing sections (ERD, class diagram, pseudocode, OCC snippet per the no-duplication split)

**M4 · UAT / test-case evidence**
- [ ] Turn `page-changelogs/todo list/test-cases.md` into a results table with real pass/fail
- [ ] Include the 292-test suite count **measured now**, not quoted

**M5 · SDD hygiene**
- [ ] Archive the 24 applied lanes (needs your word) — `prompts/completed prompt (Archieve)/README.md` rule
- [ ] Check the 5 `active` lanes with 0 open tasks: verify → flip `applied` → archive

**M6 · Push origin**
- [ ] You push `fedora-backend` manually (60 commits) — the agent must never push

**M7 · Triage `issues/ugly toast message.png`**
- [ ] Decide: real bug → small lane; or cosmetic → park in `prompts/extra tasks/`

### 🟢 Low — only if a day is light

- [ ] 🟢 Extra task 01: warn-modal colour (`prompts/extra tasks/task-01-warn-confirm-modal-colour.md`)
- [ ] 🟢 Extra task 02: one honest timeline rail (`prompts/extra tasks/task-02-replacement-history-timeline.md`) — student rail temporarily removed; all staff rails still live and their defects recorded there (8h `reviewedAt` skew, cancelled-looks-reviewed, permanently empty "Viewed" step, no outcome label)
- [ ] 🟢 Settings-surface footer (deliberately excluded from `ui-footer-global`)
- [ ] 🟢 Remove the two accidentally tracked junk files (`"` and `--log-events-text`)
- [ ] 🟢 Mark `page-changelogs/todo list/todo-list.md` as historical (its TASK-001/002/003/005 are stale)
- [ ] 🟢 Any copy/tooltip polish found during the demo

---

## 5. Week-by-week runway (rolling — adjust every Monday)

| Week | Dates | Main outcome (2–3 tasks only) |
|---|---|---|
| W7 | 2–8 Nov | SV demo ✅ · timestamp fix · footer · bug verdicts · survey sent |
| W8 | 9–15 Nov | Test lane green (reload hang + receipt guard) · §3.3 write-up · FR 2.17 in report |
| W9 | 16–22 Nov | Survey analysis (§3.3.7) if data is in · Ch4 drafting · NFR benchmarks |
| W10 | 23–29 Nov | Ch4 complete · UAT table · remaining bug fixes · archive lanes |
| W11 | 30 Nov–6 Dec | Report integration + references + proofread · slide deck draft · SMTP receipts |
| W12 | 7–13 Dec | Full rehearsal ×2 · demo DB snapshot/restore · **viva (if confirmed)** |
| W13 | 14–20 Dec | Buffer: fix what the rehearsal exposed · final report polish |
| W14 | 21–27 Dec | Portfolio submission · final checks · rest |

> **Rule:** a week that slips does not move the viva. Cut from 🟢 and 🟡, never from
> the report or the rehearsal.

---

## 6. Dependencies (do these first)

1. **SV date confirmation (3 Nov)** → the whole runway in §5.
2. **Survey distribution** → §3.3.7 analysis → Ch3 completeness (longest chain).
3. **Bug verdicts (you)** → any BUG fix lane.
4. **SMTP credentials (you/IT)** → the email beat of the demo.
5. **Your "word" for archiving / parked test lane** → those checkboxes can never move without it.
6. **Ch4 draft** → consolidated references → proofread → rehearsal.

---

## 7. Done — verified (keep for confidence, do not re-do)

- [x] Viva copy built: `CRS-demo` on `:8001`, own DB `class_replacement_viva`, originals untouched — 2026-11-02
- [x] Notification batch-hide + hidden shelf shipped (`467357b`, `f5cc56d`, `fc81dd3`, `405b4a3`)
- [x] Summary cards (My Subjects + confirmed/pending split) shipped (`e1429b1` + receipts)
- [x] Lead-time banner shipped (`3a407e4`)
- [x] FR 2.17 added to `FR&NFR copy.md` (49 FRs) — **report update still open (H5)**
- [x] Timestamp bug diagnosed and fix approved (A+B+C) — **implementation open (H2)**
- [x] `ui-footer-global` lane frozen and the apply prompt written — **apply open (H4)**
- [x] opcode/stale-serve trap documented + `scripts/dev-serve.sh`

---

## 8. Maintenance rules for this file

- **Only tick what you verified.** A commit hash, a test count, a screenshot path — or it stays unticked.
- Never copy a bug's full detail here; link the source doc.
- When a task moves to another week, move the line — do not duplicate it.
- When a whole area finishes (e.g. §3.3), move its block to §7 with the receipt.
- Keep it under ~10 new lines per week. If it grows, cut 🟢 items first.
