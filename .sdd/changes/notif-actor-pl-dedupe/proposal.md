# Proposal — notif-actor-pl-dedupe (combined small-lane batch, the user's approved F1 + the requested cleanup)

## Problem (the user's live observation, 2026-10-30)

The class-cancel fan-out writes one row per ROLE of the same event to the
same user when the class's OWNER is ALSO a department PL — staff 5425's own
cancel produced TWO identical rows in her unified one-feed panel:

| id | role | link | created_at |
|---|---|---|---|
| 8 | lecturer (the actor's record row) | /cohort-timetable | 2026-10-30 03:50:19 |
| 31 | pl (the dept fan-out copy) | /venue-timetable-ui | 2026-10-30 03:50:19 |

Both landed the same second; the event is otherwise identical. In the
pre-unified-feed era they lived in two different page views and were never
side-by-side; the ONE-FEED ruling (notif-unified-feed) surfaces every row of
the recipient's mailbox, so dual-role duplication is now visible in one list.

## The user's decision (2026-10-30)

- **F1 approved + apply now**: the dept-PL fan-out SKIPS the actor (the
  owner already receives their personal record row — the same fact, the same
  moment).
- **The existing twin row is deleted too**: the user asked this lane to
  REMOVE the already-written duplicate (demo-DB `notifications` id 31) —
  explicitly approved as an agent-run DB step for THIS row only.

## The change

1. `app/Support/Notifications/NotificationEmitter.php` (the dept-PL loop):
```diff
             if ($deptId !== null) {
                 $pls = User::query()
                     ->whereHas('lecturer', fn ($q) => $q->where('is_pl', true)->where('dept_id', $deptId))
                     ->get();
                 foreach ($pls as $pl) {
+                    /* notif-actor-pl-dedupe (the user's F1): the OWNER
+                       already receives their actor record row above — the
+                       dept-PL copy would be the same event twice in the
+                       unified one-feed panel. */
+                    if ($pl->id === $actor->id) {
+                        continue;
+                    }
                     $this->write($pl, 'pl', 'update', $title, $body, '/venue-timetable-ui', null, $sessionId, $weekNumber);
                 }
             }
```
2. `tests/Feature/NotificationsBridgeTest.php` — the class-cancelled fan-out
   receipt gains the dedupe assertion: the OWNER-IS-PL fixture produces the
   actor's ONE lecturer row and NO `pl`-role row for the actor (the OTHER
   PL's row unchanged); counts 26 → 25 rows for the 5770/ABCD shape with the
   owner-is-PL seeder identity.
3. **The demo-DB cleanup (the user-approved agent step, ONE row):**
   `DELETE FROM notifications WHERE id = 31 AND user_id = 1 AND title = 'Scheduled class cancelled' AND role_view = 'pl';`
   (verified read-only: exactly the twin of id 8; both READ; nothing else).

## Untouched

- The actor record row, the students' fan-out, the OTHER dept PLs' rows, the
  per-role link law, the after-commit write law, the one-feed panel, RBAC.

## Success criteria

- The emitter: an owner-is-PL cancellation now writes actor(1) + students +
  other-PLs(1) = one row per surface, none duplicated by identity.
- The bridge receipts green with the dedupe pins; the :8030 UI drive shows
  ONE row for 5425's own cancel.
- The demo twin (id 31) gone — 5425's mailbox holds ONE "Scheduled class
  cancelled" row for the W6 event (id 8).
- Gates green; ONE commit, no push; the data-changes log entry for the
  agent-run deletion.
