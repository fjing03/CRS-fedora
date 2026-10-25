
## Round 1 — 2026-10-25 (glm-5.3-flash)
### 🔴 Fixed (all three code-grounded)
 - confirmChangeWithSelection's early-out `if (!selectedBlock)` nullified the
   subject/slot half post-hop — widened to `(!selectedBlock && getGlobalTotal() === 0)`.
 - discardAllSelections: deselectBlock's updateCounter rider is DEAD when
   !selectedBlock — the helper pays its own updateCounter() + updateSelectionSummary().
 - the booking-intent pointer-only capture misses the cross-week case and the
   pre-fill would RESURRECT on reload — superseded by a STORE-WIDE spent-scan
   (same fields intentMatchesBlock :2870 compares); the old captures REMOVED.
### 🟡 Adopted
 - selectionHistory.length = 0 in the helper (the proceed()/pageshow undo law).
 - D4 row 8 (the hop itself stays silent) + the #selCount/footer-button receipt
   strengthening; T2's row count folded (7 → 8).
 - advisory 6 pinned: no opts.auto bypass (auto picks ride a fresh/wiped store).
### Verdict (round 2): READY TO APPLY.
