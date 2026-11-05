# Design — `mytimetable-jump-highlight`

Baseline: frozen `proposal.md`. Every edit site below is named with its current line so an implementer
does not have to re-explore.

## 1. Data flow

```
My Replacements row (payload)                 buildEventsByWeek()            browser
─────────────────────────                     ───────────────────            ───────
requestId, requestStatus,                     approved branch:               block: data-rid="<requestId>"
replacementWeek, sessionId, week      ──►     $ev['requestId'] = $r->id ──► (replacement events only)
                                                                                      │
modal / row button  ──► goToReplacementInTimetable(requestId, replacementWeek) ───────┤
        │                                                                             │
        └──► /my-timetable-ui?repl-hl=<rid>&repl-w=<week> ──► arrival arm ◄───────────┘
                                                              ├─ strip params (history.replaceState)
                                                              ├─ selectWeek(week − 1)   [page wrapper]
                                                              └─ tryFind('.event-block[data-rid="<rid>"]', 20)
                                                                   ├─ scrollIntoView({block:'center'})
                                                                   └─ flashRowPulse(el)  → .bookable-flash
```

## 2. Server — one additive key

**`app/Concerns/ResolvesTimetableTimeline.php`**, the approved branch of `buildEventsByWeek()`
(near `:325-333`, alongside `$ev['status'] = 'replacement';`):

```php
$ev['requestId'] = (int) $r->id;
```

- Additive: no existing key changes shape, no consumer breaks. The concern is shared by
  `CohortTimetable`, `VenueTimetable`, `StudentMyTimetable` and `MyTimetable`; the three browse pages
  ignore the key.
- Deliberately **only** on the approved landing event. The amber `buildAmberEvent()` path stays as it
  is: this lane navigates to a replacement that exists, never to a proposal.

## 3. Shared client — `public/js/ui-common.js`

### 3.1 `buildTimetableGrid()` (`:894-903`)

```js
if (e.requestId !== undefined && e.requestId !== null && e.requestId !== '') {
    div.dataset.rid = String(e.requestId);
}
```

Sits directly after the existing `div.dataset.sid = String(e.sessionId ?? e.id);` line, so a block can
carry both ids — `sid` for the notification arm (class identity), `rid` for this one (replacement
identity). Absent on every non-replacement block → the selector is unambiguous by construction.

### 3.2 Navigation helper (new, next to `goToReplacementFor()` `:1559`)

```js
function goToReplacementInTimetable(requestId, week) {
    if (!requestId) return;
    window.location.href = '/my-timetable-ui?repl-hl=' + encodeURIComponent(requestId) +
        (week ? '&repl-w=' + encodeURIComponent(week) : '');
}
```

One builder, two callers. Encoding is belt-and-braces (an int today).

### 3.3 Arrival arm (new, beside `applyNotifHighlight()` `:310`)

Registered in the same `DOMContentLoaded` block (`:303`, the existing `setTimeout(applyNotifHighlight, 150)`
tick) as `setTimeout(applyReplacementHighlight, 150)` — after each page's own first render pass.

```js
function applyReplacementHighlight() {
    const params = new URLSearchParams(location.search);
    const raw = params.get('repl-hl');
    if (!raw) return;
    params.delete('repl-hl');
    params.delete('repl-w');
    const qs = params.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));   // D5

    if (location.pathname !== '/my-timetable-ui' || typeof window.selectWeek !== 'function') return;

    const wk = parseInt(params.get('repl-w') || '', 10);
    if (!isNaN(wk) && wk >= 1 && wk <= 14) window.selectWeek(String(wk - 1));   // D6
    tryFind('#tableBody .event-block[data-rid="' + raw + '"]', 20);             // D7/D8 — no fallback
}
```

Notes for the implementer:

- **`params` is read before the deletes are applied to the query string** — `params.get('repl-w')` after
  `params.delete('repl-w')` would be `null`. Read `wk` **before** deleting, or capture it first. (Caught
  in self-review; the notif arm has the same shape because it reads `notif-w` *after* deleting and
  therefore always loses it — do **not** copy that bug into the new arm.)
- No fallback selector, ever (D7).
- `tryFind` already degrades silently after its budget.

## 4. My Replacements — `resources/views/livewire/replacement-home.blade.php`

### 4.1 ACTION column (`actionCell()` `:460-468`)

Add `CAL_SVG` beside `ROTATE_SVG`/`BAN_SVG`/`EYE_SVG` (`:456-458`, same 15 px / stroke 2.5 / round
caps voice — lucide `calendar`: `<rect x="3" y="4" width="18" height="18" rx="2"/>`,
`<path d="M16 2v4M8 2v4M3 10h18"/>`).

Approved / completed branch becomes **two** buttons, eye first (unchanged), then:

```js
'<button type="button" class="btn-icon btn-icon--ghost" data-tip="Go to My Timetable" ' +
'aria-label="Go to My Timetable" onclick="event.stopPropagation(); ' +
'goToReplacementInTimetable(' + c.requestId + ',' + c.replacementWeek + ')">' + CAL_SVG + '</button>'
```

`event.stopPropagation()` keeps the row-click (which opens the quick view) untouched. Pending and
"need to arrange" rows keep exactly one button.

### 4.2 Modal footer (`:288-299`) and its gates (`:838-845`)

New button **after** `#qvArrangeBtn`, so it is the last child — bottom-right under the footer's
`justify-content: space-between`:

```html
<button class="btn-action" id="qvTimetableBtn" style="display:none" onclick="qvGoToTimetable()">
    <svg …calendar…></svg>
    <span>Go to My Timetable</span>
</button>
```

Gate, beside the two existing ones:

```js
var ttBtn = document.getElementById('qvTimetableBtn');
if (ttBtn) ttBtn.style.display = (c.requestStatus === 'approved' || c.requestStatus === 'completed') ? 'inline-flex' : 'none';
```

```js
function qvGoToTimetable() {
    var c = myConflictedRows().find(function (row) { return row.id === currentOccurrenceId; });
    if (!c) { hideQuickView(); return; }
    hideQuickView();
    goToReplacementInTimetable(c.requestId, c.replacementWeek);
}
```

For approved/completed rows `#cancelRequestBtn` and `#qvArrangeBtn` are both already hidden, so the
footer shows Close (left) + Go to My Timetable (right) — no layout surgery needed.

## 5. CSS (page-local, `replacement-home.blade.php` `<style>`)

| Rule | Now | After | Why |
|---|---|---|---|
| `.col-action` (`:33`) | `6%` | `10%` | two 34 px buttons + gaps ≈ 76 px of content |
| `#timetable .col-reason` (`:30`) | `22%` | `18%` | the 4 points come from here; the reason badge wraps, so it absorbs the loss |

`.col-*` rules are page-local to My Replacements, so no other table moves. The responsive law is
re-verified by measuring every cell at eight widths (task T11) — the previous lane's fix
(`10512ef`) is the receipt pattern to copy.

## 6. Risks and their answers

| Risk | Answer |
|---|---|
| The flash lands on the class's natural block | impossible: `data-rid` exists only on replacement events, and there is no fallback selector |
| Refresh / back replays the flash | params stripped before matching (D5) |
| The week does not switch (WeekNavigator restore wins) | the switch rides `window.selectWeek`, the same wrapper the notification arm uses; runs at the same 150 ms tick |
| First render slower than the retry budget | 20 × 250 ms ≈ 5 s, and the miss is silent |
| Two buttons overflow a 6 % column | `.col-action` 10 %, verified at 8 widths |
| Cohort/venue/student payloads gain a key | additive `requestId`; those blades never read it |
| `completed` rows have no block | Completed is derived read-time from an **approved** row (`request-completed-status` C-rules); `MyTimetable` queries `['pending','approved']`, so the block exists |
| The ACTION column gets busy on the card view | the card view renders `actionCell()` too — the two buttons stack inside `.rc-*`; verify at 390 px |

## 7. Verification matrix (the receipts to produce in apply)

| # | Check | How |
|---|---|---|
| V1 | modal button visible for approved + completed, absent for pending/rejected/none | Playwright, planted fixture, computed `display` |
| V2 | click → `/my-timetable-ui`, `repl-hl`/`repl-w` **gone** from the URL after arrival | `page.url()` |
| V3 | `.bookable-flash` lands on `[data-rid="<rid>"]` exactly once, within 5 s | class observer / polled assertion |
| V4 | the neighbour block (second approved request, same week) never flashes | same run, count elements with the class |
| V5 | ACTION column: 2 buttons for approved/completed, 1 otherwise; cell overflow 0 at 1920/1600/1440/1366/1280/1100/900/820 | measured `scrollWidth vs clientWidth` per cell |
| V6 | card view at 390 px still fits | screenshot + no horizontal page overflow |
| V7 | gates: `composer run lint:check`, PHPStan level 7, `node --check`, full `php artisan test` | the standard commands |
