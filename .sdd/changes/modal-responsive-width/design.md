# Design — responsive modal width

> **Status:** frozen · 2026-11-13
> **Proposal:** `proposal.md`
> **Prototype proof:** `ui-prototype/my-timetable-mobile-preview.html`
> (rule applied, measured; no live file changed)

## 1. Three ranges, one rule each

| Range | Shape | Width | Footer | × |
|---|---|---|---|---|
| `<= 600px` | bottom sheet, flush | 100% | stacked, both full width | 48px |
| `601–768px` | centred dialog | fit-content, 420–ceiling | one row (Close left, actions right) | 40px |
| `769–1024px` | centred dialog | fit-content, 420–ceiling | one row | 40px |
| `>= 1025px` | centred dialog | fit-content, 420–ceiling | one row | 30px |

The × sizes are **not** chosen by this change: 30px is `.modal`'s own value and
40px comes from the `max-width: 1024px` touch-target rule. Only the 48px in the
sheet range is new.

The 600 / 768 split is deliberate and different from the page's own 768:
the **page** keeps 768 (frozen by `arrangement-mobile`), the **pop-up** drops
to 600 so a tablet never gets a phone sheet. Ruling 2 accepts the resulting
601–768 mismatch and requires it to be tested.

## 1a. Placement is load-bearing

`theme.css` declares the base `.modal` at line 1926, but its phone block
(`@media (max-width: 768px)` containing `.modal { width:100%; max-width:100% }`)
opens at **line 4013** and closes around 4071.

At 601–768 **both** media queries apply, and with equal specificity the *later*
one wins. So the new `@media (min-width: 601px)` block **must be appended after
line 4071**. Placed next to the base rule it would be silently overridden and
the band would stay a sheet — the exact bug the prototype hit.

This is a hard apply constraint, not a style preference.

## 2. Width rule

```css
@media (min-width: 601px) {
  .modal {
    width: fit-content;
    min-width: 420px;
  }
  /* ceilings unchanged — the change moves the width BELOW them, never above */
  .modal--detail  { max-width: 680px; }
  .modal--cancel  { max-width: 588px; }
  .modal--guide   { max-width: 520px; }
  .modal--summary { max-width: 420px; }
}
```

Why `fit-content` and not a narrower fixed width:

- A fixed 560px would shrink a wide Schedule tab unnecessarily and still leave
  a four-row Class Details pop-up empty.
- `fit-content` measures the widest **intrinsic** row. A paragraph-only body
  (confirmations, guide) has max-content equal to its unwrapped line, so it
  still reaches its ceiling. That is the correct outcome, not a bug — record it
  so it is not "fixed" later.
- The 420px floor is the current `.modal` default. Nothing can become narrower
  than today's most common pop-up, so nothing can look cramped.

**Measured (prototype, Class Details):**

| Width | Before | After |
|---|---|---|
| 1360 | 680 | 420 |
| 1100 | 680 | 420 |
| 1000 | 680 | 420 |
| 900 | 680 | 420 |
| 769 | 680 | 420 |

Per tab at 1360: Class Information 420, Schedule 420, Status 478. The box
grows when a tab needs it.

Per variant at 1360: details 680→**420**, cancel 588→**540**, guide 520→**520**,
summary 420→**420**.

## 3. Why 601–768 needs explicit dialog declarations

`theme.css` ships its sheet rules at `max-width: 768px`. Simply moving the new
sheet rule to `max-width: 600px` is **not enough**: the shipped block still
fires at 601–768 and leaves a half-sheet — square bottom, `align-items:
flex-end`, stacked footer, 16px body padding.

The prototype's first pass had exactly this bug. The fix is to declare the
dialog geometry back in the 601px-and-up block:

```css
@media (min-width: 601px) {
  .modal {
    width: fit-content;
    min-width: 420px;
    margin: auto;
    border-radius: var(--radius-2xl);
    border-bottom: 1px solid var(--color-outline);
    max-height: 85dvh;
    padding-bottom: 0;
  }
  .modal-overlay { align-items: center; }
  .modal-header { padding: 18px 22px 0; }
  .modal-body   { padding: 16px 22px; }
  .modal-footer {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 18px;
  }
  .modal-footer-left,
  .modal-footer-right {
    flex-direction: row;
    align-items: center;
    gap: 10px;
    width: auto;
  }
}
```

Alternative considered and rejected: keep the sheet at 768 and only change
width. That leaves the iPad-mini void (the report's second complaint) and keeps
two different patterns inside the same band.

## 4. Sheet rules (`<= 600px`)

```css
@media (max-width: 600px) {
  .modal-overlay {
    padding: 0;              /* 4: was 20px — the sheet floated, not docked */
    align-items: flex-end;
  }
  .modal {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    max-height: 85dvh;
    margin: 0;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    border-bottom: 0;
    padding-bottom: env(safe-area-inset-bottom);
  }
  .modal-close { width: 48px; height: 48px; font-size: 20px; }  /* 5 */
  .modal-header { padding: 14px 16px 0; }
  .modal-body   { padding: 16px; }

  .modal-footer {
    flex-direction: column;
    align-items: stretch;     /* 3: the missing half of the fix */
    gap: 8px;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
  }
  .modal-footer-left,
  .modal-footer-right {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    width: 100%;
  }
  .modal-footer .btn-close-modal,
  .modal-footer .btn-outline,
  .modal-footer .btn-danger,
  .modal-footer .btn-action,
  .modal-footer .btn-replace-now,
  .modal-footer .btn-confirm,     /* 3: intent-session "Arrange" */
  .modal-footer .btn-primary,     /* 3: confirm dialogs */
  .modal-footer .btn {            /* 3: bare `.btn` fallbacks */
    width: 100%;
    min-height: 48px;
    height: auto;
  }
  /* 3: the destructive Cancel button lives inside this wrapper, whose
     `align-items: flex-end` would otherwise stop it filling the sheet */
  .modal-footer .cancel-confirm-wrap {
    align-items: stretch;
    width: 100%;
  }
}
```

**Why the footer was broken.** Three independent causes, all required:

1. `.modal-footer` kept `align-items: center` from the desktop rule, so the
   two wrapper divs stayed shrink-to-fit. A `width: 100%` child then fills
   only its own wrapper — measured Close **75px**, Cancel **126px**.
2. `.btn-danger` was absent from the existing `width: 100%` list, so Cancel
   never even asked to stretch. Review found `.btn-confirm`, `.btn-primary`
   and bare `.btn` are missing too — those are the intent-session "Arrange"
   and every confirm-dialog button.
3. `.cancel-confirm-wrap` (the wrapper around the destructive Cancel button)
   carries `align-items: flex-end`, which blocks the same stretch one level
   deeper.

Fixing only (2) leaves both buttons narrow. Fixing only (1) leaves Cancel
narrow. All three are required.

**Measured after:** at 390 — Close **356**, Cancel **356** in a 390 viewport.
At 600 — **566 / 566**. Side gap 0, bottom gap 0, top radius 14px, bottom 0.

## 5. Pinned header and footer (all sizes)

```css
.modal {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.modal-header,
.modal-footer { flex: 0 0 auto; }
.modal-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
}
```

`min-height: 0` is what allows the middle row to shrink below its content
height; without it the flex item refuses to shrink and the footer is pushed
off-screen. This is the single most important line in the change.

**Measured** (390×420 with 18 injected filler rows): body scrolls, modal does
not, header pinned, footer pinned, × visible, footer visible.

## 6. Rendered geometry after the change (measured in prototype)

| Width | Shape | Modal w | Side gap | Bottom gap | × | Buttons |
|---|---|---|---|---|---|---|
| 390 | sheet | 390 | 0 | 0 | 48 | 356 / 356 |
| 600 | sheet | 600 | 0 | 0 | 48 | 566 / 566 |
| 601 | dialog | 420 | 91 | 263 | 40 | 75 / 126 |
| 700 | dialog | 420 | 140 | 263 | 40 | 75 / 126 |
| 768 | dialog | 420 | 174 | 325 | 40 | 75 / 126 |
| 1024 | dialog | 420 | 302 | 509 | 40 | 75 / 126 |
| 1360 | dialog | 420 | 470 | 280 | 30 | 75 / 188 |

## 7. Superseded law

`theme.css` currently states: *"every DETAILS modal is as wide as the
Replacement Details modal — one width for Class / Slot / Request / Replacement
details"*, fixed at **680px**, raised from 616 on 2026-11-11 because the
student pop-up's fourth tab needed 606px of labels inside the body.

After this change, 680px is a **ceiling**, not a fixed width. Two artifacts
must be corrected, and both are in scope:

1. **`tests/Feature/ModalConsistencyTest.php`** matches
   `/\.modal\.modal--detail \{[^}]*max-width: 680px;/s`. It must be rewritten
   to assert the new truth (floor 420 + ceiling 680 + `fit-content` present),
   not deleted.
2. **`cancelled-slot-reuse` task A17** says *"Keep 680px details width."* That
   lane has **zero tasks ticked** and no UI work started. Correction is a
   changelog + `theme.css` comment note only; its frozen files are **not**
   edited.

## 8. Preserved

- Every ceiling (680 / 588 / 520 / 420) — no pop-up may grow wider than today.
- Phone behaviour below 600 is the same sheet it is today, only docked.
- Content, wording, tabs, row order and footer buttons are untouched.
- The page layouts' 768 breakpoint is untouched.
- Print layouts are untouched.

## 9. Risks

| Risk | Mitigation |
|---|---|
| Pinned regions restructure the shared shell | Test confirmations, Cancel Class, guide, summary at 1360 and 390 |
| 601–768 band is a deliberate mismatch | Pin 601/700/768 in the spec |
| `fit-content` on a paragraph body does not shrink | Documented as correct; assert the guide/summary stay at their ceilings |
| Test regex rewrite loses a guard | Rewrite to the new invariant; never delete the test |
| Another lane restores 680 | Comment beside the law + changelog note |
| Content wraps more in the narrower box | 85vh cap + inner scroll; verified no clipping |

## 10. Apply order

1. `theme.css` — **append the 601+ block after line 4071** (see §1a), then the
   sheet block, then the pinned regions.
2. Reconcile `#confirmModal`'s page-local sheet block in
   `replacement-arrangement.blade.php` (lines 1718–1760): it sets its own
   `overflow-y: auto` + sticky header under `body.arrangement-mobile-mode`,
   which contradicts both pinning and the 601 cut-off on that page.
3. `ModalConsistencyTest` — rewrite to the bounded-width invariant.
4. New Playwright spec.
5. Re-run the existing modal specs.
6. Changelog + supersession note.

## 11. Reconciliation required: `#confirmModal` on the arrangement page

`replacement-arrangement.blade.php` has a page-local mobile block:

```css
body.arrangement-mobile-mode #confirmModal .modal {
    width: 100%; max-width: 100%; max-height: 85dvh;
    margin: 0; padding: 0 0 calc(16px + env(safe-area-inset-bottom, 0px));
    border-radius: 18px 18px 0 0; overflow-y: auto;
}
body.arrangement-mobile-mode #confirmModal .modal-header {
    position: sticky; top: 0; z-index: 1;
    background: var(--color-surface); border-bottom: 1px solid var(--color-outline);
}
```

That is a *second*, more specific scroller with a sticky header — a different
pinning mechanism from the one this change introduces globally.

Three options, to be chosen at apply time:

- **A.** Delete the page-local block and let the shared rules own it.
  Risk: the arrangement page's confirm dialog may rely on details of that block
  (18px radius, custom header) that are not reproduced globally.
- **B.** Keep it and convert its `overflow-y: auto` to the same three-region
  pinning, so both mechanisms agree.
  Risk: duplicated pinning logic on one page.
- **C.** Keep it as a documented one-page exception, and pin it in the spec.
  Risk: the confirm dialog on that page stays a sheet at 601–768 while other
  pages get a dialog.

**Recommendation: B** — it keeps the arrangement dialog visually as designed
while removing the second scrolling model. Recorded here so the choice is
explicit rather than discovered during the run.
