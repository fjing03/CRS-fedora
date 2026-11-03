# Spec — request status display (`request-completed-status`)

Capability: **request-status-display**. Requirement deltas for this lane only; every other
behaviour of the four surfaces is unchanged and is covered by their existing specs.

The user's decisions C1–C9 are quoted in `proposal.md` §2 and are the authority. This file turns
them into checkable requirements (`R`) and scenarios (`S`). Language is the user's: simple.

---

## R1 — The effective status is derived, never stored (C1, C2)

A request's **effective status** is its stored status, except in exactly one case: the stored
status is `approved` **and** the replacement class's **date** is strictly before today. That case
reads `completed`.

- The derivation happens when a page reads the row. Nothing is written: not
  `replacement_requests`, not `audit_logs`, not a cache.
- The comparison is by **date**, not by date-and-time (a class at 09:00 today is not "past" at
  10:00 today).
- The replacement date used is the same date the row prints (one computation, no second source).
- If the replacement date cannot be resolved, the status stays `approved` — the app never guesses
  Completed.

**S1.1** stored `approved`, replacement date yesterday → effective `completed`
**S1.2** stored `approved`, replacement date today → effective `approved`
**S1.3** stored `approved`, replacement date tomorrow → effective `approved`
**S1.4** stored `approved`, no resolvable replacement slot → effective `approved`
**S1.5** stored `pending` (any date) → effective `pending`
**S1.6** stored `rejected` / `cancelled` (any date) → effective unchanged
**S1.7** reading a row writes nothing: the stored status on the model is still `approved` after
the page renders, and no audit row is created

## R2 — One helper feeds every surface that prints a status (C3)

There are exactly three status producers. Each calls `ReplacementRequestMapper::effectiveStatus()`;
no surface computes the rule itself.

| Producer | Feeds |
|---|---|
| `ReplacementRequestMapper::toRow()` | Request Approval (PL) **and** the JSON API `/api/requests` |
| `MyRequestHistory::requestRow()` | My Requests (lecturer) |
| `ReplacementHome::render()` payload | Replacement Home |

**S2.1** the same approved-and-past request reads `Completed` on My Requests, on Request Approval
and in the API response
**S2.2** no other file in `app/` computes a completed status (grep: one definition)

## R3 — Exclude Completed is on by default, on all three pages, and the home gains it (C4)

- My Requests, Request Approval and the Replacement Home all show a toggle labelled
  **Exclude Completed** with the tooltip `ON = completed requests are excluded from the list`.
- It is **checked on every load** on the home page (no storage), and is the page default on the
  other two (the existing saved-filter behaviour there is unchanged).
- While checked, a `completed` row is not listed, not counted in the result count, and not counted
  in a summary card.

**S3.1** first visit to the home: the toggle is checked and no `Completed` row is listed
**S3.2** untick: the row appears, the result count and the total card include it
**S3.3** re-tick: the row disappears again and the counts drop back
**S3.4** the home toggle does not remember a previous untick across a reload (checked every load)

## R4 — The Completed badge opens the request (C5, first half)

On the home page a handled row's Action cell is a link. For a completed row that link is the
`Completed` badge pointing at `/my-request-history-ui?notif-hl=r<request_id>` — the same
destination as Pending/Approved.

**S4.1** a completed row shows a `Completed` badge (no Arrange button)
**S4.2** the badge's `href` carries this row's request id
**S4.3** clicking it opens My Requests with that request as the target

## R5 — My Requests may un-hide the row for you (C5, second half)

When My Requests is opened with that target and the row is missing, it may un-tick
**Exclude Completed** for the user **only if** that toggle is the sole reason. It then shows the
row and brings it onto the visible page, and the toggle visibly stays unticked (and the saved
filter matches what is on screen).

Any other cause — a status filter, a search term, a week filter — keeps the existing toast
("That request is hidden by your current filters.") and changes nothing.

**S5.1** row hidden only by the toggle → toggle becomes unticked, row visible, page flipped to it
**S5.2** row hidden by the status filter → toggle untouched, toast shown
**S5.3** row hidden by a search term → toggle untouched, toast shown
**S5.4** the request id in the URL does not exist → toast, no state change

## R6 — The final timeline step is timed by the class, not the approval (C6)

In My Requests' detail modal, the last timeline step (`Completed`) shows the **replacement
date**. Steps before it keep their existing timings (Submitted = requested-at, Under Review =
reviewed-at, with `—` where there is none).

**S6.1** completed row → final step reads `Completed` + the replacement date (e.g. `2 Nov 2026`)
**S6.2** approved-but-today row → final step is `Approved` and keeps the reviewed-at stamp
**S6.3** the date is rendered from the `Y-m-d` string without a timezone hop (never the day before)

## R7 — Nothing else changes (C7, C9, out of scope)

- No notification, email or bell row is produced for completion (the bell keeps its five events).
- No schema change, no migration, no seeder change, no demo/viva database write.
- The stored status is unchanged, so `whereIn('status', ['pending','approved'])` and the
  one-active-request partial unique index behave exactly as before.
- No new CSS: `status-completed` already exists.
- Request Approval needs no code change; its counts shift only because `toRow()` now says
  `Completed` (the `Approved` card drops the row, the `Reviewed` card keeps it).

**S7.1** completing nothing in the bell — the notification count for the demo personas is
unchanged after visiting the three pages
**S7.2** `git diff --stat` for this lane contains no `migrations/`, no `.sql`, no `theme.css`
**S7.3** the PL page's `Approved` card count for a past approved row is one lower than its
`Reviewed` card, with no page edit

---

## Acceptance summary

| Requirement | Proven by |
|---|---|
| R1 (S1.1–S1.7) | `ReplacementHistoriesTest` (4 cases) + `ReplacementHomeTest` (2 cases) + `ApprovalWorkflowTest` (1 case) |
| R2 (S2.1–S2.2) | the same feature tests across three producers; `grep` receipt |
| R3 | `tests/request-completed-status.spec.ts` (S3.1–S3.3, S3.4 = reload) |
| R4 | `tests/request-completed-status.spec.ts` (S4.1–S4.3) |
| R5 | `tests/request-completed-status.spec.ts` (S5.1); S5.2–S5.4 pinned by the existing toast branch + code receipt |
| R6 | code receipt + `status-tooltips-filter-chips.spec.ts` (the modal opens on the demo DB) |
| R7 | `git diff --stat`, `psql` before/after counts |
