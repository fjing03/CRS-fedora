# RBAC Route Gating Specification

## Purpose

Define access control for the nine UI routes so every request is authenticated and role-bucketed before any page renders. Covers the four access buckets, guest redirect, 403 responses, ownership scoping, and identical gating under the mock fallback.

## Requirements

### Requirement: Authentication gate on all UI routes

All nine UI routes (my-timetable, cohort-timetable, student-my-timetable, replacement-history, replacement-home, replacement-arrangement, my-request-history, venue-timetable, request-approval) MUST require an authenticated session (FR 4.14, NFR 2.1). The authentication check MUST run before any role check (design D3), so an unauthenticated request to ANY protected route is redirected to the login page, never a 403.

#### Scenario: Guest redirected from each bucket

- GIVEN no authenticated session
- WHEN a guest requests one route per bucket (student-my-timetable; my-timetable; request-approval)
- THEN each request redirects to the login page
- AND no protected route returns 403 or page content to a guest

### Requirement: Role-bucket enforcement

Route buckets MUST match the locked matrix (FR 4.13): student-my-timetable + replacement-history = student only; my-timetable, cohort-timetable, replacement-home, replacement-arrangement, my-request-history, venue-timetable = lecturer and Programme Leader; request-approval = Programme Leader only. A Programme Leader MUST pass every lecturer-gated route (its account role is `lecturer` plus the PL flag). An authenticated user outside a route's bucket MUST receive an HTTP 403 error page (FR 4.13), not a redirect.

#### Scenario: Student blocked from lecturer routes

- GIVEN a student is authenticated
- WHEN the student requests my-timetable, replacement-arrangement, and venue-timetable
- THEN each response is HTTP 403

#### Scenario: Lecturer blocked from PL-only queue

- GIVEN a lecturer without the PL flag is authenticated
- WHEN the lecturer requests request-approval
- THEN the response is HTTP 403

#### Scenario: Programme Leader passes lecturer-gated routes

- GIVEN a Programme Leader is authenticated
- WHEN the PL requests my-timetable and venue-timetable
- THEN each responds HTTP 200

#### Scenario: Student blocked from the staff cohort view

- GIVEN a student is authenticated
- WHEN the student requests cohort-timetable
- THEN the response is HTTP 403 — FR 1.2 cohort viewing is served by the student's pinned Student My Timetable, not the staff consolidated view (CodingMAIN.md §6 matrix footnote + §9 page table)

### Requirement: Student view-only surface

Students MUST NOT create, edit, delete, or modify any replacement request or timetable data (FR 1.5–1.8). Student pages MUST render no submit/cancel/arrange controls, and a directly invoked state-changing action MUST be denied.

#### Scenario: Student cannot invoke lecturer actions

- GIVEN a student is authenticated
- WHEN the student directly invokes a submit, cancel, or arrange action
- THEN the action is denied with 403 and no request or slot record changes

### Requirement: Lecturer ownership scoping

Lecturer request pages (arrangement, history, cancellations) MUST expose only the authenticated lecturer's own requests and classes (FR 2.11): every listing and action target is scoped by ownership, and acting on another lecturer's request identifier MUST fail. Lecturers MUST NOT approve or reject any request (FR 2.12); the approval queue and its actions are unreachable to them.

#### Scenario: Lecturer sees only own history

- GIVEN two lecturers each hold one pending request
- WHEN lecturer A opens request history
- THEN only A's own request rows appear

#### Scenario: Lecturer cannot approve

- GIVEN a lecturer is authenticated
- WHEN the lecturer directly invokes an approve or reject action
- THEN the action is denied and the request and slot are unchanged

### Requirement: Uniform gating under mock fallback

When the mock-fallback switch is on, every route MUST apply identical middleware (design D10): guests redirect, wrong roles get 403, and correct roles receive the legacy template with unchanged protection.

#### Scenario: Fallback mode keeps gates

- GIVEN mock fallback enabled and no session
- WHEN a guest requests replacement-home
- THEN the response redirects to login, identical to real-data mode

## Amendments (2026-10-10 unfreeze, batch 2 of 3)

Per `sync-upstream-fjing-ui` design §10 registered debt + batch-1 re-verification. Paper-only alignment with shipped code; no behavior change.

1. `upcoming-replacements` → `replacement-history` in the nine-route list (routes registry + `RouteGateMatrixTest::STUDENT_ONLY` already ship the new name).
2. Bucket matrix corrected: cohort-timetable moved to the lecturer+PL bucket (was "all authenticated roles") — evidence `routes/web.php` mw, `RouteGateMatrixTest::LECTURER_ONLY`, `CodingMAIN.md` §9 page table ("Lecturer/PL — students pinned to own cohort"; students see their cohort via the pinned Student My Timetable, per the §6 matrix footnote on FR 1.2). Guest-scenario route list updated to one-per-remaining-bucket.
3. "All-roles route open to student" scenario replaced by "Student blocked from the staff cohort view" (403) — FR 1.2 is served by the pinned Student My Timetable.
