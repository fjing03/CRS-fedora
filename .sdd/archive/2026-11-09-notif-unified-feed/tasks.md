# Tasks — notif-unified-feed

## T1. The component + blade (≤0.5h)
- [x] `render()` one-feed rewrite (D1) + remove `$roleView`/`resolveRoleView`
      (keep `$pageKey` accepted) + doc comments.
- [x] The blade caught-up copy simplification (D2).
- [x] php -l + node check (the blade compiles at runtime).

## T2. The test updates (≤0.5h)
- [x] `NotificationsBridgeTest`: the mapping test → the unified-feed test (D4).
- [x] `notifications-island.spec.ts`: the AD-2 leg → the same-feed-on-two-pages
      receipt; retire the "names the total" leg (D4).

## T3. The :8030 matrix (≤1.5h)
- [x] Seed: 5425 = pl/lecturer/student rows (incl. an inactive-row request);
      5770 + 5516 isolation rows.
- [x] The 6 PL pages as 5425: identical feed (ids+order), badge equality,
      mark-read sync, poll appearance, inactive rows, no dupes/errors/5xx.
- [x] The 5 non-PL pages as 5770: identical (empty→their own) feed, isolation,
      the approval page access-gated away.

## T4. Gates + bookkeeping (≤0.5h)
- [x] lint:check + phpstan 0 + the full suite; node check; the stale-serve
      ritual; `notifications-island.spec.ts` on :8000.
- [x] Changelog (the notifications panel + the shared nav comment).
- [x] ONE commit (`feat:` one-feed), no push. sdd.yaml → applied on green.
## T5. The demo receipt id 2 (NOT in this commit)
- [x] Held: deletion is a DB step performed ONLY on the user's explicit
      word (their SQL run, or agent-run with their explicit instruction),
      then the changelog/bookkeeping entry.
