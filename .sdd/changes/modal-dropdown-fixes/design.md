# Design — dropdown fixes

> **Status:** draft · 2026-11-14

## 1. Escaping the clip (defect 1)

`.modal` and `.modal-body` are the two clippers introduced by the pinned layout.
Both are relaxed **only while a session dropdown inside that modal is open**:

```css
.modal:has(.session-dd.open),
.modal-body:has(.session-dd.open) { overflow: visible; }
```

Why not the alternatives:

- **JS-positioned fixed panel** — accurate, but adds recompute-on-scroll/resize
  code to a page whose dropdown lives inside a modal; more moving parts than the
  defect deserves.
- **Drop-up when short of room** — still clipped once the body's top edge is
  crossed, and it changes which direction the list opens on different screens.
- **Scrolling the body** — the panel is absolutely positioned, so it does not
  extend the body's scroll height; scrolling cannot reveal it.

`:has()` is already trusted here: `theme.css` uses `.hour-cell:has(.cell-empty)`
and `:has(.lane-spacer)`, and the venue page relies on them every render.

Scope: only `.session-dd` — the shared venue dropdown lives in a page header, not
in a pop-up, and is untouched.

## 2. One arrow for the form (defect 2)

```css
.modal .filter-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 34px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239EAAB8' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
}
```

- The data URI is **the sanctioned chevron** already shipped by `.week-select`
  and `.notif-type-select`; copying it introduces no new colour.
- `padding-right: 34px` = 12px inset + 10px glyph + 12px breathing room, so the
  text never runs under the arrow.
- Scoped to `.modal`, so page-level filter selects keep their current look.

Specificity: `.modal .filter-select` (0,2,0) beats `.filter-select` (0,1,0), and it
is declared after the base rule, so ordering is not load-bearing.

## 3. What is deliberately NOT changed

- The pinned header/footer, the 420 floor, the ceilings — untouched.
- The session picker's markup and its `data-tip` rows — untouched.
- The `Cancel` / `Arrange` footer — untouched.

## 4. Verification

Read-only probe on the demo page: open the pop-up at B101, Wed 18 Nov, 12:30;
open the picker; assert every row's bottom is inside the viewport and the panel is
no longer clipped by the body; compare the two arrows' geometry (size, right
inset) before/after; screenshot in both themes.

## 5. Round-1 review notes folded in

- **Phones (`<= 600px`)** — the sheet is flush with the viewport bottom, so an
  escaped panel could draw off-screen. There the clippers stay and the list opens
  **upward** (`bottom: calc(100% + 4px)`, `max-height: 180px`). Measured at 390:
  panel 571–651, trigger 655–692, all rows visible.
- **Light theme** — `.light .modal .filter-select` carries the light stroke,
  mirroring `.light .week-select`.
- **Keyboard** — the picker's list has no keyboard navigation today; unchanged and
  out of scope here.

## 6. Round-2 follow-up — the two arrows must share one inset (user, 2026-11-14)

With the chevron unified, the user spotted that the two arrows still sat at
different distances from the same right edge. Measured: the select's glyph sits
**12px** inside the border (`background-position: right 12px center`), the session
trigger's arrow **25px** (`padding-right: 24px` + 1px border) — 13px apart.

Fix: `.session-dd-trigger { padding: 7px 12px; }` (was `7px 24px 7px 12px`), so both
arrows are 13px from the outer edge. The label keeps `flex: 1` + ellipsis, so no
text can run under the arrow. Measured after: both at 13px; boxes unchanged.
