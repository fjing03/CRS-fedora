# Design — replacement-home-status-badges

Baseline: the frozen `proposal.md` (D1–D9) + the prompt's §6/§7 contracts, re-verified
against the code at head `068a5ed` on 2026-11-03.

---

## 1. The payload contract (`app/Livewire/ReplacementHome.php`)

### 1.1 Newest request per occurrence, every status

```php
$latestRequests = ReplacementRequest::query()
    ->whereIn('class_session_id', $sessions->pluck('id'))
    ->orderBy('submitted_at')->orderBy('id')
    ->get(['id', 'class_session_id', 'week_number', 'status', 'submitted_at'])
    ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);
// keyed map: the LAST row written wins = the newest request for that occurrence
```

Replaces the active-only `$activeRequests`, and the `continue` that skipped the row is
**deleted** (D1). The row is still built only when a reason exists (exception or holiday) —
the list semantics do not change, only what it does with an already-arranged occurrence.

### 1.2 Two additive row keys

```php
'requestId'     => $latest !== null ? (int) $latest->id : null,
'requestStatus' => $latest !== null ? (string) $latest->status : null,
```

Everything else stays byte-identical (row `id` = `sessionId * 100 + week`, the cohort
labels, `conflictReason`, …). No `completed` key, no derived state.

### 1.3 D9 — the reason precedence

```php
if ($exception !== null) {
    $reason = self::titleCase((string) $exception->reason);
    if ($holiday !== null && (string) $exception->reason === 'official_event') {
        $reason = 'Public Holiday';
    }
} elseif ($holiday !== null) {
    $reason = 'Public Holiday';
} else {
    continue;
}
```

Why: `ApproveRequest::releaseOriginal()` stamps an `official_event` exception when the
occurrence had none, so on a calendar-holiday occurrence the approval would rewrite the
visible cause. The stamp is a consequence of the arrangement, not the reason the class
moved. **Documented trade-off:** a lecturer who deliberately chose "Official Event" for a
week that is also a calendar holiday sees "Public Holiday" — the two cases reuse the same
enum value and cannot be told apart in the data.

## 2. The two shared builders (`public/js/ui-common.js`)

Both options are **optional**, so the legacy mock template (which calls the same builders)
keeps its old output — trap 7.

### 2.1 `replacementHomeRow(c, opts)`

```js
var handled = opts.isHandled ? opts.isHandled(c) : false;
var days = opts.daysLeft(c.date);
var daysText = handled ? '\u2014' : daysLeftLabel(days);           // D7
var action = opts.actionCell ? opts.actionCell(c) : '<button …Arrange…>';  // D1/D2
```

The urgency span/class is reused for the em dash (no new class, no colour invention); the
sort key stays in the page (`opts.daysLeft` still reads the real date — D7's "order does
not jump").

### 2.2 `replacementHomeCard(c, opts)`

`opts.statusBadge(c)` is inserted into `.rc-header` right after the reason badge; the days
pill shows `—` when `opts.isHandled(c)`. No other structural change.

## 3. The page script (`replacement-home.blade.php`)

### 3.1 Helpers (page-local, beside `badgeClass`)

```js
function isHandled(c) {
    return !!c.requestStatus && c.requestStatus !== 'rejected' && c.requestStatus !== 'cancelled';
}
function statusLabel(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function requestHref(c) { return '/my-request-history-ui?notif-hl=r' + encodeURIComponent(c.requestId); }
function statusBadge(c) { … '<a class="badge badge-link status-' + c.requestStatus + '" href="' + requestHref(c) + '" onclick="event.stopPropagation()" …>' … }
function actionCell(c) { handled → badge; else stack: [ended label] + ARRANGE_BUTTON_HTML }
```

`ARRANGE_BUTTON_HTML` is the existing button markup **copied verbatim** from
`ui-common.js` (`replacementHomeRow`'s Action cell, SVG included) with `__SID__` /
`__WEEK__` placeholders — copied, never retyped, so the two buttons cannot drift.

The badge is an **anchor** (navigation, keyboard, middle-click, real URL) and stops
propagation so the row's quick view does not also open.

### 3.2 D8 — the group key lives INSIDE the comparator

```js
filtered.sort(function (a, b) {
    var na = isHandled(a) ? 1 : 0, nb = isHandled(b) ? 1 : 0;
    if (na !== nb) return na - nb;          // needs-action first, regardless of asc/desc
    …the existing date / code / daysLeft / venue branches, unchanged…
});
```

A separate pre-sort is not enough: the column comparator would reorder across the groups.

### 3.3 Cards (D5)

Tap and keydown branch first: `isHandled(c) ? window.location.href = requestHref(c) : goToReplacementWith(...)`.

### 3.4 Quick view (D5)

- a **Replacement Status** row in the Status tab when `c.requestStatus` exists;
- the footer button's text moves into its own `<span id="qvArrangeLabel">` (the SVG stays);
- `quickView()` sets `View Request` for a handled row;
- `qvArrange()` branches first to the request URL (capture-then-navigate, no `hideQuickView`
  ordering trap).

### 3.5 The three strings (D4)

Subtitle, guide item and empty state exactly as the prompt proposes; the summary cards are
untouched.

### 3.6 Page-local CSS

`.badge-link { text-decoration: none; cursor: pointer; }` and
`.action-stack { display: inline-flex; flex-direction: column; align-items: flex-end; gap: 4px; }`
in `@section('page-styles')` — two rules, no theme.css change.

## 4. D6 — the page-jump (`my-request-history.blade.php`)

1. **Synchronous capture** at the top of the existing `DOMContentLoaded` handler, because
   `ui-common.js` strips `notif-hl` from the URL at 150 ms and MRH's first render sits
   behind a 400 ms skeleton (trap 4):
   ```js
   var pendingHl = null;
   var hlRaw = new URLSearchParams(location.search).get('notif-hl');
   if (hlRaw && hlRaw.charAt(0) === 'r') pendingHl = hlRaw.slice(1);
   ```
2. After the **first** render (`SkeletonLoader.with(...)` → `renderTable()`), call
   `jumpToRequestRow(pendingHl)` once. It finds the row index in `currentFiltered`, flips
   `pageState.currentPage` and re-renders; if the row is filtered out it shows the existing
   toast — never touching the user's filters (their state is theirs).
3. `applyNotifHighlight` and the bell are **not** touched (D6).

## 5. Traps (from the prompt §5, mapped)

| # | Trap | Where handled |
|---|---|---|
| 1 | another lane's dirty files, and the set moves | `git status` before every stage; only this lane's files |
| 2 | one-active-request is a DB partial unique index | display only — the index is untouched, no duplicate check added |
| 3 | a row exists only when the occurrence is conflicted | the payload keeps the `else { continue; }` |
| 4 | the highlighter strips `notif-hl` at 150 ms | §4.1 |
| 5 | the flash is 1.3 s | the spec asserts navigation first, the flash only if it lands inside the window |
| 6 | `_display` composition is not involved | no notification code touched |
| 7 | the legacy mock template shares the builders | both new options are optional |

## 6. Tests

### Feature (`tests/Feature/ReplacementHomeTest.php`)

| # | Test | Assertion |
|---|---|---|
| T1 | `test_home_keeps_occurrences_with_an_active_request_and_attaches_its_status` (rename of the old hide test) | the week-5 row is **present** with `requestStatus='pending'` + its `requestId`; after `rejected` → same row, `requestStatus='rejected'` |
| T2 | `test_home_prefers_the_public_holiday_reason_over_the_official_event_stamp` | `official_event` exception + holiday on the same week/day + an approved request → `conflictReason === 'Public Holiday'` |
| T3 | `test_home_reports_the_newest_request_for_the_occurrence` | one rejected, then one pending on the same occurrence → `requestStatus='pending'` with the pending id |
| T4 | `requestId`/`requestStatus` are `null` on a row with no request | assertion inside T1 or its own case |

### Browser (`tests/replacement-home-status-badges.spec.ts`, NEW, read-only)

Fixtures pinned from the **live demo DB** (no writes): request `26` = PENDING for
class_session `40` (AMSE2003 P, Thu) occurrence week **14**, where W14 Thu is the Christmas
Eve holiday → a handled row with a Pending badge; the W8 Monday Deepavali rows carry no
request → Arrange buttons.

1. the handled row renders a `Pending` **link** and **no** Arrange button;
2. clicking it lands on `/my-request-history-ui`; the matching row is reachable (the 1.3 s
   flash is asserted only if it lands inside the window — trap 5);
3. Days Left on the handled row is `—` (the approved case shares this code path; the demo
   DB has no approved row, so the receipt names the pending row and says so);
4. handled rows appear **after** rows that need action;
5. at 390 px the card shows the badge and tapping it navigates to MRH;
6. the quick view on a handled row: footer reads **View Request**, and a **Replacement
   Status** row exists.

**The page-jump's page-2 receipt needs >10 rows for one lecturer.** The demo DB gives this
user four requests — not enough. The changelog will say plainly that the page-2 jump is
**not** automatically receipted, and that the navigation + highlight path (the reachable
half) is.

## 7. Gates

`composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` (**never**
`types:check`) · full `php artisan test` on `class_replacement_testing` (312 before this
lane) · the new spec read-only on `:8000` via `scripts/dev-serve.sh 8000`.
