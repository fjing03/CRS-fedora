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
