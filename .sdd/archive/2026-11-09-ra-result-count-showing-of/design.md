# Design — ra-result-count-showing-of

## D1. The change (one file, one deletion, one line)

`resources/views/livewire/request-approval.blade.php`:

1. **Delete** the page-local shadow (:804-806):
   ```js
   // ── Update result count ──
   function updateResultCount() {
       document.getElementById('resultCount').textContent = currentFiltered.length + ' result' + (currentFiltered.length !== 1 ? 's' : '');
   }
   ```
2. **Rewrite the single call site** inside `renderTable()` (:846) to use
   the SHARED helper (`ui-common.js:1608`, loaded in `<head>` via
   `ui-template.blade.php:50` — already global before the page script):
   ```js
   updateResultCount({ elId: 'resultCount', data: currentFiltered, total: MockData.approvalRequests.length, label: 'results' });
   ```
   → renders `Showing X of Y results` — byte-identical wording/spacing to
   my-request-history :495 (the user's "reuse the design").
3. The `// ── Update result count ──` banner comment goes with the
   deletion (nothing remains under it); no other comment or call changes.

## D2. The three deliberate decisions (frozen from the proposal)

- **`total` computed at call time** (`MockData.approvalRequests.length`,
  never a cached constant): the queue self-refreshes after approve/reject
  (`MockData.approvalRequests = res.requests`, :1164), so `Y` stays honest
  without extra wiring.
- **Always plural "results"**: the shared helper has no singular handling
  and my-request-history (the target design) none either — adding one HERE
  would diverge from the requested design. Reuse verbatim.
- **The static markup at :275 stays EMPTY**: request-approval's data is
  server-driven (PAGE_DATA bridge), so a baked default would flash a wrong
  number before hydration; `renderTable()` fills it on first paint (the
  skeleton loader covers first paint).

## D3. Safety

- No new helpers, no ui-common.js/CSS/PHP/route/DB changes — the third
  consumer of the existing shared helper (promote-on-3rd-duplication law
  satisfied by REUSING, not re-declaring).
- No other caller of the local function exists (grep-verified: def + one
  call); deleting it cannot break the approve/reject flow, pagination, or
  the chips — they never touch it.
- The name resolves to the global after deletion — every call path
  (`renderTable()` from DOMContentLoaded and from event handlers) runs
  after both scripts load.

## D4. Files touched (apply time)

| File | Change |
|---|---|
| `resources/views/livewire/request-approval.blade.php` | delete the local `updateResultCount()` (find by the banner comment `// ── Update result count ──`, NOT line anchors — they may drift); call the shared helper with the frozen cfg |
| `page-changelogs/request-approval-changelog.md` | receipt row |

Sequencing: applies AFTER the in-flight `status-tooltips-filter-chips`
commits land (same blade file — commit hygiene); the :274 static span
stays empty (verified).

## D5. Receipt (apply time — the Playwright part SKIPPED per the user's
## instruction; recorded as the user waiver in review-log.md)

The STRUCTURAL receipt (near-equivalent confidence for a static
string-concat call):
1. grep: the page-local `function updateResultCount()` GONE from
   request-approval.blade.php; EXACTLY ONE `updateResultCount({` call with
   the frozen cfg (`elId: 'resultCount', data: currentFiltered,
   total: MockData.approvalRequests.length, label: 'results'`).
2. ui-common.js:1608 untouched (the rendering string
   'Showing ' + … + ' of ' + … + ' ' + label — byte-matches
   my-request-history :495).
3. Gates: `composer run lint:check` + `vendor/bin/phpstan analyse
   --memory-limit=1G` + the stale-serve ritual (the two-command Blade
   change ritual) + the suite; a changelog row; ONE commit; NOT pushed.
