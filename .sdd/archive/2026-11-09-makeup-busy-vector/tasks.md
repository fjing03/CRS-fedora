# tasks — `makeup-busy-vector` (2026-10-27)

## T1 — the engine
- `MatrixIntersectionEngine`: NEW private `makeUpRanges(int, int): array`
  (design D1 — the double filter: `time_slots.status ∈ {pending,
  occupied}` + `replacement_requests.status ∈ {pending, approved}`).
- `lecturerBusyRanges()`: merge (owner-scoped — the rr's class_session.
  lecturer_id = the vector's `$lecturerId`).
- `cohortBusyRanges()`: merge (cohort-scoped via the join — design D2).
- `phpdoc` updates on both functions (the ranges' shape unchanged).

## T2 — the holder labels (the TEMP-HOLDER-LABEL rider)
- `ReplacementArrangement::busyCellHolderLabels`: the make-up rows'
  holder text unions the same `[weekIdx][day:start:end]` key (code +
  ` · Replacement` + cohorts).

## T3 — the new test file
- `tests/Feature/MakeUpBusyVectorTest.php` — the 6 receipts (design D5):
  the repro (false), the boundary (13:00/14:30 unblocked), the
  pending+approved both count, the owner scoping (another lecturer's
  make-up stays out of MY lecturer vector / in the cohort vector when
  the cohorts overlap), the cancelled exclusion, the UI delegation
  (busyByWeekPayload's cells carry the makeup tuples).
- THE EXISTING suite's rows may shift (fixtures whose seeders create rr
  rows now enter the vectors): the receipts that flip ARE re-cut by T3
  without weakening a receipt — the suite green IS acceptance #5 (the
  reviewer's 🔴2 resolved: one policy, no half-order).

## T4 — the changelogs
- `replacement-arrangement-changelog.md` (+ the occupied-cell aria via
  the shard's 4th tuple), `my-timetable-changelog.md`,
  `venue-timetable-ui-changelog.md`.
- ONE `feat:` commit. NO PUSH (the standing standing law).

## T5 — the gates
- pint + phpstan-1G + the full phpunit + node --check (if any JS).
