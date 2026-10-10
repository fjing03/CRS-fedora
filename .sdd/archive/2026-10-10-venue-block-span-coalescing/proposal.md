# Proposal — venue-block-span-coalescing

## Why

User-reported rendering fidelity bug against the frozen event-blocks design (`venue-event-blocks-db`, archived 2026-10-10): **a 1-hour class renders as two stacked single-slot blocks instead of one combined `span-2` block** (2-hour = four `span-1`s; upstream mocks render one `span-N` block per class).

Root cause (verified against the demo DB): the import stores the **full half-hour grid** per venue/week (38,640 = 23 × 14 × 120 rows) and a class "occupies" its adjacent 30-minute rows — session 42 (BMIT2154, Mon 09:00–11:00) is four rows. `VenueTimetable` builds **one event per row** (`start = end = slotIndex` for 30-min rows), so every class renders as N stacked span-1 blocks. Invisible under the old status-cell renderer (per-slot idiom); exposed by the event-blocks rewrite (upstream events are duration-objects).

## Scope

### S1 — Coalesce consecutive same-session rows into one spanning event
`app/Livewire/VenueTimetable.php`, event-building loop: rows are grouped by **`class_session_id` within (week, day)** — the key is the session id, NEVER module code + adjacency (B006 Monday has BMIT2154 Lecture 09:00–11:00 immediately followed by BMIT2154 *Tutorial* 11:00–12:00 — two distinct sessions; module-based grouping would wrongly fuse them into one span-6 block. The `start 2, end 5` assertion guards exactly this case). Per group:
- `start` = **min** slot index across the group's rows, `end` = **max** `slotIndex(end_time) − 1`, `id` = **min** row id (min/max semantics — the slot query has no `orderBy`, so "first/last row" is not a stable contract);
- cohorts/lecturer/name/code are session-invariant;
- status = **max severity** across coalesced rows (conflict > pending > normal — same severity map as twin-merge; v1 data is uniform per session, so this is contract-completeness for Slice B);
- session rows are contiguous by construction (the seeder fills every half-hour between the session's start/end; holiday weeks have rows uniformly absent) — so plain min/max coalescing is sufficient; **no gap-splitting machinery** is built (nothing in v1 data can exercise it; FR 4.11 honesty is structural).

### S2 — What must NOT change
- **Cards stay row-counted**: `sumTotal`/`sumAvailable`/`sumOccupied` count time_slot rows; `sumMyClasses` counts distinct sessions; `sumMyHours` = rows × 0.5 h. All verified correct today — coalescing is rendering-only.
- **Twin-merge unchanged**: `mergeTwinEvents` still collapses same (day,start) combined-lecture twins AFTER coalescing (severity map untouched, reflection test survives).
- **Payload shape** unchanged (eventsByWeek contract, `mine`, di/start/end keys) — only event multiplicity/duration changes.
- Blade/`cellRender` unchanged (it already paints `span-{info.span}` — the fix is purely payload-side).
- Derived conflicts (B005) unaffected: conflict status is per-session, coalescing preserves it.

### S3 — Tests
- Feature: new coalescing test — the real multi-row session BMIT2154 Mon 09:00–11:00 (4 rows) yields **one** event `start 2, end 5` (span 4). Assert the day's event count equals the **distinct-session count for that day (computed from the DB, not hardcoded)** and that the BMIT2154 event carries the coalesced start/end.
- Feature: existing tests re-checked — the `test_totals…` contract (48 events in B006 W1 keyed `di:start`) **changes**: coalescing collapses 48 row-events → one event per distinct session. The frozen "48" lives on in `sumOccupied` (row-count semantics); the eventsByWeek count assertion is updated to the DB-derived distinct-session count with a comment.
- Playwright `venue-db.spec.ts`: assert the BMIT2154 block renders with exactly `span-4` (simple class assertion — not `[class*="span-"]` variants, which risk over-matching if the class list grows).
- `VenueTimetableTest.php` docblock touch-up: the anchor comment reads "48 occupied slots" — becomes "48 occupied **rows** / 15 sessions" so the frozen-anchor story stays coherent after the event-count assertion changes.
- Gates: lint, phpstan, phpunit full, venue-db/timetable-wiring/nav-identity, records-intact.

### S4 — Verify + archive
One verify pass; archive to `.sdd/archive/`; **no push without explicit authorization.**

## Out of scope

- Any data change (no migrations; slot rows stay half-hour).
- Booking/pending write path (Slice B).
- Twin-merge logic changes.
- Other pages (cohort/my-timetable use different payload pipelines — checked: they don't consume this component).

## Success criteria

- A 1-hour class renders ONE `span-2` block; BMIT2154 Mon 09:00–11:00 renders ONE `span-4` block.
- Cards unchanged: B006 W1 → total 120, occupied 48, available 72; viewer 4288 → 8 classes / 14 h.
- All gates green; records intact; archived; nothing pushed without authorization.
