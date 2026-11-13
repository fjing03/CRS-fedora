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

G3 granted by the user ("commit"). Part A code/tests/docs committed as `0d727a3` (`feat: reuse a cancelled class slot safely and show both blocks`), **75 files**, branch `fedora-backend`, **no push**. Only this lane's hunks were staged: the shared `public/js/ui-common.js`, `public/css/theme.css` and `resources/views/livewire/venue-timetable.blade.php` carry other lanes' uncommitted guide/summary/picker/tinted-select work in the worktree, and those hunks were deliberately left unstaged (committed versions verified free of those markers; staged JS passes `node --check`, staged Blade compiles, staged PHP passes `php -l`, CSS braces balanced). `docs/demo-db-data-changes.md`, `prompts/for-ai-to-read/*` (mixed context/docs), `.sdd` archives and other lanes' changelogs/tests were left untouched. This commit is not a Verify/archive claim: older browser regressions and Part B remain open as recorded above.

