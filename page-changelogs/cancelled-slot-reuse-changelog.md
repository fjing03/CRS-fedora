# Cancelled-slot reuse — work in progress

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

Older browser regressions are not clean: the combined selected-day/venue/ownership run has 16 passes, 5 failures and 3 not run. Selected-day cancellation passes. The ownership fixture uses missing fixed class id 39; venue failures include old time-copy expectations and fixture/availability assumptions that still need classification. Final SDD Verify and Part A delivery gates remain open.

Existing demo cancellations have not been cleaned up. The cleanup tool remains separately gated; no demo write, commit or push was approved or performed. Part B room-holder explanations remain pending.

Exact receipts and continuation steps: `.sdd/changes/cancelled-slot-reuse/apply-log.md`.
