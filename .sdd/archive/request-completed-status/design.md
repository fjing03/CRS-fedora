# Design — "Completed" request status (derived at read)

Frozen against `proposal.md` (C1–C9, the behaviour matrix, the out-of-scope list).

## 1. The helper — one place, prompt §6 contract verbatim

`app/Services/ReplacementRequestMapper.php` already owns the replacement date
(`replacementStrings()` :227 → `displaySlot()` :~200 → the data-driven junction/fallback span) and
is the shared mapper for the PL page **and** the JSON API. So the rule lives there:

```php
/**
 * C1/C2 — the effective status ('pending'|'approved'|'rejected'|'cancelled'|'completed').
 * Derived, never stored: an approved request whose replacement class DATE is
 * strictly before today reads 'completed'. Falls back to the stored status when
 * the replacement date cannot be resolved (never guesses Completed).
 */
public function effectiveStatus(ReplacementRequest $r, Carbon $semesterStart): string
```

Body = exactly the prompt's §6 block, with `Carbon::parse($date)->startOfDay()->lt(today()->startOfDay())`
(trap 2) and the `null` date early-return (trap 4). It returns **lowercase**; every consumer
applies `ucfirst()` where it needs the pretty form.

**Why derived from the printed date (trap 5):** the helper calls the *same*
`replacementStrings()` the row prints, so the badge and the Replacement Date column can never
disagree. No second date computation exists.

**Cost (measured, stated honestly):** per **approved** row only, `effectiveStatus()` adds one
junction query plus one venue query (the constructed `TimeSlot` model resolves `venue` lazily).
Pending/rejected/cancelled rows cost nothing. A page with N approved rows pays 2N queries; the
demo/viva data has one approved request. The alternative — threading a pre-computed `$span`
through the signature — was rejected because the prompt's §6 signature is the frozen contract and
a second parameter shape would fork the call sites by page.

**What it does NOT touch:** the stored status, `whereIn('status', …)`, the active-request partial
unique index, the audit log, and the wall clock beyond `today()`.

## 2. The three call sites (closed set — C3)

| # | Caller | Exact change |
|---|---|---|
| 1 | `ReplacementRequestMapper::toRow()` (`$prettyStatus` line) | `$prettyStatus = ucfirst($this->effectiveStatus($r, $semesterStart));` |
| 2 | `MyRequestHistory::requestRow()` (`'status' =>`) | `'status' => ucfirst($mapper->effectiveStatus($r, $semesterStart)),` |
| 3 | `ReplacementHome::render()` payload (`'requestStatus' =>`) | the effective value, **raw lowercase** (the badges lane's `statusBadge()`/`isHandled()` expect raw) |

`ApiReadController::myRequests()` is **not** edited — it maps through `$mapper->toRow($r, …)` and
unsets only `requester/slotValidity/conflictReason/bucket`, so `status` arrives already derived.

`RequestApproval::rowsPayload()` is **not** edited — both its loops call `toRow()`.

### 2.1 The home payload needs two relations

`ReplacementHome` currently selects `id, class_session_id, week_number, status, submitted_at` and
eager-loads nothing on that query. `effectiveStatus()` needs `classSession` (already loaded via
the session loop) + `replacementTimeSlot` (the legacy fallback). So the `$latestRequests` query
gains `->with(['replacementTimeSlot.venue'])`. Values that are not selected are not needed: the
helper reads `status`, the class session (from the already-loaded model), and the span.

## 3. Replacement Home — the toggle (C4) and the filter

### 3.1 Markup (copied verbatim from My Requests :200–204)

```html
<label class="toggle-wrapper" id="completedToggle" data-tip="ON = completed requests are excluded from the list">
    <input type="checkbox" id="hideCompleted" checked>
    <span class="toggle-track"><span class="toggle-thumb"></span></span>
    <span class="toggle-label">Exclude Completed</span>
</label>
```

Placed in `.toolbar-left` after the week nav include. **No `localStorage`** — this page has no
saved filters and no Reset button, and the user chose "checked on every load". The
`.toggle-wrapper` / `.toggle-track` / `.toggle-thumb` / `.toggle-label` classes are shared
(`theme.css`) — **no CSS work** (prompt §7.4).

### 3.2 One predicate, three uses

```js
/* C4 — the ONE exclusion test. 'completed' is the payload's derived value. */
function completedHidden(c) {
    var box = document.getElementById('hideCompleted');
    return !!box && box.checked && c.requestStatus === 'completed';
}
/* the base list BEFORE search/week (what the page counts as "mine") */
function visibleBaseRows() {
    return myConflictedRows().filter(function(c) { return !completedHidden(c); });
}
```

- `buildTable()` filter chain — one more clause, before the sort:
  `if (completedHidden(c)) return false;`
- `updateResultCount({ …, total: myConflictedRows().length })` → `total: visibleBaseRows().length`
- `updateSummary()` — the Total card (`summaryMyConflicted`) reads `visibleBaseRows().length`;
  the two My cards keep reading `currentFiltered` (they already exclude completed through the
  filter). The instruction from the prompt (§7.3.2) is that no count may promise a row that is
  not on screen; the Total card is the only one that ignored the filter chain, so it is the only
  one that changes.
- `renderCards()` needs nothing — it renders `currentFiltered`.
- A `change` listener on `#hideCompleted` → `pageState.currentPage = 1; buildTable();`
  (`buildTable()` already calls `updateResultCount`, `updateSummary`, `renderCards`).

Empty state: when the toggle hides every row, the existing empty state shows. Its copy
("No classes currently need attention.") stays honest for this lane — the hidden rows *are*
handled.

### 3.3 The badge (no new code)

The badges lane's `statusBadge()` renders any non-rejected/cancelled status as
`<a class="badge badge-link status-<raw>" href="/my-request-history-ui?notif-hl=r<id>">`. Raw
`completed` → `status-completed` (`theme.css:3647`, already styled) and label `Completed`
(`statusLabel()` char-uppercases). `isHandled()` treats `completed` as handled → em dash in Days
Left and the handled sort group, both already written. **Zero new badge code.**

## 4. My Requests — C6 (timeline) and C5 (reveal exception)

### 4.1 C6 — the final step is timed with the replacement date

`.blade.php` `openModalById()` currently:

```js
const reviewTime = r.reviewedAt ? formatDateTime(r.reviewedAt) : null;
… { label: r.status, time: reviewTime || '—', … }
```

becomes:

```js
const reviewTime = r.reviewedAt ? formatDateTime(r.reviewedAt) : null;
/* C6: the final step is timed by the CLASS, not the stamp. `formatDate` splits
   the Y-m-d string — no Date parsing, so no UTC-midnight day shift (trap). */
const completedTime = (r.status === 'Completed' && r.replacementDate) ? formatDate(r.replacementDate) : null;
… { label: r.status, time: completedTime || reviewTime || '—', … }
```

`formatDate()` is `DateHelper.formatDate` — `iso.split('-')` and a month-name lookup
(`ui-common.js:2226`), so `2026-11-03` renders as `3 Nov 2026` with no timezone hop. **No new
payload key** (`replacementDate` already exists and is pinned by tests).

`Under Review` keeps `reviewTime` — the stamp is the right time for a review.

### 4.2 C5 — the reveal exception inside `jumpToRequestRow()`

The badges lane wrote:

```js
function jumpToRequestRow(id) {
    if (!id) return;
    var idx = currentFiltered.findIndex(...);
    if (idx < 0) { toast.show('That request is hidden by your current filters.', null); return; }
    var page = Math.floor(idx / rowsPerPage) + 1;
    if (page !== pageState.currentPage) { pageState.currentPage = page; renderTable(); }
}
```

The not-found branch becomes:

```js
if (idx < 0) {
    /* C5 — THE REVEAL EXCEPTION. Un-tick Exclude Completed ONLY when that
       toggle is the sole reason the row is missing: the row exists, it is
       Completed, and the toggle is on. Any other hiding cause (status filter,
       search, week) keeps the honest toast. */
    var all = mockRequests.find(function(x) { return String(x.id) === String(id); });
    var onlyCompletedHidesIt = all && all.status === 'Completed'
        && document.getElementById('hideCompleted').checked;
    if (!onlyCompletedHidesIt) {
        toast.show('That request is hidden by your current filters.', null);
        return;
    }
    document.getElementById('hideCompleted').checked = false;  // visible flip — the user sees it happen
    saveFilters();                                            // persist what they now see
    renderTable();                                            // rebuilds currentFiltered synchronously
    idx = currentFiltered.findIndex(function(x) { return String(x.id) === String(id); });
    if (idx < 0) { toast.show('That request is hidden by your current filters.', null); return; }
}
var page = Math.floor(idx / rowsPerPage) + 1;
if (page !== pageState.currentPage) { pageState.currentPage = page; renderTable(); }
```

Ordering that matters (and why):

- The check is `all.status === 'Completed'` on the **raw row**, not on `currentFiltered`
  (which by definition does not contain the row).
- `renderTable()` sets `currentFiltered` synchronously (:457), so the recomputed `idx` is valid
  before the page flip. The second guard re-checks (defensive: `saveFilters`/`renderTable` touch
  the DOM through the same shared helpers as the filter chips).
- `saveFilters()` runs **before** the render so the persisted state matches the visible state; the
  filter-chips bar then shows/hides the Exclude-Completed chip consistently.
- The row is *revealed*, not highlighted differently: the shared `applyNotifHighlight` still fires
  its own 1.3 s `bookable-flash` (untouched).

`pendingHl` is captured synchronously at the top of `DOMContentLoaded` (badges lane) and consumed
after the first render; `restoreFilters()` runs **before** that render (it is a synchronous call
and `SkeletonLoader.with(..., 400)` defers the callback), so a saved "Exclude Completed = off"
state is already applied when the jump runs.

## 5. Request Approval — verify only, no code

| Check | Where | Expected |
|---|---|---|
| the badge text | `renderRow()` prints `r.status` | `Completed` |
| status tooltip | `statusTooltip('Completed')` | the frozen "Replacement conducted" copy (:950) |
| the filter option | `#statusFilter` | `Completed` already listed (:259) |
| the toggle | `#hideCompletedToggle` | exists, **checked**, label "Exclude Completed" (:268–270) |
| the row filter | `buildTable` filter | `if (hideCompleted && r.status === 'Completed') return false;` (:655) |
| the summary counts | `updateSummary()` (:756–758) | `approved` no longer counts it; `reviewed` (`['Approved','Rejected','Completed']`) does |
| the dot colour | :953 | `Completed` → `dot-success` |

If any of these is false, the fix belongs to the page — never to the helper (§7.2 of the prompt).

## 6. Known issues NOT fixed here

1. **Cross-semester display base** (prompt trap 6). `MyRequestHistory::requestRow()` and
   `RequestApproval::rowsPayload()` pass the *page's* timeline semester start to the mapper for
   every row, so a request belonging to another semester would already print a wrong replacement
   date — and, through this lane's helper, a wrong Completed derivation. Every database holds one
   semester (`202605`), so this is latent. Not fixed: out of scope, and fixing it means changing
   the mapper's signature and both callers' semester resolution.
2. **`STATUS_ORDER` in the My Requests sort** has no `Cancelled` entry (falls back to `9`). Pre-
   existing, untouched by this lane — recorded so a later reader does not blame the derivation.
3. **Approved rows keep their Arrange/label semantics from the badges lane**: `approved`, and now
   `completed`, are both "handled" → badge link, em dash, handled sort group. That is the user's
   intent (a Completed request is not re-arrangeable from the home).

## 7. Test plan

### Feature (testing DB only — `DB_DATABASE=class_replacement_testing`)

`tests/Feature/ReplacementHistoriesTest.php` (My Requests surface):
- approved + replacement **yesterday** → `status === 'Completed'`;
- approved + replacement **today** → `status === 'Approved'`;
- approved with **no resolvable slot** (no junction, null anchor) → `status === 'Approved'`
  (trap 4);
- pending → unchanged (already pinned by the existing test).

`tests/Feature/ApprovalWorkflowTest.php` (the PL surface, via `toRow()`):
- an approved row whose date is past reads `Completed` **and** keeps `bucket === 'queue'`
  (only the displayed status changes).

API: `toRow()` is the only producer and its `status` key is asserted by the mapper rider above;
a narrow API assertion is added in `ReplacementHistoriesTest` only if a test file for the API
endpoint already exists — it does not, so the API is covered structurally (same function, same
key) plus the browser-free `toRow()` test. **Honest note:** no HTTP-level API test exists for
`/api/requests`; this lane does not build an API suite (prompt §9).

`tests/Feature/ReplacementHomeTest.php` (the home surface):
- past approved → `requestStatus === 'completed'`;
- future approved → `'approved'`;
- the badges lane's holiday-precedence test **expectation flips to `completed`** — it pins
  `requestStatus` on a week-5 (September) approved row, i.e. exactly the case C2 redefines. Only
  the expected literal changes; the D9 holiday assertion stays.

### Browser

- New `tests/request-completed-status.spec.ts` on the **disposable testing DB** (`:8030`), with a
  self-contained guard (no cross-lane import), an id-keyed fixture and an id-keyed sweep:
  fixture = one approved request for staff 5425 on a conflicted occurrence whose replacement date
  is 2 days ago. Receipts: home toggle checked by default and the Completed row absent; untick →
  the Completed badge appears and its href is `?notif-hl=r<id>`; click → My Requests, the reveal
  exception un-ticks the toggle, the row is on the page and shows `Completed`.
- Re-run on `:8000` (read-only demo) to prove the toggle renders, is checked, and does **not**
  change the row count (the demo DB has no completed row — stated, not claimed).
- `tests/status-tooltips-filter-chips.spec.ts` + `tests/request-approval.spec.ts` must still pass
  (they exercise the toggle and the Completed tooltip on the demo DB).

### Not receiptable, stated plainly

- **The viva twin's Completed badge today.** `class_replacement_viva` holds request 27
  (`approved`, week 7) whose replacement date is **2026-11-03 = today** — by C2 it reads
  **Approved** today and **Completed from 4 Nov**. So on the viva database the live receipt today
  is the *boundary* half of the matrix ("today stays Approved"); the "yesterday → Completed" half
  is receipted on the testing DB. No demo/viva write is made to force it (C9).
- **The reveal exception's "any other cause keeps the toast"** on real data: on the demo DB the
  pending rows are never Completed, so a hidden-by-filter scenario needs the fixture page. The
  browser spec covers the reveal branch; the toast branch is pinned by reading the code path (and
  by the badges lane's spec, which asserts the toast for a filtered row).

## 8. Gates

`composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` (**never**
`types:check`) · `node --check public/js/ui-common.js` (unchanged but cheap) · full
`php artisan test` on the testing DB (baseline **315**, quote the real number) · the new spec on
`:8030` + the two existing browser specs on `:8000`.

## 9. Receipts to collect

| Receipt | Command |
|---|---|
| helper matrix (5 rows) | `php artisan test --filter=ReplacementHistoriesTest` + `--filter=ApprovalWorkflowTest` + `--filter=ReplacementHomeTest` |
| full suite | `setsid nohup env DB_DATABASE=class_replacement_testing php artisan test` |
| home toggle + badge + reveal | `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test tests/request-completed-status.spec.ts` |
| demo read-only sanity | `npx playwright test tests/status-tooltips-filter-chips.spec.ts tests/request-approval.spec.ts` |
| no writes anywhere | `psql … -c "select …"` before/after on `class_replacement_fresh` + `class_replacement_viva` |
