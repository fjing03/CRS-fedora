# Spec: Conflict Model (holidays + class_exceptions)

> Frozen proposal ref: §5.7–5.8 (holidays, class_exceptions tables). Design ref: §2.7–2.8.

## Purpose

The conflict model is the entry point of the replacement flow. The replacement-home page lists "conflicted classes" — sessions that need replacement because of holidays, lecturer leave, or official events. Two tables represent this:

1. `holidays` — global public holidays affecting ALL sessions on a given day/week
2. `class_exceptions` — per-session cancellations (lecturer-specific leave, official events)

## Schema: holidays

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `semester_id` | FK → semesters | NOT NULL | 1 |
| `week_number` | unsignedSmallInt | NOT NULL | 5 |
| `day_of_week` | unsignedSmallInt | CHECK 0–5 | 0 (Monday) |
| `label` | VARCHAR | NOT NULL | `Public Holiday` |
| `created_at` / `updated_at` | timestamp | | |

### Index (from design §2.7)

- `[semester_id, week_number, day_of_week]`

### Domain Ruling

- Global holidays affect ALL sessions on that day/week — no per-session granularity needed
- MockData has 5 holidays per semester

## Schema: class_exceptions

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `class_session_id` | FK → class_sessions | cascadeOnDelete, NOT NULL | 5 |
| `week_number` | unsignedTinyInt | CHECK 1–14 | 3 |
| `reason` | VARCHAR(30) | CHECK 5 values | `annual_leave` |
| `created_at` / `updated_at` | timestamp | | |

### Unique Constraint

- `(class_session_id, week_number)` — one exception per session per week

### Canonical Label Set (conflict reason)

`public_holiday`, `annual_leave`, `medical_leave`, `official_event`, `emergency_leave`

## Conflicted Classes Query

```sql
SELECT
    cs.id, cs.day_of_week, cs.start_time, cs.end_time,
    cs.session_type, m.module_code, m.module_name, v.room_code,
    COALESCE(ce.reason, 'public_holiday') AS conflict_reason
FROM class_sessions cs
JOIN modules m ON cs.module_id = m.id
JOIN venues v ON cs.venue_id = v.id
JOIN session_cohorts sc ON cs.id = sc.class_session_id
LEFT JOIN class_exceptions ce ON cs.id = ce.class_session_id AND ce.week_number = :week
LEFT JOIN holidays h ON h.semester_id = cs.semester_id
    AND h.week_number = :week AND h.day_of_week = cs.day_of_week
WHERE cs.semester_id = :semesterId
  AND (ce.id IS NOT NULL OR h.id IS NOT NULL)
GROUP BY cs.id, COALESCE(ce.reason, 'public_holiday'), m.module_code, m.module_name, v.room_code;
```

## Invariants

- A session can have at most one exception per week (unique constraint)
- `reason` values must match the canonical label set exactly
- Holidays are global — a single holiday row cancels all sessions on that day/week
- Both tables reference `semesters` or `class_sessions` by FK (no orphaned rows)

## Seed Data

- Holidays: 5 rows from `window.MockData.holidays` (per semester)
- Class exceptions: from `window.MockData.conflictedClasses` (14 records, each with conflictReason)
