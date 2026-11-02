# Bug Fix: Conflicted Subject Does Not Auto-Select After Changing Subjects

## 1. Problem Description

**Page:** `/replacement-arrangement`  
**Application:** `http://127.0.0.1:8000`  
**Test account:** Staff 5425

When a user opens a conflict schedule, the conflicted class slot is initially pre-selected in the dropdown using the CSS class:

`slot-dd-item selected`

However, after the user changes the **Conflicted Subjects**, the corresponding conflict slot is no longer automatically selected.

**This is incorrect. The matching conflict slot should remain automatically selected after the subject changes.**

## 2. Reproduction Steps

Use staff account **5425**.

1. Open any of these pages:
   - `/my-timetable`
   - `/cohort-timetable`
   - `/venue-timetable`
   - `/replacement-home-ui`

2. Click the first row labelled **Replacement Arrangement**.

3. Open the following conflict schedule:

   **AMSE1003 — Software Engineering (L)**

   - Week 8
   - Monday, 9 November 2026
   - 9:00 AM–11:00 AM
   - Venue: B111
   - Cohort: DSF1 (S1) G1

4. Observe that the matching conflict slot is initially pre-selected.

5. Change the **Conflicted Subjects** selection.

6. Observe that the corresponding conflict slot is no longer pre-selected.

## 3. Expected Behaviour

Whenever the user changes the **Conflicted Subjects** selection:

- Identify the conflict slot that matches the currently selected subject and conflict schedule.
- Automatically select the matching slot in the dropdown.
- Apply the `selected` class to the correct `.slot-dd-item`.
- Ensure the dropdown's selected state and its underlying JavaScript state remain consistent.
- If the matching slot is already selected, preserve the selection without unnecessary changes.
- Do not automatically select an unrelated slot.

For the reproduction scenario, the expected selected item represents:

`AMSE1003 — Software Engineering (L), Week 8, 9:00 AM–11:00 AM, B111, DSF1 (S1) G1`.

**Important:** Do not hardcode this particular subject, week, venue, or cohort. The fix must work dynamically for other conflict schedules.

## 4. Relevant HTML

The matching dropdown item currently has this structure:

```html
<div class="slot-dd-item" data-index="0">
    <span class="slot-dd-item-label">
        <span class="cell-type-label">(L)</span>
        Week 8 · Nov 09, 2026 · 9:00 AM - 11:00 AM @ B111 ·
        <span class="slot-dd-item-cohort">
            DSF1
        </span>
    </span>

    <span class="slot-dd-item-badge"
          style="background:var(--color-error-container);color:var(--color-on-error-container);">
        Conflict
    </span>
</div>
```

Initially, the matching item has the additional class:

```html
<div class="slot-dd-item selected" data-index="0">
```

After changing the conflicted subject, the matching item loses the `selected` class.

Use this HTML as a reference for the expected selection state. Inspect the actual DOM and JavaScript state before deciding how to fix it.

## 5. Investigation and Fix Requirements

### Step 1 — Identify the cause

Investigate the dropdown's selection logic and the code that runs when **Conflicted Subjects** changes.

Check whether:
- The selection state is reset when the subject changes.
- The matching conflict slot is not recalculated.
- The dropdown is re-rendered without restoring the selected state.
- The selected index becomes outdated after the available slots change.
- The DOM selection and JavaScript state become inconsistent.
- An event or callback that originally selects the conflict slot is not triggered again.

These are possible causes, not confirmed findings. Determine the actual cause before changing code.

### Step 2 — Implement the smallest appropriate fix

- Restore automatic selection of the correct matching conflict slot after the subject changes.
- Keep the dropdown's visual state and internal selection state synchronised.
- Preserve existing conflict detection and slot filtering logic.
- Support different subjects and conflict schedules dynamically.
- Avoid introducing duplicate event listeners or unnecessary re-rendering.

### Step 3 — Regression testing

Use Playwright or browser testing to verify:

1. Opening the original conflict schedule automatically selects the correct slot.
2. Changing the conflicted subject automatically selects the corresponding matching slot.
3. The selected slot has the `selected` CSS class.
4. The dropdown's internal state matches the selected DOM element.
5. Switching between multiple conflicted subjects selects the correct slot each time.
6. No unrelated slot is selected when the subject changes.
7. Existing dropdown interactions and conflict handling continue to work.
8. Reselecting the same subject or slot does not trigger unnecessary changes or confirmation modals.

Test the original AMSE1003 scenario and at least one other conflict schedule.

## 6. Constraints

- Fix the root cause rather than forcing the CSS class onto an arbitrary element.
- Do not hardcode the AMSE1003 scenario.
- Do not modify database records, database schemas, or migrations.
- Do not refactor unrelated functionality or redesign the UI.
- Preserve existing dropdown behaviour and other selection rules.
- Keep the changes limited to the affected selection logic.

## 7. Final Report

After implementing and testing the fix, report:

1. The confirmed root cause.
2. The files and functions changed.
3. How the fix restores automatic selection.
4. Playwright test results.
5. Any remaining issues or untested scenarios.

**Success criterion:** After the user changes the Conflicted Subjects selection, the correct matching conflict slot is automatically selected again, with the correct `selected` class and consistent internal state.
