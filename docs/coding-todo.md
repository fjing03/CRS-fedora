# Coding to-do — Poong Foo Jing (RSD3 G2)

> **Coding only.** The report, the survey and the week-by-week schedule live in
> **`docs/fyp-todo.md`** — never plan writing here, and never plan code there.
> **Last planned: 2026-11-05 (Thu, 23:00) — the plan below is set for Fri 6 Nov.**
> Re-plan every Monday (15 minutes) or after any lane lands.

**Status key:** `- [ ]` not done · `- [x]` verifiably done (commit hash + a green gate) · 🔴 high · 🟡 medium · 🟢 low

**Sources of truth — do not copy their detail here:**
`.sdd/changes/<lane>/tasks.md` (lane tasks) ·
`prompts/extra tasks/README.md` (parked UI work) ·
`prompts/for-ai-to-read/PI_Chat_Context_Handover.md` (rulings + traps).
This file only decides **what to code next**.

---

## 1. Codebase state (verified 2026-11-05, 23:00)

| Item | State |
|---|---|
| Branch | `fedora-backend`. `origin` = `39b2bb7` (you pushed tonight); **1 commit unpushed** (`b57bf9d`). The agent never pushes. |
| Demo servers | `:8000` = `class_replacement_fresh` (read-only for agents; agent serves it on `:8010`). `:8001` = `tests`-free viva twins, `CRS-demo` + `class_replacement_viva` (**behind the code right now**). `:8030` = disposable lane on `class_replacement_testing` |
| Demo data | 5 requests · 77 notifications (3 hidden) · 2 `class_exceptions` · 8 slot rows. Any write is user-gated, snapshot-first, logged in `docs/demo-db-data-changes.md` |
| PHPUnit | **344 passed / 1899 assertions** (2026-11-05, 410 s, `DB_DATABASE=class_replacement_testing`) |
| Browser specs | `venue-book` **19 passed** · venue neighbours (timetable / db / status-parity / note-privacy) **61 passed / 2 skipped** · `cancel-class` **12 passed / 4 skipped** · intent-modal + cross-week + cohort-Replace-Now **5 passed** · `my-replacements-after-submit` **4 passed** |
| Gates | `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` (**never** `types:check`) · full PHPUnit · `node --check` for JS · `php artisan view:cache` for Blade |
| Write guards | `tests/helpers/receipt-target-guard.ts` — a writing spec refuses to run (exit 1 at collection) unless `CRS_MODE=testing` + `CRS_BASE=http://127.0.0.1:8030` (+ `CRS_ALLOW_SQL=1`) are exact. Never loosen it, never add a soft skip |
| SDD | **34** lane dirs in `.sdd/changes/` (33 older finished, waiting on your word) + **101** archived |
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

## 3. Fri 6 Nov — TOMORROW (one main + one small)

- [ ] 🔴 **MAIN (60–90 min) — C1 `T3d`.** Decide it, don't leave it dangling: either make
      `tests/submission-conflict-refresh.spec.ts` run green (timebox ~45 min: it fails inside its
      own arrival flow — a login redirect timeout, then `#weekSelector` absent), or quarantine it as
      `test.fixme()` with the reason written into
      `.sdd/changes/receipt-target-guard/tasks.md` (where `T3d-open` already lives). Either way the
      suite stops being *"green except one nobody looks at"*.
- [ ] 🟡 **SMALL (20–30 min) — C5 the warn-modal colour.** Parked prompt
      `prompts/extra tasks/task-01-warn-confirm-modal-colour.md`: the Replacement Arrangement
      "you will lose your selection" confirms get the warning (yellow) face instead of neutral.
      **Colour only** — no copy, no logic. Re-verify the prompt's line anchors first (they drift).
- [ ] ⏱️ **Only if both are done** — C8 the UAT results table (run the flows you already have specs
      for and record real pass/fail), or C2 refresh `CRS-demo` so the viva copy shows tonight's code.

---

## 4. Backlog by priority

### 🔴 High — correctness, receipts, demo readiness

**C1 · `T3d` — the one red browser receipt** *(tomorrow's main)*
- [ ] Green it, or quarantine with the reason recorded — see §3

**C2 · Refresh the viva copy** (demo readiness, ~15 min)
- [ ] `CRS-demo` fast-forward to tonight's commits (code only — **no DB work**), then one live check
      on `:8001` (venue grid + a booking + the bell)

**C3 · Lane archive sweep** (needs your word, ~30 min)
- [ ] The **33 older finished** lane dirs in `.sdd/changes/` → `git mv` + `sdd.yaml: archived` + a
      `docs:` commit (same pass as 2026-11-05)
- [ ] `venue-slot-validation` too, once you have read its changelog entry

### 🟡 Medium — quality, evidence, small features

**C4 · "Just cancelled" chip TTL** — `prompts/extra tasks/pi-prompt-just-cancelled-chip-ttl.md`
- [ ] Display-only 30-minute TTL on the chip (no data change, no new dependency)

**C5 · Warn-modal colour** — `prompts/extra tasks/task-01-warn-confirm-modal-colour.md` *(tomorrow's small)*

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

- [ ] Two accidentally tracked junk files (`"` and `--log-events-text`) — delete
- [ ] Settings-surface footer (deliberately excluded from `ui-footer-global`)
- [ ] Any copy/tooltip polish found while testing

---

## 5. Waiting on you (each one blocks a coding item)

| # | What I need | Blocks |
|---|---|---|
| 1 | `T3d`: fix it properly, or quarantine it | C1 |
| 2 | The word to archive the 33 finished lanes | C3 |
| 3 | Staff User Panel: real work or parked? | C9 |
| 4 | Whether `notifications:remind-pending` is wanted for the demo | C10 |

---

## 6. Done — verified (do not re-do)

- [x] **Venue slot validation** (`bee6176`, lane `venue-slot-validation` applied): hover asks one
      clear hour; the picked class's real duration decides; warning modal instead of an early verdict.
      `venue-book` 19 passed, PHPUnit 344 / 1899
- [x] **Venue bookable cells** (`daf11c6`): honest `cell-no-room` state, class-driven late cells,
      Earliest-bookable + `Bookings open` chip, the invented "2-hour gap" number deleted; 78 cells
      audited with 0 mismatches
- [x] **Cancel Class is FINAL** (`7b7f0d9`, `23c627c`, `12c644a`, `2156172`): undo gone from UI, JS
      and server; one shared irreversible line on four modals; stale notices inactive at read time
- [x] **E2E write-guard** (`b924552`, lane `receipt-target-guard` applied `7bbce9f`): writing specs
      refuse at collection unless the blessed `:8030` env is exact
- [x] **After-submit row pulse** (`a29f694`): no success toast, `?new-req=<id>`, the new row flashes —
      510 / 564 ms to navigate, zero toasts, saved filters intact
- [x] **Tooltip `ME` → `YOU`** (`476406a`, `43bda16`), live pages + the two mock-ups
- [x] **`request-approval-ui` consistency 1 + 2** (1f, 3B, 3C, 3A, 3D, 1h, 3E-3, §4) — brief archived
- [x] **Merge lane** (`replacement-merge-one-table`, archived `07274c1`): My Replacements is one table,
      one row per occurrence, `/my-replacements` canonical with two alias routes
- [x] **`ui-footer-global`** (`fea58ad`, archived)
- [x] **Notification clock** A + B1 (`67413e3`): `App\Support\LocalTime` is the one user-facing clock
- [x] **7 SDD lanes + 7 finished briefs archived** with evidence rows (`b57bf9d`)

---

## 7. Maintenance rules for this file

- **Coding only.** Report/survey/schedule → `docs/fyp-todo.md`. If a task is "write a chapter", it
  belongs there, not here.
- **Tick only what you verified** — a commit hash plus a gate (test count, PHPStan 0, a live screen).
- Never copy a lane's detail here; link the lane's `tasks.md` (or its parked prompt).
- One lane per line. When something ships, move its line to §6 with the receipt — do not leave it in
  the backlog "just in case".
- Keep it under ~10 new lines a week; cut 🟢 first.
