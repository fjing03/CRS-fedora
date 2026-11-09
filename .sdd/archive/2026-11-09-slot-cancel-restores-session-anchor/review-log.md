# Review log — slot-cancel-restores-session-anchor

## Batch 1 (proposal+design+tasks) Round 1 — 2026-10-28
### 🔴 Outstanding
- (none) — verdict: ready for apply (receipt-side conditions).
### 🟡 Addressed (folded declaratively)
- Receipt 1 gained the state-row assert via the OBSERVABLE pair (the
  restored label + no `.slot-dd-item.selected`) + the code-walk receipt
  (selectedOriginalSlot is closure-scoped; the else arm is the only null
  writer) — applied as R1 in the live probe (all four asserts PASS).
- Receipt 2 concretised: a reachable non-handoff state needs the guard's
  live block + a dropdown pick; the LIVE probe could not reach the modal
  in that shape (documented honestly above); the false-arm receipt rides
  the reviewer's static chain (pinnedSessionPayload null ⇒
  anchorSlotFromPinned null ⇒ helper false ⇒ the byte-identical
  placeholder) — the author's D4b fold in design.md.
### Applied receipts
- R1 (the user's exact flow): landing = after-cancel trigger byte-equal;
  strip unchanged; pinnedOccurrence unchanged; 0 `.selected` items.
- Format-writer grep: writeSlotTriggerFromAnchor ×1 definition + ×2 call
  sites; NO third inline 'W'+ builder remains.
