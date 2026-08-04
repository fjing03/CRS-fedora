# Spec: Time Slots (availability grid + OCC)

> Frozen proposal ref: §5.6 (time_slots table). Design ref: §2.6.

## Purpose

The `time_slots` table is the materialized availability grid and the OCC target for replacement requests. Every (venue × day × time × week) combination has a row — 30-minute granularity, pre-seeded. The `version` column enables optimistic concurrency control (FR 4.9).

## Schema

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `semester_id` | FK → semesters | NOT NULL | 1 |
| `class_session_id` | FK → class_sessions | nullable | 5 (occupied) or NULL (available) |
| `week_number` | unsignedTinyInt | CHECK 1–14, NOT NULL | 3 |
| `day_of_week` | unsignedTinyInt | CHECK 0–5 | 0 (Monday) |
| `start_time` | time | CHECK minute ∈ {0,30} | `09:00` |
| `end_time` | time | NOT NULL | `09:30` |
| `venue_id` | FK → venues | NOT NULL | 12 |
| `status` | VARCHAR(20) | CHECK: `available`/`pending`/`occupied` | `available` |
| `version` | unsignedInt | default 1 | 1 |
| `created_at` / `updated_at` | timestamp | | |

## Grid Specifications

- **Granularity:** 30-minute cells
- **Time range:** 08:00–18:30 (22 cells/day)
- **Days:** Mon–Sat (5 days/week, day_of_week 0–5)
- **Weeks:** 1–14
- **Venues:** 23 Block B rooms
- **Volume:** 22 × 5 × 14 × 23 ≈ **35,420 rows**
- **Sunday:** excluded from time_slots (CHECK 0–5); UI hard-forces Sunday cells as occupied

## Indexes (from design §2.6)

- `[venue_id, day_of_week, start_time, week_number]` — grid queries
- `status` — filtering by availability
- **Partial unique index:** `(venue_id, day_of_week, start_time, week_number) WHERE status IN ('pending','occupied')` — DB-level no-double-booking guard

## Status Semantics

| Status | Meaning | UI color | Stored? |
|---|---|---|---|
| `available` | No class, no pending request | green | Yes |
| `pending` | Someone has submitted a request for this slot | yellow (self) / grey (other) | Yes |
| `occupied` | A class is booked here | red | Yes |
| `reserved` | Viewer-relative label for `pending` by another proposer | grey | **No** — derived at query time |

### Reserved Derivation

```sql
-- "reserved" = pending + different proposer
SELECT ts.*, CASE
    WHEN ts.status = 'pending' AND rr.proposer_id = :currentUserId THEN 'pending-self'
    WHEN ts.status = 'pending' AND rr.proposer_id != :currentUserId THEN 'reserved'
    ELSE ts.status
END AS display_status
FROM time_slots ts
LEFT JOIN replacement_requests rr ON rr.replacement_time_slot_id = ts.id AND rr.status = 'pending'
WHERE ts.venue_id = :venueId AND ts.week_number = :week;
```

## Slot State Machine (FR 4.0–4.11)

```
available → pending (submit, version++)
pending → occupied (PL approve, version++)
pending → available (reject/cancel, version++)
occupied → (permanent, no further transitions)
```

## OCC Transaction Pattern (FR 4.9–4.12)

```
1. SELECT ... FOR UPDATE → lock row
2. Check status = 'available'
3. Read current version
4. UPDATE SET status='pending', version=version+1 WHERE id=? AND version=?
5. If affected_rows = 0 → conflict → audit_log(action='occ_conflict')
6. If affected_rows = 1 → success → audit_log(action='submitted', occ_validation_result='success')
```

## Cell Boundary Enforcement

```sql
CHECK (extract(minute FROM start_time) IN (0, 30))
```

All session times are hour-aligned (`:00`), but the grid has 30-min cells. A 2-hour session occupies 4 contiguous cells.

## Seed Data

Pre-seed all 35,420 rows with status='available', class_session_id=NULL, version=1. Then UPDATE to mark occupied cells from `class_sessions` after timetable seeding.
