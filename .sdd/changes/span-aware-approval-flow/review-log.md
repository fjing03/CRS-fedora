# Review Log — span-aware-approval-flow

## proposal Round 1 — 2026-10-17 (reviewer subprocess, glm-5.3-flash)
### 🔴 Fixed
- proposal.md item 11 was literally truncated (`[row-truncated]` marker) — the Q1 bulk-semantics
  tests had no landing place; regenerated the full `ApprovalWorkflowTest` coverage block (reject
  guard, stale-slot, FCFS, bulk semantics [the landing], happy path, multi-PL race).
- The queue's decided/history bucket was missing from Q3=A's approved-consumer list — added to
  item 5 (plus `reviewedBy/At` source statement in item 1's queue-data-sources block).
### 🟡 Addressed
- Venue-slots API approved anchor join added to the junction-switch list (item 5).
- Undo-toast ruling pinned: NO undo, "cannot be undone" copy (item 1).
- D7 free-room row identity pinned: keys on the ORIGINAL session's own booking
  (`class_session_id` + week), never room/time matching (item 4).
- Self-approval default moved to a NAMED grill slot **Q13** (pending user ack), no longer
  silently default (out-of-scope + review-log trail).
### 💡 Addressed
- EmailQueueTest provenance noted (uplifted from the shelved artifact's plan, bootstrapped new).
- Drain-test contract spelled out (item 7: real `queue:work --once` on the test connection,
  consumed-and-sent within that cycle, bounded <1 min).
- §10.0.6 promotion entry placed explicitly on design.md's agenda (impact table note).
### 🔴 Outstanding
- none

## proposal Round 2 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- Reject path never wrote `approver_id` (no `rejector_id` column exists) → rejected rows would
  show a blank Reviewed By → item 3 now pins `approver_id` = the deciding PL on reject too.
### 🟡 Addressed
- Duplicated D7-tests bullet + broken pointer deduped (single home in item 11; item 4 carries
  no tests section).
- Reject's junction handling pinned: release family VERBATIM incl. junction DELETE; rejected
  rows render their replacement range from the LEGACY anchor fallback (junction-miss path).
### 🔴 Outstanding
- none

## proposal Round 3 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- (none — no blockers)
### 🟡 Addressed
- Residual duplicate D7-test bullet removed (item 11 keeps ONE home for the D7 assertions, at
  its "D7 release tests" bullet; the stray anchor-only variant deleted).
- Item 1's queue-data-sources block gains the CANCELLED-row ruling: cancelled rows (junction
  deleted at cancel time) share the rejected bucket's LEGACY anchor fallback — one rendering
  rule for every junction-miss row.
### 🔴 Outstanding
- none — **verdict: "Ready to proceed."** ⇒ **proposal.md FROZEN** at 2026-10-17 Round 3.

## design Round 1 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- The frozen template's `approveNotesModal` was MISSING from the design (both single + bulk
  approve route through it; dropping it = falsified "for the audit trail" copy or silent 99.9%
  divergence) → new **D13**: the optional note is CAPTURED into the summary decision audit's
  `details.approver_note` (nullable JSON; NO new column); a bulk batch's one note lands in
  every batch member's summary audit; blank → no key (nothing fabricated). D1 step 7 + D7 +
  D10 wired to it.
### 🟡 Addressed
- D3's week keying pinned to the REQUEST row's `week_number` (the ORIGINAL occurrence's week,
  never the junction slots' — demo canary divergence W14/W5 documented).
- D3's exception-race claim replaced with the honest contract: the unique constraint BACKSTOPS
  the race; the catch treats it as 'already-stamped: goal achieved' and CONTINUES (no 500).
- D3/D7 naming collision resolved: top alias note ("proposal's D7 ≡ design D3") + D1 wording
  fixed ("original-release (D3)").
- D3(ii) flips explicitly VERSION-CHECKED (`WHERE id = ? AND version = captured`).
- 💡s: D5 signature takes `?ValidityResult` (or evaluates D6 itself) and OWNS the junction-miss
  fallback; D8 cohort-leg wording de-mudded (own job after commit, no ordering contract unless
  a test pins one); D10 thinking-out-loud fragment removed.
### 🔴 Outstanding
- none

## design Round 2 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- `action='decided'` was ILLEGAL (audit_logs CHECK :52–55 has no 'decided' — the approve txn
  would CHECK-FAIL at the audit write) → summary audits use the LEGAL tokens
  `approved`/`rejected` (mirror precedent: CancelPendingRequest audits 'cancelled').
- D3's catch-continue broke on PG (a UNIQUE violation aborts the txn — every later statement
  fails with 25P02) → the class_exceptions write is wrapped in a nested
  `DB::transaction` = SAVEPOINT; the catch rolls back ONLY to the savepoint, then the flips
  continue. Same swallow-the-violation contract rests solely on the savepoint now.
### 🟡 Addressed
- D1 step 8 carries the alias note inline too ("proposal items 2/4 'D7 original-release' ≡
  design D3") so task-generation walkers can't misread.
- D2: the request-row `FOR UPDATE` lock is held across the WHOLE release (stamp under the
  same lock — reject-vs-approve races serialize).
- D6 conflictReason map pinned to 2 cases (row moved → "already decided"; slot drift → the
  existing `slot_not_available` named-time copy; no third case).
- D5 pins junction-miss rows `slotValidity='valid'` + `conflictReason=null` (decided rows carry
  no conflict badge).
- D11 (Q13) gate: acknowledged as a pending user ack — the ack is the round-3 gate BEFORE the
  spec batch.
### 🔴 Outstanding
- none technical; Q13 ack is the last design-freeze gate.

## design Round 3 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- Round-2's 🔴1 had NOT actually landed (exact-string miss): D1 step 7 still carried
  `action='decided'` → now pinned `action='approved'` (LEGAL token) AND D2 step 5 pinned
  `action='rejected'`; D3's audit rider now says the release rides D1's summary audit
  (no separate row, same legal token).
### 🟡 Addressed
- `version = version + 1` increments named explicitly at D1(4), D2(3), D3(ii) (the 💡 three-site
  clause — task-generation cannot misread).
### 🔴 Outstanding
- none technical; the standing design-freeze gate = Q13 user ack (D11).

## design Round 4 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- The round-2/3 "legal action token" fix had repeatedly FAILED to land (exact-string misses in
  the row text; review-log claims outran the file). This round every pin was applied against
  grep-verified text and counted back: D1(7) `action`**`approved`** (LEGAL CHECK token;
  `decided` would CHECK-FAIL), D2(5) `action`**`rejected`**, `version = version + 1` named at
  D1(4) / D2(3) / D3(ii), D3's audit rider pinned INSIDE D1's summary audit (no separate row —
  proposal item 11's count contract), D1(8) dangling "alias note at the header" pointer replaced
  with the self-contained label statement.
### 🟡 Addressed
- (carried) D5 junction-miss `slotValidity='valid'` + `conflictReason=null`; D2 whole-release
  lock; D6 2-case map (all verified in-file this round).
### 🔴 Outstanding
- none technical; standing freeze gate = **Q13 user ack** (D11).

## design Round 5 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- The header alias-mapping line was NEVER landed (the round-1 atomic batch that would have
  added it was rejected wholesale) → the declarative alias note now lives in the design header
  (grep-verified) and D1(8) stays self-contained.
### 🟡 Addressed
- D5 signature type corrected `?ApprovalValidity` → **`?ValidityResult`** (the D6 evaluator's
  returned object; class-vs-object distinction pinned inline).
- D2(3) stray unbalanced paren cleaned.
### 🔴 Outstanding
- none (reviewer round 5 verdict: "design.md is ready" once these two land + grep-verified).
  NOTE (cap accounting): this one-line confirming round was justified by the reviewer's own
  round-5 verdict — no decision-level churn occurred. ⇒ **design.md FROZEN at R6 2026-10-17,
  EXCEPT the one standing gate: Q13 user ack (D11) before the spec batch.**

## design closing note — 2026-10-17
**Q13 user ack landed: "allow".** D11 ratified (the standing gate cleared); design.md is now
fully frozen — the spec batch proceeds.

## specs Round 1 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- **Data-driven junction fallback** (was bucket-keyed to decided rows): R3 now renders the
  LEGACY anchor whenever the junction has NO range — inclusive of a legacy PENDING anchor-only
  row. Basis: `ReplacementHistoriesTest:205–212` pins '14:00 – 14:30' for an anchor-only
  pending fixture; a bucket-keyed fallback would NOT break only that byte-baseline but fork
  implementers into two `toRow` branches.
### 🟡 Addressed
- R4 2-case map scoped to the PENDING body (decided rows' badge single-sourced at R3).
- Release family VERBATIM scoped to flip+DELETE only (cancel's skip semantics NOT copied).
- `ReplacementDecided` capture-at-QUEUE-TIME (pre-release snapshot; mail never re-reads a
  deleted junction).
- NFR 1.4 drain = separate method WITHOUT Queue::fake (real jobs row; Mail::fake still holds).
### 🔴 Outstanding
- none.

## specs Round 2 — 2026-10-17 (reviewer subprocess)
### 💡 Addressed
- R6 step-order swap (case-(a) at step 1); R2 step 7 gains `time_slot_id = anchor` symmetry;
  R4 case-(b) notes the version leg surfaces via the 0-affected flip guard (not `evaluate`);
  `$requestedBucket`'s role stated (bucket-labeled fields only, never the fallback).
### 🔴 Outstanding
- none — **spec batch FROZEN at 2026-10-17 Round 2.**

## tasks Round 1 — 2026-10-17 (reviewer subprocess)
### 🔴 Fixed
- (none — no blockers)
### 🟡 Addressed
- Demo-DB name corrected to `class_replacement` (CodingMAIN §98/134 SSOT; the header's
  operative discipline unchanged).
- Bulk-reject shared reason: T10 wording pin + T8b assertion (same reason in EVERY decided
  row's `rejection_reason`).
- Queue-page data-sourcing + PL-gate bullets added to T9 (spec scenario bullets land).
- 'Other' free-text verbatim assertion added (T8a reject bullet).
- T8 split → T8a (fixture + core decision tests) / T8b (bulk + race + D7) — honest ≤2h sizing.
### 💡 Addressed
- T20 phpstan = standing long form; T1 cancelled-row byte assertion; T1 promotion-entry note
  (changelog born at T11).
### 🔴 Outstanding
- none.

## tasks Round 2 — 2026-10-17 (confirming reviewer subprocess)
**Verdict: PASS — batch frozen.** All round-1 fixes verified in-file; tasks map every
spec-critical scenario to a named test task; ordering/dependency map (T1→T2 infrastructure →
T4–T8 actions → T9–T11 page → T12–T16 reads → T17–T19 notify → T20–T21 gates/closure) is sound;
no scope creep beyond the frozen artifacts. ⇒ **tasks.md FROZEN at 2026-10-17.**
