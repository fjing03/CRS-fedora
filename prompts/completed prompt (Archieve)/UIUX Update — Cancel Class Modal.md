## **UI/UX Update — Cancel Class Modal**

### **1\. Modal Title & Width**

- Change the modal title from `Cancel Class` to **`Cancel this Class?`**  
- Increase the modal width by **40%** compared with its current width.  
- Keep the existing design style and ensure the modal remains responsive.

### **2\. This Class Section**

**Additional UI Design Requirement — Class Information Container**

- Group all **Class Information** fields inside a single grey background box.  
- Use the existing **Confirm Cancellation Modal** as the UI design reference.  
- Reference page: `/my-replacements`  
- Reference element: `<dl class="cancel-details" id="cancelConfirmDetails">`  
- Reuse its grey background, spacing, padding, border radius, and overall visual style where applicable.  
- Apply the design to the Cancel Class Modal's Class Information section only.  
- Keep the existing data and functionality unchanged.

#### **Class Information Section Title**

Change the section title from `This Class` to **`Class Information`**.

```html
<div class="detail-group-title">Class Information</div>
```

Keep the existing styling and design unchanged.

Update the class information layout as follows:

| Field | Expected Display |
| :---- | :---- |
| Subject | `BMSE2163 (P) — Software Engineering` |
| Week | `Week 10` — Add this field |
| Day & Date | `Thursday · 05 Nov 2026` — Display the full day name |
| Time | `12:00 PM ~ 1:00 PM (1 hour)` — Add the duration |
| Venue | `B010 — Venue Name` — Add the venue name |
| Cohort(s) | `RSD2 (S1) G2 + RSD2 (S1) G1` — Use `+` between cohorts |
| Students | `5 + 7 = 12` — Show the individual cohort counts and total for multiple cohorts |

**Formatting Requirements:**

- Use `—` between the subject/venue code and its name.  
- Use `+` between cohort code and student counts.  
- Calculate the total student count dynamically from the actual cohort data.  
- Do not hardcode example values.  
- Preserve the existing information layout and styling.

### **3\. Cancellation Reason Section**

After the class information, add a long horizontal divider.

Below the divider, display this instruction:

**You MUST Select a Cancellation Reason** , this was 'Please select a cancellation reason.' at below (above 'This action cannot be UNDONE.').

Formatting:

- Use Title Case.  
- Keep `MUST` fully capitalised.  
- Make the instruction clearly visible.

Keep the existing **CANCELLATION REASON** section title design (uppercase) \== Cancellation Reason

**Radio Buttons:**

- Keep the existing cancellation reason radio options and their functionality.  
- Remove the existing message `Please select a cancellation reason.` because the new instruction above replaces it.

### **4\. Modal Action Button**

- Change the bottom-left button text to **`No, Keep Class`**. just add 'No,'  
- Preserve its existing cancel/close behaviour.  
- Do not change the functionality of other modal buttons.

### Additional UI/UX Update — Cancel Class Button Tooltip

Add a hover tooltip to the following button:

```html
<button type="button" class="btn-danger" data-cancel-class-btn="1">Cancel Class?</button>
```

**Tooltip Message (Title Case):**

`Cancel This Class? You Can Only Cancel Classes Scheduled From 2 Working Days Before Today Up To Today, Including Future Classes.`

**Requirements:**
- Display the tooltip when the user hovers over the button.
- Use the existing tooltip design and styling.
- Keep the tooltip readable and properly positioned.
- Do not change the button's appearance, functionality, or click behaviour.
- The rule must be calculated based on **today's date**, using working days (excluding weekends and applicable holidays).
- Example: If today is Thursday, 5 November 2026, eligible class dates include 3 November, 4 November, 5 November, and future dates.
- Ensure the tooltip accurately describes the actual cancellation eligibility rule.

### **5\. Requirements**

- Preserve existing business logic, validation, and cancellation functionality.  
- Use actual class, cohort, student, venue, and schedule data.  
- Do not hardcode displayed values.  
- Do not modify unrelated pages or components.  
- Verify the modal layout, responsiveness, and button behaviour after implementation.


## UI/UX Update 3 — Rejection Reason Modal & Approve Request Modal

Apply the following UI updates to the **Rejection Reason Modal** and **Approve Request Modal** where applicable.

### 1. Subject Display
- Change subject format from `Operating Systems [L]` to `Operating Systems [Lecture]`.
- Keep the square brackets `[]`.
- Replace short class-type codes with their full words (e.g. `[L]` → `[Lecture]`).

### 2. Rejection Reason Modal Title
- Move the Request ID into the modal title.
- Change `Rejection Reason` to `Rejection Reason (#28)`.
- Retrieve the ID dynamically from the actual request.

### 3. Remove Unnecessary Elements
- Remove `lecturer-avatar`.
- Remove `copy-icon`.
- Do not affect other modal information or functionality.

### 4. Rejection Reason Text
- Use Title Case for all rejection preset options.
- Change the instruction to:

  `Please Provide a Reason for Rejection (Required):`

### 5. Original Class Card (`cmp-card cmp-original`)

Update the information display:

**Expected format:**
```text
Original

Mon, 9 Nov 2026 (Week 8)
11:00 AM to 1:00 PM
Venue Code: B011
```

Requirements:
- Keep the `Original` label.
- Display the date and week together.
- Display the time without the duration (remove `(2 hours)` or similar).
- Add the label `Venue Code:` before the venue code.
- Use actual request data; do not hardcode the example.

### 6. Requirements
- Apply each update only to the relevant modal.
- Preserve existing modal layouts, validation, and approval/rejection functionality.
- Do not modify unrelated pages or components.
- Verify both modals after implementation.
