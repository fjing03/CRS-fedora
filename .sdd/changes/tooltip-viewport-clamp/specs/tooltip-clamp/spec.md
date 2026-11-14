# Spec — tooltip viewport clamp

> **Change:** `tooltip-viewport-clamp` · 2026-11-14

## R1 — The tooltip never leaves the window
- `left >= 0`, `right <= innerWidth`, `top >= 0`, `bottom <= innerHeight` at every
  window width from 390 up.
- No text is clipped horizontally: the box has a width ceiling and wraps.

**Scenario: a cell at the far right**
- Given a free cell whose right edge is within 20px of the window edge
- Then the tooltip is pulled left so it is fully visible

**Scenario: a cell at the bottom**
- Given a free cell near the bottom of the window
- Then the tooltip opens above it rather than off-screen

## R2 — It answers a narrowing window
- With the tooltip open, shrinking the window keeps it inside (re-placed, not
  stale). If the shrink scrolls the anchor cell out of sight, the box stays
  readable inside the window rather than disappearing.

## R2a — Every open path is placed the same way
- The `B` keyboard shortcut on a focused cell places the box exactly like a click.

## R2b — The pop-up displaces it
- Opening Select a Session hides the tooltip first (the overlay sits below it).

## R3 — Nothing else changes
- Same wording, same Arrange button, same hand-off.
- Close-on-scroll still works.

## Acceptance

- Probe receipts at 1440 / 1024 / 768 / 390 (right edge + bottom edge cases).
- `ModalConsistencyTest` untouched; no assertion weakened.
