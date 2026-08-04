# Spec: Audit Log

> Frozen proposal ref: §5.10 (audit_logs table). Design ref: §2.10.

## Purpose

The `audit_logs` table provides an immutable event trail for every state transition and OCC validation outcome. FR 3.7 requires PL identity, timestamp, action, slot, and rejection reason. FR 4.12 requires OCC validation outcomes logged.

## Schema

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `user_id` | FK → users | NOT NULL | 7 (PL actor) |
| `action` | VARCHAR(30) | CHECK 6 values | `approved` |
| `replacement_request_id` | FK → replacement_requests | nullable | 12 |
| `time_slot_id` | FK → time_slots | nullable | 123 |
| `old_status` | VARCHAR(20) | nullable | `pending` |
| `new_status` | VARCHAR(20) | nullable | `approved` |
| `occ_validation_result` | VARCHAR(20) | CHECK nullable | `success` |
| `details` | json | nullable | `{"rejection_reason": "..."}` |
| `created_at` / `updated_at` | timestamp | | |

## Canonical Label Set (audit action)

`submitted`, `approved`, `rejected`, `cancelled`, `completed`, `occ_conflict`

## Canonical Label Set (occ_validation_result)

`success`, `conflict`

## Indexes (from design §2.10)

- `replacement_request_id` — audit trail per request
- `created_at` — chronological queries

## FR Coverage

### FR 3.7 (PL identity, timestamp, action, slot, reason)

| FR field | audit_logs column |
|---|---|
| PL identity | `user_id` |
| Timestamp | `created_at` |
| Action | `action` |
| Slot | `time_slot_id` |
| Rejection reason | `details` (json) |

### FR 4.12 (OCC validation outcomes)

| OCC outcome | audit_logs columns |
|---|---|
| Successful reservation | `action='submitted'`, `occ_validation_result='success'`, `time_slot_id` |
| Conflict (slot not available) | `action='occ_conflict'`, `occ_validation_result='conflict'`, `time_slot_id`, `details` |
| Conflict (version mismatch) | `action='occ_conflict'`, `occ_validation_result='conflict'`, `time_slot_id`, `details` |

## Immutability Contract

- **No UPDATE/DELETE** on audit_logs rows — enforced at app layer (service layer), not DB trigger
- Rows are append-only; once created, never modified
- This is a design decision, not a DB constraint — the service layer must never call `update()` or `delete()` on audit log records

## Invariants

- `user_id` must reference a valid user (FK)
- `replacement_request_id` and `time_slot_id` may be null (e.g. OCC conflict before request exists)
- `old_status` and `new_status` reflect the request's state transition (nullable for OCC conflicts)
- `occ_validation_result` is non-null only for OCC-related actions (`submitted`, `occ_conflict`)
- `details` is a freeform JSON blob — no schema validation at DB level
