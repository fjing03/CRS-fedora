# Tasks — `notif-click-behaviour`

Status: **applied 2026-11-04** (receipts in `review-log.md`).

- [x] **T1** `NavNotifications`: stamp the live `notif_status` per row (`effectiveStatus`, derived Completed).
- [x] **T2** `NavNotifications::classOnlyNotifStatus()` + the request-less early exit (a cancellation-only inbox).
- [x] **T3** `NavNotifications::hideOne(int $id)` through `gateableInboxRows()` (the manage-mode gate).
- [x] **T4** `notif-row.blade.php`: `$href` re-point for lecturer class news + the `o<session>` anchor + `data-notif-status`.
- [x] **T5** `NotificationEmitter`: the two lecturer links → `/my-replacements` (student rows untouched).
- [x] **T6** `ui-common.js`: the click branches on `data-notif-status`; `notif-w` also rides `o` anchors.
- [x] **T7** `ui-common.js`: `hideNotifRowInPlace()` via component containment (no escaped selector).
- [x] **T8** `replacement-home.blade.php`: `jumpToMyOccurrence()` + the `o` arm in the synchronous arrival capture.
- [x] **T9** `ui-common.js` `tryFind()`: re-query the selector at flash time (the skeleton rebuild detached the row).
- [x] **T10** `tests/Feature/NotificationsBridgeTest.php`: the two link assertions re-pointed to `/my-replacements`.
- [x] **T11** Gates: pint passed · PHPStan level 7 → 0 errors · `node --check` ok.
- [x] **T12** Full `php artisan test` — **PASSED 2026-11-04**: 340 tests / 340 passed / 1875 assertions in
      450.8 s (baseline before this lane: 336 / 1849). Testing DB reseeded afterwards.
- [x] **T13** End-to-end **completed → hide** receipt — **DONE 2026-11-04** (option A, user-approved): the row was
      hidden through the feature itself and restored through the Hidden shelf. See review-log "Completed receipt".
- [x] **T14** Status-resolution receipt (read-only tinker): `effectiveStatus()` on the demo's approved request
      → its own date `approved`, a past date **`completed`**, a future date `approved`.
