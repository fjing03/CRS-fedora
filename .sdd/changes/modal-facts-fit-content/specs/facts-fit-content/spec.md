# Spec — facts-fit-content

> **Change:** `modal-facts-fit-content` · 2026-11-14

## R1 — The facts strip does not widen a pop-up

- `.modal .detail-facts` declares `width: 0` and `min-width: 100%`.
- A details pop-up's width is decided by its tabs and rows, not by the strip.

**Scenario: a details pop-up with a long facts strip**
- Given Request Details with Staff + email + Cohorts + Students + Conflict
- Then the pop-up is narrower than its ceiling and its rows fill the box
- And the strip still spans the pop-up's full inner width

## R2 — Nothing breaks the bounds

- No pop-up becomes narrower than 420px; none becomes wider than its ceiling.
- No horizontal clipping (`scrollWidth <= clientWidth + 1`).

## R3 — The two approved maxima

- Confirm Cancellation's shell says `680` (was 635) and stays a maximum.
- Select a Session already uses `.modal--detail` (ceiling 680) — no change.
- The `560` shell in the same partial is `#slotWarningOverlay` ("Slot Too
  Short") and is deliberately untouched.

## Acceptance

- `ModalConsistencyTest` asserts the rule and the two blades.
- `modal-responsive-width.spec.ts` still green (floor/ceiling/no clipping on the
  two fit-content pages).
- Measurements recorded in `page-changelogs/modal-responsive-changelog.md`.
