## UI/UX Update — Organise Notification Row Hover Tooltips

**Goal:** Make all notification row hover tooltips clearer, more organised and consistent.

Use the following examples as the target content and layout. Display information with clear labels, proper spacing and separate lines.

### 1. New Replacement Request

Example title: `New Replacement Request (ID #67)`

Display the relevant request information, including:
- Subject: `Subject Code (Type) -- Subject Name`
- Original Schedule
- Proposed Replacement Schedule
- Request ID

### 2. Class Cancelled

Display:

- **Subject:** Subject Code (Type) -- Subject Name
- **Cancelled Class Schedule:** Week 6 · Mon, 9 Nov 2026 · 11:00 AM–1:00 PM @ B100
- **Reason:** Annual Leave

### 3. Replacement Class Update

Display:

- **Subject:** Subject Code (Type) -- Subject Name
- **Proposed Replacement Schedule:** Week 7 · Mon, 16 Nov 2026 · 11:00 AM–1:00 PM @ B109 
- **Replaced For:** Week 6 · Mon, 9 Nov 2026 · 11:00 AM–1:00 PM @ B100 (cancellation reason)
- Include the original cancellation reason, such as `Chinese New Year (Public Holiday)` or `Annual Leave`, where applicable.

### 4. Replacement Rejected

Display:

- **Subject:** Subject Code (Type) -- Subject Name
- **Rejection Reason:** Venue Unavailable / other actual rejection reason.
- **Original Schedule:** Week 6 · Mon, 9 Nov 2026 · 11:00 AM–1:00 PM @ B100
- **Proposed Replacement Schedule:** Week 7 · Mon, 16 Nov 2026 · 11:00 AM–1:00 PM @ B109

**New Auto-Rejection Reason:**

If automatically rejected by the 3-working-hour rule, display:

`Automatically Rejected: The Programme Leader did not approve this request before the required approval deadline (3 working hours before the proposed class start time).`

### 5. Other Notification Types — Review and Identify

Review all existing notification types and identify any missing tooltip layouts.

Consider:
- Replacement Request Submitted
- Replacement Approved
- New Replacement Request
- Other existing notification types

Do not invent new notification types. Base the layouts on actual notification data and existing business rules.

### Requirements

- Use actual database values dynamically. Do not hardcode example data.
- Keep labels and information consistent across notification types.
- Use clear date, time, week and venue formatting.
- Hide irrelevant fields when data is unavailable.
- Preserve existing notification behaviour, actions and styling.
- Avoid unnecessary changes outside notification row tooltips.

### Before Implementation

First, review the existing notification types and their available data.

If any important notification type or information is missing from my proposed layouts, **show me what is missing and ask for my confirmation before implementing it.**

### Keep the existing behaviour for both notification types:

- Replacement Approved: Show both Original Schedule and Approved Replacement Schedule.
- Replacement Request Submitted: Show both Original Schedule and Proposed Replacement Schedule.

No new layout or business logic is needed for these two types. Only review and tidy up their existing tooltip presentation for consistency with the other notification types.
