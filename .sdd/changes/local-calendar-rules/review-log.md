# review-log — `local-calendar-rules` (2026-11-08)

## Why this is smaller than first reported

The first S2 note claimed the staff booking rules were 8 hours loose. **Wrong** — `WorkingHours`
already converts every instant to `Asia/Kuala_Lumpur` before reading a calendar (`at()`), so FR 2.17,
the 3-working-hour auto-reject, holidays and the working-minute maths were already campus-correct.
What was wrong: a handful of *calendar* reads using the UTC date.

## Receipts

- `tests/Feature/LocalCalendarRulesTest.php` — **6/6**. Frozen at Mon 9 Nov 2026 00:30 MYT
  (UTC still says Sunday 8 Nov):
  - the two clocks disagree about the date (the reproduction);
  - `daysSince` counts campus days (49 / 1 / 0);
  - **the staff working-hours rules are unchanged at that instant** — cutoff 2026-11-12,
    11 Nov not bookable, 12 Nov bookable, a 10:00 start too soon, a 12:00 start fine;
  - a class dated campus-yesterday reads **Completed** (was Approved until 08:00), one dated
    campus-today stays Approved, sentence says "1 day ago";
  - the landing week is **Week 8** (the UTC clock answered Week 7);
  - `Semester::active()` picks the semester whose first day is the campus date.
- **Red-check:** with `LocalTime::today()` temporarily reverted to `Carbon::now('UTC')`, **5 of the 6
  fail** — and the staff developer-facing one (working hours) still passes, which is exactly the
  claim: the staff rules were never the broken part.
- Broad suite (12 name filters: WorkingHours, SubmitGates, AutoReject, payloads, home, histories,
  local-calendar, venue rules, seed invariants, catalog, arrangement, engine): **165 passed / 168 —
  the 3 failures are the known weekend-only fixtures** (`today()->addDay()` lands on a Sunday, which
  the `day_of_week 0–5` CHECK refuses), identical before this change.
- `composer run lint:check` passed; `phpstan` 0 errors.
- Browser sanity (testing DB): staff `/my-replacements`, `/replacement-arrangement`,
  `/request-approval-ui`, `/venue-timetable-ui`, `/cohort-timetable` and the student timetable all
  load; **zero console errors**; the student page still lands on Week 7 with `Sun 8 Nov 2026` badged
  Today (the S1 fix holds).

## Risk as shipped

No stored value changed, no schema change, no migration, no dependency. Rollback = revert one commit.
