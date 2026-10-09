# Explore Brief — student-nav-remove-cohort

Date: 2026-10-09 · Trigger: user report — "student nav shows 3 headers; cohort timetables should not be there. It should have My Timetable & Request History only."

## Problem

The student top nav (`partials/ui-nav-bar.blade.php:11-14`) whitelists **3** items:

```php
$isStudent ? [
    ['key'=>'cohort-timetables','label'=>'Cohort Timetables','href'=>'/cohort-timetable-ui'],
    ['key'=>'my-timetable','label'=>'My Timetable','href'=>'/student-my-timetable-ui'],
    ['key'=>'replacement-history','label'=>'Request History','href'=>'/replacement-history-ui'],
]
```

On the student's My Timetable page this reads as three stacked header texts ("Cohort Timetables" nav pill + "My Timetable" nav pill + h1 "Student My Timetable"). User decision: **student nav = My Timetable + Request History only.**

## Why this is canon-safe

- FR 1.2 ("Students shall be able to view their cohort timetable") is satisfied by **Student My Timetable** — it renders the student's own pinned cohort schedule (cohort chip `RSD1(S1)G1` on the page header). The removed item is the *browsable any-cohort* page, which duplicates that content for students.
- CodingMAIN.md §7 RBAC line 168 "View cohort timetables: Student ✅ / Lecturer ✅ / PL ✅" stays true read as *own-cohort view* for students; the *page* becomes lecturer/PL-side. Lecturer + PL nav keeps Cohort Timetables (unchanged).
- No privilege escalation either way: the student cohort page is pinned to the student's own cohort (no data exposure beyond Student My Timetable).

## Rejected approaches

1. **Nav-only removal (route stays `['auth']`)** — rejected: the page would still be reachable by students typing the URL, so "cohort timetable" remains on the student side. Also leaves the route bucket ambiguous.
2. **Remove the component's student branch** (`CohortTimetable.php` `$isStudent` pinning + blade `@if($isStudent)` guards) — rejected as scope creep: after route gating the branch is unreachable dead code, but touching the component widens the diff and risks regressions on the lecturer path. Left in place, noted as defensive.
3. **Rename/merge "Cohort Timetables" into "My Timetable" for lecturers too** — out of scope; user only decided the student side.

## Final solution (mapping)

| Surface | Today | After |
|---|---|---|
| Student nav whitelist | 3 items (Cohort Timetables, My Timetable, Request History) | **2 items** (My Timetable, Request History) |
| Route `/cohort-timetable-ui` mw | `['auth']` (any role) | `['auth', 'role:lecturer']` (lecturers + PLs; students → 403) |
| Lecturer/PL nav | unchanged | unchanged (Cohort Timetables stays) |
| Route `nav` key | `'cohort-timetables'` | unchanged (lecturer active-state highlighting) |

## Touchpoints (complete)

1. `resources/views/partials/ui-nav-bar.blade.php:12` — delete the student entry (line 17 lecturer entry stays).
2. `routes/web.php` — `/cohort-timetable-ui` mw → `['auth', 'role:lecturer']`.
3. `tests/Feature/RouteGateMatrixTest.php` — move `/cohort-timetable-ui` from the auth bucket to `LECTURER_ONLY`; simplify the special-cases at lines 108 and 119.
4. `tests/Feature/NavIdentityTest.php:102` — student nav render test asserts the `/cohort-timetable-ui` link; update to assert absence.
5. `tests/nav-identity.spec.ts` test 2 — student whitelist 3 → 2 + cohort link absent + direct URL visit forbidden.
6. Changelogs: `page-changelogs/cohort-timetable-ui-changelog.md` (page becomes lecturer-only) + `page-changelogs/auth-wiring-changelog.md` (nav whitelist).
7. NOT touched: `CohortTimetable.php` / `cohort-timetable.blade.php` (student branch becomes dead-but-defensive), `ui-common.js` `navigateHome` (already role-aware via `PAGE_HOME`), canon file (user's call, flagged below).

## Open questions

- Canon §7 line 168 wording could gain a parenthetical ("students: own cohort via My Timetable") — canon is user-owned; flagged, not done in this change unless the user asks.
