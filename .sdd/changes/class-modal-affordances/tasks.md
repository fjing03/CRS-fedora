# tasks — `class-modal-affordances`

(≤ 2 h each; the FROZEN spec = design.md D1–D5. The acceptance ledger
runs at T8.

- [x] T1 — my-timetable: wire `CancelClass.renderButton(...)` (verbatim
  use, design D1) at the `classModal` open path (beside
  `currentModalEvent` fill), port the `onCancelled` callback body from
  the legacy MyTimetable template; ADD
  `@include('partials.ui-cancel-class-modal')` beside this blade's modal
  markup (it currently has NO cancel-modal include — the round-2 finding).
- [x] T2 — cohort-timetable: the same verbatim wiring at the `eventModal`
  open path (the `days`/`weekIndex` context from the cohort grid
  scoping); port the legacy CohortTimetable template callback; ADD
  `@include('partials.ui-cancel-class-modal')`; ADD the page-supplied
  `@section('modal-footer')` (left Close / right Cancel) BEFORE the
  `@include('partials.ui-class-detail-modal')` line.
- [x] T3 — venue-timetable: the `MockData.currentUser` ONE-LINE bridge; the
  same verbatim wiring at the `classModal` open path (legacy venue
  template port); ADD `@include('partials.ui-cancel-class-modal')`; ADD
  the `@section('modal-footer')` (left Close; right Replace Now
  [own+conflict, viewed week] + Cancel) BEFORE the
  `@include('partials.ui-class-detail-modal')`; the
  Replace Now gate = `e.lecturer === MockData.currentUser.name &&
  e.status === 'conflict'` → `goToReplacementFor(sessionId,
  currentWeek, 'venue-timetable')`; include the cancel-modal partial.
- [x] T4 — re-anchor `tests/cancel-class.spec.ts`'s 14 rows to the live
  deck per the regime (per-row receipts as COMMENT lines in the re-anchored spec file; the
  5770-owned BMIT2013 L Tue B009 anchor; status facts read, not
  assumed).
- [x] T5 — NEW Playwright rows IN A DECIDED HOME: the venue rows go INTO
  `tests/class-modal-affordances.spec.ts` (the new file, DECIDED); the
  cohort rows go into the SAME new file; the file = the lane's one place
  (rownames: cohort own/others; venue own-both/others-none/ReplaceNow-on-
  conflict-week/healthy-none).
- [x] T6 — changelogs: my-timetable / cohort-timetable / venue pages'
  receipts (the wiring + the restore-first persistence tradeoff worded
  honestly: visual-until-reload until the DB-persist lane).
- [x] T7 — gates: `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G`; `tests/cancel-class.spec.ts` 14/14 green on the LIVE
  pages; `tests/class-modal-affordances.spec.ts` green; the spot battery `--grep-invert "TC70"`; pint;
  phpstan-1G; phpunit full; running the stale-serve ritual before any
  manual verification; ONE conventional commit, explicit paths, NEVER
  push.
- [x] T8 — the acceptance ledger run: the review-log verify receipt (the
      8-task checklist vs the frozen artifacts) BEFORE the commit.
