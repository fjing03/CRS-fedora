# Scoped verification — 2026-11-14

## Result
All FOUR explicitly approved test-method repairs across three files are complete; independent reviews PASS. FINAL full backend suite **721/721 passed, 6,090 assertions**. Three affected files **31/31 passed, 308 assertions**; lint/PHPStan PASS. No application, schema or booking-rule changes. Test-only change verified; separate cancelled-slot-reuse whole-feature/browser acceptance Verify remains parked. At the verification checkpoint no commit/push or archive had been performed. User subsequently approved the scoped commit containing this report; no push or archive approval. Historical failing checkpoints below are preserved, superseded by the final receipts.

## Initial repair evidence (historical checkpoint before second approval)
All DB/test commands selected literal DB_DATABASE=class_replacement_testing; PHP current_database verified before fixtures. Receipts: /tmp/crs-history-test-fix/.

- baseline.txt: old method 0/1 pass, 3 assertions, Undefined array key 1.
- focused.txt: initial mistyped filter matched ZERO tests, exit 1; NOT a pass.
- focused-r2.txt: exact renamed method **1/1 pass, 37 assertions**.
- file.txt: **7/8 pass, 92 assertions**, one error in unchanged test_merged_page_keeps_today_and_future_approved_requests_as_approved.
- related.txt: **49/49 pass, 323 assertions** across ReplacementHistoryRowTest, ReplacementHistoryDefaultWeekTest, StudentReplacementHistoryParityTest, StudentClassReplacementsFixesTest, ReplacementMapperStatusLabelTest and CancelledSlotTimelineTest.
- lint.txt: composer run lint:check PASS.
- phpstan.txt: vendor/bin/phpstan analyse --memory-limit=1G --no-progress PASS, no suppressions/baselines.
- implementation-review.txt: independent read-only scoped PASS; all helpers and seven sibling methods byte-identical to original. Three planning batches separately reviewed/frozen before implementation.

## Initial pause: date issue (subsequently approved and fixed)
On Sat 14 Nov 2026 the unchanged boundary test uses today and tomorrow. Tomorrow is Sunday (day index 6), forbidden by time_slots_day_of_week_check. original-saturday-boundary.txt reproduces the SAME failure in the original HEAD file copied outside the working tree (1 test, 6 assertions, error), proving it predates the repair. Local Carbon callback in the repaired method restores its clock and is not the cause. Do not change schema, add Sunday offers or weaken assertions. Scope approval was requested at this checkpoint and then received; completed receipts are below.

## Approved second repair — final receipts

User approved the second boundary method date setup. Re-reviewed/re-froze proposal, design and tasks in sequence before delegated code edit. Only that method additionally changed: callback clock fixed to Thu 12 Nov 2026 09:00 UTC, with coordinate guards week11/day3 and tomorrow week11/day4. Original requests/exceptions/both approved assertions retained; earlier occurrence repair unchanged. At that instant campus date is also Nov12 and local time17:00 is past the16:00 class, so the date-only approved boundary is meaningfully exercised. No global clock or timezone edits.

- db-target-r2.txt: actual PHP current_database verified class_replacement_testing.
- both-focused.txt: **2/2 pass, 48 assertions**.
- file-r2.txt: **8/8 pass, 97 assertions**.
- related-r2.txt: **49/49 pass, 323 assertions**.
- lint-r2.txt / phpstan-r2.txt: PASS, no suppressions/baselines.
- implementation-review-r2.txt: independent read-only scoped PASS; original requests/assertions preserved, callback restoration, no other methods/helpers/first-repair changes.
- full-suite.txt: DB_DATABASE=class_replacement_testing vendor/bin/phpunit --colors=never → **721 tests, 719 passed, 6,066 assertions, 2 errors**, 587.263sec. Earlier history-test errors are absent. This receipt supersedes any old full-suite count but does not constitute a clean full-suite result.
- other-date-errors-isolated.txt: both remaining errors reproduce in a fresh separate PHPUnit process with only those unchanged two tests, ruling out a leaked mock clock from the repaired file. git diff for both files is empty.

### Remaining two errors — report, do not silently expand
1. MyReplacementsPayloadTest::test_completed_is_derived_and_the_boundary_is_today (line314): today/tomorrow on Saturday creates a Sunday class_session day6, rejected by class_sessions_day_of_week_check.
2. ReplacementHomeTest::test_home_reads_the_effective_status_per_the_date_matrix (line276): same Saturday→Sunday issue for a time_slots row, rejected by time_slots_day_of_week_check.

Both files are unchanged by this task. These are now the only errors reported by this full run; do not weaken the Sunday restriction, patch app behavior or claim no future failures. Ask permission for the two additional date-fixture repairs.

## Final two authorised fixtures — completed and full backend green

User approved the two remaining named fixtures. Proposal/design/tasks were re-reviewed in order before coding. Only their respective method bodies changed: MyReplacementsPayloadTest derives a Thu09UTC freeze from a copy of its existing relative-semester start (+3weeks,+3days), so it cannot age outside that term; ReplacementHomeTest uses fixed Thu12Nov2026 09UTC within its fixed term. Added exact yesterday/today/tomorrow coordinate guards (week4/day2–4 and week11/day2–4). All old status/label/day-count/stored-no-write assertions retained. Callback clocks restore automatically; no globals/helpers/setup/schema/app changes. Earlier two fixes byte-identical (sha256 check).

Final actual receipts in /tmp/crs-history-test-fix/:
- db-target-r3.txt: actual testing DB proof.
- final-two-focused.txt: **2/2 pass, 33 assertions**.
- three-files.txt: **31/31 pass, 308 assertions** (ReplacementHistoriesTest, MyReplacementsPayloadTest, ReplacementHomeTest).
- lint-r3.txt / phpstan-r3.txt: PASS.
- implementation-review-r3.txt: independent scoped PASS.
- **full-suite-r2.txt: DB_DATABASE=class_replacement_testing vendor/bin/phpunit --colors=never → 721/721 PASS, 6,090 assertions, 571.751sec.** Completed 11:44 local, inside original approx10:46–11:46 box. No other tests were weakened or skipped and no unrelated failure was fixed without approval.

This resolves the earlier backend-test caveat. It is NOT a claim that all cancelled-slot-reuse acceptance/delivery tasks or its final SDD Verify/archive are complete. No new browser work or demo operations.

## Safety and delivery
Only four explicitly approved test methods/name/comments edited across three existing test files. No production edits, manual reseed/reset, browser run/server restart, new dependencies, scheduler change, demo DB command, commit or push. Framework RefreshDatabase runs on the disposable testing DB; future browser runs need a guarded baseline rebuild if empty. Mixed existing UI/context/demo-log work preserved. Original 60-minute box started about 10:46 local; final checks/report completed before it expired.

## Approved commit delivery
User answered yes to committing these test fixes after the final 721/721 pass. The commit containing this entry includes ONLY the three test files and this change's seven SDD artifacts. Mixed handover edits remain unstaged to avoid including other sessions' diary changes. No production code, page/UI edits, demo-data log, unrelated archive/picker/guide work, dependencies or secrets included. Commit-only operation: no new DB command, server restart, scheduler action, push or automatic archive. Separate cancelled-slot-reuse final acceptance Verify remains parked.
