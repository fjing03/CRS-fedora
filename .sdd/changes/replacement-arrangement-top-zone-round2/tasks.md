# Tasks — Replacement Arrangement top zone Round 2

> Change: `replacement-arrangement-top-zone-round2`
> Apply status: **NOT STARTED — waiting for the user's explicit approval**
> Frozen inputs: `proposal.md`, `design.md`, `specs/top-zone-round2/spec.md`
> Baseline: commit `7692d80`

## Apply gate

- [x] T1. Confirm explicit user approval before any live edit (0.25h)
  - The user said: "just dont sdd apply until i approves."
  - Do not edit `resources/views`, `public/js`, tests or changelog until the
    user says apply.
  - Confirm `7692d80` is an ancestor of HEAD and no other lane is mid-apply.
  - Re-read the frozen proposal/design/spec; do not change decisions during
    apply.

> **Atomic apply batch:** T2 → T13 lands in one pass. The page is visibly
> inconsistent between the markup move and the renderer/CSS/test updates, so
> do not verify or commit per task.

## Markup

- [x] T2. Add the wrapper id and three-card structure (1.5h)
  - File: `resources/views/livewire/replacement-arrangement.blade.php`.
  - Add `id="topZone"` to `.toolbar-restructure.ra-top-zone`.
  - Keep `#subjectInfo` as the Conflict card; remove the Details row from it.
  - Add the `Class Details` card root `#classDetailsCard` as a sibling, with
    `#detailsToggle` (small `ra-card-toggle`, initial `aria-expanded="false"`,
    `aria-controls="detailsBody"`, `aria-label="Expand Class Details"`),
    `#detailsSummary`, new `#detailsEmpty`
    (`Select a course and conflict slot to see the class details.`), inner
    `#details` (keep `hidden`) and `#detailsBody`.
  - Move the existing `#selSummary` block into the wrapper as the fourth
    `.ra-block.ra-selection-summary.sel-summary`; remove the lower copy.
  - Change visible labels to `Venue` and `Week`; keep aria labels descriptive.
  - Do not duplicate any ID or card.

## CSS

- [x] T3. Implement the grid matrix and rails (2h)
  - Base grid `8fr 5fr 7fr`; `<=1100` one column.
  - `>=1101` rail variants: conflict 56px/5fr/7fr; details 8fr/56px/7fr;
    both 56px/56px/7fr.
  - `1101–1319` with `has-summary`: same 3-track setup row; Summary
    `grid-column: 1 / -1` row 2; include all rail variants.
  - `>=1320` with `has-summary`: `8fr 5fr 8fr 280px`; all rail variants.
  - Add §1a rail internals: padding `12px 8px`, `column-reverse` head,
    centred toggle, vertical eyebrow; hide the two-line Conflict summary in
    the desktop rail; keep it at `<=1100`.
  - Add `.ra-eyebrow--muted`.

- [x] T4. Class Details card CSS and arrow law (1h)
  - Card head, heading, small toggle, chevron, empty guidance.
  - Details body stacked on desktop; row-wrap at `<=1100`.
  - Remove the inner `.ra-details` margin/border in the card.
  - Keep the card always visible on desktop.
  - Add the generic open=up / closed=down chevron pair.
  - Add the `>=1101` left/right overrides for **both**
    `#conflictCollapse` and `#detailsToggle`.
  - Scope/remove the old `.ra-details.open .ra-details-chevron` rotation so it
    cannot win over the card-head toggle.

- [x] T5. Summary compact CSS (1h)
  - `.ra-top-zone:not(.has-summary) .ra-selection-summary { display:none }`.
  - `.ra-top-zone .ra-selection-summary { padding:12px 14px }` (beats
    `.ra-block`).
  - Header, one-column grid, stacked info rows, wrapped Tip/Submit; no
    internal overflow at 280px.
  - Do not edit the grid placement rules here; T3 owns them.

- [x] T6. Replacement card CSS (1h)
  - `.ra-replacement-controls` column/stretch; `.ra-replacement-week` 100% and
    no auto margin.
  - `.toolbar-filters` full-width column; week nav 100%; `#earliestBtn` 100%
    centred.
  - Change the page-local week-select `background` shorthand to
    `background-color` so the shared chevron survives.
  - Keep the Venue trigger `max-width: 216px` cap.
  - Add the venue-row fill rules (`.toolbar-center` 100%, `.venue-dd`
    flex 1 1 auto) without lifting the 216px trigger cap.

- [x] T7. Venue panel fit CSS (1h)
  - `@media (min-width:769px)`: panel `max-width: calc(100vw - 24px)`; each
    `.venue-col` `width/min/max:150px`; header/item padding; the item keeps
    `10px` side padding.
  - At `<=768` no new rule.

## JavaScript

- [x] T8. Wire the top-zone state and renderer (2h)
  - Declare one `const topZone = document.getElementById('topZone')` at the
    top of the closure.
  - Conflict toggle: also toggle `conflict-collapsed`; keep `is-collapsed`.
  - Details toggle: toggle `detailsBody.hidden`, `details.open`, wrapper
    `details-collapsed`, `aria-expanded` and `aria-label`.
  - `renderTitleSummary()`: `.has-slot`, `#detailsEmpty` (`hidden` false/true),
    show/hide `#details`/`#detailsToggle`, open only on empty→filled, set the
    ARIA pair; remove `details-collapsed` in the `hasSlot` false branch.
  - `renderCollapsedSummary()`: remove `conflict-collapsed` when there is no
    Course (alongside its `is-collapsed` reset); the Course-without-Slot
    collapsed state remains supported.
  - Never hide `#classDetailsCard` on desktop and never rebuild with
    `innerHTML`.

- [x] T9. Wire the Summary visibility (0.5h)
  - `updateCounter()` toggles `has-summary` on `topZone`; no `hidden` on
    `#selSummary`.
  - Keep `updateSelectionSummary()` and all summary IDs.
  - Verify first paint with a restored selection.

- [x] T10. Add the venue panel fit function (1h)
  - Measure panel width, clamp left/right to 12px, set `panel.style.left`
    relative to the trigger; reset on close.
  - Run on trigger open, on resize while open, and after a panel click that
    reveals a column.
  - No shared `ui-common.js` change.

## Mobile

- [x] T11. Add the one relocation entry and mobile CSS (1h)
  - `public/js/arrangement-mobile.js`: `.ra-class-details` →
    `[data-arrangement-mobile-context-facts]`, after `#subjectInfo`.
  - Mobile CSS: transparent card chrome; hide head; hide empty card; force
    `#detailsBody[hidden]` visible when a slot exists.
  - Do not alter `DESKTOP_CONTAINERS` or other relocation entries.

## Tests

- [x] T12. Update the existing affected specs (1.5h)
  - `tests/arrangement-mobile.spec.ts`: 769px empty Summary is
    `display:none`; empty/slotted mobile Class Details; add
    `.ra-class-details` to `T23_NODES`.
  - `tests/session-arrival-pin-guard.spec.ts`: repoint cohort/Details reads to
    `#classDetailsCard` / `#detailCohorts`; keep positives and negatives.
  - Update selectors in the named arrival/tooltips/strip/guards specs only
    where the moved Details or Summary requires it.

- [x] T13. Add `tests/top-zone-round2.spec.ts` (2h)
  - 1360px no summary: `8/5/7` within 1px; `.ra-block` chrome.
  - 1360px summary: `8/5/8 + 280`.
  - 1200px summary: setup row `8/5/7`; Summary row 2 full width.
  - 1100px one column.
  - Rails at 1200/1360; use a pinned/selected page.
  - Arrow state + `aria-label` pair for both cards.
  - Week-select `background-image`.
  - `#earliestBtn` content-box width equals the Replacement card; rows
    stacked.
  - Summary first paint with a saved selection (`has-summary` present), clear
    the last selection (`has-summary` absent), and
    `#selSummary.scrollWidth <= clientWidth` at 1360px.
  - `#detailsSummary.hidden === true`; no second summary line.
  - Venue panel: four 150px columns at 1360, panel 12px inside the viewport;
    other page columns stay 200px.

## Close-out

- [x] T14. Changelog and static checks (0.5h)
  - Append a dated Round2 entry to
    `page-changelogs/replacement-arrangement-changelog.md`.
  - `php -l`, `node --check`, `git diff --check`.
  - `php artisan view:cache` + `view:clear`.
  - Restart through `scripts/dev-serve.sh` per `AGENTS.md`.
  - No DB/schema write.

- [x] T15. Verify (1.5h)
  - Run the new matrix spec and the named existing specs on the disposable
    `:8030` testing lane with the blessed flags.
  - Check 1360/1200/1101/1100, all four rail states, Summary empty/filled,
    mobile 768/769, venue panel clamp, and the week arrow.
  - Record results in `review-log.md`; do not relax unrelated guards.

- [ ] T16. Archive (0.25h)
  - After verify passes and the user approves, archive through
    `~/.config/opencode/tools/sdd/archive-change.sh`.

## Parked follow-up

- [ ] Mobile top-zone redesign (Round2 phone patterns): separate change after
  this desktop lane.
- [ ] Compact venue sheet at `<=768px`: separate mobile follow-up.
