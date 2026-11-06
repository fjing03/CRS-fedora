# Spec — notification row + panel header

## R1 Readable rows can be hidden from the feed
**Amended 2026-11-05 (the user's follow-up): the action belongs on every READ notification, not only
the inactive ones** — the surface is exactly the hide gate (`isHideableRow`: `read_at !== null` OR an
inactive reason).
A hideable row in the normal feed MUST render a `Hide` button inside `.nr-right`, below its
timestamp, styled like the shelf's `Unhide`. An ACTIVE row is an `<a>`, so its action MUST cancel the
anchor's default action (`.stop.prevent`) — hiding must never navigate. Pressing it MUST hide that row for
the viewer (existing `hideOne` gate) and the row MUST leave the feed. Unhide MUST keep working.
Rows in manage mode / the hidden shelf / active rows keep their current layout.

## R2 Titles are Title Case
Every `.notif-row-title` MUST read Title Case: each word capitalised except minor words
(`a, an, and, as, at, but, by, for, from, in, into, of, on, onto, or, the, to, via, vs, with`) when
they are neither first nor last. Tokens containing digits, `#`, `/`, `&` or an existing acronym MUST
be left untouched (`ID`, `#28`, `PL`).

## R3 Sender line
`.notif-row-subtitle` MUST read `by YOU` when the actor is the logged-in recipient, otherwise
`by <Name> (<staff_id>)` (the name alone when no staff id). The actor MUST come from data: the
proposer for `submitted`/`awaiting`, the approver for decisions, the class lecturer for class rows.
No hardcoded names or ids.

## R4 Self-owned titles
When the actor is the recipient: a `cancelled` row MUST read **Your Scheduled Class Cancelled** and a
`submitted` row **Your Replacement Request Submitted**. All other combinations keep the R2 title.

## R5 Zero unread keeps the header's anatomy
At `0` unread the `#notifUnreadPill` MUST be visible and show `0`, and `#notifMarkAll` MUST be visible
and disabled. Neither may be hidden. Above zero both behave as today. The bell badge keeps its current
rule (hidden at zero).

## R6 Unchanged
The hidden shelf, manage mode, the filters, the 10 s poll, the read path, the tooltip fact lines, the
click targets, and the hide write gate.
