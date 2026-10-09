# Changelog — Auth Wiring

## [2026-10-09] Student nav label parity — "My Timetable" + "Replacement History" (SDD student-nav-label-parity)

User report: nav labels differed per student page — `/student-my-timetable-ui` showed "My Timetable" + "Request History" (shared whitelist) while `/replacement-history-ui` showed "Student My Timetable" + "Replacement History" (mock-era `navItems` override in its legacy template, which is live-served via the route fallback until Wave 3b wires `ReplacementHistory`).

- **Single source restored**: the `navItems` overrides deleted from `replacement-history-UI-design-template.blade.php` (live) and `student-my-timetable-UI-design-template.blade.php` (fallback-only) — both pages now inherit the shared role whitelist; only the layout pass-through + the partial reference `$navItems` remain.
- **Label rename**: student whitelist item 2 `'Request History'` → `'Replacement History'` (key `replacement-history` + href unchanged → `activeNav` highlighting intact). Lecturer items untouched (`Request History` on `/my-request-history-ui` names a different page).
- **Wave 3b guard**: when `ReplacementHistory` gets wired, the legacy template must keep inheriting the whitelist — do NOT re-introduce `navItems`.
- Verified: phpunit 129/129 · nav-identity 3/3 with a new cross-page label-parity assertion (bar + drawer on both student pages).

## [2026-10-09] Student nav whitelist 3 → 2 (SDD student-nav-remove-cohort)

`partials/ui-nav-bar.blade.php`: the student `roleItems` whitelist drops the **Cohort Timetables** entry — students see **My Timetable** (`/student-my-timetable-ui`) + **Request History** (`/replacement-history-ui`) only, on both the desktop bar and the mobile drawer (single `$items` source). Paired route change: `/cohort-timetable-ui` mw → `role:lecturer` (see `cohort-timetable-ui-changelog.md`). Tests updated: `RouteGateMatrixTest` (cohort → lecturer bucket), `NavIdentityTest` (cohort link absent), `nav-identity.spec.ts` (whitelist 2 + direct-visit 403). Lecturer/PL whitelists untouched.

## Files Changed

### `.sdd/changes/auth-wiring/sdd.yaml`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | File created | SDD manifest | name: auth-wiring, status: active |

### `.sdd/changes/auth-wiring/proposal.md`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | File created | Proposal document | 9 features: user panel wiring, logout form, role-based redirect, role-based session lifetime, remember me, session countdown, session indicator, staff lockout, auto-logout (staff only) |

### `.sdd/changes/auth-wiring/design.md`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | File created | Design document | Key decisions: Fortify::redirectUsing for redirect, config override for session lifetime, cache-based lockout, client-side countdown, JS inactivity tracker |

### `.sdd/changes/auth-wiring/tasks.md`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | File created | Task checklist | 9 tasks, ~30 subtasks |

### `page-changelogs/auth-wiring-changelog.md`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | File created | Changelog stub | Header + empty Files Changed section |

## 2026-10-08 — wire-existing-backend: real identity + role-aware nav

SDD change: `.sdd/changes/wire-existing-backend/` — proposal/design/tasks frozen (reviewer rounds 2/2/1); verify PASS. **Resolves the user-reported bug: logging in as 5425 showed the panel as 5770 ("En. Lim Jia Zheng").**

### Changes

| Timestamp | Location | Change |
|-----------|----------|--------|
| 2026-10-08 | `resources/views/partials/ui-nav-bar.blade.php` | **Identity wiring**: desktop user-panel + mobile nav-drawer now render `auth()->user()` via `User::displayName()` (honorific-aware) / `loginId()` (staff_id/student_id) / `initials()`; role line = `Lecturer (PL)` / `Lecturer` / `Student`. The 8 hardcoded mock strings (LJZ / 5770 / En. Lim Jia Zheng / Lecturer ×2 blocks) are gone; `{{ }}`-escaped; null-safe |
| 2026-10-08 | `resources/views/partials/ui-nav-bar.blade.php` | **Role-aware nav**: static 6-item list → per-role whitelists (student: Cohort Timetables + Student My Timetable + Request History; lecturer: My Timetable, Cohort, Venue, Replacement Arrangement, Request History; PL: + Request Approval). Students no longer see 403-bound lecturer links; nav keys still drive activeNav highlighting |
| 2026-10-08 | `app/Livewire/StudentMyTimetable.php` | Dropped the stale 2-item `navItems` override (Slice-A stopgap) so the partial's role whitelist governs (execution-discovered; design §2 premise corrected) |
| 2026-10-08 | `tests/Feature/NavIdentityTest.php` | NEW — 3 tests × 3 roles: panel identity + role-appropriate nav (incl. plain-lecturer PL-gating) |

### Mock → real mapping

| Mock (before) | Real (after) |
|---------------|--------------|
| `user-avatar: LJZ` | `User::initials()` (e.g. `SB` for Surayaini Binti Basri) |
| `user-name: En. Lim Jia Zheng` | `User::displayName()` = `{honorific} {name}` (e.g. `Pn. Surayaini Binti Basri`) |
| `user-id: 5770` | `User::loginId()` = staff_id/student_id of the session user |
| `user-role: Lecturer` (static) | role + PL suffix from `lecturers.is_pl` |
| nav items: static 6 (all roles) | per-role whitelists (RBAC-matrix-derived) |

Deferred (recorded): `mock-data.js` `currentUser` block + 3 legacy ownership consumers (venue :782/:795, my-request-history :316, CohortTimetable :363/:375 — mock-fallback-only); `navPendingBadge`/`notifBadge` real feeds (Slice C); session-age script in the partial (old auth-wiring change).

### Verified

- Live: 5425 panel = `Pn. Surayaini Binti Basri / 5425 / Lecturer (PL)` + approval link, **0** mock-identity hits; student panel = own identity, 3 student links, **0** lecturer-only links
- Auth matrix unchanged (guest 302 → login.student; wrong role 403); retrieval live (5425: BMIT9012+B107; student: BMIT2222+B101)
- Gates: phpunit **113/113** (110 + 3), phpstan-1G 0, pint adminer-only

### Follow-up (same day) — Playwright browser-level guard

| Timestamp | Location | Change |
|-----------|----------|--------|
| 2026-10-08 | `tests/nav-identity.spec.ts` | NEW — Playwright (chromium) spec for the JS-runtime layer PHPUnit/curl cannot see: real-browser panel identity (5425/25RSD0001/5770), zero mock text rendered, approval-link count (2 = nav + drawer), mobile-viewport drawer interaction (hamburger is a ≤768px surface), and **zero JS page errors** on load. 3/3 pass against the running app |

*Supplements `NavIdentityTest` (HTTP-level); not part of the archived change's frozen scope — added as follow-up verification at user request.*
