# Review log — notifications-real-bridge

## Batch 1 (proposal.md) Round 1 — 2026-10-28
### 🔴 Fixed
- The type column stores the FROZEN tile keys (submitted/awaiting/approved/
  rejected/update) — the mapping-law table added (a new-style type would
  render unstyled tiles + the fallback glyph).
- The read path keeps the D4 law: native anchors + the capture-phase
  durable POST (fetch keepalive) — the wire:click/js() round-trip idea
  was rejected.
### 🟡 Carried (folded at freeze)
- The AD-2 role_view mapping; the island topology; the disabled-row read
  path (not click-markable; mark-all to zero); PENDING_NAV_COUNT
  untouched; the reminder dedupe/re-arm; the mock_fallback stub.

## Batch 2+3 (design.md + tasks.md) Round 2 — 2026-10-28
### 🔴 Fixed (before round 3)
- Student rows linked the lecturer-gated /cohort-timetable → the
  per-role link law table (students → /student-my-timetable-ui).
- Mount B wrapped only #notifList → now the WHOLE .notif-panel body
  (pill/mark-all/toggle/list/empty) renders server-side from the one
  unread total; the variant property discriminates the mounts;
  initNotifPanel reduced to open/close/Esc/overlay + the rewritten
  capture click.
### 🟡 Addressed (folded)
- The batch-resolve N+1 law; notifyDeptPls DEPT-ONLY pin + the
  owner-dept resolution receipt; the CancelClass receipt correction
  (adds its own DB::afterCommit); the demo-seed hand-run task T6; the
  :8000 port law; the poll-survival receipt; the extra D6 retire names.

## Batch 2+3 Round 3 — 2026-10-28
### 🔴 Outstanding
- (none) — verdict: ready to freeze.
### 💡 Folds at freeze
- The tab parenthetical restated as the per-page map (the tab follows
  the PAGE, never the row's type).
- The :8001 clause clarified (killing a stale sibling; the serve = :8000).

## Applied receipt — the poll-cycle proof (2026-10-28, live demo DB)
- T0: the page open, the badge = 1 (one receipt row). A SECOND DB-side
  event fired mid-session via a second process; **+11 s, no reload**:
  the badge = 2 (PASS — the wire:poll island updated itself).
- The click receipt: row click → navigated to the row's link AND the
  durable POST landed (the badge 2 → 1 after the back-navigation).
- The poll-survival receipt (AD-15): an OPEN panel survived a poll
  morph (the open state + the list intact).
- The two receipt rows stay in the demo DB (labelled 'Demo receipt —
  poll cycle*'; the removal SQL is in docs/demo-db-data-changes.md's
  convention — the user deletes at will).
