# Spec: Timetable (modules + class_sessions + session_cohorts)

> Frozen proposal ref: §5.3–5.5 (modules, class_sessions, session_cohorts). Design ref: §2.3–2.5.

## Purpose

The timetable is the weekly recurring schedule template. Each `class_sessions` row defines one recurring session (module × lecturer × day × time × venue × type) that runs every week of the semester. Per-week variation (cancellations) lives in `class_exceptions` (separate spec).

## Schema: modules

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `module_code` | VARCHAR(30) | UNIQUE, NOT NULL | `BMIT5555` |
| `module_name` | VARCHAR | NOT NULL | `Software Engineering` |
| `allowed_session_types` | VARCHAR(10) | NOT NULL | `L,T` |
| `created_at` / `updated_at` | timestamp | | |

### Canonical Label Set (session_type)

`L` (Lecture), `T` (Tutorial), `P` (Practical)

## Schema: class_sessions

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `semester_id` | FK → semesters | NOT NULL | 1 |
| `module_id` | FK → modules | NOT NULL | 5 |
| `lecturer_id` | FK → users | NOT NULL | 42 |
| `day_of_week` | unsignedTinyInt | CHECK 0–5 | 0 (Monday) |
| `start_time` | time | NOT NULL | `09:00` |
| `end_time` | time | NOT NULL | `11:00` |
| `venue_id` | FK → venues | NOT NULL | 12 |
| `session_type` | VARCHAR(5) | CHECK L/T/P | `L` |
| `created_at` / `updated_at` | timestamp | | |

### Indexes (from design §2.4)

- `[lecturer_id, day_of_week, start_time]` — engine lecturer-availability vector
- `[venue_id, day_of_week, start_time]` — venue vacancy lookups

### Domain Rulings

- **Mon–Sat only:** `day_of_week` 0–5. Sunday excluded from scheduling (CHECK 0–5).
- **No week column:** sessions run all 14 weeks; per-week variation via `class_exceptions`.
- **No student_count:** derived from `session_cohorts` pivot + `students` table.

## Schema: session_cohorts (pivot)

| Column | Type | Constraint |
|---|---|---|
| `class_session_id` | FK → class_sessions | cascadeOnDelete, composite PK |
| `cohort_id` | FK → cohorts | cascadeOnDelete, composite PK |

### Index

- `cohort_id` — engine cohort-availability vector

## Cross-Module Data Flows

1. **Lecturer occupied slots:** `class_sessions WHERE lecturer_id = X` → all day×time ranges the lecturer is busy
2. **Cohort occupied slots:** `class_sessions JOIN session_cohorts WHERE cohort_id IN (X,Y)` → all day×time ranges any target cohort is busy
3. **Venue occupied slots:** `class_sessions WHERE venue_id = X` → all day×time ranges the venue is booked
4. **Module filtering:** `modules.allowed_session_types` + `venues.allowed_session_types` → venue eligibility per session type

## Seed Data

- Modules: from `dataset/timetable.md` (to be synthesized)
- Class sessions: from `dataset/timetable.md` (to be synthesized)
- Session cohorts: from `dataset/timetable.md` (to be synthesized)
- 14 cohorts, 14 lecturers, 23 venues (existing seeded data, frozen)

## Rationale (explore-brief D2, D5, D6)

- Weekly template chosen over materialized class_instances (700+ redundant rows rejected)
- No `faculty_id` on modules (codes encode faculty; MPU modules cross-faculty)
- No week column on class_sessions (per-week variation via exceptions)
- No stored student_count (derived from pivot)
