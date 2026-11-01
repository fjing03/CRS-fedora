# Design — notif-ad2-pagekey-tab

## D1 The full page-key inventory (the receipt that decides the change)

Every mount surface of the panel partial, with the values the layout
receives. `$activeNav` = the nav-highlight key (kebab); `$pageKey` =
the layout's per-page camel key (also written to `<body data-page>`):

| Route / surface | Component/template | `$activeNav` | `$pageKey` | AD-2 tab (law) |
|---|---|---|---|---|
| /my-timetable-ui | `MyTimetable.php:62` | my-timetable | myTimetable | lecturer |
| /cohort-timetable | `CohortTimetable.php:94` | cohort-timetables | cohortTimetable | lecturer |
| /venue-timetable-ui | `VenueTimetable.php:602` | venue-timetable | venueTimetable | lecturer |
| /replacement-home-ui | `ReplacementHome.php:136` | replacement-arrangement | replacementHome | lecturer |
| /replacement-arrangement | `ReplacementArrangement.php:81-83` | replacement-arrangement | replacementArrangement | lecturer |
| /my-request-history-ui | `MyRequestHistory.php:56` | replacement-history | myRequestHistory | lecturer |
| /request-approval-ui | `RequestApproval.php:47` | request-approval | requestApproval | **pl** |
| /student-my-timetable-ui | `StudentMyTimetable.php:73-74` | my-timetable | studentMyTimetable | **student** |
| /replacement-history-ui | `ReplacementHistory.php:65-66` | replacement-history | replacementHistory | **student** |
| legacy mock-fallback templates | all 9 design templates | same kebab | SAME camel keys (grep `pageKey` in ui-design-templates/) | same |
| /dashboard | plain view, no layout nav | n/a | n/a (no island) | n/a |

> Round-1 🟡 footnote: `/replacement-arrangement` (`ReplacementArrangement.php:82`,
> `hideNav => true`) NEVER MOUNTS the panel/bell — the layout gates the nav
> (and thus both islands) behind `@if(!isset($hideNav) || !$hideNav)`. Its
> D1 row is listed for `resolveRoleView` completeness only — do not count it
> as "behavior that changes" or a receiptable visible surface.

Both collision pairs are visible in the table: `my-timetable` and
`replacement-history` each serve a student AND a staff page — the kebab
key alone can never resolve the AD-2 split. `$pageKey` resolves it
EXACTLY like the retired mock law did.

## D2 The changed line + its comment

```blade
{{-- partials/ui-nav-bar.blade.php — the AD-2 tab key is the LAYOUT's
     $pageKey (camel: studentMyTimetable / requestApproval / replacementHistory
     / …; the law the retired NOTIF_ROLE_BY_PAGE read from body[data-page]).
     Kebab $activeNav is the NAV-HIGHLIGHT key: it collides across roles
     (my-timetable + replacement-history serve a student AND a staff page)
     and must never feed the panel. The $activeNav fallback keeps today's
     lecturer default for any surface that published only activeNav --}}
@include('partials.ui-notifications-panel', ['pageKey' => $pageKey ?? $activeNav ?? ''])
```

- The include line: `ui-nav-bar.blade.php:127` (round-1 anchor fix).

- NO change in `partial/ui-notifications-panel.blade.php` (it already
  forwards `pageKey`), NO change in `NavNotifications.php`
  (`resolveRoleView()`'s camel match is the correct law), NO visual
  change anywhere else.

## D3 The mapping test (the only new test)

Feature test (sibling file `tests/Feature/NotificationsTabMapTest.php` OR
one test added to `NotificationsBridgeTest.php` — chosen: ADDED to
NotificationsBridgeTest to keep the bridge law in ONE file):

- actAs a PL, mount `variant 'panel'` with each camel key and assert the
  rendered panel's `roleView` outcome through behavior:
  - pageKey `requestApproval` → pl rows ARE filtered into the list
    (seed a `pl` row + a `lecturer` row: the panel list shows ONLY the
    pl row),
  - pageKey `studentMyTimetable` / `replacementHistory` → the student row
    shows; the lecturer/pl ones do not,
  - pageKeys `myTimetable` / `cohortTimetable` / `venueTimetable` /
    `myRequestHistory` / `replacementHome` / `replacementArrangement` →
    lecturer rows show; pl/student rows stay behind the swap,
  - empty pageKey → lecturer default (the defensive fallback unchanged).

## D4 Re-verify receipts after apply

1. The :8000 `notifications-island.spec.ts` re-run — its caught-up/AD-2
   receipt now nails the fixed behavior on the LIVE DB (5425's unread pl
   row 3 must render in the panel list on /request-approval-ui).
2. The full PHPUnit suite (276 → 277+) + lint + phpstan 0.
3. `page-changelogs/notifications-panel-changelog.md` entry; close both
   stale in-code "UNDER USER VERDICT" notes (comment-only):
   `ui-common.js` (the ⚠ block) AND the pageKey repair note inside
   `tests/notifications-island.spec.ts:49-52` (round-1 🟡).

## D5 Rejected approach (for the record — the half-alternative)

Mapping `resolveRoleView()` to the kebab `activeNav` keys: structurally
impossible (D1's collisions), and it would GUESS the wrong tab on exactly
the two pages where the distinction matters most to students. The camel
`$pageKey` is the contract every surface already honors — reconnecting it
is the minimal decision-level-correct fix.
