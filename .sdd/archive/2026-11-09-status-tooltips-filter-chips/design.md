# Design — status-tooltips-filter-chips

## D0. The tooltip law (system-wide, zero new code)

Every new tooltip is a `data-tip="…"` attribute consumed by the shared
`initDataTipTooltips()` (`public/js/ui-common.js:3001`) — one floating
`.data-tip-tooltip` div, document-level delegation, **default position ABOVE
the element** (viewport-clamped). No new tooltip MECHANISM or CSS anywhere
in this change (the only shared-file edit is the D4 opt-string threading —
attribute emission, not tooltip logic). Tips render on dynamically rebuilt HTML for free (the listener is on
`document`), which is exactly the "dynamically rendered badges" requirement.

Tip strings are attribute-safe as designed (no `"`, no `<`, no `&`); each
builder appends `data-tip` only when the map produced a non-empty string, so
an unmapped/unknown status renders a plain badge (fail-open, never a broken
attribute).

## D1. Status tooltips (per-page maps, exact strings from the frozen proposal)

### `my-request-history.blade.php` — page-local `statusTooltip(status)`

| Status | tip |
|---|---|
| Pending | Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject). |
| Approved | The Programme Leader has APPROVED your request. |
| Rejected | The Programme Leader has REJECTED your request. |
| Cancelled | You MANUALLY CANCELLED this request. |
| Completed | The replacement class has been CONDUCTED — this request is complete. |

Wired at ALL the MRH status-badge build sites (batch-2 🔴1 — the desktop
page, its dialogs, AND the shared mobile card):
`:526` desktop table row (keeps its `onclick="openModalById(…)"`),
`:632` the quickCancel confirm-dialog badge and `:678` the
batchCancelSelected dialog badge (both keep their build), `:758` the
detail-modal Status row, and the MOBILE CARD badge inside the shared
`HtmlBuilder.myRequestCard` (`ui-common.js:2369`, invoked from the blade's
`renderCards()` at `:601`) — threaded via a new `opts.statusTip(status)`
callback (the builder consumes a page-supplied STRING; the map stays
page-local — the different-voices law is preserved).

### `request-approval.blade.php` — page-local `statusTooltip(status)`

| Status | tip |
|---|---|
| Pending | This request is AWAITING your review and decision (Approve or Reject). |
| Approved | You have APPROVED this request. |
| Rejected | You have REJECTED this request. |

Wired at `:679` (queue table row, keeps its `onclick`), `:938`
(detail-modal Status row), and the MOBILE CARD badge in the shared
`HtmlBuilder.requestCard` (`ui-common.js:2340`, invoked at blade `:825`)
via the same `opts.statusTip(status)` threading. Cancelled/Completed
never render in this queue (mapper rowsPayload = pending + decided) — no
tips invented for them.

Helper shape (each page):

```js
function statusTooltip(status) { /* the page's map above */ }
// builders append: (tip ? ' data-tip="' + tip + '"' : '')
```

NOT shared in ui-common.js — the two maps are different DATA in different
voices (proposer vs reviewer), not duplicated logic (rejected approach,
explore-brief).

## D2. The Overdue urgency state (request-approval — the user's Q3 ruling)

**Scope fence:** presentation-level urgency only. Request-status logic, the
mapper, and the queue composition are untouched.

1. **`urgencyDays(classDate)` stays the single diff source** (:421);
   `urgencyLevel()` (:404) refactors to consume it:

   ```js
   function urgencyLevel(classDate) {
       const d = urgencyDays(classDate);
       return d < 0 ? 'overdue' : (d <= 3 ? 'urgent' : 'normal');
   }
   ```

2. **`urgencyClass`/`urgencyLabel`** gain the `overdue` → `urgency-overdue`
   / `Overdue` entries.
3. **New pill** in the page's own `@section('page-styles')` beside :70–72
   (tokens only — same-name-same-colour):

   ```css
   .urgency-overdue { background: var(--color-error); color: var(--color-on-error); }
   ```

   Urgent keeps its translucent `--color-error-container`; Overdue is the
   SOLID red — the same red family, one visible severity step up.
4. **Row highlight** (`renderRow` :646): `level === 'urgent' || level ===
   'overdue'` → `row-urgent` (severity ≥ urgent keeps the existing red-row
   treatment; no new row class). The `row-soon` branch stays as is.
5. **The Urgent FILTER keeps matching overdue rows** (the silent-drop law):

   ```js
   function urgencyMatches(level, filter) {
       if (filter === 'urgent') return level === 'urgent' || level === 'overdue';
       return level === filter;
   }
   // :632 becomes: result.filter(r => urgencyMatches(urgencyLevel(r.classDate), urgencyFilter))
   ```

   The `.urgency-filter-chip` button set (all/urgent/normal) is NOT extended
   (no new filter button — out of scope); Normal filtering is provably
   unchanged. The `if (urgencyFilter !== 'all')` guard at `:631` STAYS as
   is (🔴2 — no change to the page's filtering control flow).
6. **The urgency tooltip** — new page-local `urgencyTip(r)`:

   | level | tip |
   |---|---|
   | overdue | Overdue — {abs(N)} day(s) past the Proposed Class. |
   | N = 0 | Urgent — the Proposed Class is TODAY. *(pending user confirmation — asserted in the receipt either way)* |
   | urgent | Urgent — {N} day(s) left before the Proposed Class. |
   | normal | Normal — {N} day(s) left before the Proposed Class. |

   `N = urgencyDays(r.classDate)` — computed at render, never hardcoded;
   singular `day` when `abs(N) === 1`. Wired at ALL THREE badge sites:
   `:502` (the approvalCard confirm-modal badge), `:678` (table cell), and
   the mobile-card badge in the shared `HtmlBuilder.requestCard`
   (`ui-common.js:2340`, invoked at blade `:825`) — threaded via
   `opts.urgencyTip` ALONGSIDE the existing
   `opts.urgencyLevel/urgencyClass/urgencyLabel` callbacks the page already
   passes at `:825–829` (the established pattern; the shared builder only
   maps a level → the page's string — no tooltip logic in ui-common.js).
   The `requestAgeHtml()` label threading (:662) receives "Overdue"
   automatically — copy re-read at apply (proposal risk).
7. **One existing tooltip copy syncs with Overdue** — the RA Urgency
   column-header tip (`:562`) currently reads "<=3 days: Urgent, 4+ days:
   Normal" and becomes factually WRONG the moment Overdue exists. Its exact
   new copy is pinned (in the spec): `Days until the original class: past
   = Overdue (solid red), ≤3 days = Urgent (red), 4+ days = Normal` (a
   tooltip COPY fix — the spec's §3 "check whether other tooltips ... "
   consistency spirit — no logic, no new element, the tip mechanism is
   untouched).

## D3. Filter bars (both pages)

### D3a. Renames + reset tip
- `#clearFilters` → **`#resetFilters`** at my-request-history `:205` and
  request-approval `:271`; both listeners updated (`:880` / `:1258`).
  Grep-verified: no other references (no spec/test collisions — a literal
  grep of `resources/views` also hits the two frozen
  `ui-design-templates/*.blade.php` reference templates; they are not live
  pages and stay untouched).
- Both buttons' tip: `data-tip="Reset all filters to DEFAULT"` (the Q4
  ruling). Button label text ("Reset Filters") unchanged.
- request-approval `:801` label: `Filters:` → **`Active Filters:`**.
  my-request-history keeps its existing `Active Filters:` label untouched.

### D3b. Clear-all (rides the innerHTML rebuild)
Both `renderFilterChips()` functions append ONE button after the chips when
`chips.length > 0` (nothing to clear otherwise; the bar's `:empty →
display:none` keeps working):

```js
chips.push('<button class="filter-chip filter-chip-clear-all" data-tip="Remove every active filter">Clear all</button>');
```

- **Shared CSS (Promoted to shared — theme.css, beside the chip block
  :3568):** `.filter-chip-clear-all` — inherits the `.filter-chip` pill
  metrics (same spacing/typing as every chip — UI-consistency law), restyled
  as a NEUTRAL action: transparent background, dashed
  `--color-outline-strong` border, `--color-on-surface-variant` ink, hover
  shifts border+ink to `--color-error`. No new colours (tokens only).
- Individual ✕ `filter-chip-remove` buttons untouched.
- The button is `type="button"`, outside any form — no submit risk.

### D3c. `clearAllFilters()` per page (real reset, never a visual hide)
- **my-request-history:** extract the existing inline reset body (`:880`
  listener) into a named `resetAllFilters()`; BOTH the `#resetFilters`
  listener and the clear-all button call it. Semantics (unchanged from the
  current button): `searchInput=''`, `statusFilter='all'`,
  `weekFilter='all'`, `hideCompleted.checked=true` (the page's default),
  `localStorage.removeItem('mrh-filters')`, `pageState.currentPage=1`,
  `rebuildTable({ render: renderTable, after: updateWeekArrowState })` —
  which re-renders the chips from REAL state.
- **request-approval:** the clear-all calls the EXISTING `resetFilters()`
  (:1177) verbatim — status→'Pending', week all, urgencyFilter all (+ chip
  toggles), rpp 10, hideCompleted true, bulk/viewed/sort/page resets,
  storage dropped — then re-renders. No reset logic is duplicated or
  reimplemented; one function, two triggers.
- Chips therefore disappear because the FILTERS are gone (the requirement:
  never merely hidden).

## D4. Files touched (apply time)

| File | Change |
|---|---|
| `resources/views/livewire/my-request-history.blade.php` | status map + data-tip ×5 sites (desktop table, quickCancel + batchCancel dialog badges, detail modal, mobile card via the new opts callback); `#resetFilters` rename; tip text; `resetAllFilters()` extraction; clear-all in `renderFilterChips` |
| `resources/views/livewire/request-approval.blade.php` | status map + data-tip ×3 sites; `urgencyTip()` + data-tip ×3 sites; Overdue state (level/class/label/row/filter/CSS pill); the Urgency column-header tip copy; `#resetFilters` rename; tip text; "Active Filters:"; clear-all in `renderFilterChips` |
| `public/js/ui-common.js` | MINIMAL opt-threading only (batch-2 🔴1): `HtmlBuilder.requestCard` + `HtmlBuilder.myRequestCard` consume new `opts.statusTip(status)` (both) and `opts.urgencyTip(r)` (requestCard) — one string in, one string out; **no tooltip logic added**; both builders keep working when the opts are absent (existing call sites unaffected) |
| `public/css/theme.css` | one `.filter-chip-clear-all` rule (shared by both pages) |
| `tests/status-tooltips-filter-chips.spec.ts` | NEW — the Playwright receipts (D5) |
| `page-changelogs/my-request-history-changelog.md`, `page-changelogs/request-approval-changelog.md`, `page-changelogs/status-tooltips-filter-chips-changelog.md` | receipts |

No routes, no DB, no mapper, no mock-data changes. `statusClass()` in
ui-common.js untouched (badge colours).

## D5. Tests (Playwright, AFTER apply — the user's mandated gate)

`tests/status-tooltips-filter-chips.spec.ts` (house style: staff login
5770 / `Turumt@2026`, server :8000, fresh views):

1. **MRH status tips** — for every rendered table row: hover the status
   badge → the shared tooltip's text equals the frozen map entry for that
   badge's own text (fixture-independent: whatever statuses the demo DB
   holds, the assertion is map-vs-tip). Plus the modal Status row carries
   the same `data-tip`.
2. **RA status tips** — same technique for the 3-status queue; before
   hovering, set the status filter to `All` (the `wire:model` select) so
   all three statuses are actually on screen — the receipt can't be
   vacuous behind the default `Pending` filter (🔴2).
3. **RA urgency** — for every queue row: the badge's `data-tip` matches
   `^(Normal|Urgent|Overdue) — (\d+ days? (left before|past) the Proposed Class\.|the Proposed Class is TODAY\.)$` AND the leading word equals the
   badge's visible label (badge↔tip consistency = the dynamic-days proof,
   since N is computed in-page and echoed in the tip). The **Overdue
   receipt is UNCONDITIONAL** (🟡3): the spec shifts
   `window.MockData.urgencyReferenceDate` forward past one row's class
   date IN PAGE, triggers a re-render (filter/page controls), then asserts
   that row flips to the `Overdue` badge + the `days past` tip + the
   `row-urgent` highlight AND still matches the Urgent filter — all
   against the live app, zero DB writes (MockData is already the page's
   date source, server-overridden via PAGE_DATA).
4. **Renames/tips** — `#resetFilters` exists on both pages with
   `data-tip="Reset all filters to DEFAULT"`; `#clearFilters` is gone;
   RA's chips label reads `Active Filters:`, MRH's label unchanged.
5. **Clear-all** — set ≥2 NON-default filters (status + search) → chips +
   the Clear-all button appear; click Clear all → the bar returns to
   EXACTLY that page's default-state chips (🔴2: MRH keeps its
   `Exclude Completed` chip; RA keeps `Status: Pending` +
   `Exclude Completed` — D3c's own defaults law), controls sit at their
   page defaults, and the table shows the non-default-filters row count;
   then re-apply one filter and remove it via its ✕ → the remaining
   chips/bar behave (the individual removes still work).
6. **Preservation** — the badge `onclick` still opens the detail modal
   (spot-check one row per page); a MOBILE-CARD badge (both pages) shows
   its status tip — and RA its urgency tip — through the shared
   `HtmlBuilder` (the 🔴1 threading receipt); sorting/pagination
   untouched (existing specs continue to gate them).

Gates at T5: `composer run lint:check` + `vendor/bin/phpstan analyse
--memory-limit=1G` (0 errors — no PHP touched, but the house gate runs
anyway), `node --check public/js/ui-common.js` (touched for the opt
threading), the new spec
green, changelogs ×3 updated, ONE conventional commit series, **no push**.
