# Proposal — keep the Arrange tooltip inside the window

> **Status:** draft · 2026-11-14 · user report with a screenshot (`ui-prototype/arrange toltip.png`)

## Why

Clicking a free venue cell opens the Arrange tooltip. The user reports three
symptoms, all visible in the screenshot with the browser window narrowed by
DevTools:

1. **Horizontal overflow** — the tooltip runs past the right edge and its text is
   cut off.
2. **No re-position on a narrower window** — once open, it keeps the coordinates it
   was given.
3. **No responsive fallback** — it never flips or shifts when there is not enough
   space.

## Cause (read from the code, and measured)

`showAvailableTooltip()` positions the box with `position: fixed` at the clicked
cell's `rect.left` / `rect.bottom + 4`. The box is `min-width: 250px` with **no
`max-width`** and **no viewport clamping**, so a cell near the right edge pushes it
off-screen, and a long sentence wraps only as far as its own max-content allows.

There is a close-on-scroll handler but **no resize handler** for the tooltip, so a
narrowing window leaves it where it was.

## What changes

1. **A real width ceiling** — `max-width: min(340px, calc(100vw - 24px))`, so the
   box can never be wider than the window minus a margin, and long text wraps
   inside it.
2. **Clamp on open** — after showing, measure the box and pull it back inside the
   viewport: `left` between 8px and `innerWidth - width - 8px`; and if it does not
   fit below the cell, place it **above** instead.
3. **Re-place on resize** — remember the anchor cell's rectangle; on `resize`,
   re-run the same placement (or hide the tooltip if the cell is gone). It already
   hides on scroll.

## In scope

`resources/views/livewire/venue-timetable.blade.php` — the tooltip CSS block and
`showAvailableTooltip()` / the resize wiring only.

## Out of scope

The tooltip's wording, its buttons, the booking hand-off, the shared `data-tip`
system, every other page.

## Risks

| Risk | Mitigation |
|---|---|
| A clamped box could cover the cell being viewed | Placement prefers below the cell, then above; horizontal clamping only moves it as far as needed |
| Resize handling could fight the close-on-scroll | Same anchor rectangle is reused; nothing else re-renders |
| The 340px ceiling could clip a long venue name | The box wraps to more lines instead; measured with the longest demo sentence |

## Success criteria

1. At any window width down to 390, the tooltip is fully inside the window, with
   no text cut off.
2. Narrowing the window while it is open keeps it inside.
3. Near the right edge it shifts left; near the bottom it opens upward.
