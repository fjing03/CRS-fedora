# Design — facts strip out of the intrinsic width

> **Status:** draft · 2026-11-14

## 1. The one rule

```css
/* the facts strip takes the pop-up's width but must not widen it */
.modal .detail-facts {
    width: 0;
    min-width: 100%;
}
```

Why it works: in a shrink-to-fit container, an item's contribution to the
container's max-content size is its own max-content width. `width: 0` replaces
that contribution with 0; `min-width: 100%` then makes the box occupy the
resulting width, so it still paints edge to edge and its flex items wrap inside.
This is the standard "fill the parent, don't size it" pattern.

Placement: beside the `.detail-facts` base rule (theme.css ~1361) so the
behaviour is visible where the box is defined — no media query involved, and no
conflict with the modal width block (which sets the pop-up, never the strip).

Scoped to `.modal .detail-facts` rather than `.detail-facts`: the strip is only
used inside details pop-ups today, and the scope keeps any future page-level use
out of this rule.

## 2. Intrinsic sizes, after the rule

| Element | Contributes to the pop-up's width |
|---|---|
| `.detail-facts` | **0** (was ~900 one-line) |
| `.modal-tabs` | its one-row label sum (unchanged) |
| `.detail-table` rows | their own label/value widths (unchanged) |
| floor / ceiling | 420 / the variant ceiling (unchanged) |

Expected result: Request Details and Replacement Details land at the tab rail's
width (~510 measured on a clone) instead of 680. Class/slot details have no
facts strip, so they do not move.

## 3. The two approved maxima

`ui-intent-session-modal.blade.php:88` `max-width:560px` → `680px`;
`replacement-home.blade.php:363` `max-width:635px` → `680px`.

Both remain maxima under `fit-content`, so the visual result only changes if
their content is wider than the old number. The user accepted that risk
(2026-11-14) for consistency with the shared 680 ceiling.

## 4. Rejected alternatives

- **Shorter copy in the strip** — a content change, and the same strip is used by
  several pop-ups.
- **A per-tab width** — fragile, and the pop-up's width would jump when the user
  switches tabs.
- **`overflow-x: hidden` on the strip** — hides content instead of fitting it.

## 5. Verification

- Clone-shell measurement (626 → 513, same content) — the mechanism proof.
- Real pop-up: measure width and strip height; the user's screenshot is on the
  demo lane, so the honest numbers come from the clone plus the user's own look.
- `ModalConsistencyTest`: the rule exists in `theme.css`; the two blades carry
  680 and no longer 560/635.
- `modal-responsive-width.spec.ts`: Request/Replacement details still respect
  floor and ceiling; no horizontal clipping.
