# Spec — request-approval (status + urgency tooltips, Overdue badge, filter chips)

Source of truth: frozen `proposal.md` + `design.md` (D1 RA, D2 Overdue,
D3, D5 test 2/3/4/5/6).
Page fence: the Request Approval page ONLY. The queue renders ONLY
Pending/Approved/Rejected (mapper rowsPayload = pending + decided) —
no Cancelled/Completed tips are invented here.

## Requirement: every status badge in the queue explains itself on hover

Status badge build sites: the queue table row (`:679`, keeps its
`onclick`), the detail-modal Status row (`:938`), and the mobile-card
badge in the shared `HtmlBuilder.requestCard` (`ui-common.js:2340`,
invoked at blade `:825`, threaded via `opts.statusTip(status)`).

### Scenario: hovering a status badge shows the mapped tip ABOVE it
- **Given** the queue is rendered (status filter set to `All` so all
  three statuses are on screen)
- **When** the user hovers a status badge (table row, mobile card, or
  detail modal)
- **Then** the shared tooltip appears ABOVE the badge with exactly:
  - `Pending` → `This request is AWAITING your review and decision (Approve or Reject).`
  - `Approved` → `You have APPROVED this request.`
  - `Rejected` → `You have REJECTED this request.`
- Plain `data-tip` attributes only — the existing tooltip system, no new
  mechanism or CSS.

### Scenario: unknown/blank status fails open
- A status outside the map renders without a `data-tip` — no broken
  markup, no invented statuses, no status-logic change.

## Requirement: urgency badges show the REAL remaining time, and past dates get a genuine Overdue badge

Scope fence: presentation-level urgency ONLY. Request-status logic, the
mapper, the DB, and the queue composition are untouched.

### Scenario: the Overdue level exists
- **Given** `urgencyDays(classDate) < 0`
- **Then** `urgencyLevel()` returns `'overdue'`; `urgencyClass()` →
  `urgency-overdue`; `urgencyLabel()` → `Overdue`
- **And** `.urgency-overdue` renders as a SOLID red pill
  (`background: var(--color-error); color: var(--color-on-error)` —
  tokens only, the same red family as Urgent's translucent container,
  one severity step up)
- **And** the row keeps the `row-urgent` highlight
  (`level === 'urgent' || level === 'overdue'`).

### Scenario: hovering an urgency badge shows the dynamic day count
- **Given** any queue row (badge sites: approvalCard confirm-modal badge
  `:502`, table cell `:678`, mobile card via `opts.urgencyTip`)
- **When** the user hovers the urgency badge
- **Then** the tip matches, with `N = urgencyDays(r.classDate)` computed
  at render (singular `day` when `abs(N) === 1`):
  - overdue → `Overdue — {abs(N)} day(s) past the Proposed Class.`
  - `N = 0` → `Urgent — the Proposed Class is TODAY.` *(new wording —
    flagged for user confirmation, asserted in the receipt either way)*
  - urgent → `Urgent — {N} day(s) left before the Proposed Class.`
  - normal → `Normal — {N} day(s) left before the Proposed Class.`
- **And** the tip's leading word equals the badge's visible label
  (badge ↔ tip consistency = the dynamic-days proof).

### Scenario: the Urgent FILTER keeps matching overdue rows
- **Given** the urgency filter is `urgent`
- **Then** rows with level `urgent` AND rows with level `overdue` both
  match (`urgencyMatches()` — the silent-drop law)
- **And** Normal filtering is provably unchanged; the
  `if (urgencyFilter !== 'all')` guard stays; the `.urgency-filter-chip`
  button set (all/urgent/normal) is NOT extended.

### Scenario: the Urgency column-header tip stays truthful
- The `:562` column-header tip's exact new copy is pinned:
  `Days until the original class: past = Overdue (solid red), ≤3 days = Urgent (red), 4+ days = Normal`
  (the old "<=3 days: Urgent, 4+: Normal" text becomes factually wrong
  once Overdue exists; this is a copy fix, no logic, no new element) —
  asserted in receipt 3.

## Requirement: filter bar — "Active Filters:", reset to DEFAULT, real Clear-all

### Scenario: the reset button is renamed and retipped
- **When** the user hovers `#resetFilters` (was `#clearFilters`)
- **Then** the tip reads exactly `Reset all filters to DEFAULT`
- **And** `#clearFilters` no longer exists; `resetFilters()` behaviour
  unchanged (status → `Pending`, week all, urgency all + chip toggles, rpp
  10, hide-completed checked, bulk/viewed/sort/page resets, storage
  dropped).

### Scenario: chips bar shows "Active Filters:" + a Clear-all button while the bar has chips
- **Given** the chips bar renders ANY chips (they render at default state
  too — `Status: Pending` + `Exclude Completed` — not only when
  non-default filters are active)
- **Then** the bar's label reads `Active Filters:` (was `Filters:`)
- **And** a `Clear all` button (`filter-chip filter-chip-clear-all`, the
  shared theme.css pill) renders INSIDE `#filterChips` after the chips,
  riding the innerHTML rebuild; every chip keeps its ✕.

### Scenario: Clear-all really resets every control (never a visual hide)
- **Given** ≥2 non-default filters are set
- **When** the user clicks `Clear all`
- **Then** the existing `resetFilters()` runs verbatim (one function, two
  triggers — no reset logic duplicated or reimplemented)
- **And** the table re-renders with the non-default-filters row count
- **And** the chips bar shows EXACTLY the default-state chips
  (`Status: Pending` + `Exclude Completed` — those defaults stay active,
  so their chips legitimately remain; nothing is merely hidden)
- **And** the `Clear all` button PERSISTS beside those default chips (the
  D3b `chips.length > 0` law — it is visible even at default load); it
  disappears only once NO chip remains at all, which happens only when the
  user ✕-removes every remaining chip (the bar then hides via `:empty`).

### Scenario: individual chip removal still works
- Removing one chip via its ✕ leaves the others, the label, and
  Clear-all intact.

## Requirement: preservation (no behavioural regression)

### Scenario: badges keep colours, clicks, layout; sorting/pagination/bulk untouched
- `statusClass()` untouched; queue-row `onclick` still opens the detail
  modal; `requestAgeHtml()` still threads the urgency label (reads
  correctly with the new `Overdue` word — re-read at apply); existing
  specs keep passing.

## Receipts (APPLY time — tests/status-tooltips-filter-chips.spec.ts)

1. Status-tip hover loop with status pinned to `All` (3-status receipt,
   non-vacuous), PLUS a fixture-independent fallback: `page.evaluate`
   calls the page's own `statusTooltip('Pending'|'Approved'|'Rejected')`
   and asserts all three strings against the frozen map — so the Pending
   string is receipted even though the demo DB currently holds no
   pending rows (zero DB writes).
2. Urgency receipt: format regex + badge↔tip word match per row; the
   Overdue branch asserted UNCONDITIONALLY by shifting
   `window.MockData.urgencyReferenceDate` in-page + re-rendering (badge
   flips to Overdue + `days past` tip + `row-urgent` + still matches the
   Urgent filter) — zero DB writes.
3. `#resetFilters` exists with the exact tip; `#clearFilters` gone; the
   chips label reads `Active Filters:`; the Urgency column header's
   `data-tip` equals the pinned three-level copy.
4. Clear-all: default-state chips law + control defaults + table count;
   individual ✕ still works.
5. Mobile-card receipt: status tip + urgency tip through the shared
   HtmlBuilder.
6. Gates: pint + phpstan(1G) + node --check ui-common.js + changelogs;
   ONE commit series; NOT pushed.
