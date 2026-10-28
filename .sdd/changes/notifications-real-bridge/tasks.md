# Tasks — notifications-real-bridge

## T1. Schema + emitter (≤1.5h)
- [ ] The `notifications` migration (D1: the indexes + the nullable FKs
      with nullOnDelete).
- [ ] `NotificationEmitter` (+ `notifyPls`/`notifyCohorts` — the mail
      law's recipient resolution).
- [ ] The four Action call sites (after-commit, beside the mails).
- [ ] `php -l` + the tests' first cut.

## T2. The islands (≤2h)
- [ ] `NavNotifications` (two mounts): the badge + the panel rows; the
      frozen classes byte-preserved; `wire:poll.10s ->whenVisible()`.
- [ ] The lifecycle resolution (D4: the disabled row + the tooltip).
- [ ] `partials/notif-row.blade.php` (the glyph + the rel/abs time).
- [ ] The nav partial + the panel partial integration.

## T3. The read path + the reminder (≤1h)
- [ ] `POST /notifications/read` + `/read-all` (auth, idempotent).
- [ ] The durable fetch swap in the capture-phase listener; the
      localStorage block retirement (the JS copies out).
- [ ] `notifications:remind-pending` + the daily schedule.

## T4. Tests + receipts (≤1.5h)
- [ ] The dept-scoped recipients test (notifyDeptPls).
- [ ] The batch-resolve law receipt (one whereIn per render).
- [ ] The open-panel-poll-survival Playwright receipt (AD-15).
- [ ] The feature tests (the emitter's five events; the island; the
      lifecycle; the reminder dedupe/re-arm).
- [ ] Playwright: the badge within a poll cycle; the visual diff = zero;
      the disabled row's tooltip; the cross-page AD-2 tabs.

## T5. Gates + bookkeeping (≤0.5h)
- [ ] lint:check + phpstan 0 + the full suite (≥266/266 + the new tests).
- [ ] The stale-serve ritual (2 separate commands, :8000) + the :8001
      sibling; the changelog + the nav pages' changelogs; ONE commit
      series (no push).

## T6. The demo seed rows (≤0.25h, user-run)
- [ ] Draft the INSERTs (a submitted row for a PL, an approved row for
      5425, a reminder) BUT hand them to the USER to run
      (docs/demo-db-data-changes.md) — never agent-run on the demo DB.
