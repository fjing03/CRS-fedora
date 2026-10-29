# PI Chat Context Handover — TAR UMT Class Replacement System

> Generated 2026-10-28 (evening, after commit `6be2235`). Source: the full
> session conversation. Purpose: a working reference for a NEW PI chat
> session. Nothing in this file is invented; each claim traces to a
> receipt, a code anchor, or an explicit user ruling recorded below.

---

## 0. Quick Status First (read before anything else)

| Item | State |
|---|---|
| Working branch | `fedora-backend` (remote: `origin` = `fjing03/CRS-fedora.git`, PULL-ONLY `upstream` = `FjingXR/class-replacement-system`) |
| Push policy | **NEVER `git push`** — the user pushes manually (they said so explicitly) |
| Unpushed commits | **4** (as of writing: `300bec8`, `f3b15d7`, `647dd34`, `6be2235` — the user pushed the older stack to origin themselves; the count is volatile, re-check) |
| Demo DB | `class_replacement_fresh` (PostgreSQL, user `philler`) — **never re-seed, never `migrate:fresh`; agent does NOT write its data (the hand-run SQL convention)** |
| Test DB | `class_replacement_testing` — disposable; fresh+seed fine; used by the suite |
| Suite | **273/273** (266 + the 7 NotificationsBridgeTest tests) |
| Gates | `composer run lint:check` (pint), `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` (NOT `types:check` — crashes at 128M), full phpunit, `node` syntax check for JS |
| SDD lanes applied, NOT archived | **12** (see §F — `notifications-real-bridge`'s sdd.yaml was correctly flipped to applied during this audit) |
| The user's language rule | **Reply in B1-level English ONLY — no other languages** |
| Approval rule | investigate first; **no code edits without the user's explicit per-task approval** |

---

## Table of Contents

1. [A. Project Overview](#a-project-overview)
2. [B. My Preferences and Working Rules](#b-my-preferences-and-working-rules)
3. [C. Important Decisions and Requirements](#c-important-decisions-and-requirements)
4. [D. Implementation Status](#d-implementation-status)
5. [E. Bug Reports and Investigation Findings](#e-bug-reports-and-investigation-findings)
6. [F. SDD and Development Workflow](#f-sdd-and-development-workflow)
7. [G. Current Problems and Pending Tasks](#g-current-problems-and-pending-tasks)
8. [H. Important File and Technical References](#h-important-file-and-technical-references)
9. [I. Latest Session State](#i-latest-session-state)
10. [Instructions for the New PI Chat Session](#instructions-for-the-new-pi-chat-session)

---

## A. Project Overview

**TAR UMT Class Replacement System (CRS)** — a Laravel + Livewire class
replacement booking app for TAR UMT (FOCS/DCIT). Lecturers book
replacement slots for cancelled classes; PLs approve/reject via an FCFS
dashboard; students see the updated timetable.

- **Stack**: Laravel 13.17, Livewire 4.1, PHP 8.3+ (runtime PHP 8.5.11),
  PostgreSQL, Flux UI + Tailwind, Fortify auth, Blade templates with a
  frozen house design (`theme.css` CSS tokens; SAME NAME = SAME COLOR
  canon), Playwright for receipts, SDD (spec-driven development) for
  every code change.
- **Dev servers**: `php artisan serve --port=8000` (CRS-fedora-backend)
  + a sibling `:8001` (CRS-demo) — restarted via the stale-serve ritual
  after any Blade/PHP edit: kill first (`pkill -f "[a]rtisan serve"`),
  then clean + serve — TWO separate commands, never one string.
- **Key pages**: `/my-timetable-ui`, `/cohort-timetable`,
  `/venue-timetable-ui`, `/student-my-timetable-ui`,
  `/replacement-home-ui`, `/replacement-arrangement`,
  `/my-request-history-ui`, `/replacement-history-ui`,
  `/request-approval-ui` (PL), `/dashboard`.
- **Login** (demo, read receipts): staff IDs **5425** (Surayaini Binti
  Basri — also a DCIT PL), 5652/5653/5770 (staff); password
  `Tarumt@2026`. Students: role-gated `role:student`.
- **Core architecture** (CodingMAIN.md is the single source of truth):
  ① Matrix Intersection Engine (4 busy vectors), ② OCC layer (wired —
  `SubmitReplacementRequest` → `validateAndReserve()`), ③ FCFS approval
  dashboard, ④ RBAC + notifications, ⑤ deployment (localhost only).
- **Wave 3b (booking/replacement write path)**: SHIPPED — this session
  verified and updated the docs accordingly.
- **Demo data facts**: `holidays` W14 day 3 = Christmas Eve (Thu Dec 24), day 4 = Christmas Day (Fri Dec 25) — 0=Monday is CONFIRMED (note: `tests/venue-db.spec.ts:10` has a stale comment saying 'W14 Wed/Thu' — cosmetic, fix on touch). `replacement_requests` #1 approved (rr#1: cs 23 W8
  → Fri W6 13:30–14:30 B009, the make-up slots 32612/32613), #5/#4
  cancelled; request **#6 pending** (the user's own live submit from the
  bridge testing); `class_exceptions`: 23/W8 medical_leave, 40/W5
  medical_leave, 40/W7 annual_leave (5 rows total was stale — 3 now);
  holidays: W8 Mon (Deepavali in lieu), W14 Thu + Fri (Christmas).
- **One new table added this session**: `notifications` (schema-add only — the table now holds 4 rows: the user's real submit fan-out ids 3/4 + the 2 logged demo-receipt rows).

## B. My Preferences and Working Rules

1. **Language**: reply in B1-level English. No other languages, ever.
2. **Approvals**: investigation BEFORE fixes for reported bugs ("Do not
   edit my system code yet" until the user approves). Most lanes get an
   explicit "apply" — never assume.
3. **SDD mandatory**: every code change needs a proposal → reviewer
   rounds (glm) → freeze → apply → verify → archive. Small bugfix lanes
   may combine proposal+design+tasks in one batch.
4. **Reviewer command** (the proven recipe — file-based, NEVER inline
   heredocs with nested parens):
   `pi --model commandcode/z-ai/glm-5.3-flash -p --tools read,grep,find,ls --system-prompt "$(cat ~/.config/opencode/agents/sdd-reviewer.md)" "$(cat /tmp/prompt.txt)" > /tmp/roundN.txt`
   (write the prompt to a temp file first; glm can hang — retry or run
   backgrounded; `commandcode/deepseek/deepseek-v4-pro` hangs silently).
5. **Playwright receipts for everything**: fresh in-flow login (staff
   login, `input[name="login_id"]`), screenshots + DOM snippets before
   claiming anything works. Gotchas: use `.cjs` files run from the repo
   cwd (package.json `"type": "module"`); avoid `ctx` name collisions;
   `:visible` is not a CSS selector; add `?_=` cache-busters.
6. **Demo-DB safety**: never re-seed/migrate:fresh the demo DB; never
   modify the user's real requests #1–#5 (#6 pending is theirs too);
   intentional demo-data writes = the user runs hand-drafted SQL,
   logged in `docs/demo-db-data-changes.md` (one exception was granted
   and logged: the two "Demo receipt — poll cycle*" rows, agent-run with
   the rollback SQL in the log).
7. **Honest zeros / honest claims**: never mark something done without a
   receipt; distinguish CONFIRMED vs SUSPECTED findings.
8. **Commits**: conventional (`feat:`, `fix:`, `ui:`, `docs:`,
   `refactor:`, `oop:`), one commit per lane, never push.
9. **UI laws**: Promote-on-3rd duplication → shared partial/helper;
   same-name-same-color; minimise plain text (icon buttons); don't
   overwhelm the page surface (explanations live in hover tooltips);
   confirm critical actions with modals; fewest clicks.
10. **After any Blade/PHP edit**: the stale-serve ritual (two commands) +
    restart the :8001 sibling:
    `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`

## C. Important Decisions and Requirements

### The user's standing rulings (do not change without approval)

| # | Ruling |
|---|---|
| 1 | **Notifications events — ALL FIVE**: submit→PL, approve→proposer+cohorts, reject→proposer, class-cancelled→owner+cohorts+**dept PLs** (`notifyDeptPls`, owner's `lecturers.dept_id`), pending reminder→PLs (24 h, deduped until read, re-armed daily). |
| 2 | **Real-time = Livewire `wire:poll` (option A)** — NOT Reverb/websockets. Poll 10 s. |
| 3 | **Notification lifecycle**: rows never deleted; an inactive target renders the row **disabled** (no href) + tooltip `This notification is inactive because the request was cancelled.` |
| 4 | **Email/S**: SV approved **Gmail SMTP** ("CAN") for demo/testing with the user's personal Gmail; NFR 7.2 may be revised by the USER (their own doc edit). The SMTP flip = an ops step outside code lanes. |
| 5 | **mock-data.js the FILE STAYS** (retire consumers lane by lane; dormant rows = spec anchors). |
| 6 | **B005 no-Diploma REPEALED** (b005-diploma-allowed) — Diploma cohorts may use B005; AMIT2034 renders clean. |
| 7 | **B006 networking priority** still enforced; P→labs rule. |
| 8 | **Receipts strategy** = disposable test-DB suite + hand-run demo seed SQL (never agent-run booking flows on the demo DB). |
| 9 | **The caught-up swap is category-aware** (the hotfix): "No unread in this category — N unread in other tabs" when the bell total > 0 but the viewed tab is clear. The pill/badge = the whole-mailbox unread TOTAL (the frozen F-2 law). |
| 10 | **No bell on the replacement-arrangement page** = ACCEPTED BY DESIGN (BUG-04). |
| 11 | **`?week=` never moves the week selector** (ARRIVAL-GRID-NO-PIN) = WONTFIX (user decision). |
| 12 | **Demo story** (viva ~week of 7 Dec 2026, UNCONFIRMED, on this MSI machine): timetables + booking + cancel class + approve + bells + emails; auth = verbal only; students demo the "replacement class got updated". |
| 13 | **THE pageLivewire() LAW (the hotfix)**: page actions use `pageLivewire()`, NEVER `Livewire.first()` (the bell islands render first). |

## D. Implementation Status

### Completed and verified (receipts exist)

| Item | Receipt |
|---|---|
| occupied-cell-explanations: the venue-grid holder tips (the dictionary: `holderTexts` 103 texts; payload 740KB→432KB) | `40ac994` + live hover receipts |
| holiday-occurrence-badge → superseded by conflict-schedule-header-status-tip: the status tooltip on `.cs-head` (cancelled `⚠️ Class Cancelled — {ReasonLabel}`; holiday literal; none for normal) | `fa39a0c` + 3 anchor receipts (40/W7, 40/W5, 40/W14) |
| selection-wipe-spares-holder-tips (the 1-line selector fix) | `13460c2` + live receipts (0 orphans after selection) |
| slot-cancel-restores-session-anchor ("No, Keep My Selection" restores the handoff trigger) | `58efa83` + byte-equal receipt |
| summary-card-click-navigate (`navigateToSavedBlock` + the tooltip + the × intact) | `f4005cc` + away-hop receipt |
| notifications-real-bridge (the table, the emitter ×4 actions, the islands, the durable POST, the reminder command, the JS retirement) | `f3b15d7` + suite 273/273 |
| The poll-cycle receipts (badge 1→2 mid-flight, no reload; the durable POST; the open-panel survival) | `647dd34` |
| pageLivewire() hotfix (the 8 call sites; the category-aware caught-up text) | `6be2235` |
| CodingMAIN.md synced to the Wave 3b reality; `docs/viva-demo-runbook.md` drafted; `docs/README.md` indexed | `05a6737` |
| The tidy tooltip family, makeup-busy-vector, pending-cell amber, B005 repeal etc. — earlier receipted (see §F) | committed |

### Partially completed

- `notifications-real-bridge`: **applied + receipted but NOT archived**;
  the Reverb upgrade is deliberately future.
- The viva demo pack: the runbook exists (draft); the SMTP flip, NFR
  benchmark receipts, UAT script = pending.
- The demo seed rows (T6) drafted in the apply message — the USER still
  needs to run them (or skip; the two "Demo receipt" rows already give a
  visible bell).

### Planned (agreed, not started)

- Notification "handled/inactive" nuance for OTHER statuses (only
  `cancelled` requests currently disable rows) — parked.
- Reverb (true push) upgrade — additive future lane.
- NFR 1.2 benchmark (<100 ms OCC) + the engine <500 ms receipt — for the
  demo-pack week (no number written until measured).

### Unverified / believed-work

- The user's manual retest of approve/reject/cancel-request flows after
  the pageLivewire hotfix (the hotfix targeted correctly in the live
  probe, but the user has not confirmed the actions end-to-end).
- Gmail SMTP delivery (the SV approved; the setup not yet performed).

## E. Bug Reports and Investigation Findings

| # | Bug | Root cause | Status |
|---|---|---|---|
| 1 | Occupied cells without tooltips (the original investigation) | The venue-grid arm emitted 3-key triples; the blade's `cellData[3]` branch never got data (the designed 4th key was never implemented) + the `?week=` landing confusion | FIXED (occupied-cell-explanations) + the week UX explained |
| 2 | The phantom B102 block in tooltips | The holder labels collided on the shared (day,start,end) key — room-blind | FIXED (the room-rider key, `c9c5768`) |
| 3 | The tooltip blocks lacked why-tags | The user's own add (round 3 of the tidy lane) | DONE (👤/🏛/👥 tags) |
| 4 | Selection wiped tooltips | `clearMergedBlock`'s domain-blind selector | FIXED (13460c2) |
| 5 | The slot-dropdown cancel blanked the trigger | The handoff's null `lastSlotIndex` | FIXED (58efa83) |
| 6 | THE HOTFIX REGRESSION (pageLivewire): cancelRequest/reject/approve/intentSessionOptions 500 "public method not found" | The bell islands broke `Livewire.first()` | FIXED (pageLivewire(), 6be2235) — the user should re-verify by hand (their real request #6 = their test case) |
| 7 | "No new notification after submit" | NOT A BUG — the rows WERE created (ids 3/4); the arrangement page has no bell (BUG-04 frozen) | Explained |
| 8 | Pill=1 next to "all caught up" | The F-2 whole-mailbox total vs the per-category tab | IMPROVED (the category-aware caught-up text) |
| 9 | W14 Thu/Fri "occupied without tooltips" investigation | ALSO found: the phantom W14 occurrence on the strip; the W7-vs-W14 landing mismatch | Resolved via the two header/status lanes + the documented design law |
| 10 | BUG-01/02/03/05/06 (the 2026-10-24 pending bugs) | See docs/replacement-arrangement-pending-bugs.md | **PENDING the user's verdicts** — do not open fix lanes until they review |

## F. SDD and Development Workflow

**Applied, awaiting `/sdd-archive`** — **12 lanes** (grep-verified; archived only on the user's word):

1. `mock-statics-die` (+ the Diploma scale amendment)
2. `b005-diploma-allowed`
3. `makeup-busy-vector` (+ the pending-cell amber round)
4. `occupied-cell-explanations`
5. `holiday-occurrence-badge` (partially superseded by #6)
6. `conflict-schedule-header-status-tip`
7. `selection-wipe-spares-holder-tips`
8. `slot-cancel-restores-session-anchor`
9. `summary-card-click-navigate`
10. `notifications-real-bridge`
11. `cancelled-blocks-stay-visible`
12. `class-cancel-2-working-day-window` + `class-cancel-db-persist`

**EXCEPTION (do not try to archive)**: the tidy-tooltip work
(why-tags/rounds/hotfixes, commits `de3db81`/`c9c5768`/`7dd99a4`) shipped
WITHOUT its own `.sdd/changes/` dir (it rode the
`prompts/pi-tidy-arrangement-cell-tooltips.md` prompt + commit chain) —
there is nothing to archive; the changelog is the record.

**Also in `.sdd/changes/` — WIP, NOT applied (the new agent must know):**
- 3 dirs with PARTIAL artifacts and **no sdd.yaml** (do not treat as
  lanes): `venue-c2-toast-noise`, `venue-intent-banner-kind`,
  `venue-intent-ghost-success-spot` — fix or delete by the user's
  decision.
- 2 ACTIVE lanes: `class-modal-affordances`,
  `selection-budget-original-duration`; 1 in EXPLORE:
  `cohort-venue-replace-now` — their state is unclear; re-verify before
  touching.

**Frozen**: NONE (the notifications lane's sdd.yaml `frozen` label was
caught during this audit and flipped to `applied`).

**SDD storage**: `.sdd/changes/<name>/` (proposal/design/tasks/
review-log/sdd.yaml; the status flow: proposed→review→frozen→applied).
Helper scripts: `SDD_ROOT=.sdd bash ~/.config/opencode/tools/sdd/{new-change,list-changes,change-status,archive-change}.sh`.

**Rules**: batches created one at a time with reviewer rounds
(MAX 5); frozen artifacts = declarative-only edits; a decision-level
change = unfreeze the chain. The reviewer verdict: no `### 🔴
Outstanding` (or empty) = pass/freeze.

**Temporary code to remember**: `TEMP-HOLDER-LABEL` (grep-able ×5 —
teardown AFTER the final viva).

## G. Current Problems and Pending Tasks

1. **[USER]** Verify by hand: approve / reject / cancel-request /
   venue-book flows after the pageLivewire hotfix (request #6 pending is
   the ready test case).
2. **[USER]** Run (or skip) the T6 demo seed INSERTs for a populated demo bell.
3. **[AGENT, on the user's word]** `/sdd-archive` ×8 lanes.
4. **[USER]** The BUG round verdicts (BUG-01/02/03/05/06).
5. **[USER/OPS]** Gmail SMTP flip (`MAIL_MAILER=smtp`, host
   `smtp.gmail.com`, port 587 TLS, an App Password) + Mailpit as the
   fallback — the runbook has the setup.
6. **[AGENT, demo-pack week]** the NFR benchmark receipts; the UAT script.
7. **[USER]** Confirm the viva date (anchored: week of 7 Dec 2026).
8. **[USER]** Push the commit stack.
9. **[LOW]** `?week=14`-style deep links still land on the remembered week
   (accepted); the notification "handled" nuance beyond `cancelled`.

**Hard absolutes the new agent MUST know (previously missing):**

- **⛔ TC70**: `tests/venue-timetable.spec.ts:690–730` must NEVER execute
  before T10 retires it — a real destructive write against the demo DB.
  Run any venue-timetable suite with `--grep-invert "TC70"`. The rule
  lives in the repo's AGENTS.md and the skills — READ AGENTS.md.
- **The queue worker**: NOTHING is running `queue:work` right now — the
  queued Mailables pile up in the DB `jobs` table and send only when the
  worker runs (`php artisan queue:work`, or `--once` for one). The SMTP
  flip is pointless without it (the demo-pack item).
- **Playwright status**: the 273/273 receipt is PHPUNIT only. The
  data-dependent Playwright specs (request-approval, warning-keywords)
  are NOT re-receiably true post-bridge — the pending request #6 can
  change their shape; re-run before trusting them.
- **Two UNTRACKED files** exist at the root: this handover file and
  `docs/pi-prompt-notifications-sdd-verify-fixes.md`. CAUTION: the root
  `.gitignore` only covers `prompts/pi-prompt-*.md` — a `docs/pi-prompt-*`
  file can be committed by accident; decide its home.
- **CodingMAIN.md stale spots** (during the next docs pass): :40 (Obj 4's
  "NEXT LANE" note — the bridge shipped), :374 (the "read-only until
  Wave 3b" heading), :382–389 (the mock-fallback page table incl. the
  dead `/upcoming-replacements-ui` route), :7 ("Last reviewed: 2026-10-10").

## H. Important File and Technical References

- `CodingMAIN.md` — the synced spec (Objective 2/4 🟢; the read-only rule
  retired; the notif bridge = the NEXT-LANE note now applied).
- `app/Support/Notifications/NotificationEmitter.php` — the five-event
  emitter (after-commit; `send` via `write`; the dept-PL scoping).
- `app/Livewire/NavNotifications.php` + `resources/views/livewire/nav-notifications.blade.php` — the island (variant bell/panel;
  `wire:poll.10s`).
- `app/Support/ReasonLabel.php` — the ONE reason-label law (don't
  duplicate).
- `resources/views/partials/ui-nav-bar.blade.php` /
  `ui-notifications-panel.blade.php` — the nav shell + the panel island
  mount.
- `resources/views/partials/page-data-script.blade.php` — the payload
  shard (the >60KB chunking — CRITICAL for big PAGE_DATA payloads).
- `public/js/ui-common.js` — `pageLivewire()` (line ~1882),
  `durableMarkNotifRead`, the rewritten `initNotifPanel`; the retired
  mock-phase notif functions are GONE.
- `app/Services/MatrixIntersectionEngine.php` — the busy vectors (the
  room riders; the makeup vectors) — DO NOT break the 5-slot tuple law.
- `app/Livewire/ReplacementArrangement.php` — the payload builder
  (`venueSlotsByWeekPayload` 4-key + `holderTextLibrary`; `busyByWeekPayload`
  with the inline labels — TEMP-HOLDER-LABEL).
- `resources/views/livewire/replacement-arrangement.blade.php` — the
  arrangement's render components (`renderHolderTip` + the grid
  pseudo-matches at ~:1803; `writeSlotTriggerFromAnchor`;
  `navigateToSavedBlock`; the clearMergedBlock selector law).
- `app/Actions/*` — the emission call sites (submit/approve/reject/cancel).
- `app/Console/Commands/NotifyPendingRequests.php` + `routes/console.php`
  — the reminder (daily 08:00 + on-demand).
- `routes/web.php` — `POST /notifications/read` + `/read-all`.
- `docs/viva-demo-runbook.md`, `docs/demo-db-data-changes.md`,
  `docs/replacement-arrangement-pending-bugs.md`, `docs/queue-and-emails.md`.
- `page-changelogs/*.md` — update after any page change.
- Commands: the stale-serve ritual; `php artisan queue:work --once`;
  `php artisan notifications:remind-pending`.

## I. Latest Session State

1. **Most recently**: the user said HI; before that, the
   notifications-real-bridge apply + the pageLivewire hotfix + the
   poll-cycle receipts, then the Gmail/SV interlude and the
   Claude_Gmail_SMTP_Setup_Prompt.md review/correction (DONE — the file
   was rewritten with the approval).
2. **Investigated & closed**: the four 500s (the Livewire.first()
   regression), the notification rows existing after submit, the
   caught-up text, the Gmail SMTP prompt audit.
3. **Unfinished**: the archive sweep ×8; the BUG verdicts; the SMTP ops
   flip; the benchmarks; the manual retest of the page actions.
4. **The new AI should do first**: read this file top to bottom, then
   `git status` + the tail of `page-changelogs/notifications-panel-changelog.md`
   to confirm sync; await the user's direction (they gate everything).
5. **MUST NOT do without approval**: push; demo-DB writes (outside the
   logged convention); opening/reopening SDD lanes; touching the user's
   real requests; changing the frozen UI classes/copy; skipping the
   reviewer rounds.

---

## Instructions for the New PI Chat Session

- Load the repo skills first (`crs-workspace`, `sdd-workflow`) and
  `prompts/my-coding-rules.md`; CodingMAIN.md is the spec source.
- The house language is B1 English. Be concise; receipts over claims.
- When the user says "go --> <prompts/pi-*.md>", execute that file
  verbatim.
- The prompt files live in `prompts/` (`pi-*.md`); Claude handoffs too
  (`Claude_Gmail_SMTP_Setup_Prompt.md` — already corrected).
- Verify every claim with Playwright or tests before reporting; archive
  lanes only on the user's word; keep `docs/` + `page-changelogs/` in
  sync with every change.
- When in doubt about a demo-DB write: don't — draft the SQL and hand it
  over.
