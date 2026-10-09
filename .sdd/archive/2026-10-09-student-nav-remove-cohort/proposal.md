# Proposal — student-nav-remove-cohort

Date: 2026-10-09 · Baseline: explore-brief.md (this change dir)

## 1. Problem

The student top nav whitelists **3** items, the first being **Cohort Timetables** (`/cohort-timetable-ui`). On the student's My Timetable page this renders three header texts ("Cohort Timetables" pill + "My Timetable" pill + h1 "Student My Timetable"). User decision (2026-10-09): **"It should have My Timetable & Request History only — remove the Cohort Timetable on the student side."**

## 2. Scope

1. **Student nav whitelist** (`partials/ui-nav-bar.blade.php`): drop the `cohort-timetables` entry — students get **My Timetable** (`/student-my-timetable-ui`) + **Request History** (`/replacement-history-ui`) only.
2. **Route gate** (`routes/web.php`): `/cohort-timetable-ui` mw `['auth']` → `['auth', 'role:lecturer']` — removal means *unreachable*, not just unlinked; students hitting the URL directly get 403, consistent with every other lecturer page. Lecturers and PLs unaffected.
3. **Tests** (3 files):
   - `RouteGateMatrixTest`: `/cohort-timetable-ui` moves from the auth bucket to `LECTURER_ONLY`; the two special-case conditions (lines 108, 119) simplify to plain bucket checks.
   - `NavIdentityTest` (line 102): student page asserts the cohort link is **absent** (was present).
   - `nav-identity.spec.ts` test 2: student whitelist 3 → 2, cohort link absent, and a direct `/cohort-timetable-ui` visit as student does **not** render the cohort page (403).
4. **Changelogs**: `cohort-timetable-ui-changelog.md` (page → lecturer-only) + `auth-wiring-changelog.md` (student nav whitelist 3 → 2).

## 3. Out of scope

- **Lecturer/PL nav** — Cohort Timetables stays (canon §7 line 168: Lecturer ✅ / PL ✅).
- **`CohortTimetable.php` + `cohort-timetable.blade.php`** — the component's student branch (`$isStudent` pinning, disabled faculty select, `pinnedCohortId`) becomes unreachable dead code after the route gate; left in place as defensive (no diff on the lecturer path). Recorded in design.
- **Canon edits** — §7 line 168 stays valid read as *own-cohort view* for students (FR 1.2 satisfied by Student My Timetable, which shows the pinned cohort chip). Any canon rewording is the user's separate call (flagged in explore-brief).
- **Student My Timetable / Request History pages** — untouched.

## 4. Success criteria

1. Student nav shows exactly 2 items: My Timetable, Request History (desktop bar + mobile drawer).
2. Lecturer (5425 PL / 5770 plain) nav unchanged — 5/6 items incl. Cohort Timetables.
3. Student GET `/cohort-timetable-ui` → 403; lecturer + PL → 200.
4. All gates green: lint · phpstan 0 · phpunit (129 + updated) · nav-identity 3/3 (updated) · timetable-wiring 5/5 · venue-db 4/4. Records-intact: no DB writes.
5. FR 1.2 unchanged in behaviour: students still see their own cohort's weekly schedule via Student My Timetable.
