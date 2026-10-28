# Review log — occupied-cell-explanations

## Batch 1 (proposal.md) Round 1 — 2026-10-27
### 🔴 Fixed
- The pre-joined `\n\n` wire format contradicted `renderHolderTip`'s
  per-tuple contract (header count + `tagsFor` parse per element).
  Resolution: `cellData[3]` is an ARRAY of block strings; the Blade
  builds one pseudo-match `[di, hi, hi+1, label, statusKind]` per
  element and calls the UNCHANGED renderer.
### 🟡 Addressed
- The truncated `for cs 39 neither tag may fire except …` criterion —
  replaced by the tautology receipt (🏛 fires on every grid holder,
  intended noise) + the negative `|null` arm criterion.
- The `@return` docblock shape migration stated explicitly (updated,
  never deleted).
- The pending-arm copy re-phrased as proposed-until-design-freeze.
- The payload-size risk now acknowledges the per-row duplication
  factor (mult-hour class ×N rows) + the dictionary fallback.
### 🔴 Outstanding
- (none — round 2 clean)

## Batch 1 (proposal.md) Round 2 — 2026-10-27
### 🔴 Outstanding
- (none) — verdict: ready for design batch.

## Batch 2 (design.md) Round 1 — 2026-10-27
### 🔴 Outstanding
- (none)
### 🟡 Addressed
- D1 now states the internal `busyCellHolderLabels()` call at :405 is
  REPLACED by the threaded parameter (double-compute guard).
- D4 gains the grid-arm statusKind-per-cell simplification receipt
  (per-row truth not recoverable without touching the frozen map; the
  cell CLASS always follows the grid status).
- D5 gains the payload-size gate (measure; >90KB ⇒ dictionary fallback).
- D2 gains the LIMIT-3 key-parse law (`explode(':', $key, 3)` — room =
  remainder; a full explode corrupts the room = the round-5 phantom).
Output:
- (folded before round 2 — see below)

## Batch 2 (design.md) Round 2 — 2026-10-27 (amended design; reviewer round not re-run — round 1 verdict was "proceed after folding", no 🔴)
### Note
- The four 🟡 items were folded declaratively; round 1 had zero
  blockers and the folds do not alter frozen decision-level content.

## Batch 3 (tasks.md) Round 1 — 2026-10-27
### 🟡 Addressed
- T4 reworded: phpunit asserts payload status codes/label arrays; the
  statusKind→tip-kind derivation is Playwright-only.
- T4 gains the payload-size gate's measured decision (already in D5).
- T5's stale-serve ritual split into the TWO separate commands (never
  one string) + the :8001 sibling restart.
- T3's `php -l` limitation noted (inline JS validated by T4's
  Playwright only).
### 🔴 Outstanding
- (none) — verdict: ready for /sdd-apply.
