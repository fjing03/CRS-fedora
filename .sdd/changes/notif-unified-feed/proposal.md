# Proposal — notif-unified-feed (combined small-lane batch)

## The user's new ruling (2026-10-29)

All staff pages show **one consistent notification feed** for the logged-in
recipient — the same notification list, unread count, and read states on
every page they can open (PL: the 6 nav pages; non-PL: the first 5). The
per-page category tabs are retired by decision: the frozen `AD-2 role-tab`
law of `notifications-real-bridge` D2 (and the just-landed
`notif-ad2-pagekey-tab`) is SUPERSEDED — the panel no longer filters by the
page's role category.

## Root cause on record (the investigation receipt)

The per-page feed difference came from the INTENTIONAL `role_view` filter in
`NavNotifications::render()` (D2's AD-2 law; the mapping itself was correct
since 52b8399). One-feed is a contract change the user now makes; the frozen
consultation receipts (§2 of the previous report) show the DB per-recipient
state already consistent (badge = whole-mailbox unread on every page).

## What this lane changes (the smallest change achieving the shared feed)

1. `app/Livewire/NavNotifications.php`:
   - `render()`: drop the `role_view` filter — `$rows = $batch` (ALL the
     user's rows, `orderByDesc created_at` — the SAME order everywhere);
     `$unreadInView = $unreadTotal` (the full-mailbox unread; the unread-only
     toggle keeps filtering the whole feed);
   - remove `$roleView` + `resolveRoleView()`; `$pageKey` stays ACCEPTED at
     mount (the mounts keep passing it; smallest diff) but no longer drives
     anything;
   - the file's doc comments updated (AD-2 → one-feed law).
2. `resources/views/livewire/nav-notifications.blade.php`: the caught-up copy
   — the "No unread in this category — N unread in other tabs" branch is
   DEAD under one feed (empty + total>0 is impossible); simplified to the
   single frozen "You're all caught up" line.
   `$showEmpty` unchanged (it still gates list-vs-empty correctly).
3. Comment updates (no behaviour): `partials/ui-nav-bar.blade.php:127` (the
   D2/AD-2 comment becomes the one-feed law), `public/js/ui-common.js:1873`.
4. Receipt updates (the AD-2 law's receipts are REPLACED):
   - `NotificationsBridgeTest::test_the_page_keys_map_the_right_tab_rows`
     → one unified-feed test (all three role rows visible everywhere
     regardless of pageKey);
   - `tests/notifications-island.spec.ts`: the AD-2 leg → the shared-feed
     receipt (the SAME ids/order on two different pages; unread-only state
     consistent); the caught-up receipt leg updated to the new copy;
   - :8030 UI-chain matrix (per the approved plan): feed equality, mark-read
     sync, poll appearance, inactive rows, recipient isolation.

## Untouched (explicit)

- The emitter, the durable POST, mark-all, the F-2 badge law, `PENDING_NAV_COUNT`,
  the D4 inactive law, links/timestamps/tiles, recipient scoping, RBAC.
- The demo receipt id 2 deletion = a separate DB step (the user runs the
  approved SQL or explicitly permits an agent-run — NOT part of this code lane).

## Risks

- The page's unread rows RELOCATE across pages (rows formerly hidden on a page
  now show there): the intended change; the unread-only toggle + scroll laws
  keep density manageable.
- The three retired-AD-2 comments/tests must all move together (the receipts
  are the pins for the new law).

## Success criteria

- As 5425: the same ids/order/count on all 6 PL pages; as 5770: the same on
  their 5 pages; poll-driven new rows appear everywhere; mark-read state
  syncs; inactive rows render disabled everywhere; the caught-up copy shows
  the frozen single line; suite + lint + phpstan + node gates green; ONE
  commit, no push; the demo receipt deletion executed only per the user's
  explicit instruction.
