# Coding to-do — Poong Foo Jing (RSD3 G2)

> **Coding only.** The report, the survey and the week-by-week schedule live in
> **`docs/fyp-todo.md`** — never plan writing here, and never plan code there.
> **Last planned: 2026-11-10 (Tue, 06:45) — the plan below is set for Tue 10 Nov.**
> Re-plan every Monday (15 minutes) or after any lane lands.

**Status key:** `- [ ]` not done · `- [x]` verifiably done (commit hash + a green gate) · 🔴 high · 🟡 medium · 🟢 low

**Sources of truth — do not copy their detail here:**
`.sdd/changes/<lane>/tasks.md` (lane tasks) ·
`prompts/extra tasks/README.md` (parked UI work) ·
`prompts/for-ai-to-read/PI_Chat_Context_Handover.md` (rulings + traps).
This file only decides **what to code next**.

---

## 1. Codebase state (verified 2026-11-10, 06:45)

| Item | State |
|---|---|
| Branch | `fedora-backend`. **Everything is pushed** — `origin` = `da4de30` and the branch is **0 ahead** (you pushed 2026-11-10 06:34). The agent never pushes |
| Demo servers | `:8000` = `class_replacement_fresh` (read-only for agents; agent serves it on `:8010`). `:8001` = the viva twin, `CRS-demo` + `class_replacement_viva` — **307 commits behind** `fedora-backend`. `:8030` = disposable lane on `class_replacement_testing` |
| Demo data | **42 modules · 101 sessions · 15 requests · 128 notifications (6 hidden) · 169 users · 155 students** (2026-11-10). Requests: 2 approved (26, 27) · 3 pending (28, 35, 39) · 3 rejected (24, 37, 38) · 7 cancelled (25, 30–34, 36). These **drift** — you add data by hand, so re-measure before quoting. Any write is user-gated, snapshot-first, logged in `docs/demo-db-data-changes.md` |
| PHPUnit | Last **full** run **423 passed / 2371 assertions**, 2026-11-07 (the 3 date-dependent errors of that weekend run were later proven pre-existing). **11-08 → 11-09 ran targeted suites only** — re-run the full suite before quoting a total |
| Browser specs | `arrangement-conflict-autoselect` **10** (run 3×, committed tree clean) · `venue-book` 19 (18 green + the known `U2a-T3` hover flake) · `arrangement-slot-ownership` **7** (run it ALONE) · `status-tooltips-filter-chips` **11** · `replacement-home-working-days` **8** · `replacement-home-status-badges` **13** · `notifications-island` 10 · `notif-row-inactive-hide` 5 · `week-filter-preserved` 3 · `cancel-class` 16 passed / 4 skipped |
| Gates | `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` (**never** `types:check`) · full PHPUnit · `node --check` for JS · `php artisan view:cache` for Blade |
| Write guards | `tests/helpers/receipt-target-guard.ts` — a writing spec refuses to run (exit 1 at collection) unless `CRS_MODE=testing` + `CRS_BASE=http://127.0.0.1:8030` (+ `CRS_ALLOW_SQL=1`) are exact. Never loosen it, never add a soft skip |
| SDD | **1** lane in `.sdd/changes/` (`cohort-venue-replace-now`, `explore`, never built) + **145** archived |
| Known red | **`T3d`** — `tests/submission-conflict-refresh.spec.ts` fails inside its own arrival flow (a login redirect timeout, then `#weekSelector` absent). The only browser receipt not green |

> **Removed 2026-11-05 at your direction:** `docs/replacement-arrangement-pending-bugs.md` (the
> 2026-10-24 BUG-01…08 round) is deleted, so no BUG-xx fix items are tracked here any more. It
> stays in git history if anything from it is ever wanted back.

---

## 2. How to run a coding day

1. Pick **one main task** (60–120 min) and **one small task** (15–30 min) from §3. Stop at three.
2. Read the source doc for the item (bug doc / lane `tasks.md` / parked prompt) — never work from memory.
3. Start the server with **`scripts/dev-serve.sh [port]`** (never `pkill -9 php`, never one combined kill-and-serve string). `:8030` for writes: `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`.
4. Work → **gates** → live check → **changelog** entry (`page-changelogs/<page>-changelog.md`, newest first) → commit.
5. Tick the box **with the receipt** (commit hash, gate output). Then ask: *"do you want me to archive this?"* — never archive on your own.
6. SDD: a new behaviour needs a lane under `.sdd/changes/<name>/`; a display-only fix may be waived by you (say so in the changelog). Commit conventions: `ui:` / `feat:` / `fix:` / `refactor:` / `oop:` / `style:` / `docs:` / `test:`.

---

## 3. Tue 10 Nov — TODAY (one main + one small)

- [ ] 🔴 **MAIN (60–90 min) — C1 `T3d`.** Decide it, don't leave it dangling: either make
      `tests/submission-conflict-refresh.spec.ts` run green (timebox ~45 min: it fails inside its
      own arrival flow — a login redirect timeout, then `#weekSelector` absent), or quarantine it as
      `test.fixme()` with the reason written into
      `.sdd/archive/receipt-target-guard/tasks.md` (the `T3d OPEN` note already lives there; the lane
      was archived on 2026-11-05). Either way the suite stops being *"green except one nobody looks at"*.
- [ ] 🟡 **SMALL (10 min) — C12 delete the junk file.** `--log-events-text` is still tracked at the
      repo root (the second accidental file, `"`, is already gone). `git rm` it, one `docs:`/`chore`
      commit, nothing else staged.
- [ ] ⏱️ **Only if both are done** — C5 the warn-modal colour (colour only, no copy, no logic), or
      C8 the UAT results table (run the flows you already have specs for and record real pass/fail).

---

## 4. Backlog by priority

### 🔴 High — correctness, receipts, demo readiness

**C1 · `T3d` — the one red browser receipt** *(today's main)*
- [ ] Green it, or quarantine with the reason recorded — see §3

**C2 · Refresh the viva copy** (demo readiness, ~30 min — bigger than it looked)
- [ ] `CRS-demo` is **307 commits behind**: fast-forward it (code only — **no DB work**), then one live
      check on `:8001` (venue grid + a booking + the bell). Its DB (`class_replacement_viva`) is untouched
      by this.

**C11 · `cohort-venue-replace-now`** — the only lane left in `.sdd/changes/`
- [ ] Status `explore` with five open tasks and **never implemented**. Read `proposal.md` with the user
      first; it is the last unfinished idea in the backlog, not a half-done feature.

### 🟡 Medium — quality, evidence, small features

**C4 · "Just cancelled" chip TTL** — `prompts/extra tasks/pi-prompt-just-cancelled-chip-ttl.md`
- [ ] Display-only 30-minute TTL on the chip (no data change, no new dependency)

**C5 · Warn-modal colour** — `prompts/extra tasks/task-01-warn-confirm-modal-colour.md` *(today's small alternative)*

**C6 · One honest timeline rail** — `prompts/extra tasks/task-02-replacement-history-timeline.md`
- [ ] All four rails: the 8-hour `reviewedAt` skew, cancelled-looks-reviewed, the permanently empty
      "Viewed" step, hardcoded "In progress"/"Next". The student rail is currently removed — this is
      the biggest parked item; do it when a day is otherwise empty

**C7 · NFR benchmark receipts** (measure, don't remember)
- [ ] OCC validator overhead < 100 ms and engine < 500 ms — a tinker timing script, numbers pasted
      into `docs/viva-demo-runbook.md` with the date and the row counts

**C8 · UAT results table** (real runs, not a template)
- [ ] Turn `page-changelogs/todo list/test-cases.md` into pass/fail rows from runs you actually did,
      quoting the suite count measured that day

**C9 · Staff User Panel** — `prompts/extra tasks/Propose01-UIUX Update — Staff User Panel.md`
- [ ] Decide with the user whether it is real work (then it needs an SDD lane): avatar = staff ID,
      id line = email, role = `Staff` / `Staff (PL)`, staff side only

**C10 · `notifications:remind-pending`** — designed, scheduled **nowhere** (your call 2026-11-04)
- [ ] Only if the demo wants the pending reminder: schedule the command + one live receipt. Otherwise
      leave it dormant and say so

### 🟢 Low — only if a day is light

- [ ] Settings-surface footer (deliberately excluded from `ui-footer-global`)
- [ ] Any copy/tooltip polish found while testing
- [ ] Dead helper `UiLabels.courseTip()` in `ui-common.js` (no live call site since 2026-11-09, and its
      comment still claims both row builders use it) — delete the function and the stale comment together

---

## 5. Waiting on you (each one blocks a coding item)

| # | What I need | Blocks |
|---|---|---|
| 1 | `T3d`: fix it properly, or quarantine it | C1 |
| 2 | The viva date (still unconfirmed — anchored week of 7 Dec 2026) | C2, C7, C8 and the demo data |
| 3 | `cohort-venue-replace-now`: read the proposal, then say build it or drop it | C11 |
| 4 | Staff User Panel: real work or parked? | C9 |
| 5 | Whether `notifications:remind-pending` is wanted for the demo | C10 |
| 6 | ~~Whether the auto-reject sweep should also run at SUBMIT time~~ — **settled 2026-11-12**: no submit-time sweep. The scheduled run now exists (crontab every minute → `schedule:run`, `DB_DATABASE=class_replacement_testing`), so the trigger was the missing piece, not the write path | nothing |
| 7 | `my to-do list.md` task 2 (Remove Change Venue Confirmation Modal) — parked, one question open | task 2 |

---

## 6. Done — verified (do not re-do)

**2026-11-07 → 2026-11-09.** `student-class-replacements` (the student page lists conflicted
**classes**, not requests; five cards, the hours card counting only unarranged rows — `758a7bc`,
`c8eadeb`, `da4de30`, archived `6ffa054`) · `arrangement-week-keep-on-subject-switch` (changing the
subject no longer moves the week — `b3fedcb`, archived `00af0b5`) · the student side health check's five
fixes (campus clock `cedc8ca`, Today `0369a11`, a notice lands on its own week `aca8f49` + `57adf18`,
row truth `336528b`, all-weeks default `c11f38b`) · login pages guest-only + a real 403 page (`2c15d2c`)
· module titles `Module::displayName()` (`65f1648`, `d6a9b84`, `f263692`) · tooltip above the hour header
+ a Today holiday badge that stays red (`52298d6`) · the COURSE column explained once on its header
(`5df521c`) · the student table joined the staff language (`aaf8163`, `c3b566d`) · `docs/demo-logins.md`
(`fea3631`) · the H1 rename that the first three names missed (`781e839`).

**Earlier (unchanged):**
- [x] **Date & time format, batches 1–4** (`10a51b0`, `5120a98`, `f420215`, `1dc3edb`); only #30 (API chip) deferred
- [x] **Working-day countdowns + the PL Urgency column removed** (2026-11-07, `replacement-home-working-days` 8/8)
- [x] **Arrangement cell state labels + the slot-ownership legend** (`c4e2fc5 … d029a6c`, archived) — six legend rows, `Your Pending` / `Other Pending`, 3px = yours
- [x] **`replacement_span` persistence + the backfill command** (2026-11-07, `ReplacementSpanTest` 8/8)
- [x] **Auto-reject rule** (`65188bd`, `0694fa5`): 3 working hours, `WorkingHours` is the only clock
- [x] **FR 2.17 server gate** (`cd5ca73`, `32b2d61`): `slot_too_soon` + the holiday landing ban in the action
- [x] **Lane archive sweeps** (`ef0a1b4`, 36 lanes at once; `6ffa054`, `00af0b5`): `.sdd/changes/` now holds one lane
- [x] **Venue slot validation** (`bee6176`) · **Venue bookable cells** (`daf11c6`) · **Cancel Class is FINAL** (`7b7f0d9`, `23c627c`, `12c644a`, `2156172`) · **E2E write-guard** (`b924552`, `7bbce9f`) · **after-submit row pulse** (`a29f694`) · **`ME` → `YOU`** (`476406a`, `43bda16`) · **`request-approval-ui` consistency 1 + 2** · **merge lane** (`replacement-merge-one-table`, archived `07274c1`) · **`ui-footer-global`** (`fea58ad`, archived) · **notification clock A + B1** (`67413e3`)

---

## 7. Maintenance rules for this file

- **Coding only.** Report/survey/schedule → `docs/fyp-todo.md`. If a task is "write a chapter", it
  belongs there, not here.
- **Tick only what you verified** — a commit hash plus a gate (test count, PHPStan 0, a live screen).
- Never copy a lane's detail here; link the lane's `tasks.md` (or its parked prompt).
- One lane per line. When something ships, move its line to §6 with the receipt — do not leave it in
  the backlog "just in case".
- Keep it under ~10 new lines a week; cut 🟢 first.
