# Proposal — notifications-real-bridge

## Problem

The notification bell/panel is the last major mock-fed surface: the rows
come from the mock seed (`mock-data.js` §2.13 — 12 invented rows), the
read-state lives in the browser's localStorage, and NOTHING real lands in
the bell when the system's actual events happen (submit/approve/reject/
cancel). The frozen UI is complete; only the data bridge is missing.

## The user's rulings (2026-10-28 — frozen at proposal)

1. **All five events** create real notification rows: request submitted
   (→PL), approved (→the proposer + the affected cohorts' students),
   rejected (→the proposer), scheduled-class cancelled (→the cohorts'
   students), pending-request reminder (→PLs, 24 h, deduped).
2. **Real-time = option A (Livewire `wire:poll`)** — the bell/badge/panel
   update themselves without a page refresh; NO websockets/Reverb in this
   lane (the emitter design keeps that upgrade additive).
3. **Lifecycle**: rows never deleted; an inactive link target renders the
   row DISABLED with the hover tip
   `This notification is inactive because the request was cancelled.`
4. **Email**: OUT of this lane (the user answers separately; the SMTP
   flip is a demo-pack item — `docs/viva-demo-runbook.md`).
5. **mock-data.js the file stays**; the panel's feed swaps to the real
   table (the §2.13 rows retire from the panel path only).
6. The bug round (`docs/replacement-arrangement-pending-bugs.md`) stays
   PENDING — untouched by this lane.

## What this change builds

1. **Table** `notifications` (fan-out rows, one per recipient):
   user_id, role_view (pl/lecturer/student), type (the FROZEN tile keys —
   see the mapping law below), title, body, link, request_id nullable,
   class_session_id nullable, week nullable, read_at nullable,
   timestamps; indexed (user_id, read_at).
2. **The emitter** (`App\Support\Notifications\NotificationEmitter`):
   `send()` + `notifyPls()` + `notifyCohorts($session)` — called from the
   real Actions AFTER the transaction commit (the mails' exact law):
   submit → PLs; approve → the proposer + the cohorts' students; reject →
   the proposer; class-cancel → the cohorts' students.
3. **The islands** `App\Livewire\NavNotifications` — ONE component
   class, TWO mount points (the bell+badge in the nav bar; the panel rows
   in the panel partial), both `wire:poll.10s ->whenVisible()`: server-
   rendered with the SAME frozen classes; the mark-read mechanism keeps
   the D4 law EXACTLY — the rows stay NATIVE anchors and the existing
   capture-phase click listener pattern survives, swapping the
   localStorage write for a **fire-and-forget durable write**
   (`fetch(..., {keepalive: true})` to a small auth'd POST endpoint)
   BEFORE the native navigation; mark-all = the same endpoint; the
   AD-2 role tabs, the AD-15 scroll law and the caught-up swap preserved
   (the toggle state lives server-side in the island); the lifecycle
   rows render disabled (no href, not click-markable — mark-all is the
   path to zero) + the user's tooltip.
4. **The reminder command** `notifications:remind-pending` (one row per
   PL per pending request older than 24 h, deduped until read) + a daily
   schedule; on-demand for the demo.
5. **The mock retirement (panel path only)**: `mock-data.js` the FILE
   STAYS; `ui-common.js`'s mock-phase notif functions
   (refreshNotifBadge/renderNotifList/getNotifReads/markNotifRead/…)
   retire; `initNotifPanel` keeps the open/close/scroll wiring for the
   island's markup.

## The type mapping law (the frozen tile canon — round-1 🔴 resolved)

The `type` column stores the FROZEN tile keys (theme.css :446–450 defines
exactly these tile classes; NOTIF_GLYPHS carries their glyphs):

| Event | `type` (frozen key) | `.notif-tile-*` | Recipient role_view |
|---|---|---|---|
| Request submitted | `submitted` | submitted | pl |
| Pending reminder (24 h) | `awaiting` | awaiting | pl |
| Request approved | `approved` | approved | lecturer (the proposer) + student (the cohorts) |
| Request rejected | `rejected` | rejected | lecturer (the proposer) |
| Scheduled class cancelled | `update` | update | student (the cohorts) |

Same-name-same-color canon: NO new tile classes, NO new glyphs. A PL's
own outcome rows surface under the **lecturer** tab (the AD-2 default on
most pages) — intended, never "fixed".

## The round-1 🟡 carries (declarative)

- `PENDING_NAV_COUNT` (the request-approval nav-item badge) is
  DELIBERATELY untouched — a different truth (the pending-queue count)
  from the bell's unread total; never "unified".
- The island renders a graceful no-op stub on the mock-fallback legacy
  pages (no full-page Livewire context).
- The `initNotifPanel` row-click capture listener is RETIRED with the
  localStorage store (the durable POST replaces it); the open/close/
  scroll wiring survives — the double-fire risk is bounded by the
  retirement.
- The reminder dedupe: per (user_id, request_id, type='awaiting') an
  UNREAD row existing → skip; a READ reminder re-arms on the next daily
  run (the intended re-remind-after-read semantics); one row per
  request per PL (the mock's aggregated "2 requests awaiting…" copy
  dies with the mock).

## Out of scope

- SMTP/the mail transport (the demo-pack item); websockets/Reverb.
- The email digests, cross-lecturer alerts (the CodingMAIN law: none
  unless requested later).
- The pending-bugs round; the email Q&A the user owes (no .env change).

## Risks

- The island polls on every page: one indexed query per 10 s per open
  tab — receipted cheap; `->whenVisible()` so background tabs idle.
- The frozen JS retirement touches the nav shell: the open/close wiring
  must keep working on EVERY page (the nav-identity Playwright gate
  receipt).
- The row markup must match the frozen classes byte-for-byte (the
  glyph/abs-time move to Blade; the visual diff = zero).

## Success criteria

- Submit → the PL's badge +1 within a poll cycle, no refresh; approve →
  the proposer + the cohort students' badges; reject → the proposer;
  class-cancel → the students; the reminder command → the PLs (deduped).
- Read/unread across reloads (DB-backed); mark-all; the unread-only
  toggle; the caught-up swap.
- The lifecycle: cancel a pending request → its PL row renders disabled
  + the user's tooltip; the row stays.
- The mock rows never render. Suite grows (emitter/island/reminder
  tests); gates green; ONE commit series per the task list; not pushed.
