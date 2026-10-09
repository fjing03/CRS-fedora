# Explore Brief — b005-diploma-conflict

Date: 2026-10-09
Trigger: user decision — the B005 venue-restriction violation (AMIT2034 P, Wed 11:00–13:00, En. Daniel Royd Michael, combined DFT+DSF diploma lecture) is to be shown as a **schedule conflict needing replacement**. The B006 usages are NOT violations — the B006 rule is priority (networking/IoT rank first), not exclusivity (VENUE-RESTRICTIONS.md reworded accordingly, commit `b70e4fe`).

## Key code facts (explored)

1. **DB state machine is 3 states** (`available`/`pending`/`occupied` — FR 4.11 canon, CodingMAIN §3). `'conflict'` exists in the app ONLY as an OCC submission outcome (`OCCValidator` → `occ_validation_result: 'conflict'`, transient at booking) — NOT a slot state.
2. **The display path for a conflict CLASS already exists end-to-end**:
   - `ui-common.js` engine DEFAULT branch renders `event.status === 'conflict'` → `.event-conflict` (red, line 758) — pages WITHOUT a custom `statusClassFn` get it free.
   - The shared class modal renders `status === 'conflict'` as "Scheduling conflict — needs attention" (ui-common.js line 933).
   - Venue view's `statusClassFn` gained `conflict → event-conflict` in merge-upstream-ui-2026-10 (contract parity, until now unreachable).
   - Cohort-timetable's `statusClassFn` takes `(div, e, isConflict)` where `isConflict = day.holiday` only — no real-conflict branch yet.
3. **The trait** (`ResolvesTimetableTimeline`) drives MyTimetable/CohortTimetable/StudentMyTimetable; `baseEvent` sets `status: 'normal'`; pending requests → `'pending'`; approved → `'replacement'`. VenueTimetable builds its own event map from `time_slots` with the same status vocabulary.
4. **replacement_requests table is empty and the replacement flow is Slice B** (not built) — a "needs replacement" REQUEST cannot be created app-natively yet.
5. MyTimetable blade + StudentMyTimetable blade have **no** custom `statusClassFn` → engine default styling.

## Rejected approaches

- **Stored DB status `'conflict'` on the session's slots** — rejected: it would REPLACE `'occupied'`, so (a) the venue view's `whereIn('status', ['occupied','pending'])` query makes the class VANISH from B005's grid, (b) the `groupBy(status)` totals skip unknown statuses (isset guard) — Total card would silently drop 4 slots, (c) the partial unique index `WHERE status IN ('pending','occupied')` would no longer protect the slot from double-booking. Three regressions for one flag.
- **A pending `replacement_requests` row** — rejected: semantically that means a lecturer PROPOSED a replacement (proposer/approver/FCFS approval flow — Slice B, not built); the user wants a conflict flag, not a fake request.
- **Mutating the seed/import data (re-scheduling the session)** — rejected: user explicitly said ignore data moves; only B006 lower-priority usages were data-side and they're acceptable under the priority rule.
- **Config-driven rule table** — rejected for v1: one rule (B005 no-Diploma) produces conflicts today; a hardcoded, documented evaluator in one shared place is simpler; promote to config when the rule count grows (booking-time enforcement, Slice B).

## Final solution (derived, not stored)

- **Shared conflict derivation**: a small evaluator — session conflicts when `venue.room_code === 'B005'` AND any cohort's `programme_code` starts with `D`. Lived in the trait's `baseEvent` (covers MyTimetable/CohortTimetable/StudentMyTimetable) + VenueTimetable's own event builder — 2 consumers now, promoted appropriately per §10.0.6 when booking-time enforcement becomes the 3rd (Slice B). Exact shape (trait method vs service) decided in design.
- **Conflict events render via the EXISTING paths**: engine default (my/student pages, loud red `.event-conflict`), modal "needs attention" line. Owner-gating for pages with custom `statusClassFn`: venue + cohort adopt the upstream owner-gated pattern (mine = loud `event-conflict`, others = quiet `event-public-holiday`) — parity with the upstream venue/cohort behaviour.
- **Honest**: no replace button anywhere (write path = Slice B); the modal's "needs attention" text is the existing honest affordance.

## Mapping (display matrix)

| Page | statusClassFn | Conflict rendering |
|---|---|---|
| MyTimetable (Daniel) | none | engine default → `.event-conflict` red (loud, it's his) |
| StudentMyTimetable (DFT/DSF students) | none | engine default → `.event-conflict` red |
| CohortTimetable | custom (isConflict=holiday) | NEW status branch: mine → `event-conflict`, others → `event-public-holiday` |
| VenueTimetable | custom | EXISTING `event-conflict` branch — extend to owner-gated (mine loud / others quiet) for upstream parity |

## Known open questions

- Should B006 priority ever produce a display state? (Decision: NO for v1 — priority is a booking-time preference, not a violation; nothing renders differently today.)
- B005 rule source: hardcoded evaluator vs VENUE-RESTRICTIONS.md parse — hardcoded, documented, single place (see rejected approaches).
