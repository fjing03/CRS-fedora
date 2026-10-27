# review-log — makeup-busy-vector

## round 1 (glm-5.3-flash, 2026-10-27) — 2 🔴 blockers + 🟡 folds

- 🔴1 the D2 owner-scoping vs the locked 1(b) "everyone's" wording —
  the two artifacts DISAGREE; the reviewer demands the user re-confirm
  before freeze. PENDING THE USER (the ask returns to them: the literal
  unscoped reading blocks OTHER lecturers' calendars at the make-up's
  times — the engineering reading scopes the lecturer vector to the
  make-up's OWN lecturer, the cohort vector stays unowned).

- 🔴2 the T3 clause contradictory → RESOLVED: one policy ("the flipping
  receipts are re-cut without weakening").

- 🟡 the T2/D4 per-row label-key law → FOLDED into design D4 (+ the
  reviewer's slotIndex format check recorded: the format is a non-issue).

- 🟡 the D2 merge shape (independent query + array_merge, not bolted
  onto the class-side chains) → FOLDED into design D2's preamble.

- ✅ the consumer audit: validateSlot/findAvailableSlots/
  busyByWeekPayload compose through BOTH vectors — one source holds.

- ✅ the double status filter holds against drift both ways (a cancelled
  rr with slots pending → excluded by the rr filter; a pending rr with
  released slots → excluded by the slot filter — honest).

- ✅ the half-open boundary law is reachable: 13:00/14:30 unblocked via
  the existing `overlaps()`.

## freeze round (2026-10-27, the user's ruling) — RESOLVED

- 🔴1 the owner scope → THE USER'S RULING (verbatim): **"(i) — Owner-
  scoped lecturer vector. The system must check venue, cohort, and
  lecturer conflicts independently. A pending make-up class should block
  only the resources it uses. Other lecturers must still be able to book
  the same time if they use a different venue, different cohort(s), and
  a different lecturer. The cohort and venue checks must apply across
  all lecturers, while the lecturer check should apply to the lecturer
  assigned to the class. All checks must prevent overlapping time slots,
  not only exact time matches."** — the design D2's owner-scoped lecturer
  vector + the cohort-side ownerless join = the EXACT ruling (the
  overlapping-time law = the existing `overlaps()` half-open math, the
  reviewer already verified it reachable).
- Applied + verified: phpunit 265/265 (the 3 new receipts), pint +
  phpstan 0 (the DB::table rewrap of the label rows killed the 11
  dynamic-alias error lines), the Playwright receipt on the live demo:
  B010 Fri W6 13:30/14:00 = `cell-occupied` with the honest holder aria
  (`You — BMSE3153(P) · B009 · … · Replacement (approved)`) — the exact
  bug9 repro now visually blocked.
