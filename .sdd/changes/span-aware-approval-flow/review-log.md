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
