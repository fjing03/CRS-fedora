# Design — `filters-button`

**Batch 2.** `proposal.md` is **frozen** (review round 4, 2026-11-14); its four decisions are taken
(§7: no Reset on the student page · the count pill follows the prototype · `All Weeks` · the second week
dropdown is IN on all three pages). This document pins what the proposal left open — every selector,
id, function and file — and settles proposal §7.2 and §7.3, which it explicitly carried here.

Line numbers are as of **2026-11-15** and are cited to be *found*, not trusted.

---

## 1. Shape

```
partials/ui-filter-panel.blade.php   (new)  — the row + the panel frame, nothing else
        │
        ├─ <div class="filters-row" id="filtersRow">
        │      [Filters button] [#filterChips] [#resultCount]        ← stays on the page
        └─ <div class="filters-overlay" id="filtersOverlay" hidden>
               <div class="filters-panel" id="filtersPanel" role="dialog" aria-modal="true">
                   head:  Filters  ·  Reset (staff only)  ·  ×
                   body:  <div id="filtersPanelBody">   ← the page's .toolbar is MOVED here
        </div>

ui-common.js  →  var FiltersPanel = { init, open, close, relocate, unrelocate }
theme.css     →  .filters-row, .filters-btn, .filters-overlay, .filters-panel*, .filter-field,
                 .filter-line, .result-count pill, scoped panel rules, print, phone chip sizing
```

The controls never change owner: `.toolbar` stays the same node with the same ids (`#searchInput`,
`#statusFilter`, `#weekFilter`, and the switch — `#hideCompleted` on My Replacements and the student
page, `#hideCompletedToggle` on Request Approval) and is **moved**, not rebuilt — the
house rule from the summary lane, and the reason nothing in the pages' own JS has to be re-pointed.

The move happens **at every width**. There is no `matchMedia` relocation branch: only the panel's
*shape* changes at 768 (popover → bottom sheet).

## 2. Decision log (why each thing is where it is)

| decision | why |
|---|---|
| the panel is a plain overlay div at `z-index: 1200`, not `<dialog>` | `.showModal()` uses the top layer, which beats every z-index; the tooltip engine sits at 9999 and every control in this panel carries a `data-tip`. Measured last lane; rejected in the proposal (§4 Option C). |
| the button, chips and count are emitted by the **same** partial as the panel | a page cannot ship the door without the room, and the partner lane learned to keep the frame in one file. The toolbar is the only part the page supplies. |
| the toolbar is **moved** into the panel body, never re-rendered | a move keeps `#resetFilters` (listeners bound by id), `#filterChips` (repainted by the page) and `#resultCount` (written by the page) intact. A rebuilt copy would be a second page to keep in sync. |
| the panel body ships **empty** and the button ships **hidden** | graceful failure: if the JS never runs, the toolbar is still inline where it has always been and the door is simply absent. The mirror-image design (empty page, populated by JS) would leave a dead page. |
| `.filters-btn` is revealed by a `filters-ready` class on `<html>` | the reveal *is* the proof the module booted and the move succeeded — one class, no `hidden` bookkeeping, and the tests can assert it. |
| labels are real Blade markup, not injected at runtime | the prototype builds them in JS (§5.4 of the brief); that is the option the proposal rejected (Option B) because a failed boot then shows an unlabelled bar. |
| desktop panel is positioned by JS from the button's rect | the veil and the panel must share one fixed overlay to stack predictably (`z 1200`), and a panel inside the row cannot sit above a fixed veil without inventing stacking contexts. Content is static once moved, so a one-shot fit cannot be wrong (the venue picker's growth bug does not apply). |
| own scroll-lock class `filters-panel-open` | `summary-locked` is shared and unrefcounted; so is `summary-sheet-open`. |
| `wire:ignore` on the toolbar host and the panel body | both pages are Livewire page components and DO round-trip (Approve/Reject — that is why `withWeekLivewireGuard()` exists). A morph would otherwise compare the client's moved toolbar against the server's copy and either duplicate or delete it. |
| the second week field reuses `partials/ui-week-nav` a **second time** | the partial is already parameterised by `$selectId` and by its two `onclick` strings; a second include gets arrows, tips and disabled-state for free and the shared file stays byte-identical for its five other pages. |
| phone chip sizing overrides the 48px touch floor | the app-wide `@media (max-width:768px) { button, a { min-height:48px; min-width:48px } }` is what makes today's chips row **120px** tall (the ✕ becomes a 48px square). The prototype drops the ✕ to 26px and lets the pill be the target. Trade accepted and measured in §9. |

### 2.1 Two places this design does **not** follow the prototype

1. **Desktop placement.** The prototype puts its card at the viewport's top-right
   (`align-items:flex-start; justify-content:flex-end`). The frozen proposal says *popover under the
   button*, and that is what ships — the button the user pressed is the anchor. The prototype's card
   look (dim `0.32`, `fit-content` width, `460px` cap, `shadow-lg`) is kept.
2. **`Active Filters:` label.** The prototype hides it with a CSS rule; this lane deletes the string
   from the three builders instead, so the DOM and the screen agree.

## 3. Breakpoint and placement

One breakpoint, **768px** (house one): `@media (min-width: 769px)` in CSS,
`matchMedia('(max-width: 768px)')` in JS.

| width | panel | placement |
|---|---|---|
| ≤768 | bottom sheet: full width, `max-height: 88dvh`, head sticky, body scrolls, `overscroll-behavior: contain`, `padding-bottom: env(safe-area-inset-bottom)` | `align-items: flex-end` on the overlay; veil `rgba(0,0,0,.45)` |
| ≥769 | card: `width: min(460px, calc(100vw - 16px))`, `border-radius: var(--radius-lg)`, `box-shadow: var(--shadow-lg)` | computed from the button: `left = clamp(rect.left, 8, vw - w - 8)`, `top = rect.bottom + 8`, `max-height = vh - top - 8`; veil `rgba(0,0,0,.32)` |

`position: fixed` for the card is deliberate: it is a child of `#filtersOverlay` (also fixed), so the
two share a stacking context and the panel is inside the veil's tree rather than racing it.

The trigger stays bright above the veil via `body.filters-panel-open .filters-btn { z-index: 1300 }`,
which works because no ancestor of the page content forms a stacking context today (neither `.lw-page`
nor `.app-container` carries a transform/filter/z-index; the summary sheet at 1200 is the working
precedent). **Fallback if that ever changes:** move `#filtersOverlay` to `document.body` on `open()` and
back on `close()` — never raise the overlay's `z-index`, because the tooltip engine at 9999 depends on
being above it.

Recompute on: `open()`, `resize`, `orientationchange`, and `populateWeekSelect`'s own 200ms resize
hook is irrelevant here (the card's height is bounded by `max-height`, the body scrolls). Crossing 768
while open **keeps it open** and re-applies the placement — a rotated phone must not throw away a
half-entered search.

## 4. Markup contract

### 4.1 `partials/ui-filter-panel.blade.php` (new)

Blade vars, all optional:

| var | default | meaning |
|---|---|---|
| `$filtersCount` | `true` | emit `#resultCount` in the row (student page passes `false`) |
| `$filtersCountTip` | `'Rows matching the current filters'` | that element's `data-tip`; **My Replacements passes `'Classes matching the current filters'`** (its own wording today, `:224`) |
| `$filtersReset` | `false` | emit the Reset button in the panel head (two staff pages pass `true`) |
| `$filtersResetOnclick` | `''` | Reset's handler string, e.g. `'resetAllFilters()'` |
| `$filtersResetTip` | `'Reset all filters to DEFAULT'` | Reset's `data-tip` |

Two small deltas from the proposal's §5.2/§5.3 sketch, recorded so the two documents cannot drift: the
`$panelId` variable is gone (the panel id is fixed — every page ships the same frame), and the funnel
glyph is **14px** (the prototype's size, matching the 12px label) rather than the proposal's 16px.

```blade
<div class="filters-row" id="filtersRow">
    <button type="button" class="filters-btn" id="filtersButton"
            aria-haspopup="dialog" aria-controls="filtersPanel" aria-expanded="false">
        {{-- funnel, 14px, aria-hidden="true" --}}
        <span>Filters</span>
    </button>
    <div class="filter-chips" id="filterChips"></div>
    @if($filtersCount)
        <span class="result-count" id="resultCount" data-tip="{{ $filtersCountTip }}"></span>
    @endif
</div>

<div class="filters-overlay" id="filtersOverlay" hidden>
    <div class="filters-panel" id="filtersPanel" role="dialog" aria-modal="true"
         aria-labelledby="filtersPanelTitle">
        <div class="filters-panel-head">
            <h2 class="filters-panel-title" id="filtersPanelTitle">Filters</h2>
            @if($filtersReset)
                <button type="button" class="filters-panel-reset" id="filtersResetButton"
                        data-tip="{{ $filtersResetTip }}"
                        onclick="{{ $filtersResetOnclick }}">{{-- circular arrow, 13px --}} Reset</button>
            @endif
            <button type="button" class="filters-panel-close" aria-label="Close Filters">&times;</button>
        </div>
        <div class="filters-panel-body" id="filtersPanelBody" wire:ignore></div>
    </div>
</div>
```

Rules:

- `#filterChips` keeps its id **and its class** — the pages' repaint writes `innerHTML` and the CSS
  shadow rules are keyed on `.filter-chips`. The row makes it inline instead of a bar (§5).
- `#resultCount` keeps its id and class (`.result-count`); only its box changes.
- `Reset` is a **slot**, not a fixed button: absence is the student page's shape (decision 1).
- The `hidden` attribute (not a class) is the closed state, so nothing inside is focusable or announced.

### 4.2 Per-page toolbar markup (the seven / five touch points)

Every page keeps its `.toolbar` where it is, wrapped so the module can find it and so a morph cannot
duplicate it:

```blade
<div id="filterToolbarHost" wire:ignore>
    <span class="filters-toolbar-placeholder" hidden></span>
    <div class="toolbar">
        ...
    </div>
</div>
```

Inside the toolbar, per control:

```blade
<div class="filter-field">
    <label class="filter-label" for="searchInput">Search</label>
    <input class="search-input" id="searchInput" ...>            {{-- placeholder kept --}}
</div>

<div class="filter-line">
    <div class="filter-field">
        <label class="filter-label" for="statusFilter">Status</label>
        <select class="filter-select" id="statusFilter" data-tip="Request Status">…</select>
    </div>
    <div class="filter-field filter-field--toggle">
        <label class="filter-label" for="hideCompleted">Hide COMPLETED</label>   {{-- Request Approval: for="hideCompletedToggle" --}}
        <label class="toggle-wrapper" id="completedToggle" data-tip="ON = completed requests are excluded from the list">
            <input type="checkbox" id="hideCompleted" checked>                    {{-- Request Approval: id="hideCompletedToggle" --}}
            <span class="toggle-track"><span class="toggle-thumb"></span></span>
            <span class="toggle-label">Exclude <span class="info-keyword">COMPLETED</span> Status</span>
        </label>
    </div>
</div>

<div class="filter-field">
    <label class="filter-label" for="weekFilter">Original slot week</label>
    @include('partials.ui-week-nav', [... as today ...])
</div>

<div class="filter-field">
    <label class="filter-label" for="weekFilterRequested">Requested slot week</label>
    @include('partials.ui-week-nav', [
        'selectId' => 'weekFilterRequested',
        'prevOnclick' => "prevWeekFilter('weekFilterRequested')",
        'nextOnclick' => "nextWeekFilter('weekFilterRequested')",
        'selectOnclick' => "requestedWeekFilterChanged(this.value)",
        'selectTip' => 'Show only rows whose requested slot falls in this week',
        'prevTip' => 'Previous requested week',
        'nextTip' => 'Next requested week',
        'showTodayBtn' => false,
    ])
</div>
```

- The switch keeps its existing inner `toggle-label` text; **inside the panel** `.toggle-label` is
  hidden (the visible `Hide COMPLETED` label above says it), exactly as the prototype does.
- The `<label class="filter-label" for="hideCompleted">` and the wrapping `<label class="toggle-wrapper">`
  are two different things: the first is the field name, the second is the control.
- `aria-label` is removed from `#statusFilter` on both staff pages: the accessible name must come from
  the visible `<label>` (an `aria-label` would win and announce the old string). `data-tip` stays.
- My Replacements drops the magnifier: the `<svg class="search-icon">` and the `.search-wrapper`
  wrapper leave the page (proposal §7.2, answered **drop**). `.search-wrapper`/`.search-icon` stay in
  `theme.css` — the nine design templates still render them.
- The `#resetFilters` button leaves the toolbar (it is now `#filtersResetButton` in the panel head)
  **and its boot listener must be deleted in the same commit**, because the pages bind it by id and a
  `null` there throws at `DOMContentLoaded` — taking every listener wired after it with it:
  `replacement-home.blade.php:1419` (`… #resetFilters').addEventListener('click', resetAllFilters);`)
  and `request-approval.blade.php:1368` (`… addEventListener('click', resetFilters);`). The panel-head
  Reset is wired by the partial's own inline `onclick`, so nothing replaces those lines.

### 4.3 The second week field, per page

| page | existing field | new field (id) | label | chip text | row key the week comes from |
|---|---|---|---|---|---|
| My Replacements | `#weekFilter` (original) | `#weekFilterRequested` | Requested slot week | `Requested Slot Week: …` | `c.replacementDate` |
| Request Approval | `#weekFilter` (requested) | `#weekFilterOriginal` | Original slot week | `Original Slot Week: …` | `r.classDate` |
| Class Replacements | `#weekFilter` (original) | `#weekFilterRequested` | Requested slot week | `Requested Slot Week: …` | `r.req.week` (1-based; `null` when unarranged) |

Field order in the panel is fixed by the proposal: **Search · Status + Hide COMPLETED · Original slot
week · Requested slot week**. On Request Approval the new *Original* field therefore renders **above**
the existing requested one.

**The shared week-nav partial is not edited.** Its arrows already take their handlers as strings; the
only code change is in `ui-common.js`:

```js
function prevWeekFilter(selectId) { /* defaults to 'weekFilter' */ }
function nextWeekFilter(selectId) { /* defaults to 'weekFilter' */ }
function updateWeekArrowState(selectId) { /* scoped to that select's own .week-nav */ }
```

`updateWeekArrowState()` today hardcodes `#weekFilter` and `updateWeekArrows()` uses
`document.querySelector` — with two navs on the page the second pair would never disable. Both gain an
optional id; **with** an id they resolve the arrows inside that select's own `.week-nav`; **without**
one they keep today's exact behaviour (first pair in document order — four timetable pages call
`updateWeekArrows()` with no `#weekFilter` on the page at all, so the no-argument path must not start
depending on `#weekFilter`).

Row semantics to state plainly (and test):

- A specific **requested** week hides every row with no arrangement (no replacement date) — including
  all `Need to Arrange` rows on My Replacements and every `none` row on the student page. That is the
  honest reading of "shows only that week", and the reverse of the completed switch's job.
- The student page's requested week reads `r.req.week`, which is already 1-based; the select's values
  are 1-based too, so the predicate is `r.req && Number(r.req.week) === wReq` (cast: the payload's int
  must not be trusted through JSON) — **no** `-1`
  (the original-week filter there subtracts 1 because it compares against the 0-based `r.week`; the two
  must not be copied into each other).

### 4.4 Chips

Same nodes and same removal mechanisms as today, three text edits plus one new chip:

| page | builder | edits |
|---|---|---|
| My Replacements | `renderFilterChips()` (`replacement-home:648`) | drop the `Active Filters:` prefix; insert the requested chip after the original one; `Exclude COMPLETED Status` → `Completed hidden` |
| Request Approval | `renderFilterChips()` (`request-approval:803`) | same, with `Original Slot Week:` inserted **before** the existing `Requested Slot Week:` chip (soft-frozen 2026-11-15: chips follow the panel's field order, Status · Original · Requested) |
| Class Replacements | `renderChips()` (`replacement-history:415`) | drop the prefix; add a requested chip with `data-remove="weekFilterRequested"`; `Completed hidden`; the delegated handler (`:729`) gains that branch |

Chip order: Status · Original slot week · Requested slot week · Search · Completed hidden · Clear all.

## 5. CSS (`public/css/theme.css`, one new section after the chips block)

```css
/* ── Filters button + panel ─────────────────────────────────────────── */
.filters-row { display:flex; flex-wrap:wrap; align-items:center; gap:6px 12px; margin-bottom:8px; }
.filters-btn { display:none; }                    /* revealed by .filters-ready */
.filters-ready .filters-btn {
    display:inline-flex; align-items:center; gap:6px;
    height:34px; min-height:34px; padding:0 12px;
    border:1px solid var(--color-outline); border-radius:var(--radius-sm);
    background:var(--color-surface-variant); color:var(--color-on-surface);
    font:600 12px/1 inherit; cursor:pointer; transition:border-color .15s, color .15s;
}
.filters-ready .filters-btn:hover { border-color:var(--color-primary); color:var(--color-primary); }
.filters-ready .filters-btn svg { width:14px; height:14px; flex:0 0 auto; }

/* chips stop being a bar: the row is the bar now */
.filters-row #filterChips { flex:1 1 auto; min-width:0; margin-bottom:0;
    padding:0; background:none; border:0; border-radius:0; }

/* the count becomes a pill at the right, at every width (M15 retired) */
.filters-row .result-count { display:inline-flex; align-items:center; flex:0 0 auto;
    margin-left:auto; height:26px; padding:0 12px; border-radius:var(--radius-pill);
    background:var(--color-surface-variant); color:var(--color-on-surface-variant);
    font:600 12px/1 inherit; white-space:nowrap; }

.filters-overlay { position:fixed; inset:0; z-index:1200; display:flex;
    align-items:flex-end; justify-content:center; background:rgba(0,0,0,.45); }
.filters-overlay[hidden] { display:none; }
.filters-panel { display:flex; flex-direction:column; background:var(--color-surface);
    color:var(--color-on-surface); border:1px solid var(--color-outline); }
.filters-panel-head { position:sticky; top:0; z-index:2; display:flex; align-items:center;
    gap:8px; padding:10px 14px; background:var(--color-surface);
    border-bottom:1px solid var(--color-outline); }
.filters-panel-title { margin:0; font-size:14px; font-weight:700; }
.filters-panel-reset { margin-left:auto; display:inline-flex; align-items:center; gap:6px;
    height:30px; min-height:30px; padding:0 10px; border:1px solid var(--color-outline);
    border-radius:var(--radius-sm); background:var(--color-surface-variant);
    color:var(--color-on-surface); font:600 12px/1 inherit; cursor:pointer; }
.filters-panel-reset:hover { border-color:var(--color-primary); color:var(--color-primary); }
.filters-panel-close { width:44px; height:44px; border:0; border-radius:var(--radius-sm);
    background:transparent; color:inherit; font-size:22px; line-height:1; cursor:pointer; }
.filters-panel-reset:focus-visible, .filters-panel-close:focus-visible,
.filters-btn:focus-visible { outline:2px solid var(--color-primary); outline-offset:2px; }
.filters-panel-body { min-height:0; padding:12px 12px 16px; overflow-y:auto;
    overscroll-behavior:contain; background:var(--color-bg); }

/* the moved toolbar loses its own card and becomes a stacked form */
.filters-panel-body .toolbar { display:grid; grid-template-columns:minmax(0,1fr);
    gap:10px; margin:0; padding:0; background:none; border:0; box-shadow:none; }
.filters-panel-body .toolbar-left { display:grid; grid-template-columns:minmax(0,1fr);
    gap:10px; width:100%; }
.filters-panel-body .toolbar-right { display:none; }   /* the count lives in the row */
.filter-line { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:end; }
.filter-field { display:block; min-width:0; }
.filter-label { display:block; margin:0 0 4px 2px; font-size:10px; font-weight:700;
    letter-spacing:.06em; text-transform:uppercase; color:var(--color-primary); }
.filter-field--toggle .filter-label { text-align:right; }
.filter-field--toggle .toggle-wrapper { height:38px; padding:0 12px; gap:0;
    border:1px solid var(--color-outline); border-radius:var(--radius-md);
    background:var(--color-surface-variant); }
.filter-field--toggle .toggle-label { display:none; }

/* the fields read as one set of controls */
.filters-panel-body .search-input { width:100% !important; padding-left:12px; }
.filters-panel-body .filter-select { width:100%; color:var(--color-on-surface); }
.filters-panel-body .week-nav { width:100%; margin:0; min-width:0; }
.filters-panel-body .week-select { flex:1 1 auto; min-width:0;
    background-color:var(--color-surface-variant); }
.filters-panel-body .week-arrow { color:var(--color-primary); }
.filters-panel-body .week-arrow:disabled { color:var(--color-on-surface-variant); }

body.filters-panel-open { overflow:hidden; }
/* the door keeps its full colour above the dim */
body.filters-panel-open .filters-btn { position:relative; z-index:1300; }

@media (max-width:768px) {
    .filters-panel { width:100%; max-height:88dvh; border-bottom:0;
        border-radius:var(--radius-lg) var(--radius-lg) 0 0;
        padding-bottom:env(safe-area-inset-bottom); }
    /* button and count share the first line, the chips take the next */
    .filters-row .filters-btn { order:1; }
    .filters-row .result-count { order:2; }
    .filters-row #filterChips { order:3; flex:1 1 100%; gap:4px 6px; }
    /* the app-wide 48px touch floor makes every chip ✕ a 48px square and the row
       120px tall; the pill becomes the target instead (prototype's own trade) */
    .filters-row #filterChips .filter-chip { padding:2px 9px; font-size:11px; gap:4px; min-height:32px; }
    .filters-row #filterChips .filter-chip-remove { width:26px; height:26px;
        min-width:0; min-height:0; font-size:14px; }
    .filters-row #filterChips .filter-chip-clear-all { height:26px; min-height:0;
        padding:0 10px; font-size:11px; }
}
@media (min-width:769px) {
    .filters-overlay { align-items:flex-start; justify-content:flex-start; background:rgba(0,0,0,.32); }
    .filters-panel { width:min(460px, calc(100vw - 16px)); border-radius:var(--radius-lg);
        box-shadow:var(--shadow-lg); }
}
@media print {
    .filters-overlay, .filters-btn { display:none !important; }
    body.filters-panel-open { overflow:visible; }
}
```

Kept on purpose: `.filter-chips:empty { display: none }` (frozen proposal §5.2 listed dropping it). The
row no longer depends on it — the button and the pill are siblings — and keeping it lets an empty chip
bar collapse instead of leaving a 12px gap between the button and the count.

Deleted in the same edit: My Replacements' `@media (max-width:1499px) { #resultCount { display:none } }`
(`replacement-home:106–110`, M15 — decision 1b).

## 6. JS (`public/js/ui-common.js`, a new `FiltersPanel` section after `SummarySheet`)

```
FiltersPanel = {
    _overlay, _panel, _button, _host, _placeholder, _opener, _mq, _onKeyDown, _onResize

    init()      // once per page: find #filtersPanel; move .toolbar in; wire everything;
                // <html class="filters-ready">; sync the closed state
    open()      // close the bell + the summary sheet, remember the opener, unhide, lock, focus, place
    close(restoreFocus = true)  // hide, unlock, aria-expanded=false, focus the opener
    relocate()  // .toolbar → #filtersPanelBody (idempotent)
    unrelocate()// .toolbar → its placeholder in #filterToolbarHost (idempotent)
    _place()    // ≥769 only: set left/top/max-height from the button's rect
}
```

Pinned behaviour:

1. **init** — bail silently if `#filtersPanel` or `.toolbar` is missing. Insert the placeholder span
   (already emitted by the page) into `#filterToolbarHost` if absent, `relocate()`, then
   `document.documentElement.classList.add('filters-ready')`. Wire: button click → `open()`; veil
   click (only when `e.target === overlay`) → `close()`; close button; `beforeprint` → `unrelocate()`;
   `afterprint` → `relocate()`; `matchMedia('(max-width:768px)')` change → `_place()` if open.
2. **open** — `closeNotifPanel()` and `SummarySheet.close()` first (one overlay at a time, §7); set
   `_opener`; `overlay.hidden = false`; `aria-expanded="true"`; `body.classList.add('filters-panel-open')`;
   `_place()`; focus the first control in the body
   (`body.querySelector('input:not([type=hidden]), select, button')`); bind keys.
3. **close** — the exact reverse. `restoreFocus` exists for the co-existence rule only (a bell or
   summary-sheet open closes the filters without stealing focus); **a resize never closes the panel**
   (§3) and a print does not call `close()` at all — it relocates and lets CSS hide the frame.
4. **Esc** — **capture phase**, and a no-op while a dialog above is visible
   (`document.querySelector('.modal-overlay[style*="flex"]')` or `#summaryExplainModal` open): one
   Escape means one layer, the rule the summary lane pinned.
5. **Tab trap** — keep focus inside `#filtersPanel`; wrap on the last/first control; suspended while a
   dialog above is visible (same predicate as Esc).
6. **Scroll lock** — `body.filters-panel-open` only; never `summary-locked` / `summary-sheet-open`.
7. **One overlay at a time** — `FiltersPanel.open()` closes the bell (`closeNotifPanel()`) and the
   summary sheet (`SummarySheet.close()`); symmetrically `openNotifPanel()` and `SummarySheet.open()`
   each gain one line: `if (window.FiltersPanel) FiltersPanel.close(false);`. Without this, two
   `z-index: 1200` overlays on one page would decide the winner by DOM order.
8. **Print** — the toolbar goes back inline for the paper (so a printout still shows the fields), the
   button and the overlay are hidden by CSS, and the chips + count still print (they never left the
   page). Nothing prints worse than today.
9. **No DOM ownership beyond the move** — the module never touches `#filterChips`, `#resultCount` or
   any control's value; those stay the page's.

Per-page wiring (each page's own boot, next to its existing init):

| page | calls | new handler |
|---|---|---|
| My Replacements | `FiltersPanel.init()` | `requestedWeekFilterChanged(v)` → `pageState.currentPage = 1; saveFilters(); buildTable(); updateWeekArrowState('weekFilterRequested')` |
| Request Approval | `FiltersPanel.init()` | `onOrigWeekFilterChange(v)` → `currentPage = 1; saveFilters(); renderTable(); updateWeekArrowState('weekFilterOriginal')` |
| Class Replacements | `FiltersPanel.init()` (from its `DOMContentLoaded` closure) | `requestedWeekFilterChanged()` → `saveView(); renderUpcoming(); updateWeekArrowState('weekFilterRequested')` |

## 7. Per-page code changes

### 7.1 My Replacements (`resources/views/livewire/replacement-home.blade.php`)

| where | change |
|---|---|
| `:106–110` | delete the M15 block |
| `:193–227` toolbar | wrap in `#filterToolbarHost wire:ignore` + placeholder; labels + `.filter-field` wrappers; drop `.search-wrapper`/magnifier; status/switch into `.filter-line`; second week-nav; delete `#resetFilters`, `#resultCount`, `#filterChips` |
| after the toolbar | `@include('partials.ui-filter-panel', ['filtersReset' => true, 'filtersResetOnclick' => 'resetAllFilters()', 'filtersCountTip' => 'Classes matching the current filters'])` |
| `saveFilters()` `:613` | add `requestedWeek: weekFilterValue('weekFilterRequested')` |
| `restoreFilters()` `:622` | restore it, guarding an option that does not exist |
| `resetAllFilters()` / `clearAllFilters()` `:679/:690` | set `#weekFilterRequested` to `'all'` too, and sync both navs: the existing `updateWeekArrowState` in `rebuildTable({ after: … })` only drives `#weekFilter`, so add `updateWeekArrowState('weekFilterRequested')` after each call |
| `buildTable()` `:700` | add `const reqWeekVal = weekFilterValue('weekFilterRequested')` and `const matchesReqWeek = reqWeekVal === 'all' \|\| (!!c.replacementDate && String(getWeekNumber(c.replacementDate)) === reqWeekVal)` |
| `renderFilterChips()` `:648` | 3 text edits of §4.4 |
| `populateWeekSelect` `:880` | `allLabel: 'All Weeks'`; then `populateWeekSelect('weekFilterRequested', { includeAll: true, allLabel: 'All Weeks' })` |
| `jumpToNewRequest()` `:1234–1250` | clear `#weekFilterRequested` beside the existing search/status/week clears (its ruling — "a filter hiding the row is cleared FOR THIS VISIT ONLY" — now covers a second week), then `updateWeekArrowState('weekFilterRequested')` next to the existing no-arg call |
| boot `:1402–1419` | keep the four change/input listeners; **delete the `#resetFilters` click listener (`:1419`)**; add one for `#weekFilterRequested`; the week listener's `after: updateWeekArrowState` stays |

### 7.2 Request Approval (`resources/views/livewire/request-approval.blade.php`)

| where | change |
|---|---|
| `:227–252` toolbar | same treatment; switch keeps `onchange="toggleHideCompleted()"`; **new** original week-nav renders above the existing requested one |
| after the **sort hint** (`:254`, which stays between the toolbar and the row, proposal §7.3) | `@include('partials.ui-filter-panel', ['filtersReset' => true, 'filtersResetOnclick' => 'resetFilters()'])` |
| `saveFilters()` `:475` | add `origWeekFilter: weekFilterValue('weekFilterOriginal')` |
| `restoreFilters()` `:487` | restore it |
| `filterData()` `:636` | add `const origWeek = weekFilterValue('weekFilterOriginal'); if (origWeek !== 'all' && !isInWeek(r.classDate, origWeek)) return false;` |
| `onWeekFilterChange()` `:911` and boot `:1330` | each no-argument `updateWeekArrowState()` becomes `updateWeekArrowState('weekFilter')` — the Original field now renders **above** the requested one, so the document-order default would re-sync the wrong nav and leave the requested widget's ‹ › stale |
| `renderFilterChips()` `:803` | 3 text edits + the original-week chip |
| `resetFilters()` `:1256` / `clearAllFilters()` `:1277` | set `#weekFilterOriginal` to `'all'` in both, then **both** `updateWeekArrowState('weekFilter')` and `updateWeekArrowState('weekFilterOriginal')` (neither function re-syncs arrows today, and both selects were just moved to `all`) |
| `populateWeekFilter()` `:1245` | `allLabel: 'All Weeks'`; then `populateWeekSelect('weekFilterOriginal', { includeAll: true, allLabel: 'All Weeks', labelFn: function (w) { return w.label; } })` — same `labelFn` as the requested field, so the two read alike at every width |
| boot `:1361–1368` | **delete the `#resetFilters` click listener (`:1368`)**; add a `change` listener for `#weekFilterOriginal`; `FiltersPanel.init()` next to `populateWeekFilter()` |

### 7.3 Class Replacements (`resources/views/livewire/replacement-history.blade.php`)

| where | change |
|---|---|
| `:143–150` toolbar | wrap + labels; week field; second week-nav; switch's label |
| `:151` | delete `#filterChips` (the partial emits it) |
| after the toolbar | `@include('partials.ui-filter-panel', ['filtersCount' => false])` — **no Reset**, no count |
| `saveView()` `:265` | add `reqWeek: …` |
| restore `:696` | restore `reqWeek` (option-guarded, like `week`) |
| `renderChips()` `:415` | prefix dropped, `Completed hidden`, requested chip with `data-remove` |
| delegated handler `:729` | add the `weekFilterRequested` branch (`value = 'all'`, `saveView()`, `renderUpcoming()`) |
| `clearAllFilters()` `:443` | reset the requested week too (it early-returns when a value is already `'all'` — check per select, not once) |
| `renderUpcoming()` `:455` | `const wReq = req === 'all' ? null : parseInt(req, 10);` and the predicate `(req === 'all' \|\| (r.req && Number(r.req.week) === wReq))` — **cast, per §4.3** |
| `populateWeekSelect` `:690` | `allLabel: 'All Weeks'` ×2 |
| `revealNotifRow()` `:798–812` | if `#weekFilterRequested` is set and would hide the row, clear it to `'all'` and set `changed = true` (same treatment the original week and the switch already get), then `updateWeekArrowState('weekFilterRequested')` — otherwise a saved requested week makes the arrival reveal skip silently |
| boot `:696–745` | a `change` listener for `#weekFilterRequested` |

Page changelogs: one 3-line entry each in `page-changelogs/replacement-home-changelog.md`,
`request-approval-changelog.md`, `replacement-history-changelog.md`.

## 8. Behaviour table (the contract to verify)

| # | action | expected |
|---|---|---|
| 1 | load any of the three pages | Filters button in the row; **no** filter control visible (search included); chips and count in the row |
| 2 | click the button at ≥769 | card under the button, clamped inside the viewport; veil `0.32`; page locked; button still bright; focus in the first field |
| 3 | click the button at ≤768 | bottom sheet, head sticky, body scrolls; veil `0.45` |
| 4 | Esc / × / veil tap | closes; focus back on the button; `aria-expanded=false`; page scrolls again |
| 5 | Tab / Shift+Tab | cycles inside the panel only |
| 6 | change any filter, close the panel | chips repaint on the page without reopening; count pill matches |
| 7 | requested week = week 9 on My Replacements | only rows whose replacement date is in week 9; `Need to Arrange` rows disappear; one chip appears and removes only itself |
| 7b | open, change nothing, close | `#filterChips` is the **same DOM node** before and after (no re-created bar, so the page's repaint keeps writing where it always did) |
| 8 | original week = week 9 on Request Approval | only rows whose class date is in week 9; the two week filters compose (AND) |
| 9 | Reset (staff pages) | status back to that page's default, **both** weeks `All Weeks`, exclusion ON, saved filters forgotten, both navs' arrows re-synced; **Request Approval also returns rows-per-page to 10** (proposal §5.5) |
| 10 | Clear all (all three) | every filter off, including both weeks; chips row empty but the button and count stay |
| 11 | reload after using a second week | both weeks come back |
| 12 | open the bell, then the filters | the bell closes; and the reverse |
| 13 | print preview | no button, no panel/veil, the toolbar fields **do** print (relocated back), chips + count print |
| 14 | resize across 768 with the panel open | stays open, re-shapes in place |
| 15 | Approve a request on Request Approval, then reopen the panel | one toolbar, still inside the panel, both week selects still populated and keeping their values |
| 16 | live inside a row modal (`.modal-overlay`), press Esc | the row modal closes, the panel does not |

## 9. Numbers and the one accepted trade

The lane exists to give the first screen back, so the acceptance is a position, measured at **390×844 in the default view — nothing the user set, but the exclusion chip and its
Clear all still show, and the student page has no count**, y measured from the viewport top:

| page | today | must be |
|---|---|---|
| My Replacements | 519px | **≤ 400px** |
| Request Approval | 541px | **≤ 400px** |
| Class Replacements | 375px | **≤ 375px (must not regress)** — its bar was already the cheap one |

The chips row itself must measure **≤44px** in that same state (today 120–126px). With three or more
active chips the row is expected to wrap and grow — that is the pill's own content, not the bar this
lane deletes, and no bound applies there.

Accepted trade: the chip ✕ becomes 26px inside a 32px pill, below the app's 48px touch floor. The pill
is the target and the chips row is a summary, not a primary action; the alternative — a 48px ✕ per
chip — is exactly the 120px block this lane deletes. Recorded here so it is a decision, not an
accident.

## 10. Failure modes

| failure | what the user sees |
|---|---|
| `ui-common.js` does not load | toolbar inline as today, labels above the controls, **no** Filters button (the `filters-ready` reveal never happens) — degraded but usable |
| the module throws mid-init | same as above (the class is added last) |
| a Livewire morph after Approve/Reject | nothing moves: both the toolbar host and the panel body are `wire:ignore`; if a morph still wipes the panel body, the page **loses the controls** (worst case) — the test in §8.15 is the canary |
| a page keeps its old `#resultCount` markup | two nodes with one id: the page's own would be the first, the pill the second. The task list deletes the old markup in the same commit; the test asserts exactly one |
| first paint before `DOMContentLoaded` | the toolbar is briefly inline before the move (a flash of the old bar on a slow phone). Cosmetic; the restore-on-print path reuses the same relocation, so no action is planned unless it shows in testing |
| someone "tidies" an id (`#weekFilter` → `#originalWeek`) | the page's filter silently stops working; §4 and §7 pin the ids, and `#filterChips` node identity is asserted |

## 11. Out of scope, confirmed

The summary cards, the page headers, the guide dialogs, print redesign (`timetable-print-v2`), the nine
`ui-design-templates/*` pages, `partials/ui-week-nav.blade.php` itself, any backend change, any
migration, and any new dependency.

## 12. Rollback

Revert the three blades + `theme.css` + the `FiltersPanel` section of `ui-common.js` + delete the new
partial; the chips/count/labels revert with them. The `updateWeekArrowState(selectId)` widening and the
`prevWeekFilter(selectId)` widening are backwards compatible and can stay even if the rest is reverted.
