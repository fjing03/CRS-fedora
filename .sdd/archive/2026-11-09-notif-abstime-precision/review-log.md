## proposal+design+tasks Round 1 — 2026-10-30 (combined batch)
### 🔴 Fixed
- (none — no blocking issues; verdict: ready to apply)
### 💡 Folds at freeze
- The redundant ->copy() before ->format() dropped at apply (format never mutates).
- The per-row compare form for the stability receipt adopted (compare row k
  against row k, not the whole-list JSON).
### 🔴 Outstanding
- (none)
## Applied receipt — 2026-10-30 (the user's F2 approval)
- T1: both edits landed (the island $absTime formats $n->created_at
  directly; the partial's data-tip source changed; the redundant ->copy()
  folded per the review).
- T2: the stability receipt — the same rows' abs-tips captured ≥10 s apart
  are BYTE-EQUAL (and the two dual-role twins — both written 03:50:19 — now
  show the identical "3:50 AM"; the drift artifact is gone).
- Gates: pint PASSED; phpstan 0; the full suite 277/277; islands 7/7 on
  :8000; node --check; the ritual.
- NO push; no DB writes.
