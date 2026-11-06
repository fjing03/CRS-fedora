# Proposal: notification row — hide an inactive row, Title Case titles, a sender line, and a zero-unread header

## Intent
Four small user-facing gaps in the notifications panel, reported together:

1. An **inactive** row (read-time `undone` / `superseded` / decided-away notice) has no way to be put
   away from the feed — the only Hide affordance lives in manage mode. The shelf's `Unhide` already
   proves a per-row action fits in `.nr-right`; the same shape is wanted for Hide.
2. Titles arrive as stored sentences (`Scheduled class cancelled`) — the panel must read Title Case.
3. The row never says **who did it**. The subtitle should read `by Surayaini Binti Basri (5425)`, or
   `by YOU` when the recipient is the actor; and for the two self-owned kinds the title says **Your**.
4. With zero unread the header hides both the `0` pill and the Mark-all icon, so the header's anatomy
   changes shape on a caught-up mailbox. Both should stay, with Mark-all disabled.

## Scope
### In Scope
- `resources/views/partials/notif-row.blade.php` — the Hide action on inactive feed rows, the Title
  Case title, the sender subtitle, the `Your …` titles for `cancelled` + `submitted`.
- `app/Livewire/NavNotifications.php` — two computed row attributes (`sender_self`, `sender_line`)
  derived from the SAME batched request/session maps the R5 staff line already uses (no new query).
- `app/Support/TitleCase.php` (new) — one Title Case helper + a unit test.
- `resources/views/livewire/nav-notifications.blade.php` — pill always rendered (shows `0`),
  Mark-all always rendered and `disabled` at zero.
- `public/css/theme.css` — `.notif-hide` shares `.notif-unhide`'s rule; `.notif-iconbtn:disabled`.

### Out of Scope
- The hide WRITE path (`hideOne` / `gateableInboxRows` / `isHideableRow`) — unchanged; an inactive
  row is already hideable by `isHideableRow` (`read_at !== null || inactive_reason !== null`).
- The bell badge: it keeps hiding at zero (a `0` badge on the button is not in the brief).
- The hidden shelf, manage mode, filters, the poll, the read path, the tooltips' fact lines.
