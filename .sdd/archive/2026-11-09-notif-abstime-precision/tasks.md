# Tasks — notif-abstime-precision

## T1. The two blade edits (≤0.25h)
- [x] The island `$absTime` closure → format the stored timestamp directly.
- [x] The notif-row partial's `data-tip` source → `$n->created_at`.

## T2. The stability receipt (≤0.5h)
- [x] :8000 — the same row's abs-tips captured ≥10 s apart are EQUAL.
- [x] The :8000 island spec (7/7) + node check + the full suite.

## T3. Gates + bookkeeping (≤0.3h)
- [x] pint + phpstan 0 (the blade compiles to PHP); the stale-serve ritual.
- [x] The changelog entry + ONE commit (`style:` — display-only), no push;
      sdd.yaml → applied on green; archive on the user's word.
