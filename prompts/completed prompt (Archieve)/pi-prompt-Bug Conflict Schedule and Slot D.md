# Bug: Conflict Schedule and Slot Dropdown Become Inconsistent

## 1. Environment
- **Login:** Staff `5425`
- **URL:** `http://127.0.0.1:8000/replacement-arrangement?session=68&week=14&from=replacement-home`

## 2. Steps to Reproduce

1. Open the URL above.
2. Confirm the Conflict Schedule displays:
   - **Subject:** AMSE2003 (T)
   - **Week:** 14
   - **Date:** Thursday, 24 December 2026
   - **Time:** 11:00 AM–12:00 PM
   - **Venue:** B100
   - **Cohort:** DSF2 (S1) G1
3. Select any available timetable slot.
4. Click the `slotTrigger` dropdown.
5. Select the following conflicting item (`data-index="1"`):

   ```html
   <div class="slot-dd-item" data-index="1">
       <span class="slot-dd-item-star" data-index="1" data-tip="Toggle favourite">★</span>
       <span class="slot-dd-item-label">
           <span class="cell-type-label">(T)</span>
           W14 · Dec 24, 11:00 AM - 12:00 PM @ B100
       </span>
       <span class="slot-dd-item-badge">Conflict</span>
   </div>
   ```

6. When the **"Change Time Slot?"** confirmation modal appears, click **"No, Keep My Selection"**.
7. Observe the `slotTriggerText` and Conflict Schedule.

## 3. Actual Behaviour

After clicking **"No, Keep My Selection"**:

- The dropdown text resets to `Select a slot to replace`.
- The Conflict Schedule still displays the original AMSE2003 occurrence.
- The dropdown and Conflict Schedule appear inconsistent.

Relevant DOM:

```html
<span id="slotTriggerText">Select a slot to replace</span>
```

## 4. Expected Behaviour

Investigate the intended behaviour when the user cancels a time-slot change.

- The previously selected slot and its displayed text should remain consistent, if the action is intended to cancel the change.
- The Conflict Schedule should continue to represent the correct selected occurrence.
- If cancelling is supposed to clear the selection, the UI should update all related components consistently.

**Do not assume which behaviour is correct until you inspect the existing selection and confirmation-modal logic.**

## 5. Investigation Tasks

Use the existing SDD workflow to investigate the root cause.

- Trace the `slotTrigger` selection and cancellation logic.
- Check how **"No, Keep My Selection"** handles the previous selection and current selection.
- Inspect how `slotTriggerText` and the Conflict Schedule strip are updated.
- Determine whether the modal cancellation handler clears or restores the wrong state.
- Check for related state-synchronisation issues involving slot selection, dropdown labels, conflict details, and confirmation modals.
- Use Playwright to reproduce the issue and verify the state before and after cancellation.
- Inspect relevant JavaScript, Blade templates, backend logic, and existing SDD specifications as needed.

## 6. Constraints

- **Investigate only. Do not fix the bug yet.**
- Do not modify application code, database records, or existing requests.
- Do not create or modify SDD lanes without approval.
- Do not report suspected causes as confirmed without evidence.
- Follow the repository's existing SDD workflow.

## 7. Required Report

Provide a concise report containing:

1. **Root cause:** Why the dropdown resets while the Conflict Schedule remains unchanged.
2. **State comparison:** Selection and displayed values before selecting the conflicting item, after the modal appears, and after clicking "No, Keep My Selection".
3. **Expected behaviour:** What the existing design and requirements indicate should happen.
4. **Related bugs:** Any similar state-synchronisation problems discovered.
5. **Proposed fix:** The smallest suitable change and regression tests.

**Stop after the investigation and proposed fix. Wait for my approval before making changes.**
