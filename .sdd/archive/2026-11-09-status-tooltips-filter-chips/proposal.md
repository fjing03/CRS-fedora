# Proposal — status-tooltips-filter-chips

## Problem

Neither request page explains its badges on hover. A lecturer reading
`Cancelled` or `Urgent` on their own queue must guess what it means, and the
two pages' filter bars have drifted apart (Request Approval still says
"Filters:", neither page can clear all chips in one click, and the Reset
button's tip undersells what it does).

## The user's rulings (2026-10-30 — frozen at proposal)

1. **Status badges get hover tooltips** on `/my-request-history-ui` (5
   statuses) and `/request-approval-ui` (3 statuses — the queue only ever
   renders Pending/Approved/Rejected), using the EXACT wording tables below.
2. **Request Approval gains a REAL Overdue urgency badge** (the user's Q3
   ruling — a sanctioned badge-logic change): past-dated requests stop
   masquerading as "Urgent" and get their own label + colour + tooltip.
3. **The reset tip becomes "Reset all filters to DEFAULT"** on BOTH pages
   (the user's Q4 ruling — the two buttons are identical).
4. **Filter chips gain a Clear-all button** on both pages that REALLY resets
   every control (not a visual hide), and Request Approval's bar label
   becomes "Active Filters:" (My Request History already has that label —
   untouched there).
5. **Tooltips reuse the existing system** (`data-tip` → the shared floating
   `.data-tip-tooltip`, which already positions ABOVE its element and works
   on dynamically rebuilt HTML). No new tooltip style, ever.
6. **No request-status/DB logic changes** — the request-status state machine,
   the mapper, and queue composition are untouched. The ONE sanctioned
   exception is the **urgency badge level on request-approval only** (the
   Overdue ruling above) — that is presentation-level urgency logic, never
   request-status logic. No unsupported statuses invented ("expired" does
   not exist in this system — none introduced).

## What this change builds

1. **Per-page `statusTooltip(status)` helpers** (page-local — the two pages
   speak in different voices: proposer vs reviewer) feeding `data-tip` on
   EVERY status-badge build site (desktop table, mobile cards, detail-modal
   Status rows) so dynamically rendered badges carry their tip.
2. **The Overdue state** on request-approval: `urgencyLevel()` returns
   `'overdue'` when the class date has passed; `urgencyLabel` → "Overdue";
   a new `.urgency-overdue` pill in solid `--color-error`/`--color-on-error`
   (the same red family as Urgent's translucent container — one visible
   severity step up); overdue rows keep the `row-urgent` row highlight; the
   **Urgent filter keeps matching overdue rows** (predicate extended — the
   existing filtering behaviour is preserved, not silently changed); the
   urgency tooltip shows the REAL day count from `urgencyDays(classDate)` —
   "Normal — N days left before the Proposed Class." /
   "Urgent — N days left before the Proposed Class." /
   "Overdue — N days past the Proposed Class."
3. **The filter bars**: `#clearFilters` → `#resetFilters` (both pages, all
   listeners updated), the new reset tip text (both), a chip-styled
   **Clear-all** button rendered inside `#filterChips` (rides the existing
   innerHTML rebuild; appears only while chips exist) calling a per-page
   `clearAllFilters()` that mirrors that page's OWN reset defaults, and the
   "Active Filters:" label on Request Approval.

## The tooltip mapping law (exact strings)

### `/my-request-history-ui` (the proposer's own requests)

| Status | `data-tip` |
|---|---|
| Pending | Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject). |
| Approved | The Programme Leader has APPROVED your request. |
| Rejected | The Programme Leader has REJECTED your request. |
| Cancelled | You MANUALLY CANCELLED this request. *(the spec's "You Manually Manually this request." was a typo — user-confirmed)* |
| Completed | The replacement class has been CONDUCTED — this request is complete. *(the 5th supported status — user-ruled in)* |

### `/request-approval-ui` (the PL's review queue)

| Status | `data-tip` |
|---|---|
| Pending | This request is AWAITING your review and decision (Approve or Reject). |
| Approved | You have APPROVED this request. |
| Rejected | You have REJECTED this request. |

### Request Approval — urgency badge tooltip (dynamic, from `urgencyDays`)

| Badge | `data-tip` |
|---|---|
| Normal | Normal — N days left before the Proposed Class. |
| Urgent | Urgent — N days left before the Proposed Class. |
| Overdue | Overdue — N days past the Proposed Class. |

`N` is computed at render (never hardcoded); a same-day class reads
`Urgent — the Proposed Class is TODAY.` *(the N=0 edge case — new wording
not in the user's tables; flagged for user confirmation and asserted in
the Playwright receipt either way, so it can never surface as a surprise
at verify)*

## Same-name-same-colour canon

No new colours: `.urgency-overdue` uses the EXISTING `--color-error` /
`--color-on-error` tokens (red = the urgency family's severe end). Urgent
keeps its translucent `--color-error-container`. Status badge colours
(`statusClass`) untouched.

## Out of scope

- Any change to request status logic, the mapper, DB records, or the
  Request Approval queue's composition (cancelled/completed rows stay out).
- A new Overdue FILTER button (overdue rows remain inside the Urgent filter
  family — behaviour-preserving).
- The My Request History bar label (already "Active Filters:" — untouched).
- Any new tooltip CSS/JS, new dependencies, routes, or migrations.

## Risks

- The urgency-filter predicate extension must not bleed into the Normal
  filter (receipt in the spec run).
- The Clear-all button rides a rebuilt innerHTML — it must vanish with the
  last chip and never linger as a dead control.
- `requestAgeHtml()` threads `urgencyLabel(level)` into its age chip — the
  new "Overdue" word must still read correctly there (checked at apply).

## Success criteria

- Hovering ANY status badge on either page shows exactly the mapped tip,
  above the badge, on desktop rows, mobile cards, and detail modals.
- A past-dated Request Approval row shows the Overdue badge + "N days past"
  tip, still counts as Urgent for filtering, still highlighted.
- One click on Clear-all empties every chip AND every control on both pages
  (verified against each page's own reset defaults); the Reset button tip
  reads "Reset all filters to DEFAULT"; RA's label reads "Active Filters:".
- Playwright receipts green; pint/phpstan/node-check green; changelogs
  updated; ONE commit series; NOT pushed.
