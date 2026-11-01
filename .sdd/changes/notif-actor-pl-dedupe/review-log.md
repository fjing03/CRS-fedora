# Review log — notif-actor-pl-dedupe

## proposal+design+tasks Round 1 — 2026-10-30 (combined batch)
### 🔴 Fixed (declaration-level, before freeze)
- T3 verify arithmetic disambiguated: the check is USER-1'S MAILBOX
  5 → 4 rows (verified read-only pre-apply: ids 3,5,7,8,31 → 31 goes); the
  proposal's "26 → 25" naming was an event-shape figure, not the demo check.
- T4 house-law explicitness: the Playwright island run MUST carry
  --grep-invert "TC70"; phpstan MUST be vendor/bin/phpstan analyse
  --memory-limit=1G (types:check crashes at 128 M).
### 🟡 Addressed
- The demo-DB row-level claims re-receipted read-only into review-log before
  apply (the reviewer could not execute SQL; the agent pins the SELECT
  receipts here).
- The dept-PL skip gains the defensive (int) casts per house convention.
- The added pl-row assertion pins to the EVENT (title + class_session_id=40
  anchor), not merely role_view globally.
- review-log.md created (this round's record; the folder lacked it).
### 💡 Carried (out of scope, recorded)
- Undo leaves its cancelled-class notification rows behind (pre-existing);
  a future lane may address the post-undo mailbox semantics.
### 🔴 Outstanding
- (none — conditional go accepted, both declaration fixes landed)

## Applied receipt — 2026-10-30 (the user's F1 + the cleanup approval)
- T1: the (int)-guarded skip landed in the dept-PL loop; php -l green.
- T2: the bridge test now asserts NO pl-role row for the owner on this event
  (event-pinned: role + class_session_id + title) while user 2 keeps theirs
  — 11/11 file green.
- T3: the demo twin deleted (verified 1 pre-count; DELETE 1; user 1's
  mailbox 5 → 4; only id 8 remains for the W6 event) — logged in
  docs/demo-db-data-changes.md (agent-run, explicitly user-approved).
- T4 gates: pint + phpstan 0 (1G) + the full suite 277/277 + the islands
  spec 7/7 (--grep-invert "TC70" as the hard guard).
- NO push; no other demo row touched.
