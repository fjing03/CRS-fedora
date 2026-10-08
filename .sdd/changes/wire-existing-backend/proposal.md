# Proposal — wire-existing-backend

**Status:** draft (Batch 1) · **Date:** 2026-10-08 · **Baseline:** explore-brief.md (this change)
**Context:** Wave 3a of the agreed re-sequencing — wire the EXISTING backend (auth, identity display, my-timetable retrieval) now; Slices B/C of `wire-backend-into-refactored-ui` stay frozen until the user supplies real records (CSV + MD).

## Why

The user-visible bug: logging in as staff `5425` shows the user panel as `5770` ("En. Lim Jia Zheng") — `ui-nav-bar.blade.php` is a mock shell with hardcoded identity in both the desktop panel and the mobile drawer, zero `auth()` reads. The same partial serves a **role-agnostic static nav** that shows lecturer-only pages to students. Meanwhile auth itself, the route gating, and the three timetable components already run on real data (Slice A, green since 2026-08-31) — what's missing is the last identity display layer and live proof that retrieval works against the current (Wave-2-repaired, upstream-merged) database.

## What

1. **Wire real identity into `partials/ui-nav-bar.blade.php`** — both the desktop `user-panel` and the mobile `nav-drawer-user` blocks read `auth()->user()` per explore-brief D1/D2: honorific+name, staff/student id, role line (`Lecturer (PL)` / `Lecturer` / `Student`), derived avatar initials. No new layers (no composer/service provider).
2. **Role-aware nav — per-role whitelists with role-specific hrefs** (D3, not a filter of the static list: the static 6-item list contains zero student-page entries and nav keys collide across roles). Whitelists derived from the frozen rbac spec + current middleware:
   - **Student**: Cohort Timetables (`/cohort-timetable-ui`), My Timetable (`/student-my-timetable-ui`), Request History (`/replacement-history-ui`);
   - **Lecturer**: My Timetable (`/my-timetable-ui`), Cohort Timetables, Venue Timetable, Replacement Arrangement (`/replacement-home-ui`), Request History (`/my-request-history-ui`);
   - **PL** (lecturer + `is_pl`): lecturer set + Request Approval (`/request-approval-ui`).
   Desktop nav + drawer render the same role-appropriate list; future pages default hidden (whitelist rule).
3. **Identity-consumption sweep**: grep `5770`/`LJZ`/`Lim Jia Zheng` across `resources/views/` + served pages — post-wiring, no *display* surface may render the mock identity for a logged-in real user. `mock-data.js` `currentUser` data block stays (fallback for not-yet-converted B/C pages — deferred by design). **Known accepted-deferred consumers — there are three** (recorded, not "discovered" mid-apply): legacy `venue-timetable-UI-design-template` (:782/:795), `my-request-history-UI-design-template` (:316), and `CohortTimetable-UI-design-template` (:363/:375) each compare ownership against `MockData.currentUser.name` — they serve only under mock fallback (the CohortTimetable route serves the live component today); conversion lands in B/C.
4. **Live verification (auth + retrieval)**, curl-first per D4: **pre-edit baseline capture first** (current panel HTML for 5425 + student — the "before" of the diff), then auth matrix spot-checks (guest → 302 login redirect; wrong role → 403; right role → 200); timetable retrieval asserted against the seeded demo DB (server-rendered HTML contains real session rows — e.g. 5425's BMIT5678 Tue 11:00 B105; a student's own cohort rows); identity assertions for 5425 (contains `5425`/`Surayaini`, never `5770`/`LJZ`) and a student.
5. **Feature test** `NavIdentityTest` (D5): lecturer + student + **PL** renders → panel identity + role-appropriate nav asserted (student nav must *contain* the student links; PL nav must contain Request Approval + the lecturer items); guards against regression when B/C land. Reuses `User::displayName()` (honorific-aware) and `User::loginId()` (role-based id) — they already implement the D2 name/id contract; **`User::initials()` (first+last letter of the whole initials string, no id fallback) is the accepted display behavior for 3a** (diverges from brief D2's two-word rule only on 3+-word names; both give `SB` for 5425). The `Lecturer (PL)` role line derives from `$user->lecturer->is_pl` (null-safe lazy load) — the only new display logic; the brief's D2 initials fallback clause is superseded by this decision.
6. **Records**: changelog entries (placement per design; open question §4 of the brief).

## Out of scope

- Slices B/C of the frozen Wave-3 package (replacement flow, approval, email) — resume after the user's real records land, with the recorded 3-batch rename unfreeze.
- `navPendingBadge` / `notifBadge` real feeds (Slice C); `mock-data.js` `currentUser` removal (last B/C conversion).
- Any migration, model, service, or route-middleware change (Slice A products are inputs, not targets).
- Data quality of seed identities (`Student 25RSD0001`) — the user's real CSV records replace them.
- The mock session-age script in the partial (`:118–140`, `authStart = Date.now() - 25s`, tagged "BACKEND WIRE LATER") — session-lifetime territory (old `auth-wiring` change), **explicitly deferred**, untouched here.
- Push (never without explicit authorization).

## Success criteria

| # | Criterion | Evidence |
|---|---|---|
| 1 | Logged in as `5425` (lecturer **and PL**): rendered panel (desktop + drawer) shows `Pn. Surayaini Binti Basri`, `5425`, role line including PL; nav contains the lecturer items **plus Request Approval**; page HTML contains **no** `5770`/`LJZ`/`Lim Jia Zheng` | curl + grep, recorded |
| 2 | Logged in as student `25RSD0001`: panel shows `Student 25RSD0001` / `25RSD0001` / `Student`; nav **contains** Cohort Timetables + My Timetable (`/student-my-timetable-ui`) + Request History (`/replacement-history-ui`); lecturer-only links absent from HTML | curl + grep, recorded |
| 3 | Auth matrix unchanged from Slice A: guest → 302 `login.student`; wrong role → 403; right role → 200 (spot-checks live; `RouteGateMatrixTest` stays green) | curl outputs + suite |
| 4 | Timetable retrieval live: server-rendered HTML of the 3 timetable pages contains real DB session rows — 5425's BMIT5678/B105/Tue 11:00 **and** the student's own cohort rows — not mock-only content | curl + grep, recorded |
| 5 | `NavIdentityTest` green; full suite green at **110 + new** tests; phpstan-1G 0 errors; `composer run lint:check` + pint adminer-only baseline | gate outputs |
| 6 | Sweep: zero mock-identity display references remain in rendered pages for real users; the **three** known legacy `MockData.currentUser.name` ownership consumers (venue :782/:795, my-request-history :316, CohortTimetable :363/:375) recorded as accepted-deferred (B/C) | grep + recorded note |
| 7 | Changelog entries exist in `page-changelogs/auth-wiring-changelog.md` (closest domain log, per reviewer arbitration); change archived | file diffs + `.sdd/archive/…` |

## Risks

| Risk | Mitigation |
|---|---|
| Partial is included by 9+ layouts/pages — a PHP error breaks every page | Change is read-only `auth()` access with null-safe operators; feature test renders multiple roles; smoke hits ≥3 page families |
| Role-filtered nav could hide a page a role legitimately needs (matrix mismatch) | Matrix derived from the frozen rbac spec + current middleware (source of truth), pinned in design; criterion 3's matrix check guards it |
| Blade escaping of honorific/name with special chars | `{{ }}` escaping only; no `{!! !!}` |
| User's upcoming real records diverge from seed shapes (e.g. missing honorific) | Display contract handles nulls (D2); no assumption of seed-only values |
