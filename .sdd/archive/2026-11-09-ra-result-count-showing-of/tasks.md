# Tasks — ra-result-count-showing-of

## T1. The blade change (≤0.25h)
- [x] request-approval.blade.php: delete the page-local `updateResultCount()`
      (by the banner comment `// ── Update result count ──`) and rewrite its
      single call site inside `renderTable()` to the shared helper with the
      frozen cfg (`elId: 'resultCount', data: currentFiltered,
      total: MockData.approvalRequests.length, label: 'results'`).

## T2. The structural receipt (≤0.2h)
- [x] grep: the local def GONE; EXACTLY ONE `updateResultCount({` call with
      the frozen cfg in the blade; ui-common.js:1608 untouched.
- [x] The stale-serve ritual (the two-command Blade ritual).

## T3. Gates + bookkeeping (≤0.3h)
- [x] composer run lint:check + vendor/bin/phpstan analyse --memory-limit=1G
      + the full suite.
- [x] The changelog (page-changelogs/request-approval-changelog.md) + ONE
      commit (`ui:`), no push; sdd.yaml → applied on green.
