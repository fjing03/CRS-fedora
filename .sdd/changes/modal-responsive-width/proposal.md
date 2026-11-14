# Proposal — responsive modal width (fit-content dialog + flush phone sheet)

> **Status:** frozen · 2026-11-13
> **Change:** `modal-responsive-width`
> **Prototype trial:** `ui-prototype/my-timetable-mobile-preview.html`
> (applied and measured; **no live file touched**)

## Why

Two user reports, both about pop-up size:

1. **Wide screens waste space.** Every details pop-up is pinned at 680px no
   matter how little content it holds. The Class Details pop-up from the
   report shows four short rows in a 680px box — roughly 260px of dead width.
2. **iPad mini (768px) looks empty.** The pop-up is a bottom sheet 413px tall
   docked to a 1024px-tall screen, leaving **591px of blank overlay above it**.

Measured on the current prototype at 768px: modal 680 wide, widest content
line 634 — only 2px of width is actually wasted. The emptiness there is
**vertical**, caused by the sheet pattern being applied to a tablet.

The phone screenshots also exposed two real defects, not styling opinions:

- **The stacked phone footer never fills the sheet.** Measured: Close 75px,
  Cancel 126px, both centred, inside a 348px footer. `theme.css` intends
  full-width buttons here; two rules stop it (see §3).
- **The sheet does not dock.** The phone block sets `margin: 0` and
  bottom-aligns, but `.modal-overlay` keeps `padding: 20px`, so the sheet
  floats 20px off the left, right and bottom edges.

## What the user decided (seven rulings, 2026-11-13)

| # | Ruling |
|---|---|
| 1 | Bottom-sheet cut-off moves **768 → 600px**. Above 600 = centred dialog that sizes to content. |
| 2 | The 601–768 band (phone-ish page, desktop-ish dialog) is **accepted**, but must be pinned by a test rather than assumed safe. |
| 3 | **Fix the footer**: stretch both wrappers and add `.btn-danger` to the full-width list. |
| 4 | **Remove the overlay padding** in the sheet range so the sheet docks flush. |
| 5 | The **× becomes 48px** in the sheet range (it measures 40px today, below the 48px floor the stylesheet declares for phones). |
| 6 | **Pin header and footer**; only the body scrolls. |
| 7 | Prototype first, then live with the full test pass. |

Plus: **every modal variant** is included (details, confirmations, Cancel this
Class?, guide, summary) — "all modals" — but each keeps its **existing ceiling**
(680 / 588 / 520 / 420). The change moves the width *below* the ceiling, never
above it.

## What changes

### 1. Width: shrink to content, floored at 420px

```css
@media (min-width: 601px) {
  .modal { width: fit-content; min-width: 420px; }
  .modal--detail  { max-width: 680px; }   /* ceilings unchanged */
  .modal--cancel  { max-width: 588px; }
  .modal--guide   { max-width: 520px; }
  .modal--summary { max-width: 420px; }
}
```

`fit-content` measures the widest intrinsic row — subtitle, tab rail, or
label/value pair — and clamps it. **A paragraph-only body still reaches its
ceiling**, so confirmations and the guide barely change. That is correct and is
recorded here so nobody "fixes" it later.

Measured in the prototype (Class Details): **680 → 420** at every width from
769 to 1360. Per-tab: Class Information 420, Schedule 420, Status 478 — it
grows when the tab genuinely needs more room.

### 2. Breakpoint: the sheet is phones only

The sheet geometry moves from `max-width: 768px` to `max-width: 600px`.
From 601px up the pop-up is the centred dialog above.

**This contradicts the shipped stylesheet, which still docks at 768.** The
prototype proved that leaving it alone makes 601–768 a *half-sheet*: square
bottom, stacked footer, 16px padding. The design therefore adds explicit
dialog declarations for that band.

### 3. The two footer bugs

- `.modal-footer` keeps `align-items: center` from the desktop rule, so
  `.modal-footer-left` / `-right` stay shrink-to-fit. A `width: 100%` child can
  then only fill its own 75px wrapper.
- `.btn-danger` is **missing** from theme.css's full-width list, so
  "Cancel Class?" stays 126px while the Close beside it is meant to fill.

Fix: `align-items: stretch` on the sheet footer, stretch both wrappers, and
add `.btn-danger` to the full-width set.

### 4. Flush dock

Drop `.modal-overlay`'s `padding` in the sheet range; keep the existing
safe-area bottom inset on `.modal`.

### 5. The × target

`.modal-close` is 40px because its own `min-height` beats the phone floor
`button { min-height: 48px }`. In the sheet range it becomes 48×48.

### 6. Pinned header/footer

`.modal` becomes a three-row flex column: header (`flex: 0 0 auto`), body
(`flex: 1 1 auto; min-height: 0; overflow-y: auto`), footer (`flex: 0 0 auto`).
Today the whole `.modal` scrolls, so a long tab scrolls the title, the × and
the Close/Cancel actions off-screen.

**This is the highest-risk item** — it restructures the shared shell used by
every pop-up in the app.

## In scope

- `public/css/theme.css` — modal width, sheet breakpoint, footer stretch,
  overlay padding, × size, pinned regions.
- `tests/Feature/ModalConsistencyTest.php` — the `max-width: 680px` regex must
  be rewritten to the new truth (bounded, not fixed).
- `tests/*.spec.ts` — one new spec for the width/breakpoint/pinned behaviour.
- `page-changelogs/` — cross-page entry listing the affected pop-ups.

## Out of scope

- Modal content, wording, tabs, row order.
- The page layouts' own 768 mobile breakpoint (ruling 2 accepts the mismatch).
- `cancelled-slot-reuse` artifacts — the correction is a changelog note, not an
  edit to another lane's frozen files.
- Print layouts.

## Success criteria

1. At 1360 / 1100 / 1024 / 900 / 769 the details pop-up is **narrower than
   680** and its content is not clipped horizontally.
2. At **768** (iPad mini) the pop-up is a **centred dialog**, not a bottom
   sheet; the 591px void is gone.
3. At **601 / 700 / 768** nothing overlaps or clips (the accepted mismatch is
   verified, not assumed).
4. At **600 and below** the sheet is **flush**: side gap 0, bottom gap 0,
   full width.
5. In the sheet range the footer stacks with **both** buttons full width
   (Close and Cancel equal, ~screen width minus padding).
6. In the sheet range the × is **48×48**.
7. With content taller than the viewport, **header and footer stay visible**
   and only the body scrolls.
8. Phone sheet behaviour at 390 is otherwise unchanged from today.
9. No pop-up becomes **wider** than it is today.

## Risks

| Risk | Mitigation |
|---|---|
| Pinning header/footer touches every modal | Verify confirmations, Cancel Class, guide, summary at desktop + phone |
| 601–768 band mismatch | Pinned by test at 601/700/768 (ruling 2) |
| `ModalConsistencyTest` pins 680 as a fixed number | Rewrite to assert floor 420 + ceiling 680 + `fit-content` |
| `cancelled-slot-reuse` A17 says "keep 680px" | Changelog + `theme.css` comment corrections; lane has 0 tasks done, no UI started |
| Narrower box wraps long values into more lines | Already safe: 85vh cap + inner scroll (verified: no clipping, no unexpected scrollbar) |
| Sheet flush edge hides content under a notch | Existing `env(safe-area-inset-bottom)` retained on `.modal` |

## Corrections recorded during the prototype trial

- **An earlier claim of mine was wrong.** I said the tablet cap was 560px. It
  is not: at 900px the details pop-up measures **680**, because
  `.modal.modal--detail` outranks the generic tablet rule. That band was never
  protected.
- **The first implementation pass left 601–768 as a half-sheet.** The shipped
  768 sheet rules still applied; explicit dialog declarations were required.
  This is why ruling 2 demands a test on that band.

## Approval

- User, 2026-11-13: seven rulings above, prototype approved ("yea good").
- Apply to `theme.css` remains gated on explicit approval.
