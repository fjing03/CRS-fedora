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

## round 2 (the user's 2026-10-27 report): the pending make-ups painted `cell-occupied`

The user's receipt: the PENDING make-up rows (via the busy vector) rendered
`cell-occupied` (the error red) — the vector's ranges carried no status
kind. Resolved (the lane's own follow-on, the design's D4 rider):

- the engine's two make-up readers return the `rr status` alongside the
  range (`status: pending|approved`);
- `busyByWeekPayload`'s tuples gain the 5th element (the status kind, null
  for the class-side rows — the existing tests' `array_slice(0, 3)` trim
  stays green);
- the arrangement blade's `engineBlocked` arm splits: all-pending matchers
  → `cell-pending` + the aria `Pending — <holder>`; ANY non-pending
  matcher wins `cell-occupied` (the safe rank: an approved + a pending
  overlap renders red);
- the `.cell-pending` family color = `var(--color-tertiary-container)` /
  `--color-on-tertiary-container` (the user's law — the awaiting pair; the
  same-name-same-color canon: the awaiting surfaces ride this pair).

Receipts (Playwright, live demo): the pending cells = `cell-pending`
`Pending — You — AMSE2003(P) · B010 · 12:30-13:00 · … Replac…` with the
bg `rgba(219,168,0,0.35)` = tertiary-container ✓; the approved cells stay
`cell-occupied` `Occupied by … B009 … · Replacement (approved)` with the
error pair ✓. Suite 265/265; pint + phpstan 0 (the two edited files).
