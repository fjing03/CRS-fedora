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
| Branch / remotes | `fedora-backend` → `origin` = `fjing03/CRS-fedora.git` (**27 commits ahead**, see Push state); `upstream` = `FjingXR/class-replacement-system` (**pull-only**) |
| Push policy | **NEVER `git push`** — the user pushes. One commanded exception, 2026-11-02; afterwards the rule resumed. |
| Push state | `origin/fedora-backend` sits at **`5a6e441`**; **27** commits are unpushed (2026-11-06, newest `bee6176`). Re-check: `git rev-list --count origin/fedora-backend..HEAD`. The user pushes. |
| AI context layout | Root **`AGENTS.md`** is a bootstrap pointer → this folder holds `AGENTS.md`, `CodingMAIN.md` (the spec), `my-coding-rules.md` (quick rules), `FR&NFR copy.md` (read-only without the user's approval), this file, and `history/`. |
| Servers | `:8000` = `CRS-fedora-backend` / **`class_replacement_fresh`** (dev). `:8001` = `../CRS-demo` / **`class_replacement_viva`** (viva twin, `APP_DEBUG=false`). `:8030` = disposable AI/E2E lane on `class_replacement_testing` only. |
| Database trap | `class_replacement_demo` is the **older, empty** twin (0 requests) — not the viva DB. |
| Demo data (2026-11-05) | `class_replacement_fresh`: **5 requests** — 24 rejected (session 40 W14), 25 cancelled (session 38), 26 **approved** (session 40 W14), 27 pending (session 38), 28 pending (session 97 W8) — **77** notifications (**3** hidden), **2** `class_exceptions` (session 24 W7 medical_leave, session 40 W14 official_event), 8 slot rows. Two test-residue cleanups the same day (the Test-2 cancellation, and request 29 + its 2 notices + 5 audit rows): both net-zero, both logged. |
| Test DB | `class_replacement_testing` — disposable; `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed` is fine **there only**. |
| Ops trap | `opcache.enable_cli=On` with `revalidate_freq=180` → a long-running `artisan serve` can serve a `.php` file's old bytecode for ~3 min. Use **`scripts/dev-serve.sh [port]`** (kills that port, clears views, starts with `revalidate_freq=0`). |
| Gates | `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` · full phpunit · `node --check` for JS. **Never** `composer run types:check` (crashes at 128M). |
| Suite | Last full PHPUnit run **344 tests / 1899 assertions** (2026-11-06, 410 s, after `bee6176` — the count returned to 344 because `venue-slot-validation` added two `minClassSlots` cases and deleted the same two with the plumbing). Browser suites are separate and NOT part of that number — quote them by name: `venue-book` 19, venue neighbours 61 passed / 2 skipped, `cancel-class` 12 passed / 4 skipped. |
| SDD lanes | **34** lane dirs under `.sdd/changes/` (**33** older finished ones + `venue-slot-validation`, `status: applied` 2026-11-06), **101** archived. Archive only on the user's word. |
| **Venue page slot rules** | Since 2026-11-06: hover asks ONE question (**one clear hour**, `MIN_BOOK_SLOTS = 2`, never derived from a class), and a class's duration is judged only AFTER it is picked (Arrange gate → `#slotWarningOverlay`, no navigation). Lane `venue-slot-validation`; commits `daf11c6` + `bee6176`. |
| **The merge lane** | `replacement-merge-one-table` — **APPLIED 2026-11-04**, archived `07274c1`: `e70c1ea` (payload/mapper), `6856577` (view), `ab65f9a` (routes + retired page), `d3f6f5b` + `c4d0ad3` (specs). The merged page is **My Replacements** at `/my-replacements`, with `/replacement-home-ui` and `/my-request-history-ui` as alias route entries. |
| **Cancel Class** | **FINAL since 2026-11-05** — undo gone from the UI, the JS and the server (`23c627c`); the inbox marks stale notices inactive at read time instead of offering dead clicks (`7b7f0d9`); four final-decision modals carry *"This action cannot be UNDONE."* (`12c644a`). |
| **E2E write-guard** | Live and fail-closed: `tests/helpers/receipt-target-guard.ts` + the three guarded specs (`b924552`, lane `receipt-target-guard` applied `7bbce9f`). Its one unverified receipt is `T3d` — the submission-conflict suite is not green; see §5. |
| Language | **B1-level English only** — no other language, ever. |
| Approval rule | Investigate first. **No code edit without the user's explicit per-task approval.** |

---

## 1. Latest sessions — 2026-11-03 → 2026-11-06

**2026-11-05 — the cancel-final chain, the E2E write-guard, and the toast order (8 unpushed commits):**
- **`7b7f0d9`** — the inbox stops offering dead clicks *and* Cancel Class becomes final (the user
  chose option B after a row-highlighting investigation). Two read-time rules in
  `NavNotifications`: a request-less `update` row whose `class_exceptions` row is gone →
  `inactive_reason = 'undone'`; an `approved`/`rejected` row older than the newest request for the
  same class+week → `'superseded'`. Both render disabled with their own tooltip. **Never a data
  edit, never a delete, never a re-link.**
  - Bug found while wiring it: **`Eloquent\Collection::merge()` re-keys and silently drops string
    keys** — the keyed exception map emptied and live cancellations were mislabelled "undone". Fixed
    with a plain array (`exceptionMap()`); two Feature tests now create their `class_exceptions` row
    the way `CancelClass` does in production.
  - Bug 2 found: fresh cancel notices carried **no jump target** — the target derivation only
    understood the legacy `/my-timetable-ui` link while new notices use `/my-replacements`.
- **`23c627c`** — the retired undo path deleted (`cancelClassUndo()`, `class_cancel_undone`, the
  `priorStatus` ledger field, `UNDO_TOAST_MS`, the `?restored=1` toast); `showUndoToast()` became
  `showCancelToast()`. The ledger `exceptionId` and the generic toast `undoCallback` API stay —
  other features use them.
- **`12c644a`** — one shared irreversible sentence, `partials.ui-irreversible-line`, on FOUR modals
  (Approve, Reject, Cancel Request, Cancel Class); the old red `.confirm-irreversible` CSS deleted.
- **`2156172`** — a refused cancellation explains itself again: `_refreshGate()` re-armed the button
  and rewrote `#cancelReasonHint` AFTER the refusal copy was written, erasing it. Gate first, message
  second, on a freshly queried node (the Livewire morf can detach it).
- **`ce31746`** — `tests/cancel-class.spec.ts` rewritten for the no-undo app (12 passed / 4 skipped):
  an `openFirstCancellable()` live-deck walker instead of hardcoded week/class, S6 rebuilt as a real
  two-tab race (a frozen clock lets `auto-logout.js` submit the logout form — measured), and a
  corrected receipt: a cancelled block does NOT disappear, it re-renders as `event-conflict` with a
  `(Cancelled)` marker.
- **`b924552` + `7bbce9f`** — the `receipt-target-guard` lane applied: the write-capable specs now
  REFUSE (exit 1 at collection) instead of soft-skipping when the blessed testing env is missing;
  one UI-regression confirm got an in-test throw (it writes a real request row).
- **`1156685`** — found by the user in the manual conflict walkthrough: the blocked arrival
  (`?blocked=request-pending`) showed a *cancellation* reminder and swallowed the entry-guard
  message. Now the blocked arrival speaks first (the note is deferred, not spent), and the reminder
  is **one-shot** (`markLoadToastShown()` writes `toastShown`), so a note the app can never clear
  cannot nag on every load.

**2026-11-05 (later) — after-submit highlight, the YOU relabel, and the archive pass (4 more commits):**
- **`a29f694`** — *My Replacements After Submit*: the submission success toast
  (`✓ Submitted — Pending Approval` + `View →`) and its 1.2 s pause are gone; the redirect
  carries `?new-req=<id>` and the new row pulses with the shared `bookable-pulse` (3 × 0.5 s).
  Server: `submitBlocks()` returns `request_id` (additive). Client: `jumpToNewRequest()` +
  a synchronous, self-stripping param. A hiding filter is cleared **for the visit only**
  (`saveFilters()` deliberately not called); "Exclude COMPLETED" stays ticked; nothing found =
  silent. Receipt: `tests/my-replacements-after-submit.spec.ts` (guarded) — 510 / 564 ms to
  navigate, zero toasts, one row flashed, saved filters intact.
- **`476406a` + `43bda16`** — the Event Hover Tooltip relabel: the own-class segment of
  `data-tip2` reads **`YOU`** instead of `ME` on the cohort and venue timetables (two different
  ownership mechanisms — name comparison vs the server's `mine` flag), and the two reference
  mock-ups under `ui-design-templates/` were brought along. Zero `ME` tooltip labels remain.
- **The archive pass**: seven finished lanes moved to `.sdd/archive/` (`git mv`, `status:
  archived`, `archived: 2026-11-05`). `.sdd/changes/` is down to **33** dirs, all older finished
  lanes; `.sdd/archive/` holds **101**.

**2026-11-04 — the merge lane: planned, then applied and archived (seven commits, unpushed at the
time of writing):**
- All four SDD batches passed review (**proposal 8 rounds**, **design 2**, **specs 2**,
  **tasks 5**), then PI applied them: `e70c1ea` (payload + vocabulary), `6856577` (the view),
  `ab65f9a` (URLs, routes, retirement), `d3f6f5b` + `c4d0ad3` (the reversed specs), `008e941`
  (changelogs + handover). Archived at `07274c1` — `.sdd/archive/replacement-merge-one-table/`
  (`sdd.yaml`: `archived`). Gates quoted: PHPUnit 336/336 (1849 assertions, baseline 325/1729),
  pint, PHPStan 0, `node --check`.
- The three residuals were closed: `3d61cef` + `e2c2d7a` (the `ui-regression` selectors and a
  real session for that file), the completed-status sibling fixture, and the user's approval of
  the invented tooltip sentence (`26a5fa6`).
  The log is `review-log.md`; the working list is `tasks.md` (five commits, 24 tasks, every
  receipt a number).
- The reviews earned their keep: they caught the replacement **time** being read from the first
  30-minute cell instead of the whole booking, a dash printed for cancelled/rejected requests that
  *did* have a slot, the no-request row (the majority) losing its badge and sentence, the page
  having **no name anywhere**, and a URL check whose only green was editing the frozen notification
  files.
- **A live bug was fixed early** (`9939d08`, user chose "now" over "with the merge"): the entry
  guard toast printed the page name twice ("… in My Request History. My Request History"). The body
  is now one short sentence with the state word in caps, and the merge's commit 3 (`ab65f9a`) then
  renamed the link to **My Replacements** → `/my-replacements` (`ui-common.js:4457`, `:4460`).
- Line references in `design.md` were **re-measured** (`335f454`) after another session moved the
  mapper's timezone handling into `App\Support\LocalTime`: everything from `effectiveStatus()`
  onward moved up **2** lines. `review-log.md` keeps its own numbers as the snapshot each round saw.
- Housekeeping the same day: the prompt folders settled (`21c258d` — toolkit / archive / open
  tasks), the ugly-toast screenshot gitignored (`1f92c1d`).

**Later on 2026-11-03 — the merged replacement page (docs only, nothing pushed):**
- The user finalised the **one-table** design: the page is **My Replacements** at the new
  canonical URL **`/my-replacements`** (one row per class occurrence, seven columns, status
  in the tab label and as a badge, no HISTORY block, icon-only actions, five cards). Both
  `/replacement-home-ui` and `/my-request-history-ui` stay as aliases. Full record: `.sdd/archive/replacement-merge-one-table/explore-brief.md`.
- **Static prototype** (discussion instrument, never staged, outside the repo):
  `../ui-prototype/replacement-merged-preview.html` — loads the live `theme.css` from
  `:8000`, so the dev server must be running to see real colours.
- **Proposal frozen after 4 review rounds** (`ccaa7ba`, log in
  `.sdd/archive/replacement-merge-one-table/review-log.md`): round 1 failed on the
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

### 2026-11-06 (venue slot validation + archive pass)

- **`c5dd0c4`** — archived the seven finished lanes (`mytimetable-jump-highlight`,
  `notif-click-behaviour`, `receipt-target-guard`, `replacement-home-status-badges`,
  `request-completed-status`, `notif-recipient-rulings`, `ui-footer-global`). `.sdd/changes/`
  40 → 33, archive 94 → 101.
- **`daf11c6`** — `venue-timetable` bookable cells: honest "no room" state, class-driven late
  cells, Earliest-bookable button + chip, the invented "2-hour gap" number deleted.
- **`bee6176`** (`venue-slot-validation`, SDD lane applied) — the user's follow-up report: with a
  14:00 class, a free 13:00 cell still printed "no classes can run here" *before* a class was
  picked, because the dropdown's default subject is a 2-hour lecture. The page now splits the two
  questions:
  **hover = one clear hour** (`MIN_BOOK_SLOTS = 2`, no class assumption; the `minClassSlots`
  plumbing is deleted), **after the class is picked = does THIS class fit?**
  (`confirmIntentSession` gate → `#slotWarningOverlay`, no navigation). The pop-up lists every
  session with `data-slots` / `data-fit` and pre-selects the first fitting row.
- **`913419e`** `test:` — `venue-cross-week-handoff` S1 pinned Tue 03 Nov 2026, which had turned
  read-only (`cell-too-soon`) as the clock passed it; the fixture now uses the last semester week
  (`pickSpanFreeCell`) with the same assertions.
- New standing rulings: **the hover floor is one hour and is never derived from a class**; **a
  duration verdict is only allowed after the class is picked**; **a page function used by an
  inline `onclick=` in a shared partial must be exported on `window`** (the page script runs
  inside a `DOMContentLoaded` closure).

## 2. The user's standing rulings (do not change without approval)

| # | Ruling |
|---|---|
| 1 | **Notifications events — ALL FIVE** (corrected 2026-11-04 to match R1–R4, `notif-recipient-rulings`): submit→PLs + one confirmation row for a **non-PL** proposer (a PL proposer gets only their queue row); approve→proposer (**one merged** `Replacement class updated` row, R3) + cohort students; reject→proposer; class-cancelled→**owner + cohort students only** — the department-PL fan-out was **REVERSED** by R4 (2026-11-03), so the old `notifyDeptPls` wording here is dead; pending reminder→PLs (24 h, deduped until read, re-armed daily) — **designed but DORMANT**: `notifications:remind-pending` is not scheduled (user decision 2026-11-04). |
| 2 | **Real-time = Livewire `wire:poll` (option A)** — NOT Reverb/websockets. Poll 10 s. |
| 3 | **Notification lifecycle**: rows never deleted; an inactive target renders the row **disabled** (no href) + tooltip `This notification is inactive because the request was cancelled.` |
| 4 | **Email/S**: SV approved **Gmail SMTP** ("CAN") for demo/testing with the user's personal Gmail; NFR 7.2 may be revised by the USER (their own doc edit). The SMTP flip = an ops step outside code lanes. |
| 5 | **mock-data.js the FILE STAYS** (retire consumers lane by lane; dormant rows = spec anchors). |
| 6 | **B005 no-Diploma REPEALED** (b005-diploma-allowed) — Diploma cohorts may use B005; AMIT2034 renders clean. |
| 7 | **B006 networking priority** still enforced; P→labs rule. |
| 8 | **Receipts strategy** = disposable test-DB suite + hand-run demo seed SQL (never agent-run booking flows on the demo DB). |
| 9 | **ONE feed on every page** (supersedes the category-aware caught-up hotfix — `notif-unified-feed` retired the per-page tabs 2026-10-29): all staff pages show the same recipient-wide feed, newest first, and the caught-up view is always the single line "You're all caught up". The pill/badge = the **actionable-unread** total (D5: `read_at` null AND not inactive), computed per render, never moved by the view filters. |
| 10 | **No bell on the replacement-arrangement page** = ACCEPTED BY DESIGN (BUG-04). |
| 11 | **`?week=` never moves the week selector** (ARRIVAL-GRID-NO-PIN) = WONTFIX (user decision). |
| 12 | **Demo story** (viva ~week of 7 Dec 2026, UNCONFIRMED, on this MSI machine): timetables + booking + cancel class + approve + bells + emails; auth = verbal only; students demo the "replacement class got updated". |
| 13 | **THE pageLivewire() LAW (the hotfix)**: page actions use `pageLivewire()`, NEVER `Livewire.first()` (the bell islands render first). |
| 14 | **A class that falls on a public holiday can NEVER be cancelled** (user-confirmed 2026-11-03 — the correct behaviour, not a gap). Rule **S4** rejects `day.holiday` in the shared guard `Cancellation.isCancellable()` (`public/js/ui-common.js` — grep the function name; the line numbers drift every lane), enforced at the button, the confirm path and the stale-button sweep. Browser proof: `tests/cancel-class.spec.ts` **S4** (the block's modal opens on the Deepavali in-lieu Monday but offers no cancel action). Do **not** "restore" the missing button; a holiday class is handled by arrangement, not cancellation. |
| 15 | **Cancel Class is FINAL** (user 2026-11-05: *"i give up all UNDONE function"*). No undo button, no undo callback, no server undo route — removing them was the fix, not a regression. Every final-decision confirm modal (Approve, Reject, Cancel Request, Cancel Class) shows *"This action cannot be UNDONE."* Reversible actions get nothing. |
| 16 | **Toast order**: a `?blocked=request-pending\|request-approved` arrival shows the ENTRY-GUARD message first, and any pending cancellation reminder is deferred (neither shown nor spent) to the next page load. The cancellation reminder is **one-shot** — one reminder per cancellation, never one per load. |
| 17 | **Stale notifications are marked inactive AT READ TIME**, never edited: `undone` (a request-less cancel notice whose `class_exceptions` row is gone) and `superseded` (a decision notice older than the newest request for the same class+week). Badge = D5 (actionable unread), so an inactive row leaves the badge but stays on the list. |
| 18 | **A slot is judged by the class the user picks, not by a guess** (user brief `Venue Timetable Slot Validation.md`, 2026-11-06). On `/venue-timetable-ui` the hover answers ONE question — *is there at least one clear hour?* (`MIN_BOOK_SLOTS = 2`; `cell-no-room` = "Not Enough Slot" when the next class starts inside that hour, `cell-no-fit` = past day end). It is **never** derived from the arrange pool (the old `minClassSlots` plumbing is deleted). The real duration is checked only AFTER a session is picked, in `confirmIntentSession()` → `#slotWarningOverlay` (span or day-end copy), which never navigates. The pop-up lists every session with `data-slots` / `data-fit` and pre-selects the first fitting row; no verdict is shown before the pick. |

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
- **The write-guard is live (2026-11-05).** Every writing spec is fail-closed through
  `tests/helpers/receipt-target-guard.ts`: `abortUnlessTestingTarget(spec, { requireAllowSql })` as
  the first statement after the imports (aborts at COLLECTION — no login, no SQL), or
  `throwUnlessTestingTarget(name)` on the first line of one dangerous test. Exact strings only:
  `CRS_MODE=testing` + `CRS_BASE=http://127.0.0.1:8030` (+ `CRS_ALLOW_SQL=1` for SQL fixtures).
  There are no defaults and no soft skips any more — a bare run is a loud exit-1 refusal that
  prints the blessed command. Blessed form:
  `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test <spec>`,
  after `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`.
  Never loosen the guard to make a run pass; re-seed between write-receipt runs.
- **Inline `onclick=` + a page script inside a `DOMContentLoaded` closure** = the markup cannot see
  the function unless it is exported (`window.closeSlotWarning = closeSlotWarning;`). The symptom is
  a modal that opens and then throws *"… is not defined"* when you close it — that is what the venue
  slot warning did for one round (2026-11-06). Check the `window.` export block at the end of the
  page's script before blaming the markup.
- **Browser fixtures rot with the clock.** `venue-cross-week-handoff` S1 pinned *Tue 03 Nov 2026*;
  once that date passed, the whole week rendered read-only (`cell-too-soon`) and the spec could not
  even click. Date-independent fixtures pick the LAST semester week instead (`913419e`). When a
  spec fails on a click that used to work, check the calendar before checking the code.
- **Fortify's login limiter is 5/min.** Multi-context specs plus a few manual logins in the same
  minute produce a login that "does nothing" (no redirect). Wait a minute before concluding a spec
  is broken.

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
| Reusable prompt **templates** (paste-ready; no project state) | `prompts/general&reusable prompt references/` — `PI-PROMPT-TEMPLATE.md`, the two propose templates, the UI-page spec, the context-transfer prompt |
| Demo-DB writes, restores, snapshots | `docs/demo-db-data-changes.md`, `backups/README.md` |
| Viva runbook, queue/mail, known pending bugs | `docs/viva-demo-runbook.md`, `docs/queue-and-emails.md`, `docs/replacement-arrangement-pending-bugs.md` |
| **The lanes just applied** | `venue-slot-validation` — `.sdd/changes/venue-slot-validation/` (status `applied` 2026-11-06; the venue hover/gate split, receipt in `review-log.md`). `receipt-target-guard` — same folder (status `applied`; one leg open: `T3d`). Merge lane: `.sdd/archive/replacement-merge-one-table/` (archived `07274c1`). |
| **The venue-page slot rules** | `resources/views/livewire/venue-timetable.blade.php` (`MIN_BOOK_SLOTS`, `spanBlocked()`, `nextEventStartAfter()`, `refreshSessions()`, `confirmIntentSession()`, `showSlotWarning()`), `resources/views/partials/ui-intent-session-modal.blade.php` (`#slotWarningOverlay`), `app/Livewire/VenueTimetable.php` (`bookableCandidates()`, `intentSessionOptions()`) |
| Cancel Class + the inbox rules | `app/Livewire/NavNotifications.php` (`exceptionMap()`, the two read-time classifiers), `resources/views/partials/notif-row.blade.php` (`$rowTip`), `public/js/ui-common.js` (`showCancelToast`, `needsLoadToast`, `markLoadToastShown`, `CancelClassModal`) |
| Lanes already landed | `prompts/pi-replacement-home-status-badges.md`, `prompts/pi-request-completed-status.md` |
| The approved picture of the merged page | `../ui-prototype/replacement-merged-preview.html` (outside the repo, never staged) |
| Superseded session diaries | `history/2026-10-31-to-2026-11-03-addenda.md` |

---

## 5. Open tasks (state at 2026-11-06)

1. **[USER] Push** — 27 commits sit on `fedora-backend` past `5a6e441` (`bee6176` newest, 2026-11-06).
   Nothing is pushed by an agent, ever.
2. ~~**[USER, waiting on the archive word]** Seven finished lanes~~ — **ARCHIVED 2026-11-05**:
   `mytimetable-jump-highlight`, `notif-click-behaviour`, `receipt-target-guard`,
   `replacement-home-status-badges`, `request-completed-status`, `notif-recipient-rulings`,
   `ui-footer-global` now live in `.sdd/archive/` with `status: archived` + `archived: 2026-11-05`.
   **Still open:** whether `.sdd/archive/README.md` should exist at all, the ~33 older finished
   lane dirs left in `.sdd/changes/`, and `venue-slot-validation` itself (applied 2026-11-06,
   archive on the user's word).
3. ~~**[AGENT, ready on the word]** `prompts/request-approval-ui CONSISTENCY.md` **part 2**~~
   **SHIPPED 2026-11-04** (1f, 3B, 3C, 3A, 3D, 1h, 3E-3, §4 — `page-changelogs/request-approval-changelog.md`
   + the `ui(request-approval)` commits through `fc217a5`). The brief is archived as of 2026-11-06
   (`prompts/completed prompt (Archieve)/`). Nothing left here.
4. **[AGENT]** `receipt-target-guard` `T3d` — `tests/submission-conflict-refresh.spec.ts` is **not
   green** and never has been recorded green: it fails inside its own staged-arrival flow (a login
   redirect timeout, then `#weekSelector` absent on the landed page). Decide: diagnose it timeboxed,
   or quarantine it as `test.fixme()` with the reason recorded. The guard itself is unaffected
   (it refuses at collection, independently).
5. **[AGENT, queued]** The "Just cancelled" chip TTL prompt had no committed file last checked —
   re-create it if the user still wants the 30-minute display-only TTL.
6. **[USER]** Confirm the viva date (anchored: week of 7 Dec 2026) and decide the viva demo data —
   both DBs are thin; a populated story needs user-gated SQL, snapshot-first, logged.
7. **[USER/OPS]** Purge the viva repo's 27 stale jobs before any worker run.
8. **[USER]** BUG-01/02/03/05/06 verdicts (`docs/replacement-arrangement-pending-bugs.md`).
9. **[AGENT, demo week]** Chapter 5 browser-evidence run and the report; NFR benchmarks; the UAT
   script.
10. **[LOW]** `?week=` deep links still land on the remembered week (accepted); three reference
    templates under `resources/views/ui-design-templates/` still carry `Exclude Completed` and a
    `ClassCancellation.undo(entry)` call — they are design references, not loaded pages.
