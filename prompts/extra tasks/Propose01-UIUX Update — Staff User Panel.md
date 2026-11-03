**UI/UX Update — Staff User Panel**

Update the `user-panel` on the **staff side only**.

Make the following changes:

1. **User Avatar**
   - Replace the current initials (e.g. `SB`) inside `<div class="user-avatar">` with the staff ID (e.g. `5425`).

2. **User ID**
   - Replace the existing `<span class="user-id">5425</span>` with the staff member's email address.

3. **User Role**
   - Replace `<span class="user-role">Lecturer</span>` with:
     - `Staff` for normal staff.
     - `Staff (PL)` for Programme Leaders (PL).

**Requirements:**
- Display the correct information based on the currently logged-in staff account.
- Keep the existing user-panel layout and visual style.
- Do not affect the student-side user panel.
- Do not hardcode staff IDs, email addresses, or roles.
- Check that all three values display correctly for both normal staff and PL accounts.
