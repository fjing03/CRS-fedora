# Spec — venue-page-event-blocks (rendering contract)

Scope: the DB-backed venue timetable page (Livewire `VenueTimetable` + blade) after the rewrite. The frozen design (§1–§3) is the reference; this spec pins the externally observable requirements. Upstream mock specs are NOT covered (N/A per proposal S1).

## R1 — Event blocks with two-axis language

The venue grid renders scheduled classes as `.event-block` elements (never status cells):

- viewer's own normal class → class `event-mine` (3px border)
- others' normal class → class `event-others` (0.5px hairline)
- pending paths exist and resolve to `event-mine-pending` / `event-others-pending` — pending is truly unreachable from v1 data (zero `replacement_requests` rows); wired so Slice B only feeds data.
- conflict paths exist and resolve to `event-conflict` (owner) / `event-public-holiday` (others') — **conflict IS reachable in v1 data**: the component derives it from venue-restriction violations (`venueRestrictionConflict`), NOT from replacement requests. Live seed instance: the B005 AMIT2034 Wed-11:00 session, pinned by the existing `tests/venue-db.spec.ts` B005 conflict test — **that test MUST survive the re-pin** (owner → `event-conflict`; extend to pin the others'-side `event-public-holiday` where feasible).

**Scenario:** lecturer opens `?venue=B006` → every occupied slot renders a block; own classes carry `event-mine`, others' carry `event-others`; no `.vt-cell-*` element exists anywhere in the grid.

## R2 — PH-day exception (holiday only)

On a public-holiday day: empty slots render as offday cells (`cell-sun` on Sundays, `cell-ph` on holidays); the viewer's OWN class renders as a loud red `event-conflict` block; everyone else's classes render as empty offday cells (hidden). Sunday: all events hidden, all cells `cell-sun`. The marker text of `cell-ph`/`cell-sun` is hover-reveal (opacity 0 until hover) — visible-by-default offday signalling is the day-header badge.

**Scenario (payload contract, Feature layer):** a crafted session for the viewer starting on an imported holiday date appears in `eventsByWeek`, the holiday date is exposed via `holidaysJs`, the slot is excluded from `sumAvailable` and included in `sumTotal`/`sumMyClasses`/`sumMyHours`.
**Scenario (rendering, Playwright layer):** when the displayed week contains a holiday with the viewer's own class, the block carries `event-conflict` and offday empty cells carry `cell-ph` (no green tint).

## R3 — Legend

The legend bar shows exactly 4 items in order — Available (colour swatch, honest tip: booking arrives with the replacement workflow; Sunday, holiday and lead-time slots can't be booked), Normal (`event-normal` swatch), Pending (`event-pending`), Conflict / Public Holiday (`event-conflict`) — plus the ownership hint ("thick border (3px) = your classes · thin border (0.5px) = others'"). No 7-item legend remains. The guide-block "Slot colours" bullet is rewritten to the two-axis language (colour = status, border = ownership; PH-day own-class red) — the old 7-state wording must not survive.

## R4 — Tooltips

Each block exposes `data-name` (class name) and `data-tip2` = `lecturer · status` (status label via `eventStatusLabel`, holiday-aware). The rendered hover tooltip composes `name · lecturer · status` via theme.css `.event-block::after`.

## R5 — Venue dropdown

B006 (CiscoLab) is reachable only under the Lab category (3 categories: Tutorial / LectureHall / Lab); the standalone CiscoLab group assertion is gone. Each room option carries a `data-tip` full name.

## R6 — Summary cards

Same 5 ids: `sumTotal` (120 = 6 days × 20 DB rows per venue; no Sunday rows exist — the grid renders 7 columns but Sunday is always empty), `sumAvailable` (free slots **excluding Sunday and public-holiday slots**), `sumMyClasses` (viewer's sessions, distinct rows, visible blocks incl. own PH-day classes), `sumMyHours` (same rule, slot = 30 min), `sumOccupied` (booked slots). Descriptions adopt `info-keyword`/`warn-keyword` spans. Card 5 remains labelled "Occupied" (documented deviation from upstream's "Unavailable").

## R7 — Read-only honesty

No booking affordance on available cells; the Slice-B hint line stays. No fabricated pending/conflict data.

## R8 — Invariants unchanged

Week navigation, `?venue=` switching, twin-merge folding, auth gating, and the D10 legacy mock fallback behave as in v1. No migrations; no new endpoints; `/api/v1` stays commented.
