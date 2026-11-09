# Design — notif-actor-pl-dedupe

## D1 The emitter dedupe (the behaviour change)

In `NotificationEmitter::classCancelled` step 3 (the dept-PL loop), skip the
row when the PL **is the actor**: `if ($pl->id === $actor->id) { continue; }`
— the actor's record row (step 1) already carries the whole fact for them.

Rationale (the surfaces preserved, none lost for the actor):
- the actor's ONE row carries the same title/body/anchor moment;
- the actor's `/cohort-timetable` link (the D1 per-role law) remains — the
  venue view is reachable by navigation, not needed as a second row for the
  same event instant;
- OTHER dept PLs (Rahmat) keep their own separate rows.

## D2 The cleanup (the existing twin)

`DELETE FROM notifications WHERE id = 31 AND user_id = 1 AND title = 'Scheduled class cancelled' AND role_view = 'pl';`
— verified read-only first (id 31 = exactly the pl twin of id 8; both read;
no class_exceptions row remains for W6 — the undo stands). ONE row, agent-run
under the user's explicit instruction in the apply message; logged in
`docs/demo-db-data-changes.md` (2026-10-30 entry).

## D3 Test updates

`NotificationsBridgeTest::test_class_cancelled_fans_out_to_owner_students_and_dept_pls`:
- the seeder identity: owner 1 IS a DCIT PL (so the dedupe path executes in
  the receipted run);
- assert: NO `pl`-role row exists for user 1 from THIS event (the dedupe);
  the OTHER dept PL (user 2) still receives their pl row; the actor's
  lecturer row still exists and links `/cohort-timetable`;
- the total rows for the event: 1 + (students) + (other PLs).

## D4 Receipts

- :8030 (testing DB): as 5425, cancel one own class through the REAL UI
  chain → exactly ONE 'Scheduled class cancelled' row for 5425 (the lecturer
  record) + students' rows + the other PL's row (no pl row for 5425).
- :8000 (demo): the read-only check — the twin id 31 no longer listed after
  the cleanup; only id 8 remains for the W6 event.
- Gates: pint + phpstan (the emitter is PHP) + the full suite + node check.
