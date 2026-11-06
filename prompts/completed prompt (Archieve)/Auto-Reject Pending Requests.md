### New Business Logic — Auto-Reject Pending Requests

**Rule:** The minimum required gap is **3 working hours**, based on TARUMT Sabah working hours.

**Working hours:**
- Monday–Friday: 8:30 AM–5:30 PM
- Exclude Saturdays, Sundays and Sabah Public Holidays.

**Auto-reject condition:**

Automatically reject a **Pending** replacement request if its proposed start date and time are within or exactly **3 working hours** from the current date and time.

**Calculation rules:**
- Count only time within the defined working hours.
- Do not count evenings, weekends or public holidays.
- When calculating across multiple days, resume counting from the next working period.
- The exact 3-working-hour boundary must trigger auto-rejection (`gap ≤ 3 working hours`).
- Requests with more than 3 working hours remaining must stay Pending.

**Examples:**

| Current time | Proposed start time | Working-time gap | Result |
|---|---|---:|---|
| Fri, 6 Nov, 11:30 AM | Fri, 6 Nov, 2:30 PM | 3 hours | Auto-reject |
| Fri, 6 Nov, 11:30 AM | Fri, 6 Nov, 3:00 PM | 3.5 hours | Remain Pending |
| Fri, 6 Nov, 4:30 PM | Mon, 9 Nov, 9:30 AM | 2 hours | Auto-reject |
| Fri, 6 Nov, 4:30 PM | Mon, 9 Nov, 11:00 AM | 3.5 hours | Remain Pending |

**Requirements:**
- Use the actual current date and time.
- Apply this rule only to Pending requests.
- Exclude Sabah Public Holidays when calculating working hours.
- Do not affect other request statuses.

### UI Update — Pending Status Tooltip

**Pages:**
- `my-replacements`
- `request-approval-ui`

**Target:** `.badge.status-pending`

Update the existing `data-tip` message to include the new auto-rejection rule.

**New Tooltip Message:**

`Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject). <br>
If the Programme Leader has not Approved this Request by {Earliest Approval Time}, it will be Automatically Rejected.`

**Requirements:**
- Calculate `{Earliest Approval Time}` dynamically based on the proposed class start date and time, minus 3 working hours.
- Follow TARUMT Sabah working hours: Monday–Friday, 8:30 AM–5:30 PM.
- Exclude weekends and Sabah Public Holidays.
- Display the actual calculated deadline for each request.
- Apply consistently to both pages.
- Preserve the existing Pending badge design and functionality.
