## proposal.md + design.md + tasks.md — Round 1 (combined batch, small one-line lane) — 2026-10-29
### 🔴 Fixed
- (none — the reviewer found NO blocking issues; verdict: ready for /sdd-apply)
### 🟡 Addressed (folded before freeze, declarative)
- The :8000 caught-up receipt is NOT a regression pin (it passes in both
  the broken and the fixed state) — the proposal's overclaim rewritten;
  apply tightens the row-visible leg to assert the pl row's anchor really
  renders in the list. The D3 mapping test = the regression pin.
- The stale "UNDER USER VERDICT" note in tests/notifications-island.spec.ts
  :49-52 added to T3 (comment-only close; alongside ui-common.js's).
- D1 gain the footnote: /replacement-arrangement (hideNav=regression pin) never
  mounts the panel/bell — completeness row only, not a receiptable surface.
- Anchor fixes: resolveRoleView() = NavNotifications.php:88-95 (not :47);
  the include = ui-nav-bar.blade.php:127 (not :126).
### 🔴 Outstanding
- (none)

## Applied receipt — 2026-10-29 (the user's "apply")

- T1: the one-line fix landed (`ui-nav-bar.blade.php:127` → `$pageKey ?? $activeNav ?? ''` + the D2 comment).
- T2: `test_the_page_keys_map_the_right_tab_rows` — 11/11 in the file, total suite 277/277 (266 + 7 bridge + 3 re-verify + 1 this lane); lint PASSED; phpstan 0.
- T3: stale-serve ritual; `notifications-island.spec.ts` 7/7 on :8000 (the tightened
  AD-2 pin now FAILS on regression and PASSes live — roleView "pl", row 3
  rendered as the /request-approval-ui anchor, captured innerHTML receipt);
  `notifications-island-testing-db.spec.ts` 5/5 on the reseeded :8030 (regression);
  both stale in-code "UNDER USER VERDICT" notes closed (ui-common.js + the spec).
- NO push; no demo-DB writes (the real pl row 3 is the visible proof).
