# Proposal — notif-click-highlight (combined small-lane batch)

## The user's design ruling (2026-10-30)

Clicking an **active** (non-inactive) notification row — read or unread —
navigates to its linked page AS IT DOES TODAY, and **visually highlights the
related table row or cell**, located by the notification's associated record
id; the highlight is temporary, themed, and works across page navigation.
Target-not-found = graceful. **INACTIVE rows must NOT trigger anything**
(keep-as-it: the D4 disabled anchor stands).

## The root mapping (the investigation receipt)

The notification rows are native anchors (D4 law) — the capture-phase
listener marks them read then the browser performs the navigation. The
target identities already exist on the receiving payloads:

| Link surface | Notification anchor | Receiving DOM identity |
|---|---|---|
| `/request-approval-ui` | `request_id` | the queue tables' `<tr data-id=r.id>` (`request-approval.blade.php:651`) — the payload rows carry the DB request id (`ReplacementRequestMapper:97`) |
| `/my-request-history-ui` | `request_id` | `<tr data-id=r.id>` (`my-request-history.blade.php:520`) — the payload rows carry the DB id (`MyRequestHistory:79`) |
| `/cohort-timetable` (cancel rows) | `class_session_id` | grid cells' `openClassModal` events carry the session id as the "Class Ref" (`CohortTimetable:211` `'id' => $slot->id` → the event id) |
| `/venue-timetable-ui` (dept-PL rows) | `class_session_id` | the venue payload carries slot group ids — session-aware anchoring is the most complex surface |

## What this lane builds (STAGE 1 = the request-rows tables + the helper)

1. **The click-time handoff** — `ui-common.js`: the capture-phase listener
   (the D4 law's home) appends the anchor params to the NOTIFY LINK at
   click time — `?notif-hl=<kind>:`+`<id>` (kind: `r` request / `s`
   session) — read/unread rows both (their rule). No DB, no emitter change.
2. **The arrival highlighter** — one shared helper `applyNotifHighlight()`
   (ui-common, called on DOMContentLoaded):
   - parses `notif-hl`, locates the target row
     (`table tr[data-id="<id>"]` for kind `r`),
   - highlights it EXACTLY like the "Jump to earliest bookable slot" flash
     (the user's 2026-10-30 refinement): the SAME `bookable-flash` pulse
     (theme.css 1.15 s) via the ONE shared ledger `flashRowPulse(el)`
     (ui-common: remove → reflow restart → add → auto-remove ~1.3 s), and
     scrolls it into view;
   - clean-up: the URL params are stripped from history state
     (`history.replaceState`) so refreshes don't re-flash; no NEW theme
     class at all — the EXISTING flash class is reused.
3. **Not-found handling**: the helper exits silently (no toast spam) — the
   navigation itself remains the primary behaviour unchanged.
4. The inactive rows: NO href/data-id → the listener's existing guard
   (`closest('.notif-row[data-id]')`) already excludes them ✓ (keep-as-it).

## STAGE 2 (ACTIVATED — the user's rulings, 2026-10-30)

1. **Ruling A:** a NARROW exception to ARRIVAL-GRID-NO-PIN for notification
   deep links ONLY — a `notif-hl` URL may move the week selector on arrival.
   All other deep links keep the law.
2. **The cancel-row link law** (the notification emitter): ALL cancel-class
   rows (the owner-actor lecturer row AND the dept-PL copies) link to
   **`/my-timetable-ui`** (the house route the user called "my-timetable");
   the students' rows stay `/student-my-timetable-ui` (their own timetable —
   unchanged). The former `/cohort-timetable` (owner) and
   `/venue-timetable-ui` (dept-PL) links retire.
3. **The expected behaviour**: arriving on /my-timetable-ui with kind-s →
   the arrival handler SWITCHES the week selector to the notification's
   week (`notif-w`), finds the cancelled class cell (the builder stamps
   `data-sid` — the documented one-line bridge patch), flashes it via the
   SAME bookable-flash pulse, and scrolls into view.
4. **Known consequence (flagged to the user)**: a NON-owner dept-PL copy
   (Rahmat) navigates to THEIR OWN timetable, which does not contain the
   cancelled class → the helper's graceful no-highlight skip (the navigation
   itself unchanged). The ruling reads "ALL cancel-class rows"; if the user
   prefers dept-PL copies to keep a venue anchor, that is a one-line
   emitter-link split later (insurance: the kind-s graceful skip already
   handles it honestly).

## Reuse, no duplication

- The existing click listener + durable POST + scroll law: untouched
  (the param-append happens BESIDE durableMarkNotifRead).
- The theme: ZERO css change — the existing bookable-flash pulse reused.
- No emitter/query/RBAC change; the mark-read + read-sync behaviours stay.

## Risks

- `notif-hl` params must not collide with existing URL params on the
  receiving pages (the pages' own keys are distinct; the helper strips
  them from history after read).
- Flash-handler timing: the tables render from payloads in page-scripts —
  the highlighter must run AFTER the rows exist (DOMContentLoaded → the
  pages' own render calls; the helper defers a microtask/setTimeout with a
  couple of retries, receipted).

## Success criteria (Playwright, :8030 + :8000 read-only)

- Click a pl row (unread) on a timetable page → arrives queued at the
  approval page → the queue row w/ data-id=6 gains bookable-flash (visible,
  themed) and the class self-removes after ~6 s.
- The same for the history page (rejected rows) and read rows (their rule:
  read too).
- Inactive rows: no navigation change, no params, no highlight (keep-as-it).
- No console errors; the existing island receipts stay green;
  mark-read/durable-POST sync unchanged.
