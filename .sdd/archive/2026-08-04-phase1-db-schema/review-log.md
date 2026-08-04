## proposal Round 1 — 2026-08-03 22:05 (original, pre-unfreeze)
### 🔴 Fixed
- replacement_requests could not reach original class session (FCFS queue can't derive subject/cohorts per FR 3.3) — added `class_session_id` FK + `week_number` to replacement_requests
- time_slots lacked week dimension — added NOT NULL `week_number` (1–14) and reworked index to `[venue_id, day_of_week, start_time, week_number]`
- day_of_week CHECK 0–4 contradicted mock domain (Saturday classes on di:5) — widened to 0–5 (Mon–Sat), documented as explicit domain ruling
### 🟡 Addressed
- Added `cohort_id` index on session_cohorts (engine cohort-availability vector)
- Fixed FR citation: auditability is FR 3.7 + FR 4.12, not "FR 7.x" (7.x = Legal & Ethical)
- Added DB-level no-double-booking guard: partial unique index on (venue, day, start, week) WHERE status IN ('pending','occupied')
- Pinned pending/reserved semantics: stored = available/pending/occupied; reserved = derived viewer label
- Added Impact Scope file table (house style)
- Corrected risk wording: statuses stored lowercase; UI title-case is presentation
- Added "no draft state" ruling (submitted_at = created_at); immutability = app layer, not DB trigger
### 🔴 Outstanding
- (none)

---

## proposal Round 2 — 2026-08-03 23:40 (unfreeze & rewrite)
### 🔴 Fixed
- Frozen proposal had 7 migrations; explore session grilled 11 decisions revealing 4 decision-level conflicts — proposal unfrozen and rewritten to 10 tables per user-approved explore-brief.md
- `venues.room_name` removed (UI never displays names; user chose 4 columns per grilled D4)
- `replacement_requests.status` expanded from 4 states to 5 (pending/approved/rejected/cancelled/**completed**) per grilled D8 — frontend mock-data.js uses 5 statuses verbatim
- Added `semesters` table (semester_code, label, start_date, end_date, week_count) — date-derivation anchor per grilled D11; FKs added to class_sessions, time_slots, holidays, replacement_requests
- Added `holidays` table (semester_id, week_number, day_of_week, label) per grilled D7
- Added `class_exceptions` table (class_session_id, week_number, reason CHECK) per grilled D7 — one exception per session×week, unique constraint
- Added `semester_id` FK to `time_slots` (week-scoped grid requires semester context)
- Added `remarks` column to `replacement_requests` (frontend MockData has remarks field)
- `class_sessions` — removed `week_number` column (D6: no week col; per-week variation handled by class_exceptions); added `semester_id` FK
- `holidays` — full schema with semester_id FK, week_number, day_of_week, label
- `class_exceptions` — reason CHECK expanded to 5 values matching frontend conflictReason set
- Updated Impact Scope table to 10 files
- Updated Risks table (30-min cell boundary enforcement, completed semantics)
- Updated Rollback Plan from --step=7 to --step=10
- Added Domain Rulings section (day-of-week, slot state machine, enum encoding, no-draft, audit immutability, completed semantics, reserved derivation)
- Specs list expanded from 5 to 7 capabilities (added semesters, conflict-model)
### 🟡 Addressed
- Test-DB switch (D10): approved by user; documented as open decision — fold into this change or sibling
- Sunday ruling clarified: hard-forced occupied in UI, CHECK 0–5 on schema, no Sunday rows in time_slots
### 🔴 Outstanding
- (none)

---

## proposal Round 3 — 2026-08-04 00:15 (post-unfreeze re-review)
### 🔴 Fixed
- (none — no issues found)
### 🟡 Addressed
- (none)
### 🔴 Outstanding
- (none)

**Reviewer verdict:** Proposal passes all 10 checklist items against explore-brief.md baseline. All 11 decisions reflected; 10 tables correct; 7 domain rulings pinned; label sets complete; scope, risks, rollback, success criteria all verified. **Proposal is now FROZEN.**

---

## design Round 1 — 2026-08-04 00:30
### 🔴 Fixed
- (none)
### 🟡 Addressed
- holidays composite index `[semester_id, week_number, day_of_week]` added to §2.7 migration code block
- class_sessions composite indexes `[lecturer_id, day_of_week, start_time]` and `[venue_id, day_of_week, start_time]` added to §2.4 migration code block
- replacement_requests FCFS index `[status, submitted_at]` added to §2.9 migration code block
- audit_logs indexes `replacement_request_id` and `created_at` added to §2.10 migration code block
- 30-min boundary CHECK `time_slots_start_time_check` added to §2.6 migration code block; cross-reference in §7 corrected
- holidays.day_of_week CHECK (defensive addition beyond proposal) — noted as design decision
- class_exceptions cascadeOnDelete (defensive addition beyond proposal) — noted as design decision
### 🔴 Outstanding
- (none)

**Reviewer verdict:** Design passes all 12 checklist items. All 10 tables match proposal exactly. All CHECK constraints, partial unique index, OCC pattern, date formula, slot state machine, conflicted classes query, grid volume, dependency graph, and open questions verified. **Design.md is now FROZEN.**

---

## specs Round 1 — 2026-08-04 01:00
### 🔴 Fixed
- (none)
### 🟡 Addressed
- venues/spec.md: added `room_type` and `capacity` indexes (missing from spec, present in design §2.2)
- conflict-model/spec.md: fixed GROUP BY to use `COALESCE(ce.reason, 'public_holiday')` to prevent duplicate rows when session has both exception and holiday
- design.md §6: aligned GROUP BY with spec fix
### 🔴 Outstanding
- (none)

**Reviewer verdict:** Specs batch passes. All 7 capabilities covered. All 14 CHECK constraints, 13 indexes, 8 canonical label sets verified across specs. Schema columns/types match frozen proposal and design exactly. No contradictions between specs. **Specs are now FROZEN.**

---

## tasks Round 1 — 2026-08-04 01:15
### 🔴 Fixed
- (none)
### 🟡 Addressed
- Task 6: added explicit checkbox for `CHECK (extract(minute FROM start_time) IN (0, 30))` alongside other CHECKs
- Task 7: fixed `holidays.week_number` type from `unsignedSmallInt` to `unsignedTinyInt` (consistent with all other week_number columns)
### 🔴 Outstanding
- (none)

**Reviewer verdict:** Tasks batch passes. All 10 tables covered with correct dependency order. All 14 CHECK constraints, 13 indexes, 8 FKs, 3 unique constraints verified. Verification steps on every task. Tasks 11–12 cover full rollback and test suite. **Tasks.md is now FROZEN. All 4 batches complete — change is fully specified.**
