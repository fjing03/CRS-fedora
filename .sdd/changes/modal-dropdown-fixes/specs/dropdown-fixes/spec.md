# Spec — dropdown fixes

> **Change:** `modal-dropdown-fixes` · 2026-11-14

## R1 — The session list is fully visible
- Opening the session picker inside a pop-up shows **every** row of the list.
- No row is clipped by the pop-up body or the pop-up shell.
- Closing the picker restores the pinned layout's clipping rules.

**Scenario: open the picker**
- Given the Select a Session pop-up with two conflicted sessions
- Then both rows are visible and clickable
- And the panel's bottom edge is inside the viewport

## R2 — One arrow for the pop-up's selects
- The Subject select draws the same chevron as the Session picker: same 10×6
  shape, same 12px right inset.
- No text is hidden under the arrow (`padding-right` ≥ arrow + inset).
- Page-level selects outside a pop-up are unchanged.

## R3 — Nothing else moves
- Pop-up width, header, footer, buttons, warning caveat: unchanged.
- No new colour token: the chevron reuses the sanctioned data URI.

## Acceptance

- Read-only probe (above) recorded in the changelog.
- `ModalConsistencyTest` still green; no assertion weakened.
