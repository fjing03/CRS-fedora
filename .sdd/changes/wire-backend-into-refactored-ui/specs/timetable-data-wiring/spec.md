# Timetable Data Wiring Specification

## Purpose

Serve My Timetable, Cohort Timetable, Student My Timetable, and Replacement History from live records, not mock data: role scoping, class details, canonical legend, mock fallback.

## Requirements

### Requirement: My Timetable own sessions

My Timetable MUST list only sessions the lecturer teaches (FR 2.2) from live records for the selected week. Clicking any listed class — conflicted included — MUST open details (module, venue, time, cohorts) (FR 2.3).

#### Scenario: Lecturer views own sessions

- GIVEN a lecturer with seeded sessions
- WHEN the lecturer opens My Timetable
- THEN only their sessions render with module, day, time, venue

#### Scenario: Conflicted class opens details

- GIVEN a class overlapping another of theirs
- WHEN the lecturer clicks it
- THEN details open with the same fields as any class

### Requirement: Cohort Timetable lecturer/PL access

Cohort Timetable MUST be reachable by lecturers and Programme Leaders only; students MUST receive HTTP 403 (the student's FR 1.2 cohort view is the pinned Student My Timetable, not the staff consolidated view). Lecturers and PLs MAY view any cohort. Data MUST come from live class-session and cohort records.

#### Scenario: Student blocked

- GIVEN a student in one cohort; sessions seeded for two
- WHEN the student requests Cohort Timetable
- THEN the response is HTTP 403; no cohort sessions render

#### Scenario: Lecturer views other cohort

- GIVEN an authenticated lecturer
- WHEN the lecturer selects a different cohort and week
- THEN that cohort's sessions render

### Requirement: Student My Timetable sessions + statuses

Student My Timetable MUST show the student's own cohort's sessions (FR 1.2) and the status of requests affecting that cohort (FR 1.3). It MUST be read-only: students SHALL NOT create, edit, delete, or modify any data (FR 1.5–1.8).

#### Scenario: Statuses visible

- GIVEN a pending request affecting the student's cohort
- WHEN the student opens Student My Timetable
- THEN the occurrence shows the Pending label with canonical styling

### Requirement: Replacement History details

Replacement History (student-only) MUST list approved replacements for the student's own cohort in current or future weeks (week ≥ current) with new date, time, venue (FR 1.4); past weeks and other cohorts MUST NOT appear.

#### Scenario: Details visible

- GIVEN an approved replacement for the student's cohort in a future week
- WHEN the student opens Replacement History
- THEN the new date, time, and venue are displayed

#### Scenario: Past or other-cohort excluded

- GIVEN replacements for another cohort and, in a past week, for the student's cohort
- WHEN the student opens Replacement History
- THEN neither appears

### Requirement: Rendering and edge cases

Timetable pages MUST render from the database (sessions, session–cohort links, time slots, semester scoping); an empty week MUST render an empty state, never an error (§3); a conflicting block MUST render with canonical Conflict styling.

#### Scenario: Empty week empty state

- GIVEN a week with no sessions
- WHEN the timetable renders
- THEN an empty-state message shows with HTTP 200

#### Scenario: Conflicted block styled Conflict

- GIVEN two overlapping blocks in a cohort week
- WHEN the timetable renders
- THEN the clashing block shows the Conflict styling

### Requirement: Canonical read-only legend

The legend MUST show exactly the four canonical pairs (§10.0 table A): Normal Class (secondary), Replacement (primary), Pending (tertiary), Conflict (error) — identical on every timetable page (NFR 3.2).

#### Scenario: Legend matches canonical table

- GIVEN any timetable page renders
- WHEN the legend is inspected
- THEN exactly Normal Class, Replacement, Pending, Conflict appear with mapped tokens

### Requirement: Mock fallback switch

Config off → pages MUST serve live data; on → the untouched legacy template, byte-identical. Role gating MUST be identical in both modes (design D10).

#### Scenario: Fallback serves legacy

- GIVEN fallback enabled and an authenticated student
- WHEN the student opens Student My Timetable
- THEN the legacy template renders with unchanged RBAC

## Amendments (2026-10-10 unfreeze, batch 2 of 3)

Per `sync-upstream-fjing-ui` design §10 registered debt + batch-1 re-verification. Paper-only alignment with shipped code; no behavior change.

1. "Upcoming Replacements" → "Replacement History" (5 prose sites: purpose, requirement header, requirement body, two scenario WHEN lines). The original 5-site debt list only counted camelCase `UpcomingReplacements` in design/specs/tasks; these prose sites were found by the 2026-10-10 re-verification.
2. "Cohort Timetable scoping by role" requirement corrected: lecturer/PL-only access (was "open to all roles"); "Student sees own cohort" scenario replaced by a student-blocked (403) scenario — FR 1.2 is served by the pinned Student My Timetable.
