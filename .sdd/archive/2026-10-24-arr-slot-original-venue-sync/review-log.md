# review-log — `arr-slot-original-venue-sync`

## create Round 1 — 2026-10-24 (pi session, prompt = the handoff user law)
### 🔴 Fixed
- (during creation) the prompt's sketch dropped the whole venue family for
  `opts.auto` picks — that would regress the post-boot subject-switch
  dead-venue fallback + the pre-boot arrival fallback. Fixed in design.md
  D1: the session-change branch keeps the old fallback VERBATIM but gated
  to `opts && opts.auto`; the new law owns only the non-auto path.
- offered-check tightened: `filtered.some(...)` (not raw deck match) so a
  slot whose orig venue left the deck can't trip the select() phantom
  guard silently.
### 🟡 Addressed
- D3/D4 documented: equal-target refire is safe (revertingChange pair);
  orig-not-offered keep case carries no new toast.
### 🔴 Outstanding
- none — proposal/design/tasks consistent; proceeding to apply.

## verify — 2026-10-24 (implementation receipts)
### receipts (all run today, tree HEAD-pre-commit)
- Live receipt (probe BEFORE the formal spec): 38/14 → AMSE1003 manual pick:
  label B010 → B111, chips followed (cap 80 / data-tip "Capacity — 80 seats" /
  desc "Lecture Hall. For LECTURE.").
- Spec `tests/arr-slot-original-venue-sync.spec.ts` 5/5 ×2 (plus setup 3/3
  both runs).
- Spot battery (7 files, --grep-invert "TC70"): 25/25 in 44.6s.
- pint passed; phpstan-1G 0 errors; phpunit 230/230 (264s).
- The correction: design.md D1 updated by the probe — the post-boot
  subject-switch fallback is ALREADY inert today (applySubjectChange re-keys
  currentSessionId before the pick); the restructure changes NO auto-path
  behavior (pre-boot arrival fallback retained verbatim on opts.auto).
- BLOCKED rows honestly recorded in the spec header + design D4; no data
  invented.
### 🔴 Outstanding
- none
## round 2 — 2026-10-24 (the 68/14 whole-red sighting, user report -> decision-level amendment)
### sourcing
- The user's live report IS the round-2 word: after a subject switch the
  venue must agree with the strip BEFORE a manual pick (the strip read
  "@ B010", the dropdown stayed B100, every cell red until a manual pick).
### mechanism receipts (probe/stepwise markers, /tmp scratch, purged after)
- applyDefaultSlotSelection early-returns on the pinned-slot branch-1 facts
  (hydrated from PAGE_DATA.pinnedSession) -> the auto-pin pick never
  commits; no commit = no round-1 sync on the switch path (probe receipts:
  markers BOOTCLOSURE/DEFAULTPICK fired; SELECT/COMMIT silently absent).
- The staging also surfaced a commit-time ctx-freshness race while probing
  (the commit ran BEFORE applySubjectChange's tail re-filter) -> the sync
  moved INTO the shared syncVenueFamily(slot, phase) helper; the manual
  pick path (commitSlotSelection) and the auto-pin path
  (applySubjectChange's post-boot tail) both call it; the helper re-runs
  buildVenueFilter() itself.
### gates (rerun after the amendment)
- spec 9 rows x2 incl. the NEW 68-sighting row (label B010 + grid
  availability WITHOUT a manual pick); the alternation row rewritten to the
  round-2 reality (the auto-pin now follows B111 immediately).
- spot battery 25/25; pint; phpstan-1G 0; phpunit 230/230.
### 🔴 Outstanding
- none

## archive-day verify — 2026-10-24
- **PASS.** Fresh receipts on the CURRENT tree (post venue-restrictions-enforcement): spec 9/9 (1.3 m); the T8 gate runs (battery 25/25 --grep-invert TC70; pint; phpstan-1G 0; phpunit 258/258) executed AFTER the enforcement commit that touched the same blade — current-tree certified.
### Verdict: PASS — archived to .sdd/archive/2026-10-24-*. 
