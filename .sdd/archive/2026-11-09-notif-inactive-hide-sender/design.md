# Design

## D1 · Sender identity is a computed attribute, never a column
`senderIdentity()` returns `[bool self, ?string line]` from maps that already exist:
- request rows → `submitted`/`awaiting` = the **proposer**; decisions (`approved`, `rejected`,
  `updated`, `replacement_confirmed`) = the **approver**, falling back to the proposer;
- class rows (`request_id === null` + `class_session_id`) → the session's **lecturer**.
`self` = the actor IS the recipient. Label = `Name (staff_id)` via the existing `staffLabel()`.
Attached in `attachStaffLines()` beside `staff_line` — zero extra queries (the island's one-query law).

## D2 · Title
- `sender_self` + `cancelled` → **Your Scheduled Class Cancelled**
- `sender_self` + `submitted` → **Your Replacement Request Submitted**
- everything else → `TitleCase::of($composedTitle)` (so a PL's queue row keeps
  `New Replacement Request (ID #28)`, and `Scheduled class cancelled` → `Scheduled Class Cancelled`)

`TitleCase::of()` capitalises every word except the minor words (`and`, `by`, `of`, `the`, `to`, …)
when they are neither first nor last, and never touches tokens that carry digits, `#`, `/`, `&` or an
existing acronym — so `ID`, `#28`, `PL`, `AMSE2003` survive untouched. Unit-tested.

## D3 · Subtitle
ONE `.notif-row-subtitle`: `by YOU` when the actor is the viewer, `by Name (staff_id)` otherwise. When
no actor can be resolved (legacy row with neither request nor session) the two old spans render as
before, so nothing loses information. This also removes the duplicate pair of spans that appeared when
the composed subtitle and the R5 staff line agreed.

## D4 · Hide on an inactive row
`$variant === null && $activation` → the time moves into `.nr-right` and a `.notif-hide` button sits
under it (`wire:click="hideOne(id)"`), exactly the shelf's `.nr-right` shape. The row stays a
non-anchor `<span>`, so a nested button is valid. The write gate is untouched: `isHideableRow()` admits
an inactive row whether or not it was read.

## D5 · Zero unread
`$userTotal` still drives the BELL badge (hidden at zero, unchanged). The panel's pill and Mark-all stop
using it: the pill always renders `{{ $unreadTotal }}` (so `0`), and Mark-all renders with
`@disabled($unreadTotal === 0)`; `.notif-iconbtn:disabled` dims it and keeps it clickable enough for
its tooltip (no `pointer-events: none`).

## Verification
- `tests/Unit/TitleCaseTest.php`
- `tests/notif-row-inactive-hide.spec.ts` (guarded write spec on `:8030`): the Hide button exists on an
  inactive row, clicking it removes the row from the feed and raises the `Hidden (n)` count, the shelf
  still Unhides; titles are Title Case; the subtitle reads `by …`; at zero unread the pill reads `0`
  and Mark-all is visible-but-disabled.
- Updated: the two island specs that asserted the old zero-unread hiding.
