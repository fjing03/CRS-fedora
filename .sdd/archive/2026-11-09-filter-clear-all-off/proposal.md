# Proposal — `filter-clear-all-off` (the user's bug report, 2026-10-30)

**The report:** the pages' chip Clear-all button (`my-request-history-ui` +
`request-approval-ui`) leaves filters alive: the default exclusions
(`Exclude Completed`, and Request Approval's own default `Status: Pending`)
survive the click, and the bar keeps saying `Active Filters:`.

**Why (the honest read of the receipts):** the applied
`status-tooltips-filter-chips` lane (8e75425) froze the clear-all as
"reset-to-DEFAULT" — but on Request Approval the DEFAULT status = `Pending`
(≠ `all`) and BOTH pages default `hideCompleted = true`, so a
reset-to-default STILL renders default-exclusion chips. The lane's own
receipt rows codified that contradictory law. The user's ruling overrides:
**Clear all = every filter OFF, the bar EMPTY.**

## The change (the two pages'受阻 chip buttons only — the Reset button untouched)

- **`my-request-history`:** a NEW page-local `clearAllFilters()` = the
  `resetAllFilters()` body (:877) with ONE state difference:
  `hideCompleted.checked = false` (vs the default's `true`); everything else
  identical (search '', status 'all', week 'all', the `mrh-filters` storage
  dropped, page 1, re-render). The chip button's onclick → `clearAllFilters()`; the page-local window exposure
  `window.clearAllFilters = clearAllFilters;` added beside the existing
  `window.resetAllFilters` (the :998 law — inline handlers need the global).
  The `#resetFilters` button + its "Reset all filters to DEFAULT" tip stay
  byte-identical (they own the DEFAULT law).
- **`request-approval`:** a NEW `clearAllFilters()` = the `resetFilters()`
  body (:1214) with TWO state differences: `statusFilter.value = 'all'` (vs
  the default's `'Pending'`) and `hideCompleted = false` (checkbox
  unchecked); everything else identical (urgency 'all' + the urgency-chip
  toggles, week 'all', search '', rpp 10, bulk/viewed cleared, page 1, sort
  default, the storage dropped, re-render). The TWO hideCompleted statements
  BOTH flip (:1221 `hideCompleted = false` + :1222 the checkbox — the chip
  branch reads the variable, the ✕ sets the checkbox — fixing only one
  leaves the other lying). The chip button's onclick → `clearAllFilters()`
  (+ its window exposure). The `#resetFilters` button stays byte-identical.
- **The bar law (both):** after the clear → `#filterChips` renders NOTHING
  (no chips, no `Active Filters:` label) — the existing
  label-only-with-chips rule already yields this once the filters are off.
- **The spec law flip** (`tests/status-tooltips-filter-chips.spec.ts`
  T5.MRH-2 + T5.RA-3 — the frozen-lane receipts, flipped HERE by this
  amendment, not silently): the post-Clear-all asserts flip from "the
  default chips remain + Clear-all persists" to "the bar EMPTY, no chips,
  no `Active Filters:`, the Clear-all pill gone (+ the controls at OFF)".
  The post-clear table assert ALSO flips: the rows = the UNFILTERED
  all-rows count (the old asserts expected a filtered count — contradicted
  under the new law). The ✕ segments + the mobile/card segments stay.

## Out of scope

- The `#resetFilters` DEFAULT law + tip (the lane's ruling 3 — untouched).
- The ✕ chips, the tooltips, the urgency badges, the data/DB logic.
- Any hidden-state visual hack — the chips disappear because the filters
  ARE off.

## Verify

- Playwright: BOTH spec rows green after the flip — the bar EMPTY after the
  Clear-all (no chips, no label, the Clear-all pill itself gone), the
  controls at OFF, the Reset button's DEFAULT law intact.
- Gates: pint + phpstan-1G 0; the full suite; the TC70 guard. NO push; no
  DB writes; the :8000 demo is read-only for these specs.
