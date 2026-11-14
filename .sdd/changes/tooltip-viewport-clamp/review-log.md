# Review log — tooltip-viewport-clamp

## Batch review — 2026-11-14 (independent, read-only)

Reviewer: `commandcode/z-ai/glm-5.3-flash`.

| Artifact | Verdict |
|---|---|
| `proposal.md` | PASS (one note) |
| `design.md` | FAIL → fixed |
| `specs/tooltip-clamp/spec.md` | PASS (non-blocking additions) |
| `tasks.md` | FAIL → fixed |

Diagnosis confirmed in the tree: `.available-tooltip` is `position: fixed`,
`min-width: 250px`, **no** `max-width`, and `showAvailableTooltip()` pins it to
`rect.left` / `rect.bottom + 4`. Measure-then-clamp is the right algorithm and
`getBoundingClientRect` is transform-invariant.

### Blocking issues → all fixed before apply

1. **Stale rectangle.** The design stored `tooltip._anchor = rect`; a rect cannot be
   re-measured and cannot answer "is the cell still there". Now the **cell element**
   is stored and re-measured.
2. **The keyboard path bypassed placement.** `showAvailableTooltip(null, …)` used a
   centred fallback, so R1 would fail there. Both paths now go through
   `placeAvailableTooltip()`.
3. **The pop-up vs the tooltip.** The overlay is z-index 999, the tooltip 9999, and
   `openIntentSessionModal()` never hid it. It now hides the tooltip first.
4. **Tasks named `view:cache`** — corrected to `view:clear` (the blade is cached).

### Non-blocking notes → handled

- `100vw` includes the scrollbar, so the CSS ceiling alone can be ~15px generous —
  the JS clamp uses `innerWidth` and keeps both margins at 8px, so the browser is
  correct even where the CSS is optimistic.
- No collision with the `<=600px` / `<=768px` theme blocks (they never touch
  `.available-tooltip`).

### Found while applying (not in the review)

The clamp loop ended exactly on the window edge because a shrink-to-fit fixed box
widens as it moves away from the edge. Measuring at `left: 8px` first — the widest
the window allows — makes the width final; the clamp then always leaves room for it.
Recorded in design §3.

## Freeze — 2026-11-14

All four artifacts frozen; `implementation_approved: true` (user reported the three
symptoms and asked for the fix).
