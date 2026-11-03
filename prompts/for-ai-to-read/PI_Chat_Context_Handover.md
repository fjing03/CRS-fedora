# PI Chat Context Handover — TAR UMT Class Replacement System

> **Restructured 2026-11-03.** The six session addenda that used to sit here
> (§0.5 – §0.10, ~540 lines) were moved verbatim to
> **`history/2026-10-31-to-2026-11-03-addenda.md`** — archived prompts and SDD
> review logs still cite those section numbers, so they keep their names there.
> Everything in this file is either current state, a standing ruling, or a
> pointer. Paths are repo-root-relative unless they start with `../`.
>
> **Every volatile number below is marked with its date.** Re-check it before you
> quote it; a stale count has caused wrong decisions before.

---

## 0. Quick Status (read this first)

| Item | State |
|---|---|
| Branch / remotes | `fedora-backend`; `origin` = `fjing03/CRS-fedora.git`; `upstream` = `FjingXR/class-replacement-system` (**pull-only**) |
| Push policy | **NEVER `git push`** — the user pushes. One commanded exception, 2026-11-02; afterwards the rule resumed. |
| Unpushed commits | **79** (2026-11-03, after this session). Re-count: `git rev-list --count origin/fedora-backend..HEAD`. |
| AI context layout | Root **`AGENTS.md`** is a bootstrap pointer → this folder holds `AGENTS.md`, `CodingMAIN.md` (the spec), `my-coding-rules.md` (quick rules), `FR&NFR copy.md` (read-only without the user's approval), this file, and `history/`. |
| Servers | `:8000` = `CRS-fedora-backend` / **`class_replacement_fresh`** (dev). `:8001` = `../CRS-demo` / **`class_replacement_viva`** (viva twin, `APP_DEBUG=false`). `:8030` = disposable AI/E2E lane on `class_replacement_testing` only. |
| Database trap | `class_replacement_demo` is the **older, empty** twin (0 requests) — not the viva DB. |
| Demo data (2026-11-03) | `fresh`: 3 requests — 24 rejected, 25 cancelled, 26 pending (all W14), **no approved row**. `viva`: 4 requests, incl. **27 approved** (W7, replacement 3 Nov 2026 10:00–11:00). Both were cleaned to blank on 2026-11-02 and rebuilt by the user's own testing since. |
| Test DB | `class_replacement_testing` — disposable; `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed` is fine **there only**. |
| Ops trap | `opcache.enable_cli=On` with `revalidate_freq=180` → a long-running `artisan serve` can serve a `.php` file's old bytecode for ~3 min. Use **`scripts/dev-serve.sh [port]`** (kills that port, clears views, starts with `revalidate_freq=0`). |
| Gates | `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` · full phpunit · `node --check` for JS. **Never** `composer run types:check` (crashes at 128M). |
| Suite | Last full run **295/295** (2026-11-02). Re-run before quoting; no PHPUnit run has been done since. |
| SDD lanes | **39** lane dirs, **93** archived, **38** with a proposal (2026-11-03, after the merge lane's proposal froze). Archive only on the user's word. |
| Language | **B1-level English only** — no other language, ever. |
| Approval rule | Investigate first. **No code edit without the user's explicit per-task approval.** |

---

## 1. Latest session — 2026-11-03 (this one)

**Later in the same day — the merged replacement page (docs only, nothing pushed):**
- The user finalised the **one-table** design: `/replacement-home-ui` becomes
  **My Replacements** (one row per class occurrence, seven columns, status in the tab
  label and as a badge, no HISTORY block, icon-only actions, five cards). Alias
  `/my-request-history-ui` stays. Full record: `.sdd/changes/replacement-merge-one-table/explore-brief.md`.
- **Static prototype** (discussion instrument, never staged, outside the repo):
  `../ui-prototype/replacement-merged-preview.html` — loads the live `theme.css` from
  `:8000`, so the dev server must be running to see real colours.
- **Proposal frozen after 4 review rounds** (`ccaa7ba`, log in
  `.sdd/changes/replacement-merge-one-table/review-log.md`): round 1 failed on the
  cancel-path owner and an unsourced test inventory; round 3 failed on three unbucketed
  test files; round 4 passed. Next batch: `design.md`.
- **New implementing brief:** `prompts/pi-replacement-merge-one-table.md` — the merge lane's
  PI prompt, written from the frozen proposal.
- Reversals recorded the same day, so they are not re-done: the page says **Course** (a
  `Subject` rename lasted an hour), **`Exclude COMPLETED Status`**, cohorts stack **one per
  line**, and the toolbar count hides **below 1500 px**.

**Landed earlier today (docs only, nothing pushed):**
- Ruling **14** added below: a public-holiday class can never be cancelled.
- Two implementing briefs committed, in dependency order:
  1. `prompts/pi-replacement-home-status-badges.md` — `0b11d36`. Keep a pending/approved
     row on the Replacement Home and swap its Arrange button for a clickable status
     badge that jumps to My Requests.
  2. `prompts/pi-request-completed-status.md` — `850db2e`. Derive **Completed** for an
     approved request whose replacement day has passed; hide completed rows by
     default on all three pages. **Must not start before lane 1 is merged.**
- This handover was restructured to six sections; the addenda moved to `history/`.
- **The lab booking roster now matches the timetable** (user ruling, SDD waived
  by the user, decision recorded in `docs/venue-restrictions.md` R3): BMIT2013
  plus the other eight subjects whose seeded L/T already sit in a lab joined
  both `lab_access_module_codes` and `b006_allowed_module_codes`. A new seed
  invariant (`TimetableSeedInvariantsTest::test_t8_…`) fails if a future import
  drifts from the roster.

**In flight, other session:** `.sdd/changes/notif-recipient-rulings/` (proposal frozen
and approved with two refinements; batch-2 design). Product code untouched as of this
date, and its files are dirty in the tree — see §3.

**Parked (do not start unless the user names it):**
- The four timeline rails — `prompts/extra tasks/task-02-replacement-history-timeline.md`
  (a visible placeholder note prints above each rail, `b58c884`).
- The Chapter 5 browser-evidence run (`prompts/ch5-playwright-evidence-8030.md`);
  Phase 1 (`CRS_BASE`) landed in `2f3b0be`, the run itself is not done.
- BUG-01/02/03/05/06 verdicts; the footer lane; the `receipt-target-guard` test lane.
- The viva demo data decision (both DBs are thin, not the old populated story).

---

## 2. The user's standing rulings (do not change without approval)

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
| 14 | **A class that falls on a public holiday can NEVER be cancelled** (user-confirmed 2026-11-03 — the correct behaviour, not a gap). Rule **S4** rejects `day.holiday` in the shared guard `Cancellation.isCancellable()` (`public/js/ui-common.js:3837`), enforced at the button (`:4460`), the confirm path (`:4100`) and the stale-button sweep (`:4721`). Browser proof: `tests/cancel-class.spec.ts:447` (the block's modal opens on the Deepavali in-lieu Monday but offers no cancel action). Do **not** "restore" the missing button; a holiday class is handled by arrangement, not cancellation. |

---

## 3. Safety laws, traps, and ways of working

**Database**
- Never `migrate:fresh` / `migrate:refresh` / `db:wipe` / reseed a demo DB. Any demo
  write is **user-gated per write**, snapshot-first (`pg_dump -Fc`, `pg_restore --list`
  must pass, dumps in `backups/`), and logged in `docs/demo-db-data-changes.md`.
- **⛔ `--env=testing`**: no `.env.testing` exists, so it silently boots the *demo* DB.
  Select the testing database only with `DB_DATABASE=class_replacement_testing`.
- **Schema migrations on a demo DB are additive only** — add a nullable column or an
  index; never `change`/`drop`/data statements. Snapshot → rehearse on the testing DB →
  print the target → plain `migrate` → row-count receipt + log.
- **Never stage `docs/demo-db-data-changes.md`** — it is another session's write log,
  unless that log *is* the task.

**Destructive tests**
- **⛔ TC70**: `tests/venue-timetable.spec.ts:690–730` must never execute until it is
  retired — it writes against the demo DB. Run that suite with
  `--grep-invert "TC70"`.
- Guarded E2E runs require `CRS_BASE` exactly `http://127.0.0.1:8030` **plus**
  `CRS_MODE=testing` (and `CRS_ALLOW_SQL=1` for SQL fixtures). Every spec honours
  `CRS_BASE ?? http://localhost:8000`; the default port is a demo DB.

**Runtime**
- **No queue worker is running.** Queued mailables pile up in `jobs`. The dev repo is
  on the `log` mailer (harmless); the viva repo is on `smtp` and holds **27 stale jobs**
  that must be purged (gated write) before the first worker run. `failed_jobs` was 0.
- After any Blade/PHP edit: `scripts/dev-serve.sh 8000` (never combine `pkill` and the
  serve start in one shell string). The `:8001` twin restarts separately:
  `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`.
- The dev `.env` Gmail App Password was exposed in a chat paste on 2026-11-03; the user
  declined rotation. Rotate if they ever ask.

**Receipts and honesty**
- No claim without a receipt. Fresh in-flow login (staff `input[name="login_id"]`),
  screenshots + DOM snippets. Distinguish CONFIRMED from SUSPECTED.
- Playwright gotchas: run `.cjs` helpers from the repo cwd (`package.json` is
  `"type": "module"`); avoid `ctx` name collisions; `:visible` is not a CSS selector;
  add `?_=` cache-busters.
- The 295/295 receipt is **PHPUnit only**. The data-dependent specs (request-approval,
  warning-keywords) must be re-run after any data change.

**SDD**
- Every code change runs proposal → reviewer rounds (max 5) → freeze → apply → verify →
  archive. Small fixes may combine proposal+design+tasks in one batch.
- Reviewer: the `sdd-reviewer` subagent. The legacy CLI recipe (`pi --model …glm-5.3-flash`)
  is in git history, not needed.
- Frozen artifacts take declarative-only edits; a decision-level change unfreezes the
  chain. Archive only on the user's word.
- Known-exception lanes that shipped without `.sdd/changes/` are recorded in the lane's
  changelog; do not invent retroactive SDD dirs.

**How to talk to this user**
- Simple everyday English, B1, no jargon dumps. Decisions as options with their main
  risks and benefits plus a recommendation. **One clear question at a time**, phrased
  "So the question in plain terms: …". Never start an approval-gated action before a yes.
- Conventional commits (`feat:`/`fix:`/`ui:`/`oop:`/`refactor:`/`style:`/`docs:`/`test:`),
  one lane per commit series; commit only the files the task touches.

---

## 4. Where the truth lives

| Question | Look here |
|---|---|
| Architecture, FR/NFR, conventions, git policy | `CodingMAIN.md` (the spec) |
| Quick coding rules, UI colour canon | `my-coding-rules.md` |
| The requirements as the code sees them | `FR&NFR copy.md` (49 FR / 26 NFR, `v2026-11-02`; read-only without approval) |
| What changed on a page, with dates | `page-changelogs/<page>-changelog.md` |
| Lane history, design decisions, review logs | `.sdd/changes/` and `.sdd/archive/` |
| Demo-DB writes, restores, snapshots | `docs/demo-db-data-changes.md`, `backups/README.md` |
| Viva runbook, queue/mail, known pending bugs | `docs/viva-demo-runbook.md`, `docs/queue-and-emails.md`, `docs/replacement-arrangement-pending-bugs.md` |
| The next lane | `prompts/pi-replacement-merge-one-table.md` (the merged page; proposal frozen, `design.md` next) |
| Lanes already landed | `prompts/pi-replacement-home-status-badges.md`, `prompts/pi-request-completed-status.md` |
| The approved picture of the merged page | `../ui-prototype/replacement-merged-preview.html` (outside the repo, never staged) |
| Superseded session diaries | `history/2026-10-31-to-2026-11-03-addenda.md` |

---

## 5. Open tasks

1. ~~**[AGENT, on the user's word]** Execute `prompts/pi-replacement-home-status-badges.md`,
   then `prompts/pi-request-completed-status.md`~~ — **both landed** (`a16727c`/`2e58547`,
   `e2b2f4c`/`7d2bb9a`).
1b. **[AGENT, on the user's word]** The merge lane `replacement-merge-one-table` — proposal
   frozen, `design.md` next, then `specs/`, `tasks.md`, apply. Brief:
   `prompts/pi-replacement-merge-one-table.md`.
2. **[OTHER SESSION]** `.sdd/changes/notif-recipient-rulings/` — frozen proposal + batch-2
   design; apply pending.
3. **[USER]** Confirm the viva date (anchored: week of 7 Dec 2026).
4. **[USER]** Decide the viva demo data — both DBs are thin; a populated story needs
   user-gated SQL, snapshot-first, logged.
5. **[USER/OPS]** Purge the viva repo's 27 stale jobs before any worker run.
6. **[AGENT, on the user's word]** The `receipt-target-guard` test lane (the
   `submission-conflict-refresh` reload fix at `:122`), then commit `test:` + `sdd:`.
7. **[USER]** BUG-01/02/03/05/06 verdicts (`docs/replacement-arrangement-pending-bugs.md`).
8. **[AGENT, demo week]** Chapter 5 browser-evidence run and the report; NFR benchmarks;
   the UAT script.
9. **[USER]** Push the commit stack (**22** local commits, re-count first).
10. **[LOW]** `?week=` deep links still land on the remembered week (accepted); the
    notification "handled" nuance beyond `cancelled`.
