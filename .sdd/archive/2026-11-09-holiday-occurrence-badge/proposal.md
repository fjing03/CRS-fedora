# Proposal — holiday-occurrence-badge

## Problem (the confirmed phantom occurrence)

The conflict-schedule strip on `/replacement-arrangement` derives its
occurrence line from the recurrence model and never consults the
calendar's holidays. Live receipt (staff 5425, `?session=38&week=14`):

> `Conflict Schedule BMSE2163 — Software Engineering (P)
> Week 14 · Thu, Dec 24 2026 · 12:00 PM–1:00 PM @ B010 …`

while `holidays` holds **W14 day 3 = "Christmas Eve"** and **day 4 =
"Christmas Day"** — the importer drops those days' class rows (91 rows
absent at W14; cs 39/41's Thu/Fri classes among them), the grid renders
the whole day `cell-ph`, and the submit side rejects holiday days
(`MatrixIntersectionEngine.validateSlot` → `holidayDays()`). The strip's
claim is a phantom: that occurrence can never meet, never be replaced,
never submit — yet the strip presents it as a bookable conflict.

This misdirection framed the user's original tooltip bug report.

## What this change does (the FULL and ONLY scope)

One addition, nothing else: **a hover tooltip on the strip's occurrence
line** when the occurrence's week/day is a holiday.

- Text: `· {Holiday label} holiday — no class` rendered when the label
  does not already contain "holiday" (case-insensitive), else
  `· {Holiday label} — no class` — so the frozen receipts read exactly
  `· Christmas Eve holiday — no class` (W14 day 3) and
  `· Deepavali Holiday (In Lieu) — no class` (W8, label already carries
  the word). The leading `·` is part of the sanctioned literal.
- Trigger: `(anchorSlot.week + 1, anchorSlot.day)` matches a holidays
  entry (`anchorSlot.week` is the 0-based weekData index; `dayIndex` is
  the 0-based day index the holidays payload already uses — the same
  convention cohort-timetable's whole-day `cell-ph` renders from: the
  convention witness). Multiple rows on one `(week, dayIndex)`: the
  FIRST payload match wins (the demo table holds one per entry today).
- The attribute is (re)computed inside `renderTitleSummary()` so every
  anchor change re-derives it; normal occurrences attach NO tooltip.
- Text is never hardcoded: it always reads the holidays record.

## Decisions locked by the user (2026-10-27)

1. **Tooltip-only**: "for Q3, i ONLY allow u to edit or add it's
   hovertooltip, other design keep-as-it" — the strip line stays
   byte-identical visually (NO icon, NO marker, NO styling). Discovering
   the tip requires hover; the user accepted that trade-off explicitly.
2. **The Earliest-Bookable default landing stays**; `?week=` keeps NOT
   moving the selector (`ARRIVAL-GRID-NO-PIN` untouched).
3. The server's holiday-day rejection remains the backstop — no rule
   change.

## Out of scope

- No strip layout/icon/color/copy change; no occurrence-picker or slot
  panel change; no server/engine/schema change; no new dependency.
- No change to the pinned-occurrence derivation itself (the strip keeps
  advertising the phantom — only the hover truth is added).

## Risks & constraints

- The strip is rendered via `innerHTML` — the tooltip attachment must
  ride the same render pass (one string append in the `slotLine`
  element's attributes), not a separate post-walk.
- The existing `initDataTipTooltips()` global layer serves `data-tip`
  attributes dynamically; the apply stage verifies it sees
  re-rendered attributes (receipt in verify, Playwright hover).
- The holidays payload only exists for the arranged semester; absence of
  a semester entry must degrade to "no tooltip" (never an error).

## Success criteria

- Hovering the `Week 14 · Thu, Dec 24 2026 …` occurrence line shows
  exactly `· Christmas Eve holiday — no class`; the W14 Friday anchor
  shows `· Christmas Day holiday — no class`; the W8 Monday anchor shows
  `· Deepavali Holiday (In Lieu) — no class` (the same code path, a
  different payload entry — the dayIndex off-by-one class of bug dies
  here); a non-holiday anchor (e.g. Week 7) shows no tooltip; the
  strip's visible `textContent` unchanged pre/post in the Playwright
  receipt (the mechanical check).
- Playwright receipt (a small spec) + no regression in the full suite;
  pint/phpstan untouched-but-green (the change is client-side Blade/JS).
