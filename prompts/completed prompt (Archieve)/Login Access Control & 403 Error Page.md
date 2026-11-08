## BUG Fix & UI Update — Login Access Control & 403 Error Page

### 1. BUG — Logged-in Users Can Still Access Login Pages

**Affected URLs:**
- `/login/staff`
- `/login/student`

**Current Behaviour:**
A user who is already logged in can still access either login page.

**Expected Behaviour:**
- Logged-in users should not be able to access login pages.
- Redirect them to their correct default page based on their authenticated role.
- Staff → Default Staff Page.
- Student → Default Student Page.

Check both routes and their authentication/role-based redirect logic.

### 2. UI Update — Staff Accessing Student Page

**Scenario:** A logged-in Staff user accesses:

`/student-my-timetable-ui`

The system correctly displays a 403 Access Denied page.

Update the following elements:

**A. Access Denied Title**
```html
<div class="error-title">Access Denied</div>
```
- Change the font colour to white.

**B. Go Back Button**
```html
<a href="/" class="btn-back">Go Back</a>
```
- Do not always redirect users to `/`.
- Redirect based on the authenticated user's role:
  - Staff → Default Staff Page.
  - Student → Default Student Page.
- Ensure the destination is correct for each user's situation.

### 3. Investigation & Verification

Use SDD to inspect the existing authentication, role-checking and redirect logic before making changes.

Verify:

1. Logged-in Staff cannot access `/login/staff` or `/login/student`.
2. Logged-in Student cannot access `/login/staff` or `/login/student`.
3. Staff accessing `/student-my-timetable-ui` receives the 403 page.
4. Student accessing a Staff-only page receives the 403 page.
5. The `Access Denied` title uses white font colour.
6. `Go Back` redirects to the correct default page for both roles.
7. Guest users can still access the appropriate login pages.

### Scope & Restrictions

- Fix the login-page access control and the specified 403 UI behaviour.
- Preserve existing authentication, authorisation and role permissions.
- Do not weaken or bypass access restrictions.
- Do not modify unrelated pages or functionality.
- Report the affected files and explain the changes made.
- Test both Staff and Student scenarios before reporting completion.
