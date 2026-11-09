## Student Side UI Update & SDD Investigation — Replacement History

**Page:** Student Side — `Replacement History`

**Reference:** Staff-side `my-replacements` page.

**Goal:** Make the Student Replacement History consistent with the Staff-side page in table structure, UI design, status badges, labels, date/time formats and displayed information.

### 1. SUBJECT → COURSE

- Rename the `SUBJECT` column to `COURSE`.
- Update the hover tooltip to display:

  `Subject or Course Code, Course Name (Type)`

- Example: `AMSE2003, Operating Systems (Lecture)`.
- Retrieve the full course name and class type from the actual data.

### 2. Add CONFLICT REASON Column

- Add a `CONFLICT REASON` column to the table.
- Display the relevant conflict reason for each replacement request.
- Use the actual conflict information from the request data.
- Follow the Staff-side table design and styling.
- Do not display misleading or generic reasons when no conflict exists.

### 3. NEW SLOT → REQUESTED SLOT

Rename the `NEW SLOT` column to `REQUESTED SLOT`.

### 4. ORIGINAL SLOT & REQUESTED SLOT — Display Format

Make both columns follow the same format as Staff-side `my-replacements`.

**Expected display:**
```text
Tue, 3 Nov 2026 (Week 7)
Overdue (3 Working Days Ago)
2:00 PM ~ 4:00 PM (2 hours)
[Venue Icon] B009
```

Requirements:
- Display the date and academic week on the first line.
- Display the urgency label below the date.
- Display the time and duration on the next line.
- Display the venue icon and venue code on the last line.
- Use the existing urgency colours and working-day calculation rules from the Staff side.
- Apply consistently to both ORIGINAL SLOT and REQUESTED SLOT.

### 5. STATUS → REQUEST STATUS

- Rename the `STATUS` column to `REQUEST STATUS`.
- Use the same status badge design as Staff-side `my-replacements`.
- Follow the existing CSS class naming convention: `badge status-*`.
- Match the Staff-side badge colours, background, borders, padding, font styling and other visual details.
- Apply the correct badge class according to each request status.
- Do not introduce a separate Student-side badge design.

### 6. Week Filter — Clarify "All Weeks"

Currently:
```html
<option value="all">All Weeks</option>
```

Update the label to clearly identify which class date the filter refers to.

- Check the existing `weekFilter` logic first.
- If it filters by Original Class date, use `All Original Class Weeks`.
- If it filters by Requested Replacement date, use `All Requested Replacement Weeks`.
- Update the active filter chip to use the same naming convention.

Do not change the filtering logic unless an inconsistency is found.

### 7. Pending Status — Display Requested Slot Information

**Current Behaviour:**

The REQUESTED SLOT column displays:
`Awaiting PL approval`

**Expected Behaviour:**

For requests with **Pending** status, display the actual requested class information in the REQUESTED SLOT column, using the same date, urgency, time, duration and venue format described in Section 4.

- Do not replace the requested slot information with `Awaiting PL approval`.
- Keep the Pending status clearly visible in the REQUEST STATUS column.
- Preserve the existing PL approval workflow and status logic.

### 8. SDD Investigation — Other Inconsistencies

Use `/sdd-explore` to explore the Student Replacement History beyond the changes listed above.

Compare it with the Staff-side `my-replacements` page and investigate:

- Missing or outdated table information.
- Different date, time and duration formats.
- Incorrect urgency labels, colours or working-day calculations.
- Missing or inconsistent tooltips.
- Different status badge designs or CSS classes.
- Missing or incorrect conflict information.
- Filter options, filter chips and filtering behaviour.
- Pending, Approved, Cancelled and Completed request handling.
- Incorrect or missing requested/original class information.
- Broken buttons, links, modals or interactions.
- Responsive layout and table overflow issues.
- Any other outdated or inconsistent behaviour.

Do not assume every difference is a bug. Consider the intended Student-side functionality and permissions.

### 9. Implementation & Verification

Implement the changes in Sections 1–7.

For Section 8:
- Investigate and report additional findings.
- Separate confirmed bugs from possible improvements.
- Do not fix unrelated issues without my approval.

Verify:

1. All table columns display the correct information.
2. Both slot columns follow the expected format.
3. Pending requests display their actual requested slot information.
4. Status badges match the Staff-side `badge status-*` design.
5. Week filtering and active filter chips work correctly.
6. Urgency labels match the Staff-side business rules.
7. Existing Student permissions and request workflows remain unchanged.
8. No unrelated Staff-side functionality is affected.

**Restrictions:**
- Preserve existing database records and booking rules.
- Do not delete or overwrite existing data.
- Do not modify unrelated pages or functionality.
- Report completed changes, affected files and verification results first.
- Present additional SDD findings separately and wait for my approval before fixing them.
