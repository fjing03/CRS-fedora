# Design — tooltip viewport clamp

> **Status:** draft · 2026-11-14

## 1. One placement function

```js
/* place the box for an anchor rectangle: below it, pulled inside the window;
   above it when the window is too short below */
function placeAvailableTooltip(rect) {
    const t = document.getElementById('availableTooltip');
    t.style.left = '0px';
    t.style.top = '0px';
    t.classList.add('show');            // measure at its real size first
    const box = t.getBoundingClientRect();
    const margin = 8;

    let left = rect.left;
    left = Math.min(left, window.innerWidth - box.width - margin);
    left = Math.max(margin, left);

    let top = rect.bottom + 4;
    if (top + box.height + margin > window.innerHeight) {
        const above = rect.top - box.height - 4;
        top = above >= margin ? above : Math.max(margin, window.innerHeight - box.height - margin);
    }

    t.style.left = Math.round(left) + 'px';
    t.style.top = Math.round(top) + 'px';
    t.style.transform = '';
}
```

Measure-then-move is what makes it correct at any width: the box's own width is the
input to the clamp, and `max-width` keeps that width inside the window in the first
place.

## 2. CSS ceiling

```css
.available-tooltip {
    max-width: min(340px, calc(100vw - 24px));
    box-sizing: border-box;
}
```

`min()` is already used in this stylesheet. 340px holds the longest demo sentence
(`Arrange a replacement in B010 on Thursday, 24 Dec 2026 at 2:30 PM?`) in 3–4
lines without touching the edges.

## 3. Resize

Round-1 review corrections, all applied:

- **Store the CELL, not a rectangle.** A saved `DOMRect` goes stale the moment the
  grid reflows, and "is the anchor still there" cannot be asked of a rect. The
  element is kept (`tooltip._anchorCell`) and re-measured on resize.
- **One path for clicks and for the `B` shortcut.** The old centred fallback
  bypassed placement entirely; both now call the same `placeAvailableTooltip()`.
- **The pop-up owns the screen.** `.modal-overlay` is z-index 999 and the tooltip
  9999, so `openIntentSessionModal()` now hides the tooltip first — otherwise it
  floats over the dimmed page and a resize would re-place it there.
- **Resize runs after layout** (`requestAnimationFrame`), not during it.
- **A narrowed window can scroll the cell out of sight** (the grid scrolls
  sideways). The box then stays **readable**, clamped inside the window, instead
  of vanishing under the user's cursor — the Arrange it carries still belongs to
  that cell.

### Why the measurement is taken at `left: 8px`

A shrink-to-fit fixed box takes its width from the space left of it. Measuring at
the final spot produced a feedback loop (move left → more room → wider → move
again) that ended exactly on the window edge. Measuring at `left: 8px` — the
widest the window allows — gives the final width, and the clamp then always leaves
room for exactly that width: both margins stay at 8px.

## 4. Kept as-is

- close-on-scroll (both the window and the grid scroller),
- the `data-tip` hover system (separate, already viewport-aware),
- wording, buttons, the pop-up hand-off,
- z-index 9999 and the shadow.

## 5. Verification

Read-only probe at 1440, 1024, 768 and 390: open a cell at the far right, then at
the bottom-right, and assert `box.right <= innerWidth`, `box.left >= 0`,
`box.bottom <= innerHeight`, and that the text is fully inside. Then resize the
window while open and repeat. Screenshots for the report.
