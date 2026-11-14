# Spec — responsive modal width

> **Change:** `modal-responsive-width`
> **Status:** frozen · 2026-11-13
> **Sources:** `proposal.md`, `design.md`

## R1 — Dialog shrinks to content, floored at 420, capped at the existing ceiling

- Above 601px every modal uses `width: fit-content` with `min-width: 420px`.
- Each variant keeps its ceiling: detail 680, cancel 588, guide 520,
  summary 420.
- No modal may render **wider** than it does today.

**Scenario: Class Details at each desktop width**
- Given the details pop-up open at 1360 / 1100 / 1024 / 900 / 769
- Then its width is **less than 680** and at least 420
- And `scrollWidth <= clientWidth + 1` (no horizontal clipping)

**Scenario: the ceiling still wins**
- Given the guide pop-up (paragraph body) at 1360
- Then its width equals its ceiling (520), not the floor

**Scenario: per-variant ceilings are preserved**
- At 1360: details 420, cancel <= 588, guide <= 520, summary <= 420
- And none exceeds the value it had before the change

## R2 — The bottom sheet is phones only (`<= 600px`)

- `<= 600px`: sheet — full width, flush to the bottom edge, top-only radius.
- `601px` and above: centred dialog — side gaps > 0, symmetric radius.
- **768px (iPad mini) must be a dialog**, not a sheet.

**Scenario: 390 phone**
- Then side gap = 0, bottom gap = 0, modal width = viewport width
- And top radius > 0, bottom radius = 0

**Scenario: 600 edge**
- Then still a sheet (side gap 0, bottom gap 0)

**Scenario: 601 / 700 / 768**
- Then a dialog: side gap > 0, bottom gap > 0
- And symmetric radius (top = bottom)
- And the footer is one row, not stacked
- And nothing overlaps or clips at any of the three widths

## R3 — The stacked footer fills the sheet

- In the sheet range both footer wrappers stretch to the full footer width.
- `.btn-danger` joins the full-width button set.
- Close and Cancel render at the same width, filling the sheet minus padding.

**Scenario: phone footer**
- Given the details pop-up at 390 with a Cancel action
- Then Close width = Cancel width
- And both are >= (viewport width - 2 × 16px - tolerance)
- And both are >= 44px tall

**Scenario: every footer button family fills the sheet**
- Given the intent-session pop-up at 390 ("Cancel" + "Arrange")
- And the confirm dialog at 390 ("Close" + "Confirm")
- And the cancel-class pop-up at 390 ("No, Keep Class" + "Yes, Cancel Class")
- Then every visible footer button spans the sheet minus padding
- And none is narrower than its sibling

**Scenario: desktop footer is unchanged**
- At 1360 the footer is one row; Close is left and the actions are right
- And the buttons keep their content width (not stretched)

## R4 — The sheet docks flush

- `.modal-overlay` has no padding in the sheet range.
- `.modal` retains the safe-area bottom inset.

**Scenario: flush dock**
- At 390 and 600: side gap = 0 and bottom gap = 0

## R5 — The × is a 48px target in the sheet range

- `.modal-close` renders 48×48 with `<= 600px`.
- Above 600 it keeps its existing size.

**Scenario: close target**
- At 390 and 600: × measures 48×48
- At 768 and 1360: × is smaller than 48 (unchanged)

## R6 — Header and footer are pinned; only the body scrolls

- `.modal` is a flex column with `overflow: hidden`.
- `.modal-header` and `.modal-footer` are `flex: 0 0 auto`.
- `.modal-body` is `flex: 1 1 auto; min-height: 0; overflow-y: auto`.

**Scenario: tall content on a short phone**
- Given 390×420 with content taller than the body
- Then the body scrolls (`scrollTop > 0` after scrolling)
- And the modal itself does not scroll
- And the header position is unchanged before/after scrolling
- And the footer position is unchanged before and after
- And the × and the footer buttons remain visible at the bottom of the scroll

## R7 — Preserved behaviour

- Phone sheet layout at 390 is otherwise the same as today.
- Modal content, wording, tabs, row order and footer actions are unchanged.
- The page layouts' own 768 breakpoint is untouched.
- Print layouts are untouched.
- No modal becomes wider than before.

**Scenario: no regression in the existing suites**
- `cancel-class`, `class-modal-affordances`, `session-arrival-pin-guard`,
  `venue-db` and the confirmation-dialog specs stay green without assertion
  edits.

## R8 — The superseded 680px law is corrected, not deleted

- `ModalConsistencyTest` asserts the **bounded** invariant: floor 420, ceiling
  680, `fit-content` present.
- The test is not removed.
- A comment beside the `theme.css` law states that 680 is now a ceiling.

**Scenario: the guard survives the rewrite**
- The test still fails if someone restores a fixed 680px width with no floor
- And it still fails if the ceiling is raised above 680

## Acceptance

- New Playwright spec covers R1–R6 at 390 / 600 / 601 / 700 / 768 / 1024 / 1360.
- `ModalConsistencyTest` rewritten and green (R8).
- Existing modal suites green (R7).
- Measurements recorded in `review-log.md`.
