# FYP daily to-do — Poong Foo Jing (RSD3 G2)

> **Open this first every day.** Pick **1–3 tasks** from *Today / this week*; tick a
> box only when you have **verified** it (a green test, a live screen, a commit).
> Never re-plan from scratch — adjust the week, keep the backlog.
> **Last re-planned: 2026-11-05 (Thu, 22:50) — the plan below is set for Fri 6 Nov.**
> Re-plan every Monday (15 minutes).

**Status key:** `- [ ]` not done · `- [x]` verifiably done · 🔴 high · 🟡 medium · 🟢 low
**Sources of truth (do not duplicate tasks here):**
`docs/replacement-arrangement-pending-bugs.md` · `.sdd/changes/<lane>/tasks.md` ·
`docs/viva-demo-runbook.md` · `prompts/extra tasks/README.md` · `prompts/general&reusable prompt references/PI-PROMPT-TEMPLATE.md`.
This file only decides **when** and **in what order**.

---

## 1. Where the project stands (verified 2026-11-05, 22:50)

| Area | State |
|---|---|
| App | Working end to end: auth, timetables (staff/cohort/student), venue grid + **slot validation**, booking + approval, **Cancel Class (FINAL — no undo)**, notifications (mark/hide/shelf, read-time staleness), summary cards, lead-time banner, **My Replacements as ONE table** with the after-submit row pulse |
| Demo environment | ✅ `CRS-demo` on `:8001`, own DB `class_replacement_viva`; originals untouched. `CRS-demo` is **behind** the code (fast-forward, no DB work) |
| Repo | `fedora-backend` — you pushed tonight: `origin` = **`39b2bb7`**, **1 commit unpushed** (`b57bf9d`, the prompt archive). A parallel session keeps `docs/demo-db-data-changes.md` dirty — never stage it |
| Verification | Full PHPUnit **344 passed / 1899 assertions** (2026-11-05, 410 s); `venue-book` **19**, venue neighbours **61 passed / 2 skipped**, `cancel-class` **12 passed / 4 skipped** |
| Demo data | `class_replacement_fresh`: 5 requests · 77 notifications (3 hidden) · 2 `class_exceptions` · 8 slot rows — unchanged since the two logged cleanups |
| SDD | **34** lane dirs in `.sdd/changes/` (33 older finished + `venue-slot-validation` `applied`), **101** archived |
| Open bugs / test debt | 5 arrangement bugs awaiting **your** verdicts; `submission-conflict-refresh.spec.ts` (`T3d`) still not green; `receipt-target-guard` applied but that one receipt unproven |
| Report | Ch1, Ch2, 3.1, 3.2, 3.4 content-complete (2026-08-28); **§3.3 ≈ 60%**, survey blocked on data; Ch4 unconfirmed |

**Key dates**

| When | What | Status |
|---|---|---|
| **Tue 3 Nov 2026** | **Face-to-face progress demo to SV** | confirmed |
| Week of 9 Nov | Resume full working weeks | — |
| **Week of 7 Dec 2026** | Viva/demo week in the runbook | ⚠ **still unconfirmed — ask SV this week** |
| Week of 21 Dec 2026 | FYP1 portfolio + viva per the 14-week profile | ⚠ conflicts with the line above; resolve it |
| Sun 27 Dec 2026 | Semester 202605 ends | fixed |

> **Still the #1 unknown:** the real portfolio/viva week. Everything in §5 shifts by it.
> Until SV answers, the plan uses *week of 7 Dec* with a 2-week buffer.

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

## 3. This week (2026-11-02 → 2026-11-08)

### Done this week — verified (full receipts in §7)

- **Tue 3 Nov** — demo day: `:8001` story run + SV demo. *If you have the SV feedback or the
  portfolio/viva answer, write it into §7 — the date is still open.*
- **Tue 3 Nov** — notification timestamp fix **A + B1** shipped (`67413e3`): one user-facing clock
  (`App\Support\LocalTime`), every timestamp in the panel through it.
- **Wed 4 Nov** — `ui-footer-global` applied (`fea58ad`) and archived.
- **Wed 4 Nov** — the merge lane `replacement-merge-one-table` applied + archived (`07274c1`):
  My Replacements is now one table, one row per occurrence.
- **Thu 5 Nov** — **Cancel Class is FINAL** (`7b7f0d9`, `23c627c`, `12c644a`, `2156172`), the E2E
  write-guard (`b924552`), the after-submit row pulse (`a29f694`), the tooltip `ME` → `YOU`
  (`476406a`, `43bda16`).
- **Thu 5 Nov** — venue page: bookable cells (`daf11c6`) and the slot-validation split (`bee6176`);
  seven finished briefs archived (`b57bf9d`).

### Fri 6 Nov — TOMORROW (one main + one small, stop at three)

- [ ] 🔴 **MAIN (90–120 min) — report §3.3.6 + §3.3.1.** Conceptual domain model (drawio → PNG
      300 dpi + the two-sentence explanation) and the §3.3.1 overview paragraph. Unblocked, needs
      no data, and it is the last self-contained piece of §3.3 — *source: §4 H8.*
- [ ] 🟡 **SMALL (30 min) — NFR benchmark receipts** (§4 M1): time the OCC validator and the
      intersection engine with a tinker script, paste the measured table into
      `docs/viva-demo-runbook.md`. Numbers only from the measurement, never from memory.
- [ ] 🔴 **ALWAYS, 5 minutes — the survey (§4 H3).** If the two forms are not distributed yet, send
      them tomorrow; they have the longest lead time of anything on this list.
- [ ] ⏱️ **Only if both are done** — ask the agent for §4 **H6** (`T3d`: diagnose
      `submission-conflict-refresh.spec.ts` timeboxed, or quarantine it with the reason recorded).

### Sat 7 – Sun 8 Nov — buffer
- [ ] Only if behind: survey follow-up (§4 H3), the bug verdicts (§4 H7), or the FR 2.17 report edit (§4 H5).

## 4. Backlog by priority

### 🔴 High — do these first

**H1 · SV demo (Tue 3 Nov) — RAN. One follow-up left**
- [x] Prep + run (see §7)
- [ ] **Still open:** confirm the portfolio/viva week with SV and update this file + `docs/viva-demo-runbook.md`

**H2 · Notification timestamp fix — SHIPPED 2026-11-03 (`67413e3`, A + B1)**
- [x] One user-facing clock (`App\Support\LocalTime`), panel + mapper convert through it
- [x] B2 moot (the merge retired that page), C dropped with your agreement — see §7

**H3 · Survey results (§3.3.7) — longest lead time**
- [ ] Confirm no responses exist yet (last recorded state: blank forms, `[PENDING]` cells)
- [ ] Get/confirm SV sign-off for student distribution; send both forms
- [ ] Chase to n ≥ 20 (students) / n ≤ 14 (lecturers)
- [ ] Then: tables 3.12–3.27 + Figure 3.4 + the FR traceability sentence
- **Blocks:** §3.3 completion → Ch3 completeness → portfolio quality.

**H4 · `ui-footer-global` — SHIPPED + ARCHIVED 2026-11-04 (`fea58ad`)**
- [x] Applied, verified (dark/light, 390 px, print) and moved to `.sdd/archive/` — see §7

**H5 · Report sync (small but graded)**
- [ ] Add **FR 2.17** to Table 3.5 and change "48 FR" → **49** wherever the count is quoted
      (source: `prompts/for-ai-to-read/FR&NFR copy.md`, change log `v2026-11-02`)
- [ ] Re-check the draft-review "48 FR / 26 NFR" sentence

**H6 · Test lane — the guard is APPLIED; one receipt still open**
- [x] `receipt-target-guard` applied (`b924552` + `7bbce9f`) and archived: writing specs now refuse
      (exit 1 at collection) unless the blessed `:8030` env is exact
- [ ] **`T3d` — `tests/submission-conflict-refresh.spec.ts` is still not green** (it fails inside its
      own arrival flow). Decide tomorrow: diagnose timeboxed (~45 min), or quarantine as
      `test.fixme()` with the reason recorded in the lane's `tasks.md`

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
- [ ] Archive the **33 older finished** lane dirs left in `.sdd/changes/` (needs your word) —
      `git mv` + `sdd.yaml: archived` + a `docs:` commit, the same pass as 2026-11-05
- [ ] Archive `venue-slot-validation` too once you have read its changelog entry

**M6 · Push origin**
- [x] You pushed 27 commits tonight — `origin` = `39b2bb7`
- [ ] One more sits unpushed: `b57bf9d` (the prompt archive). Push when convenient; the agent never pushes

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
| W7 | 2–8 Nov | **Delivered:** SV demo · timestamp fix · footer · merge lane · Cancel Class final · venue slot rules. **Still open from this week:** viva date, survey distribution, the 5 bug verdicts, §3.3.6 + §3.3.1 |
| W8 | 9–15 Nov | §3.3 write-up finished · FR 2.17 in the report · `T3d` decided (fix or quarantine) · the 33 finished lanes archived |
| W9 | 16–22 Nov | Survey analysis (§3.3.7) if data is in · NFR benchmark table · Ch4 drafting |
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
- [x] opcode/stale-serve trap documented + `scripts/dev-serve.sh`
- [x] **Notification timestamp fix A + B1** (`67413e3`): `App\Support\LocalTime` is the one
      user-facing clock; panel + mapper convert through it. B2 moot, C dropped by agreement — 2026-11-03
- [x] **`ui-footer-global`** applied + archived: `fea58ad`, lane in `.sdd/archive/` — 2026-11-04
- [x] **`replacement-merge-one-table`** applied + archived (`07274c1`): My Replacements is ONE table,
      one row per occurrence, `/my-replacements` canonical with the two alias routes — 2026-11-04
- [x] **`request-approval-ui CONSISTENCY` parts 1 + 2** shipped (1f, 3B, 3C, 3A, 3D, 1h, 3E-3, §4) —
      brief archived 2026-11-05
- [x] **Cancel Class is FINAL** (`7b7f0d9`, `23c627c`, `12c644a`, `2156172`): undo removed from UI,
      JS and server; one shared irreversible line on four modals; stale notices marked inactive at
      read time — 2026-11-05
- [x] **E2E write-guard live** (`b924552`, lane applied `7bbce9f`): a writing spec refuses at
      collection unless `CRS_MODE=testing` + `CRS_BASE=…:8030` (+ `CRS_ALLOW_SQL=1`) are exact — one
      receipt (`T3d`) still open, see §4 H6
- [x] **After-submit row pulse** (`a29f694`): no success toast, `?new-req=<id>`, the new row flashes —
      2026-11-05
- [x] **Tooltip `ME` → `YOU`** on cohort + venue (and the two mock-ups): `476406a`, `43bda16` — 2026-11-05
- [x] **Venue page bookable cells** (`daf11c6`): honest `cell-no-room` state, class-driven late cells,
      Earliest-bookable action + `Bookings open` chip, the invented "2-hour gap" number deleted — 2026-11-05
- [x] **Venue slot validation** (`bee6176`, lane `venue-slot-validation`): hover asks one clear hour,
      the picked class's real duration decides; warning modal instead of an early verdict — 2026-11-05
- [x] **Archive passes**: 7 SDD lanes → `.sdd/archive/` (2026-11-05) and 7 finished briefs →
      `prompts/completed prompt (Archieve)/` with evidence rows (`b57bf9d`)

---

## 8. Maintenance rules for this file

- **Only tick what you verified.** A commit hash, a test count, a screenshot path — or it stays unticked.
- Never copy a bug's full detail here; link the source doc.
- When a task moves to another week, move the line — do not duplicate it.
- When a whole area finishes (e.g. §3.3), move its block to §7 with the receipt.
- Keep it under ~10 new lines per week. If it grows, cut 🟢 items first.
