# Proposal — ra-result-count-showing-of

## Problem

`/request-approval-ui`'s result counter renders a bare runtime count
(`0 results` when nothing matches — inline JS:
`currentFiltered.length + ' result(s)'`; the static markup itself is
empty) — while EVERY other list page
(`/my-request-history-ui`, `/replacement-home-ui`) renders the shared
`Showing X of Y results` format through the common
`updateResultCount(cfg)` helper in `ui-common.js:1608`. The user asked for
request-approval to match my-request-history's design — literally: "Showing
7 of 7 results".

## Root cause (explored 2026-10-30)

request-approval.blade.php defines its OWN page-local, no-arg
`function updateResultCount()` (:804) that **shadows** the shared global
helper of the same name, then inlines a different format inside it:

- Shared helper (`ui-common.js:1608`, used by my-request-history :495 and
  replacement-home :497): `updateResultCount({ elId, data, total, label })`
  → `'Showing ' + data.length + ' of ' + total + ' ' + label`.
- request-approval (:804-806, called once at :846 inside `renderTable()`):
  no-arg local → `currentFiltered.length + ' result' + (…'s')`.
- The page's full (unfiltered) queue lives in `MockData.approvalRequests`
  (server-overridden via `PAGE_DATA.requests` at :1257) — the honest `Y`.

## The fix (reuse the existing design — zero new helpers)

1. DELETE the page-local `updateResultCount()` (:804-806).
2. Replace its single call site (:846) with the shared-helper call:
   `updateResultCount({ elId: 'resultCount', data: currentFiltered, total: MockData.approvalRequests.length, label: 'results' });`
   → renders exactly `Showing X of Y results` (X = rows matching the
   current filters, Y = every request in this PL's queue), identical
   wording/spacing to my-request-history.
3. The static pre-JS markup at :275 stays EMPTY (my-request-history's
   baked "Showing 20 of 20 results" is its mock default; request-approval's
   data is server-driven — a hardcoded default would flash a wrong number
   before PAGE_DATA arrives; `renderTable()` fills it on first paint).

## Scope fence

- One file: `resources/views/livewire/request-approval.blade.php` (one
  deletion + one call-site line). No ui-common.js change, no CSS, no
  helper added or duplicated, no PHP/DB/routes.
- No plural logic: the shared helper always says "results" — matching
  my-request-history verbatim is the requirement (its "Showing 7 of 7
  results" is the target, singular handling deliberately absent there).
- NOT touching the in-flight `status-tooltips-filter-chips` change (same
  file — this change applies AFTER that one's commits land).

## Out of scope

- Changing the shared helper's format or its other consumers
  (my-request-history, replacement-home).
- The result-count `data-tip` ("Rows matching the current filters") —
  already identical on both pages.
- Pagination/summary interplay (the summary strip stays as is).

## Success criteria

- `/request-approval-ui` shows `Showing X of Y results` (X = filtered,
  Y = `MockData.approvalRequests.length`) after load and after every
  filter/reset/clear-all — the exact my-request-history format.
- Deleting the local function breaks nothing else (single call site,
  verified).
- Playwright receipt asserting the format on both pages; pint/phpstan/
  node --check gates; changelog updated; ONE commit; not pushed.
