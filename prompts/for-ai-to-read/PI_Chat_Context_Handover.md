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
| **Same day, later — the prototypes move INTO the repo (`ui-prototype/`)** | They had always lived at `../ui-prototype/`, one level above the repo, so nothing in git could cite them, diff them or review them. Moved to `ui-prototype/` at the repo root (5.8 MB on disk incl. the reference screenshots; **the 44 PNG/PDFs are gitignored** in
`ui-prototype/.gitignore`, so git carries the 15 HTML/MD files and nothing binary). Their two asset families were rewritten: the hand-built previews now load `../public/css/theme.css` (they had `../CRS-fedora-backend/public/…`, which no longer resolves), and the three **saved live pages** keep `<base href="http://127.0.0.1:8000/">` so they still need the dev server for CSS/JS — that is why they look unstyled on a bare static server. Every markdown reference in the repo (`../ui-prototype/…`, 92 of them across 39 files, in lanes, prompts, briefs and the to-do list) was rewritten to repo-root-relative `ui-prototype/…`; the archived lanes were normalised to the same repo-root form (a first pass had turned their
`../../ui-prototype/…` into a path that pointed at `.sdd/` — caught before the commit). **The new convention (user's idea, accepted):** the prototypes are the picture and they carry the design intent in **code comments** (why a tier is 11px, why a badge is grey, which rule a class obeys); a lane's papers point at the prototype and quote those comments instead of restating them, and only a *decision* change still needs the paper edited. Recorded in `prompts/for-ai-to-read/AGENTS.md` under the read-first list. Verified after the move on a static server rooted at the repo: five pages 200, both the timetable previews styled by the in-repo `theme.css`, `my-replacements.html` still 9 rows and 0 console errors. |
| **Latest (2026-11-15) — the student timetable gets its prototype, and the class card settles into three tiers** | Lane 8 (`my-timetable-responsive-prompt`) was still at proposal stage, so this was **prototypes only** — no blade, no `theme.css`, no test run. New file `ui-prototype/student-my-timetable-mobile-preview.html` (1,142 lines): phase B's visual reference, built by **copying the staff preview** and applying exactly the four student deltas of proposal §3b — read-only (footer is a neutral `Close`, no Arrange / Cancel / `View Full Request` / `Requested At` / `Requested By`), cohort chip + two-item nav, the class's own lecturer in the block tip, and card 2 reading `Class Hours`. Fixtures are quoted from `docs/demo-logins.md` (DFT2 (S1) G1 · `24DFT0001`), with Monday 9 Nov on the Deepavali holiday. On the user's word the **staff** preview changed too, and the brief + lane were brought into line: (1) the card has **three tiers** — primary = time range + venue code (both 14px/700 phone, 11px/700 tablet), secondary = course code + type (12px/500, `--color-on-surface-variant`), tertiary = one **right-aligned** note line (11px/500, grey) carrying `(Pending since …)` / `(Replaced for …)` / `✕ Cancelled` / `⚠ Conflict`; below 1280px the Cancelled/Conflict word left the line under the time for that note slot (the desktop grid block keeps its shipped order), keeping its status colour and dropping to note size; (2) **the subject name left the card below 1280px** — it stays in the tip, the sheet subtitle and the `aria-label`, because the name was only ever rendered below 1280 anyway; (3) **both previews' class pop-up now matches the decided shape** — short tabs `Class Info · Schedule · Original` (section captions keep the long names). Later that day the user corrected the pop-up: **`Close` is never the filled primary** (reversing the 14 Nov "fill when alone" rule — the student page was always that case), a cancelled class's Status row reads **`Class Cancelled`**, and it finally has a skin, because `theme.css` never defined `.badge-cancelled` and the row printed a transparent pill — grey (`.badge-reason`'s pair), not red, on the user's second ruling that red is Public Holiday's. Phone card measures **68px** without a note, **94px** with one; narrow tablet blocks (two lanes side by side) let the venue fall to the second line on the right rail. Checked at 320 / 402 / 768 / 1024 / 1280 / 1440 in both themes, zero page errors, no sideways scroll. The **live** student page was deliberately not opened for a side-by-side: a login writes a `sessions` row and `SESSION_DRIVER=database`, so it needs the user's word. Records updated: brief §5/§8/§15, proposal §3.2/§3.3/§3b/§9d/§10, `sdd.yaml`, `review-log.md`, to-do task 8 (+ notes on tasks 10/11), and the two PI prompts. **One more yes the same day:** asked whether the three replacement pop-ups should follow "Close is never the filled primary" too, the user said yes — so the rule is now system-wide. Applied to the prototypes (`student-class-replacements.html` had it hard-coded, `my-replacements.html` toggled it, `request-approval.html` already complied); **owed in two blades** (`replacement-history.blade.php`, `replacement-home.blade.php`) and recorded as decision 10 of the `details-modal-consistency` prompt. |
| **Latest (2026-11-14, night) — `filters-button`: proposal frozen, and the user's answers grew the job** | Task 7's lane (`.sdd/changes/filters-button/`, **proposal frozen after 4 review rounds**, nothing designed or applied). One labelled **Filters** button on the three list pages — My Replacements, Request Approval, Class Replacements — with every filter behind it (popover above 768, bottom sheet below), chips + row count left on the page. Explore measured the case honestly: the bar + chips eat **up to half** the phone screen on the two staff pages (265px bar + 120px chips, ending 519px down) and only a quarter on the student page. **Four user answers shaped it:** (1) the student page gets **no Reset** (its panel head is title + close); (1b) the count pill **follows the prototype** — the old rule hiding `#resultCount` below 1500px is retired, duplicate with the pagination bar accepted; (2) the week "all" option reads **`All Weeks`**, deliberately reversing the 2026-11-10 SLOT option label; (3) the **second week dropdown is IN on all three pages** (Requested on My Replacements and the student page, Original on Request Approval) — so this is no longer layout-only: each page gains one real filter (predicate, chip, reset, remembered state), with the requested week **derived from `replacementDate`** (no backend change). Three test files pin the strings it changes on purpose and are amended in the same commit: `status-tooltips-filter-chips.spec.ts` (`:380` week text, `:416` chips label, `:171/:206/:422` chip text), `week-filter-preserved.spec.ts:141`, and the **whole** `StudentReplacementHistoryParityTest.php` (`:143/:144/:146/:148` + docblock — it pins the student page as single-week). Next batch: `design.md`, then specs, then tasks; apply needs its own word. |
| **Latest (2026-11-14, night) — the cards move behind one Summary button on a phone (lane archived)** | The `summary-cards-button` lane ran the whole ladder — explore → propose → design → specs → tasks → apply → verify → **archive** — and shipped as **`0514aaf`** plus **`49c7290`**; nothing pushed. At ≤768px the seven card pages' `#summarySection` is **moved** (never copied) into one shared bottom sheet emitted by `partials/ui-summary-bar`, opened by a bare chart icon beside the help "?"; ≥769px is untouched. Mechanism worth remembering: the sheet is an **overlay div** (z 1200), not a `<dialog>` — `showModal()` uses the top layer and would have made the existing ⓘ dialog unopenable above it (measured with `elementFromPoint`) — so the ⓘ is lifted with one scoped rule (1400); one **Escape closes one layer** (capture-phase listener that no-ops while the ⓘ is up, with the Tab trap suspended); the sheet has its **own** scroll-lock class because `body.summary-locked` is shared and unrefcounted; the button hides itself when the page hides the cards at runtime (a `MutationObserver`, never `offsetParent` — null for a section inside the closed sheet); print un-relocates on `beforeprint` and restores on `afterprint`. Explore also corrected the brief: the cards never filled the first screen — they sit 970–3871px down — and found the **plain card variant** (`student-class-replacements`) was the odd one out (three 114px columns at 390, no ⓘ, hover-only backs, a "Hover a card" hint on touch, behind a phone CSS rule that never applied because a later tablet rule overrode it); fixed inside the sheet only (2 columns / 148px, 1 / 132px below 390, tap-to-flip, touch wording) — and the page was then **reported and converted to the house flip cards** the same night (`7f95048`: three bullets + ⓘ, superseding that lane's R5). Evidence: new spec **13/13**, `venue-timetable` TC36/37/**58** 6/6, `cancelled-slot-reuse` 15/15, zero page errors on all seven pages in both themes; four other failures are pre-existing and attributed in the archived `sdd.yaml`. Committed **hunk-wise** — the lesson (diff the index against the worktree afterwards) is in `my-coding-rules.md` §5. |
| **Latest (2026-11-14, evening) — arrangement sheet folds, summary stays open, slot rows stack** | Three user-approved pieces on `replacement-arrangement`, all committed locally, nothing pushed. **`bd4a8e8`** — the phone "Choose venue" sheet's grey captions became folding bars (`<button aria-expanded aria-controls>`, sticky, chevron rotates, first block open, a search opens every matching block, folding resets on reopen/keystroke); that commit also had to carry the **2026-11-12 searchable sheet** it lives inside (`renderArrangementVenuePicker` existed in no commit at all), its dialog markup in `ui-arrangement-mobile`, its spec `tests/venue-dropdown-responsive.spec.ts` (was untracked) and the arrangement changelog block — user instruction ("yes bring in"), recorded in the lane. **`c2fe630`** — the **Selection Summary can no longer fold** (button + listener + the unreachable `.sel-summary.is-collapsed` rule deleted; Conflict Schedule and Class Details keep their toggles) and each **slot row is four labelled glyph rows** (type+week+date, time with the `~` joiner, venue, `DFT2 + DSF2 (2 cohorts)`); the cohort bracket counts the **cohorts underneath**, not the distinct codes (user answer A). The four glyphs were hoisted out of `updateSelectionSummary()` into one `RA_ICONS` constant. Verified 7/7 on `venue-dropdown-responsive.spec.ts` (four times) and `selection-summary-collapse.spec.ts` (rewritten to pin the fold's ABSENCE) passing; `rearrangement-toolbar-tooltips.spec.ts`'s `@ B` assertion became the venue row, and its other two failures are **data-driven**: `?session=67` redirects to `/my-replacements?blocked=request-pending`. The summary/slot-rows work used the **small-change exemption** (user: "SKIP & make the two changes now") — its lane `.sdd/changes/arrangement-summary-and-slot-rows/` is kept as the record, `status: superseded-by-exemption`. Receipts `/tmp/arr-venue-collapse/`, `/tmp/mt-resp/`. |
| **Today's UI batch — pop-ups, evening slots, tooltip (2026-11-14, latest)** | Six user-approved UI lanes applied and committed locally, none pushed, none archived. **`modal-responsive-width` `f15ef96`** — 680 is now a **ceiling**: one shared `theme.css` rule, `width: fit-content` + 420 floor above 600px, full-width sheet at ≤600px, variant ceilings (details 680 / guide 520 / summary 420 / Cancel 560, 588 at ≥1025), 48×48 close, stretched phone footer, three pinned regions; the four page-local 680 pins were deleted because on ID specificity they beat the shared rule. **`modal-facts-fit-content` `308034d`** — `.modal .detail-facts { width: 0; min-width: 100% }`, the grey strip no longer decides the pop-up's width (626 → 513 at the same height); Confirm Cancellation 635 → 680. **`1756745`** — "Slot Too Short" (`#slotWarningOverlay`) raised 560 → 680. **`venue-evening-tail` `6b861f2` + `e2aefa3`** — the venue grid paints and counts its evening half-hours instead of painting them red; the tail exists only for days whose last served row is in the final hour, `Not Enough Slot` is green, the red `Too Late to Book` arm is retired; and the evening became **really bookable** — 3,864 `available` rows added to the DEMO DB (see the demo-data row) plus `TimeSlotsSeeder` loop `< 19`. **`modal-dropdown-fixes` `85ce7a3` + `6f197de`** — the session picker floats outside the pop-up (`:has(.session-dd.open) overflow: visible`, opens upward inside the phone sheet) and both pop-up arrows share the sanctioned chevron at a 12px inset. **`tooltip-viewport-clamp` `65f1dc1`** — the Arrange tooltip stays inside the window (340px ceiling, 8px clamp on every edge, flips above the cell, re-places on `resize`) and every open path (click + the `B` key) is placed the same way. Measured read-only in a real browser at 1440 / 1024 / 768 / 390 / 360 plus specific 600 / 601 / 700 widths. `ModalConsistencyTest` **16/16**; new `tests/modal-responsive-width.spec.ts` **9/9**; new `U2a-T18` in `venue-book.spec.ts` **written but not run** (the `:8030` lane could not authenticate at the time — it can again since the evening restore; the spec still needs its one run). Backend suite **not** re-run (the separate test cluster is stopped and `phpunit.xml` targets the protected manual DB). `T3b` of `modal-responsive-width` stays **deferred** (the arrangement blade carries another session's work). Receipts `/tmp/crs-modal-measure/`, `/tmp/crs-evening/`; lanes under `.sdd/changes/`; changelogs `modal-responsive`, `venue-timetable-ui`. |
| **Approved-replacement cancellation fix (2026-11-14)** | User said "fix and test this flow": an opened Approved replacement was cancelled by its recurring source identity, so the server dropped it as already-cancelled (request 19 stayed Approved) while the modal still counted ticks. Now cancelled by exact request ID + shown landing week/day, keeping source session/week for history and re-arranging; single and mixed decisions share the batch/lock/strict full-span release, all-or-refuse, and a later failure rolls everything back. Success list/count use saved receipts, no recurring ledger, Arrange Now uses source week, dismissal refreshes. **226/226 backend, 2,820 assertions**; **26/26** new file (10-pass/15-fail/1-error saved-original baseline); **32/32** read-only browser; lint/PHPStan1G/views/syntax/whitespace PASS; independent review PASS. Isolated forced target `55439`/`/tmp/crs-approved-suppression/pgdata`, wrong-target refusal exit2, cluster stopped after. Manual request/exception/junction/slot hashes and all audit rows unchanged; notification content unchanged except one `read_at`/`updated_at` from a `notifications/read` request at 14:38:39 (left alone, no actor inferred). **Request 19 is still Approved — retry the fixed flow, no automatic repair.** Committed **b480a95** (with its context sync; 13 ahead now), not pushed; scoped Verify PASS, not archived. Receipts `/tmp/crs-approved-cancel/`, `.sdd/changes/approved-replacement-cancellation/`. |
| **Latest own-approved suppression / protected manual DB (2026-11-14)** | User-approved refinement complete and committed locally: a cancelled original disappears ONLY for its own Approved request, not pending/rejected/withdrawn/auto-rejected/absent or another source. All four timetable payloads consistent; history retained. **122/122 backend, 2,659 assertions** on a forced/verified temporary PostgreSQL instance; **18/18** read-only browser cases + **3/3** auth. Actual approved request19/source2673/week10 checked on all four real pages, desktop/phone, both themes; source absent, true 13:00–14:00 landing intact. Manual business records identical. Revised SDD chain and independent scoped review PASS. Temporary cluster **stopped**; **:8030 remains running with the user's manual data: NEVER reset/reseed/RefreshDatabase there**. Writing lifecycle collected only. Committed **46fd6f7**; **7 ahead, not pushed**; no archive/demo cleanup; whole-feature Verify open. Receipts `/tmp/crs-approved-suppression/`. |
| **Latest half-hour overlap fix / MANUAL DB GUARD (2026-11-14)** | User approved fix & test for staff 5770 pending 13:00–14:00 versus cancelled 13:30–14:30. Shared planner corrected; **18/18** read-only planner/layout browser cases, including 1,764 pair combinations in both orders; separate **3/3** auth setups, lint/PHPStan/JS syntax and independent scoped review PASS. Actual request **19** was pending then (**approved since**), source **2673**, week **10**, checked on desktop/phone, both themes; original request **18** was cancelled by the user. **Testing :8030 remains running and class_replacement_testing contains the user's manual work: NEVER RefreshDatabase/reset/reseed without permission.** Before/after manual data identical. Committed locally as **23f62fb** (superseding interim 5a46b2e) with approval (**12 owned paths/hunks**; other-session JS/changelog hunks stayed unstaged); 6 ahead at that point, no push. Other-session diffs preserved; whole-feature Verify remains separate. `/tmp/crs-half-hour-overlap/`; cancelled-slot-reuse apply/review logs. |
| **Small test repair (2026-11-14)** | User approved a 60-minute test-only repair. All four explicitly approved method fixes complete across three test files and committed with approval as **0e6bc1a — test: align replacement history assertions and isolate fixture dates**. FINAL full backend **721/721 PASS, 6,090 assertions**; three affected files **31/31, 308 assertions**; lint/PHPStan and independent reviews PASS. No app changes or weaker Sunday rules. Committed **0e6bc1a**; current HEAD **46fd6f7**, **7 ahead**, not pushed. Separate cancelled-slot-reuse final acceptance Verify remains parked. `.sdd/changes/student-history-test-contract/verify-report.md`; `/tmp/crs-history-test-fix/`. The testing DB was later rebuilt with a guarded plain migrate/seed and now holds the user's protected manual data (2026-11-14). |
| **Summary card backs + structured explanation (2026-11-14)** | `summary-card-detail-copy` applied. The flip-card backs now read properly: block vertically centred (was 7px above / 48px below), `line-height: 1.45`, 4px bullet gap, and the 44px `.summary-info` moved **top-right** so it stops eating the text; every line reserves `38px` (39px re-wraps a bullet, 40px worse). Below **390px** the flip summary bar drops to **one column** — the rule is required, not cosmetic, and its position in `theme.css` is load-bearing: it and the 768px 2-column rule are both specificity 0,2,0. The explanation popup is no longer one paragraph: optional **formula strip** → `term<br><span>meaning</span>` list → optional footnote, rendered from the optional `$card['detail']` (`formula`/`items`/`foot`) in `partials/ui-summary-bar.blade.php` into `.summary-explanation-source`, so **no JS change** was needed (`SummaryCards.open()` already prints that node's `innerHTML`). `.explain-*` rules are deliberately **unscoped** — `#summaryExplainModal` is a sibling of `.summary-section`, so scoping them would style nothing while still passing a content check. Chips carry **no live numbers** (all front values are runtime); Total Classes deliberately has no `= total` chip. Four bullets shortened, full wording kept in the popup. Verified: **48 page+width combos** (320→1360px) max **3 lines**, 0 spill, centring diff 0px, 1 column at 320/360/375 → 2 at 390; all **35** modal checks pass (title matches card, ≥2 items, 13 with formula, dark theme included), 0 page errors; `ThemeCssIntegrityTest` 3/3. No value/calc/filter/`valueId`/colour change. **Uncommitted, not pushed.** Lane `.sdd/changes/summary-card-detail-copy/`. |
| **Other sessions in flight (2026-11-14)** | A concurrent session owns the summary-card flip cards, icon-only guide and tinted week select: its CSS is committed (`0851531`) but the `SummaryCards` block in `ui-common.js`, the guide partials (`ui-guide-icon`, `ui-guide-modal`), the summary/header/week-nav partials, the timetable Blade edits and their specs are still uncommitted. It archived the two top-zone lanes (committed as `a1e6470`) and moved `my-timetable-ui-cleanup` + `guide-toggle-icon-only` into `.sdd/archive/` **on disk only**, along with the untracked `summary-card-detail-copy`, `timetable-print-v2` and parked `bulk-cancel-class` lane folders. Do not stage, reformat or revert those hunks; `0851531`, `7d446d9`, `8c4cdab` and `a1e6470` are its commits. |
| **Current Apply checkpoint (2026-11-14)** | `cancelled-slot-reuse`: the original 38-unit delivery remains **27/38 checked** historically; the revision-2 refinement **R201–R205 is complete** (own-approved suppression). Part A/B and the refinement are committed. Five older browser regressions cleared (**24/24**), room-holder browser checks **8/8**, lifecycle/layout/planner **26/26**, integrated seven-spec **52/52**; the latest refinement run is **122/122 backend / 2,659 assertions** plus **21/21 read-only browser (18 +3 setups)**. The former full-suite history error is fixed: **721/721, 6,090 assertions**. **13 ahead / not pushed at the 2026-11-14 measurement** (own cancellation fix + context sync, with other-session CSS, SDD-plan and archive commits interleaved). A concurrent session keeps committing, so re-check with `git rev-list --count origin/fedora-backend..HEAD` instead of trusting the number. No push/demo-cleanup authority; mixed other-session hunks remain unstaged. Whole-feature Verify remains open. Receipts: `.sdd/changes/cancelled-slot-reuse/apply-log.md`, `/tmp/crs-reconcile-b/`, `/tmp/crs-history-test-fix/`, `/tmp/crs-approved-suppression/`. |
| Branch / remotes | `fedora-backend` → `origin` = `fjing03/CRS-fedora.git` (see Push state); `upstream` = `FjingXR/class-replacement-system` (**pull-only**) |
| Push policy | **NEVER `git push`** — the user pushes. Two commanded exceptions: 2026-11-02, and **2026-11-10 evening** ("then git push origin fedora-backend"); each time the rule resumed afterwards. |
| Push state | **PUSHED 2026-11-14 (night): `origin/fedora-backend` moved from `a6e6a1f` to the commit that carries this line** — 45 commits of queued work plus tonight's sync, in one push, on the user's explicit word. The rule is unchanged: agents never push unless told. Re-check with `git rev-list --count origin/fedora-backend..HEAD` before quoting any "ahead" number — it now reads 0 until the next session commits. |
| AI context layout | Root **`AGENTS.md`** is a bootstrap pointer → this folder holds `AGENTS.md`, `CodingMAIN.md` (the spec), `my-coding-rules.md` (quick rules), `FR&NFR copy.md` (read-only without the user's approval), this file, and `history/`. **`my to-do list.md`** is the user's own MAIN task list (**untracked — never stage it**) — read it, mark a task Done only after verifying it, never rewrite / re-word / delete a task (ask first). |
| Servers | `:8000` = `CRS-fedora-backend` / **`class_replacement_fresh`** (dev). `:8001` = `../CRS-demo` / **`class_replacement_viva`** (viva twin, `APP_DEBUG=false`). **2026-11-14: `:8030` / `class_replacement_testing` holds protected user manual work, NOT disposable. No reset/reseed/RefreshDatabase or writing fixture there without permission.** **2026-11-14 (evening, resolved):** that database had been **emptied** (every table 0 rows, all 33 migrations in one batch) — the signature of a backend test run, because `phpunit.xml` targets it on `127.0.0.1:5432` (its `pg_stat_database` shows ~120,000,000 tuple inserts over its life vs 57,347 on `fresh`, so it has always been the PHPUnit target). On the user's approval it was **restored** with `pg_restore --clean --if-exists --single-transaction` from `/tmp/crs-evening/manual-before.dump` (their own 18:20 snapshot), the evening slot rows were re-added, and staff **5425 / 5770 / 4363** log in again on `:8030`. `class_replacement_fresh` was proven untouched (its nine counts identical before/after; different database OID). Full receipt: `docs/demo-db-data-changes.md`, 2026-11-14. **⚠️ The trap remains: any backend test run will empty that database again while `phpunit.xml` names it** — changing that one line to a separate test database is the durable fix and needs the user's word. |
| Database trap | `class_replacement_demo` is the **older, empty** twin (0 requests) — not the viva DB. |
| Demo data (**2026-11-12 22:15, measured; extended 2026-11-14**) | `class_replacement_fresh`: **42 modules / 101 sessions / 22 requests / 248 notifications (6 hidden) / 169 users / 155 students**. `class_replacement_fresh.time_slots` went **38,640 → 42,504** on 2026-11-14 (two scoped `INSERT…SELECT`s copying the 17:30 rows into 18:00–18:30 and 18:30–19:00 as `available`; snapshot `/tmp/crs-evening/manual-before.dump`, rehearsal rolled back first, row-count receipt in `docs/demo-db-data-changes.md`; no other table touched, 0 held/occupied new rows) so the venue page's evening half-hours are genuinely bookable. Requests by status: **3 approved** · **3 pending** (**35, 39, 46**) · **6 rejected** · **10 cancelled**. Notifications by view: student 178 · PL 48 · lecturer 22. Notifications by type: submitted 44 · approved 44 · rejected 6 · update 154. The counts DRIFT because the user decides/cancels requests and adds demo data by hand between sessions — always re-measure, never quote this row. `class_replacement_demo` stays the pristine deck: **0 requests / 0 notifications**. Schema: `replacement_requests.replacement_span` is on both (additive, 2026-11-07). Receipts: `docs/demo-db-data-changes.md`. Logins for every demo account: **`docs/demo-logins.md`**. |
| Test DB | **2026-11-14: the normal `class_replacement_testing` is protected manual data. Do NOT reset/reseed/RefreshDatabase without permission.** Backend refresh tests used a separate verified PostgreSQL instance with the same literal DB name, socket `/tmp/crs-approved-suppression/socket`, port55439; it is now stopped. Preserve :8030 and verify full connection identity, not just database name. |
| Ops trap | `opcache.enable_cli=On` with `revalidate_freq=180` → a long-running `artisan serve` can serve a `.php` file's old bytecode for ~3 min. Use **`scripts/dev-serve.sh [port]`** (kills that port, clears views, starts with `revalidate_freq=0`). |
| Gates | `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` · full phpunit · `node --check` for JS. **Never** `composer run types:check` (crashes at 128M). |
| Suite | **2026-11-14 evening:** the backend suite was **not** re-run for today's UI batch — `phpunit.xml` points at `class_replacement_testing` (protected manual data, and the separate `55439` test cluster is **stopped**), so PHPUnit must not be run until a test target is agreed. The batch's own receipts are static + browser only: `ModalConsistencyTest` **16/16**, `tests/modal-responsive-width.spec.ts` **9/9** (both run earlier today, while the `:8030` lane could still log in — it no longer can), and read-only browser measurements at the widths listed in the today's-batch row; `U2a-T18` is written but **still not executed** (the lane can log in again since the evening restore — one run is outstanding). The last recorded full backend run is **760 tests / 759 passed** (2026-11-14, one order-dependent `CancelClassBatchTest` `time_slots_no_double_book_idx` error that passes when run alone), before that **721/721 PASS, 6,090 assertions** (`/tmp/crs-history-test-fix/full-suite-r2.txt`); the former stale `ReplacementHistoriesTest` error was repaired by the test-only commit `0e6bc1a`. Re-run before quoting. Browser suites are separate and NOT part of that number — quote them by name: `arrangement-mobile` **51 passed / 3 skipped** at 390px (2026-11-12, after Round 2), `top-zone-round2` **13/13** (2026-11-12), `session-arrival-pin-guard` 3/3, `arrangement-conflict-autoselect` 10, `venue-book` 19 (18 green + the known `U2a-T3` hover flake), `arrangement-slot-ownership` 7 (run it ALONE), `status-tooltips-filter-chips` 11, `replacement-home-working-days` 8, `replacement-home-status-badges` 13, `notifications-island` 10, `notif-row-inactive-hide` 5, `week-filter-preserved` 3, `cancel-class` 16 passed / 4 skipped. The 2026-11-14 own-approved check added **18 read-only planner/layout cases +3 setups**; its writing lifecycle spec was collected, not executed on manual data. |
| SDD lanes | **`.sdd/changes/` holds 18 folders** (2026-11-14 night), 17 after the card lane left: `approved-replacement-cancellation` (applied, scoped Verify PASS, not archived), `cancelled-slot-reuse` (revision-2 refinement complete; whole-feature Verify open), `student-history-test-contract` (verified, archive-ready), `cancel-whole-day`, `summary-card-detail-copy`, `student-modal-staff-parity` (applied/in flight, verify-or-archive on the user's word), `arrangement-venue-picker-collapse` (ready-to-apply, no code; its B02/B03 gate is now cleared, its line references were re-synced 2026-11-14), `timetable-print-v2` (frozen plan, no code), `cohort-venue-replace-now` (explore only), `bulk-cancel-class` (untracked/parked), and today's four new ones — `modal-responsive-width`, `modal-facts-fit-content`, `modal-dropdown-fixes`, `venue-evening-tail`, `tooltip-viewport-clamp` (all **applied**, waiting on the user's word for an independent Verify and archive), plus `arrangement-venue-picker-collapse` (**applied, `bd4a8e8`**), `my-timetable-responsive-prompt` (**proposal drafted, 2 review rounds, not frozen; phase B = the student page, and its prototype now exists**), `arrangement-summary-and-slot-rows` (**superseded-by-exemption** — the workload shipped in `c2fe630` under the small-change exemption, the folder is the record), and **`filters-button`** (task 7: **proposal frozen**, 4 rounds, no design yet). **151 lanes archived** in `.sdd/archive/` (today's: `2026-11-14-summary-cards-button`, plus `guide-toggle-icon-only` and `my-timetable-ui-cleanup` on disk only) — the two top-zone lanes were archived (and committed `a1e6470`), while `my-timetable-ui-cleanup` + `guide-toggle-icon-only` sit there on disk only (untracked) after the 2026-11-14 archive. Archive only on the user's word; never stage another lane's untracked files. |
| **The student page is `Class Replacements` (2026-11-09)** | Lists conflicted class **occurrences** (cancelled class or public holiday, request or no request), not requests; read-only apart from `Exclude COMPLETED Status`; rows with no live request read **Awaiting Arrangement**; cards `Classes Awaiting Arrangement` · `Hours Awaiting Arrangement` · `Pending` · `Approved` · `Completed`, the hours card counting **only unarranged rows**. Address `/student-class-replacements` (+ the old `/replacement-history-ui` as an alias). Full ruling: §2 #32. |
| **A page's name lives in FOUR places (2026-11-09)** | Tab title · nav label · route (old address kept as an alias) · the `<h1>`, which comes from `partials/ui-page-header`'s `title` argument and is the one a rename misses. Full ruling: §2 #34. |
| **The COURSE column is explained ONCE, on its header (2026-11-09)** | `UiLabels.courseTipSubject`; no per-cell hover text. `UiLabels.courseTip()` still exists in `ui-common.js` but has **no live call site** (dead helper, awaiting a cleanup that needs the user's word). Full ruling: §2 #35. |
| **Auto-reject rule (2026-11-06)** | A **pending** request whose proposed start is within — or exactly — **3 working hours** is rejected by the system. Working time = TARUMT Sabah hours, **Mon–Fri 08:30–17:30**, weekends + the semester's `holidays` excluded (`App\Support\WorkingHours`, `MIN_GAP_MINUTES = 180`). Write leg `App\Actions\AutoRejectRequest` (cancel-family release, `approver_id` NULL, audits `details.auto`); trigger `php artisan requests:auto-reject` (`--dry-run`) scheduled every 5 min — **never** a write-on-read. The Pending badge's `data-tip` states the deadline (proposed start − 3 working hours) on both pages; the **lecturer arm names the rule** since the 2026-11-06 UI update — *"Auto-Reject Rule: If This Request Has Not Been Approved At Least 3 Working Hours (…deadline…) Before The Proposed Class Start Time, It Will Be AUTOMATICALLY REJECTED."* (emphasis = capitalisation, a tip is textContent; the PL arm is unchanged). **Status 2026-11-14:** the user's crontab now runs `php artisan schedule:run` every minute with `DB_DATABASE=class_replacement_testing`, so `requests:auto-reject` fires every five minutes. That database holds the user's protected manual testing work, so the sweep can reject a pending request they are testing by hand; do not stop, alter or re-point the cron line without explicit permission, and never point it at a demo DB. Request #35's old manual decision is history. |
| **The notification tooltips speak one vocabulary (2026-11-06)** | Every live row's `data-tip` is composed by `App\Support\Notifications\NotificationTip` — labels / Subject / schedules / `Rejection Reason` / `Reason` / `Request ID`, holiday-first reason in the Original line's parentheses, `Automatically Rejected by System` when `approver_id IS NULL`, students without the Staff lines, legacy `title — body` only for an unknown kind. One extra batch (`holidays`); the `replacement_requests` one-query law holds. Receipts: `NotificationsBridgeTest` +8, `notifications-island.spec.ts` 10/10, demo hover `/tmp/opencode/notif-tip-hover.png`. |
| **A cancelled request displays as "Need to Arrange" (2026-11-06)** | A lecturer's own cancelled pending request is DISPLAYED as no active request on My Replacements: `badge-outline status-none` reading **Need to Arrange** (no-request tip), an em dash in REQUESTED SLOT, and a details pop-up with **no Requested Slot Schedule tab** (no Request ID/Week/Day/Date/Time/Duration/Venue). One normalisation in `ReplacementHome::buildRow()` — status→null + strings blanked. The RECORD is untouched (still `cancelled`, audited, slots released); history + notifications still say *Cancelled by YOU*. The status filter offers **no Cancelled option** (nothing on the page can carry that state); a filter saved before the removal is mapped onto Need to Arrange at restore. Receipts: `request-cancel-pending.spec.ts` (write), `replacement-home-status-badges.spec.ts` receipts 9 + 10. |
| **A JS-owned control survives a Livewire round-trip (2026-11-06)** | The week `<select>`s have no server-side options, so a page-component re-render used to morph them empty — Approve/Reject blanked the Week filter and its chip printed a bare `Week: `. Guard: `withWeekLivewireGuard()` in `ui-common.js` (snapshot before `pageLivewire().call()`, restore options + value **before the caller's `.then()`**), plus a `Livewire.hook('commit')` net. The chip it protects reads `Original Slot Week: …` (My Replacements) / `Requested Slot Week: …` (PL queue) — the SLOT wording since 2026-11-10 — never a bare `Week: `. Receipt: `tests/week-filter-preserved.spec.ts` (U1 Approve, U2 Reject, U3 Cancel Pending; U1/U2 pick the fixture's REQUESTED week 7 because of the filter law below). |
| **The submit action's own guards** | Since 2026-11-06 `SubmitReplacementRequest` enforces what only the page did: FR 2.17's 3-working-day notice (`slot_too_soon`, `WorkingHours::noticeCutoff()`) **and** D2's holiday landing ban (`holiday`). Sunday needs no arm (no Sunday slot rows). `tests/Feature/SubmitGatesTest.php` is the receipt file (10 cases). |
| **The arrangement grid colours by state, not ownership (rewritten 2026-11-11)** | Lane `arrangement-slot-ownership-legend`. A cell's class comes from ONE resolver (`classifyCell()`, exported on `window`) fed by BOTH arms: the venue payload's `status 1/3/4` and the busy vectors' **6th tuple key `mine`** (engine rows: lecturer vector `true`, cohort class rows `false`, cohort make-up rows `proposer_id === viewer` via the optional `?int $viewerId`). Precedence: own pending → others' pending → own conflict → any block → too-soon/available. **The 3px ownership ring is gone** from the conflict and pending families: colour alone says “cannot book”; who owns the slot is only in the hover words. Twins (my class that also carries my cohort) are merged in `busyByWeekPayload()` with `mine = OR(twins)` so the tooltip keeps one block. **Legend = four rows** — Available (green `.cell-available`), Your Current Selection (blue `.event-selection`), Pending (amber `.cell-pending`), Conflict (red `.cell-occupied`) — and **no ownership-hint line**. The cell's hover words still distinguish `Your Pending` / `Other Pending` / `Your Conflict` / `Venue Conflict` / `Cohorts Conflict` / `Your & Cohorts Conflict`; tips are TEXT (capitals = emphasis, `\n` = line break, never `<br>`). The Venue and Cohort grids keep their 0.5px ownership hairline and their own legend line. The bug it kills: the engine arm had no ownership test, so another staff member's pending painted "Pending (You)" whenever the selected subject shared that cohort (measured: request 28 / staff 5770 on B111 W9 in cohort 4's vector). Receipts: `arrangement-slot-ownership.spec.ts` (four rows, rings `0px`; its write test is blocked by a pre-existing W8 date fixture), `SlotBorderAndReasonBadgeTest`, `MakeUpBusyVectorTest`, `ReplacementArrangementTest`, `UiLegendBarTest` 4/4. |
| **A day countdown counts WORKING days (2026-11-07)** | One rule for every countdown the app prints (My Replacements' ORIGINAL SLOT cell + its details pop-up, and Request Approval's REQUESTED SLOT cell). Words: `Today` · `1 Working Day Left` · `N Working Days Left` · `Overdue (N Working Day(s) Ago)` — **`Today` is the only relative word** (no Yesterday/Tomorrow). Bands: past + today red (`--color-error`), **1–7 amber** (`--color-warning`), **8+ GREEN** (`--color-success` — the far band used to be grey; user law). Working days = Mon–Fri minus the timetable's own holiday rows; the walk is symmetric (a Friday read on a Saturday = `Overdue (1 Working Day Ago)`) and clamps ±1 when a calendar gap contains no working day. ONE holiday derivation for the whole page (`workingDayHolidayKeys()` — the cancel-class window reads it too, its private copy deleted, `8274ba0`). One band rule (`urgencyBandClass(days)`) and the `.urgency-*` colour rules live in **`theme.css`**, not a page. Placement (2026-11-07, same night): the countdown owns the line UNDER the date — date / urgency / time, `<br>`-separated — in both My Replacements columns and in Request Approval's requested column (`pendingCountdown` in `replacementBlock()`); a landed row keeps an INLINE `—` (no countdown to place). The **make-up** countdown appears **only while the request is pending**. In the details pop-up the countdown rides in BRACKETS after the date (`9 Nov 2026 (1 Working Day Left)`; `(Today)`), an OVERDUE label follows an em dash instead (`6 Nov 2026 — Overdue (1 Working Day Ago)` — its own words already carry brackets, user answer B), and a landed row reads `9 Nov 2026 —`. One helper (`detailCountdown()`) serves both pop-up tabs. Receipts: `tests/replacement-home-working-days.spec.ts` **8/8**, `replacement-home-status-badges` receipt 3 rewritten, `status-tooltips-filter-chips` T5.RA-4 + T5.RA-5. |
| **A request keeps its own length (2026-11-07)** | A replacement slot is a **30-minute cell**, so a 1-hour proposal covers two and a 2-hour one four. The run lives in `replacement_request_slots`, and EVERY release action (`RejectRequest`, `CancelPendingRequest`, `AutoRejectRequest`) DELETEs those rows to free the cells — leaving the anchor (`replacement_time_slot_id`, the span's FIRST cell) as sole evidence. `replacement_requests.replacement_span` (additive nullable smallint, 2026-11-07) now stores the width at submit, and `ReplacementRequestMapper::spanFromAnchor($anchor, $span)` grows the end from the anchor's start (`span × 30` min); a null width keeps the old single-cell shape. The junction still wins when present, so approved rows are byte-identical. ONE reader — never a second copy of the anchor shape (the notification tip had one, `NavNotifications::$anchorSpan`, and told a 2-hour rejection `11:00 AM ~ 11:30 AM`; `602a585`). Backfill for old rows: `php artisan crs:backfill-replacement-span` (dry run default, `--commit`, `--force` on demo DBs; reads the audit log — the decision entry's `slots` list, else the `submitted` count). Receipts: `tests/Feature/ReplacementSpanTest.php` 8/8, `MyReplacementsPayloadTest` case 5, `NotificationsBridgeTest` +1. |
| **The arrangement cell says WHY it is blocked (2026-11-07)** | User brief *UI Update — Hour-Cell Hover Labels* (grilled 1A 2A 3A 4A 5B 6A). The hour cell's hover label names the reason, from DATA: `Your Pending` · `Other Pending` (ownership) · `Venue Conflict` (grid `status 1`) · `Cohorts Conflict` (a row whose 7th tuple key `arms` contains `cohort`) · `Your Conflict` (a non-pending row with `mine`) · `Your & Cohorts Conflict` (both); `Occupied` survives only as the fallback for a row naming no arm. ONE resolver `classifyState(facts)` → `{cls, label}`; `classifyCell()` keeps its old string contract (the ownership receipt's 12 verdicts pass untouched) and `cellStateLabel()` returns the words. The words are a real `<span class="cell-state-label">` in the cell (2 lines, 11 px, clipped inside) and the CSS `::after` overlay is switched off ONLY where that span exists (`:has()`), so venue-timetable is untouched. The engine's four range mappers now carry `arms` (`own`\|`cohort`), and `dedupeBusyRanges()` UNIONS the arms of merged twins (a class that is mine AND the cohort's keeps both reasons). The legend is **FOUR rows** since 2026-11-11 — Available · Your Current Selection · Pending · Conflict — and the ownership hint is gone; the CELL still names the owner/reason (`Your Pending`, `Other Pending`, `Your Conflict`, `Venue Conflict`, `Cohorts Conflict`, `Your & Cohorts Conflict`). Do not reintroduce the six-row split or a “thick border = yours” line on this page. The cell's tooltip sits ABOVE the cell (`data-tip-pos="left"` pin removed) and its own-block tag reads `👤 Your conflict`. Receipts: `arrangement-slot-ownership.spec.ts` (four rows, rings `0px`; its write test is blocked by a pre-existing W8 date fixture), `ReplacementArrangementTest` (7-key tuples + arm union), `SlotBorderAndReasonBadgeTest`, screenshots `/tmp/opencode/label-*.png`, `tip-*.png`. |
| **The week filter names which weeks it means — and the two pages mean different ones (2026-11-07; "all" option → `All Weeks` 2026-11-14)** | **2026-11-14 (user): the "no filter" option reads `All Weeks` on every page** — the visible label above the field already says which week it is, so the 2026-11-10 SLOT form of the *option* is superseded (chips and column headers keep SLOT). The change ships with the `filters-button` lane and moves three pinned strings with it.<br>My Replacements filters the **original slot** week: chip `Original Slot Week: Week 8 · 9 Nov 2026 ~ 15 Nov 2026`, "all" option `All Original Slot Weeks`. Request Approval filters the **requested slot** week (the slot the PL is approving): chip `Requested Slot Week: …`, option `All Requested Slot Weeks`, its select tip says "Pick the Requested Replacement week to display". A request whose class sits in week 14 and whose proposal lands in week 7 therefore belongs to **week 7** on the PL queue — measured. `filterData()` on that page passes `r.replacementDate` to the shared `isInWeek()`, never `r.classDate`; the Home page passes `classDate`. `populateWeekSelect(sel, { includeAll, allLabel })` is the ONE builder of an "all" option (default `All Weeks`). The PL table's status column header reads **REQUEST STATUS** (was `STATUS`), and its sort hint names that too. **Leftover (2026-11-11):** the two week-select hover tips still read `Pick the Original Class week to display` / `Pick the Requested Replacement week to display`, and the `populateWeekSelect()` docblock in `ui-common.js` (~`:3408`) still names `All Original Class Weeks` / `All Requested Replacement Weeks` — the SLOT rename did not touch them, so rename or accept on the user's word. Receipts: `status-tooltips-filter-chips.spec.ts` **T5.RA-5** (both labels, the header, the chip, week 7 lists request 26 while its original week 14 does not, clearing restores it; it reseeds the fixture itself behind the fail-closed guard), `week-filter-preserved` U1/U2 moved to week 7 + U3 asserts the Home labels → **14/14** with the status file. |
| **FR 2.17 is server-side** | Since 2026-11-06 `App\Actions\SubmitReplacementRequest` refuses a slot inside the 3-working-day notice window (`slot_too_soon`) — the grid's `leadTimeCutoff()` was only the browser's word. Calendar: `WorkingHours::noticeCutoff()` / `isBookableOnDate()`, the same class as the auto-reject sweep, so grid "Bookable from …", the gate and the pending-deadline tooltip agree (measured 2026-11-06: Fri 6 Nov → **12 Nov** on both sides, 98/98 semester days). |
| **Venue page slot rules** | Since 2026-11-05: hover asks ONE question (**one clear hour**, `MIN_BOOK_SLOTS = 2`, never derived from a class), and a class's duration is judged only AFTER it is picked (Arrange gate → `#slotWarningOverlay`, no navigation). Lane `venue-slot-validation`; commits `daf11c6` + `bee6176`. |
| **The merge lane** | `replacement-merge-one-table` — **APPLIED 2026-11-04**, archived `07274c1`: `e70c1ea` (payload/mapper), `6856577` (view), `ab65f9a` (routes + retired page), `d3f6f5b` + `c4d0ad3` (specs). The merged page is **My Replacements** at `/my-replacements`, with `/replacement-home-ui` and `/my-request-history-ui` as alias route entries. |
| **Cancel Class** | **FINAL since 2026-11-05** — undo gone from the UI, the JS and the server (`23c627c`); the inbox marks stale notices inactive at read time instead of offering dead clicks (`7b7f0d9`); four final-decision modals carry the irreversible line (`12c644a`) — Approve / Reject / Cancel Request say *"This action cannot be UNDONE."*, **Cancel Class says the short `CANNOT UNDONE` in its footer since 2026-11-10** (§2 #39). **Whole-day cancel (lane `cancel-whole-day`, 2026-11-11/12):** the Cancel this Class? modal can tick every class the lecturer teaches that day; one transaction cancels the set, closes any pending/approved request on those classes and releases its slots. The make-up block is a note, not a row; the single-class path is unchanged. Receipts: `CancelClassBatchTest`, `MyDayClassesTest`, `ReleaseRequestSlotsTest`, `tests/cancel-whole-day.spec.ts`; full recorded suite **721/721 PASS, 6,090 assertions** (2026-11-14). |
| **E2E write-guard** | Live and fail-closed: `tests/helpers/receipt-target-guard.ts` + the three guarded specs (`b924552`, lane `receipt-target-guard` applied `7bbce9f`). Its one unverified receipt is `T3d` — the submission-conflict suite is not green; see §5. |
| **Date & time format (2026-11-07)** | One standard, shipped in batches 1–4 (`10a51b0`, `5120a98`, `f420215`, `1dc3edb`): date `5 Nov 2026` (no leading zero, day first), time `2:00 PM`, range `2:00 PM ~ 4:00 PM` (`~` joiner; hyphen acceptable but never mixed in one element; `–` is not a joiner). Day word `Thu` in compact lines / `Thursday` in prose. 24-hour grid axis and short notification tips stay. Emails carry the full date + 12-hour range. Full audit: `docs/date-time-format-audit-2026-11-07.md`; only #30 (API chip) remains, deferred. |
| Language | **B1-level English only** — no other language, ever. |
| Approval rule | Investigate first. **No code edit without the user's explicit per-task approval.** |

---

## 1. Latest sessions — 2026-11-03 → 2026-11-15

### 2026-11-15 — Student My Timetable gets its prototype; the class card settles into three tiers

Two days before, the timetable lane was a proposal and a staff preview. Today it gained phase B's
reference and an agreed card hierarchy, **still without touching a blade, `theme.css` or a test**.

**The student preview.** `ui-prototype/student-my-timetable-mobile-preview.html` is the staff
preview *copied*, then given the four deltas proposal §3b names — not a second hand-built page. That
choice is the point: the two files now share one `card()` builder and one stylesheet block, so a
later edit to one cannot silently diverge from the other without being copied deliberately. The
deltas: read-only (the pop-up's footer is a quiet outline `Close`, no filled button at all since the
same-day ruling below; no Arrange, Cancel, request link or
`Requested At` / `Requested By` rows), a cohort chip beside the title with the two-item student nav,
the **class's own lecturer** in the block tip where the staff page names the viewer, and summary
card 2 reading `Class Hours`. The fixtures are quoted from `docs/demo-logins.md` so the sample tells
a true story: cohort `DFT2 (S1) G1`, `24DFT0001`, and Monday 9 Nov 2026 on the Deepavali holiday —
the same class the demo student's pending request is about.

**The card, on the user's word (2026-11-15).** One hierarchy, by size and one grey: **primary** =
the time range and the venue code at the same size and weight; **secondary** = the course code and
type, smaller and grey; **tertiary** = one right-aligned note line, smallest and grey. Two
consequences worth remembering:

- **Below 1280px, the Cancelled / Conflict word moved off the line under the time** and onto that
  note line, where `(Pending since …)` and `(Replaced for …)` already lived. It keeps its status
  colour — it is the alarm — and only its size drops. This supersedes the "directly below the time
  range" wording that the brief had carried since 2026-11-12 **for the phone card and the tablet
  block**; the one-label rule itself is unchanged, and the desktop grid block keeps that placement
  because the instruction was width-scoped and desktop must stay ~97% identical.
- **The subject name left the card below 1280px.** Nothing was lost: the name was only ever printed
  in the phone/tablet card, and it survives in the hover tip, the details sheet subtitle and the
  card's `aria-label`. Phone cards went from ~100px to **68px** (94px when a note line is present),
  which is what makes a four-class day fit.

**The class pop-up.** Both timetable previews now carry the shape the separate
`details-modal-consistency` lane decided: short tab labels (`Class Info · Schedule · Original`;
the section captions keep the long names). The footer then diverged on the user's word later the
same day: **`Close` is never the filled primary** here, reversing the 14 Nov "Close takes the fill
when no real action shares the rail" rule. The student page was always that case, so it had been
shipping a blue Close as its normal state. Two more corrections came with it: a cancelled class's
Status row reads **`Class Cancelled`** rather than a bare `Cancelled` (which can be read as the
*request* being cancelled; a block keeps the short word because a one-hour desktop cell is narrow),
and the row now has a skin at all — **`theme.css` never defined `.badge-cancelled`**, so a cancelled
class printed a transparent pill while Conflict got red. **Ruling #2 the same day:** that first skin
was red and the user rejected it — **red is `Public Holiday`'s** (`.badge-public-holiday` and
`.badge-conflict` already share `--color-error` in §10.0), so a third meaning on the same colour would
blunt the signal. The two previews now wear **`.badge-reason`'s pair**,
`--color-surface-variant` / `--color-on-surface-variant` — the grey chip `Class Cancelled` already
prints on My Replacements — measured `rgb(228,232,238)` on `rgb(90,105,120)` in light theme.
**Porting still needs a `theme.css` line**, now copying `.badge-reason`, not `.badge-conflict`.

The timetable class pop-up is **not** in the `details-modal-consistency` lane — the note in both the
to-do list and the PI brief says so. That no longer causes a conflict: on 2026-11-15 the user said
**yes** to the same rule for the three replacement pop-ups, so `Close` is never the filled primary
anywhere. Applied to the prototypes that evening (`student-class-replacements.html` had it hard-coded
on the button; `my-replacements.html` toggled it in the quick-view opener; `request-approval.html`
already complied), and recorded as decision 10 in
`prompts/pi-prompt-details-modal-consistency.md`. **The port still needs two blade edits** —
`replacement-history.blade.php`'s hard-coded `is-primary` and `replacement-home.blade.php`'s
`classList.toggle('is-primary', !hasAction)` — after which
`theme.css`'s `.modal--detail .modal-footer .btn-outline.is-primary` has no consumer on these three
pop-ups (keep the rule; just stop calling it the Close rule).

While verifying that in the browser, the **student** prototype turned out to log itself out: it is a
saved copy, so its session countdown still carried the capture-time `data-last-activity`, and with a
12-hour lifetime the first tick auto-submitted the real `127.0.0.1:8000/logout`. Fixed prototype-only
by re-basing the countdown to load time (`student-class-replacements.html`, comment says "never copy
this back into the blade"); the pill still counts down, the redirect is gone. The two staff copies
happen to have lost that script when they were saved.

Evidence: 320 / 402 / 768 / 1024 / 1280 / 1440, both themes, zero page errors, no sideways scroll at
320. The **live** student page was not opened for a side-by-side: `SESSION_DRIVER=database`, so a
login writes a row into the demo database, and that needs the user's word. Records touched: the
brief (§5, §8, new §15), the lane (§3.2/§3.3, §3b, §9d, new §10, `sdd.yaml`, `review-log.md`), the
to-do list (task 8 status, plus "not this lane" notes on tasks 10 and 11) and the two PI prompts.

### 2026-11-14 (night) — the summary cards move behind one button, and the lane ran the whole ladder

`summary-cards-button` (`.sdd/archive/2026-11-14-summary-cards-button/`) is the first lane in weeks
to go explore → propose → design → specs → tasks → apply → verify → archive in one sitting. **Seven**
review rounds across the four batches, each a separate read-only subprocess — and every round found
something real, which is the argument for keeping the gate:

- design round 1 (🔴): my `syncButton()` used `offsetParent` to mean "the page hid the cards" — but the
  section lives inside the **closed** sheet at ≤768, where `offsetParent` is null, so the button could
  never have appeared. Predicate is now the section's own computed `display`.
- design round 2 (🔴): the hint-hide CSS was unscoped and would have deleted the **whole hint line** on
  all six flip pages inside the sheet, contradicting its own prose and decision 2.
- design round 2 (🟡): the 1-column card height had been derived at 389 (the widest 1-column case)
  instead of 320, where a fixed-height face clips rather than grows.
- specs round 1 (🔴): R6 said "the only additions are the button and the overlay" while the frozen
  design also splits the hint — a correct build would have failed verify.
- tasks round 1 (🔴): the evidence task had **no assertion** for repaint-after-relocation (the lane's
  one structural risk, on the `wire:ignore` page) and none for clipped card backs.

**What shipped.** `0514aaf` (feature) + `49c7290` (two delegations + the last page opt-in). Seven pages
opt in with one array key each; the sheet frame lives in `partials/ui-summary-bar` beside the cards it
shows, so a page cannot ship the button without the panel. The plain variant got its own inside-sheet
treatment (2 columns, 148px / 132px, tap-to-flip, touch hint) — it had been the odd one out for months.

**Mechanisms worth reusing** (now in `CodingMAIN.md` §10.0): an overlay sheet + a lifted dialog rather
than `<dialog>` (top layer beats every z-index — measured); one Escape per layer (capture-phase, no-op
while the upper dialog is open, trap suspended); a dedicated scroll-lock class (`summary-locked` is
shared and has no refcount); hide a control when its target is hidden, detected on runtime `display`
changes, never via `offsetParent`; `beforeprint`/`afterprint` for sheets that hold the content.

**Commits and hygiene.** The lane was committed **hunk-wise** because four of its page files also carry
another session's uncommitted guide-dialog work. That worked, but it caught me out once: the first pass's
hunk filter matched only the delegation that carried a `summary-cards-button` comment, so the **keydown
and mouseout** delegations were silently left out of the commit while the working tree (the verified
state) had them. Found by diffing index against the worktree *after* committing; fixed by staging the
remainder and amending with an accurate message. Lesson for §2: after a hunk-wise commit, **diff index
against the worktree for every lane file** before calling it done. The other session's `5c51ab8`
(venue selector) simultaneously took this lane's venue-page flag along, because the flag sat on a line
they rewrote.

**Same-night follow-up (user report, `7f95048`).** "The student-class-replacements summary cards are outdated — the
description is not 3 lines and there is no INFO button." Correct: that page was the last one still on the plain
card variant. It is a flip-card page now — three single-line bullets per back (shortened until the copy renders
3 lines at 320/390/768 with 0px spill; the long sentences moved into the ⓘ), the `detail` term list, ids and
colours untouched. `SummarySheet.unrelocate()` now strips the tap state from every card, because skipping flip
cards would have left one pinned after a resize. The plain variant now has **no live consumer** — only the nine
reference templates still render it, so its CSS and partial branch stay.

**Deliberately uncommitted**: the other session's guide-dialog blades and changelog blocks,
`theme.css`'s `.modal--detail` footer block, `top-zone-round2.spec.ts`, and
`rearrangement-toolbar-tooltips.spec.ts`.

### 2026-11-14 (late) — the phone venue sheet folds, the summary stops hiding itself, the slot rows read

Three pieces the user asked for after the UI batch above. Nothing pushed, nothing archived.

**`bd4a8e8` — the arrangement phone sheet's categories fold.** The "Choose venue" sheet listed all
23 rooms under grey captions that did nothing. Each caption is now a folding bar: `<button
aria-expanded aria-controls>`, a 14px chevron that turns, sticky at the top of the scrolling list,
token fill so it reads as chrome. First (sorted) block open; a **search opens every block with a
match**, or a hit could sit invisible inside a folded block; folding deliberately resets on reopen
and on every keystroke, matching the venue page's own picker. `aria-expanded` on the button is the
only state. That commit also landed the **2026-11-12 searchable sheet** the change lives inside —
`renderArrangementVenuePicker` existed in **no commit at all** — with its dialog markup, its
(untracked) spec and the arrangement changelog block; without that, the folding code could not be
committed without splitting a function from itself. User's word: "yes bring in".

**`c2fe630` — the Selection Summary cannot fold, and a slot row is a stack.** The summary's chevron
could hide the picked slots, the totals and the Submit button behind one click, so the user asked
for it gone: the button, its listener and the unreachable `.sel-summary.is-collapsed` rule are
deleted, while Conflict Schedule and Class Details keep their toggles. The slot list's single
wrapping sentence became four glyph rows — `(P) Week 8 · Nov 9, 2026` / `11:00 AM ~ 1:00 PM` / `B110`
/ `DFT2 + DSF2 (2 cohorts)`. The bracket counts the **cohorts underneath**, not the distinct codes
(user answer A): `DSF2 (2 cohorts)` when two cohorts shorten to the same word, bare `DSF2` for one,
no row at all for none. The four glyphs were hoisted out of `updateSelectionSummary()` into one
`RA_ICONS` constant, because the row builder could not reach them there.

**Verification:** `venue-dropdown-responsive.spec.ts` **7/7** on the blessed `:8030` invocation (four
runs, including one after reverting the lane's regions for an A/B); `selection-summary-collapse.spec.ts`
rewritten to pin the fold's **absence** and passing; real Tab reaches the fold bars with a 2px primary
ring, Space/Enter toggle them; both themes; 0 page errors at 1440/1360/390; the testing database's
eight counts identical before and after every run. `arrangement-mobile.spec.ts` gives 36 passed /
1 failed / 2 skipped, and the failure **reproduces with the lane's regions reverted** (it wants a
freshly seeded DB). `rearrangement-toolbar-tooltips.spec.ts` keeps two **data-driven** failures:
`?session=67` redirects to `/my-replacements?blocked=request-pending`, so the page never renders.

**Process note:** the last two items ran under the **small-change exemption** after the user stopped
a full lane ("SKIP & make the two changes now"). The lane folder remains as the record
(`status: superseded-by-exemption`); the facts it captured (exact regions, the spec inventory,
the cohort-count semantics, the icon-scope wall) are what made the two edits quick and honest.

### 2026-11-14 (evening) — pop-ups fit their content, the evening became bookable, the tooltip stays on screen

Six UI lanes, each run through its own SDD chain (proposal → design → specs → tasks, independently
reviewed read-only) before any code was touched. Nothing pushed, nothing archived, no demo cleanup,
cron untouched.

**`modal-responsive-width` (`f15ef96`).** User brief: pop-ups must fit small screens without changing
the desktop look. The lane replaced five page-local width pins with **one** shared `theme.css` rule:
680 is a **ceiling**, above 600px `width: fit-content` with a 420px floor and `max-height: 85dvh`,
at ≤600px a full-width sheet with the overlay's padding removed. Variant ceilings: details 680, guide
520, summary 420, Cancel Class 560 (588 at ≥1025). Close button 48×48, the phone footer stretches
every button, and header / scrolling body / footer are pinned as three regions. The page-local
`max-width: 680px` on `#quickViewModal .modal` and on the Request Approval shell were **deleted**
because on ID specificity they beat the shared rule. Measured at 390/600/601/700/768/1024/1360: no
pop-up grew; class details went 680 → 420 above 768. `T3b` (the arrangement page's own confirm-dialog
block) stays **deferred** — that file carries another session's uncommitted work.

**`modal-facts-fit-content` (`308034d`) + `1756745`.** The grey facts strip was deciding the pop-up's
width; `.modal .detail-facts { width: 0; min-width: 100% }` makes it wrap instead (626 → 513 at the
same height). While applying, the brief's assumption was corrected: "Select a Session" was never the
560 pop-up — it already uses `.modal--detail`; the 560 belongs to `#slotWarningOverlay` ("Slot Too
Short"), which was then raised to 680 and pinned.

**`venue-evening-tail` (`6b861f2` + `e2aefa3`).** The venue grid painted its last half-hours red
("Too Late to Book") even when they were free. Round 1 repainted them from the data: the tail exists
only for days whose last served row is in the final hour, so a half-day room stays truthful; the tail
is counted as available; `Not Enough Slot` stays green. Round 2 made the evening **really bookable**
at the user's word: 3,864 `available` `time_slots` rows added to `class_replacement_fresh` (snapshot →
rolled-back rehearsal → two committed `INSERT…SELECT`s → row-count receipt → log) and
`TimeSlotsSeeder`'s loop widened to `< 19`, so the axis is 08:00–18:30 with rows to 19:00. Verified on
B009 Thu 19 Nov: 6:00 PM offers, 6:30 PM green "Not Enough Slot", 0 red cells, Available 72 = 72.

**`modal-dropdown-fixes` (`85ce7a3` + `6f197de`).** The session list was clipped by the pop-up's
scrolling body; `:has(.session-dd.open)` now goes `overflow: visible` so it floats outside (and opens
**upward** inside the phone sheet). The pop-up's select arrows were a second arrow language; they now
use the sanctioned `.week-select` chevron at a shared 12px inset — measured 13px from the outer edge
on both, dark and light.

**`tooltip-viewport-clamp` (`65f1dc1`).** The Arrange tooltip ran past the right edge and never
answered a narrower window. It now has a 340px ceiling, is measured once at `left: 8px` (a
shrink-to-fit fixed box widens as it moves away from the edge, so measuring at the final spot looped
and landed exactly on the border), clamped 8px inside every edge, flipped above the cell when there is
no room below, re-placed on `resize` after layout, hidden when a pop-up opens over it, and placed the
same way by the `B` key as by a click. Measured at 1440/1024/768/390/360 including a live resize while
open.

**Honest gaps:** the new `U2a-T18` spec is **written but not run** — `venue-book.spec.ts` refuses any
target but `:8030`, and `:8030` cannot authenticate because `class_replacement_testing` holds **0
users**. The backend suite was not re-run (its config points at that same protected database and the
separate test cluster is stopped). The user's to-do list task 4 (`modal-responsive-width`) was then
ticked on their word.

### 2026-11-14 — own-approved original hidden; all other states retained

User explicitly approved proceeding and clarified that only the exact source occurrence's Approved replacement removes its cancelled timetable block. Sequential proposal/design/spec/tasks revision-2 reviews passed before implementation. The shared staff/cohort/student builder and venue backfill now use their existing own-approved source-pair maps without the old overlap escape; dead interval accumulation removed. Stored exception/reason/audit/request/link/slot data, landing span, privacy, JS layout and booking rules unchanged.

Updated four obsolete backend expectations, added all-four six-state/six-location matrices, other-source and second cancelled-week controls, and before/after business snapshots. Saved-original baseline had **10 expected failures /31**, without reverting live files. Final ten-file backend run **122/122, 2,659 assertions**, on a forced/queried isolated testing database (socket `/tmp/crs-approved-suppression/socket`, port55439, verified data_directory); wrong-target refusal tested. Temporary cluster is now **stopped**, files/dump retained. No backend refresh test touched the manual database. Read-only planner/layout **18/18 +3 auth**, lint/PHPStan/PHP/JS/Blade/whitespace and independent scoped review PASS. Writing lifecycle T1c updated, **11 cases collected only**, never executed on manual data.

Real request19 is now **approved**; request18 remains cancelled. Source2673/week10 exception16 retained. Actual staff5770, cohort488 DSF2(S1)G1, matching student24DSF0001 and venueB010 pages omit the source from server JSON/DOM and show one full **13:00–14:00** approved landing, two half-hour cells. Sixteen desktop/phone light/dark screenshots; Main inspected four. Student access to lecturer-only cohort route still403; permissions unchanged. Manual business rows and audit/notification counts compare identical. :8030 restarted through sanctioned script and remains HTTP200 on login. Committed as **46fd6f7**; seven commits ahead, nothing pushed. No push/archive/demo cleanup/whole-feature Verify. Receipts and dump `/tmp/crs-approved-suppression/`; matching SDD apply/review logs and five timetable/feature changelogs.

### 2026-11-14 — half-hour overlap regression fixed; protect manual testing data

User reported pending 1:00 PM ~ 2:00 PM and cancelled 1:30 PM ~ 2:30 PM side by side instead of overlapping. Approved fix & test. Stored exclusive ends were compared with raw inclusive candidates; the shared planner now normalises both, forms chronological components, then allocates cancellation-first with fresh per-component lanes. Only the planner function and two existing read-only browser specs changed; PHP/Blade/CSS and other sessions' pending UI work are untouched. Three new cases cover the report, 1,764 independent occupied-cell pair combinations in both orders, and actual desktop column geometry plus phone containment. **18/18 browser cases**, separate **3/3 auth setups**, lint/PHPStan/JS syntax and independent scoped review PASS. Existing responsive/theme/print cases pass. Main also checked the user's REAL request **19** (pending then, **later approved**), source **2673**, week **10**, without staging payloads or clicking an action: correct stacking and half-hour alignment on desktop; phone cards retained, both themes captured. Request **18** was cancelled by the user before this fix. Before/after requests, exception, junctions, three affected cells and audit/notification counts are identical. Receipts `/tmp/crs-half-hour-overlap/`, matching SDD apply/review logs and timetable changelogs. **Do not run DB-refresh tests or reseed: testing DB now contains manual work. Leave :8030 running.** No commit/push/archive/demo cleanup/scheduler operation; whole-feature Verify remains separate.

### 2026-11-14 — final: all four approved test repairs complete, full backend green

User approved the two remaining date fixtures from the full run. ONLY their named methods in MyReplacementsPayloadTest/ReplacementHomeTest additionally changed. Relative-term payload test freezes a copied existing semester start +24days09UTC (Thu/week4), avoiding future date-aging; fixed-term Home test freezes Nov12Thu09UTC (week11). Explicit Wed/Thu/Fri coordinate guards added, all status/label/day-count/no-write assertions retained. Earlier two repairs unchanged (hash confirmed), helpers/setUp/schema/app untouched. Final focused pair2/2 (33 assertions), three full files31/31 (308), lint/PHPStan and independent review PASS. **FINAL full suite721/721 PASS,6,090 assertions,571.751sec**, `/tmp/crs-history-test-fix/full-suite-r2.txt`, completed11:44 inside original60minbox. This resolves the backend-test caveat; cancelled-slot-reuse whole-feature acceptance Verify remains separate/parked. Later user-approved commit **0e6bc1a** includes only three test files and seven narrow SDD artifacts. No push/archive/demo operation; other sessions' work remains unstaged. Lint/PHPStan rechecked before commit. Narrow change `.sdd/changes/student-history-test-contract/` verified and committed; archive not performed. Older failed checkpoints below are historical and superseded.

### Earlier checkpoint — two approved repairs completed; remaining two date-fixture approval requested

**Later approval exercised:** the second boundary method now freezes only its own callback at Thu12Nov2026 09:00 UTC and asserts legitimate week11/day3–4 coordinates. Both original approved-status assertions retained; first repair unchanged. Whole file **8/8, 97 assertions**, six related suites **49/49, 323 assertions**, lint/PHPStan and independent scoped review PASS. Full suite run completed in587.263sec: **721 tests, 719 passed, 6,066 assertions, 2 errors** (other files MyReplacementsPayloadTest / ReplacementHomeTest attempt Sunday fixtures on Saturday). Both errors reproduce in a fresh isolated process; both files have empty git diffs. Do not broaden without approval or claim clean full-suite/whole-feature Verify. No app edits/commit/push/demo activity. Current change scoped-verified, not archived. Full details: `.sdd/changes/student-history-test-contract/verify-report.md` and `/tmp/crs-history-test-fix/full-suite.txt`.

### Earlier checkpoint — stale student test repaired; second fixture approval requested

Only the obsolete student payload test method/name/comment in `tests/Feature/ReplacementHistoriesTest.php` changed: assert occurrence ids `session:week`, nested `orig`/`req`, pending proposal vs approved status, independently derived dates/geometry and stronger cohort/leave-reason privacy; local `Carbon::withTestNow` restores on exit. Fixed method **1/1, 37 assertions**; six related suites **49/49, 323 assertions**. Lint/PHPStan and independent scoped review PASS. All seven sibling methods/helpers unchanged. Whole file **7/8, 92 assertions** because unchanged `test_merged_page_keeps_today_and_future_approved_requests_as_approved` uses tomorrow on Saturday 14 Nov → Sunday day 6, rejected correctly by DB. Original HEAD copy reproduces the identical error, proving no clock leak/regression from this repair. Do not claim full suite green. Stop/report and ask approval for this second date-fixture fix rather than broadening silently. No app changes, demo action, browser/server restart, manual reset/reseed, scheduler change, commit or push. Testing schema retained with zero classes/requests after test framework rollback; no browser baseline rebuild was performed. Current tiny SDD: `.sdd/changes/student-history-test-contract/`; receipts `/tmp/crs-history-test-fix/`. Cancelled-slot-reuse final Verify remains separate/parked.

### 2026-11-13 — latest: older regressions cleared and Part B implemented

**Later commit approval exercised:** user answered yes to separate commits. Completed **ca98fb5 — test: make booking regression fixtures repeatable** (four test/helper files), then **74ab29b — feat: explain actual room holders in arrangement** (12 owned paths/hunks). B04 checked, **27/38**. Mixed Blade/changelog staging used continuation-only preflight deltas; all 43 pre-existing tracked diffs were preserved (normalised hunk-offset receipt: `/tmp/crs-reconcile-b/commit-preservation.json`). Lint/PHPStan rerun passed before the second commit. No push/DB action/server restart. This mixed handover remains unstaged to avoid bundling another session's diary edits.

User approved fixing the five older failures and all Part B. Fixed natural source discovery (staff/module/type, not class 39), live-calendar/real-offer fixtures, current date/time wording, an independent room-matrix span audit, ownership labels versus notice labels, and the whole-day test's 1-based/0-based week error. Combined old specs pass **24/24** with all three formerly unrun followers. On the final baseline class IDs were 49–149 and obsolete class 39 was absent; no sequence-reset workaround.

Part B names the real active room holder from shared claim proof, uses the full stored/junction span and public NORMAL/PENDING/REPLACEMENT vocabulary, and merges factual holder identities across calendars. Unknown/drifted claims remain neutral; available cells gain no cancellation note. The existing phone snapshot/sheet consumes the same safe text. Two fallback edge cases found by independent review were fixed and received passing rereview.

Checks: **98 backend tests / 1,712 assertions**, **26 lifecycle/layout/planner browser checks**, **8 new holder checks**; static gates pass. The real holder browser fixture reuses the guarded CSR seed/real-submit helper; synthetic merge/escaping checks are disclosed separately. Main inspected desktop/phone light/dark PNGs in `/tmp/crs-reconcile-b/room-holder-results-r5/`. Full-suite history-shape error remains outside scope; final Verify is not claimed. No new commit/push, demo DB access/write/restart, new migration/production schema change or scheduler change. Part A's approved amended commit is **b1f13fd** (the earlier 0d727a3 receipt hash was superseded).

### 2026-11-13 — continuation: lifecycle passes, actual testing rehearsal, full-suite receipt

The new lifecycle suite is now 11/11 (8 cases + 3 setups), including mobile booking/auto-rejection, reject/withdraw and both privacy contrasts. Shared layout/planner is 18/18 (15 + 3 setups), with retained viewport/theme/print captures under `/tmp/crs-reuse-layout-receipts-r5`. A real phone defect was fixed locally: free base-row offers now show time ranges with ≥44px targets. No ordinary-cell/global layout redesign.

Full direct PHPUnit completed in 724.605 seconds: 708 tests, 707 passing, 5,877 assertions; only the pre-existing ReplacementHistoriesTest line-302 payload-shape error. The first artisan run hit a 600-second tool timeout, not a pass. Static/lint/view gates pass. Independent approval and display/payload slice reviews PASS; whole-feature Verify remains open.

C03 now has genuine testing backup/restore-list/hash/manifest evidence and real guarded CLI apply: 4 legacy original cells freed at versions 1→2, one maintenance audit, zero notifications, clean verification. Real recovery restores at version 3 inside a rolled-back rehearsal; version drift and pending reuse each refuse without partial writes. Repeat plan is zero-change. Scoped fixtures were removed; the browser baseline was seeded only after testing target + zero users were checked. Details/receipts: `/tmp/crs-reuse-next/cleanup-*` and latest apply-log.

Older browser regressions remain: 16 passed / 5 failed / 3 not run across selected-day, venue and ownership suites. Selected-day cancellation passes. Ownership uses absent fixed class id 39; venue cases include stale date/time copy and span/no-fit/day-end assumptions. Do not claim all five proven pre-existing: finish classification without weakening fail-closed real occupancy or resetting sequences. Formal tasks 23/38; remaining targeting/visual/scope gates, regression reconciliation, A22/G3 and Part B are open. All workers exited. Testing server :8030 is stopped after receipts; restart only with literal testing DB for continuation. No demo cleanup/write/restart, schema/scheduler change, staging, commit or push.

### 2026-11-13 — cancelled-slot reuse backend connected; delivery checks still open

Cancellation now releases the original room cells in the same transaction, alongside attached pending/approved booking closure. Selected-day class changes and checked make-up withdrawals roll back together. Approval protects its complete landing run, including same-slot reuse and junction-less stored spans. Shared source locks leave Submit's OCC outside a new outer transaction. Canonical geometry, precise lock membership, current cancellation-audit evidence and truthful release receipts have targeted tests.

Timetable payload/layout code is present: separate occurrence/request identities and stacked blocks; venue selection uses actual room-cell state so pre-cleanup occupied rows cannot turn green merely because their visible event is cancelled. Cleanup CLI/runbook/testing units exist, but no live operation was requested and real testing backup/recovery workflow evidence is still open. Part B holder-hover work is not implemented.

Latest combined targeted receipt: `/tmp/crs-reuse-integrated-targeted-tests-r5.txt` **171/171, 2,813 assertions**. Static/lint/view checks pass. Browser `/tmp/crs-reuse-e2e-tests-r4.txt`: the real UI cancellation, exact same-class request and PL approval pass. T2 has a mistaken time-delimiter expectation (`~` vs the allowed existing `-`); its privacy checks and four later lifecycle/student cases remain unfinished. Full PHPUnit, old regressions, independent re-review, settled viewport screenshots and C03's real testing `pg_dump`/`pg_restore --list` rehearsal still need work. The full command/file receipts and next steps are in `.sdd/changes/cancelled-slot-reuse/apply-log.md`.

All subprocesses exited; code is uncommitted. Preserve other sessions' guide/mobile/summary/archive changes, the user's untracked task list, and the separate request35 demo-repair receipt. Only `:8030` was restarted, on the testing DB. No demo writes, demo restart, scheduler change, staging, commit or push. G2/G3 remain closed.


### 2026-11-12 — the arrangement goes mobile, the student popup matches, and the day cancels as one

**Mobile arrangement day view (lane `arrangement-mobile-day-view`, `791380f`, archived `0c2899f`).**
`/replacement-arrangement` now has a dedicated phone day view: one day at a time, a compact
weekday/date picker, the same bottom-sheet frame for every popup, and a fixed action footer with the
blank band removed. Desktop is unchanged. A second mobile pass the same day added the full-bleed
`--color-primary-container` top bar, a 44px text-less Back pill and theme toggle, the icon-only
`Earliest bookable` trigger, and the footer-clearance fix. Receipt: `arrangement-mobile.spec.ts`
**47 passed / 3 skipped** at 390px; unit 48/48; no demo DB touched.

**Desktop top zone, two cards then Round 2 (lanes `replacement-arrangement-top-zone`, `7692d80`;
`replacement-arrangement-top-zone-round2`, `d2d91ae`).**
The old conflict strip is gone. Round 1 made the desktop top zone a **Conflict Schedule** card
(`#subjectInfo`) plus a **Replacement Schedule** card, with the visible label **Course** (internal
`subjectSelector` kept), a two-line Slot trigger (`(L/T/P) Week N · Day, D Mon YEAR` /
`time ~ time @ venue pill`), a whole-card collapse, the real shared venue dropdown showing code +
seats, and the details facts from `reasonFacts()`. **Round 2 is applied and committed (`d2d91ae`, lane
`replacement-arrangement-top-zone-round2`):** three setup cards (Conflict Schedule / Class Details /
Replacement Schedule) plus a conditional fourth Summary, 56px side rails, `8fr 5fr 7fr`, the
1101–1319px Summary row 2 and the 1320px `8fr 5fr 8fr + 280px` grid. The Conflict card stacks Course
over Slot; the Venue trigger fills its row. Mobile gains exactly one relocation entry
(`.ra-class-details` → the Original class facts). One live-parity correction followed the first
screenshot review: the Conflict card stacks **Course over Slot** and the **Venue trigger fills its
row** (the old 45/55 split and 216px cap were stale). Changelog:
`page-changelogs/replacement-arrangement-changelog.md`; receipts `tests/top-zone-round2.spec.ts` 13/13,
`arrangement-mobile` 51 passed / 3 skipped, `session-arrival-pin-guard` 3/3.

**Student popup becomes the staff Replacement Details popup (lane `student-modal-staff-parity`,
`8ab7108`).** The student Class Replacements popup now uses the staff layout: title **Replacement
Details**, four tabs (Requested Slot Schedule · Original Slot Schedule · Class Info · Request Status),
a static **Close LEFT / Go to My Timetable RIGHT** footer, the conflict as a strip above the tabs,
course line in the subtitle, and `Class ID` / `Request ID` naming. The one-details-width law moved
**616 → 680px**, and `.modal-tabs` wraps below **520px**. Checklist items **B5/B6** and **B9** folded
into it; **B8/B10** stayed out. Receipts: `ModalConsistencyTest`, `StudentReplacementHistoryParityTest`,
`ReplacementHistoryRowTest`.

**My Replacements table and popup (2026-11-11/12).** The ownership desk was re-cut to **Course 16% ·
Cohort 10% · Original 18.9% · Conflict 13.2% · Requested 18.9% · Status 17% · Action 6%**, with both
slot columns equal and course up / conflict down (`9968ea4`). The Replacement Details venue row is
now plain text, exactly like Request Details — no icon, no muted `.cell-sub` skin, no em dash when
absent (`ecd5406`).

**Cancel this Class? can cancel the whole day (lane `cancel-whole-day`, `e21a261` / `5655d52` /
`f0b86ac`).** The modal's optional block ticks every class the lecturer teaches that day; unticked and
folded by default. The head tick is select-all with a half-tick; every row is its own checkbox; the
button reads **Yes, Cancel N Classes**. The day cancels in **one transaction**, closes any pending or
approved request on those classes, releases the slots and audits each class. The make-up block is a
note, never a tickable row; the single-class flow is unchanged. The success screen lists the classes
and the withdrawn-request count, then refreshes from the server. Receipts: `ReleaseRequestSlotsTest`,
`CancelClassBatchTest`, `MyDayClassesTest`, `tests/cancel-whole-day.spec.ts`; the suite was later
repaired to **721/721 PASS, 6,090 assertions** (2026-11-14).

**Auto-reject investigation (2026-11-12; resolved the same day).** Staff 5425's request **#35** (B100,
week 8, Thu 12 Nov 2026, 08:00–09:00) had stayed Pending past its deadline because the machine had no
scheduler runner. The rule, span mapping and deadline were correct. **Resolution:** the user pinned a
crontab line that runs `php artisan schedule:run` every minute with
`DB_DATABASE=class_replacement_testing`, so `requests:auto-reject` now fires every five minutes; see
§0 and §5 item 5. Since 2026-11-14 that database holds protected manual data, so do not alter the cron
line or manually reject #35 without permission.

**Planning only.** `timetable-print-v2` (real print on the four timetable pages) has frozen
proposal/design/spec/tasks but **no code**. `guide-toggle-icon-only` (one question-mark guide icon on
eight pages) is frozen and waiting for apply. `bulk-cancel-class` remains parked.

### 2026-11-11 — the ownership ring goes, the badges get short, and the cards explain their numbers

**Ownership ring removed; legend matches the grid (`b00c0b1`, `28f9f4d`).** Colour alone now says
“cannot book”. The arrangement conflict and pending cells lost their 3px ownership ring; the legend is
**four rows** (Available · Your Current Selection · Pending · Conflict) and the ownership-hint line is
deleted. Ownership is still named in the cell hover (`Your Pending` / `Other Pending`, `Your Conflict`
/ `Venue Conflict` / `Cohorts Conflict` / `Your & Cohorts Conflict`). Venue and Cohort keep their
0.5px hairline and their own legend line. Receipt: `SlotBorderAndReasonBadgeTest`,
`arrangement-slot-ownership.spec.ts` re-pointed.

**Conflict badges are short in the table and full in the popup (`28f9f4d`, `5b0b9ff`).** The table
and phone card print only **`Public Holiday`** or **`Class Cancelled`**, with the detail in the
badge hover. The details popup has room and prints **`Public Holiday — Deepavali Holiday (In Lieu)`**
/ **`Class Cancelled — Annual Leave`** in full, with no hover. The student side gets the holiday name
but no cancellation-reason tip (the lecturer's reason stays private).

**Summary cards explain their formula (`d5e9953`, `552b63f`).** Every live page's cards now state how
the number is made, formula first, with `+N` explained only where printed. On My Timetable and Student
My Timetable, **Total Classes** and **Teaching Hours** split finished + pending (`9+1`, `12+1`), using
the shared `{ splitPending: true }` path; Cohort/Venue already used that shape. The arrangement page's
summary bar is commented out, so its cards are not visible there.

**Smaller UI fixes.** A blank week select no longer empties the table or the chip (`2630c9d`); the
Earliest-bookable tooltip now names the correct day (`837d7b2`); a student notice lands on **Class
Replacements** and flashes the matching row/card (`adeb3f9`). The arrangement tip became a compact
`[💡 Tip]` trigger (`b00c0b1`).

### 2026-11-10 — one vocabulary across the modals, tables and icons

**The day shipped 32 commits and ended with the user's commanded push** (§0 Push state: `71a8254`,
0 ahead). Each step was verified in a browser on :8000 with zero console errors, except the final
cancel-class commit, where the user waived the browser pass (static gates only — say so if you need
that receipt, it was never taken).

**Modal consistency (`36779f3`, `a65f4a0`, `47626bb`, `3a66d9f`, `ce176fc`, `1c9e4d9`, `b9ea391`).**
One shell `.modal.modal--detail` (616 px) with the timeline rail gone (`DetailModal.timeline()` + dots
removed); the subtitle carries the class type; `Replace Now` → **`Arrange Replacement`** with the swap
glyph (id `#btnReplaceNow` kept — the frozen specs select it); Class Information leads with `Session ID`,
names the **subject once** (the `Subject` row was deleted, the subtitle owns it) and the **room once**
(`B002 — Tutorial Room (35 seats)`; `Room Name`/`Capacity` rows gone, `venueSeats` handed in only by the
venue page); Schedule = Venue · Week · Day & Date · Time & Duration; **one footer button size — 34 px
fixed height** (a `min-height` left text-only buttons at 35 px, hence a fixed `height`); **one Close
skin** (`.btn-close-modal` = the `.btn-outline` skin, class name kept as the JS hook); **one subtitle
separator — the em dash**; the slot-validity row stopped printing a second glyph. ONE wording helper:
`ModalFacts` (`dayDate`, `timeDuration`, `roomName(code, name)`, `venue(code, name, seats)`).
Board for the rest: `docs/Modal inconsistent Report checklist.md` (**untracked**, the user's doc) —
4 fixed · 1 mostly fixed · **6 open (B2, B5, B8, B9, B10, B11)** · 2 withdrawn; B12/B13 were **my**
measurement errors (a `.modal-title` read against these modals' bare `<h2>`, and a hover colour measured
straight after a click).

**The tables say SLOT (`817e633`, `09b7c62`, `dff51bf`, `8688a3e`).** `ORIGINAL CLASS` → **`ORIGINAL
SLOT`**, `REQUESTED REPLACEMENT` → **`REQUESTED SLOT`** (the student page already said Slot), the duration
text under each class line is removed from both columns on both staff pages and in the merged builders,
and the sort hint, the week-filter "all" options, the filter chips and the header tips followed. Pop-up
tabs + section headings read **`Original Slot Schedule` / `Requested Slot Schedule`**, and `NotificationTip`
followed for the bell (`Original Slot Schedule`, `Requested Slot Schedule`, `Approved Slot Schedule`) —
a tip is **built on read**, so no data rewrite was needed. `Cancelled Class Schedule` stays (user).

**The arrangement page (`bb675d9`, `dac3a41`, `c49a17e`, `bd3eb48`, `7009313`, `89a0d1e`, `75441d6`).**
The selected-slot card is four icon rows at ONE typography (`B111` / `W8, Fri, 13 Nov 2026` /
`10:00 AM ~ 12:00 PM` / `4 slots (2 Hours)`), its venue tooltip through `ModalFacts.roomName()`;
**ruling C**: the 3-working-day rule now OUTRANKS the ownership paint (`.cell-ph` and `.cell-pending`
preserved, the blocking reason kept in the tooltip; on the W8/B111 census that was 64 too-soon cells,
29 of them carrying a reason — a snapshot, not a rule); the lead-time banner moved into that cell's
tooltip and the cell reads `Min 3 Working Days` while the tip states the rule with the FULL weekday;
Submit Request moved beside the summary tip as a **sibling** of `#summaryTip` (that node's `innerHTML`
is rewritten every render), Clear ALL is commented out and
`.footer-area` is deleted. The slot picker's badge now names the real reason — **`Public Holiday`** when
the day is a holiday (`weekData[week].days[day].holiday`, the same fact the grid paints), `Cancelled` for
a cancelled class, `Conflict` otherwise. The "Showing venues that fit N students" note is removed (the
venue FILTER stays).

**Cancel this Class? (`c55b530`, `71a8254`).** The shouty `You MUST Select a Cancellation Reason` line is
gone; the eight reasons sit **two per row** (one per row under 520 px); the heading carries the red
required asterisk; the irreversible warning moved to the **footer** as the short **`CANNOT UNDONE`** —
`ui-irreversible-line` grew an opt-in `compact` arm so the other three modals keep the sentence
byte-identical — and it shows only while Yes is armed; "Other" reveals a labelled block
(`Other reason *` + the full-width textarea) below the grid; the blank-`Other` sentence is gone (the gate
still refuses, silently). Browser pass waived by the user for this one; pint/phpstan/view:cache green.

**One action, one icon (`8ed6e73`, `8cdd750`).** The ACTION column's primary button and the Replacement
Details `#qvArrangeBtn` now draw `#btnReplaceNow`'s swap glyph (15 px, stroke 2) instead of the old
circular "rotate" arrow; boxes, tooltips, aria and the `goToReplacementFor`/`qvArrange` calls untouched.

**Venue Timetable (`21ae301`, `ada25d0`).** The room description is the arrangement page's **info-icon
chip** (`.badge.badge-past` + `data-tip` + `aria-label`, 32×20 desktop / 40×40 phone — measured identical
to `#arrVenueDescription`) instead of a plain grey line, in the same `.venue-side-chips` group as the
capacity chip. The week cluster (arrows + picker + Today + Earliest) is **centred** in the toolbar: a
5-column grid whose equal `minmax(0,1fr)` side columns put it exactly on the bar's centre line (offset
0 px at 1600/1440/1280/1024/920/900), falling back to the wrapping flex row below 900 px.

**Measured at the end of the day:** full PHPUnit **529 tests / 2992 assertions — 528 green + 1 pre-existing
stale error**; targeted 45/45 (`ModalConsistency`, `VenueTimetable`, `CancelledBlockStaysVisible`,
`ThemeCssIntegrity`, `TimetableWiring`). Proof screenshots in `/tmp/opencode/`.

### 2026-11-09 — the student side becomes one real page, and the week stops jumping

**The student page is rebuilt (lane `student-class-replacements`, `758a7bc`, archived `6ffa054`).**
Eight grilling rulings (R1–R8) and then a full SDD lane. `ReplacementHistory` became an
**occurrence walk** (sessions × weeks, `exception ∨ holiday`) instead of a request list, so a
cancelled class with no request now appears at all; row key `"<sessionId>:<week>"`, `requestId`
nullable, status literal `'none'`, 0 queries inside the loop. `ReplacementRequestMapper::statusLabel()`
gained an `$audience` arm (`'student'` → **Awaiting Arrangement**; `statusShort()` deliberately does
not — staff/API only). The blade became the staff table's language: `COURSE · LECTURER · ORIGINAL SLOT
· CONFLICT REASON · REQUESTED SLOT · REQUEST STATUS`, five cards, `status-none` outline badge, sort
rank incl. `none:3`, `All Original Slot Weeks`, one pop-up whose `Go to My Timetable` jumps **and
flashes**. `ui-common.js`: `goToReplacementInTimetable(requestId, week, basePath)` (third arg optional;
staff call sites byte-identical) and `applyReplacementHighlight()` accepting both timetable paths.
Three defects the apply surfaced: a JS quote error in the jump button (caught by `node --check`),
`closeModalOutside` never exported on that page (pre-existing), and helper names colliding with
`TestCase::component()`.

**Then two user rulings on the cards the same day.** `c8eadeb`: the hours card moves to **second**,
right of Awaiting Arrangement. `da4de30`: the two cards are renamed **`Classes Awaiting
Arrangement`** / **`Hours Awaiting Arrangement`**, and the hours number now counts **only the rows
with no live request** — this **supersedes R8** ("every visible row, arranged or not"); measured on
`24DFT0001` it reads 5 classes / 6 hours where the old predicate read 8. `781e839`: the on-page
`<h1>` still said "Replacement History" — the `ui-page-header` include was the fourth place the
rename missed (see §0 "A page's name lives in FOUR places").

**The arrangement page stops jumping when the subject changes (lane `arrangement-week-keep-on-subject-switch`,
`b3fedcb`, archived `00af0b5`).** Eight rulings (D1–D8). Changing the **Subject** dropdown used to
`selectWeek(firstConflictRow.week)` unconditionally; now `decideSwitchWeek(row)` keeps the visible week
when it is **bookable** for the new subject (the date rule — not Sunday, not a holiday, not inside the
3-working-day cut-off — **AND** at least one free cell in the room the page settles on), otherwise moves
to the **earliest** bookable week, and when nothing is bookable it stays put silently. The auto-pin, the
slot box, the auto-tick, the venue sync, the booking-intent path and the pinned-arrival rule are
untouched by ruling. `window.__switchWeek` is a read-only receipt. `tests/arrangement-conflict-autoselect.spec.ts`
rewritten to **10/10** (twice, plus once on the committed tree, zero fixture residue — `time_slots`
back to 34677 available / 3963 occupied); red-check (restoring the jump) fails the "week stays" test.

**Also on 11-09.** `52298d6` the tooltip clears the hour header (raise the **cell**, `td.hour-cell:has(.event-block:hover)`
— a block's own z-index is confined to its cell) and a Today holiday badge stays red at rest, turning
inverse-surface only on hover. `5df521c` the COURSE column header explains itself once (the per-cell tip
was then removed by ruling; `UiLabels.courseTip()` has no live call site since — the helper is dead but
still in `ui-common.js`, with a comment that still claims both row builders use it). `aaf8163` the student table joined the staff
language (14 rulings — superseded hours later by the lane above). `c3b566d` the student filter's all-option
reads `All Requested Slots Weeks`. `fea3631` **`docs/demo-logins.md`** — 42 student handles (3 per cohort,
password `Tarumt@2026`), the 3 staff accounts, and the 2 students that carry real request data.
`ef0a1b4` the backlog sweep (36 finished lanes out of `.sdd/changes`). `2a9c785` a correction: two briefs
had been *staged* in `prompts/` rather than moved — `git add` does not relocate a file.

### 2026-11-08 — the student side gets a health check, and the calendar asks the campus clock

**Login access + the 403 page (`2c15d2c`).** The two login pages are guest-only again, and a wrong-role
user now gets a real 403 page that links home; the brief archived same day (`d0f3fe7`).

**The student side health check (S1–S11)** ran as an investigation and produced five fix commits:
`0369a11` the student timetable's *Today* is the real today; `cedc8ca` the calendar rules ask the
**campus clock** (`App\Support\LocalTime`, `Asia/Kuala_Lumpur`) instead of the UTC server clock;
`aca8f49` + `57adf18` a notification lands on its **own** week (and an old student notice still does);
`336528b` a replacement-history row tells the class's own truth and the new slot its whole run;
`c11f38b` the student history opens on **all weeks**; `63f9bd3` the top-bar logo keeps you inside the
app. Rulings and diary: `prompts/Student Side System Health Check.md` (archived `69cc247`).

**Module titles.** `65f1648` a module with no real name renders **`Subject AMSE2003`** (`Module::displayName()`
— one rule, one place, now used by every payload builder); `d6a9b84` four dataset titles from the user's
own wording; `f263692` `MPU-2212` → *Bahasa Kebangsaan A* (42/42 named, `dataset/import/course-titles.php`).
Three prompt archives the same day: `b4227b0` (composition + lab-access rulings).

### 2026-11-07 (night) — the arrangement cell names its reason; the tip moves above

Three follow-ups on one page, each grilled/decided by the user the same night.

**1. The hover label says WHY (SDD `arrangement-cell-state-labels`).** `Occupied` / `Pending` /
`Reserved` became `Venue Conflict`, `Cohorts Conflict`, `Your Conflict`, `Your & Cohorts Conflict`,
`Your Pending`, `Other Pending`. Answers that shaped it: **1A** the reason must be DATA (each busy
row gained a 7th tuple key `arms`, and `dedupeBusyRanges()` unions the arms of twins — the tooltip's
`🏛/👥/👤` tags still parse the holder text, the cell does not); **2A** a fourth red word for
"your own class only", which the brief omitted; **3A** a real `<span class="cell-state-label">`
(so `<br>` works) with the old `::after` overlay retired via `:has()`; **4A** 11 px, two lines,
clipped inside the cell — measured unclipped at 390/768/1024/1440 px. One resolver
`classifyState()`; `classifyCell()` unchanged for the ownership receipt.

**2. The legend (5B, then the user's own revision).** First eight rows; the user then kept five and
added **Others Conflict** (the previous row's wording) — six rows, the non-yours red family grouped,
the CELL still naming the reason.

**3. Two wording/placement asks.** The cell tip now sits ABOVE the cell (`data-tip-pos="left"` pin
removed — it used to lay a multi-line block over the day columns; the engine flips it below only on
the first row), and the tip's own-block tag reads `👤 Your conflict`, not `👤 Own conflict`.

Receipts: `arrangement-slot-ownership.spec.ts` **7/7** (six labels from crafted facts with real
tuple shapes; real hovers on an own pending / another's pending / an own-class cell asserting the
text, the box inside the cell, no clipping, `::after` = none, the tip ABOVE, the tag wording and an
untouched `data-tip`; a grid-wide sweep; the six-row legend), `ReplacementArrangementTest` 7-key
tuples + arm union. Full suite 423 tests: 420 green + the 3 Saturday-only fixtures. Commits
`358ad2a`, `e6e4866`, `4ecfe48`, `ecec7f0` (plus `a0b7790` for the earlier docs sync).

**Then the countdown's placement (user brief *UI Update — Urgency Label & Date Display*,
`c8b41be` + `2b25386`).** The label moved from beside the date to its OWN line — date / urgency /
time — in both My Replacements columns and in Request Approval's requested column; a landed row
keeps its inline `—`. The details pop-up brackets it (`9 Nov 2026 (1 Working Day Left)`), except an
overdue label, which follows an em dash (`6 Nov 2026 — Overdue (1 Working Day Ago)` — user answer
B, because its own words already carry brackets). One `detailCountdown()` helper serves both
pop-up tabs. The same two commits archived `arrangement-slot-ownership-legend` and
`lab-access-type-aware`. Receipts: `replacement-home-working-days.spec.ts` 8/8 (markup order +
pop-up shape), status-badges 13/13, PL/status-tooltips 15/15.

**Then the week-filter labels + the PL filter's date (brief *Week Filter Labels & Request Status*).**
The chips and the "all" options now name their date domain (`Original Slot Week:` /
`All Original Slot Weeks` on My Replacements, `Requested Slot Week:` /
`All Requested Slot Weeks` on the PL queue — they read "Original Class / Requested Replacement" until
the SLOT rename of 2026-11-10), the PL header reads **REQUEST STATUS**, and —
the real change — `filterData()` filters on `r.replacementDate` instead of `r.classDate`.
`week-filter-preserved` U1/U2 moved to the fixture's requested week (7) because of it; T5.RA-5 is
the new receipt (labels + header + the week-7/14 cross-check + clearing), and it resets the
fixture itself under the fail-closed guard because a previous spec may have decided the pending
request. 14/14 green across the two files.

**Then the detail pop-ups (user brief *Modal Tab Order & Class Type Display*, `7aa9ad7`).**
The **Proposed Replacement Schedule** tab is now LEFT-MOST and the tab both detail modals open on
(`DetailModal.render()` activates index 0, so the order *is* the default); the status tab stays last,
and a row with no proposal keeps Original first because that tab cannot be invented. The PL modal's
subtitle now prints the class TYPE in full words — `#28 · AMCS2093 — Operating Systems (Lecture)` —
from the row's own `classType` through `UiLabels.classType(…, 'long')`. Display-only, SDD waived.
Receipts: five specs **34/34** with `--workers=1`. Two pre-existing test-law repairs while
verifying: the reject card's joiner assertion still said `to` (law is `~`), and both reseed helpers
only cleared the FK trail for fixture ids 10/24/25/26 — a spec that created its own request made
`TestingFixtureSeeder` die on `audit_logs_replacement_request_id_foreign`; both now clear the whole
trail (isolated testing DB). Run the reseeding specs with `--workers=1`, never against the manual `:8030` data.

### 2026-11-07 (later) — working-day countdowns, the PL Urgency column removed, and a request that keeps its length

Four user reports, all shipped the same evening. Commits: `1ae378b`, `9ff4813`, `fa50a95`,
`8274ba0`, `efeea89`, `705637a`, `7c9cef6`, `c24d72d`, `602a585`.

**1. The class-date countdown now counts WORKING days** (`1ae378b`). The words, the bands
(red / amber 1–7 / **green 8+**, the far band was grey) and the placement are in §0 and
§2 #27. New `ui-common.js` helpers: `workingDayHolidayKeys()`, `workingDayKey()` (local
`Y-m-d`, never `toISOString`), `workingDayParse()`, `isWorkingDayDate()`,
`workingDaysBetween()`, `workingDaysDiff()`. **A typo caught in the brief:** it said
`urgency-med`; the page's real class is `urgency-mid` (the first amber render had no rule
at all — no colour, no nowrap). The new spec asserts both bands resolve, so a typo cannot
land silently again.

**2. Request Approval lost its Urgency column entirely** (`9ff4813`): badge, sort arm,
`All/Urgent/Normal` chips, the active-filter chip, row stripes, the phone-card line and the
compare-card badge are gone; the age pill stays (`5 days ago · 4+ days old`, no urgency
claim). Mistake caught and fixed in the same commit: the first cut also deleted
`compactCohort()`/`venueText()`, so the queue rendered no rows — both restored.

**3. Two countdown placements** (`fa50a95`, `efeea89`): the row pop-up carries the same
number as the table, and a PENDING request prints its make-up countdown beside the
requested date **on both pages** (`HtmlBuilder.replacementBlock()` gained an opt-in
`pendingCountdown`; only Request Approval passes it). The `.urgency-*` rules moved from
My Replacements' page `<style>` into `theme.css` and the band choice became the shared
`urgencyBandClass()`. **Caught while verifying:** that page's bridge never cleared
`MockData.mockNow`, so the countdown measured from the frozen demo anchor (Mon 5 Oct 2026)
and printed `24 Working Days Left` for a 6 Nov slot — the page now sets
`MockData.mockNow = null` like every other live page.

**4. A decided request keeps its real length** (`7c9cef6`, demo `c24d72d`, tip `602a585`).
Reported as "0.5 hours" on #24 and as the `col-requested` cell of #37. Cause and cure in §0
and §2 #29: the release actions delete the junction, the anchor is ONE 30-minute cell, so
the width is now stored on the request (`replacement_span`) and one reader
(`spanFromAnchor()`) owns the shape. **A test pinned the defect as a law**
(`MyReplacementsPayloadTest:403` asserted the 30-minute anchor for a rejected row) — it now
keeps that assertion only as the LEGACY null-width case, with a stored-width leg beside it.
`EnforcementEngineValidateSlotTest` had also been left stale by the 2026-11-07 type-aware
lab roster (three rows still expected AMCS1013/BMIT1173 `L` to book a lab) — fixed
separately in `705637a`, which is why the full suite is green again apart from the three
weekend-only fixtures.

**Suite after all of it:** 422 tests / 2352 assertions — 419 green + the 3 Saturday-only
errors (§5 item 9a). Demo DBs migrated + backfilled with a snapshot, row-count receipt and
log entry (13 rows on `fresh`; `demo` had none to fill).

### 2026-11-07 — date & time format: audit, then three shipped batches

- **The audit.** `docs/date-time-format-audit-2026-11-07.md` read every live render path (Blade,
  `ui-common.js`, the PHP feeders, the mail views) and listed 30 findings. The user approved them
  all, with three refinements: #19 (grid event block) uses `~`; #28 (week dropdown) approved; #30
  (API chip) deferred until `/api/v1` is unparked. Rulings: `~` is the range joiner (a hyphen is
  tolerated but no element mixes joiners); the en dash is not a joiner; day words are `Thu` in
  compact lines and `Thursday` in prose; the 24-hour grid axis and short notification tips stay.
- **Batch 1 (`10a51b0`)** — same-screen contradictions: Quick View proposed time 12-hour; the
  arrangement block cards; the approve/reject compare cards (shared `classBlock` /
  `replacementBlock`); the venue arrange question and booking subtitle.
- **Batch 2 (`5120a98`)** — joiner drift: My Replacements cancel confirm + mobile card, history
  `to` → `~`, arrangement error/toast/slot lines, grid event block, mobile history card,
  `NotificationTip` schedule lines. Four unlisted en-dash spots were swept in the same pass.
- **Batch 3 (`f420215`)** — padded/month-first dates: the shared week scaffold and
  `DateHelper.fmt/fmtShort` unpad (`5 Nov 2026`); day headers, week dropdown, mobile chip and
  lead-time note follow; semester chip becomes `21 Sep 2026 ~ 27 Dec 2026`; picker label/tip
  `Thu, 24 Dec 2026`; venue session rows `Mon, 9 Nov 2026`; the holiday skip compares the same
  unpadded shape.
- **Batch 4 (`1dc3edb`)** — out-of-page surfaces: the request card's Date line gains its comma
  (`5 Nov 2026, 2:00 PM`), and all three emails now carry the date + 12-hour range —
  `Mon, 9 Nov 2026, 1:30 PM ~ 3:30 PM` — through the shared
  `ReplacementRequestMapper::mailSchedule()/mailDate()/mailTime()`. The page payload keeps its ISO
  date + 24-hour span; `EmailQueueTest` pins the rendered shapes. **#30 (API chip) stays deferred**
  until `/api/v1` is unparked.
- **Also 2026-11-07 (`fe26b10`)**: the Request Approval urgency count now measures the **proposed
  replacement** date (`urgencyDate(r) = r.replacementDate || r.classDate`), not the original class;
  the header tip and `tests/status-tooltips-filter-chips.spec.ts` follow.
- Verification for the three batches was code-level (`node --check`, `view:cache`, Pint, PHPStan)
  plus `NotificationsBridgeTest` 36/36 (241 assertions) for batch 2; the user waived browser runs
  for date/time changes.

### 2026-11-06 — notification row tooltips, one vocabulary (grilled brief)

- The user's UI update *Organise Notification Row Hover Tooltips* came with a `grill-me`; eight
  decisions were asked and answered before any code (G1 students, G2 the `awaiting` reminder, G3 the
  proposer's own copy, G4=B one `Original Class Schedule:` label, G5 `approver_id IS NULL` = automatic,
  G6=A holiday-first reason, G7 the user's date/time/ID formats, (a) no `Conflict Reason:` line,
  (b) `Automatically Rejected by System`).
- Implemented as one pure composer (`NotificationTip`) + `ClassTypeLabel` (the PHP twin of the JS
  `UiLabels`): the island classifies the kind from the tile type + stored title and passes the maps it
  already loads. ONE new batch (`holidays`) — the one-`replacement_requests`-query receipt stays green.
- Eight Feature cases pin the exact lines per kind; the demo hover shows the real thing
  (`/tmp/opencode/notif-tip-hover.png`: the 6-line new-request tip).
- Two stale browser fixtures were fixed on the way (both red since the 2026-11-05 "panel opens on Active
  only" default): `notifications-island-testing-db.spec.ts` asserted inactive rows without switching that
  filter off. 6/6 green now. Full PHPUnit **394 / 2093**.

### 2026-11-06 — the Pending tooltip names the rule (UI update)

- User UI update *Pending Status Tooltip*: `.badge.status-pending`'s tip must spell out the rule, not
  only when it fires. Only the **lecturer** arm changed (`ReplacementRequestMapper::statusTip()`); line 1
  and the PL voice are byte-identical.
- New second line: *Auto-Reject Rule: If This Request Has Not Been Approved At Least 3 Working Hours
  (Friday deadline) Before The Proposed Class Start Time, It Will Be AUTOMATICALLY REJECTED.* The
  deadline is still the row's own `approvalDeadlineLabel()` value (proposed start − 3 working hours on
  the `WorkingHours` calendar, holidays included), so it stays dynamic per request.
- Emphasis rides capitalisation (`Auto-Reject Rule:`, `3 Working Hours`, `AUTOMATICALLY REJECTED`)
  because a `data-tip` is `textContent`; an assertion now forbids `<` in the string so nobody turns it
  into HTML. Badge class/colours/click behaviour untouched.
- Receipts: `AutoRejectPendingRequestsTest` pins both lines + the two emphasis substrings;
  `status-tooltips-filter-chips.spec.ts` (new `MRH_PENDING_TAIL_RE`) **9/9** on the demo DB; measured on
  the testing fixture (proposal Fri 6 Nov 14:00 → deadline `Fri, 6 Nov 2026, 11:00 AM`),
  screenshot `/tmp/opencode/pending-tip.png`.

### 2026-11-06 — a cancelled request stops advertising its dead proposal (user brief)

- Brief *Cancelled Pending Requests*: after the owner cancels, the occurrence must read **Need to
  Arrange** (badge), show `—` in REQUESTED REPLACEMENT, and lose the whole **Proposed Replacement
  Schedule** tab in the pop-up — without touching the record.
- Implemented as ONE normalisation in `ReplacementHome::buildRow()`: an owner-cancelled row's status
  becomes `null` and its replacement strings (plus `remarks`/`rejectionReason`) are blanked. The view
  already renders the no-request state for an absent date, so the badge class/tip/sentence, the dash,
  the missing tab, the visible Arrange button and the needs-action sort group all follow — **no view
  or JS change at all**.
- `cancelled` == "the owner cancelled it" because `CancelPendingRequest` is the only writer of that
  status and it is ownership-checked (FR 2.10). Untouched: the record, `decided_at`, the audit rows,
  the released slots, the notification; `requestId` stays on the payload for the row identity and the
  notification jump. Rejected/Approved/Completed/Pending rows are byte-identical.
- Tests moved with the law: `MyReplacementsPayloadTest` cases 5 + 9, `ReplacementHistoriesTest`,
  `SpanReservationTest` (its anchor-after-release leg is now re-pointed at a **rejected** row, where
  that fallback is still visible — the cancelled row no longer displays a slot at all).
- Receipts: `request-cancel-pending.spec.ts` green (real submit → cancel → badge/dash/modal/DB/audit
  asserted, fresh load agrees, span bookable again); `replacement-home-status-badges.spec.ts` **12/12**
  on the demo DB (new receipt 9); `week-filter-preserved` + `my-replacements-after-submit` +
  `arrangement-entry-guard` 13/13; full PHPUnit **386 / 2053**.
- Follow-up on the user's word ("remove that option"): the status filter's **Cancelled** option is
  gone (All / Need to Arrange / Pending / Approved / Rejected / Completed), and `restoreFilters()` maps
  a pre-removal saved `status: "cancelled"` onto `none`, so an old browser session neither blanks the
  dropdown nor shows an empty list. Receipt: badges spec receipt 10 (red without the mapping).
- Follow-up (user: "highlight that cancelled tr … reuse the Earliest Bookable and today-btn highlight
  function/js"): `flashRowPulse(el, tone)` gained an optional tone, and `flashCancelledRow()` pulses the
  just-cancelled row **and** its phone card in `bookable-flash--warning`
  (`@keyframes bookable-pulse-warning`, `--color-warning`, same 0.5 s × 3). One mechanic, two tones —
  the green arm is byte-identical. Receipt: `request-cancel-pending.spec.ts` captures the class through
  a MutationObserver and asserts the computed `bookable-pulse-warning / 0.5s / 3`.
- Also fixed while using it: `request-cancel-pending.spec.ts` had a strict-mode violation of its own
  (the shared irreversible line carries TWO `.warn-action` spans — CANCEL and UNDONE — so the UNDONE
  assertion needed `.last()`). That spec had been red since the CANCEL keyword landed.

### 2026-11-06 — the Week filter no longer blanks after a decision (bug report)

- Report: after **Approve/Reject** on `request-approval-ui` and **Cancel Pending** on `my-replacements`
  the Week dropdown went blank while its chip stayed, printing a bare `Week: `.
- **Cause, measured:** the week selects have NO server-side options — `populateWeekSelect()` builds them
  from `weekRanges`/`weekData`. A page component's round-trip morphs the DOM from the server's HTML
  (empty `<select>`), so the options were deleted and the value collapsed to `''`; the chip, rebuilt
  from the empty select, lost its label. Pre-fix receipt (non-writing round-trip, `approve` on an
  unknown id): `options 15 → 0`, `value '8' → ''`, chip `Week: ×`.
- **Fix (shared, no per-page code):** `withWeekLivewireGuard()` — `pageLivewire()` now returns a Proxy
  whose `call()` snapshots every registered select's value and restores options + value **before the
  caller's `.then()`** (that callback rebuilds the chips — a deferred `setTimeout` restore is too late,
  measured). A `Livewire.hook('commit')` net covers other round-trips. Attributes still morph, so
  `cohort-timetable`'s server-driven `disabled` is untouched; no `wire:ignore`.
- Receipt: `tests/week-filter-preserved.spec.ts` (guarded, writes on the testing DB; reseeds
  `TestingFixtureSeeder` per test, clearing `audit_logs`/`notifications` first because the FK blocks it):
  **3/3 green**, and **red before the fix** for U1/U2/U3 (U3 only went red once it also asserted the
  cancel's own toast — a silent no-op had been passing it).
- Neighbours re-run green: `my-replacements-after-submit` + `arrangement-entry-guard` (13 passed),
  `venue-book` **18/19** — `U2a-T3` fails **identically on the unmodified HEAD** (hover-tooltip race;
  pre-existing flake, logged in §5).

### 2026-11-06 — FR 2.17 gets a server leg (the grid stops being the only guard)

- The user's challenge was correct on the rule, wrong on the conflict: the grid already refuses a
  too-soon cell (**3 working days**, `leadTimeCutoff()`), and the auto-reject rule closes the *later*
  decay (a request legally made 3 working days out that the PL never decides). Different moments,
  different units, no overlap.
- But the notice lived **only in JavaScript**: `SubmitReplacementRequest` had no lead-time check, so
  a direct submit could create a Pending row for a slot hours away — the exact row the sweep would
  then have to clean up. Now the action refuses it: the slot's DATE must be at least the **3rd
  working day** after today (weekends + holidays skipped, the 3rd working day selectable, today
  never), failure **`slot_too_soon`**, mapped to the grid's own sentence.
- One clock: `WorkingHours::noticeCutoff()` / `isBookableOnDate()`. Measured with Playwright on the
  demo DB (read-only): the page prints *"Bookable from Thursday, 12 Nov 2026 onward"* and the
  server's cutoff is **2026-11-12**; all **98** semester days cross-checked JS vs server → **0
  mismatches**.
- Receipts: new `tests/Feature/LeadTimeGateTest` 6/6; full suite **382 / 2022**.
- Fixture consequence (kept honest, not hidden): `tests/TestCase::bookablePairs()` is now the shared
  helper, the two scanning suites scan bookable pairs only, `SpanReservationTest`'s fixture semester
  starts two weeks back, and the two fixed-calendar suites (`ReplacementArrangementTest`,
  `OccurrencePinTest`) pin `Carbon::setTestNow('2026-09-14')`.
- **The sibling hole was closed the same day** (`40ffb5f`): D2's holiday rule now lives in the action
  too (`holiday`), so a crafted submit can no longer land a makeup on a public holiday. Sunday needs
  no arm — the grid has no Sunday slot rows. Precedence: notice gate first, so a holiday inside the
  window answers `slot_too_soon`.

### 2026-11-06 — the auto-reject rule (one clock, two faces)

- **The rule.** A **Pending** request whose proposed start is within — or exactly — **3 working
  hours** is rejected automatically; further out it stays Pending; no other status is ever looked
  at. Working time = **Mon–Fri 08:30–17:30** on the TARUMT Sabah clock, with the semester's own
  `holidays` rows excluded — so Friday 16:30 → Monday 09:30 is **2 hours** (reject) while
  → Monday 11:00 is **3.5 hours** (stay). The brief's example table is the unit contract
  (`tests/Feature/WorkingHoursTest`, 7 cases).
- **`app/Support/WorkingHours.php`** — the one office-hours clock: `gapMinutes()` (working minutes
  between two instants; a weekend proposal starts counting at the next opening bell; a past start has
  0) and `subtractWorkingMinutes()` (the inverse, used for the deadline). Holidays resolve through
  `semesters.start_date` + the `holidays` week/day rows — no second holiday list.
- **`app/Actions/AutoRejectRequest.php`** — reuse of the cancel family's release (version-checked
  `pending→available`, junction DELETE) with the rejected stamp: `approver_id` stays **NULL**,
  audits ride the allowed `rejected` action with `details.auto = true` and the PROPOSER as `user_id`
  (`audit_logs.user_id` is NOT NULL; there is no system user). No mail — the feed derives the
  rejection from the status.
- **`app/Services/PendingRequestExpiry.php`** + `php artisan requests:auto-reject` (`--dry-run`),
  scheduled every five minutes. Deliberately **not** a write-on-read: a page render must never
  mutate the demo DB, so the sweep only ever runs from the command.
- **The badge tooltip** (both pages, one vocabulary): a pending row's `data-tip` gains a second line
  naming the deadline — `If the Programme Leader has not Approved this Request by Fri, 13 Nov 2026,
  10:00 AM, it will be Automatically Rejected.` (the PL reads *If you have not Approved it by …*).
  The deadline is computed from the row's OWN `replacementDate`/`replacementTime` strings, so it
  costs no second junction read (the merged page's constant-statements law still passes).
- **Trap worth keeping:** the brief said `<br>`, but `data-tip` is rendered with `textContent`
  (+ `pre-line` on a `\n`) — HTML would print literally. The tooltip is two newline-joined lines.
- Receipts: full suite **376 / 1998**; `status-tooltips-filter-chips.spec.ts` **9/9** on the demo DB
  (:8010, read-only — its pending receipts now pin the two-line SHAPE, a fixed date would rot);
  demo DB `--dry-run` → *Scanned 2 pending request(s); would auto-reject: none* (both demo requests
  sit 3.5 h out — the brief's own "remain Pending" row). Nothing written to any demo DB.

### 2026-11-05 — the notifications panel's two follow-ups

- **`3f4a440`** — the panel now opens on **actionable unread**: `activeOnly` defaults to `true`
  (`unreadOnly` already was), so decided/superseded/undone rows are one toggle away. 25 panel
  constructions in `NotificationsBridgeTest` that assert inactive-row markup now pass
  `activeOnly: false` explicitly instead of riding a default.
- Same commit: **every disabled control shows `cursor: not-allowed`** (one `theme.css` rule for
  `button/.btn/input/select/textarea:disabled`, still no `pointer-events: none` so tooltips
  survive). `#notifMarkAll` needed its own `:disabled` line — its long-standing **ID** rule
  (`cursor: pointer`) out-specifies any class. Measured, not assumed: computed cursor read
  `pointer` until the ID got the state.

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
- **Static prototype** (discussion instrument): `ui-prototype/replacement-merged-preview.html`
  — hand-built, so it loads `../public/css/theme.css` from this repo and needs no dev server.
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
- **The lab booking roster matches the timetable, per module AND session type**
  (2026-11-03 ruling, then corrected 2026-11-07 by SDD `lab-access-type-aware`;
  decision recorded in `docs/venue-restrictions.md` R3). The two flat arrays are
  ONE map, `LectureSessionCatalog::LAB_ACCESS` (`module => ['L'?, 'T'?]`);
  `b006_allowed_module_codes` is derived from its keys. A listed type may book a
  lab only when that type's own seeded session already sits in one — six
  modules lost lab access on their L, AMCS1013 keeps T (B006) and loses L.
  `TimetableSeedInvariantsTest::test_t8_…` fails, in BOTH directions over
  `(module, type)`, if a future import drifts from the roster.

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

### 2026-11-05 (notifications panel) — **`notif-inactive-hide-sender`** (the inline brief)

- An **inactive row** now carries its own **Hide** in `.nr-right` (the shelf's `Unhide` shape, the
  same `hideOne()` gate); active rows never do. `App\Support\TitleCase::of()` projects the stored
  sentence at render (minor words stay low; `ID`, `#28`, acronyms untouched) — 15 unit cases.
- **Sender line**: `sender_self` / `sender_line` computed per render from the R5 maps (proposer for
  `submitted`/`awaiting`, approver for decisions, the class lecturer for class rows — no extra
  query). The subtitle reads **`by Surayaini Binti Basri (5425)`** / **`by YOU`**, and the two
  self-owned kinds take **Your** in the title (*Your Scheduled Class Cancelled*, *Your Replacement
  Request Submitted*) — recognised by type **or** the stored sentence, because the emitters write
  both as `update`.
- **Zero unread** no longer reshapes the header: the pill reads **0** and Mark-all stays visible but
  `disabled`. The bell badge keeps its own rule (hidden at zero).
- Receipts: guarded `tests/notif-row-inactive-hide.spec.ts` (5 passed), `NotificationsBridgeTest`
  28/28 with the four-state sender contract rewritten, full suite **359 / 1914**. Trap worth
  remembering: several notification specs in ONE Playwright process can trip Fortify's 5/min login
  limiter — the new spec uses the setup's `storageState` instead of logging in.

### 2026-11-05 (venue slot validation + archive pass)

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
  inline `onclick=` in a shared partial must be exported on `window`** — but ONLY on a page whose
  script is closure-wrapped. **Check the wrapper first** (measured 2026-11-10): the student pages
  (`replacement-history`, `my-timetable`) wrap their whole script in
  `document.addEventListener('DOMContentLoaded', function () { … })`, so nothing inside is global
  and an inline handler calling `saveView()` threw a ReferenceError; `request-approval` and
  `replacement-home` declare their functions at the top level of a classic `<script>`, where
  `function foo() {}` IS a global — so their inline chip handlers work untouched (`typeof
  window.toggleHideCompleted === 'function'`, verified in the browser). A grep for `window.x =`
  alone proves nothing: look for the wrapper.

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
| 8 | **Receipts strategy** = isolated verified test-DB suite + hand-run demo seed SQL (never agent-run booking flows on the demo DB, and never refresh the protected `:8030` manual data). |
| 9 | **ONE feed on every page** (supersedes the category-aware caught-up hotfix — `notif-unified-feed` retired the per-page tabs 2026-10-29): all staff pages show the same recipient-wide feed, newest first, and the caught-up view is always the single line "You're all caught up". The pill/badge = the **actionable-unread** total (D5: `read_at` null AND not inactive), computed per render, never moved by the view filters. |
| 10 | **No bell on the replacement-arrangement page** = ACCEPTED BY DESIGN (BUG-04). |
| 11 | **`?week=` never moves the week selector** (ARRIVAL-GRID-NO-PIN) = WONTFIX (user decision). |
| 12 | **Demo story** (viva ~week of 7 Dec 2026, UNCONFIRMED, on this MSI machine): timetables + booking + cancel class + approve + bells + emails; auth = verbal only; students demo the "replacement class got updated". |
| 13 | **THE pageLivewire() LAW (the hotfix)**: page actions use `pageLivewire()`, NEVER `Livewire.first()` (the bell islands render first). |
| 14 | **A class that falls on a public holiday can NEVER be cancelled** (user-confirmed 2026-11-03 — the correct behaviour, not a gap). Rule **S4** rejects `day.holiday` in the shared guard `Cancellation.isCancellable()` (`public/js/ui-common.js` — grep the function name; the line numbers drift every lane), enforced at the button, the confirm path and the stale-button sweep. Browser proof: `tests/cancel-class.spec.ts` **S4** (the block's modal opens on the Deepavali in-lieu Monday but offers no cancel action). Do **not** "restore" the missing button; a holiday class is handled by arrangement, not cancellation. |
| 15 | **Cancel Class is FINAL** (user 2026-11-05: *"i give up all UNDONE function"*). No undo button, no undo callback, no server undo route — removing them was the fix, not a regression. Every final-decision confirm modal (Approve, Reject, Cancel Request, Cancel Class) shows *"This action cannot be UNDONE."* Reversible actions get nothing. |
| 16 | **Toast order**: a `?blocked=request-pending\|request-approved` arrival shows the ENTRY-GUARD message first, and any pending cancellation reminder is deferred (neither shown nor spent) to the next page load. The cancellation reminder is **one-shot** — one reminder per cancellation, never one per load. |
| 17 | **Stale notifications are marked inactive AT READ TIME**, never edited: `undone` (a request-less cancel notice whose `class_exceptions` row is gone) and `superseded` (a decision notice older than the newest request for the same class+week). Badge = D5 (actionable unread), so an inactive row leaves the badge but stays on the list. |
| 18 | **A slot is judged by the class the user picks, not by a guess** (user brief `Venue Timetable Slot Validation.md`, 2026-11-05). On `/venue-timetable-ui` the hover answers ONE question — *is there at least one clear hour?* (`MIN_BOOK_SLOTS = 2`; `cell-no-room` = "Not Enough Slot" when the next class starts inside that hour, `cell-no-fit` = past day end). It is **never** derived from the arrange pool (the old `minClassSlots` plumbing is deleted). The real duration is checked only AFTER a session is picked, in `confirmIntentSession()` → `#slotWarningOverlay` (span or day-end copy), which never navigates. The pop-up lists every session with `data-slots` / `data-fit` and pre-selects the first fitting row; no verdict is shown before the pick. |
| 19 | **The notification row speaks plainly** (inline brief, 2026-11-05): an inactive row can be hidden from the feed (Hide in `.nr-right`); every title reads **Title Case** at render (the stored sentence is never rewritten); the subtitle is the **sender** line — `by YOU` when the reader is the actor, else `by Name (staff_id)` — and a self-owned cancellation/submission says **Your** in the title; at zero unread the pill reads **0** and Mark-all stays visible but **disabled** (the bell badge still hides at zero). |
| 20 | **A pending request has a 3-working-hour window — and it is the SYSTEM that closes it** (user brief `Auto-Reject Pending Requests.md`, 2026-11-06). Working time = TARUMT Sabah hours, Mon–Fri 08:30–17:30, weekends and the semester's `holidays` excluded; the boundary itself rejects (`gap ≤ 180` minutes), anything further out stays Pending, and no other status is ever considered. **`App\Support\WorkingHours` is the only clock** — the deadline a tooltip promises and the moment the sweep compares are one computation, and neither may hardcode its own hours. The sweep is `php artisan requests:auto-reject` (scheduled 5 min, `--dry-run` safe) — **never** triggered from a page render (that would be a write-on-GET against the demo DB). An auto-rejected row keeps `approver_id = NULL` and is audited with `details.auto = true`, because no human decided. The Pending badge's `data-tip` always names the deadline (both pages, one vocabulary). |
| 21 | **The notifications panel opens on "Active only"** (user, 2026-11-05): `activeOnly` defaults ON beside `unreadOnly`, so the first view is actionable unread and the decided rows are one toggle away. A **disabled control is visible but inert** and shows `cursor: not-allowed` everywhere (one rule for `button`/`.btn`/`input`/`select`/`textarea`, no `pointer-events: none` so a disabled icon keeps its tooltip); an ID selector that sets `cursor: pointer` must restate `:disabled` itself. |
| 22 | **A rule the page enforces must also exist in the action** (2026-11-06). FR 2.17's 3-working-day notice was JavaScript-only; `SubmitReplacementRequest` now refuses `slot_too_soon` on the same `WorkingHours` calendar, and the failure copy reuses the grid's own sentence. When you add or touch a booking rule: action first, then the page, then one test — never the browser alone. |
| 23 | **A JS-owned control must survive a Livewire round-trip** (2026-11-06). Anything the server renders EMPTY but JavaScript fills (the week selects, `#filterChips`, table bodies) is deleted by the morph. The week selects are guarded centrally (`withWeekLivewireGuard()` around `pageLivewire().call()`; restore synchronously, never in a `setTimeout` — the caller's `.then()` reads the value first). Prefer that over `wire:ignore`, which would also freeze server-driven attributes. |
| 24 | **A cancelled request is displayed as "no active request"** (user brief 2026-11-06). On My Replacements an owner-cancelled occurrence shows **Need to Arrange**, an em dash in REQUESTED SLOT and no Proposed Replacement Schedule tab — one normalisation in `buildRow()` (status → null, replacement strings blanked), never three separate view patches. The RECORD is not rewritten: `cancelled`, `decided_at`, audit rows, released slots and the notification stay, and the history/notification surfaces still read *Cancelled by YOU*. `cancelled` means "the owner did it" because `CancelPendingRequest` is the only writer and it is ownership-checked. Other statuses are untouched. **Follow-up (`remove that option`):** the status filter no longer offers **Cancelled** — nothing on the page can carry that state — and a filter saved before the removal is mapped onto Need to Arrange when restored. **Follow-up (highlight):** the just-cancelled row pulses in the WARNING tone (`flashRowPulse(el, 'warning')` — the Earliest/Today mechanic, never a second animation; the phone card carries it too). |
| 25 | **The notification tooltip has ONE vocabulary** (user UI update 2026-11-06, after a grill): every live row is composed by `NotificationTip` — Subject / schedules / reasons / `Request ID`, labels omitted when their facts are missing, the reason holiday-first inside the Original line's parentheses, `Automatically Rejected by System` for a system rejection (`approver_id IS NULL`), students without the Staff/Requested-At lines, and the legacy `title — body` only for a kind with no layout. A `data-tip` stays TEXT. |
| 26 | **Dates and times speak one format** (user-approved 2026-11-07, `docs/date-time-format-audit-2026-11-07.md`; batches 1–3 shipped). Date `5 Nov 2026` (day first, no leading zero); time `2:00 PM`; range `2:00 PM ~ 4:00 PM` — `~` is the joiner, a hyphen is acceptable but one element never mixes joiners, and the en dash `–` is not a range joiner (it means "no data" / a break). Day words follow their container: `Thu` in compact lines, `Thursday` in prose and expanded modal rows. Keep the 24-hour grid hour axis (a scale) and the short notification tips (no year). New surface → use this standard; never re-pad a day or move the month first. |
| 27 | **A countdown counts WORKING days, and its bands are red / amber / GREEN** (user 2026-11-07, brief *Urgency, Countdown & Date/Time Consistency*). Words: `Today` · `1 Working Day Left` · `N Working Days Left` · `Overdue (N Working Day Ago)` — `Today` is the ONLY relative word (Yesterday/Tomorrow were tried and reverted). Past + today red, **1–7 amber, 8+ green** (the far band was grey). Working days = Mon–Fri minus the timetable's holiday rows; ONE holiday derivation (`workingDayHolidayKeys()`) feeds both the countdown and the cancel-class window. One band rule (`urgencyBandClass()`) + the `.urgency-*` rules in `theme.css`; a page never re-colours a countdown. Placement (amended 2026-11-07, same night): the countdown is its OWN line under the date (date / urgency / time, `<br>`-separated) in both columns of both pages, and it rides in BRACKETS in the details pop-up — except an overdue label, which follows an em dash (`6 Nov 2026 — Overdue (1 Working Day Ago)`). The class-date countdown lives on the Original Class row; the make-up countdown rides the requested date **only while pending** — approved/completed print `—` (inline in the table, `9 Nov 2026 —` in the pop-up). |
| 28 | **The PL queue has no Urgency notion at all** (user 2026-11-07). The column, its sort, the `All/Urgent/Normal` chips, the active-filter chip, the row stripes, the phone-card line and the compare-card badge are REMOVED — do not reintroduce them. The request-age pill stays and speaks only about waiting time (`5 days ago · 4+ days old`), never urgency. The lecturer page and the PL page count down to different dates **by design** (class date vs proposed slot); the answer to *the two pages disagree* was to delete the PL rule, not to align numbers. |
| 29 | **A request keeps its own length** (user 2026-11-07, requests #24 / #37 / #38). `replacement_requests.replacement_span` stores the block width in 30-minute cells at submit; `ReplacementRequestMapper::spanFromAnchor()` is the ONE place that turns a junction-less request into a range, and a null width keeps the legacy single-cell shape. The junction still wins when it exists. Never hard-code `count = 1` anywhere else — a second copy of that shape is exactly how the notification tip kept printing `11:00 AM ~ 11:30 AM` for a 2-hour rejection. Old rows are filled by `php artisan crs:backfill-replacement-span` (audit-derived; dry run by default). |
| 30 | **The arrangement cell names its reason; the tip sits above it (2026-11-07).** The hover label is one of `Your Pending` · `Other Pending` · `Venue Conflict` · `Cohorts Conflict` · `Your Conflict` · `Your & Cohorts Conflict` (`Occupied` = fallback only), decided by ONE resolver (`classifyState()`) from DATA: `mine` (ownership), the busy rows' 7th tuple key `arms` (`own`/`cohort`, unioned when twins merge) and the grid payload's `status 1` (the room). The words are a real span in the cell — never the CSS `::after` overlay — so a `<br>` is legal there (11 px, 2 lines, inside the cell). The LEGEND was six rows until 2026-11-11; it is now **four rows** (Available · Your Current Selection · Pending · Conflict) and has **no ownership-hint line** — see #41. The CELL still names the owner/reason; do not rename `Your Pending`/`Other Pending` back to `Pending (You)/(Others)`. The cell's tooltip is placed ABOVE the cell (the default engine mode; no `data-tip-pos`) and its own-block tag says `Your conflict`. |
| 31 | **The week filter names which week it means, and the two pages mean different weeks (2026-11-07).** My Replacements filters the **original slot** week (chip `Original Slot Week: …`, option `All Original Slot Weeks`); Request Approval filters the **requested slot** week (chip `Requested Slot Week: …`, option `All Requested Slot Weeks` — the SLOT wording since 2026-11-10), because that is the slot the PL is approving — a request whose class is in week 14 and whose proposal lands in week 7 belongs to week 7 on that queue. `isInWeek()` takes whichever date the page hands it; never swap those two. The PL queue's status column header reads **REQUEST STATUS**. `populateWeekSelect(sel, { includeAll: true, allLabel })` is the one place an "all" option is built. |
| 32 | **The student's page lists CLASSES, not requests — and it is called `Class Replacements` (2026-11-09, lane `student-class-replacements` R1–R8).** A row is a **conflicted class occurrence** (cancelled class OR public holiday) whether or not a request exists; identity is `"<sessionId>:<week>"` with a nullable `requestId`, because most rows have none and a request id would leave them unclickable. No live request (none / cancelled / rejected) reads **Awaiting Arrangement** and the page **never names a dead request**; the staff vocabulary serves pending/approved/completed. The week filter is the **class's own** week with the option `All Original Slot Weeks` (SLOT wording since 2026-11-10). The page is **read-only** with exactly one control (`Exclude COMPLETED Status`, decided by the make-up date only), so an unarranged class never becomes "past". Five cards, in this order: `Classes Awaiting Arrangement` · `Hours Awaiting Arrangement` · `Pending` · `Approved` · `Completed` — and the hours card counts **only the rows with no live request** (2026-11-09, `da4de30`: this **supersedes R8**'s "every visible row"; measured 5 classes / 6 hours on `24DFT0001` where the old predicate read 8). The single pop-up's `Go to My Timetable` jumps **and flashes**, guarded by `requestId`. |
| 33 | **Changing the subject must not move the week (2026-11-09, lane `arrangement-week-keep-on-subject-switch` D1–D8).** On `/replacement-arrangement` the Subject dropdown keeps the **visible** week when it is bookable for the new subject — bookable = the date rule (not Sunday, not a holiday, not inside the 3-working-day cut-off) **AND** at least one free cell in the room the page settles on (`weekBookableForSubject` → `firstBookableWeekForSubject` → `decideSwitchWeek`, receipts on `window.__switchWeek`) — else the **earliest** bookable week, and when nothing is bookable it stays put silently. A week that holds the subject's own clash still counts as bookable if it has a free cell. The auto-pin, the slot box and auto-tick, the venue sync, the booking-intent ordering and the pinned-arrival rule are NOT part of this and must not change with it. The decision runs **before** the venue settles, so it must read `firstConflictRow.venue` up front. |
| 34 | **A page's name lives in FOUR places (2026-11-09).** Tab title (`@section('title')`), nav label (`partials/ui-nav-bar`), address (`routes/web.php` — keep the old route alive as an alias so bookmarks and notification links do not 404) and the on-page `<h1>`, which comes from `partials/ui-page-header`'s own `title` argument — that fourth one is what a rename misses (measured the same day: three said "Class Replacements", the H1 still said "Replacement History"). Pin the include line in a test so they cannot drift apart. |
| 35 | **The COURSE column is explained once, on its header (2026-11-09).** `UiLabels.courseTipSubject` is the single home of *"Subject or Course affected by the conflict"*, shared by Request Approval, My Replacements and Class Replacements. **No per-cell hover text** on a course cell (added and removed the same day by the user); `UiLabels.courseTip()` is DEAD: it still sits in `ui-common.js` (and its comment still claims both row builders call it) with no live call site — a cleanup waiting for the user's word, so do not bring a cell tip back by calling it. The cell shows code, full name and class type; a class type letter never replaces the name. |
| 36 | **The login pages are guest-only, and a wrong role gets a real 403 (2026-11-08, `2c15d2c`).** `/login/staff` and `/login/student` redirect an already-authenticated user to their own default page; a signed-in user who opens a page their role may not use gets the 403 page (which links home), not a login form. Tests: `LoginAccessControlTest`, `AccessDeniedPageTest`, `RouteGateMatrixTest`. |
| 37 | **Three permanent removals/keeps (user, 2026-11-10 — code cleanup).** (a) The **Logout Modal is gone for good**: `public/js/logout-modal.js`, `partials/ui-logout-modal.blade.php`, its `@include`, its `<script>` tag and the two commented `showLogoutModal()` handlers are deleted; both logout buttons submit `#logout-form` directly. (b) The **venue "Add to Favourite" feature is gone for good**, including the star that used to appear beside a room in the shared `VenueDropdown` (`isFav` branch in `_createRoomItem`) — `getFavourites`/`toggleFavourite`/`refreshFavourites`/`updateFavStar`, the `venueFavourites` key and the Favourites group arm all went with it. **Recent venues are untouched.** (c) The **`.cell-content.cell-occupied` hover tooltip is KEPT permanently** — the old "remove it before the viva" request is **REVOKED**; `theme.css` and the arrangement blade both carry a ⛔ note, and `CodeCleanupRemovalsTest` pins all three. Never restore (a) or (b); never delete (c). |

| 38 | **One name, one icon, one shape — the 2026-11-10 UI consistency batch.** (a) **One action, one icon**: the Arrange Replacement action wears `#btnReplaceNow`'s swap glyph everywhere it appears (the ACTION column's primary button, Replacement Details' `#qvArrangeBtn`, the modal button) — 15 px, stroke 2, `aria-hidden`; the old circular "rotate" arrow is banned for that action. (b) **A modal names the subject ONCE and the room ONCE**: the subtitle owns the subject (there is no `Subject` row) and the `Venue` row owns the room *with its seats* (`B002 — Tutorial Room (35 seats)`; `Room Name`/`Capacity` rows are gone, and only the venue page hands `venueSeats` in). Wording comes from ONE helper — `ModalFacts` (`dayDate`, `timeDuration`, `roomName(code,name)`, `venue(code,name,seats)`) — never a copy per builder. (c) **One footer button size**: a fixed **34 px** height inside `.modal-footer` (`min-height` is wrong: text-only buttons land at 35 px because of the border), size only — no re-order, no re-colour, no re-wording. (d) **One Close skin**: `.btn-close-modal` is `.btn-outline`'s twin (transparent, `--color-outline` hairline, `--color-on-surface`) and keeps its class name, which is the JS hook the frozen specs select. (e) **One subtitle separator: the em dash** (`\u2014`), spelled as the escape inside Blade JS — never the middle dot, never a literal em dash beside an escape. (f) **A column names the slot, a tab names the schedule**: tables say `ORIGINAL SLOT` / `REQUESTED SLOT` (with no duration text under the class line), pop-up tabs + section headings + `NotificationTip` say `Original Slot Schedule` / `Requested Slot Schedule`; `Cancelled Class Schedule` is correct because it is about a class being cancelled. A tip is built on read, so a wording change needs no data migration. (g) **A slot badge names the real reason**: the arrangement page's slot picker prints `Public Holiday` when the day carries `weekData[week].days[day].holiday` (the same fact the grid paints), `Cancelled` when the class is cancelled, and `Conflict` only for a genuine non-holiday clash — never one flat label for all three. |
| 39 | **Cancel this Class? — the reason block (2026-11-10, brief `prompts/cancel-class-reason-block.md`).** Eight reasons, **two per row** (one per row under 520 px), and NO instruction line above them — the heading's red required asterisk plus the disabled Yes button are the whole cue. The irreversible warning is the **footer's** short `CANNOT UNDONE`: 11 px, warning colour, right-aligned, directly above Yes, visible **only while Yes is armed** (`_refreshGate()` owns it, `_swapState()` hides the wrapper so the success state can never show it). `partials/ui-irreversible-line` keeps its long sentence for Approve / Reject / Cancel Request via an **opt-in `compact` arm** — default byte-identical. `Other` stays the **eighth radio inside the grid** and reveals a labelled block BELOW the grid (`Other reason *` + the full-width `.modal-textarea`); the wrapper owns the visibility, so the typed text survives switching reasons. A blank `Other` is **silent** (no "Please describe…" sentence) but still refuses — the `#cancelReasonHint` paragraph stays because it carries the real refusals (window / no longer cancellable / server error). |
| 40 | **The venue toolbar's two facts (2026-11-10).** A room's **description** is an info-icon chip — `.badge.badge-past` + the info glyph, the words in `data-tip`, `Room description: …` in `aria-label` — inside the same `.venue-side-chips` group as the capacity chip, never a plain grey line (measured identical to the arrangement page's `#arrVenueDescription`: 32×20 desktop, 40×40 phone). The week cluster (arrows + week picker + Today + Earliest) is **centred** in the bar: a 5-column grid whose equal `minmax(0,1fr)` side columns put it exactly on the centre line (offset 0 px from 900 px up); below 900 px the bar keeps the wrapping flex row, because a ~600 px cluster cannot be centred on a phone. The arrangement page's "Showing venues that fit N students" note is REMOVED for good — the venue FILTER it narrated stays. |
| 41 | **The arrangement grid has no ownership ring (2026-11-11).** The 3px border is gone from `.cell-occupied` and `.cell-pending`; colour alone says “cannot book”. Ownership is only in the cell's hover words (`Your Pending` / `Other Pending`, `Your Conflict` / `Venue Conflict` / `Cohorts Conflict` / `Your & Cohorts Conflict`). The arrangement legend is **four rows** (Available · Your Current Selection · Pending · Conflict) and has **no ownership-hint line**. Venue and Cohort keep the 0.5px hairline and their own legend wording. Do not reintroduce the six-row split or a “thick border = yours” legend on `/replacement-arrangement`. |
| 42 | **Conflict badges name the kind; the popup prints the full detail (2026-11-11).** Tables and phone cards show only **`Public Holiday`** or **`Class Cancelled`** and carry the detail in the badge hover. The details popup prints **`Public Holiday — <holiday name>`** / **`Class Cancelled — <reason>`** in full, with no hover. The student page gets the holiday name but no cancellation-reason tip (the lecturer's reason stays private). Receipt: `SlotBorderAndReasonBadgeTest`. |
| 43 | **Every summary card states its formula; the timetable cards split finished + pending (2026-11-11).** All live pages use formula-first descriptions; `+N` is explained only where it prints. On My Timetable and Student My Timetable, **Total Classes** and **Teaching Hours** show `finished +pending` (`9+1`, `12+1`) through the shared `{ splitPending: true }` path; Cohort/Venue already used that shape. `/replacement-arrangement`'s summary bar is commented out, so its cards are not visible. |
| 44 | **The student Class Replacements popup is the staff Replacement Details popup (2026-11-11/12).** Title **Replacement Details**, four tabs (Requested Slot Schedule · Original Slot Schedule · Class Info · Request Status), static **Close LEFT / Go to My Timetable RIGHT** footer, conflict strip above the tabs, `Class ID` / `Request ID` naming. One details width: **680px**; `.modal-tabs` wraps below **520px**. Checklist B5/B6 and B9 folded in; B8/B10 stayed out. |
| 45 | **Cancel this Class? can cancel the whole day (2026-11-11/12).** The optional block ticks every class the lecturer teaches that day; unticked/folded by default; select-all with half-tick; **Yes, Cancel N Classes**. One transaction cancels the set, closes pending/approved requests on those classes and releases their slots; the make-up block is a note, never a tickable row. The single-class path is unchanged. Receipt: `CancelClassBatchTest`, `MyDayClassesTest`, `tests/cancel-whole-day.spec.ts`. |
| 46 | **The arrangement page owns a phone day view (2026-11-12).** At phone widths `/replacement-arrangement` uses one day at a time, a compact weekday/date picker, the shared bottom-sheet frame for popups, and a fixed action footer; desktop keeps the grid. The mobile top bar is full-bleed `--color-primary-container`, with a 44px text-less Back pill and theme toggle. Receipt: `arrangement-mobile.spec.ts` 51 passed / 3 skipped at 390px after Round 2 (47 at this lane's own apply). |
| 47 | **The auto-reject checker runs automatically from the user's crontab, pinned to the testing DB (2026-11-12).** `crontab -l` runs `php artisan schedule:run` every minute with `DB_DATABASE=class_replacement_testing`, and `requests:auto-reject` fires every 5 minutes; cronie starts at boot and appends to `storage/logs/scheduler.log`. **Since 2026-11-14 that database holds the user's protected manual testing work**, so the sweep can reject a pending request they are testing by hand. Do not stop, alter or re-point the cron line without explicit permission, and never let it touch a demo DB. The first unattended run had rejected demo request #35; it was restored by hand the same evening (`docs/demo-db-data-changes.md`). |
| 48 | **A cancelled original leaves the timetable only for its OWN approved replacement (2026-11-14, `46fd6f7`).** Suppression keys on the exact source pair `(semester, class session, source week)` in the existing own-approved map, before counts, dedupe and layout. Pending, rejected, withdrawn/cancelled, auto-rejected and request-less occurrences keep the cancelled block, and another occurrence's approval never hides it. Landing week/day/room/overlap is irrelevant. Stored exceptions, reasons, audits, requests, junctions and slots are untouched; the approved landing keeps its true full span. All four timetable pages share the rule (personal, cohort and student through `ResolvesTimetableTimeline`; venue also filters its cancelled-exception backfill). Receipts: `tests/Feature/CancelledSlotTimelineTest.php` lifecycle/geometry/other-source matrices, `/tmp/crs-approved-suppression/`. |
| 49 | **Arrangement desktop top zone = three setup cards + conditional Summary (2026-11-12, `d2d91ae`).** Conflict Schedule · Class Details · Replacement Schedule, plus the existing `#selSummary` only while a slot is selected. Grid: `<=1100px` one column; `1101–1319px` setup `8fr 5fr 7fr` with the Summary on row 2; `>=1320px` `8fr 5fr 8fr + 280px`. Conflict and Class Details fold to independent 56px vertical rails; the rail hides the Conflict two-line summary (that summary stays for `<=1100`). Conflict card stacks Course over Slot; the Venue trigger fills its row; the week select keeps `background-color` (never `background`). Mobile gets exactly one relocation entry (`.ra-class-details` after `#subjectInfo`) and is otherwise untouched. Do not create a second Summary, do not set `hidden` on `#selSummary`, and do not add a second mobile relocation entry. |
| 50 | **Cancel Class on an approved replacement cancels the REQUEST, not the original occurrence (2026-11-14, `b480a95`).** The modal sends the exact request ID plus the shown landing week/day, keeps the source session/week for history and Arrange Now, and never re-cancels the already-cancelled source. Single and mixed day selections share the batch, complete mutation-lock union and strict full-span release; every selected target must resolve or the whole decision refuses, and a late failure rolls back everything. Success list/count come from saved server receipts — never ticks plus an assumed primary class — and Approved cancellation writes no recurring client ledger. Request-linked notices state the actual landing span and never the original class's private reason. Receipts: `.sdd/changes/approved-replacement-cancellation/verify-report.md`, `/tmp/crs-approved-cancel/`. |

---

## 3. Safety laws, traps, and ways of working

**Database**
- Never `migrate:fresh` / `migrate:refresh` / `db:wipe` / reseed a demo DB. Any demo
  write is **user-gated per write**, snapshot-first (`pg_dump -Fc`, `pg_restore --list`
  must pass, dumps in `backups/`), and logged in `docs/demo-db-data-changes.md`.
- **⛔ `--env=testing`**: no `.env.testing` exists, so it silently boots the *demo* DB.
  Select the testing database only with `DB_DATABASE=class_replacement_testing`.
- **`class_replacement_testing` is protected manual data (2026-11-14)**, not a disposable
  database: never `migrate:fresh`/`migrate:refresh`/`db:wipe`, reseed, use `RefreshDatabase`
  or run writing browser fixtures there without explicit per-task permission. Refresh-style
  backend tests use a separate verified PostgreSQL instance with the same literal name; force
  and query host/socket, port, database and `data_directory` before any write, abort on mismatch.
- **Schema migrations on a demo DB are additive only** — add a nullable column or an
  index; never `change`/`drop`/data statements. Snapshot → rehearse on the testing DB →
  print the target → plain `migrate` → row-count receipt + log.
- **`.auth/*.json` states expire with the session — around 120 minutes (2026-11-14).** A browser
  probe that suddenly redirects to `/login/student` is a stale storage state, not a broken page;
  re-create it (`npx playwright test tests/auth.setup.ts` for the three staff identities, or one
  UI login for a student) before concluding anything about the code. The Fortify limiter is
  5 logins/min **per identity**, so refresh once and reuse.
- **`class_replacement_testing` carries two red fixtures from earlier runs (2026-11-14)**:
  `cancelled-slot-layout` S21 expects 6 `.cell-available` and finds 8, and `ui-regression` TC-7.2
  sees card sums of 10 against 9 rows. Neither is a code defect — clean the fixture state
  (`docs/cancelled-slot-reuse-cleanup.md`) before a demo or a "full suite green" claim.
- **Never stage `docs/demo-db-data-changes.md`** — it is another session's write log,
  unless that log *is* the task.

**Display & data laws (2026-11-07)**
- **A live page must set `MockData.mockNow = null`.** `mock-data.js` pins the demo clock to
  Mon 5 Oct 2026 for the frozen templates; a live page that forgets this line measures every
  date from October. The countdown bug that printed `24 Working Days Left` for a 6 Nov slot
  was exactly that one missing line on `/request-approval-ui`.
- **An explicit `get([...])` column list silently returns NULL for the column it omits.**
  `ReplacementHome`'s request query listed its columns by hand; after `replacement_span`
  landed it was not listed, so the page kept reading the anchor. When a model gains a
  display column, grep for the queries that enumerate columns.
- **A hover label that must wrap cannot be a CSS `::after`.** `content:` holds one line and no
  `<br>`, so the arrangement cell's state words are a real `<span class="cell-state-label">` and
  `:has()` retires the old overlay exactly where the span exists. A 40 px cell fits two lines at
  11 px; a longer string clips INSIDE the cell (never outside it) — verified at 390/768/1024/1440 px.
- **One reader per fact.** A range, a label, a tip or a countdown is computed in ONE place
  (`ReplacementRequestMapper::spanFromAnchor()`, `statusTip()`, `NotificationTip`,
  `urgencyBandClass()`, `workingDayHolidayKeys()`, `classifyState()`). A local copy of the same
  shape is how `NavNotifications::$anchorSpan` kept printing 30-minute rejections after the fix
  shipped — and how a cell label would drift from the tooltip's why-tags.
- **A test can pin a defect as a law.** `MyReplacementsPayloadTest:403` asserted the
  30-minute anchor for a rejected row, i.e. it protected the bug. When a fix contradicts a
  green assertion, read the assertion's intent before believing it.

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
  `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test <spec>`.
  **The guard still names `:8030`, but that lane now holds manual data**, so a writing run needs
  explicit permission and an isolated verified testing instance — never `migrate:fresh --seed`
  on the manual database. The guard checks the mode/base/allow flags, **not** which PostgreSQL
  cluster backs `:8030`; verify host/port/database separately first. Never loosen the guard.
- **Inline `onclick=` + a page script inside a `DOMContentLoaded` closure** = the markup cannot see
  the function unless it is exported (`window.closeSlotWarning = closeSlotWarning;`). The symptom is
  a modal that opens and then throws *"… is not defined"* when you close it — that is what the venue
  slot warning did for one round (2026-11-05). Check the `window.` export block at the end of the
  page's script before blaming the markup.
- **Browser fixtures rot with the clock.** `venue-cross-week-handoff` S1 pinned *Tue 03 Nov 2026*;
  once that date passed, the whole week rendered read-only (`cell-too-soon`) and the spec could not
  even click. Date-independent fixtures pick the LAST semester week instead (`913419e`). When a
  spec fails on a click that used to work, check the calendar before checking the code.
- **`data-tip` is TEXT, not HTML.** `initDataTipTooltips()` (ui-common.js) writes `textContent` and
  only adds `pre-line` when the string contains a real newline — so a brief that says `<br>` must be
  rendered as `"line one\nline two"`, or the tag prints literally (auto-reject deadline, 2026-11-06).
  Keep tooltip copy in ONE server-side source (`ReplacementRequestMapper::statusTip()`), never a
  page-local copy.
- **Per-row additions must not add a junction read.** The merged page pins "the junction is read
  exactly once per render, whatever the request count" (`MyReplacementsPayloadTest` case 8). A new
  per-row value rides data that is already resolved (the auto-reject deadline uses the row's own
  `replacementDate`/`replacementTime` strings), never a fresh `span()` call inside `buildRow()`.
- **A date-dependent gate makes old fixtures illegal.** FR 2.17's server leg (2026-11-06) broke five
  suites at once because their fixtures submitted to weeks that are now inside/behind the notice
  window. The cure is `tests/TestCase::bookablePairs()` for scanning fixtures (date-derived, cannot
  rot), a `now()`-relative semester for self-contained fixtures, and `Carbon::setTestNow()` for suites
  whose weeks are fixed — never a widened gate. A new calendar rule means auditing every fixture that
  submits.
- **⛔ Sunday (day 6) does not exist in the timetable.** `class_sessions` and `time_slots` carry a
  `day_of_week BETWEEN 0 AND 5` CHECK, so a fixture that walks `today()->addDay()` or
  `generate_series(0, 6)` breaks on the weekend. Three PHPUnit tests did fail on a Saturday only;
  they were repaired with local `Carbon::setTestNow()` weekday clocks and legitimate `0..5` day
  guards (`0e6bc1a`), and two Playwright fixtures hit the same CHECK on 2026-11-09. Cure a new
  fixture the same way: freeze a weekday, use `0..5`, or read the calendar; never widen the CHECK.
- **Fortify's login limiter is 5/min.** Multi-context specs plus a few manual logins in the same
  minute produce a login that "does nothing" (no redirect). Wait a minute before concluding a spec
  is broken.

**Runtime**
- **No queue worker is running.** Queued mailables pile up in `jobs`. The dev repo is
  on the `log` mailer (harmless); the viva repo is on `smtp` and holds **27 stale jobs**
  that must be purged (gated write) before the first worker run. `failed_jobs` was 0.
- **A scheduler runner IS running (2026-11-12).** The user's crontab runs
  `php artisan schedule:run` every minute with `DB_DATABASE=class_replacement_testing`, so
  `requests:auto-reject` fires every five minutes; cronie starts at boot. **Since 2026-11-14
  that database holds the user's protected manual testing data**, so the sweep can reject a
  pending request they are testing by hand. Do not stop, alter or re-point the cron line
  without explicit permission, and never point it at a demo DB. Request #35's old manual
  decision is history. Background: `prompts/pi-prompt-auto-reject-scheduler.md`.
- After any Blade/PHP edit: `scripts/dev-serve.sh 8000` (never combine `pkill` and the
  serve start in one shell string). The `:8001` twin restarts separately:
  `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`.
- The dev `.env` Gmail App Password was exposed in a chat paste on 2026-11-03; the user
  declined rotation. Rotate if they ever ask.

**Livewire + JS**
- A page component's re-render morphs the DOM from the server's HTML: JS-filled containers
  (`#weekFilter` options, `#filterChips`, `#tableBody`) come back EMPTY unless guarded. The week
  selects are snapshot+restored around `pageLivewire().call()` (2026-11-06). If a restore has to land
  before a caller's `.then()`, do it inside the wrapper — `setTimeout` runs after the `.then()`.
- Spec caution: a test that only asserts "nothing broke" can pass because the action never ran. Assert
  the action's own receipt (toast, row left/status flip) too — that is how the Cancel case was caught.

**Receipts and honesty**
- No claim without a receipt. Fresh in-flow login (staff `input[name="login_id"]`),
  screenshots + DOM snippets. Distinguish CONFIRMED from SUSPECTED.
- Playwright gotchas: run `.cjs` helpers from the repo cwd (`package.json` is
  `"type": "module"`); avoid `ctx` name collisions; `:visible` is not a CSS selector;
  add `?_=` cache-busters. Two more that cost time on 2026-11-10: a leftover
  `.modal-overlay.show` (e.g. `#confirmModal`) keeps intercepting hover/click, so strip it
  (`document.querySelectorAll('.modal-overlay.show').forEach(o => o.classList.remove('show'))`)
  or use `force: true` / `dispatchEvent`; and `#loginBtn` stays `disabled` until the form
  validates, so `waitForFunction(() => !document.getElementById('loginBtn').disabled)` before
  clicking it.
- A full-suite number is **PHPUnit only** (last recorded **721/721 PASS, 6,090 assertions**,
  2026-11-14; the former stale `ReplacementHistoriesTest` case was repaired by `0e6bc1a`). The
  data-dependent specs (request-approval, warning-keywords) must be re-run after any data change.

**CSS and layout traps (both bit on 2026-11-10)**
- **Never put `*/` inside an existing CSS comment.** It closes the block early and the rules after
  it stop applying — silently. `tests/Feature/ThemeCssIntegrityTest.php` guards the whole of
  `theme.css`; run it after any comment edit (`3d54a78` was exactly this mistake).
- **A global `position: relative` on a grid cell collapses it.** `.cell-content` is
  `position: absolute; inset: 0` on desktop — a page-wide `position: relative` unhooks that fill and
  every cell drops to height 0. The one sanctioned exception is scoped inside `theme.css`'s
  `@media (max-width: 520px)` block (`:has(> .cell-state-label)`, ~`:2868`); ask "does this need to be
  global?" before writing one anywhere else.

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
| Viva runbook, queue/mail | `docs/viva-demo-runbook.md`, `docs/queue-and-emails.md` · **what to code next** → `docs/coding-todo.md` |
| **The lanes just applied / active** | See the §0 **SDD lanes** row for the live list: **11 active lanes and 150 archived**, measured 2026-11-14. It includes `cancelled-slot-reuse` (revision-2 refinement complete, whole-feature Verify open), `student-history-test-contract` (verified, archive-ready), the applied/in-flight UI lanes, the frozen no-code plans and the one explore-only lane. Archive only on the user's word; never stage another lane's untracked files. |
| **The student's Class Replacements page** | `app/Livewire/ReplacementHistory.php` (`occurrenceRow()`, the 8-read walk, `$live`), `resources/views/livewire/replacement-history.blade.php` (six columns, five cards, `status-none`, `Exclude COMPLETED Status`, `All Original Slot Weeks`), `ReplacementRequestMapper::statusLabel(?string $status, ?string $date, string $audience)` / `statusVocabulary()` with the `'student'` arm; receipts `ReplacementHistoryRowTest`, `StudentReplacementHistoryParityTest`, `ReplacementMapperStatusLabelTest` |
| **The week that stays when the subject changes** | `resources/views/livewire/replacement-arrangement.blade.php` — `weekBookableForSubject()`, `firstBookableWeekForSubject()`, `decideSwitchWeek()` and the read-only receipt `window.__switchWeek`; receipt `tests/arrangement-conflict-autoselect.spec.ts` (10 tests) |
| **Demo logins** | `docs/demo-logins.md` — 42 student handles (3 per cohort, password `Tarumt@2026`, `/login/student`), the 3 staff accounts (5425, 5770, 4363), and the two students that carry real request data (`24DFT0001` pending #28; `24RSD0009` approved #27 + cancelled #25) |
| **The venue-page slot rules** | `resources/views/livewire/venue-timetable.blade.php` (`MIN_BOOK_SLOTS`, `spanBlocked()`, `nextEventStartAfter()`, `refreshSessions()`, `confirmIntentSession()`, `showSlotWarning()`), `resources/views/partials/ui-intent-session-modal.blade.php` (`#slotWarningOverlay`), `app/Livewire/VenueTimetable.php` (`bookableCandidates()`, `intentSessionOptions()`) |
| **The week-select guard** | `public/js/ui-common.js` — `snapshotWeekSelects()`, `restoreWeekSelects()`, `withWeekLivewireGuard()` (used by `pageLivewire()`), the `commit`-hook net; receipt `tests/week-filter-preserved.spec.ts` |
| **Which date a page's week filter means** | `replacement-home.blade.php` (`populateWeekSelect(…, allLabel: 'All Original Slot Weeks')`, chip `Original Slot Week:`, filters `classDate`); `request-approval.blade.php` (option `All Requested Slot Weeks`, chip `Requested Slot Week:`, filters `replacementDate`); the ONE "all" builder is `populateWeekSelect()` in `ui-common.js`; receipt `status-tooltips-filter-chips.spec.ts` T5.RA-5 |
| **The 2026-11-10 → 12 UI laws** | wording goes through `ModalFacts` (`public/js/ui-common.js`) and the modal partials under `resources/views/partials/`; the reason block + footer `CANNOT UNDONE` live in `ui-cancel-class-modal.blade.php` + `ui-irreversible-line.blade.php` (opt-in `compact`), the too-soon precedence in `replacement-arrangement.blade.php`'s `cellRender`; the whole-day cancel is `app/Concerns/CancelsClasses.php` (`myDayClasses()`, `cancelClassDay()`) + `CancelClassBatch`; the mobile day view is `partials/ui-arrangement-mobile.blade.php` + `public/js/arrangement-mobile.js`; the student popup is `replacement-history.blade.php`; the four-row arrangement legend is in `replacement-arrangement.blade.php`; receipts `ModalConsistencyTest`, `CancelClassBatchTest`, `MyDayClassesTest`, `SlotBorderAndReasonBadgeTest`, `arrangement-mobile.spec.ts`; the open B-list is `docs/Modal inconsistent Report checklist.md` (untracked, the user's doc) |
| **A request's stored length (span)** | `replacement_requests.replacement_span` (migration `2026_11_07_000001_…`), `ReplacementRequestMapper::spanFromAnchor($anchor, $span)` (the ONLY span builder), `app/Console/Commands/BackfillReplacementSpan.php` (`crs:backfill-replacement-span`); receipts `tests/Feature/ReplacementSpanTest.php` 8/8 + `MyReplacementsPayloadTest` case 5 |
| **The auto-reject rule** | `app/Support/WorkingHours.php` (the office-hours clock: `gapMinutes()`, `subtractWorkingMinutes()`, `holidays()`, `MIN_GAP_MINUTES = 180`), `app/Actions/AutoRejectRequest.php` (the release + the system stamp, `approver_id` NULL), `app/Services/PendingRequestExpiry.php` (the sweep), `app/Console/Commands/AutoRejectPendingRequests.php` (`requests:auto-reject --dry-run`, scheduled in `routes/console.php`); the deadline rides `ReplacementRequestMapper::approvalDeadlineLabel()` + `statusTip()`. **Trigger 2026-11-14:** the user's crontab runs `schedule:run` every minute with `DB_DATABASE=class_replacement_testing`, so the sweep fires every five minutes; that DB is now protected manual data, so do not alter the cron line without permission and never point it at a demo DB. |
| FR 2.17 / the notice gate | `app/Actions/SubmitReplacementRequest.php` (the `slot_too_soon` and `holiday` gates, both right after the anchor lookup), `app/Support/WorkingHours.php` (`noticeCutoff()`, `isBookableOnDate()`), `app/Livewire/ReplacementArrangement.php` (`conflictText` arm), `public/js/ui-common.js` (`leadTimeCutoff`, `isSlotTooSoon`, `renderLeadTimeNote`), `tests/Feature/SubmitGatesTest.php` (renamed from `LeadTimeGateTest` when the holiday leg joined it: 6 notice + 4 holiday cases) |
| Cancel Class + the inbox rules | `app/Livewire/NavNotifications.php` (`exceptionMap()`, the two read-time classifiers), `resources/views/partials/notif-row.blade.php` (`$rowTip`), `public/js/ui-common.js` (`showCancelToast`, `needsLoadToast`, `markLoadToastShown`, `CancelClassModal`) |
| Date & time format (audit + batch log) | `docs/date-time-format-audit-2026-11-07.md` — approved 2026-11-07; batches 1–4 shipped (`10a51b0`, `5120a98`, `f420215`, `1dc3edb`); only #30 (API chip) deferred |
| Lanes already landed | `prompts/pi-replacement-home-status-badges.md`, `prompts/pi-request-completed-status.md` |
| The approved picture of the merged page | `ui-prototype/replacement-merged-preview.html` (in-repo since 2026-11-15) |
| The approved arrangement top zone | `ui-prototype/replacement-arrangement-top-zone-preview.html` (in-repo since 2026-11-15); live parity = `replacement-arrangement.blade.php` Round 2 (`d2d91ae`) — three setup cards, conditional Summary, 56px rails, Course-over-Slot, full-width Venue trigger |
| Superseded session diaries | `history/2026-10-31-to-2026-11-03-addenda.md` |

---

## 5. Open tasks (state at 2026-11-14)

1. **[USER] Push** — **13 commits were waiting at the 2026-11-14 measurement**: `origin/fedora-backend`
   is at **`a6e6a1f`**; re-check the count, since another session commits concurrently. The
   user pushes; an agent never pushes unless told.
2. **[AGENT] C1 — `T3d`** (`docs/coding-todo.md`): `tests/submission-conflict-refresh.spec.ts` is still
   **not green** and never has been recorded green (it fails inside its own staged-arrival flow — a
   login-redirect timeout, then `#weekSelector` absent on the landed page). Decide: diagnose it
   timeboxed, or quarantine it as `test.fixme()` with the reason written down. The write-guard itself is
   unaffected (it refuses at collection, independently).
3. **[AGENT, low] `venue-book.spec.ts` `U2a-T3`** — the hover tooltip race
   (`#availableTooltip.show #tooltipBookBtn` never visible inside 5 s). Fails identically on unmodified
   HEAD (18/19), so it is pre-existing; worth a timeboxed look when the demo drills are re-run.
4. **[USER, the #1 unknown] The viva date** is still unconfirmed (anchored: week of 7 Dec 2026), and the
   viva demo data is still thin. The demo counts DRIFT because the user adds data by hand — re-measure
   `class_replacement_fresh` before quoting §0, never trust a remembered number.
5. **[USER/OPS — done 2026-11-12, re-checked 2026-11-14] The auto-reject checker now runs by itself.**
   The user's crontab runs `php artisan schedule:run` every minute with
   `DB_DATABASE=class_replacement_testing`, so `requests:auto-reject` fires every 5 minutes. **That
   database is now the user's protected manual testing data**, so the sweep can reject a pending request
   they are testing by hand. Do not stop, alter or re-point the cron line without explicit permission;
   never point it at a demo DB. Request #35's old manual decision is history; do not manually reject it.
   Background: `prompts/pi-prompt-auto-reject-scheduler.md`.
6. **[USER] `my to-do list.md` task 2 — Remove Change Venue Confirmation Modal** is **parked**: the brief
   and its notes moved to `prompts/extra tasks/` on 2026-11-07, and one question is still open there
   (what happens to the picked slots when the venue changes). Task 1 (lab access type) is Done and
   verified. Never start task 2 without the user naming it.
7. **[ACTIVE SDD LANES — 2026-11-14]** `.sdd/changes/` has eleven active lanes:
   **`approved-replacement-cancellation`** (applied, scoped Verify PASS, not archived),
   **`cancelled-slot-reuse`** (revision-2 own-approved refinement complete; whole-feature Verify open),
   **`student-history-test-contract`** (verified, archive-ready), **`cancel-whole-day`**,
   **`summary-card-detail-copy`**, **`student-modal-staff-parity`**
   (applied/in flight — verify or archive on the user's word), **`arrangement-venue-picker-collapse`**
   (planned, no code), **`modal-responsive-width`** (frozen, no code),
   **`timetable-print-v2`** (frozen plan, no code),
   **`cohort-venue-replace-now`** (explore only), **`bulk-cancel-class`** (untracked/parked).
   **151 lanes archived** in `.sdd/archive/` (today's: `2026-11-14-summary-cards-button`, plus `guide-toggle-icon-only` and `my-timetable-ui-cleanup` on disk only) — the two top-zone lanes are committed (`a1e6470`);
   `my-timetable-ui-cleanup` and `guide-toggle-icon-only` are on disk only (untracked).
   Do not stage or apply another lane's files.
8. **[AGENT, demo week]** Chapter 5 browser evidence + report, the NFR benchmarks (C7) and the UAT
   results table (C8) — the coding side is tracked in `docs/coding-todo.md`. **Careful: that file has
   drifted twice over** — its §1 "verified 2026-11-10 06:45" row still quotes `origin` = `da4de30`,
   423 PHPUnit tests and the twin "307 commits behind", and its §3 day plan is dated 2026-11-05
   ("Fri 6 Nov — TOMORROW") with C3 (lane sweep) already DONE. Re-measure and re-date both before using
   it, or it will send you at finished work.
9. **[AGENT, fixture law]** A `now()`-relative test fixture must not land on **Sunday** — the durable
   rule and its receipts now live in §3 ("Sunday (day 6) does not exist in the timetable"). It bit twice
   on 2026-11-09; check a new fixture against it before blaming the code.
10. **[OPEN product question]** A **"Replacement class updated"** notice lands on the class's **own**
    week (14) rather than the **replacement's** week (8), which is what a student wants to see. Not a bug
    report yet — an unanswered design question.
11. **[AGENT, micro] S9** — student notification rows carry no related-status attribute for class-only
    rows, so a class-only notice cannot show the same status chip the request notices do.
12. **[USER/OPS]** The viva twin `../CRS-demo` sits at **`0176f2a`** — **64 commits behind at the
    2026-11-14 measurement** (re-measure; the gap grows with every commit here). Code only, no DB work: its
    `class_replacement_viva` already has the `replacement_requests.replacement_span` column and its
    `failed_jobs` is **0**. Refresh it before any viva rehearsal (C2 in `docs/coding-todo.md`).
13. **[LOW]** `?week=` deep links still land on the remembered week (accepted, WONTFIX); three reference
    templates under `resources/views/ui-design-templates/` still carry `Exclude Completed` and a
    `ClassCancellation.undo(entry)` call — they are design references, not loaded pages.
14. **[PARKED, `prompts/extra tasks/`]** the "Just cancelled" chip TTL, the warn-modal colour (C5), the
    one-honest timeline rail (C6), the Staff User Panel (C9), and `notifications:remind-pending` (C10,
    designed and scheduled nowhere). A parked file is not an instruction.
15. **[USER, decision] The modal B-list is still partly open.** The 2026-11-11 student-popup parity lane
    folded **B5/B6** (staff `Class ID` / `Request ID` naming) and **B9** (`Request Status (Approved)`) for
    the student popup; **B5** still has the separate `Session ID` in Class/Slot Details. Still open:
    **B2** Request Details has no Close button (deliberate earlier ruling); **B8** `factsHtml` is still
    used by `replacement-home` + `request-approval`; **B10** the holiday is stated three times in Slot
    Details; **B11** the student Class Details still passes no `extraFields`; plus the B6 placement
    residue (Request ID row's tab). Board: `docs/Modal inconsistent Report checklist.md` (untracked).
16. **[RESOLVED 2026-11-14] The stale PHPUnit test is fixed.** The obsolete
    `ReplacementHistoriesTest` method was replaced by `test_replacement_history_emits_own_cohort_occurrence_rows`
    (occurrence identity, nested `orig`/`req`, privacy) and the three Saturday-only date fixtures were
    isolated with local clocks in `0e6bc1a`. Full suite: **721/721 PASS, 6,090 assertions**. Do not
    "fix" the payload shape to revive the old assertion.
17. **[USER] `bulk-cancel-class` is parked.** Untracked lane (`.sdd/changes/bulk-cancel-class/`:
    `explore-brief.md`, `proposal.md`, **`sdd.yaml`** — no `tasks.md`/`design.md` yet) with a live
    prototype `ui-prototype/bulk-cancel-preview.html` (needs `:8000` up for `theme.css`). The reviewer
    round starts only when the user is happy with the prototype. Its first planned step — the shared
    `ReleaseRequestSlots` helper — already exists from `cancel-whole-day` T1; the parked lane still
    needs its own migration → action → single path → UI before it can apply.
18. **[USER] Arrangement top zone — both rounds shipped.** Round 1 (two cards) shipped in `7692d80`
    (lane `replacement-arrangement-top-zone`, prompt `prompts/replacement-arrangement-top-zone.md`).
    Round 2 (three setup cards + conditional Summary + 56px rails) shipped in **`d2d91ae`** (lane
    `replacement-arrangement-top-zone-round2`; `tasks.md` T1–T15 ticked, T16 archive gated on the
    user). The live receipt was corrected once after apply: Course over Slot and a full-width Venue
    trigger, matching the prototype. Prototype:
    `ui-prototype/replacement-arrangement-top-zone-preview.html`. Do not edit the prototype without
    the user.
19. **[USER] `prompts/cancel-class-reason-block.md` is FINISHED and tracked** (items 1–5 shipped in
    `c55b530`/`71a8254`), so it is ready for the archive step — `git mv` it into
    `prompts/completed prompt (Archieve)/` with one evidence row in that folder's README, on the user's
    word. Do not archive it silently.
20. **[LOW, pre-existing] Request Approval's Actions column clips Reject at 1600 px.** Reported, never
    touched; reproduce before "fixing" anything else in that column.
21. **[USER, planned] `timetable-print-v2` is frozen, not applied.** Proposal/design/spec/tasks are in
    `.sdd/changes/timetable-print-v2/`; the real print header, print CSS/JS, Normal Class filter and
    tests are **not** implemented. Wait for the user's explicit “apply”.
22. **[USER, planned] `guide-toggle-icon-only` is frozen, not applied.** The one question-mark guide icon
    on eight pages (with page descriptions moved into the guide) has proposal/design/spec/tasks only; no
    live edit. Wait for the user's explicit “apply”.

