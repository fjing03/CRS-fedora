# Spec — my-request-history (status tooltips + filter chips)

Source of truth: frozen `proposal.md` + `design.md` (D1 MRH, D3, D5 test 1/4/5/6).
Page fence: the My Request History page ONLY. No urgency badges exist here
(urgency is a request-approval concept) — none are invented.

## Requirement: every status badge on the page explains itself on hover

The MRH status badge build sites are: the desktop table row (`:526`, keeps
its `onclick="openModalById(…)"`), the quickCancel confirm-dialog badge
(`:632`), the batchCancelSelected dialog badge (`:678`), the detail-modal
Status row (`:758`), and the mobile-card badge in the shared
`HtmlBuilder.myRequestCard` (`ui-common.js:2369`, invoked at blade `:601`,
threaded via `opts.statusTip(status)`).

### Scenario: hovering a status badge shows the mapped tip ABOVE it
- **Given** the page is loaded and a request row/card is rendered
- **When** the user hovers the status badge (desktop table, mobile card,
  or either cancel dialog)
- **Then** the shared `.data-tip-tooltip` appears ABOVE the badge and its
  text equals exactly the map entry for that badge's own visible status:
  - `Pending` → `Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject).`
  - `Approved` → `The Programme Leader has APPROVED your request.`
  - `Rejected` → `The Programme Leader has REJECTED your request.`
  - `Cancelled` → `You MANUALLY CANCELLED this request.`
  - `Completed` → `The replacement class has been CONDUCTED — this request is complete.`
- **And** no tooltip mechanism is introduced: the tip is a plain
  `data-tip` attribute consumed by the existing `initDataTipTooltips()`
  (`ui-common.js:3000`).

### Scenario: the detail-modal Status row carries the same tip
- **Given** a request detail modal is open
- **Then** the modal's Status badge carries the identical `data-tip` for
  its status (hover shows the same tip as the row that opened it).

### Scenario: unknown/blank status fails open
- **Given** a status value not present in the MRH map (should not occur
  with the supported vocabulary)
- **Then** the badge renders WITHOUT a `data-tip` attribute — no broken
  markup, no invented status, no status-logic change.

## Requirement: the filter bar says "Active Filters:", resets to DEFAULT, and can clear all chips

### Scenario: the reset button is renamed and retipped
- **Given/When** the user hovers `#resetFilters` (was `#clearFilters`)
- **Then** the tip reads exactly `Reset all filters to DEFAULT`
- **And** `#clearFilters` no longer exists anywhere on the page; the
  button's label text (`Reset Filters`) and behaviour are unchanged
  (search/status/week cleared, `hideCompleted` back to checked,
  `mrh-filters` storage dropped, back to page 1).

### Scenario: chips bar shows "Active Filters:" + a Clear-all button while the bar has chips
- **Given** the chips bar renders ANY chips (they render at default state
  too — `Exclude Completed` — not only when non-default filters are
  active)
- **Then** the bar's label reads `Active Filters:` (unchanged from today)
- **And** a `Clear all` button (class `filter-chip filter-chip-clear-all`,
  the shared theme.css pill) is rendered INSIDE `#filterChips` after the
  chips, riding the existing innerHTML rebuild
- **And** every chip keeps its individual ✕ remove button.

### Scenario: Clear-all really resets every control (never a visual hide)
- **Given** ≥2 non-default filters are set (e.g. status + search)
- **When** the user clicks `Clear all`
- **Then** `resetAllFilters()` (the extracted body of the current reset
  listener — one function, two triggers) runs: search cleared, status →
  `all`, week → `all`, `hideCompleted` → checked, `mrh-filters` storage
  dropped, page → 1
- **And** the table re-renders with the NON-default-filters row count
- **And** the chips bar shows EXACTLY the default-state chips
  (`Exclude Completed` only — `hideCompleted` defaults to checked, so that
  chip is not "cleared"; nothing is merely hidden while a filter is active)
- **And** the `Clear all` button PERSISTS beside that default chip (the
  D3b `chips.length > 0` law — visible even at default load); it
  disappears only once NO chip remains at all, which happens only when the
  user ✕-removes every remaining chip (the bar then hides via `:empty`).

### Scenario: individual chip removal still works
- **Given** ≥2 chips are shown
- **When** the user clicks one chip's ✕
- **Then** only that filter is cleared; the remaining chips, the label,
  and the Clear-all button persist.

## Requirement: preservation (no behavioural regression)

### Scenario: badges keep their colours, clicks, and layout
- Status badge classes still come from the shared `statusClass()`
  (`ui-common.js` — untouched); badge `onclick` still opens the detail
  modal; table/card layout unchanged.

### Scenario: filtering, sorting, pagination, bulk-cancel unaffected
- The existing specs (`tests/my-request-history*.spec.ts` and the suite)
  continue to pass; this change adds only `data-tip` attributes, the
  `#resetFilters` id, the extracted `resetAllFilters()`, and the
  Clear-all button.

## Receipts (APPLY time — tests/status-tooltips-filter-chips.spec.ts)

1. Per-row hover loop: badge text ↔ tooltip text per the map above
   (fixture-independent — whatever statuses the demo DB holds), PLUS a
   `page.evaluate` fallback asserting the page's own
   `statusTooltip('Pending'|'Completed'|…)` strings against the full map —
   so the Pending/Completed strings are receipted even though the demo DB
   currently holds only approved/cancelled/rejected.
2. Detail-modal Status row `data-tip` spot-check.
3. `#resetFilters` exists with the exact new tip; `#clearFilters` gone.
4. Clear-all: default-state chips law + control defaults + table count;
   individual ✕ still works.
5. Mobile-card badge hover shows the status tip (the HtmlBuilder
   threading receipt).
6. Gates: pint + phpstan(1G) + node --check ui-common.js + the three
   changelogs (`my-request-history-changelog.md`,
   `request-approval-changelog.md`,
   `status-tooltips-filter-chips-changelog.md`); ONE commit series; NOT
   pushed.
