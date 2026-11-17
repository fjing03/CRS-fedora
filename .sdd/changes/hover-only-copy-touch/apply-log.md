# Apply log — P2 pointer contract (2026-11-17)

Applied after round 4, with the owner's explicit go. Five files of code, one test updated.

## What changed

| # | File | Change |
|---|---|---|
| 1 | `resources/views/partials/ui-legend-bar.blade.php` | `.legend-item` gets `tabindex="0"`, `role="img"`, and `aria-label="{{label}}: {{tip}}"` **only when it carries a tip** — so the announced name carries the colour word (the tip alone never says which colour), and no unlabelled tab stop exists |
| 2 | `public/css/theme.css` | the hint swap re-keyed `(hover: none)` → `(any-pointer: coarse)`; `.legend-item` minimum 48×48 under the same query; `.legend-item:focus-visible` ring; `.legend-item` **removed** from the `max-width: 1024px` 40px block (see the receipt below) |
| 3 | `resources/views/livewire/student-my-timetable.blade.php` | guide text → "Hover, tap or focus a colour in the legend…" |
| 4 | `resources/views/livewire/replacement-arrangement.blade.php` | the two legend tips `HOVER the slot` → `OPEN the slot's tooltip`; `#submitTip` gains `aria-label` and a `tabindex` synced **from `btn.disabled`** inside `updateCounter()`; `.btn-tip:focus-visible` ring |
| 5 | `tests/arrangement-slot-ownership.spec.ts:853` | the legend-tip assertion demanded the word `HOVER`; it now demands `OPEN` and the absence of the old verb |

## The failure the walks caught — and why it mattered

First run of the 48×48 walks: **5 failed**, all my `.legend-item`: `64×40`, `97×40`, `70×40`, `167×40`
inside the week summary sheet at 390 / 744 / 1024.

Cause: `@media (max-width: 1024px)` sets `.legend-item { min-height: 40px }` (theme.css ≈5167) and it
sits **later in the file** than the capability floor, so a width gate beat the capability law by source
order. This is the exact trap the same block's own comment records for `.badge` (task 11, 2026-11-17).

Fix: `.legend-item` left that width-gated block, with the reason written beside the `.badge` precedent.
On a fine pointer a legend row needs no floor; on a coarse one the capability block owns it.

## Receipts

| Check | Result |
|---|---|
| `tests/timetable-responsive.spec.ts` + `tests/cohort-venue-responsive.spec.ts:1738` (the two walks) | **42 passed** after the fix (5 failed before it) |
| `tests/arrangement-slot-ownership.spec.ts` | **7 passed** (after the tip-copy assertion update) |
| `tests/pages-parity.spec.ts`, `tests/status-tooltips-filter-chips.spec.ts` | passed |
| `tests/timetable-print.spec.ts` | 15 passed, **1 pre-existing failure** — PR-02. Control run with my code stashed: **still fails**, so it belongs to the cohort/venue port, not to this lane |
| desktop 1440, mouse | hint "HOVER", legend item `105×16` (no floor on a fine pointer), `tabindex=0`, `role=img`, `aria-label="Normal Class: Scheduled class with no issues"` |
| touch laptop 1440 (`hasTouch`) | hint **"TAP"**, item `105×48`, **tap shows the tip** |
| `documentElement.scrollWidth` | unchanged at 1024 / 1440 |
| arrangement `#submitTip`, nothing picked | `disabled=true`, `tabindex=0`, `aria-label` = the visible tip |
| arrangement tips | both read `OPEN the slot's tooltip`; the multi-line break survives |

## Known gap (measured, not assumed)

**The enabled half of AC-4 was not reachable in the demo data.** Every candidate replacement slot sits
inside the 3-working-day window or is otherwise read-only, so `has` never becomes true and Submit never
enables — the wrapper therefore keeps its tab stop in every state I could produce. The removal branch is a
single `if (btn.disabled) … else removeAttribute` guarded by the same variable that sets `disabled` three
lines above, and it is exercised the moment a bookable slot exists. Recorded rather than claimed.

## What is still out of scope

Phase 2 of the lane: the 8 `<th>` header tips and the 3 badge tips (`badge-holiday`, `status-rejected`,
`badge-outline`). They hold real text and need the same treatment; they live in `ui-common.js` and
`replacement-home.blade.php`.
