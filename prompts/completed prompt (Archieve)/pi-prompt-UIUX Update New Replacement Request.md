## UI/UX Update: New Replacement Request Notifications

Update the **New replacement request** notification layout and hover tooltip.

### 1. Notification Title

Change:

`New replacement request`

To:

`New replacement request (ID #76)`

Use the actual request ID dynamically.

### 2. Add Requester Subtitle

Add a new subtitle displaying the requester's **staff name and staff ID**.

Display the requester's full name followed by their staff ID in parentheses.

Example: Poong Foo Jing (5425)

### 3. Update Notification Description

Display the proposed schedule and venue instead of the requester's name.

Format:

`Week {N} · {Day}, {Time Range} · {Venue Code}`

Example: `Week 7 · Friday, 09:00–11:00 · B002`

### 4. Improve the Hover Tooltip

For active notification rows (`.notif-row` and `.notif-row--read`), display the complete request information in a neatly organised, multiline tooltip.

**Format:**

```text
Staff: {Staff ID} — {Full Name}
Requested at: {Timestamp} (){Relative Time} ago)
Proposed Schedule: Week {N} · {Day}, {Date} · {Time Range}
Proposed Venue: {Venue Code} — {Venue Name}
Subject: {Subject Code} ({Type}) — {Subject Name}
```

**Example:**

```text
Staff: 5425 — Stephen Curry
Requested at: 29 Oct 2026, 10:44 AM (5 minutes ago)
Proposed Schedule: Week 7 · Friday, 6 Nov 2026 · 09:00–11:00
Proposed Venue: B002 — Tutorial Room
Subject: AMSE2003 (L) — Subject Name
```
Requirements:
- Display the actual request timestamp and calculate the relative time dynamically.
- Keep each information item on a separate line.
- Reuse the existing tooltip design and positioning.
- Populate all values dynamically; do not hardcode example data.


### N+1. Inactive State for Read and Actioned Notifications

A **New Replacement Request** notification must become inactive when any of the following conditions is met:

- **Read:** The recipient has already read the notification.
- **Approved:** The Programme Leader has approved the replacement request.
- **Rejected:** The Programme Leader has rejected the replacement request.

Inactive notifications must use the existing `.notif-row--inactive` styling and must not appear as active notifications.

### N+2. Cancelled Request Tooltip

For a New Replacement Request notification where the requester has cancelled the request, use the `.notif-row--inactive` class and update its tooltip to clearly explain the cancellation.

**HTML example:**

```html
<span
    class="notif-row notif-row--inactive"
    data-tip="This Notification is inactive -- the Request was CANCELLED by the Requester."
    aria-label="New replacement request — AMSE2003 (T) · W7 · submitted by Surayaini Binti Basri (inactive)"
>
```

**Requirements:**
- Use the exact tooltip message: `This notification is inactive because the request was cancelled by the requester.`
- Apply this message only when the request is cancelled by the requester.
- Populate the notification details dynamically.
- Preserve the existing inactive notification behaviour and styling.

### N+3. Automatically Categorise Inactive Notifications as READ

All notification rows with the `.notif-row--inactive` class must automatically belong to the **READ** category, regardless of their previous read/unread status.

**Requirements:**
- Inactive notifications must not appear under the **UNREAD** category.
- Automatically classify inactive notifications as **READ** in the notification category/filter logic.
- Preserve the existing inactive styling and behaviour.
- Do not incorrectly mark genuinely active, unread notifications as READ.
- Ensure the notification category and displayed read/unread status remain consistent.

### Requirements

- Populate all values dynamically using the actual request data.
- Support multiline tooltips with clear spacing and alignment.
- Reuse the existing tooltip design and positioning.
- Preserve existing notification navigation, read/unread behaviour, and inactive-notification handling.
- Do not hardcode example values or modify unrelated notification logic.
- Test the changes using Playwright.
- Verify these behaviours across all relevant notification pages using Playwright.
