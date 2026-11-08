## SDD Investigation — Student Side System Health Check

**Command:** `/sdd-explore`  
**Main Focus:** Student Side  
**Starting Page:** `student-my-timetable-ui`

### Background

The Student side has not received much attention for several months. I would now like to shift focus back to the Student side and review its overall condition.

I have noticed that the date displayed as **Today** on `student-my-timetable-ui` is inaccurate. It appears to show **5 Oct 2026**.

Please investigate this issue and check whether there are other outdated data, missing information, broken functions or inconsistencies across the Student side.

### 1. Date & Timetable Accuracy

Start with `student-my-timetable-ui`.

Check:
- Why the displayed Today date is inaccurate.
- Whether the page uses the correct system date and timezone.
- Whether timetable dates, weeks and weekdays are calculated correctly.
- Whether current and upcoming classes are displayed accurately.
- Whether past classes, holidays and academic weeks are handled correctly.
- Whether any dates or timetable information are outdated or hardcoded.

### 2. Full Student Side Review

Use `/sdd-explore` to explore the entire Student side, including its pages, features and related backend logic.

Check for:
- Outdated or incorrect data.
- Missing information or incomplete features.
- Broken buttons, links, filters, navigation or interactions.
- Incorrect timetable, class, venue or cohort information.
- Incorrect date, time or academic week calculations.
- Missing or incorrect notifications.
- Authentication and access-control issues.
- UI/UX inconsistencies and responsive layout problems.
- Features that no longer work with the current backend or database.
- Differences between Student and Staff functionality that may cause problems.

Do not assume every difference between Student and Staff is a bug. Consider the intended role and explain whether each difference is reasonable.

### 3. Data & Business Logic Audit

Trace important Student-side features to their actual database records and backend logic.

Check whether:
- Student information is linked to the correct cohort.
- Timetable data matches the actual class schedules.
- Class changes and replacement arrangements are reflected correctly.
- Students can see the latest approved replacement information.
- Cancelled, approved and completed class changes are handled correctly.
- Any old data, missing records or outdated business rules affect the Student side.

### 4. Comparison with Staff Side

Where relevant, compare Student-side behaviour with the existing Staff-side implementation.

Identify:
- Shared functionality that should behave consistently.
- Student-specific functionality that is missing or outdated.
- Differences caused by role permissions.
- Any Staff-side updates that have not been reflected on the Student side.

### 5. Investigation Report — No Changes Yet

Organise the findings into this table:

| No. | Page / Feature | Current Behaviour | Issue / Missing Item | Suggested Improvement | Priority |
|---|---|---|---|---|---|

Then provide:

1. **Overall Health:** General condition of the Student side.
2. **Date & Timetable Issues:** Findings from `student-my-timetable-ui`.
3. **Outdated / Missing Features:** Features or information that need attention.
4. **Data & Business Logic Issues:** Problems found in database or backend logic.
5. **Staff vs Student Comparison:** Important inconsistencies.
6. **Recommended Fix Order:** Suggested order based on impact and priority.

### Important Restrictions

- Use `/sdd-explore` for investigation.
- Investigation only. Do not modify code, UI, database, configuration or existing records.
- Do not assume something is broken without checking the actual implementation and data.
- Clearly distinguish confirmed bugs from possible issues and suggested improvements.
- Use simple, everyday English. Include evidence such as affected files, functions, queries or database records where relevant.
- Do not start fixing issues or implementing missing features yet.

**Final Goal:** Give me a clear picture of the Student side's current condition and a prioritised improvement plan. Present the report and wait for my approval before making any changes.
