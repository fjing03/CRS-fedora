# Review log — modal-dropdown-fixes

## Batch review — 2026-11-14 (independent, read-only)

Reviewer: `commandcode/z-ai/glm-5.3-flash`, whole batch against the real tree.

**Verdict: PASS — proposal, design, spec, tasks.** No blocking issues.

Confirmed:

1. The diagnosis is right and the fix is sufficient: `.modal { overflow: hidden }`
   and `.modal-body { overflow-y: auto }` are the **only** clipping ancestors
   (`.detail-row`, `.detail-value`, `.modal-overlay` clip nothing), the panel is
   `position: absolute`, and its `z-index: 1000` already paints it over the static
   footer. `:has()` is already in use in this stylesheet.
2. The arrow rule is safe: this partial is the only pop-up that puts
   `.filter-select` in a `.modal`, so no other select changes; the data URI is
   copied verbatim from `.week-select` / `.notif-type-select` (`%239EAAB8`), so no
   new hex. The light variant mirrors `.light .week-select` (`%235A6978`).
3. Spec/tasks are testable and no assertion is weakened.

Non-blocking notes, all resolved before apply:

- **Phone band.** An escaped panel could draw below the viewport on a flush sheet,
  so `<= 600px` keeps the clippers and opens the list **upward** instead; measured
  at 390: panel 571–651 above a trigger at 655–692, every row in view.
- **Light chevron.** The `.light .modal .filter-select` variant was added and
  screenshotted.
- **Keyboard.** The picker's list has no keyboard navigation — out of this lane's
  scope, recorded in the design's "not changed" list.
- **Clipping restored on close** is covered by the probe (the rules are
  `:has(.open)`-scoped, so closing cannot leave them applied).

## Freeze — 2026-11-14

All four artifacts frozen; `implementation_approved: true` (user reported the two
defects and asked for them fixed).

## Round 2 — arrow inset follow-up, 2026-11-14

User report after round 1: the two arrows were still inconsistent. Measured cause
(the trigger padded 24px, the select's chevron sits at 12px) and fixed by matching
the trigger's padding to the select's inset. Structural pin added to
`ModalConsistencyTest`; no assertion removed. Screenshot evidence at 2× in both
themes.
