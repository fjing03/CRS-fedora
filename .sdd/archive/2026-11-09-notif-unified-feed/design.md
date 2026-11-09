# Design — notif-unified-feed

## D1 `NavNotifications.php` (the behaviour change)

```php
public function render(): View
{
    $userId = (int) Auth::id();
    $rows = collect();
    $unreadTotal = 0;
    $unreadInView = 0;

    if ($userId > 0) {
        $base = Notification::query()->where('user_id', $userId);

        $unreadTotal = (clone $base)->whereNull('read_at')->count();

        $unreadInView = $unreadTotal;   // ONE feed: the viewed slice IS the mailbox

        $rows = $this->resolveRequests(
            (clone $base)->orderByDesc('created_at')->get()
        );

        $rows = $this->unreadOnly
            ? $rows->filter(fn (Notification $n) => $n->read_at === null)
            : $rows->values();
    }

    return view('livewire.nav-notifications', [
        'rows' => $rows,
        'unreadTotal' => $unreadTotal,
        'unreadInView' => $unreadInView,
    ]);
}
```

- `resolveRoleView()`, `$roleView` REMOVED (no consumer); `$pageKey` stays an
  accepted mount property (the mounts keep passing it; unused → the "one
  feed" law; the AD-2-era doc comments updated to the new law).
- `resolveRequests()` (the D4 batch-resolve + ONE-whereIn law) UNTOUCHED.

## D2 The caught-up copy (the only blade change)

```blade
<span>You're all caught up</span>
```

— the `$userTotal ? 'No unread in this category — ...' : ...` ternary is
replaced (that wording owed its existence to the vanished per-page tabs;
empty-feed can only happen with total = 0). `$showEmpty` logic untouched:
it keeps (a) unread-only with zero unread → empty, (b) a truly empty mailbox
→ empty. `#notifList`/`#notifEmpty` ids/classes untouched (frozen).

## D3 Comment updates (behaviour-free)

- `partials/ui-nav-bar.blade.php:127` — the comment becomes: the panel shows
  the ONE recipient-wide feed on every page; the camel `$pageKey` no longer
  drives it (kept passing for mount compatibility).
- `public/js/ui-common.js:1873` — the AD-2 notes replaced by the one-feed law
  (F-2 unchanged: the badge/pill count the whole mailbox).
- The island blade's header comment gains the one-feed sentence.

## D4 The receipts (replacements for the AD-2-era ones)

- `NotificationsBridgeTest`:
  `test_the_page_keys_map_the_right_tab_rows` →
  `test_the_panel_shows_one_unified_feed_for_the_recipient`: one user with
  pl+lecturer+student rows; mount with `pageKey 'requestApproval'`, `''`,
  `studentMyTimetable` — the SAME full row set renders on mount regardless
  of the page key; the unread-only toggle still filters across ALL rows.
  The other bridge receipts (badge F-2, lifecycle, reminder) unchanged.
- `tests/notifications-island.spec.ts`: the AD-2 leg becomes "the panel shows
  the same feed on two different pages" (relative receipts: same list
  contents/order on `/request-approval-ui` vs `/my-timetable-ui` as 5425;
  caught-up copy = the single frozen line when empty); the caught-up
  "names the total" leg retires (the copy no longer carries that phrase).
- :8030 (testing DB) matrix per the approved plan — seeded pl/lecturer/
  student rows for 5425, isolation rows for 5770/5516: feed equality +
  ordering across the 6 PL / 5 non-PL pages, badge = DB unread, mark-read
  sync via the durable POST, poll appearance, inactive rows, recipient
  isolation, no dupes/console errors/5xx.

## D5 Relationship to the superseded lanes

- `notifications-real-bridge` design D2's AD-2 law = SUPERSEDED by the user's
  2026-10-29 ruling (recorded in this proposal). No frozen-artifact edit —
  this lane states the supersession instead.
- `notif-ad2-pagekey-tab` is absorbed: the mapping is no longer consulted;
  its artifacts stay as history (archive recommendation at the next sweep).
