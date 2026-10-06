## proposal Round 1 — 2026-08-04 19:05

### 🔴 Fixed
- None

### 🟡 Addressed
- Changelog append reclassified: `page-changelogs/backend-automated-by-ai.md` moved from "Files created" to "Files touched" (it's an existing tracked file)
- Engine read-list corrected from 10 to 7 tables: removed `modules`, `cohorts`, `semesters` (only reached via model relationships / scalar params per D6/D7)

### 🔴 Outstanding
- None — proposal.md PASSES and is FROZEN.

## design Round 1 — 2026-08-04 19:25

### 🔴 Fixed
- None

### 🟡 Addressed
- §5 vs Q-2 time-cast contradiction resolved: `ClassSession` casts now read "none (raw string `H:i:s`, per Q-2)"
- Index name corrected to real migration name `time_slots_venue_day_start_week_idx`
- §6 arithmetic corrected: ~36,764 available / ~1,876 occupied (33 sessions × 4 cells × 14 weeks, one 6-cell)
- `validateSlot` gains week/semester consistency guard (returns false on mismatch)
- Integration-test correctness assertion (lecturer 4288 / DFT2, week 5) restored per D9
- Overlap test switched to interval form `busy.start < cell.end AND busy.end > cell.start` (alignment-independent)
- 4 factory files added to frozen proposal §5 (declarative); SessionCohort factory dropped (direct-insert pivot); §8/§9 counts aligned

### 🔴 Outstanding
- None — design.md PASSES and is FROZEN.

## specs Round 1 — 2026-08-04 19:40

### 🔴 Fixed
- S-1 `time_slot_ids` corrected 2 → 4 (2-hour run = 4 cells; was contradictory with design Step 5 + S-8)

### 🟡 Addressed
- S-17 hardened: non-empty assertion + concrete pinned window (`day:1, 10:00:00, B002`, verified against seed busy ranges) + clarified the week-5 exception is session 14 (4127/RSD2) and does not affect this call
- S-4b added: venue-occupied excluded scenario (E-4's base-query filter path, was only in brief's test list)
- S-11 now asserts E-11 ordering (day, start_time, venue_code)
- E-12 reworded: only week mismatch is testable via the public API (no semester param)

### 🔴 Outstanding
- None — specs/intersection-engine/spec.md PASSES and is FROZEN.

## tasks Round 1 — 2026-08-04 19:55

### 🔴 Fixed
- None

### 🟡 Addressed
- Task 7 note added: design §4 step 2's semester clause is not implementable via the public API (no semester param) — superseded by E-12; week guard only
- Verify: one-liners added to Tasks 4, 5, 6, 7 (targeted phpunit runs), matching Task 3's convention

### 🔴 Outstanding
- None — tasks.md PASSES and is FROZEN. Change is ready for /sdd-apply.
