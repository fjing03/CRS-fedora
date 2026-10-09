# Proposal — b005-diploma-conflict

## Why

The venue-restriction audit (VENUE-RESTRICTIONS.md, 2026-10-09) found exactly one dataset violation: **AMIT2034 "Fundamentals of Computer Networks" P, Wed 11:00–13:00, B005, En. Daniel Royd Michael, combined DFT+DSF diploma lecture** — B005 disallows Diploma students (`D*` programmes). User decision: this class is to appear in the system as a **schedule conflict needing replacement**. The B006 usages are priority-tier, not violations (rule reworded, `b70e4fe`).

## What's in scope

1. **Derived conflict flag (no DB mutation)** — a session conflicts when its venue is `B005` AND any of its cohorts belongs to a `D*` programme. Emitted as `status: 'conflict'` in the event payload:
   - In the shared trait `ResolvesTimetableTimeline::baseEvent` → covers MyTimetable, CohortTimetable, StudentMyTimetable.
   - In `VenueTimetable`'s own event builder (same rule, same payload key).
   - Exact shared shape (trait method promoted to both vs `App\Services` evaluator) decided in design; single implementation, no copy-paste.
2. **Display via existing paths**:
   - MyTimetable / StudentMyTimetable: engine default renders `.event-conflict` (loud red) — zero blade change.
   - CohortTimetable: NEW status-conflict branch in its `statusClassFn`, owner-gated per the upstream pattern (mine → `event-conflict`, others → `event-public-holiday`).
   - VenueTimetable: its existing `event-conflict` branch becomes owner-gated (mine → `event-conflict`, others → `event-public-holiday`) for upstream venue parity.
   - Modal: "Scheduling conflict — needs attention" already ships (ui-common.js) — no change.
3. **Tests**: feature tests (trait pages render the AMIT2034 Wed-11:00 B005 block as conflict; RSD cohorts' B005 sessions unaffected; venue view emits the flag) + playwright spec coverage where a spec exists for these pages.

## Out of scope

- Any DB mutation — the slot stays `occupied`, counts (Total/Available/Occupied/My-Teaching) unchanged, unique-index protection intact.
- The booking-time enforcement of ALL venue restrictions (B006 priority, practicals-in-labs) — Slice B scope; this change implements ONLY the B005 no-Diploma derivation as display.
- A replace/cancel button — write path is Slice B; the modal's existing "needs attention" text stays the honest affordance.
- B006 display changes — priority is a booking-time preference, renders nothing today.
- The stored-status and fake-replacement-request approaches — rejected (explore brief).

## Success criteria

- The AMIT2034 Wed-11:00 B005 block renders as a red conflict on MyTimetable (Daniel), the DFT/DSF student timetables, CohortTimetable (owner-gated), and the venue page B005 grid (owner-gated).
- No other session's rendering changes (RSD-only B005 sessions stay normal; all B006 sessions stay normal).
- Slot counts, venue cards, and the DB state machine untouched (phpunit 123/123 + new tests green; phpstan 0; lint clean).
