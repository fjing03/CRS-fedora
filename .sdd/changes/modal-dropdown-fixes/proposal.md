# Proposal — the pop-up's two dropdown defects

> **Status:** draft · 2026-11-14 · user report with a screenshot (`ui-prototype/Select a Session.png`)

## Why

The user reported two defects in the shared "Select a Session" pop-up:

1. **The Session dropdown's list is hidden.** Opening the picker showed one row and
   cut the rest off — measured: the panel's bottom sits **53px below** the modal
   body's bottom edge, and the panel is clipped by it.
2. **The Subject dropdown's arrow** does not match the Session picker's arrow
   beside it (native browser arrow, drawn at a different inset), so the two rows
   of the same form look unrelated.

## Cause (measured, not guessed)

1. `modal-responsive-width` made the pop-up a three-row flex column with
   `.modal { overflow: hidden }` and `.modal-body { overflow-y: auto }` so the
   header and footer stay pinned. The session panel is an **absolutely positioned**
   child of a row inside that scroll box, so it is clipped at the body's edge.
   Before that change the whole modal scrolled with `overflow-y: auto`, and the
   panel was tall enough to stay inside the (auto-height) modal.
2. The Subject control is a native `<select class="filter-select">`. Its arrow is
   drawn by the browser at the padding edge, while the Session trigger draws a
   custom 10×6 chevron inset 12px. Two arrow languages on one form.

## What changes

1. While the session picker is open, the pop-up lets its panel escape the clip:

   ```css
   .modal:has(.session-dd.open),
   .modal-body:has(.session-dd.open) { overflow: visible; }
   ```

   CSS only, no JS, no new dependency. `:has()` is already used in this
   stylesheet (`.hour-cell:has(.cell-empty)`), so the support floor is unchanged.
2. The pop-up's selects reuse the session picker's chevron:

   ```css
   .modal .filter-select {
       appearance: none;
       padding-right: 34px;
       background-image: url(<the sanctioned 10×6 chevron already used by
                            .week-select and .notif-type-select>);
       background-repeat: no-repeat;
       background-position: right 12px center;
   }
   ```

   The SVG is copied verbatim from `.week-select` — the same sanctioned data URI,
   so no new colour is introduced (house rule: tokens only, no new hex).

## Out of scope

The picker's markup, its data, the booking hand-off, the warning caveat copy, the
pinned header/footer behaviour itself (only its clipping is relaxed *while a
dropdown is open*), and every other page's selects (the arrow rule is scoped to
`.modal`).

## Risks

| Risk | Mitigation |
|---|---|
| `overflow: visible` while open could let a long body scroll away | The pop-up's body is short; the state lasts only while the picker is open, and closing restores both rules |
| The custom arrow hides a native affordance | The chevron is the same shape the app already uses on two other controls, and the select still opens natively |
| A future pop-up with a long body could show the panel over its own footer | Accepted and specifically better than hiding rows behind a clip |

## Success criteria

1. Every row of the session picker is visible and clickable.
2. The Subject and Session arrows are the same shape, size and inset.
3. Nothing else in the pop-up moves.
