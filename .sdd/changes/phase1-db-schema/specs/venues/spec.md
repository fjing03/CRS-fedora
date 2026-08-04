# Spec: Venues

> Frozen proposal ref: §5.2 (venues table). Design ref: §2.2.

## Purpose

The `venues` table is the room catalog — 23 Block B rooms at TAR UMT Sabah. The Matrix Intersection Engine (Objective 1) filters venues by capacity, room type, and allowed session types.

## Schema

| Column | Type | Constraint | Example |
|---|---|---|---|
| `id` | bigIncrements | PK | 1 |
| `room_code` | VARCHAR(20) | UNIQUE, NOT NULL | `B002` |
| `capacity` | unsignedSmallInt | NOT NULL | 35 |
| `room_type` | VARCHAR(30) | CHECK: `tutorial`/`lecture_hall`/`lab`/`cisco_lab` | `tutorial` |
| `allowed_session_types` | VARCHAR(10) | NOT NULL | `L,T` or `P` |
| `created_at` / `updated_at` | timestamp | | |

## Canonical Label Set (from explore-brief §5.11)

`room_type`: `tutorial`, `lecture_hall`, `lab`, `cisco_lab`

## Room Inventory (23 rooms — Block B only)

| Type | Capacity | Rooms | Count |
|---|---|---|---|
| Tutorial | ≤35 | B002, B014–B018, B100–B109 | 16 |
| Lecture Hall | >35 (80) | B110, B111 | 2 |
| Lab | 28 | B005, B009–B011 | 4 |
| Cisco Lab | 32 | B006 | 1 |

## Session-Type Rules (FR 4.8)

- Tutorial rooms (`tutorial`): allow `L` and `T`
- Lecture halls (`lecture_hall`): allow `L` only
- Labs (`lab`): allow `P` only
- Cisco lab (`cisco_lab`): allow `P` only

MPU-3133 (Tutorial-only per FR 4.8) → filter venues excluding labs → tutorial rooms + lecture halls only.

## Invariants

- `capacity` must be > 0
- `allowed_session_types` must be comma-separated subset of `{L,T,P}`
- `room_code` format: `B` + 3-digit number (Block B only)
- Block C rooms excluded (user decision)

## Indexes (from design §2.2)

- `room_type` — venue-type filtering
- `capacity` — capacity filtering

## Referenced By

- `class_sessions.venue_id`
- `time_slots.venue_id`

## Rationale (explore-brief D4)

UI never displays room names — only codes (B002, B110…). CodingMAIN §5 specifies no name field. 4 columns chosen over 5.
