# Design — replacement-merge-one-table

Baseline: the frozen `proposal.md` (M1–M17, §6b data contract) + `explore-brief.md` + the approved
prototype `../ui-prototype/replacement-merged-preview.html`. Every code claim below was re-read at
head `bcab5f5` on 2026-11-04; line numbers are that head's.

**Precedence.** M1–M17 are the user's; this document does not re-open one of them. Where a decision
below is *not* in the proposal, it is marked **[D]** (design decision) so `tasks.md` and the
apply-round can tell the two apart.

---

## 1. The payload (`app/Livewire/ReplacementHome.php`)

### 1.1 What the row set is (unchanged), and why it is closed

The page lists **conflicted occurrences** exactly as today: one session × one week, emitted only when
an exception or a holiday exists for that cell (`render():93-111`, `continue` at `:111`). M1 adds a
row's *request* to that row; it does not add rows.

**[D] Invariant, stated so nobody "fixes" it later:** a request can only be created from a conflicted
occurrence (the arrangement page is reached from this table), `ApproveRequest::releaseOriginal()`
stamps `official_event` when the occurrence had no exception, and nothing deletes the exception
afterwards. So every live request has a row. A request on a **non-conflicted** occurrence is
unreachable through the UI and renders no row — that is not a bug, and the payload must not widen to
"all requests" (a row with no conflict reason would break M4's fourth column). Tested explicitly
(§8.1 case 7).

### 1.2 The newest request per occurrence (widen the select, additively)

```php
$latestRequests = ReplacementRequest::query()
    ->with(['replacementTimeSlot.venue'])
    ->whereIn('class_session_id', $sessions->pluck('id'))
    ->orderBy('submitted_at')->orderBy('id')          // last one written wins = newest (§6b)
    ->get([
        'id', 'class_session_id', 'week_number', 'status', 'submitted_at',
        'replacement_time_slot_id',                    // effectiveStatus + span fallback — NEVER dropped
        'remarks', 'rejection_reason',                 // new — the two rows M8 keeps distinct
    ])
    ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);
```

Three columns join the select; nothing is removed. The comment at `:75-79` explains why the FK must
stay — keep it, and add one line saying `remarks`/`rejection_reason` are display-only.

`submitted_at` is `NOT NULL` (`2026_08_03_000009_create_replacement_requests_table.php:26`), so the
ASC order has no null case; §6b records the `nulls first` guard should that ever change.

### 1.3 The batch span path — two reads, no per-row query

```php
$mapper   = app(ReplacementRequestMapper::class);
$spans    = $mapper->spansForRequests($latestRequests->pluck('id')->all());   // 1 read (junction)
$venueIds = collect($spans)->pluck('venue_id')->unique()->values()->all();
$venues   = $venueIds === []
    ? collect()
    : \App\Models\Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id'); // 1 read
```

Per row (§6b law 3, both steps, in this order):

```php
$span = $spans[$latest->id]                                   // step 1 — junction, min→max
    ?? $mapper->spanFromAnchor($latest->replacementTimeSlot);  // step 2 — the eager-loaded anchor
$venue  = $span === null
    ? null
    : ($venues->get($span['venue_id']) ?? $latest->replacementTimeSlot?->venue);
$strings = $mapper->stringsFromSpan($span, $startDate, $venue);
```

**Why the fallback is not optional:** `spansForRequests()` reads `replacement_request_slots` only
(`ReplacementRequestMapper.php:333-345`) and the junction is DELETEd by `CancelPendingRequest.php:86-88`
and `RejectRequest.php:132-134`. Without step 2, every cancelled/rejected request that *did* have a
slot prints `—` and hides its Proposed Replacement Schedule tab — inventing absence (§6b law 6).

**Mapper change (additive, behaviour-preserving):**

```php
/** span()'s step 2, extracted so the batch caller reuses the body instead of copying it. */
public function spanFromAnchor(?TimeSlot $anchor): ?array
{
    if ($anchor === null) return null;
    return [
        'week' => (int) $anchor->week_number, 'day' => (int) $anchor->day_of_week,
        'venue_id' => (int) $anchor->venue_id,
        'start' => (string) $anchor->start_time, 'end' => (string) $anchor->end_time,
        'count' => 1,
    ];
}
```

and `span()` (`:206-238`) ends with `return $this->spanFromAnchor($r->replacementTimeSlot);` instead
of its inline block. Same output, byte for byte — the existing `SpanReservationTest` receipts must stay
green, which proves it.

### 1.4 `effectiveStatus()` gains the already-computed date

```php
public function effectiveStatus(ReplacementRequest $r, Carbon $semesterStart, ?string $replacementDate = null): string
{
    $stored = (string) $r->status;
    if ($stored !== 'approved') return $stored;

    $date = $replacementDate ?? $this->replacementStrings($r, $semesterStart)['date'];
    ... // unchanged
}
```

The default keeps every existing caller identical (`toRow():131`, the API, the PL queue). The merged
payload passes `$strings['date']`, so **one** derivation still decides `completed`, and it costs no
query. §6b law 2 holds; law 5 holds (`toRow()` is untouched).

### 1.5 The request block — the row's new keys

Added to `$rows[]` (existing keys untouched — A′ reads `lecturer`, `sessionId`, `week`, `id`):

| Key | Value |
|---|---|
| `requestStatus` | `$mapper->effectiveStatus($latest, $startDate, $strings['date'])` or `null` |
| `requestStatusLabel` | `$mapper->statusLabel($status, $strings['date'])` |
| `requestStatusShort` | `$mapper->statusShort($status)` — the tab label |
| `requestStatusDesc` | `$mapper->statusSentence($status, $strings['date'])` |
| `requestStatusTip` | `$mapper->statusTip($status)` — the badge `data-tip` |
| `replacementDate` | `$strings['date']` (`Y-m-d`) or `null` |
| `replacementTime` | `$strings['time']` (`'14:00 – 16:00'`) or `null` |
| `replacementWeek` | `$strings['week']` or `null` |
| `replacementDay` | `$strings['day']` or `null` |
| `replacementVenue` | `$strings['venue']` (code) or `null` |
| `replacementVenueName` | `$strings['venueName']` (`room_name`, may be `null`) or `null` |
| `remarks` | `$latest?->remarks` |
| `rejectionReason` | `$latest?->rejection_reason` |
| `requestId` | `$latest?->id` (unchanged) |

All 14 are `null` when `$latest === null` — never `''`, never `"—"` (the client prints the dash, §6b
law 6). `duration` stays the **original** class duration (`class_sessions`), as today.

**[D] The JSON does not carry a request formula.** No `completedAt`, no `daysAgo`: the label is
server-built (§1.6) so the badge's "n days ago" can never disagree with the status that produced it.

### 1.6 The status vocabulary — one map, in PHP, for all six states

Added to `ReplacementRequestMapper` (pure, no queries):

```php
public function statusLabel(?string $status, ?string $date): string   // the badge text
public function statusShort(?string $status): string                  // 'Pending' | 'Completed' | ...
public function statusSentence(?string $status, ?string $date): string// the Status Description row
public function statusTip(?string $status): string                    // data-tip
```

| status | label (M5) | short | sentence |
|---|---|---|---|
| `null` | `Need to Arrange` | `Need to Arrange` | `No replacement has been proposed for this class yet.` |
| `pending` | `Pending — Waiting for Approval` | `Pending` | `The proposed replacement is waiting for the PL to approve or reject it.` |
| `approved` | `Approved` | `Approved` | `The PL approved the proposed replacement, so this class will run at the new time.` |
| `rejected` | `Rejected` | `Rejected` | `The PL declined the proposed replacement, so a different slot has to be arranged.` |
| `cancelled` | `Cancelled by YOU` | `Cancelled` | `You cancelled this request, so the class is free to be arranged again.` |
| `completed` | `Completed — <n> days ago` | `Completed` | `The replacement took place <n> days ago.` (or `today.`) |

`<n>` uses the **server's** `today()`, the same clock `effectiveStatus()` compares against:

```php
$days = (int) abs(Carbon::parse($date)->startOfDay()->diffInDays(today()->startOfDay()));
$ago  = $days <= 0 ? 'today' : $days === 1 ? '1 day ago' : $days.' days ago';
```

`effectiveStatus` only says `completed` when the replacement date is strictly before today, so `n ≥ 1`
on a completed row; the `today` branch is the guard, not a state. **Why server-side:** a browser in
another timezone would otherwise print "Completed — Today" beside a `completed` badge, or vice versa —
§6b law 2 forbids the page deciding anything about status, including its suffix.

The descriptions resolve for **all six** states (§6b: the old `statusDesc()` returned `''` outside its
five keys and would print an empty row for `Need to Arrange`).

### 1.7 The one shaping path, and the re-read after a cancel

`render()`'s loop body moves into a private method, and a second caller uses it:

```php
/** @return array<string,mixed>|null  null only when the occurrence is not conflicted */
private function buildRow(
    ClassSession $session, int $week,
    ?ClassException $exception, ?array $holiday,
    ?ReplacementRequest $latest, array $spans, \Illuminate\Support\Collection $venues,
    Carbon $startDate
): ?array
```

`render()` calls it per cell (§1.1–1.5). The cancel path calls it for one cell:

```php
public function cancelRequest(int $id): array
{
    $ok = app(CancelPendingRequest::class)(auth()->user(), $id);   // the ONLY write — unchanged

    if (! $ok) return ['ok' => false, 'row' => null];

    $request = ReplacementRequest::query()
        ->with(['classSession.module', 'classSession.venue', 'classSession.cohorts.programme',
                'classSession.lecturer', 'replacementTimeSlot.venue'])
        ->find($id);

    return ['ok' => true, 'row' => $request === null ? null : $this->rowFor($request)];
}
```

`rowFor()` re-loads that occurrence's exception/holiday, runs the **same** batch reads for the single
request id (or `spanFromAnchor` when the junction is gone — it now is, because the action deleted it),
and returns the freshly built row. The client patches that row and repaints (§4.4).

**[D] Why a returned row and not `location.reload()`:** a reload loses the search/week/chips state and
the page position; the returned row is the server's own answer, so §6b law 4 ("re-read, never assume")
is satisfied with one round trip. Why not a local `status = 'cancelled'` splice: that is exactly the
assumption law 4 forbids — and it is how the anchor-only `14:00 – 14:30` (the post-cancel truth) would
be missed.

### 1.8 Query budget (the no-N+1 receipt)

| Read | Count |
|---|---|
| `sessions` + module/venue/cohorts/lecturer | 1 (existing) |
| `class_exceptions` | 1 (existing) |
| `latestRequests` (+ eager `replacementTimeSlot.venue`) | 1 + 2 (existing) |
| `spansForRequests` | **1, any number of rows** |
| `venues` for the spans | **1, any number of rows** |

Constant with 1 request or 40. `effectiveStatus()` adds **zero** (the date is passed in). Today's page
adds 2 per approved row through `span()` + the `TimeSlot → venue` lazy load — the merge removes that.
§8.1 case 8 asserts the constant with `DB::listen`.

---

## 2. The component, the retired page, the routes

### 2.1 `ReplacementHome` is the merged page — moves, not copies

| Today | After |
|---|---|
| `ReplacementHome::render()` — conflicted classes only | the merged payload (§1) + `cancelRequest()` |
| `MyRequestHistory::render()` — one row per request | **retired** |
| `replacement-home.blade.php` — 9-column table, summary, quick view | rewritten: 7 columns, chips, cards, pagination, tabbed modal, confirm overlay |
| `my-request-history.blade.php` — the machinery the merged page needs | its *markup and behaviour* move into the rewritten view, then it is deleted |

**Moved into the merged view, verbatim where possible** (M10 "reuse, never a second version"): the
toolbar (`#searchInput`, `#statusFilter`, `ui-week-nav`, `#hideCompleted`, `#resetFilters`,
`#resultCount`), `#filterChips` + `saveFilters`/`restoreFilters`/`renderFilterChips`/
`resetAllFilters`/`clearAllFilters`, `#paginationBar`/`#paginationInfo`/`#paginationControls` +
`initRpp`, `#cancelConfirmOverlay` (markup at `my-request-history.blade.php:275-294`) and its
`openCancelConfirm`/`closeCancelConfirm`, `jumpToRequestRow` (the M16 reveal law, `:403-437`).

**Not moved:** `#modalOverlay`/`#detailsModal` (M7 — `#quickViewModal` survives), `#batchCancelOverlay`
+ `batchCancelSelected` + `BulkSelection` (M12), the `sort-hint` line, the timeline
(`DetailModal.timeline` is not called anywhere by the merged page).

`render()` keeps `->extends('layouts.ui-template', ['homeUrl' => '/my-timetable-ui', 'activeNav' =>
'replacement-arrangement', 'pageKey' => 'myReplacements'])`. `pageKey` changes from
`replacementHome` → `myReplacements` because it only drives scroll restoration (`ui-template:60-61`),
and this is a different page; **[D]** the key is new so a stale scroll offset from the old page is not
restored.

### 2.2 `MyRequestHistory` retires — no forwarder

The class and its view are **deleted**. Nothing else in `app/`, `routes/` or `resources/` references
`MyRequestHistory` after §2.3; the only references left are tests, which §2.5 re-points or rewrites in
the same commit. A forwarder would keep a second payload shape alive for no caller (proposal §3 item 6
allows either; this is the decision).

### 2.3 `routes/web.php` — three entries, one component, no redirects

```php
'/my-replacements' => [
    'component' => 'App\Livewire\ReplacementHome',
    'legacy' => 'ui-design-templates.replacement-home-UI-design-template',
    'nav' => 'replacement-arrangement',
    'mw' => ['auth', 'role:lecturer'],
],
'/replacement-home-ui' => [ /* same three keys, nav => replacement-arrangement */ ],
'/my-request-history-ui' => [ /* same, nav => 'replacement-history' (M3: harmless) */ ],
```

`/my-request-history-ui` replaces the `MyRequestHistory` entry (`:66-71`, now `:56,68` after the URL
edit). **All three in the same edit as the class deletion** — `routes/web.php:88-93` silently falls
back to `view($page['legacy'])` when `class_exists($page['component'])` is false, so a leftover
`MyRequestHistory` entry would render the frozen legacy template with no error and the alias specs
would pass against it. `RouteGateMatrixTest` gains `/my-replacements` (§8.2).

### 2.4 The nav — two items, one

`resources/views/partials/ui-nav-bar.blade.php:18-19`:

```php
['key'=>'replacement-arrangement','label'=>'My Replacements','href'=>'/my-replacements'],
// the 'replacement-history' / Request History line is deleted
```

The arranging page keeps `nav => replacement-arrangement`, so it highlights the same item (M3). The
student branch (`:12-15`) is untouched.

### 2.5 Test re-pointing, file by file (proposal bucket C, plus the two aliases)

| File:line | Today | This lane |
|---|---|---|
| `Feature/ReplacementHistoriesTest.php:178-190` (`mrhPayload`) | `Livewire::test(MyRequestHistory::class)`, `payload['requests']` | `Livewire::test(ReplacementHome::class)`, `payload['conflictedClasses']` |
| `:206-229` (mock-shape test) | request-row keys (`requester`, `courseCode`, `replacementDate`, …) | rewritten to the **occurrence row**: `code`, `cohorts`, `conflictReason`, `requestId`, `requestStatus`, `replacementDate`, `replacementTime`, `replacementVenue`. The fixture must also stamp the `ClassException` (a request alone no longer produces a row — §1.1) |
| `:250-290` (cancel persists / refuses decided) | `call('cancelRequest')` → bool | `call('cancelRequest')` → `['ok'=>…, 'row'=>…]`; assert `ok`, the DB status, the D6 release, the audit row — and (new) `row['requestStatus'] === 'cancelled'` + `row['replacementTime'] === '14:00 – 14:30'` (the anchor-only truth) |
| `:250-290` `test_batch_cancel_returns_only_the_cancelled_ids` | `cancelRequests()` | **deleted** — M12 removes bulk cancel; named replacement: the per-request cancel test above plus `request-cancel-pending.spec.ts` (§8.2) |
| `:346-380` (completed / boundary) | `payload['requests']`, `status === 'Completed'` | `payload['conflictedClasses']`, `requestStatus === 'completed'`, `requestStatusLabel` starts with `Completed — `; the boundary test keeps asserting `approved` for today/tomorrow |
| `Feature/SpanReservationTest.php:454-476` | `app(MyRequestHistory::class)->render()`, `payload['requests']`, `status === 'Pending'` | `app(ReplacementHome::class)->render()`, `payload['conflictedClasses']`, `requestStatus === 'pending'`; **the assertions stay**: `14:00 – 16:00` (junction) and `14:00 – 14:30` after cancel. This is the lane's data-accuracy receipt for §6b law 3 |
| `e2e/history.spec.js:6` | `checkPage('/my-request-history-ui', …, 'My Request History')` | rewritten as the **alias contract**: same URL, `#timetable`, title `My Replacements` — proves M2 without a redirect |
| `e2e/smoke-my-request-history.spec.js:6,10-16` | title + `window.MockData.requests.length >= 3` | title `My Replacements`; `window.MockData.conflictedClasses.length >= 1` (the merged table is occurrence-keyed), plus ≥1 `#tableBody tr` |
| `e2e/auth-wiring.spec.js:294` | title `'My Request History'` | `'My Replacements'` (the `/replacement-home-ui` entry `:293` already matches `Replacement`) |
| `e2e/full-suite.spec.js:453-472` | MRH describe: `MockData.requests`, `.request-card` | URL `/my-replacements`; `MockData.conflictedClasses`; `#cardView .replacement-card` |
| `:645` | MRH search interaction | URL `/my-replacements` |
| `:692` | `{ path:'/my-request-history-ui', title:/Request History/ }` | two rows: `{ path:'/my-replacements', title:/My Replacements/ }` and `{ path:'/my-request-history-ui', title:/My Replacements/ }` |
| `:688-700` title-case statuses | `MockData.requests` statuses | `conflictedClasses` → `requestStatusLabel`, asserting each is one of the six M5 strings (or `Need to Arrange`) |

Bucket **B** and **D** are updated with this lane too (the behaviour they pin is what M6/M7/M12/M15
change): `replacement-home-status-badges.spec.ts`, `request-completed-status.spec.ts`,
`status-tooltips-filter-chips.spec.ts` (B); `request-cancel-pending.spec.ts:68-89`,
`warning-keywords.spec.ts:13-24`, `ui-regression.spec.ts:151` (D). Their new assertions are listed in
§8.2.

---

## 3. The table

### 3.1 Columns (M4) and headers

```html
<th class="sortable" data-sort="code"     data-tip="Course affected by the conflict">Course Code &amp; Name</th>
<th class="col-cohort"                    data-tip="Affected cohorts — not sortable: a multi-cohort row has no single value">Cohort</th>
<th class="sortable" data-sort="date"     data-tip="The class that cannot run as timetabled">Original Class</th>
<th class="sortable" data-sort="reason"   data-tip="Why this class cannot run">Conflict Reason</th>
<th class="sortable" data-sort="replacementDate" data-tip="The new slot proposed for this class">Requested Replacement</th>
<th class="sortable" data-sort="status"   data-tip="Where the request stands — the newest request wins">Request Status</th>
<th                                      data-tip="What you can do with this class">Action</th>
```

The `<thead>` lives in the blade (not `ui-common`), so the merged page owns its own header row; the
prototype's `data-label` attributes are used to repaint the arrow.

### 3.2 `HtmlBuilder.replacementHomeRow` — an explicit merged branch

`ui-common.js:2481-2506`. **Nothing existing changes**: the function returns today's 9 cells unless
`opts.merged === true` (the frozen `ui-design-templates` copies pass no such opt and keep their
output byte for byte, `mock_fallback` off by default).

```js
static replacementHomeRow(c, opts) {
    if (!opts.merged) { /* …today's body, untouched… */ }
    return [
        { html: '<span class="cell-code">' + c.code + '</span>' +
                '<span class="cell-name">' + c.name + ' <span class="cell-type-label">(' + typeLabel + ')</span></span>',
          cls: 'col-code' },
        { html: c.cohorts.join('<br>'), cls: 'col-cohort' },                       // M4: one per line
        { html: opts.formatClassBlock(c), cls: 'col-original' },                   // page-owned
        { html: opts.reasonCell(c), cls: 'col-reason' },
        { html: opts.requestCell(c), cls: 'col-requested' },
        { html: opts.statusCell(c), cls: 'col-status' },
        { html: opts.actionCell(c), cls: 'col-action' },
    ];
}
```

The same `opts` are passed to `replacementHomeCard` (unchanged signature + `statusBadge`), so the
mobile card and the table cannot drift.

### 3.3 The four page-owned cells

- **Original Class** — `formatClassBlock(c)` for the merged page:
  `<div class="cell-class-block">` day, date, `(Week n)` + the days-left span (em dash when handled) ·
  `time to time` `(n hrs)` · the venue line `<span class="cell-sub">` + room icon + code.
  Days-left is inline text, not a column (M4) — no `.pv-date-line` wrapper is needed.
- **Conflict Reason** — `badgeClass(reason)` collapses to **two** classes: `badge-holiday` (public
  holiday) and `badge-reason` (everything else, neutral grey). The ten page-local classes are deleted.
- **Requested Replacement** — `—` (`<span class="cell-dash">`) when `replacementDate == null`; else day,
  date, `(Week n)`, `replacementTime`, `(n hrs)` computed from the string, venue line
  (`replacementVenue` + `replacementVenueName` in the modal only — the column prints the code, §6b).
- **Request Status** — `<span class="badge status-<key>" data-tip="<requestStatusTip>">` +
  `requestStatusLabel`; `status-none` + `badge-outline` + `Need to Arrange` for `null`. **Not a link**
  (M6): no `<a>`, no `onclick`.
- **Action** — icon-only `<button class="btn-icon btn-icon--…">`, `stopPropagation` on click:
  | status | icon | class | action |
  |---|---|---|---|
  | `pending` | ban | `btn-icon--ghost btn-icon--danger` | `openCancelConfirm(c.requestId, c.id)` |
  | `approved` / `completed` | eye | `btn-icon--ghost` | `quickViewByRow(c)` |
  | null / `rejected` / `cancelled` | rotate | `btn-icon--primary` | `goToReplacementFor(c.sessionId, c.week, 'replacement-home')` |

  Each carries `data-tip` + `aria-label` (M6's wording: "Arrange a replacement for this class" /
  "Cancel this request" / "View this request").

### 3.4 Grouping, sorting, and the payload's order

- Group: **needs action** (null / rejected / cancelled) before **handled** (pending / approved /
  completed) — M17. `isHandled(c) = !!c.requestStatus && c.requestStatus !== 'rejected' && c.requestStatus !== 'cancelled'`.
- Sort fields: `code`, `date` (original), `reason`, `replacementDate`, `status`; default
  `{field:'date', dir:'asc'}`. Direction applies **inside** the group (M14).
- **Status rank** (explicit, so the order is not the alphabet): `null 0 · rejected 1 · cancelled 2 ·
  pending 3 · approved 4 · completed 5`. `replacementDate` sorts `null` last (`'9999'`).
- **Tie-break: `a.id - b.id`** — without it two rows with the same key can swap between renders, and
  pagination would show a row twice or not at all.
- **[D] The payload is emitted in display order** (group, then `date` asc, then `id`). The A′ spec
  (`replacement-home-qv.spec.ts`) correlates the first rendered row with `conflictedClasses[0]`; the
  client's stable sort on an already-sorted array is a no-op, so that correlation survives the merge.
  Emitting the payload in some other order and hoping the client's sort matches is how A′ goes red.

Arrows: `<span class="sort-arrow">▲/▼</span>` appended to the **active** header only, repainted from
`data-label` on every render (M14; prototypes' `:773` markup, `.sort-arrow` from `theme.css:1137`).

### 3.5 Filters, chips, the two reset laws (M16/M17)

- Search: `code`, `name`, and **cohorts** — the query is compared both as typed and with whitespace
  removed against the spaced label (`RSD3(S1)G2` finds `RSD3 (S1) G2`).
- Status select values: `all · none · pending · approved · rejected · cancelled · completed`
  (`none` = Need to Arrange).
- Week navigator: `ui-week-nav` (`includeAll`), filtering on `getWeekNumber(c.date)`.
- `Exclude COMPLETED Status`: checked by default; `.info-keyword` on `COMPLETED` (M17).
- **Persistence [D]:** key `mrh-filters`, same object plus `week` — the merged page *is* the old
  My Requests destination, so a lecturer's saved filter must be reconciled here rather than orphaned
  (M16's "stale saved filter is exactly how an arrival silently skips"). Unknown/absent values fall
  back to defaults; `rpp-page-size` is reused unchanged.
- **Reset Filters** (`#resetFilters`) → page defaults: status `all`, week `all`, search empty,
  exclusion **ON**. **Clear all** (chip bar) → everything off, exclusion **OFF** (M17's two laws).
- Chips: Status · Week · Search · Exclude Completed, plus `Clear all` when any is active.

### 3.6 Counts, pagination, the 1500 px rule

`#resultCount` = `Showing N of M classes`, N = `currentFiltered.length`, M = owned rows before filters
(`myConflictedRows().length`). `#paginationInfo` = `Showing a-b of N`. Both come from the same
`currentFiltered` (§6b law 1); the five cards take it as an argument. `#resultCount` is hidden below
1500 px by a page media query (M15) — it stays in the DOM, so a `textContent()` read still returns
text (proposal bucket A's caveat).

### 3.7 Cards (phones) and the five summary cards

`#cardView` is filled with `HtmlBuilder.replacementHomeCard(c, opts)` in the same pass; the card is
clickable (row click) and shows `statusBadge` in its header (M10). The five cards use
`partials.ui-summary-bar` with M9's labels and the prototype's descriptions, values from
`currentFiltered`: Classes to Arrange = `!isHandled` count · Class Hours to Replace = the **same**
needs-action rows' `duration` sum · Pending Requests · Approved Requests · Rejected Requests.
`.card-conflict/.card-duration/.card-pending/.card-approved/.card-rejected` value colours come from
tokens (`--color-error/-primary/-warning/-success`), as the prototype's page-local rule shows; they
are page-local, not new theme definitions.

---

## 4. The modal (M7)

### 4.1 Shell and DOM contract

`#quickViewModal` (`.modal-overlay`, `onclick` on the overlay closes), title `Replacement Details` +
`.modal-subtitle` = `CODE · Name (Lecture)`, `#qvBody`, footer:

```html
<button class="btn-outline" onclick="hideQuickView()">Close</button>
<button class="btn-danger" id="cancelRequestBtn" style="display:none" onclick="openCancelConfirm(currentRequestId, currentOccurrenceId)">
  <svg …ban…></svg> Cancel Request
</button>
<button class="btn-action" id="qvArrangeBtn" onclick="qvArrange()">
  <svg …rotate…></svg> <span id="qvArrangeLabel">Arrange Replacement</span>
</button>
```

- `#cancelRequestBtn`: **static node**, `style.display` gate, `inline-flex` only when the row is
  `pending` (proposal 3b; today `my-request-history.blade.php:268,876`). Clicking it binds the *viewed*
  request id, or the footer click is a silent no-op.
- `#qvArrangeBtn`: hidden when handled, `inline-flex` otherwise; `qvArrange()` captures
  `sessionId`/`week` **before** `hideQuickView()` (the `qv-arrange-null-order-fix` lesson).
- Both `#qvArrangeBtn` and `#qvArrangeLabel` stay (4b + the A′ spec clicks the id).

### 4.2 Facts strip + three tabs

`DetailModal.render` gains **one optional key** (additive — no other caller passes it):

```js
// ui-common.js, DetailModal.render, immediately before the tabs block:
if (cfg.factsHtml) html += '<div class="detail-facts">' + cfg.factsHtml + '</div>';
```

then the merged page renders:

```js
DetailModal.render({
  modalId: 'quickViewModal',
  title: 'Replacement Details',
  subtitle: c.code + ' · ' + c.name + ' (' + typeLabel + ')',
  factsHtml:
      '<span class="df-item"><span class="df-label">Cohorts</span>' + c.cohorts.join(', ') + '</span>' +
      '<span class="df-item"><span class="df-label">Students</span>' + c.totalStudents + '</span>' +
      '<span class="df-item df-item--full"><span class="df-label">Conflict</span>' + reasonBadge(c) + '</span>',
  tabs: [ … §4.3 … ]
});
```

Tabs (in this order):

1. **Original Class Schedule** — `Class ID #<sessionId>` · Week · Day (long) · Date (long) · Time
   `HH:MM ~ HH:MM` · Duration · Venue `CODE — Name` (name nullable → code only).
2. **Proposed Replacement Schedule** — **omitted entirely when `replacementDate == null`** (M7: "hidden
   when nothing is proposed"). When present: `Request ID #R<requestId>` · Week · Day · Date · Time ·
   Duration (from the span's time strings) · Venue.
3. **Request Status (`requestStatusShort`)** — Status badge · Status Description · **Rejection Reason**
   (**only** when `requestStatus === 'rejected'`; `detail-value--strong`) · **Remark** (omitted when
   empty). No timeline, no history block (M7/M8).

Every row uses `DetailModal.row(...)`, which omits `null`/`''` values — §6b law 6, structurally.

### 4.3 A row with **no** request

Tab 1 renders normally; tab 2 is absent; tab 3 shows `Need to Arrange` + `No replacement has been
proposed for this class yet.` — and no Rejection Reason, no Remark. §6b requires the tab to resolve, so
it is always present.

### 4.4 Cancel flow

1. Row ban icon **or** footer `#cancelRequestBtn` → `openCancelConfirm(requestId, occurrenceId)`:
   `#cancelConfirmOverlay` becomes `display:flex` and `#cancelConfirmBody` is filled with the class
   summary (course, original class date, current badge) — the history page's markup and copy move here
   verbatim (`:275-294`, `:676-690`).
2. `#confirmCancelAction` → `pageLivewire().call('cancelRequest', id)`.
3. On `ok`: patch that occurrence's row from `result.row`, close **both** overlays
   (`closeCancelConfirm()` + `closeModal()`, `my-request-history.blade.php:703-705`), repaint, toast
   `Request #<id> cancelled.` (`toast.show`). On `!ok`: close both, toast
   `This request can no longer be cancelled.`, leave the row as the server has it.
4. A double-click guard: the button is disabled while the call is in flight (the history page's
   guard, kept).

The badge therefore always shows what the database says (§6b law 4); after a cancel the row's
`replacementTime` becomes the anchor-only range — the same truth `SpanReservationTest` pins.

### 4.5 The arrival law (M16)

On load, one block after the first render:

- `?notif-hl=r<requestId>` → find the row by `data-request-id` (the merged row emits **both**
  `data-id` = occurrence and `data-request-id` = request, proposal item 10), then the M16 reveal:
  - hidden **only** by `Exclude COMPLETED Status` and the row is `completed` → untick, save, rebuild,
    then jump;
  - hidden by any other filter → toast `That request is hidden by your current filters.` and change
    nothing;
  - visible → jump to its page and let `applyNotifHighlight` pulse it.
- `&view=request` (producer: "View Full Request", §5.3) → additionally **open that row's modal**, looked
  up in `payload.conflictedClasses` (not the DOM), even if a filter currently hides the row. The bell
  carries no `view` marker, so it keeps today's jump-and-pulse and M16's toast is never contradicted by
  a modal showing the same record.

`applyNotifHighlight` itself is the shared helper and is not re-implemented (§5.2).

---

## 5. `public/js/ui-common.js` — four additive edits

### 5.1 `replacementHomeRow` merged branch — §3.2.

### 5.2 `applyNotifHighlight` fallback selector (proposal 10b)

`tryFind($sel, 14, $fallbackSel)` resolves as `querySelector($sel) || querySelector($fallbackSel)`.
**Never** a comma-joined selector list: a selector list resolves by document order, so a small request
id (`r5`) could match a `tr[data-id="5"]` before the intended `tr[data-request-id="5"]`.
`data-request-id` is tried first, `data-id` second (rows on `/request-approval-ui` carry no
`data-request-id`, so their behaviour is unchanged). Same 14×250 ms retry, same single pulse, same
silent skip, same strip list (`:324-327`).

### 5.3 `NotificationRoutes` / `#routes` values + the "View Full Request" producer

- `:3071-3079` — **the two URL values and the default only**: `replacement-home` and
  `my-request-history` → `/my-replacements`. The `#routes` **keys** and every `from=` producer stay
  (~14 specs pass `from=replacement-home`; a renamed key silently falls through to the default).
- `:1292` — "View Full Request" → `/my-replacements?notif-hl=r<requestId>&view=request` (it used
  `?id=`, whose reader retires with My Requests — M2 item 7b).
- `:4420,4423` — the entry-guard toast links **and** labels: "My Request History" → "My Replacements".

### 5.4 Untouched in this file

`replacementHomeCard` (M10 reuses it), `statusClass()` (title-case map, used by request-approval), the
timeline, `BulkSelection` (still used by other pages), `DetailModal` except the one `factsHtml` line.

---

## 6. CSS — every prototype class mapped

The proposal's item 9 names three missing definitions. The approved picture needs two more, both
one-liners; they are listed here so the count is not a surprise, and every one is token-only (no hex).

| Prototype class | App target | Where |
|---|---|---|
| `.pv-icon-btn`, `--primary`, `--ghost`, `--danger` | **`.btn-icon`** (global base, 34 px, `--radius-md`) + `--primary/--ghost/--danger` modifiers | `theme.css` (item 9a). The existing mobile rule (`:3326`) still applies its 48 px touch target — the base must not set `min-height` |
| `.pv-reason-cancelled` | **`.badge-reason`** — `surface-variant` / `on-surface-variant` | `theme.css` (9b) |
| `.badge-holiday` | same name, moved out of the page's local block | `theme.css` beside `.badge-reason` (9b, the holiday exception) |
| `.pv-status-none` | **`.badge-outline`** — transparent + 1 px `on-primary-container` | `theme.css` (9c) |
| `.pv-cell-sub` | **`.cell-sub`** — 12 px muted inline-flex with a 12 px icon | `theme.css` (4th; used by both schedule cells) |
| `.detail-facts`, `.df-item`, `.df-item--full`, `.df-label` | same names | `theme.css` (5th; the M7 facts strip — `DetailModal` has no facts slot today) |
| `.pv-dash`, `.pv-dash--faint` | **`.cell-dash`** (`on-surface-variant`, `opacity:.6`) | `theme.css` (same block as `.cell-sub`) |
| `.pv-date-line` | *not needed* — days-left is inline text in the block (§3.3) | — |
| `.pv-icon-btn--danger:hover`, etc. | token pair only | — |
| `.status-*` | **already in `theme.css:3631-3651`** | reuse |
| `.urgency-*`, `.cell-class-block`, `.class-day-date`, `.class-time`, `.class-duration`, `.badge`, `.modal-tab`, `.detail-row`, `.detail-group`, `.filter-chip*`, `.pagination-*`, `.summary-*`, `.modal-subtitle`, `.card-view` | existing | reuse |

Deleted from `replacement-home.blade.php:8-300`: the ten `badge-*` reason classes, `.badge-link`,
`.action-stack`, the column-width rules for the removed columns; `.just-cancelled-chip` and the
`ClassCancellation` ledger wiring stay (a different feature — the chip's render block is already
commented out at `:538-570` and stays that way).

**[D] Column widths for the 7-column table:** `.col-code` flexible · `.col-cohort 130px` ·
`.col-original 210px` · `.col-reason 150px` · `.col-requested 200px` · `.col-status 190px` ·
`.col-action 70px`; `min-width` on `.grid-scroll .timetable` raised so the seven columns never crush
(the current `1120px` was for nine).

---

## 7. The URL sweep — the atomic commit

The route table + these **six live files, 16 line occurrences** land together (proposal §7):

| File | Lines | Change |
|---|---|---|
| `routes/web.php` | `:56`, `:68` (+ the new canonical entry) | three entries, §2.3 |
| `app/Livewire/ReplacementArrangement.php` | `:78`, `:79`, `:201` | `FROM_LANDING_ROUTES` values + the `?? '/replacement-home-ui'` default → `/my-replacements` |
| `resources/views/livewire/replacement-arrangement.blade.php` | `:2241`, `:2245` | post-submit toast link + `location.href` |
| `public/js/ui-common.js` | `:3071`, `:3072`, `:3079`, `:4420`, `:4423`, `:1292` | §5.3 |
| `resources/views/partials/ui-nav-bar.blade.php` | `:18`, `:19` | one item, §2.4 |
| `resources/views/livewire/replacement-home.blade.php` | `:376` (`requestHref()`) | **deleted** by M6, not moved |

**Frozen — not touched:** `NotificationEmitter.php:64,88`, `partials/notif-row.blade.php:96` (compared
byte for byte), the seven `mock-data.js` fixtures, and
`ui-design-templates/replacement-arrangement-UIdesign-template.blade.php:1916` (the `mock_fallback`
copy, off by default).

Order inside the commit: (1) the three route entries, (2) the six live files, (3) delete
`MyRequestHistory` + `my-request-history.blade.php`, (4) the test re-pointing of §2.5 that names the
class. Any other order leaves a window where a URL names a deleted class (silent legacy render) or a
test names a deleted component.

---

## 8. Tests

### 8.1 New — `tests/Feature/MyReplacementsPayloadTest.php` (the user's bar, in assertions)

1. One row per occurrence; the newest request wins (`submitted_at, id`), a second request on the same
   occurrence does not add a row.
2. No request → `requestStatus === null`, label `Need to Arrange`, sentence non-empty, all replacement
   fields `null`, `remarks`/`rejectionReason` `null`.
3. Each of the six states → label / short / sentence / tip from §1.6 (data-provider).
4. `completed` label is `Completed — 1 day ago` for a replacement yesterday, and the status stays
   `approved` for today and tomorrow (the boundary, moved from `ReplacementHistoriesTest:346-380`).
5. **Span accuracy:** a 4-cell junction → `14:00 – 16:00`; after `CancelPendingRequest` the same row
   → `14:00 – 14:30` (anchor only) and `requestStatus === 'cancelled'`. This is §6b law 3's receipt.
6. `remarks` and `rejection_reason` are independent: a rejected row with a remark shows **both**; an
   approved row with a remark shows only the remark.
7. A request on a **non-conflicted** occurrence produces **no** row (§1.1's invariant, asserted so it
   is a documented property, not a surprise).
8. **No N+1:** with `DB::listen`, the number of `replacement_request_slots` / `venues` reads is **1 + 1**
   and the total row-loop query count is identical for 1 request and for 4 requests.
9. `cancelRequest()` returns `['ok'=>true, 'row'=>…]` with the fresh status, closes over the D6 release
   and the audit row (moved from `ReplacementHistoriesTest:250-290`), and returns `ok=false, row=null`
   for an approved request.
10. **All three URLs** (`/my-replacements`, `/replacement-home-ui`, `/my-request-history-ui`) render the
    merged component with an identical payload **and keep the query string**
    (`?notif-hl=r1&view=request` survives) — the M2 receipt.

### 8.2 Updated (buckets B and D — the behaviour the merge reverses)

| File | New assertion |
|---|---|
| `replacement-home-status-badges.spec.ts` | row extraction moves from `a.badge-link` / `.col-urgency` to `.col-status .badge` + `.col-original`; receipts assert the badge text (M5), the icon-only Action cell, and that the badge is **not** a link |
| `request-completed-status.spec.ts` | badge anchor → `.col-status .badge`; `tr[data-id]` now means the occurrence, the request is `tr[data-request-id]`; the toggle/chips/toast receipt keeps `mrh-filters` |
| `status-tooltips-filter-chips.spec.ts` | `MRH_MAP` replaced by the six server labels; `statusTooltip` read off `data-tip`; `#modalOverlay` → `#quickViewModal`; `.request-card` → `.replacement-card` |
| `request-cancel-pending.spec.ts:68-89` | the row **stays** after cancelling and reads `Cancelled by YOU`; the cancel is driven from the row's ban icon, then the confirm overlay |
| `warning-keywords.spec.ts:13-24` | opens from `.col-status .badge:has-text("Pending")`, still requires `#cancelRequestBtn` inside `#quickViewModal` |
| `ui-regression.spec.ts:151` | `#modalOverlay` → `#quickViewModal`, and the click target is `#tableBody tr` (not a desktop-hidden card) |

### 8.3 What cannot be proven without the testing DB

The Playwright suites are testing-DB gated (no `.env.testing`; only `DB_DATABASE=class_replacement_testing`
selects it) — on a bare run they *skip*, which is green-that-proves-nothing. So the apply receipt is:
PHPUnit green (`php artisan test`), plus a **manual** Playwright run on the testing DB for the merged
page's six states, and `arrangement-entry-guard` / `submission-conflict-refresh` for the URL moves
(proposal bucket A″).

---

## 9. Risks this design introduces (and the counter)

| Risk | Counter |
|---|---|
| A ledger-appended row (`ClassCancellation.applyLedger`) has no request block → `undefined` labels | the client's `statusText(c)` returns `Need to Arrange` when `requestStatusLabel` is absent; no JS status map is created |
| The payload's order is assumed by A′ | §3.4 emits in display order and the client sort is stable |
| `statusLabel()`'s "n days ago" disagrees with `effectiveStatus()` across a midnight boundary | both read `today()` in the same request; a completed row can only be ≥1 day old |
| The confirm overlay's summary is built from a stale row object | it reads the **current** row from the row set at open time, and the success path replaces it with the server's row |
| Deleting the ten badge classes breaks another page | they are page-local to `replacement-home.blade.php`; grep confirms no other view references them |
| `.btn-icon`'s new base fights the mobile 48 px rule | the base sets no `min-height`; the existing media query still wins (asserted by eye at ≤768 px) |

## 10. Apply plan (commits)

1. `feat(my-replacements): the merged payload, the batch span path, and the six-state vocabulary`
   — §1 + `MyReplacementsPayloadTest` (8.1) + `SpanReservationTest` re-point.
2. `ui(my-replacements): one table, seven columns, and the tabbed modal` — §3/§4 view rewrite +
   `ui-common` §5.1/§5.2 + `theme.css` §6.
3. `refactor(my-replacements): the canonical URL, three route entries, and the retired page` —
   §2.2–§2.5 + §7, atomic, plus the test re-pointing that names the class.
4. `test(my-replacements): the specs the merge reverses` — §8.2 + the remaining bucket A″ strings.
5. `docs(my-replacements): changelogs + handover` — `page-changelogs/` for the two old pages and the
   nav, `PI_Chat_Context_Handover.md` §0/§1/§4/§5.
