# Review Log — venue-block-span-coalescing

## proposal Round 1 — 2026-10-10
### 🔴 Outstanding
- (none)
### 🟡 Addressed (soft-freeze amendments, applied verbatim)
- **Coalescing key pinned to `class_session_id` within (week, day)** — with the adjacent-same-module trap cited (BMIT2154 Lecture 09:00–11:00 + BMIT2154 Tutorial 11:00–12:00 = two sessions; module-based grouping would fuse them into a fake span-6). The `start 2, end 5` assertion guards this case.
- **"First/last row" replaced by min/max semantics** (`start` = min slot index, `end` = max end-index − 1, `id` = min row id) — the slot query has no `orderBy`, so row order is not a contract.
- **Gap-split rule disposed**: session rows are contiguous by construction (seeder fills all half-hours; holiday weeks uniformly absent) → plain min/max coalescing, NO gap-splitting machinery (nothing could exercise it).
### 💡 Addressed
- S3 pins the simple `span-4` class assertion (no `class*=` over-matching risk) + the VenueTimetableTest docblock anchor touch-up ("48 occupied rows / 15 sessions").
### Verdict
**PASS — proposal FROZEN** (single-artifact micro-plus per user-approved lean pattern; all reviewer fold-ins applied as prescribed).

## verify — 2026-10-10
### Applied
- Component: coalescing pre-pass in VenueTimetable.php (group w:day:session_id, min/max, severity max, cards stay row-counted)
- Tests: Feature event-count contract → DB-derived distinct sessions (15); BMIT2154 L/T trap guard (start 2/end 5 vs start 6/end 7); Playwright span-4 assertion; docblock anchor touch-up
### Gates
- lint ✓ · phpstan 0 ✓ · phpunit 130/130 (848) ✓ · venue-db+timetable-wiring+nav-identity 13/13 ✓
- records-intact ✓ (101 / 38640 / 0)
