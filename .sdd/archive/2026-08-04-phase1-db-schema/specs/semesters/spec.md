# Spec: Semesters

> Frozen proposal ref: §5.1 (semesters table). Design ref: §2.1.

## Purpose

The `semesters` table is the date-derivation anchor for the entire system. Every class session, time slot, holiday, and replacement request references a semester to derive concrete dates.

## Schema

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `semester_code` | VARCHAR(20) | UNIQUE, NOT NULL | `202605` |
| `label` | VARCHAR | NOT NULL | `202605 Semester` |
| `start_date` | date | NOT NULL | `2026-08-31` (Week-1 Monday) |
| `end_date` | date | NOT NULL | `2026-12-06` |
| `week_count` | unsignedSmallInt | NOT NULL | 14 |
| `created_at` / `updated_at` | timestamp | | |

## Date Derivation Formula

```
date = semesters.start_date + (week_number − 1) × 7 + day_of_week
```

- `week_number` ∈ 1–14
- `day_of_week` ∈ 0–5 (Mon–Sat)
- Example: Week 3, Wednesday (day_of_week=2) = `2026-08-31 + (3-1)×7 + 2 = 2026-09-14`

## Invariants

- `week_count` must equal the number of weeks between `start_date` and `end_date` divided by 7, plus 1
- `start_date` must be a Monday (day_of_week=0)
- `end_date` must be within the semester range
- Only one active semester at a time (enforced at app layer, not DB)

## Referenced By

- `class_sessions.semester_id`
- `time_slots.semester_id`
- `holidays.semester_id`
- `replacement_requests.semester_id`

## Seed Data

1 row: semester_code=`202605`, label=`202605 Semester`, start_date=`2026-08-31`, end_date=`2026-12-06`, week_count=14

## Rationale (explore-brief D11)

Table-driven to match existing dataset pattern (cohorts.md, lecturers.md); config constant rejected because it breaks cross-semester FK integrity.
