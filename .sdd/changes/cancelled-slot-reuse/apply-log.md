# Apply log — cancelled-slot-reuse

## Authority

User explicitly said: “u may sdd apply.” This opens G1 for implementation/testing only. G2 (exact fresh-only cleanup), G3 (commit) and push remain unapproved. Frozen planning headers describe the previous planning gate; sdd.yaml and this log record the later authority. No demo data writes or schema changes are permitted by this Apply.

## Preflight — 2026-11-13

HEAD: a6e6a1f, branch fedora-backend. The initial dirty tree includes other sessions' guide, timetable UI, arrangement mobile/venue selector, summary formatting, context, changelogs and two SDD archive moves. Those changes must remain untouched. Backend app/Actions, app/Services, app/Concerns and tests/Feature tracked files have no baseline diff. The parallel arrangement-cancelled-room-hover folder no longer exists; this does not prove permission to overwrite shared UI work.

Start with new isolated ownership/release backend files and tests. Shared UI coordination is still pending; do not edit shared JS/CSS/Blade until ownership is confirmed. A01 remains unchecked until its full baseline and coordination requirements are met. Backend foundations may be prepared but no cancellation/approval integration is considered complete or ready to use yet.

Testing target: only class_replacement_testing via explicit DB_DATABASE. Do not stop or alter the live automatic checker. No tests or migration commands may target demo databases. Baseline checks before new code completed:

- `DB_DATABASE=class_replacement_testing php artisan tinker --execute=…` printed `Testing target: class_replacement_testing`.
- Existing CancelClassBatchTest / MyDayClassesTest / ApprovalWorkflowTest / CancelledBlockStaysVisibleTest: **40 passed, 175 assertions** (92.717s); `/tmp/crs-reuse-baseline-tests.txt`.
- `composer run lint:check`: passed; `/tmp/crs-reuse-baseline-lint.txt`.
- `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`: passed, zero errors; `/tmp/crs-reuse-baseline-phpstan.txt`.

These are targeted baseline checks, not the full implementation gate/full suite. Other historical failures are retained in project context and are not claimed fixed.

Isolated coding subprocesses (pi model commandcode/z-ai/glm-5.3-flash, read/grep/find/ls/edit/write only, no shell/DB tools) own new A02/A02b/A03 foundation files and separate A04 source-lock helper/Submit wrapper. Main agent retains testing and verification responsibility. No shared UI edits delegated.

Initial unrelated diff/status/shared UI checksums captured under `/tmp/crs-reuse-preflight/` to detect accidental overwrite.

## Verified foundation slice — A02/A02b/A03/A04

- New SELECT-only OriginalOccurrenceReservation with exact geometry, full live-claim coherence and lifecycle evidence; typed plan/entry DTOs in separate PSR-4 files.
- New ReleaseOriginalOccurrenceSlots with active-transaction guard, complete coordinate/owner/status/version conditional flips, structured release/no-op receipts, and stale-member rollback exception. Not integrated into cancellation/approval yet.
- New OccurrenceWriteLock / contention exception; SubmitReplacementRequest wraps its existing logic without adding an outer transaction. Captures the same connection through finally and disconnects on unverified unlock to avoid stranded locks.
- Tests and code fixes were made by isolated coding subprocesses. Main ran testing-only checks and targeted automatic Pint formatting. Initial failed fixtures, type/list mistakes and safety omissions were fixed before task completion; earlier failure receipts remain under /tmp rather than being represented as successful runs.

Final evidence:

- Foundation tests: **35 passed, 337 assertions**; `/tmp/crs-reuse-foundation-tests-r6.txt`.
- Lock/SubmitGates/SpanReservation regression subset: **36 passed, 160 assertions**; `/tmp/crs-reuse-lock-tests-r2.txt`.
- Combined foundation + existing cancellation/approval/selected-day regressions: **110 passed / 111, 670 assertions**, one MyDayClassesTest fixture insert collided with a seeded occupied coordinate (time_slots_no_double_book_idx); `/tmp/crs-reuse-foundation-combined-tests.txt`. This is NOT claimed as clean or proved pre-existing. Isolated rerun of MyDayClassesTest passed; `/tmp/crs-reuse-myday-isolated.txt`. Investigate fixture-order sensitivity during A07/A21 rather than silently dropping it.
- Final `composer run lint:check`: passed; `/tmp/crs-reuse-finish-lint.txt`.
- Final full `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`: passed, zero errors; `/tmp/crs-reuse-finish-phpstan.txt`.
- `node --check public/js/ui-common.js`: passed (JS unchanged by this slice).
- Independent read-only foundation code audit: PASS, no blocking defects; `/tmp/crs-reuse-foundation-code-review.txt`, copied into review-log.md.
- Preflight shared JS/CSS/timetable/arrangement checksums all still match. No shared UI files overwritten. No server restarted and no scheduler changed. All coding subprocesses have exited.

A01 is partial (baseline captured, shared-file ownership still pending). Four isolated backend units are checked; no cancellation-release or stacking feature is considered shipped. A05 onwards, full-suite/browser/view verification and complete SDD Verify remain outstanding. No cleanup facility/live cleanup, demo write, schema edit, commit, stage or push was performed. Before shared UI changes, confirm the other sessions are finished or agree file ownership; then continue applying the remaining reviewed tasks. G2/G3 remain closed.

## Shared-file coordination cleared — 2026-11-13

The user answered “yea they're done.” to confirm the other sessions had finished editing shared timetable files. A01 coordination is now resolved. Existing dirty UI/context/archive work remains the baseline, not permission to revert it. A fresh diff/status/checksum snapshot is under `/tmp/crs-reuse-continuation/`. Baseline event/count behaviour is documented in frozen D5/D7 and existing cancellation/approval/visibility tests (initial 40-test receipt above); comparisons must preserve those no-reuse formulas and other sessions' summary/guide changes.

Disjoint coding owners now handle cancellation/atomic-day integration + shared mutation-lock helper; approval integration; scoped timeline/venue payloads; shared JS/CSS/four timetable renderers; and new guarded cleanup CLI/tests/runbook (code only). Coding subprocesses have no shell/DB tools. Main session alone runs testing-only checks. No cleanup write, stage, commit, push or scheduler change is authorised.

### Integration checks in progress (not task-completion receipts)

- Served pure-lane Playwright suite: **11 passed** (`/tmp/crs-reuse-lane-tests-r1.txt`), explicit testing target, no auth setup/database fixtures. `node --check public/js/ui-common.js` passed.
- First approval/foundation run: **56/66 passed, 455 assertions**; the 10 new errors were a fixture using forbidden room type `lecture` instead of `lecture_hall`. Fixture and stale-request guards corrected; rerun pending. The fixture-only `EmailQueueTest` original-grid addition was inspected and accepted into this task's scope.
- First cancellation run: **32/39 passed, 272 assertions**; seeded-coordinate fixture collisions, new fixture/version assumptions and legacy/request-only cases need correction (`/tmp/crs-reuse-cancellation-tests-r1.txt`).
- First timeline/cleanup/reuse matrix: **30/42 passed, 1,391 assertions**. New timeline fixtures incorrectly used slot status `approved` rather than `occupied`; a student empty-week shape assertion, duplicate exception fixture, deliberately drifted snapshot expectation and an undefined matrix-fixture variable need correction (`/tmp/crs-reuse-timeline-cleanup-matrix-tests-r1.txt`). Five real availability→submit→approve reuse cases passed in this run.
- Read-only inspection additionally found implementation blockers: attached-landing validation lacked the complete canonical geometry proof; mutation locks did not revalidate every separate landing receipt; venue history-only layout could offer pre-cleanup occupied cells as green because it lacked actual room-cell state. Coding owners are correcting these before their tasks can be checked. Standalone race-worker guard is also being tightened to the literal testing database, not a caller-supplied database name.
- PHPStan is **not passing** yet. Use `vendor/bin/phpstan analyse --memory-limit=1G -v` for the full report (`/tmp/crs-reuse-integration-types-r2-full.txt`); the Composer default hit 128MB. `composer run types:check` is the script name, not `composer run phpstan`.
- Shared cancellation-audit evidence and the cleanup mutation-lock boundary are being reconciled to single shared implementations; no deferred integration placeholders may be treated as done. Independent approval implementation review is running read-only.

At that checkpoint no backend/UI/cleanup integration units beyond A01/A02/A02b/A03/A04 were complete. Those failures were in-progress findings, **not** SDD Verify. Demo databases, scheduler, staging and commits remained untouched.

## Backend integration receipt — 2026-11-13 (user: “proceed”)

**17/38 units are now checked:** A01/A02/A02b/A03/A04/A05/A06/A07/A08/A09/A09b/A10/A10b/A11/A14/C01/C02. Other implemented slices remain unchecked until their remaining browser/delivery evidence is complete.

### Passing checks

- `/tmp/crs-reuse-integrated-targeted-tests-r5.txt`: **171/171 tests, 2,813 assertions**, explicit `DB_DATABASE=class_replacement_testing`. Includes both foundation classes; cancellation, atomic day, default/opt-in release; mutation locks; timeline/venue projection; five reuse combinations/blockers; old approval workflow; 14 new approval cases including complete junction-less stored spans; cleanup gate/apply/recovery units; real two-process OCC race and source interlocks. This is a targeted regression set, **not the full PHPUnit suite**.
- `/tmp/crs-reuse-integration-types-r7.txt`: PHPStan **PASS**, memory limit 1G.
- `/tmp/crs-reuse-integration-lint-r4.txt`: complete `composer run lint:check` **PASS**. A formatting-only subprocess ran Pint on the exact owned PHP manifest `/tmp/crs-reuse-pint-owned-files.txt`; no blanket formatting of other-session files.
- `/tmp/crs-reuse-integration-views.txt`: testing-target `artisan view:cache` **PASS**. `node --check public/js/ui-common.js` and `git diff --check` **PASS**.
- `/tmp/crs-reuse-layout-browser-tests-r3.txt`: **7/7** including 3 fresh-auth setup cases and 4 default/custom/pre-cleanup/responsive-print cases. Earlier pure served planner receipt remains **11/11**. Browser auth state can expire: the `--no-deps` r2 run landed on login pages, not a layout failure. Refresh setup before synthetic layout tests.
- `/tmp/crs-reuse-e2e-tests-r4.txt`: **3 real UI lifecycle cases PASS** — selected cancellation releases all four original cells; same class rebooks the exact original room/date/time and shows distinct blocks; PL queue UI approval keeps the complete landing occupied and preserves the original reason. The suite is **not green**: T2 currently expects `9:00 AM ~ 11:00 AM` while the existing venue block prints `9:00 AM - 11:00 AM`; this is a test delimiter assumption (both formats are permitted by the standing rule), not evidence of a failed booking. T2 privacy checks and 4 later ending/student cases remain unfinished. Earlier constructor/identity test bugs were corrected without changing application contracts.

### Corrections made before these passes

Canonical request geometry now has shared core methods for planner classification and strict landing proof, with their different available-state policies explicit. Mutation locks re-read exact request/source/anchor/span/receipt membership before locking the complete slot union; nested day helpers reuse held locks. Attached pending/approved release has conditional coordinate/owner/status/version updates, owner clearing and truthful before/after receipts; default callers keep their policies. Post-commit notification faults are reported rather than falsely claiming committed work rolled back. Approval reads complete legacy anchor+stored-span runs, not only their anchor, and refuses malformed tails without partial changes.

One shared `CancelledOccurrenceEvidence` predicate serves timeline and cleanup. The audit lookup is scoped to requested source IDs. Venue rendering receives real `roomCellStatesByWeek`: occupied/pre-cleanup or missing cells cannot become green merely because their visible event is cancelled history. Cleanup uses the shared mutation-lock boundary. Its forbidden environment-option guard checks the actual `--env` option as well as the environment filename (the absent-file fallback makes filename-only detection unsafe).

### Still open / next continuation

1. Fix the **new test's** T2 delimiter assumption, retain privacy checks and complete remaining reject/withdraw/automatic-reject/student browser cases. Do not change production formatting merely to satisfy a mistaken assertion.
2. Re-run layout receipts with `--output=/tmp/crs-reuse-layout-receipts` so subsequent Playwright runs do not delete screenshots. Main viewed 1440-light and 390-dark captures: stacking/true spans are visible, but investigate mobile base-cell time labels and capture settled theme/transition states before declaring A19 visually complete. No mobile regression is yet proven against the baseline. Previous `test-results/` PNGs were cleared by later runs.
3. Recheck independent implementation review after the legacy approval fix (the earlier approval review's FAIL is addressed in code/tests but has not received a reviewer PASS). Run full PHPUnit and remaining old venue/selected-day/arrangement regressions; do not replace them with the 171-test count.
4. C03 needs an actual testing-only `pg_dump -Fc` / `pg_restore --list` / checksum workflow rehearsal. Unit manifests deliberately use fabricated files to test gates; they are not a verified backup receipt. No live inventory/backup/apply (O01–O03) was requested or performed.
5. Finish remaining A12–A21b evidence/changelogs, then A22 review/G3 gate. Part B remains unimplemented and depends on Part A delivery. No whole-feature SDD Verify or archive yet.

Testing DB baselines were rebuilt **only after a literal current_database guard and zero-user check**, because PHPUnit leaves empty fixtures. `DatabaseSeeder` succeeds; `TestingFixtureSeeder` was attempted once and rolled back on its hard-coded class-id-24 FK assumption after test sequences advanced. The new isolated browser fixture does not need it; no schema reset or seeder edit was used to conceal this. Testing `:8030` alone was restarted through `scripts/dev-serve.sh`; its environment was confirmed testing. Auto-checker remains running and testing-only, unchanged.

All coding/formatting subprocesses have exited. Measured HEAD `a6e6a1f`, branch `fedora-backend`, 0 commits ahead of origin at this checkpoint (not the stale handover count). No commit, stage, push, demo DB write, demo server restart or scheduler change by this Apply slice. Preserve other-session dirty/archive/guide/mobile/summary work, the user's untracked to-do file, and the unrelated demo request35 repair-log receipt.

## 2026-11-13 — lifecycle complete; real cleanup rehearsal; full regression measured

User said “proceed” to the continuation plan. G1 only; G2/G3 remain closed. Current scope is still Apply, not whole-feature Verify.

### Changes and independent checks

- Corrected new browser-test assumptions only: T2 accepts the existing hyphen or tilde time joiner; T5 explicitly selects the intended target week, clicks the visible phone time row and real Submit button; T7 checks request-page controls rather than mistaking shared cancellation-notification links for decision buttons. The served raw cancellation reason was preserved; no production change was made to satisfy those assumptions.
- Fixed a real mobile issue in the NEW venue base offers: safe textContent time labels and local ≤768px normal-flow/minimum-44px styling. Ordinary cells and desktop geometry are unchanged. Theme transitions settle before screenshots. Added visible-label/target-height/no-duplicate checks.
- Independent read-only A08 re-review PASS: prior junction-less full-span blocker resolved. Report: /tmp/crs-reuse-approval-rereview-output.jsonl.
- Independent read-only A11–A19 display/payload review PASS, slice only. Report: /tmp/crs-reuse-ui-rereview-output.jsonl. Minor coverage observation: venue proven-user-cancelled/non-overlap suppression lacks a specifically named test; personal suppression and venue stamp-only arms are tested. No blocking code finding.

### Executed receipts

All DB writes below selected literal class_replacement_testing, checked actual current_database(), and used only :8030. No PHPUnit/RefreshDatabase run overlapped browser/committed rehearsal fixtures.

- Full direct PHPUnit: DB_DATABASE=class_replacement_testing vendor/bin/phpunit --debug --colors=never → **708 tests, 707 passed, 5,877 assertions, 1 error**; /tmp/crs-reuse-full-phpunit-r3.txt. The sole error is the previously recorded ReplacementHistoriesTest::test_replacement_history_emits_own_cohort_rows_in_the_mock_shape, line 302, Undefined array key 1. Runtime 724.605 seconds. The first php artisan test attempt hit the tool's 600-second timeout without a completed receipt; it is NOT counted as a pass. A testdox retry was stopped in favour of the completed debug run. No clean full-suite claim.
- Guarded lifecycle: CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test tests/cancelled-slot-reuse.spec.ts --project=chromium --workers=1 --grep-invert TC70 --output=/tmp/crs-reuse-next/lifecycle-results-r7 → **11/11** (8 lifecycle + 3 setup), /tmp/crs-reuse-e2e-tests-r7.txt. Covers cancellation/exact rebook/PL approval, outsider privacy, PL reject, owner withdraw, phone booking + disclosed frozen-action auto-rejection seam, student cohort/private-field/read-only checks.
- Guarded layout/planner: same environment/project/worker/TC70 options, specs tests/cancelled-slot-layout.spec.ts tests/timetable-lane-layout.spec.ts, output /tmp/crs-reuse-layout-receipts-r5 → **18/18** (4 layout + 11 planner + 3 setup), /tmp/crs-reuse-layout-browser-tests-r5.txt. Saved all 1440/768/390 light/dark and print-emulation PNGs separately from lifecycle output. Main inspected desktop light/dark, tablet light, phone dark and full print-emulation capture; labels/base targets are now visible. Print receipt checks row retention, not a new print-v2 design.
- Older browser regressions: same guards/options, specs tests/cancel-whole-day.spec.ts tests/venue-book.spec.ts tests/arrangement-slot-ownership.spec.ts → **16 passed, 5 failed, 3 not run**, /tmp/crs-reuse-older-browser-regressions.txt. Whole-day cancel PASS: four selected classes cancelled, zero remaining that day, no console errors. Ownership setup failed on hard-coded class id 39 (absent after sequence advancement), so three followers did not run. Venue T2 expects old Mon/24-hour prose instead of existing Monday/12-hour format; T3 found no assumed span-free cell; T6 found no assumed no-fit offer; T8 expects finite day-end room where the unchanged helper contract can return Infinity. Do NOT call all five proven baseline failures: fixture/copy assumptions and intentional real-room fail-closed effects still need final classification/reconciliation. HEAD already returns Infinity in roomBeforeNextEvent; no production weakening was made.
- composer run lint:check; vendor/bin/phpstan analyse --memory-limit=1G --no-progress; node --check public/js/ui-common.js; DB_DATABASE=class_replacement_testing php artisan view:cache; git diff --check → PASS. Receipts: /tmp/crs-reuse-next/{lint,phpstan,views}.txt.

### C03 — actual testing backup, apply and recovery rehearsal

The guarded /tmp/crs-reuse-next/cleanup-rehearsal.php creates identity-scoped committed legacy fixtures, not a schema reset. Main reviewed/fixed a helper row-shape mistake before running it. Existing unit tests remain the missing/ambiguous/protected-claim matrix; this separate rehearsal supplies genuine backup and command evidence.

1. After the full suite left zero users, guarded seed created one legacy cancelled original (four occupied cells), plus unchanged other-week rows.
2. Main ran actual pg_dump -Fc -d class_replacement_testing to /tmp/crs-reuse-next/cleanup-testing.dump, then pg_restore --list on THAT dump, verified expected tables, captured both SHA256s and a manifest/operator checklist. Dump SHA256: 99cd102f61abbb6316fca99b3086c1750fd83ce118bf3caf6e3258971162508f. Restore-list SHA256: 20405b3bc1722d6956a38aca2bea74f2d03451c5e1a42d6fdc0e7cc3143b5eef. No restore into any DB was run.
3. Fresh testing inventory/plan, approval block explicitly bound to testing-only G1 rehearsal, then real classes:release-cancelled-slots --apply with exact testing confirmation, dump/manifest, approval reference/operator, actor and operation csr-c03-testing-rehearsal. Result: **4 changed cells, versions 1→2, owner NULL; 1 maintenance audit; 0 notifications; post-commit verification clean**. Plan fingerprint sha256:b6a3aa2a3f408fd54395b8bd53efa42ef580ea8e67d6a526a868ebed46809c7e.
4. Real recovery helper safely restored four cells at version 3 inside an explicitly rolled-back rehearsal transaction. Separate version-drift and real pending-claim fixtures each refused with zero restored rows and byte-identical remaining state; outer fixture transactions rolled back.
5. Fresh repeat inventory: zero proposed changes. Removed only registered rehearsal identities; current_database remained testing and users returned to zero. Baseline DatabaseSeeder then rebuilt auth/timetable data; no TestingFixtureSeeder, schema reset or sequence-reset workaround.
6. Receipts: /tmp/crs-reuse-next/cleanup-{seed,inventory,apply,recovery,repeat,fixture-removed}.txt; actual dump, restorelist, manifest, checksum and operator-checklist files beside them; saved state-receipt JSON records exact IDs. Do not add this TESTING operation to the unrelated demo write log.

### Remaining gates

Tasks now **23/38 checked**: additionally A12/A13/A15/A16/A18/C03 based on full backend, new browser receipts and independent display review. A17/A19/A20–A21b remain open conservatively until remaining targeting/visual/scope evidence and older regression reconciliation are complete, despite the passing new lifecycle/layout suites. No final Verify or Part A delivery/commit approval request yet. Part B remains untouched and depends on Part A delivery.

Next: classify/reconcile the older browser assumptions without changing booking rules or resetting sequences, finish remaining interaction/visual scope checks and final Part A evidence review, then present A22/G3. Do not silently fix the unrelated history-shape test or tick another lane/user task list. No live inventory/backup/apply, demo write/restart, schema or scheduler change, staging, commit or push.

## Commit receipt — 2026-11-13

G3 granted by the user ("commit"). Part A code/tests/docs committed as `0d727a3` (`feat: reuse a cancelled class slot safely and show both blocks`), **75 files**, branch `fedora-backend`, **no push**. Only this lane's hunks were staged: the shared `public/js/ui-common.js`, `public/css/theme.css` and `resources/views/livewire/venue-timetable.blade.php` carry other lanes' uncommitted guide/summary/picker/tinted-select work in the worktree, and those hunks were deliberately left unstaged (committed versions verified free of those markers; staged JS passes `node --check`, staged Blade compiles, staged PHP passes `php -l`, CSS braces balanced). `docs/demo-db-data-changes.md`, `prompts/for-ai-to-read/*` (mixed context/docs), `.sdd` archives and other lanes' changelogs/tests were left untouched. This commit is not a Verify/archive claim: older browser regressions and Part B remain open as recorded above. The final amended commit hash is **b1f13fd**; 0d727a3 above was superseded by the receipt amendment.

## Continuation preflight — older regressions and Part B (2026-11-13)

The user subsequently approved fixing the five older browser failures and proceeding with all of Part B. This grants code/test work, not a new commit/push or demo cleanup. Final Part A Verify remains open; do not treat the earlier commit as a clean-suite receipt or silently expand this work to the unrelated ReplacementHistoriesTest error.

- Rechecked branch/HEAD: fedora-backend / b1f13fd, 63 dirty/untracked entries at preflight. Saved the exact starting diff/status in /tmp/crs-reconcile-b/preflight.patch and preflight-status.txt. All existing guide/mobile/summary/picker/archive/context work is preserved.
- Matching frozen authority remains A21 regression reconciliation and B01–B03, S24/S25, D8. No proposal/spec behaviour has been changed. The former arrangement-cancelled-room-hover folder is absent; no separate hover coding process was present. Earlier shared-file coordination approval stands.
- Delegated only the two older regression specs/test helper files to one no-shell coding agent, and Part B arrangement backend/Blade/mobile/new focused tests to a separate no-shell coding agent. Main owns review, all DB/test commands and evidence. Neither agent has DB, shell, commit or push tools.
- Confirmed actual current_database() = class_replacement_testing (191 users); started only testing :8030 with DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030. Guarded auth setup passed 3/3. No seed/reset, demo access or scheduler change.
- Read-only browser probe confirmed two stale T3 assumptions: hour-header cells now each span two half-hours (11 headers, 22 slot indices), and fixed week 8 is in the past relative to the current notice window. The testing baseline supplies rows only up to 18:00 while the UI axis extends farther; missing later offers correctly stay blocked. This explains why a no-fit tail offer is not guaranteed by ordinary seed data. New assertions/fixtures must respect real room offers, not revert fail-closed availability.
- Implementation and rerun receipts were pending at this preflight point. The completed continuation receipts below supersede that checkpoint; they do not grant commit/push or demo cleanup authority.

## Completed continuation — five older failures and Part B

### Implementation and scope

- Older regressions are fixed without relaxing booking rules. Source identity is discovered by staff/module/type (AMCS2093 P in B010), not dead class id 39; source weeks are real, unexceptioned and free of active requests; proposed cells use a safe future week. The natural fixture also avoids a different source whose time overlaps the viewer's own class, preserving the not-mine assertion rather than weakening it.
- Venue tests use the existing full-weekday/12-hour sentence, the shared half-hour axis (not the now-hour-wide header count), actual room offers and live-event/matrix boundaries. Two guarded identity-scoped tail offers exercise the final-axis no-fit case; missing ordinary seed offers stay blocked. The gap audit independently derives the next live/matrix block and compares the delta/Infinity contract. No history geometry becomes occupancy.
- Further repeat-run fixture issues were corrected: the ownership sweep separates the existing notice labels from the six ownership labels; the whole-day spec converts its 1-based target to the correct 0-based navigation index and refuses to click a cancelled or arbitrary neighbour. The final integrated run cancelled week 9 after the earlier passing run cancelled week 8, with no intervening broad baseline reset.
- Fixture setup/cleanup uses literal testing DB commands with live current_database guards, ON_ERROR_STOP and argument-safe execFileSync. Request/slot registrations precede flips; conditional flips/junction creation are atomic; cleanup locks and checks the full registered set, versions, owners, junctions and foreign fallback spans before scoped deletion/restoration in one transaction. No sequence-reset or TestingFixtureSeeder workaround.
- B01/B02: ArrangementSlotHolders reuses strict shared request-claim proof. Proven active receipts/stored spans beat stale class ids; unknown claims neutralise all touched landing cells, including drifted fallback tails and available anchor twins. The class arm excludes exceptions. One public 12-hour full-span holder formatter feeds the identity-based library; room/own/cohort facts merge once while distinct identities survive equal display strings. Existing classification, labels, colours, eligibility and shared mobile snapshot/textContent flow remain unchanged. No renderer write, new dependency, schema migration, print redesign or scheduler change.
- B03 uses the existing guarded CSR fixture/REAL submit action for a holder with a different lecturer and a cohort disjoint from all viewer cohorts. The empty picker is asserted, and only its actual active lecturer vector is tested (unselected courses' calendars are not falsely imposed). All four held cells expose the same true two-hour range. Synthetic identity/escaping stages are explicitly disclosed and use genuinely bookable non-holiday days; they are not claimed as backend holder proof.

### Actual checks, not proposed commands

All PHP DB/test commands selected DB_DATABASE=class_replacement_testing explicitly. All browser runs used CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030, Chromium, one worker and --grep-invert TC70.

1. Relevant backend: vendor/bin/phpunit tests/Feature/MakeUpBusyVectorTest.php tests/Feature/CancelledSlotRoomHolderTest.php tests/Feature/ReplacementArrangementTest.php tests/Feature/CancelledSlotTimelineTest.php tests/Feature/CancelledSlotReuseMatrixTest.php tests/Feature/VenueTimetableTest.php tests/Feature/OccurrencePinTest.php --colors=never → **98 passed, 1,712 assertions**, 96.356s. Receipt: /tmp/crs-reconcile-b/part-b-feature-r2.txt. New holder file has 13 focused cases. Earlier 35/313 initial pass did not cover the review's two fallback gaps and is not substituted for this run.
2. Extra focused ReplacementArrangementTest run passed; receipt /tmp/crs-reconcile-b/arrangement-final-targeted.txt. Existing DatabaseMigrations tests tear down their testing schema; main rebuilt only the empty testing baseline with plain migrate (when needed) plus DatabaseSeeder after exact PHP current_database proof. No manual schema reset to fit fixture ids. Final baseline classes were **49–149; class 39 absent**, proving the rewritten fixture does not rely on the obsolete identity. Seed receipts: browser-baseline-seed-r2.txt and -r3.txt.
3. Older three specs alone: **24/24 (21 cases + 3 auth setups)**, no skipped/unrun followers; /tmp/crs-reconcile-b/older-r5.txt. Lifecycle/layout/planner: **26/26 (23 + 3)**, /tmp/crs-reconcile-b/lifecycle-layout.txt. New Part B: **8/8 (5 + 3)**, /tmp/crs-reconcile-b/room-holder-r5.txt.
4. FINAL INTEGRATED browser run, all seven specs TOGETHER: tests/cancel-whole-day.spec.ts tests/venue-book.spec.ts tests/arrangement-slot-ownership.spec.ts tests/timetable-lane-layout.spec.ts tests/cancelled-slot-layout.spec.ts tests/cancelled-slot-reuse.spec.ts tests/cancelled-slot-room-holder.spec.ts → **52/52 (49 cases + 3 shared auth setups), no skips or unrun cases**, 2.9min. /tmp/crs-reconcile-b/integrated-browser.txt; artifacts in integrated-browser-results/. This is the final cross-suite receipt, not the sum of runs with repeated setups.
5. Main inspected all four real holder PNGs in /tmp/crs-reconcile-b/room-holder-results-r5/: desktop light/dark with the actual hover tooltip visible, phone light/dark with the shared info sheet open. Public holder name/cohort, PENDING and 9:00 AM ~ 11:00 AM are readable; no private remark/reason. Earlier desktop captures lacking the hovered tip were not accepted as visual proof. Integrated run retains refreshed copies too.
6. FINAL composer run lint:check; vendor/bin/phpstan analyse --memory-limit=1G --no-progress; node --check public/js/ui-common.js; node --check public/js/arrangement-mobile.js; DB_DATABASE=class_replacement_testing php artisan view:cache; git diff --check → **PASS**. Receipts: /tmp/crs-reconcile-b/{lint-final,phpstan-final,views-final}.txt. Six initial PHPStan findings were fixed at their real cause (required shapes/support-collection API), without ignores, type casts or baselines; official identifier pages were read before correction. Targeted Pint touched only this task's PHP files.
7. Independent backend R1 requested two fallback fixes; all three new adversarial cases passed and backend rereview PASS. Independent browser/fixture review **PASS for safety/truthfulness**, /tmp/crs-reconcile-b/browser-review-r1.txt (full JSON browser-review-output.jsonl). Reviewers read main's receipts and did not run tests. Nonblocking legacy note: whole-day scan still stops at week 13 and fails loudly if no earlier suitable day; no invented pass. The stale localhost/reset header was corrected to the exact guard invocation.
8. Failed intermediate test-only iterations remain in /tmp/crs-reconcile-b/{venue-r1,older-r2,older-r3,older-r4,room-holder-r1,room-holder-r2,room-holder-r3,room-holder-r4}.txt. They include SQL/type/capture/calendar fixture mistakes found and fixed before the final integrated pass; no failed/timed-out run is counted as a pass. No production eligibility change was used to satisfy them.

### Remaining authority and gates

Tasks **26/38 checked** (B01–B03 added). Part A remaining interaction/visual/full-scope delivery checkboxes and final SDD Verify stay open conservatively; the prior unrelated ReplacementHistoriesTest shape error is not fixed or silently relabelled. No new full-suite clean claim: the prior full receipt was 708 tests, 707 passed, 1 error. B04/new commit and all live cleanup gates remain closed until user approval. Current HEAD remains **b1f13fd**, fedora-backend, **1 ahead of origin**, no push. The index is empty.

Preflight preservation comparison found **zero unexpected changes to the 43 pre-existing tracked dirty diffs** outside the explicitly owned arrangement Blade/changelog and context row; /tmp/crs-reconcile-b/preservation-receipt.json. Other-session guide/mobile/summary/picker/CSS/shared-JS/archive work, user task list and demo-repair log remain unstaged and unchanged by this task. No demo DB read/write/restart, operational cleanup, new migration file, production schema change or scheduler change was performed. Testing fixture writes are not a demo change-log operation.

Final identity-scoped state receipt: /tmp/crs-reconcile-b/final-testing-state.json confirms testing target, zero old ownership/RHH fixture requests, zero active CSR claims, zero tail-offer rows, and class 39 still absent. Only testing :8030 was stopped after the final receipts; demo servers and the testing scheduler were untouched. No coding/test subprocess remains active. Final git status is saved in final-status.txt; no files were staged at that checkpoint.

## Approved separate continuation commits

User answered **yes** to: “Shall I commit the test fixes and Part B as separate commits?” This opens G3 only for these two scoped commits, not a push, demo cleanup, extra implementation or final Verify waiver.

1. **ca98fb5 — test: make booking regression fixtures repeatable**: exactly four owned files, tests/arrangement-slot-ownership.spec.ts, tests/cancel-whole-day.spec.ts, tests/helpers/intent-modal-helper.ts and tests/venue-book.spec.ts. No production booking changes.
2. **Part B — the separate feature commit containing this entry**: ArrangementSlotHolders service; owned ReplacementArrangement payload/tooltip changes; focused backend/browser tests; this lane's SDD delivery notes and changelogs. Mixed arrangement Blade/changelog files were staged from continuation-only deltas against the saved preflight version, NOT whole files. Other sessions' guide/mobile/summary/picker hunks remain unstaged. B04 is checked; **27/38 tasks**. Part A delivery/full-feature Verify and the unrelated history-shape error remain open.

Receipt basis is unchanged: relevant backend 98/98, 1,712 assertions; final combined browser 52/52 (49 cases + 3 shared setups); scoped independent backend/browser reviews PASS. No code changed between those checks and this commit-only task. No DB command, server restart, scheduler change or push is part of the commit operation. Mixed handover updates remain in the working tree rather than bundling another session's diary edits.

