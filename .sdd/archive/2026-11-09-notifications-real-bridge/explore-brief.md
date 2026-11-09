# Explore brief — notifications-real-bridge (2026-10-28)

## The user's rulings (verbatim intent)
- **Events — ALL FIVE**: request submitted (→PL), approved (→proposer +
  the cohort's students), rejected (→proposer), scheduled-class cancelled
  (→the cohort's students), pending-request reminder (→PL).
- **Real-time: option A — Livewire `wire:poll`** (the badge/panel update
  themselves, no page refresh; NO websocket/Reverb in this lane; the
  emitter design keeps a future Reverb upgrade additive).
- **Lifecycle**: rows are NEVER deleted; a row whose link target is
  gone/cancelled renders DISABLED with the hover tip
  `This notification is inactive because the request was cancelled.`
- **Email**: the user answers next reply — the SMTP flip is OUT of this
  lane (mails are already queued; Mailpit = the demo-pack item parked in
  docs/viva-demo-runbook.md).
- **mock-data.js the FILE STAYS** (the standing law) — but the panel's
  feed swaps to the real table entirely (the §2.13 mock rows retire from
  the panel path).
- **Bugs**: `replacement-arrangement-pending-bugs.md` stays PENDING (the
  user has not reviewed the findings) — no fix lanes.

## The frozen-UI contracts the bridge must preserve (code receipts)
- The panel/bell markup + classes are frozen: `.notif-panel`,
  `.notif-head`, `.notif-unread-pill`, `.notif-row` (+`--read`),
  `.notif-tile-{type}`, the glyph SVGs (`notifGlyph`),
  `.notif-row-time` + the abs-time tooltip, the caught-up swap
  (`#notifEmpty`), the unread-only toggle (`#notifUnreadOnly`),
  mark-all (`#notifMarkAll`). (`partials/ui-notifications-panel.blade.php`,
  `ui-common.js` :1890–2230.)
- The badge law: ONE unread TOTAL = badge + pill + mark-all all follow
  it (`updateNotifHeaderState`, AD-7/AD-8 seeding).
- The row-click law (D4): mark read BEFORE the native navigation, scroll
  preserved (AD-15).
- The role tabs (AD-2): the viewed category derives from the page
  (`NOTIF_ROLE_BY_PAGE`); the mailbox = the user's own rows (the mock's
  `recipientId` guard becomes the real WHERE user_id = auth id).

## The real-time decision (the user's A) — receipts
- Stack: Laravel 13.17 + Livewire 4.1 (composer.json); Livewire 4 has
  `wire:poll` first-party; `BROADCAST_CONNECTION=log` (broadcasting
  unconfigured). Reverb = a future additive lane (the emitter stays
  row-writing; a listener can broadcast later).

## The existing emission points (all post-commit, all mail-queued)
- `SubmitReplacementRequest` — the PL alert (FR 4.15) after commit.
- `ApproveRequest` — the proposer's decided mail + the cohorts'
  approval notice (FR 2.13/1.9) after commit.
- `RejectRequest` — the proposer's decided mail.
- `CancelClass` — the class cancelled (the cohort's students lose the
  class — the notification fan-out mirrors the mail-less gap TODAY: the
  cancel path queues NO mail — the notification rows fill the real gap).
- `CancelPendingRequest` — the proposer withdraws; the PL's 'submitted'
  row becomes INACTIVE (the lifecycle law).

## Design decisions (proposed — the reviewer's yard)
- **Table** `notifications`: user_id (recipient), role_view
  (pl/lecturer/student), type enum, title, body, link, request_id
  nullable, class_session_id nullable, week nullable, read_at nullable,
  timestamps. Fan-out rows (one per recipient) with read_at — the
  user's "real table" + the simplest unread queries.
- **Emitter** `App\Support\Notifications\NotificationEmitter` (or a
  service): `send(...)` + `notifyPls` + `notifyCohorts(session)` — the
  Actions call it AFTER the transaction commit (the mails' exact law).
- **The island** `App\Livewire\NavNotifications`: `wire:poll.10s` +
  renders the badge count + the panel rows server-side (the SAME frozen
  classes; the row glyph/abs-time move to Blade partials/components);
  `markRead`/`markAll` = Livewire actions (read_at writes); the row
  click = mark-then-navigate via the Livewire js() helper (the D4 law).
  Lifecycle at render: request_id rows resolve live — a cancelled/
  missing request renders disabled (no href) + the user's tooltip text.
- **Reminder** `notifications:remind-pending` (one row per PL per
  pending request older than 24 h, deduped per request per PL until
  read) + `->daily()` scheduled; on-demand for the demo.
- **The localStorage read-store dies** (DB read_at supersedes);
  ui-common's mock-phase notif functions retire from the panel path;
  the mock FILE stays.

## Open questions carried into the proposal
- The island's mount cost: a poll every 10 s per open page — lightweight
  (one indexed query) but receipted.
- The AD-8 legacy seed logic dies with the localStorage store.
- The 'cancelled' notification's link target: the cohort timetable (the
  students' view) — the same link the approval notice uses.
