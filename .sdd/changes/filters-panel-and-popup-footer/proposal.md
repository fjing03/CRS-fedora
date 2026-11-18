# Proposal — filters panel clears the first column; phone detail footer becomes one row

Date: 2026-11-18
Lane: `filters-panel-and-popup-footer` (small, compact lane: one artifact, one review round)
Status: VERIFIED for the two reported surfaces; not archived. Independent review round 1
passed the causes and D1, and identified missing button selectors and right alignment in
D2 plus the stale `modal-responsive-width` R3 assertion. Those findings were addressed
before verification. D3 makes the existing touch floor hold for the new row. Reviewer: read-only `pi`
subprocess, `commandcode/z-ai/glm-5.3-flash`, output `/tmp/fpf-review.txt`.
Source: pass-3 critique, `docs/ui-drift-2026-11-17.md` §9 — remaining P3 #3 (filters panel covers
the column it filters) and P3 #4 (phone detail pop-up footer reads as three blocks).
User: "yes go" — both in one lane, two commits (one per surface).

## 1. Problem 1 — the open panel hides the column being filtered

Measured on `/my-replacements` at 1440×900, light (`/tmp/footer-filters/measure.json`):

| element | geometry |
|---|---|
| `#filtersButton` | x 24–106, y 145, h 34 |
| `#filtersPanel` | x 24–484, y 187, h 336 (`width: 460px` written by `_place()`) |
| `#table` | x 25, w 1390 (viewport 1440 — the grid nearly fills the page) |
| `Course` (1st column) | 29–246 (217 wide) — covered 217/217 |
| `Cohort` (2nd) | 250–386 — covered 136/136 |
| `Original Slot` (3rd) | 390–647 — covered 94/257 |
| `Conflict Reason` (4th) | 651–830 — covered 0 |

Every visible row's first cell is fully hidden while the panel is open: a lecturer searching by
course cannot see the course names change. The panel's left edge sits on the column's left edge, so
**narrowing the panel cannot clear the column** — coverage falls only in proportion to the width.
That rules out the finding's first suggestion as written.

## 2. Problem 2 — the phone detail footer stacks unequal pieces

Measured on `/my-timetable-ui` at 390×844, touch, `.modal--detail` open (`popups.json`):

```
footer         388×116, flex-direction: column, justify-content: space-between
├ .modal-footer-left   340×48   Close (.btn-close-modal)      340×48  ← full-width bar
└ .modal-footer-right  340×40   Cancel Class? (.btn-danger)   130×40  ← centred pill at x 130
```

Two different shapes (a bar and a floating pill) plus a 40px target next to a 48px one read as
three blocks, not as a footer. Cause: the ≤520 block in `theme.css` (2026-11-14
`mobile-modal-top3`) scopes "secondary = auto width, centred" to `.btn-danger` and
`.btn-outline:not(.is-primary)` but **not** `.btn-close-modal`, which keeps `width: 100%` from the
≤600 sheet block. For comparison the same pop-up at 1440 is one row: Close 78×34 left, Cancel
Class? 130×34 right.

## 3. Decisions

**D1 — the panel clears the first labelled column instead of being narrowed.**
`FiltersPanel._place()` (≥769 only) keeps its top (`button.bottom + 8`), width (460, capped) and
existing viewport clamps, and moves its **left** edge to just past the grid's first labelled column:
`left = clamp(firstLabelledTh.right + 12, 8, innerWidth - w - 8)`. "First labelled" = the first
`thead th` whose text is non-empty, so a leading checkbox column does not count. With no measurable
column (empty or hidden table) the current button anchor is used unchanged.

Measured result of the same rule on `/my-replacements`: panel left 253, Course covered 0,
Cohort 133/136, Original Slot 257/257, Conflict Reason 62/179, Requested Slot / Request Status /
Action 0. The row's identity column and the action column both stay readable.

*Narrow desktop fallback (reviewer, round 1):* when `firstTh.right + 12` would push the
card past the right gutter, the existing `innerWidth - w - 8` clamp wins — the card slides to
the right edge and may cover the tail of a very wide first column rather than overflow. At 769
that clamp is `769 - 460 - 8 = 301`; My Replacements' Course right edge was x=229 at
that width. No table reflow or new horizontal scroll in either branch.

*Rejected — shift the table while the panel is open (the finding's second suggestion):* measured,
`margin-left: 476px` on `.grid-wrapper` leaves `scrollWidth 1280` against `clientWidth 914` — a
forced 366px horizontal scroll every time the filters open, on top of the scroll-cue work that
exists because horizontal scrolling is already a burden. One overlap traded for a worse one.

*Rejected — right-anchor the card to the grid's right edge:* keeps the first column but puts the
card ~900px from the button that opened it and hides the whole Action column.

**D2 — the phone footer becomes one row of secondaries, primary full width underneath.**
At ≤520, inside `.modal--detail` only: the two rails stop being boxes (`display: contents`), the
footer becomes a wrapping row with `justify-content: space-between` (this is the mechanism that
puts Close left and the action right once the rails are gone — the old `margin-left:auto` only ever
matched `.btn-action`), secondary actions lose the forced full width and the forced centring
(`width:auto`), and the primary keeps `order:2; flex:0 0 100%`.

**The families must be completed in the same edit** (reviewer, round 1 — without this the order
inverts and the "action" renders *above* Close): secondary = `.btn-close-modal` (added),
`.btn-danger`, `.btn-outline:not(.is-primary)`; primary = `.is-primary`, `.btn-action`,
`.btn-approve`, plus `.btn-replace-now` (Arrange Replacement, my-timetable/cohort/venue) and
`.btn-confirm` (intent-session "Arrange"). Note `.modal--detail` is static markup in exactly one
blade (`ui-intent-session-modal.blade.php:8`, included by venue-timetable); the class-detail
pop-ups get it at runtime (`ui-common.js:1917`), so the selector is correct for both.

Source order and the existing `order` values decide the rest:

- Close + Cancel Class? → one row: Close left, Cancel Class? right (the desktop law).
- Close + Arrange Replacement → Close left on its own row, Arrange full width below.
- Close alone → Close left, as on desktop.
- Plain `.modal` confirm dialogs (Reject/Approve, cancel-confirm) are **not** touched.

Measured at 390 touch: footer 388×68 (was 116px tall), Close 78×48 at x=25 and
Cancel Class? 130×48 at x=235. The same left/right alignment holds at 320 and 520.

**D3 — the row's two targets must be the same height, so the parked 40px exception goes.**
Putting Close (48 on touch, via the coarse rule at `theme.css:7036–7041`) beside Cancel Class?
(40, from the ≤520 `min-height: 40px` at `2817`) on one row would show an 8px mismatch that this
lane created. Drop that `min-height: 40px` override so the sheet's 44px floor applies, and enforce
48×48 on detail footer actions only at ≤520 with a coarse pointer. Quiet Close keeps
its independently shipped all-width coarse-pointer floor. The old Close-height P2 is
already covered by the modal visual rules lane.

*Rejected — stack two full-width bars:* re-spends the 120px of sheet the 2026-11-14 ruling
recovered, and makes the secondary look like a second primary.

## 4. Files

- `public/js/ui-common.js` — `FiltersPanel._place()`, ≥769 branch.
- `public/css/theme.css` — the ≤520 `.modal--detail` footer block.
- `tests/filters-button.spec.ts` — its "left = clamp(button left…)" pin encodes the old anchor;
  replace with the new law (top unchanged, left just past the first labelled column, still clamped).
- `tests/modal-responsive-width.spec.ts` — **R3 pins the old stack** for `#classModal` at 390
  (`footerDir === 'column'`, equal widths, "fills the footer content box"). Rewrite it to the new
  law: one row, Close left of the action, equal heights ≥44, neither wider than the content box.
  (Round-1 correction: the proposal's earlier "confirm, adjust if needed" was wrong — it does.)
- Page changelogs: `replacement-home-changelog.md`, `request-approval-changelog.md`,
  `replacement-history-changelog.md` (the three filter pages) and `my-timetable-changelog.md`,
  `cohort-timetable-changelog.md`, `venue-timetable-ui-changelog.md`,
  `student-my-timetable-ui-changelog.md` (the `.modal--detail` pop-ups). Venue owns the
  Select a Session partial; Replacement Arrangement does not include it.
- `docs/ui-drift-2026-11-17.md` §9 — mark both findings answered, with the measurements above.

## 5. Acceptance criteria

1. At ≥769 the open panel's left edge is the first labelled column's right edge plus 12px,
   clamped within the viewport. Without a measurable column, use the button's left edge.
   Top and width are unchanged; ≤768 is untouched.
2. At 1440, Course remains clear on My Replacements and student Class Replacements.
   Request Approval uses the same rule with rows and falls back to the button when empty.
   The panel adds no horizontal overflow and the table does not shift.
3. At ≤520 in `.modal--detail`, secondary actions sit on one row (left/right), the primary is full
   width underneath, no footer button is centred alone, and every footer button is at least 44px
   tall — 48 on touch — at 320, 390 and 520 (D3).
4. At ≥521 the `.modal--detail` footer geometry is unchanged (for example, one row with 34px mouse buttons at 1440).
5. Plain `.modal` confirm dialogs keep their stacking and their focus order.
6. Keyboard order inside the panel is unchanged; focus still lands in the panel on open; Escape and
   the overlay click still close it.
7. No new runtime dependency, token, class or markup; colours and domain data unchanged.

## 6. Tasks

- [x] Implement D1 in `_place()`, with the fallback for an unmeasurable column.
- [x] Implement D2 and D3 in `theme.css`, ≤520 only, `.modal--detail` only.
- [x] Update the `filters-button` and `modal-responsive-width` pins; add focused receipts: panel-vs-first-column at 1440/1024/769,
  footer shapes at 320/390/520 plus the Arrange and Close-only variants, and a desktop no-change
  check at 1440.
- [x] Run `filters-button`, `modal-responsive-width`, `class-modal-affordances`, `cancel-class`
  (read-only cases only) and one scoped Impeccable detector.
- [x] Capture before/after at 1440 and 390 touch, light and dark; record the numbers.
- [x] Changelogs + survey §11; two commits, lane files only; no push.

## 7. Risks

- Moving the anchor weakens the "panel came from this button" cue. Mitigation: the card keeps its
  position under the same row (top unchanged), the veil, the shadow and the focused panel.
- `display: contents` on the rails is the only structural trick. `my-timetable.blade.php:11–16`
  does style `.modal-footer` and both rails in a page-local `<style>` (including its own padding);
  the theme rule wins on specificity (0,2,0 against 0,1,0) and the page's footer padding is not
  touched, but the earlier "verified in theme.css" claim was wrong and is corrected here.
- The narrow layout deliberately changes the visual placement of outline and primary buttons,
  not their DOM order, handlers or show/hide rules. In timetable footers where runtime Cancel
  Class? and Arrange are both visible, the existing keyboard order can differ from visual order.

## 8. Verification receipt

- Focused Playwright: **7 checks passed** plus 3 auth setups. The desktop course search changes
  the rows without closing the panel; Course is still uncovered. The phone sheet at 768,
  desktop card at 769/1024/1440, empty approval fallback, 320/390/520 touch footer, real
  Arrange footer and 1440 mouse footer were checked. Both updated legacy pins also pass.
- **24 before/after captures** in `/tmp/footer-filters/final/`, at 1440 and 390 touch in both
  themes. Course coverage went from 217px to 0 on My Replacements and 200px to 0 on
  student Class Replacements. My Replacements' Close moved x=156 → 17; Arrange remained
  356×48. Student timetable's Close-only state is left-aligned. Zero page overflow in the batch.
- `node --check public/js/ui-common.js`, `git diff --check` and scoped Impeccable layout scan
  passed (0 layout findings). No Blade or domain-data edits. No destructive tests ran.
- Wider run: **30 passed, 1 skipped, 8 known failures** (the count-pill assertion,
  four older modal-width setup timeouts on hidden event blocks, three fixture-dependent
  class affordance failures). The modal and affordance failures reproduced against the
  original CSS/JS. An additional read-only Cancel Class sample gave 4 passes including
  setup; S7/S8 stopped on its double-match `.cancel-required-mark` selector before confirmation.
- Request Approval's empty queue was checked live; no live pending row was available.
