# Tasks — notif-actor-pl-dedupe

## T1. The emitter dedupe (≤0.25h)
- [x] The skip in the dept-PL loop; php -l.

## T2. The test + receipt updates (≤0.5h)
- [x] The bridge test's dedupe assertion (D3).
- [x] :8030 UI drive — one row for 5425's own cancel.

## T3. The demo twin cleanup (≤0.2h — the user-approved agent step)
- [x] Verify id 31 read-only → run the ONE-row DELETE → verify: USER-1'S
      MAILBOX 5 → 4 rows (the verified list pre-apply = ids 3,5,7,8,31; only
      id 8 remains for the W6 event) → the doc log entry
      (the proposal's "26 → 25" figure describes the event's full fan-out
      shape with owner-is-PL, not this demo check).

## T4. Gates + bookkeeping (≤0.4h)
- [x] pint + vendor/bin/phpstan analyse --memory-limit=1G + the full suite +
      the :8000 island spec with --grep-invert "TC70" (the hard guard; no UI
      change expected — emitter-only PHP, but run the gates anyway).
- [x] ONE commit (`fix:`), no push; changelog entry; sdd.yaml → applied.
