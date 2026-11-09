# Tasks
- [x] T1 `app/Support/TitleCase.php` + `tests/Unit/TitleCaseTest.php`
- [x] T2 `NavNotifications`: `senderIdentity()` + `sender_self` / `sender_line` attributes
- [x] T3 `notif-row.blade.php`: Title Case + the two `Your …` titles; sender subtitle; Hide on inactive
- [x] T4 `nav-notifications.blade.php`: pill always visible (0 included), Mark-all visible + disabled
- [x] T5 `theme.css`: `.notif-hide` shares `.notif-unhide`; `.notif-iconbtn:disabled`
- [x] T6 specs: new guarded `notif-row-inactive-hide.spec.ts`; update the two island specs
- [x] T7 gates: pint · PHPStan · full PHPUnit · `node --check` · `view:cache`
- [x] T8 `page-changelogs/notifications-panel-changelog.md` entry

## Follow-up 2026-11-05 (same session, before the lane was archived)

The user asked for the action on **every read notification**, not only inactive ones. The partial's
condition became `$activation || $n->read_at !== null` (exactly `isHideableRow`), and the action on an
ACTIVE `<a>` row carries `wire:click.stop.prevent` so a hide never navigates. Unread rows still have
no action (there is nothing to put away yet), and the guarded spec gained a read-active fixture that
proves both: the row disappears, the URL is unchanged, and the shelf restores it.
