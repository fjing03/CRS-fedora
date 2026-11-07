# Design

## D1 — arms in the engine

`MatrixIntersectionEngine`'s four range mappers gain `arms`:
`classSessionRangesForLecturer` + `makeUpRangesForLecturer` → `['own']`;
`classSessionRangesForCohorts` + `makeUpRangesForCohorts` → `['cohort']`.

## D2 — the payload's 7th key

`busyByWeekPayload()` emits `[day, start, end, label, statusKind, mine, arms]` (see
`$toRanges`, `$defaultArms`). `dedupeBusyRanges()` still keys on the first five, merges
`mine = OR`, and now unions `arms` — so twins keep both reasons.

## D3 — the resolver

`classifyState(facts)` returns `{cls, label}`; `classifyCell(facts)` keeps its string
contract (the spec's 12 pinned verdicts stay byte-identical) and `cellStateLabel(facts)`
returns the HTML for the span. Precedence, unchanged for the amber family:

1. own pending (grid 3 or a mine pending row) → `cell-pending` / `Your Pending`
2. others' pending (grid 4 or a not-mine pending row) → `cell-reserved` / `Other Pending`
3. red: **own + cohort** → `cell-occupied cell-occupied--mine` / `Your & Cohorts Conflict`
4. red: **own only** → `…--mine` / `Your Conflict`
5. red: **cohort only** → `cell-occupied` / `Cohorts Conflict`
6. red: **venue only** (grid 1, engine quiet) → `cell-occupied` / `Venue Conflict`
7. red: none of the above but blocked → `cell-occupied` / `Occupied` (safety net)

Own = any non-pending row with `mine = true`; cohort = any non-pending row whose `arms`
include `cohort`; venue = `gridStatus === 1`.

## D4 — the span

`buildTimetable()` appends `<span class="cell-state-label">` to a cell that classified. CSS:
hidden until `:hover`, absolute `inset: 0`, 11 px, `line-height: 1.12`, two lines, centred,
`overflow: hidden`, `pointer-events: none`; red family → error surface, amber family →
tertiary-container (**6A**). `.cell-content:has(> .cell-state-label)::after { content: none }`
retires the old overlay exactly where the span is used; the legend swatches have no span and
keep the existing `::after` kill.

## D5 — the legend

`ui-legend-bar`'s arrangement call gains four rows (eight total): Available · Your Current
Selection · **Your Pending** · **Other Pending** · **Venue Conflict** · **Cohorts Conflict** ·
**Your Conflict** · **Your & Cohorts Conflict**, each swatch carrying the real cell class, plus
the ownership hint (`thick border (3px) = yours` / `no border = others'`).

## D6 — receipts

PHP: `MakeUpBusyVectorTest` + `ReplacementArrangementTest` (arms on the rows, union on merge,
7-key tuples). Browser (`tests/arrangement-slot-ownership.spec.ts`, run ALONE): the six
labels via the exported resolver fed real tuples; one REAL hovered cell per family asserting
the visible span text, its box inside the cell, `::after` content `none`, and `data-tip`
unchanged; the eight-row legend.
