# Proposal — notif-new-request-ux

**Date:** 2026-10-31
**Status:** PROPOSED (batch 1 — awaiting reviewer gate)
**Ask:** `prompts/pi-prompt-UIUX Update New Replacement Request.md`
**Baseline:** `explore-brief.md` (rulings D1–D14) + `explore-notes.md` (611 lines of
file:line facts)

---

## Why

The "New replacement request" notification (the Programme Leader's signal that a
replacement request exists) is today a single line — a static title and a body that
mixes module info with the requester's name — with a one-line tooltip. The user's
6-part ask turns it into a decision-ready record: WHICH request (ID), WHO asked
(name + staff ID), WHAT is proposed (schedule + venue at a glance), the FULL facts on
hover (multiline tooltip), and an honest lifecycle: once the PL has seen it, or the
request is approved/rejected/cancelled, the row stops posing as actionable (inactive
styling) and stops inflating the unread badge.

## What (the 6 parts, as requirements)

1. **Title** — `New replacement request (ID #76)` with the REAL request ID.
2. **Requester subtitle** — NEW line under the title: `Poong Foo Jing (5425)`
   (full name + staff ID).
3. **Description** — the requester's name LEAVES the description; it becomes
   `Week 7 · Friday, 09:00–11:00 · B002` (week, day, time range, venue code).
4. **Multiline hover tooltip** — for active request-linked rows, the 5-line block
   (Staff / Requested at + relative / Proposed Schedule with date / Proposed Venue
   code — name / Subject code (type) — name), reusing the existing `data-tip` engine's
   multiline arm; values dynamic, never hardcoded.
5. **Inactive state** — a `submitted` notification goes `.notif-row--inactive` when
   READ, APPROVED, or REJECTED (plus CANCELLED, already the case).
6. **Cancelled tooltip** — the exact sentence from decision D1; **inactive ⇒ READ
   category** (D5): never in the unread filter, never in the badge/pill.

## How (summary — the design batch pins mechanics)

Uniform RENDER-TIME composition (D6): the existing `resolveRequests` batch in
`NavNotifications.php` is extended with eager loads (proposer/lecturer/module/venue/
semester — extra queries hit OTHER tables, keeping the one-`replacement_requests`-query
law intact) and the broadened inactive rule; `notif-row.blade.php` composes title/
subtitle/desc/aria/tooltip server-side (survives the 10 s poll morph). The emitter and
stored columns are UNTOUCHED — legacy rows get the new look for free, no DB writes, no
backfill (D7). Unread maths: inactive rows are excluded from the unread total/filter by
classification, `read_at` untouched (D5). Every part FAILS OPEN to the current
`title — body` rendering when a fact is missing (honest fallback, legacy-safe).

## Scope

**In:** `app/Livewire/NavNotifications.php`, `resources/views/partials/notif-row.blade.php`,
`resources/views/livewire/nav-notifications.blade.php` (closure/formatter additions),
`public/css/theme.css` (one `.notif-row-subtitle` class in the notif block), the test
updates in the impact map, `page-changelogs/notifications-panel-changelog.md`.

**Out:** the emitter + mail stack, recipients (D14), `awaiting` reminder semantics (D2),
the row's time chip format, the tooltip ENGINE (no mechanism change — D10), student
surfaces, schema changes, legacy backfills, and every other lane's dirty files
(`my-request-history.blade.php` etc. are read-only context here).

## Hidden decisions (confirm or reword — the explore-brief carries the full D1–D14 table)

- **D1** the exact cancelled tooltip string (requirements-line variant wins).
- **D15** the inactive copy FAMILY for non-cancelled reasons (reviewer round-1 blocker):
  reason-specific sentences in the same pattern, stronger-fact-wins precedence
  cancelled > approved > rejected; the still-undecided read row shows
  `This notification is inactive because you have already read this request.` — the
  classifier must classify the REASON, not a boolean.
- **D3 + D4** — the two frozen laws this change narrows/overturns: (a) notif-click-
  highlight's "read or unread, both clickable" survives ONLY for non-submitted types
  (a read `submitted` row settles inactive after its first click-navigation);
  (b) the real-bridge D4 "decided links stay alive" is overturned for `submitted`
  rows (approved/rejected render inactive; the PL navigates manually via the
  Request Approval filters).
- **D5** inactive ⇒ READ by classification (no `read_at` stamping; the unread total is
  BATCH-DERIVED in PHP — a subquery would be a second `replacement_requests` query and
  fail the one-query law) — the badge number drops by the inactive-but-unread rows
  (honest: they are no longer actionable). This also NARROWS the real-bridge F-2
  "whole-mailbox unread total" law wording: the badge becomes the ACTIONABLE unread,
  and the F-2 test name / bridge-test assertion message get their comments reworded.
- **D6/D7** render-time composition, no backfill: legacy stored bodies keep the old
  text as history but are no longer DISPLAYED for `submitted` rows.
- **D8/D9** tooltip timestamp with year (`29 Oct 2026, 10:44 AM`) from `created_at`;
  verbose relative time (`5 minutes ago`) in the tooltip only.
- **D2** open tension (flagged, not addressed): an `awaiting` reminder row for an
  already-decided request stays active until read.

## Test impact (frozen map — notes §4.7)

UPDATE `NotificationsBridgeTest.php:147` (sentence), `:191-201` (approved → inactive),
`:151-166` (badge law with inactive exclusion); `notifications-island-testing-db.spec.ts
:131-133` (read rows are now inactive spans) + `:148` (exact tip). KEEP GREEN `:82`
(stored body fact) and `:253-274` (one-query law), plus the ONE-FEED equality spec and
the email-side tests (`ApprovalWorkflowTest.php:520/:566`, `EmailQueueTest.php:29` — the
emitter is untouched). NEW receipts: the 6 parts, multiline tooltip, inactive-on-read/
approved/rejected + the D15 copy family, badge exclusion, fail-open fallback
(Playwright per the ask's own requirement — the new WRITE-PATH receipts run ONLY against
the :8030 disposable testing DB under its env guard (`CRS_MODE=testing CRS_ALLOW_SQL=1`);
the demo DB :8000 is never written).

## Success criteria

- All 6 parts verified by a green Playwright receipt on the panel (the one surface,
  D12) + updated/added feature tests; pint + phpstan(1G) green; changelog entry written.
- ONE `replacement_requests` query per render still holds; badge == pill == actionable
  unread; every fallback path renders the honest old format rather than partial data.
- No pushes; commits only on the user's word.
