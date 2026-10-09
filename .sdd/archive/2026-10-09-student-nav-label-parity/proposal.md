# Proposal — student-nav-label-parity

Date: 2026-10-09 · Baseline: explore-brief.md (this change dir)

## 1. Problem

Student nav labels differ per page: `/student-my-timetable-ui` shows "My Timetable" + "Request History" (shared role whitelist), while `/replacement-history-ui` shows "Student My Timetable" + "Replacement History" (mock-era `navItems` override in its legacy template — that page is served by the template fallback because `App\Livewire\ReplacementHistory` is Wave 3b, parked). User decision (2026-10-09): **"i want 'My Timetable' & 'Replacement History'"**.

## 2. Scope

1. **Single source of nav truth** — delete the `navItems` override from `ui-design-templates/replacement-history-UI-design-template.blade.php` (live-served page): it inherits the shared whitelist. `homeUrl`, `activeNav`, `pageKey` stay.
2. **Same deletion** in `ui-design-templates/student-my-timetable-UI-design-template.blade.php` (fallback-only today — the component exists — but the identical override would resurface the divergence if it ever serves).
3. **Label rename** in the shared whitelist (`partials/ui-nav-bar.blade.php`, student items only): `'Request History'` → `'Replacement History'`. Key `replacement-history` and href `/replacement-history-ui` unchanged → `activeNav` highlighting intact. Lecturer items untouched (`'Request History'` → `/my-request-history-ui` is a different page/meaning; user scoped to student side).
4. **Playwright parity assertion** (`tests/nav-identity.spec.ts` test 2): after the existing assertions, visit `/replacement-history-ui` as the student and assert the nav shows the same two labels ("My Timetable", "Replacement History") — the user's acceptance criterion, directly tested.
5. **Changelogs**: `auth-wiring-changelog.md` (primary — whitelist label + override removals), `replacement-history-changelog.md` (page now uses shared nav), `student-my-timetable-ui-changelog.md` (its template is edited + its 2026-10-09 postscript currently claims the student nav shows "Request History" — goes stale after the rename; add a one-line correction).

## 3. Out of scope

- **Wave 3b** — wiring `ReplacementHistory` (the page body remains mock-era static until the user gives the go). This change is nav-parity only.
- **Lecturer nav labels** — `'Request History'` on `/my-request-history-ui` stays.
- **Page h1 titles** — "Student My Timetable" / "Replacement History" page titles are page identity, not nav; unchanged.
- **Canon** — §10.0 same-name rule: student "Replacement History" vs lecturer "Request History" name DIFFERENT pages (different hrefs), so no canon conflict. CodingMAIN.md untouched.
- **MANUAL-TEST-CASES.md / legacy e2e specs** — parked; their "Request History" references are the lecturer page.

## 4. Success criteria

1. Student nav on BOTH `/student-my-timetable-ui` AND `/replacement-history-ui`: exactly "My Timetable" + "Replacement History" (bar + drawer).
2. Active-state highlighting still correct on both pages (cohort item removed earlier; here `my-timetable` / `replacement-history` keys unchanged).
3. Lecturer nav unchanged (5/6 items, "Request History" label intact on `/my-request-history-ui`).
4. Gates: lint · phpstan 0 · phpunit 129/129 · nav-identity 3/3 (extended) · timetable-wiring 5/5 · venue-db 4/4. Records-intact: no DB writes.
5. No `navItems` override remains in any student-reachable template (grep-verified: only the layout pass-through + the partial consumer reference `$navItems`).
