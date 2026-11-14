# Cancelled-slot reuse — work in progress

## 2026-11-14 — half-hour overlap regression fixed

Staff 5770 exposed a missed 30-minute intersection: pending 1:00 PM ~ 2:00 PM versus cancelled 1:30 PM ~ 2:30 PM. Stored intervals used an exclusive end but comparisons received an inclusive candidate end. Both sides now use the same half-open interval. Components form in time order before cancellation-first lane allocation; each component reuses lanes from zero. Original event coordinates and durations are unchanged.

- **18/18** planner/layout browser cases pass, plus **3/3** separately executed auth setups. New coverage includes the exact report, an independent **1,764-pair** cell-occupancy oracle with reversed input, and actual desktop column-position checks. Existing venue availability, responsive 1440/768/390, both-theme and print checks remain green.
- Baseline independent oracle found **124/1,764 mismatches**. An isolated browser supplied with the original JS also reproduced one row instead of two against the user's real request. The live JS was not rolled back for that check.
- Actual testing request **19**, source **2673**, week **10**, now shows cancellation above pending with the true 30-minute overlap. Desktop/phone captures in both themes are in `/tmp/crs-half-hour-overlap/`; main inspected desktop light and phone dark. This live-booking check used no synthetic payload and no booking action. Synthetic regression fixtures are disclosed separately.
- Lint, PHPStan 1G, JS syntax and scoped independent review pass. No PHP/Blade/CSS edit, booking/data reset, scheduler change, commit or push. Testing :8030 stays open for the user's manual work. Whole-feature Verify and demo cleanup remain separate.


## 2026-11-13

Scope: My Timetable, Cohort Timetables, Student My Timetable and Venue Timetable. SDD: `.sdd/changes/cancelled-slot-reuse/`.

- Cancelled occurrences and new bookings have separate event identities. Shared lane layout keeps both blocks and their real time ranges.
- Pending and approved booking spans are read from their junction rows, with the stored-span fallback for old rows without links.
- A current exception plus an exact, non-maintenance cancellation audit identifies user cancellation history. An approval-only `official_event` stamp does not create that history.
- Venue booking cells use the actual room-cell state. Cancelled history cannot make a still-occupied or missing cell appear free. A separate base row exposes genuine free cells below history when needed.
- Summary inputs use event identity rather than day/start coordinates. History and layout-only rows do not add teaching hours or occupied room cells.
- On phones, free base-row cells now show their 12-hour time range and have at least 44px tap height. The change is local to the new base offers; desktop geometry and ordinary cells are unchanged.
- Existing guide, mobile, summary-card and popup work is preserved. No new dependencies, schema changes or print redesign.

### Checks so far

- Targeted backend: **171 passed, 2,813 assertions** on `class_replacement_testing`.
- Lint, PHPStan, JavaScript syntax, Blade compilation and whitespace checks pass.
- Pure lane planner: **11 passed**. Shared layout browser cases: **4 passed**, plus 3 auth setup cases.
- Lifecycle browser: **8 passed**, plus 3 auth setup cases. Includes selected cancel, exact-slot request/approval, rejection, withdrawal, 390px mobile booking and automatic rejection, other-staff and student privacy. The automatic rejection uses the disclosed guarded action/clock seam; the scheduler is unchanged.
- Layout/planner rerun: **15 passed**, plus 3 auth setups. Saved 1440/768/390 light/dark and print-emulation captures; mobile labels and ≥44px targets have DOM assertions.
- Full backend: **708 tests, 707 passed, 5,877 assertions, 1 known stale ReplacementHistoriesTest error** at line 302. No clean full-suite claim.
- Independent approval and display/payload reviews: **PASS for their slices**, not whole-feature Verify.

### Later continuation: older regressions and Part B

The five older browser failures are fixed. The combined selected-day/venue/ownership run now passes **24/24 (21 cases + 3 auth setups)**, including the three formerly unrun ownership checks. Fixtures discover the correct source by staff/module/type instead of class ID 39, use a future landing week and real room offers, and restore only their own checked rows. Tooltip expectations follow the existing full-weekday/12-hour wording. The venue audit checks live events and the room matrix independently. Repeated whole-day tests now select the exact 1-based source week rather than cancelling the following week. No booking rule was relaxed.

Part B resolves active room holders from proven request receipts or the stored-span fallback before considering original class geometry. Unproven claims, including drifted fallback tails and available anchor twins, cannot manufacture a NORMAL holder. Public blocks use the shared 12-hour full range, permitted lecturer/cohort facts, and NORMAL/PENDING/REPLACEMENT. Factual identity merges duplicate calendar arms while keeping different holders separate. The existing phone sheet consumes the same safe text; there is no second renderer.

Current checks: **98/98 backend tests, 1,712 assertions**, plus an extra focused arrangement run; **26/26 lifecycle/layout/planner browser checks (23 cases + 3 setups)**; lint, PHPStan 1G, JS syntax and Blade compilation pass. Independent backend review first found two fallback edge cases; both were fixed, tested and passed rereview. New Part B browser checks pass **8/8 (5 cases + 3 auth setups)**, including real room-only full-span holder text on desktop and phone in both themes, plus the disclosed synthetic identity/escaping checks. Main inspected all four PNGs under /tmp/crs-reconcile-b/room-holder-results-r5/. A final combined run of all seven browser specs passed **52/52 (49 cases + 3 shared auth setups)**, with no skips or unrun cases; /tmp/crs-reconcile-b/integrated-browser.txt. Do not infer a clean full-suite or final Verify from these scoped checks.

Part A was committed separately as **b1f13fd** with the user's approval; it has not been pushed. User subsequently approved separate commits for the test fixes (**ca98fb5**) and Part B (the feature commit containing this entry). Push remains unauthorised. Existing demo cancellations have not been cleaned up; the operational gate remains closed. The unrelated history-shape test error and final Part A/whole-feature Verify remain open.

Exact receipts and continuation steps: `.sdd/changes/cancelled-slot-reuse/apply-log.md`.
