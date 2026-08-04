# Spec: Replacement Requests

> Frozen proposal ref: §5.9 (replacement_requests table). Design ref: §2.9.

## Purpose

The `replacement_requests` table tracks the full lifecycle of a class replacement — from lecturer submission through PL decision. It references both the original class (via `class_session_id` + `week_number`) and the proposed replacement slot (via `replacement_time_slot_id`), which is the OCC target.

## Schema

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `semester_id` | FK → semesters | NOT NULL | 1 |
| `proposer_id` | FK → users | NOT NULL | 42 (lecturer) |
| `class_session_id` | FK → class_sessions | NOT NULL | 5 (original session) |
| `week_number` | unsignedTinyInt | CHECK 1–14 | 3 |
| `replacement_time_slot_id` | FK → time_slots | NOT NULL | 123 (OCC target) |
| `approver_id` | FK → users | nullable | 7 (PL) |
| `status` | VARCHAR(20) | CHECK 5 states | `pending` |
| `rejection_reason` | text | nullable | `Insufficient notice period` |
| `remarks` | text | nullable | `Replacement completed` |
| `submitted_at` | timestamp | = created_at | `2026-09-01 10:30:00` |
| `decided_at` | timestamp | nullable | `2026-09-02 09:00:00` |
| `created_at` / `updated_at` | timestamp | | |

## Canonical Label Set (request status)

`pending`, `approved`, `rejected`, `cancelled`, `completed`

## Index (from design §2.9)

- `[status, submitted_at]` — FCFS queue ordering (FR 3.2)

## Request → Original Class Resolution

A request references the original class via:
- `class_session_id` → `class_sessions` → module (code, name), lecturer, venue, session_type, day_of_week, start_time, end_time
- `week_number` → concrete date via semester start_date derivation
- Pivot `session_cohorts` → cohorts (multi-cohort support, e.g. "DFT2 + DSF2")

## Request → Replacement Slot Resolution

- `replacement_time_slot_id` → `time_slots` → week_number, day_of_week, start_time, end_time, venue_id
- UI displays: replacementDate, replacementTime, replacementVenue

## FCFS Queue (FR 3.2)

```sql
SELECT rr.*, ts.start_time, ts.day_of_week, ts.venue_id
FROM replacement_requests rr
JOIN time_slots ts ON rr.replacement_time_slot_id = ts.id
WHERE rr.status = 'pending'
ORDER BY rr.submitted_at ASC;
```

## Lifecycle States

| Status | Meaning | Next transitions |
|---|---|---|
| `pending` | Submitted, awaiting PL review | → approved, rejected, cancelled |
| `approved` | PL approved | → completed (later phase) |
| `rejected` | PL rejected (rejection_reason mandatory per FR 3.6) | (terminal) |
| `cancelled` | Lecturer cancelled own request | (terminal) |
| `completed` | Replacement occurred | (terminal) |

## Domain Rulings

- **No draft state:** `submitted_at` = `created_at`; request is submitted the moment it's created
- **`completed` semantics:** set at a later phase (app layer wiring); schema stores the status
- **FK-normalized:** all display fields (courseCode, classDate, venue, cohorts) derived via FKs — no denormalized columns

## Invariants

- `replacement_time_slot_id` NOT NULL — a request must pick a replacement slot to submit
- `rejection_reason` must be non-null when status transitions to `rejected` (FR 3.6, app-layer enforced)
- `proposer_id` must be a user with role `lecturer` (app-layer enforced)
- `approver_id` must be a user with `lecturer.is_pl = true` (app-layer enforced)
- One pending request per time_slot at a time (enforced by time_slots partial unique index)

## Seed Data

From `window.MockData.requests` (20 records in mock-data.js) — only when `phase2-db-seed` runs.
