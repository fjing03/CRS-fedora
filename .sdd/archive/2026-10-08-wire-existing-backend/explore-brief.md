# Explore brief — wire-existing-backend

**Date:** 2026-10-08 · **Vehicle:** Wave 3a — wire the EXISTING backend (auth, identity display, my-timetable retrieval) before any Slice B/C work.
**Relation to `wire-backend-into-refactored-ui`:** that package stays **frozen and untouched** (Slice A already applied; Slices B/C deferred until the user inserts real database records — they will supply CSV + MD). This is a separate, small change.

## 1. Ground truth (verified first-hand, 2026-10-08)

### 1.1 The static identity surfaces (`resources/views/partials/ui-nav-bar.blade.php`)

- Desktop `user-panel` (:40–44): avatar `LJZ`, name `En. Lim Jia Zheng`, id `5770`, role `Lecturer` — **hardcoded, zero `auth()` reads**.
- Mobile `nav-drawer-user` (:87–91): same 4 hardcoded fields.
- Root cause of the user-reported bug (login `5425` → panel shows `5770`): the partial is a mock shell; every authenticated user sees the same mock identity. Pre-diagnosed 2026-10-07 (addendum in the Wave-3 package's explore-brief), re-confirmed today.
- The partial receives only `activeNav` + `navItems` from `layouts/ui-template.blade.php:35`.

### 1.2 Identity data shape (live DB)

- Staff `5425`: `{ name: "Surayaini Binti Basri", honorific: "Pn.", role: "lecturer", staff_id: "5425", is_pl: true }`
- Student `25RSD0001`: `{ name: "Student 25RSD0001", honorific: null, role: "student", student_id: "25RSD0001" }`
- `users.honorific` exists (migration `2026_10_02_024758`); display = `{honorific} {name}` (honorific nullable).

### 1.3 What is ALREADY wired (do not re-do)

- **Auth/session**: Fortify login real; role middleware + auth-first order live (Slice A); `RouteGateMatrixTest` 9 routes × 4 roles green (110/110 suite).
- **Timetable retrieval**: Slice A's 3 Livewire components (`MyTimetable`, `CohortTimetable`, `StudentMyTimetable`) query real Eloquent data; `TimetableWiringTest` green. The Livewire views **already bridge real identity into JS**: `MockData.currentUser = @json([name/staffId from auth()->user()])` (my-timetable.blade.php:114, cohort-timetable.blade.php:101).
- Routes serve components when `!mock_fallback && class_exists(component)` (routes/web.php:88); `APP_MOCK_FALLBACK` unset → false → components live.

### 1.4 Remaining unwired surfaces (this change's scope)

1. **Nav partial identity** (both blocks above) — the actual bug.
2. **Role-agnostic nav items** — static 6-item list shows lecturer-only pages (venue, replacement-arrangement, request-approval…) to students (→ 403/redirect on click). No role filtering exists. Student-facing pages (`student-my-timetable-ui`, `replacement-history-ui`) appear in NO nav list.
3. **Identity-consumption sweep** — `ui-common.js:1701` reads `MockData.currentUser.staffId || 'demo'`; fine while views inject real values, but the sweep must confirm no *display* surface still renders `5770`/`LJZ` post-wiring (mock-data.js `currentUser` :80 stays as fallback data — Slice C/B decision, not here).
4. **Live verification of timetable retrieval** — TimetableWiringTest proves test-context; live curl assertion against the seeded demo DB still owed (server-rendered initial HTML should contain real session rows, e.g. 5425's BMIT5678 Tue 11:00 B105).

### 1.5 Explicitly deferred

- `navPendingBadge` (:2778, mock-fed) + `notifBadge` — real pending-count is Slice C (RequestApproval) territory.
- `mock-data.js` `currentUser` removal — B/C pages still mock-consume it; removal happens when the last consumer converts.
- Slices B/C of the frozen package (await user's CSV/MD real records).
- The 3-batch rename unfreeze (`UpcomingReplacements` → `ReplacementHistory`) — belongs to the B/C resumption, not here.

## 2. Decisions captured here (feed proposal)

- **D1:** Identity is read **directly in the partial** via `auth()->user()` (single edit point; every route that renders the layout is behind `auth` middleware, so the user is always present). No new controller/view-composer layer.
- **D2:** Display contract: name line = `{honorific} {name}` (honorific nullable, single space collapse); id line = `staff_id` (lecturer) / `student_id` (student); role line = `Lecturer (PL)` when `is_pl`, else capitalized role; avatar initials = first letters of the first two name words (fallback: first two chars of id).
- **D3:** Role-aware nav: filter items per the frozen RBAC matrix (student: cohort-timetables + student pages; lecturer: my-timetable, venue, replacement-arrangement, request-history; PL: + request-approval). Exact matrix pinned in design against `specs/rbac-route-gating` of the frozen package + current `routes/web.php` middleware.
- **D4:** Verification-first posture: auth matrix + timetable retrieval asserted live (curl) BEFORE and AFTER the wiring edits; identity change asserted by diffing rendered panel content for 5425 (must contain `5425`, must NOT contain `5770`/`LJZ`) and for a student.
- **D5:** Feature test `NavIdentityTest` (or extend `TimetableWiringTest`): render pages as lecturer + student, assert panel shows session user's honorific/name/id/role and role-filtered nav.

## 3. Rejected approaches

- **View composer / service provider** to inject identity — indirection for one partial; the layout already passes data via `@include` vars, and `auth()` in a partial is idiomatic Laravel.
- **Passing identity through `MockData.currentUser` only** (JS-side swap) — the panel is server-rendered Blade; JS patching would flash mock identity and leave no-se-JS users with 5770.
- **Fixing nav items by hiding 403 pages only** — filtering must be whitelist-per-role (D3), not blacklist, so future pages default to hidden.
- **Doing this inside the frozen Wave-3 package** — would force an unfreeze of 4 frozen batches for a small independent capability; split keeps B/C resumption clean.

## 4. Open questions

- Student seed names are literally `Student 25RSD0001` — display works, but avatar initials ("S2") look odd. Accept for 3a (data quality, not wiring); the user's real CSV records will replace them.
- Where to record the partial change: no page-changelog maps to a shared partial — propose appending to `page-changelogs/backend-automated-by-ai.md` + a line in `login-oop-refactor-changelog.md` (closest auth-related log). Reviewer to arbitrate.
