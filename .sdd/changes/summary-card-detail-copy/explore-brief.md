# Explore brief — summary card back spacing and explanation detail

> Created 2026-11-13. Planning only; no live code changed yet.
> **Re-synced 2026-11-14** against the current working tree.
> Baseline: `fedora-backend` with `my-timetable-ui-cleanup` applied (flip cards
> shipped, uncommitted).
> Reference: `ui-prototype/summary-card-back-preview.html` (repo parent).

## Re-sync summary

Nothing in this lane has shipped. The **back copy** did ship, from another lane:
all six flip pages now pass three short `<strong>`-accented bullets plus an
`explanation` string per card. The tight spacing, the paragraph-only modal, the
missing `detail` array, the missing `.explain-*` rules and the missing sub-390px
column are all still exactly as described below. See `proposal.md`'s re-sync
table and `review-log.md`'s 2026-11-14 entry for the evidence.

## Problem

Two complaints about what `my-timetable-ui-cleanup` shipped:

1. **The card back is too tight.** The user's screenshot of the Teaching Hours
   back shows three lines touching each other. Re-measured 2026-11-14:
   `line-height: 1.15` (13.8px lines), `.summary-points { gap: 0 }`, block
   top-aligned at **7px from the top with 48px of empty space below**.
2. **The explanation modal is one dense paragraph.** The user asked for it to
   "do some work": avoid plain text, use `<br>`, break into a longer bullet
   form. Re-verified 2026-11-14: the modal still renders one
   `DIV.summary-explanation` with 0 `<br>` and 0 `<li>`.

## What exists today

| Piece | Where | Note |
|---|---|---|
| Flip cards | `.summary-section--flip` in `theme.css` | 96px, 3 bullets, 44px info button — **shipped** |
| Card data | `$cards` array per Livewire view | carries `bullets` and `explanation` — **shipped** |
| Shared partial | `partials/ui-summary-bar.blade.php` | prints both faces + the sibling modal shell — **shipped** (105 lines) |
| Modal print | `SummaryCards.open()` in `ui-common.js` | prints `.summary-explanation-source`'s HTML — the `body.innerHTML` line in `SummaryCards.open()` |
| Long copy | `$card['explanation']` per card | one sentence, usually with `<strong>`; present on **every** flip card |
| `detail` array | — | **does not exist** |
| `.explain-*` CSS | — | **does not exist** |

## Constraints found by reading the code

- **96px is fixed.** The user approved it twice; growing it is rejected.
- **The info button cannot shrink.** `my-coding-rules.md` rule 9 requires
  ≥44×44px (WCAG 2.5.5). Moving the corner is the only way to free the text.
- **`card['class']` is not a unique key.** `card-total` covers Total Classes,
  Total Requests, Total Reviewed and Total Slots, and appears twice on
  `request-approval`. Detail data must be per card.
- **No JS change should be needed.** The controller prints a hidden node's
  `innerHTML`, so structured markup can be emitted by the partial.
- **`replacement-history`** still uses the non-flip branch and must keep
  working unchanged.

## Final solution (proposed)

- Move the info button to the **top-right** and give the back
  `justify-content: center`, `line-height: 1.45`, `4px` bullet gap and a
  uniform `38px` right reserve.
- Shorten four bullets (three distinct wordings) on two pages, keeping the full
  wording in the modal. Venue needs no copy change: its 106px bullet fits the
  110px column at a reserve of 38px — but it is the first bullet to re-wrap if
  the reserve goes to 44px.
- Add an optional `detail` array (`formula`, `items`, `foot`) and render a
  formula strip + `term <br> meaning` bullets + footnote in the modal, with the
  current paragraph as fallback.
- **New constraint from the re-sync:** the back face already states the formula
  in words (`Normal + blue` / `+N = pending` / `No red blocks`). The `items`
  must expand those bullets, not assert a different rule.

## Evidence

Prototype measures every card live at 1360px and 390px and reports: lines per
card, space above, space below, and whether any text overlaps the button. All
30 cards were checked in both variants.

Re-measured on the live pages 2026-11-14 with the patch injected: 18px / 18px,
3 lines, 0 overlaps, max spill −12px at 320 / 360 / 375 / 390 / 414 / 768 /
1024 / 1360px across the five staff pages. The student page was checked
separately for structure only.

## Open question left for review

Whether the four shortened bullets should instead be accepted as 4-line cards
on phones. Rejected in design §3 because it reintroduces the density the user
objected to.
