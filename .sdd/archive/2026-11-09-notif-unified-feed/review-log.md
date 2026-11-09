## proposal+design+tasks Round 1 — 2026-10-29 (combined batch)
### 🔴 Fixed
- (none — no blocking issues; verdict: ready to proceed)
### 🟡 Addressed / 💡 folds (ride along in the commit)
- Tab-wording comments folded: tests/notifications-island-testing-db.spec.ts
  ("lecturer tab" phrases) + notifications-island.spec.ts docblock — both
  reworded to the one-feed law so the per-tab mental model is not resurrected.
- The changelog records the supersession as a NEW dated entry (no history edit).
### 🔴 Outstanding
- (none)

## Applied receipt — 2026-10-29 (the user's "apply")
- T1: NavNotifications — the ONE-FEED render ($rows = the whole mailbox;
  $unreadInView = $unreadTotal; $roleView/resolveRoleView removed; $pageKey
  kept accepted, now non-selecting); the blade caught-up copy = the single
  frozen line; doc comments updated (component, ui-nav-bar, ui-common).
- T2: NotificationsBridgeTest — the AD-2 mapping test REPLACED by
  test_the_panel_shows_one_unified_feed_for_the_recipient (same rows on every
  pageKey + the isolation assertion); the island spec's AD-2 + caught-up legs
  replaced by the ONE-FEED + single-caught-up-line receipts; the testing-db
  spec's tab wording folded (review 💡).
- T3 matrix (:8030, fixtures swept after): 6 PL pages as 5425 — the SAME
  ids/order/badge (4) + the inactive row everywhere: PASS; mark-read sync —
  durable POST 4→3 unread visible read across pages: PASS; poll appearance —
  badge 3→4 within ≤10 s with a no-reload sentinel: PASS; 5 non-PL pages as
  5770 after a FAITHFUL logout+login — identical feed (their one row), badge
  equality: PASS; recipient isolation (only their rows): PASS; RBAC: the
  approval page → 403 Access Denied: PASS; zero console errors; wire 200s.
- Gates: lint PASSED; phpstan 0; the full suite 277/277; node --check;
  the :8000 island spec 7/7 (incl. the live ONE-FEED cross-page receipt).
- T5 (the demo receipt id 2 deletion) HELD — the user-run SQL or an explicit
  agent-run approval, separately.
- NO push; no demo-DB writes.
