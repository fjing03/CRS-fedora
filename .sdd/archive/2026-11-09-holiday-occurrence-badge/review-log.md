# Review log — holiday-occurrence-badge

## Batch 1 (proposal.md) Round 1 — 2026-10-27
### 🟡 Addressed (folded declaratively before design)
- The tip literal pinned: `· {label} holiday — no class` with the
  ` holiday` suffix only when absent (case-insensitive) — the exact
  user-sanctioned string; `· Deepavali Holiday (In Lieu) — no class`
  stays clean.
- First-match rule for same `(week, dayIndex)` collisions.
- W8 Monday added as a third falsifiable receipt.
- The `textContent`-identity receipt made mechanically checkable.
### 🔴 Outstanding
- (none) — verdict: ready for design.

## Batch 2 (design.md) Round 1 — 2026-10-27
### 🔴 Fixed
- The design invented a `div.cs-line` wrapper (the real occurrence
  markup is `<span class="cs-val">` inside `div.cs-seg`, blade
  ~:2600–2606) — the redraft rides the EXISTING span inside the SAME
  ternary; the `data-tip-pos="bottom"` dead attribute dropped; the
  W14-Fri/W8-Mon anchors get apply-time session/day picking.
### 🟡 Addressed
- `php -l` no longer claimed to validate inline template JS (the
  Playwright + textContent receipts carry it).
### 🔴 Outstanding
- (none)

## Batch 2 (design.md) Round 2 — 2026-10-27
### 🔴 Outstanding
- (none) — the round-1 blocker fully cleared; verdict: ready for apply.
### 🟡 Fixed (folded)
- The "no attr-escaper exists" receipt was WRONG: the global `escHtml`
  (ui-common.js :1327–1329) exists and is already proven as a `data-tip`
  attr escaper (:2042). Design updated to REUSE `escHtml` — no local
  duplicate (the reuse standing rule).

## Batch 3 (tasks.md) Round 1 — 2026-10-27
### 🔴 Outstanding
- (none) — verdict: PASS, freeze and proceed.
### Process note
- This review-log.md was created with this round's entry; the two
  design rounds and the round-1 fold are recorded above (the reviewer
  asked for their receipt — they are here, not in an archive).
