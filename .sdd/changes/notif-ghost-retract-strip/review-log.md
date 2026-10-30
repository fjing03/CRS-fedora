## proposal Round 1 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Outstanding
- (none)
### 🟡 Fixed (this round, folded into proposal.md)
- 🟡1 — the C2-family verify row added (the B110/5770 trap = the no-claim path's cheapest regression detector).
- 🟡2 — R2's condition defined as the MATCHING-attribution selection keep-frozen branch (closes the third-state variant where a surviving selection belongs to another module).
- 🟡3 — R3 added: the held reason string untouched (byte-identical).
### 💡 Addressed
- R1 simplified to the single updateCounter() rider (it chains updateSelectionSummary/Progress/Stats at :1334 — verified :1313-1336).
## proposal Round 2 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Outstanding
- (none — READY; verdict text: "READY to proceed. No remaining 🔴. design.md may be authored; pin the R2 match key there.")
### 🟡 Addressed
- R2 match semantics pinned in design.md (D0/D2: the pinned key = intentReason==='in-the-way'; the equivalence argued by the consume/placement code paths).
### 💡 Addressed
- The stale :1491-1494 comment cleanup folded into D1.
## design Round 2 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Fixed (folded this round)
- 🔴1 — the keep-frozen `in-the-way` arm had NO gate. Folded in D3/D5: a live
  receipt is DATA-BLOCKED — the lateral NOT-EXISTS SQL scan shows ZERO
  subject slots free on any B-Block grid's first half-hour (the legacy
  auto-pick route cannot place); the :8030 fixtures seed from the same
  import, so a fixture row = a seeder change (rejected). Compensating gates:
  the D0 equivalence argument + the two no-claim rows catching the DANGEROUS
  inversion + the changelog note. The lower-risk inversion covered neither —
  stated honestly.
### 🟡 Addressed (folded this round)
- 🟡1 — D0's claim narrowed to "clause-carrying renders".
- 🟡2 — D5 states the inline JS is syntax-covered by the Playwright family.
### 💡 Addressed
- The :1622-1623 anchor drift folded into D1's text (the banner-drop anchor corrected).
## design Round 3 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Outstanding
- (none — READY; "the compensating-gate argument verified in code, not hand-wavy": the single-setter :3227, the two arm sites :2858/:4180, evaluateBookingFit resets intentReason at :3026.)
### 💡 Addressed
- The D0 "fallbxAttribution" typo noted for the apply pass (doc-only).
## tasks Round 1 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Outstanding
- (none — READY once folded)
### 🟡 Addressed (folded this round)
- 🟡1 — T3's strip gate scoped: mandatory on the B002-ghost row; on C2b it is the validated natural zero (the fallback short-circuit places nothing) — the wording no longer claims a lane-specific retract gate there.
- 🟡2 — D5 gains the honest-zeros `proceed()` receipt (declarative append to the frozen D5 — the receipt-list detail, no implementer-behaviour change).
### 💡 Addressed
- The chained trio anchor corrected (:1333-1335); the deselectBlock comment anchor corrected (:1489-1492).

## tasks Round 2 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Outstanding
- (none — verdict "READY for /sdd-apply" after the T3 scope fold + the T4/D5 receipt reconciliation; both folded.)

## Applied receipts — 2026-10-30
- T1: the retract tail now reads `resolveBookingIntent(); evaluateBookingFit(); updateCounter();`
  (the rider AFTER the re-arm, order per D1); the deselectBlock's stale
  comment reworded (the :1333-1335 chaining truth).
- T2: the manual branch's clause = the `in-the-way` ternary — the frozen arm
  byte-identical; the no-claim arm `AMSE2003 (L) — its sessions fit B002;
  pick another free slot for it.` (no Pre-selected, no stays). The R3
  reason strings untouched (verified: grep shows them byte-identical).
  (One implementation slip caught in-flight: the first edit placed the
  explanatory /* */ INSIDE the template literal — it would render as literal
  text; moved outside the template before any receipt run.)
- T3: C2b + B002-ghost rows flipped (the no-claim asserts + the
  .sel-summary-card toHaveCount(0) gate in both; the strip gate mandatory on
  B002-ghost per D3, the validated natural zero on C2b). The B002-ghost
  row's week index bumped W6→W10: the demo calendar drifted the W6-Fri slot
  into the 3-working-day window (its banner read 'within 3 working days'),
  hiding the held receipt — the occupancy is recurring, the held receipt
  moves with it (noted in the row's comment).
- T4 gates: pint PASSED; phpstan-1G 0; the full suite 277/277; the family
  10/10 (--grep-invert "TC70").
- The :8000 demo receipt (5425, read-only): the held banner + the NO-CLAIM
  clause + ZERO .sel-summary-card + infoTotal "0 of 4 slots" + the submit
  button disabled with the tip "Select a time slot first to enable
  submission" (the honest-zeros state the ghost's card used to falsify; the
  proceed()-click probe itselfproved the button unclickable — converted to
  the state read per D5's reconciliation). NO push; NO DB writes.
