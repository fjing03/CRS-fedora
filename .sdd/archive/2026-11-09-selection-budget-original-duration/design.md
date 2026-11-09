# design — `selection-budget-original-duration`

## D1 — the budget law (one quantity, three sites)

`MAX_SELECTION` = **the CURRENT occurrence's span** (the Original Class's
half-hour span, min 1 / max 4) — set at every occurrence-grain site:

- Site A (the boot URL `&duration=` branch, ~:3665):
  `MAX_SELECTION = BLOCK_SPAN` (span already computed = round(hrs × 2), the
  1..4 clamp unchanged). `urlSpanLocked` keeps ONLY the width authority
  (R1).
- Site B (the pinned-session hydration, ~:2624):
  `MAX_SELECTION = BLOCK_SPAN` (replaces the raise-only `Math.max`).
- Site C (the commit clamp, ~:3380):
  `MAX_SELECTION = slotSpan` (the same min/max family).
- **The comment-retirement list (COMPLETE — the round-1 🟡5):** every
  sentence stating the old budget regime rewrites in this change: the F-8
  block (~:3366–3379: "0.5–4 h → ≤8 slots" = the drift; "MAX_SELECTION
  floor stays ≥ BLOCK_SPAN" at :3375 = now the OPPOSITE of the law), the
  boot declaration (~:1206–1207: "the multi-week BUDGET stays
  MAX_SELECTION"), and the URL branch's "book twice" note (~:3662–3663 —
  the R3 supersession).

The `urlSpanLocked && opts.auto` commit keeps its width-preserving guard
(BLOCK_SPAN untouched) — but the BUDGET still flexes (R1's consequence:
an auto commit under a handoff whose span differs sets the budget to the
committed span = the occurrence's own duration, the row's live facts).

## D2 — the counter + the strings

- The counter already interpolates MAX_SELECTION (L1384/L1420) — the
  strings land per D1: the URL arrivals show the URL span at boot; the
  pinned/landings show it at the first commit.
- The rejection toast rewords: `You’ve reached the ' + MAX_SELECTION +
  '-slot limit — clear your selection to pick another.` (the budget is
  occurrence-grain; "another week's" read wrong).
- Post-clear (the undo/回退): the budget STAYS the occurrence's span
  (a cleared selection does not reset the budget to 4 — the occurrence
  governs; the counter returns to `0 of N`).
- **The auto-flex readers (round-1 🟡5):** the summary tip (:1429) and
  the progress bar (:2190) interpolate MAX_SELECTION — they ride the
  variable, no copy change (verify lists them as reviewed readers).

## D3 — the existing rows' re-anchors (per the frozen R3)

- `confirm-guards.spec.ts` ×3: re-point to each row's context occurrence
  (each asserts the dynamic string its own flow commits).
- `ui-regression.spec.ts` TC-4.1/4.2/4.3: the receipts rule — each row's
  anchor occurrence's span verified during the apply; the asserts re-point
  to the dynamic strings.
- `MANUAL-TEST-CASES.md` arrangement row: the budget wording re-points.

## D4 — the test plan (the acceptance ledger)

`tests/selection-budget-original-duration.spec.ts`:
- **The T row (1h)**: land `?session=68&week=14` (AMSE2003 T, 1h) → pick a
  free Fri 1–2 cell in W6 → the summary = the one 1-hour block; switch
  W7 → pick another 1h → REJECTED with the 2-slot toast; the summary
  keeps the ONE selection; the counter = "2 of 2 slots".
- **The L row (2h)**: land the pinned 2-h AMSE2003 L handoff → pick a
  2h block in W7 → switch W6 → pick → REJECTED with the 4-slot toast
  (the regression guard for the old-correct path).
- **The fixtures verified DURING the apply, never assumed** (the round-1
  🟡2): the exact session ids (67/68 or their deck siblings), the T/L
  spans (2/4 slots) and the free-cell coordinates re-read from the live
  payload before the rows freeze — a wrong id fails loudly at the test.
- **The budget-flex receipt**: the counter string changes when the
  pinned occurrence changes (the T → "2 of 2"; the L → "4 of 4").
- **The clamp-edge family** (the frozen proposal's commitment): the
  min-1/max-4 clamp produces the budget 1 for a 0.5h class ("0 of 1
  slots") and 4 for a 4-slot-capped class. Fixture basis: the T-row's
  counter receipts assert the render family at its own span (2 of 2);
  the 0.5h/4-slot edges validate through the SAME clamp path (Site C's
  min/max family) — asserted inside the T-row's receipt artifacts
  (the unit-level provider rows enumerate span 1 and span 4), never a
  separately invented browser row.

## D5 — gates

The lane's spec ×2 (`--grep-invert "TC70"`); confirm-guards + ui-regression
re-anchored rows green; pint; phpstan-1G; phpunit 258; the spot battery;
the changelog entry.
