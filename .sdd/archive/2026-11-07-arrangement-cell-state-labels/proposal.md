# Proposal — the cell says WHY it is blocked

## Why

`replacement-arrangement`'s hour cells show one word on hover: `Occupied`, `Pending` or
`Reserved`. That hides two facts the page already knows: **whose** block it is and **why** it
is blocked (this room, the cohorts, or your own calendar).

## What (user answers 1A 2A 3A 4A 5B 6A)

| Cell state | Label | Colour |
|---|---|---|
| your pending | `Your Pending` | amber |
| another staff member's pending | `Other Pending` | amber (**6A** — the overlay was grey) |
| venue conflict | `Venue Conflict` | red |
| cohorts conflict | `Cohorts Conflict` | red |
| your own class/replacement | `Your Conflict` (**2A** — a fourth red label the brief omitted) | red |
| yours *and* the cohorts' | `Your & Cohorts Conflict` (**2A**) | red |

- **1A — the reason rides the data.** Each busy row gains `arms` (`own` / `cohort`); dedupe
  unions the arms of merged twins, so a class that is both yours and the cohorts' keeps both.
  The venue arm is the grid payload's `status 1`. No wording is ever parsed.
- **3A — a real element in the cell.** The label becomes a `<span class="cell-state-label">`
  inside the cell (the CSS `::after` overlay is neutralised where that span exists), so
  `<br>` and wrapping work.
- **4A — wrap and shrink, words unchanged**: 11 px, two lines max, clipped inside the cell.
- **5B — the legend speaks the same words**: eight rows, each swatch carrying the real class.
- **6A** — both pending overlays are amber (`--color-tertiary-container`).

## Out of scope

Tooltips (`data-tip`), booking rules, cell colours, the ownership ring, and every other page
(`venue-timetable` keeps its own `--hover-label` copy because its cells carry no span).
