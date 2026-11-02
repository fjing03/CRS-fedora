# BUG Investigation — Venue Timetable & Cohort Timetable

**Target:** `http://localhost:8000`

Investigate the following reported bugs and identify their root causes. Use the working pages as references for the expected behaviour.

## 1. Missing Pending Since Date (Venue Page)

**Problem:** Pending classes belonging to the logged-in staff member (own sessions) are missing the pending date label on the Venue Timetable.

Expected:
`<span class="ev-note">(Pending since 02 Nov 2026)</span>`

Example affected element:
`event-mine-pending` for AMSE2003(P), B010, 2:00–3:00 PM.

**Reference pages (correct behaviour):**
- `/cohort-timetable`
- `/my-timetable-ui`

## 2. Cancelled Class Not Marked as Event Conflict (Venue Page)

**Problem:** Cancelled classes are displayed as normal events (`event-mine`) instead of being marked as `event-conflict`.

Example: BMSE3153(P), B009, 1:00–2:00 PM.

**Expected:** Follow the existing cancelled-class behaviour on:
- `/cohort-timetable`
- `/my-timetable-ui`

The Venue Timetable should correctly identify and visually distinguish cancelled classes.

## 3. Proposed Replacement Classes Missing (Venue Page)

**Problem:** Proposed Replacement classes are completely missing from the Venue Timetable.

Example class information:
- Subject: AMSE2003
- Type: Practical (P)
- Lecturer: Surayaini Binti Basri
- Cohort: DSF2(S1)G1
- Class Ref: #40
- Total Students: 15

**Reference:** `/my-timetable-ui`

Investigate why these proposed replacement events are not displayed on the Venue Timetable.

## 4. Incorrect "Replace Now" Button (Cohort Page)

**Problem:** A conflicted class that already has a Proposed Replacement should not display the `Replace Now` button.

Affected element:
`#btnReplaceNow`

**Expected:** Follow the existing behaviour on `/my-timetable-ui`.

Check whether the button visibility correctly considers the class's replacement status.

## 5. Find Additional Bugs

Perform a broader bug audit of the Venue Timetable and Cohort Timetable.

Look for:
- Incorrect event status or visual indicators.
- Missing or incorrectly displayed classes.
- Incorrect replacement status handling.
- Differences in class information between timetable pages.
- Incorrect button visibility or available actions.
- UI inconsistencies and potential data-display errors.
- Other related edge cases.

Do not report purely cosmetic differences unless they affect usability or consistency.

## Investigation Requirements

- Compare the affected pages against the reference pages.
- Trace the relevant frontend rendering, backend data, and status-handling logic.
- Identify the actual root cause instead of applying superficial UI fixes.
- Check whether multiple bugs share the same underlying cause.
- Avoid assuming that matching HTML appearance means the underlying data is correct.

## Strict Constraints

- **Investigation only. DO NOT fix anything yet.**
- Do not modify application code, database records, migrations, or configuration.
- Do not change existing business logic.
- Do not introduce unrelated refactoring.
- Preserve existing functionality.

## Expected Report

Present the findings in a table:

| ID | Page | Bug / Issue | Root Cause | Severity | Suggested Fix |
|---|---|---|---|---|---|
| 1 | Venue | Missing pending date | ... | ... | ... |
| 2 | Venue | Cancelled class status | ... | ... | ... |

Also include:
1. Findings for all 4 reported bugs.
2. Additional bugs discovered.
3. Relevant files and functions responsible.
4. Whether any bugs share the same root cause.
5. Recommended fixing order based on severity and dependencies.
6. Verification steps for each proposed fix.

**Stop after completing the investigation report. Wait for my approval before modifying anything.**
