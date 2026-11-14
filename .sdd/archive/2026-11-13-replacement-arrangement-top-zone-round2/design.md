# Design — Replacement Arrangement top zone Round 2

> **Status:** frozen · 2026-11-12
> **Frozen proposal:** `.sdd/changes/replacement-arrangement-top-zone-round2/proposal.md`
> **Baseline:** commit `7692d80`
> **Visual contract:** `../../../../ui-prototype/replacement-arrangement-top-zone-preview.html`

## 1. Grid and state classes

The desktop wrapper is `.toolbar-restructure.ra-top-zone`. Add
`id="topZone"` to that wrapper and keep **one** closure-level reference:

```html
<div class="toolbar toolbar-restructure ra-top-zone" id="topZone">
```

```js
const topZone = document.getElementById('topZone');
```

Declare it above the first consumer, at the top of the `DOMContentLoaded`
closure, so `pageshow`/bfcache paths cannot hit the temporal dead zone.
Every `has-summary`, `conflict-collapsed` and `details-collapsed` writer uses
that same reference. Do not repeat `getElementById` inside each handler and do
not guard the reference — a missing id must fail loudly, not hide the Summary.

```css
.toolbar-restructure.ra-top-zone {
    display: grid;
    grid-template-columns: minmax(0, 8fr) minmax(0, 5fr) minmax(0, 7fr);
    align-items: stretch;
    gap: 12px;
}
.toolbar-restructure.ra-top-zone > .ra-block { min-width: 0; }

@media (max-width: 1100px) {
    .toolbar-restructure.ra-top-zone { grid-template-columns: minmax(0, 1fr); }
}

@media (min-width: 1101px) {
    .ra-top-zone.conflict-collapsed { grid-template-columns: 56px minmax(0, 5fr) minmax(0, 7fr); }
    .ra-top-zone.details-collapsed  { grid-template-columns: minmax(0, 8fr) 56px minmax(0, 7fr); }
    .ra-top-zone.conflict-collapsed.details-collapsed {
        grid-template-columns: 56px 56px minmax(0, 7fr);
    }
}

@media (min-width: 1101px) and (max-width: 1319px) {
    .ra-top-zone.has-summary { grid-template-columns: minmax(0, 8fr) minmax(0, 5fr) minmax(0, 7fr); }
    .ra-top-zone.has-summary .ra-selection-summary { grid-column: 1 / -1; }
    .ra-top-zone.has-summary.conflict-collapsed {
        grid-template-columns: 56px minmax(0, 5fr) minmax(0, 7fr);
    }
    .ra-top-zone.has-summary.details-collapsed {
        grid-template-columns: minmax(0, 8fr) 56px minmax(0, 7fr);
    }
    .ra-top-zone.has-summary.conflict-collapsed.details-collapsed {
        grid-template-columns: 56px 56px minmax(0, 7fr);
    }
}

@media (min-width: 1320px) {
    .ra-top-zone.has-summary {
        grid-template-columns: minmax(0, 8fr) minmax(0, 5fr) minmax(0, 8fr) 280px;
    }
    .ra-top-zone.has-summary.conflict-collapsed {
        grid-template-columns: 56px minmax(0, 5fr) minmax(0, 8fr) 280px;
    }
    .ra-top-zone.has-summary.details-collapsed {
        grid-template-columns: minmax(0, 8fr) 56px minmax(0, 8fr) 280px;
    }
    .ra-top-zone.has-summary.conflict-collapsed.details-collapsed {
        grid-template-columns: 56px 56px minmax(0, 8fr) 280px;
    }
}
```

- The wrapper classes are set by JS, not by a card resizing itself.
- `#subjectInfo` keeps `.is-collapsed` for its own summary; the wrapper class
  is the grid owner.
- The 1101–1319 summary spans row 2; nothing else in the 3-track template
  changes.

### 1a. Rail internals

A 56px track is not a rail without the card's own padding/heading rules. Copy
the prototype's rail law for both cards:

```css
@media (min-width: 1101px) {
    .ra-top-zone.conflict-collapsed #subjectInfo { padding: 12px 8px; }
    .ra-top-zone.conflict-collapsed #subjectInfo .ra-block-head,
    .ra-top-zone.details-collapsed #classDetailsCard .ra-block-head {
        flex-direction: column-reverse;
        align-items: center;
        gap: 12px;
        margin-bottom: 0;
    }
    .ra-top-zone.conflict-collapsed #subjectInfo .ra-card-toggle,
    .ra-top-zone.details-collapsed #classDetailsCard .ra-card-toggle {
        margin-left: 0;
    }
    .ra-top-zone.conflict-collapsed #subjectInfo .ra-eyebrow,
    .ra-top-zone.details-collapsed #classDetailsCard .ra-eyebrow {
        writing-mode: vertical-rl;
        white-space: nowrap;
    }
    .ra-top-zone.details-collapsed #classDetailsCard { padding: 12px 8px; }
    /* The two-line Conflict summary belongs to the <=1100 collapsed state. */
    .ra-top-zone.conflict-collapsed #subjectInfo .ra-collapsed-summary { display: none; }
}
```

- At `<= 1100px` the existing `#subjectInfo.is-collapsed .ra-collapsed-summary`
  rule still shows the two-line summary; the desktop rail hides it. This is
  the frozen proposal §3 two-line rule scoped to `<=1100`, matching the
  prototype's rail rule.
- `#detailsToggle` is a small `ra-card-toggle` in the head, not the
  full-width `.ra-details-toggle`; do not reuse the old row button styling.

## 2. DOM: three setup cards + Summary

Target order inside `.toolbar-restructure.ra-top-zone`:

```html
<section class="ra-block" id="subjectInfo" aria-labelledby="conflictHeading">
  ... Conflict heading, #conflictCollapse, #conflictCardBody ...
</section>

<section class="ra-block ra-class-details" id="classDetailsCard"
         aria-labelledby="classDetailsHeading">
  ... #detailsToggle heading, #detailsEmpty, #details, #detailsBody ...
</section>

<section class="ra-block" aria-labelledby="replacementHeading">
  ... Venue + Week ...
</section>

<div class="ra-block ra-selection-summary sel-summary" id="selSummary">
  ... existing summary markup moved here unchanged ...
</div>
```

- Remove the old `#selSummary` block below the grid.
- `#subjectInfo` is only the Conflict card.
- `#classDetailsCard` is always mounted. `#details` remains the inner
  container; the renderer may hide `#details`, never `#classDetailsCard` on
  desktop.
- `#selSummary` is the fourth card root; it takes `.ra-block
  .ra-selection-summary` plus its existing `.sel-summary`.

## 3. Class Details card

### Markup

```html
<section class="ra-block ra-class-details" id="classDetailsCard"
         aria-labelledby="classDetailsHeading">
  <div class="ra-block-head ra-class-details-head">
    <span class="ra-eyebrow ra-eyebrow--muted" id="classDetailsHeading">
      [info icon] Class Details
    </span>
    <button class="ra-card-toggle" type="button" id="detailsToggle"
            aria-expanded="false" aria-controls="detailsBody"
            aria-label="Expand Class Details">
      <svg class="ra-details-chevron">...</svg>
    </button>
  </div>

  <span id="detailsSummary" hidden></span>

  <p class="ra-empty-guidance" id="detailsEmpty" hidden>
    Select a course and conflict slot to see the class details.
  </p>

  <div class="ra-details" id="details" hidden>
    <div class="ra-details-body" id="detailsBody" hidden>
      Reason / Cohorts / Students (existing nodes)
    </div>
  </div>
</section>
```

- The heading is a separate span; `#detailsToggle` is the small chevron
  button, so hiding it with no slot keeps the `Class Details` heading visible.
- Keep `#details` and its `hidden` attribute; `renderTitleSummary()` continues
  to use `details.hidden` as the empty→filled probe.
- `#detailsSummary` stays mounted but `hidden`; `measureDetailsSummary()` is
  deliberately retained (no visual line) and can be deleted in a later
  cleanup, not this lane.
- Drop the old `.ra-details` `margin-top`/`border-top` inside the card; the
  card head owns the separation.
- Add `.ra-empty-guidance` styling:
  `margin: 2px 0 0; font-size: 12.5px; color: var(--color-on-surface-variant);`.
- Add the missing muted modifier:
  `.ra-eyebrow--muted { color: var(--color-on-surface-variant); }`.
- Details body law:

```css
.ra-class-details .ra-details-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
@media (max-width: 1100px) {
    .ra-class-details .ra-details-body {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 14px 24px;
    }
}
```

### Renderer

In `renderTitleSummary()`:

- `hasSlot` true:
  - `#classDetailsCard.classList.add('has-slot')`;
  - show `#detailsToggle`, `#details`, `#detailsEmpty.hidden = true`;
  - if the card was empty, open `#detailsBody`, set `aria-expanded=true` and
    `aria-label="Collapse Class Details"`;
  - keep a manual collapse across slot changes.
- `hasSlot` false:
  - `#classDetailsCard.classList.remove('has-slot')`;
  - show `#detailsEmpty` (`hidden = false`), hide `#details` and
    `#detailsToggle`;
  - set `aria-expanded=false`, `aria-label="Expand Class Details"`, remove
    `details.open`, and remove the wrapper `details-collapsed` class.
- Never set `hidden` on `#classDetailsCard`.

### Toggle

```js
detailsToggle.addEventListener('click', function () {
    const body = $('detailsBody');
    const opening = body.hidden;
    body.hidden = !opening;
    this.setAttribute('aria-expanded', String(opening));
    this.setAttribute('aria-label', opening ? 'Collapse Class Details' : 'Expand Class Details');
    $('details').classList.toggle('open', opening);
    topZone.classList.toggle('details-collapsed', !opening);
    if (opening) setTimeout(measureDetailsSummary, 0);
});
```

- The wrapper class drives the 56px rail.
- `renderTitleSummary()` removes `details-collapsed` when there is no slot.
- Mobile hides the card when `.has-slot` is absent; the desktop card stays
  visible.

## 4. Conflict card

- Keep the current markup and `#conflictCollapse` handler.
- Add the wrapper class toggle:

```js
topZone.classList.toggle('conflict-collapsed', collapsed);
```

- `renderCollapsedSummary()` must remove `conflict-collapsed` when no Course
  exists, alongside its existing `is-collapsed` reset.
- Arrow CSS:

```css
.ra-card-toggle[aria-expanded="true"]  .ra-card-chevron { transform: rotate(180deg); }
.ra-card-toggle[aria-expanded="false"] .ra-card-chevron { transform: rotate(0deg); }

@media (min-width: 1101px) {
    .ra-top-zone #conflictCollapse[aria-expanded="true"] .ra-card-chevron,
    .ra-top-zone #detailsToggle[aria-expanded="true"] .ra-details-chevron {
        transform: rotate(90deg);   /* left */
    }
    .ra-top-zone #conflictCollapse[aria-expanded="false"] .ra-card-chevron,
    .ra-top-zone.conflict-collapsed #conflictCollapse .ra-card-chevron,
    .ra-top-zone.details-collapsed #detailsToggle .ra-details-chevron {
        transform: rotate(-90deg);  /* right */
    }
}
```

- Below 1101px the generic up/down rules apply.
- The old `.ra-details.open .ra-details-chevron` rule is scoped so the desktop
  rail rules win; remove any conflicting generic `rotate(180)` after the
  card head move.

## 5. Summary move

### Markup/CSS

- Move the existing `#selSummary` block into the top zone; keep every child
  and ID unchanged.
- Add `.ra-block.ra-selection-summary` to `#selSummary`.
- Remove the old `margin-top: 14px`, border, radius and shadow from
  `.ra-selection-summary`; the `.ra-block` chrome + 12px grid gap is the only
  spacing.
- Compact the interior for 280px:

```css
.ra-top-zone .ra-selection-summary { padding: 12px 14px; }
.ra-selection-summary .sel-summary-header { padding: 0 0 8px; }
.ra-selection-summary .sel-summary-grid {
    grid-template-columns: 1fr;
    padding: 10px 0;
}
.ra-selection-summary .sel-summary-info { padding: 10px 0; }
.ra-selection-summary .sel-summary-info .info-rows { flex-direction: column; gap: 8px; }
.ra-selection-summary .sel-summary-action {
    flex-wrap: wrap;
    padding: 10px 0 0;
}
.ra-selection-summary .sel-summary-action #submitTip { flex: 1 1 auto; }
.ra-selection-summary .sel-summary-action .btn { width: 100%; justify-content: center; }
```

- The root padding selector must be `.ra-top-zone .ra-selection-summary` so it
  beats `.ra-block`'s `padding: 12px 16px`; do not use a bare
  `.ra-selection-summary` at the same source order.

### Visibility and JS

- `updateCounter()` is the single entry; add:

```js
topZone.classList.toggle('has-summary', has);
```

- CSS: `.ra-top-zone:not(.has-summary) .ra-selection-summary { display: none; }`.
- Do not set `hidden` on `#selSummary`.
- `updateSelectionSummary()` continues to build `#summaryGrid`, totals and
  `#summaryEmpty`; the card-level class hides the whole thing when empty.
- Initial boot reaches `updateCounter()` through `buildTimetable()`, so a
  restored selection shows the card on first paint.

## 6. Replacement card and venue panel

- Labels: change the two visible `.ra-field-label` spans to `Venue` and `Week`;
  leave the aria labels descriptive.
- The top-zone Conflict card stacks Course over Slot, matching the prototype:
  `.ra-top-zone .ra-original-controls { grid-template-columns: minmax(0,1fr); gap:14px; }`.
  Both controls take the full card content width; no sideways truncation.
- Earliest full width: the live `.ra-replacement-controls` is a content-sized
  row and `.ra-replacement-week` is `flex: 0 0 auto`, so the inner filters
  cannot reach the card width. Un-row the outer container first, then the
  filters column:

```css
.ra-replacement-controls {
    flex-direction: column;
    align-items: stretch;
    gap: 14px;
}
.ra-replacement-week { width: 100%; margin-left: 0; }
.ra-replacement-week .toolbar-filters {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
}
.ra-replacement-week .week-nav { width: 100%; }
.ra-replacement-week #earliestBtn {
    width: 100%;
    justify-content: center;
}
.ra-replacement-venue .toolbar-center { width: 100%; }
.ra-replacement-venue .venue-dd { flex: 1 1 auto; min-width: 0; }
```

- `body.arrangement-mobile-mode .ra-replacement-controls` already uses
  `display: block`, so mobile is unaffected.
- The Venue trigger fills the venue row, matching the prototype: remove the
  page-local `max-width: 216px` cap for the replacement trigger
  (`width:100%; max-width:none; min-width:0`); the description chip stays at
  the row tail. The Earliest button still reaches the card width.
- Panel CSS:

```css
@media (min-width: 769px) {
    .ra-replacement-venue .venue-dd-panel { max-width: calc(100vw - 24px); }
    .ra-replacement-venue .venue-dd-panel .venue-col {
        width: 150px;
        min-width: 150px;
        max-width: 150px;
    }
    .ra-replacement-venue .venue-dd-panel .venue-col-header { padding: 8px 10px 6px; }
    .ra-replacement-venue .venue-dd-panel .venue-col-item { padding: 8px 10px; gap: 6px; }
}
```

- Fit function (page-local, no shared change):

```js
function fitArrangementVenuePanel() {
    const dd = document.getElementById('buildingSelectorDropdown');
    if (!dd || !dd.classList.contains('open')) return;
    const panel = dd.querySelector('.venue-dd-panel');
    const trigger = dd.querySelector('.venue-dd-trigger');
    if (!panel || !trigger) return;
    const width = panel.getBoundingClientRect().width || 603;
    const triggerLeft = trigger.getBoundingClientRect().left;
    const margin = 12;
    let left = triggerLeft;
    const max = window.innerWidth - margin - width;
    if (left > max) left = max;
    if (left < margin) left = margin;
    panel.style.left = Math.round(left - triggerLeft) + 'px';
    panel.style.right = 'auto';
}
```

- Call after the trigger click (setTimeout 0), on resize while open, and after
  each panel click that reveals another column. Reset `panel.style.left` when
  the dropdown closes.
- At `<=768px` the mobile adapter owns the control; do not add a compact sheet
  here.

## 7. Mobile relocation

- `public/js/arrangement-mobile.js`: add one entry to `RELOCATION_PLAN` after
  `#subjectInfo`:

```js
{ node: '.ra-class-details', host: '[data-arrangement-mobile-context-facts]' },
```

- Add mobile CSS:

```css
body.arrangement-mobile-mode .ra-class-details {
    border: 0;
    border-radius: 0;
    box-shadow: none;
    padding: 0;
    background: transparent;
}
body.arrangement-mobile-mode .ra-class-details .ra-block-head { display: none; }
body.arrangement-mobile-mode .ra-class-details:not(.has-slot) { display: none; }
body.arrangement-mobile-mode .ra-class-details #details { margin: 0; border: 0; }
/* A desktop manual collapse must not hide the facts with no visible control. */
body.arrangement-mobile-mode .ra-class-details #detailsBody[hidden] { display: flex; }
```

- With a slot, the relocated card shows its body; the existing
  `#detailsBody` row styling remains.
- Do not alter `DESKTOP_CONTAINERS`; `.toolbar-restructure` still hides the
  desktop stack.

## 8. Test updates

- `tests/arrangement-mobile.spec.ts` T23b at 769px: the fresh page now has
  `summaryDisplay === 'none'` because `.has-summary` is absent. Change that
  assertion; keep the rest of the 768/769 boundary test. Also assert the empty
  mobile Class Details card is hidden and a slotted one is visible.
- Add `.ra-class-details` to the `T23_NODES` node-identity list so the new
  relocation entry has the same restore guard as the other moved nodes.
- `tests/session-arrival-pin-guard.spec.ts`: repoint the cohort assertions
  from `#subjectInfo` to `#classDetailsCard` / `#detailCohorts`; keep the
  positives and negatives, do not delete the guard.
- Add `tests/top-zone-round2.spec.ts` for the desktop matrix:
  - 1360px with summary: `8/5/8 + 280`; without: `8/5/7`.
  - 1200px with summary: summary row 2 full width; setup cards remain `8/5/7`.
  - 1100px: one column.
  - rail classes create 56px tracks at 1200/1360. Use a pinned/selected page
    (`?session=49&week=14` or pick a Course/Slot) because the toggles are
    disabled/hidden without one.
  - both card arrow states and their `aria-label` pair; week-select
    `background-image`; panel 150px columns and viewport clamp above 768px.
  - Course/Slot are stacked full width in the Conflict card.
  - The Venue trigger fills the venue row, `#earliestBtn` width equals the
    Replacement card inner width, and the Venue/Week rows are stacked.
- Add/adjust top-zone layout assertions in the named existing specs; do not
  relax unrelated guards.

## 9. File map

| File | Change |
|---|---|
| `resources/views/livewire/replacement-arrangement.blade.php` | grid CSS, Class Details card, Conflict/Details JS, Summary move, labels, venue panel fit, arrows |
| `public/js/arrangement-mobile.js` | one `.ra-class-details` relocation entry |
| `page-changelogs/replacement-arrangement-changelog.md` | dated Round2 entry |
| `tests/arrangement-mobile.spec.ts` | 769px conditional Summary; empty mobile Details |
| `tests/top-zone-round2.spec.ts` | new desktop matrix, rail, arrow, week-arrow and panel tests |
| `tests/arr-slot-original-venue-sync.spec.ts`, `default-pin`, `rearrangement-toolbar-tooltips`, `replacement-strip-anchor`, `venue-cross-week-handoff`, `arrival-grid-no-pin`, `clear-guards-cross-week`, `session-arrival-pin-guard`, `arrangement-earliest-week-default` | selectors/layout assertions as needed; repoint Details facts to `#classDetailsCard` |
| `.sdd/changes/replacement-arrangement-top-zone-round2/` | design, spec, tasks, review log |

## 10. Risks and mitigations

- **Grid class order/specificity:** put the 1101–1319 and >=1320 blocks after
  the base min-1101 rails; test all four state combinations.
- **Summary visibility vs mobile adapter:** wrapper class only; no `hidden` on
  `#selSummary`.
- **Details card empty state:** toggle `.has-slot` from the same
  `renderTitleSummary()` branch; never hide the desktop card root.
- **Rail + summary row 2:** the summary's `grid-column: 1 / -1` must stay
  outside the rail track overrides.
- **Venue panel offset:** relative to `#buildingSelectorDropdown`; clamp with
  measured width and reset when closed.
- **Mobile facts:** relocation entry order after `#subjectInfo`; hide the
  desktop card head and empty card on mobile.
- **Round1 tests:** update the named specs; do not relax unrelated guards.
