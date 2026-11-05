## UI/UX & Behaviour Update — Venue Timetable Slot Validation

**Page:** `venue-timetable-ui`

### 1. Current Behaviour and Problem

Example:

- Venue: `B002`
- Date: Monday, 16 Nov 2026 (Week 9)
- Existing class: `MPU-3232 (L)`, 2:00 PM–3:00 PM.

When users hover over available cells before the existing class:

| Selected cell | Current hover behaviour |
|---|---|
| 1:30 PM | `Not Enough Slot` |
| 12:00 PM | Shows `Arrange a replacement...` |
| 12:30 PM | Shows `Arrange a replacement...` |
| 1:00 PM | Shows `Arrange a replacement...` |

The current problem is that clicking the available tooltip's `tooltipBookBtn` can trigger a warning:

`None of your classes can run in B002 at this cell. 13:00 plus the class length runs into an existing event below. NOT ENOUGH FREE TIME BELOW— pick a Free Cell.`

This warning appears too early because the system does not know which class or class duration the user intends to arrange yet.

### 2. Required Behaviour — Separate Two Validation Stages

**Stage 1: Hover and Slot Selection**

Keep the existing hover and `intentPickers` behaviour.

- Continue checking the minimum required slot length of **one hour**.
- If a cell cannot provide at least one continuous hour before the next existing event, keep showing `Not Enough Slot`.
- Example: Hovering over 1:30 PM must continue showing `Not Enough Slot` because the existing class starts at 2:00 PM.
- For 12:00 PM, 12:30 PM and 1:00 PM, continue showing the existing `Arrange a replacement...` tooltip.
- Do NOT show the `NOT ENOUGH FREE TIME BELOW` warning at this stage.
- Allow users to select their intended class through the existing intent pickers.

**Important:** Do not assume the intended class duration during hover. The actual duration is only known after the user selects a class.

**Stage 2: Validate After Class Selection**

After the user selects an intended class through the existing intent picker:

1. Retrieve the selected class's actual duration.
2. Check whether the selected venue and starting time can accommodate the entire duration continuously.
3. Check for conflicts with existing events below the selected time.
4. If the full duration fits, allow the existing booking/navigation flow to continue.
5. If the full duration does not fit, show a new warning modal.

### 3. New Warning Modal — Insufficient Free Time

<badge color="warning">Warning state</badge>

Display a warning-style modal when the selected class duration exceeds the available continuous time before the next existing event.

**Example scenario:**

Staff `5770` selects:

`BMIT2013 (L) · Week 7 · Tue, 3 Nov 2026 · 2:00 PM–4:00 PM`

This class requires **2 hours**.

The user then selects B002 on Monday, 16 Nov 2026:

| Selected start time | Available time before existing class | Result |
|---|---:|---|
| 12:00 PM | 2 hours | Allow |
| 12:30 PM | 1.5 hours | Show warning |
| 1:00 PM | 1 hour | Show warning |
| 1:30 PM | 30 minutes | Keep existing `Not Enough Slot` behaviour |

**Warning message:**

`None of your classes can run in B002 at this cell.`

`1:00 PM plus the selected class duration runs into an existing event below. NOT ENOUGH FREE TIME BELOW — pick a Free Cell.`

- Use the actual selected start time, venue and class duration.
- Use the existing conflict explanation where possible, but display it in a new warning-style modal.
- Do not use a browser alert.
- Provide a clear way to close the warning and return to the timetable.
- Do not proceed with booking or navigation when validation fails.

### 4. Successful Booking Flow

If the selected class duration fits the available time, preserve the existing `intentArrangeBtn` behaviour.

Example:

When the user selects a 2-hour class and chooses B002 at 12:00 PM, navigate to:

`/replacement-arrangement?venue=B002&date=16%20Nov%202026&time=12%3A00&from=venue-timetable&code=BMIT2013&session=24&cohort=RSD2%20(S1)%20G2&week=7`

Use actual selected class and timetable data to build the URL dynamically. Do not hardcode the example values.

### 5. Important Requirements

- Keep the existing `intentPickers` and their selection flow.
- Do not reject a slot based on an assumed class duration.
- Validate the actual selected class duration only after class selection.
- Keep the existing minimum one-hour hover validation.
- Prevent the user from proceeding when the selected duration overlaps an existing event.
- Allow valid slots to continue through the existing booking flow.
- Preserve unrelated timetable behaviour, tooltip design and functionality.
- Avoid unnecessary changes to other pages or booking logic.

### 6. Verification

Test at least these cases:

1. Hover at 1:30 PM before a 2:00 PM class → `Not Enough Slot`.
2. Select a 2-hour class and choose 12:00 PM → booking flow continues.
3. Select a 2-hour class and choose 12:30 PM → warning modal appears.
4. Select a 2-hour class and choose 1:00 PM → warning modal appears.
5. Select a 1-hour class and choose 1:00 PM → booking flow continues if no other conflict exists.
6. Confirm the warning does not appear before the user selects an intended class.

**Do not change unrelated functionality. Report the files changed and test results.**
