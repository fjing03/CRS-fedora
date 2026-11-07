# Spec — arrangement-slot-styling

Delta on the existing `replacement-arrangement` grid behaviour. Only the statements below change;
every other rule of the page (grid geometry, lead-time cells, PH/Sunday cells, selection mechanism,
tooltip content, summary semantics, handover URLs) is unchanged.

Vocabulary: **own** = the row belongs to the viewing lecturer's own calendar (`mine = true`, from
`lecturerBusyRanges()` or a cohort make-up whose proposer is the viewer). **others** = every other
blocking cause.

## Requirement R1 — Ownership is data, never inferred from colour or text

- Every busy-range row produced by `MatrixIntersectionEngine` MUST carry a `mine` boolean.
  `lecturerBusyRanges()` rows are `true`; `cohortBusyRanges()` class-side rows are `false`; its make-up
  rows are `true` iff `replacement_requests.proposer_id` equals the viewer id passed in.
- `cohortBusyRanges()` MUST accept an optional viewer id defaulting to `null` (no viewer → every row
  `false`), so enforcement callers are unaffected.
- The page's busy tuples MUST carry `mine` as their **6th** key; keys 0–4 keep today's meaning.
- Rows that are the same blocking fact (identical day, start, end, label, status kind) MUST be merged
  to **one** row with `mine = any(...)` **inside `busyByWeekPayload()` before the tuples are shipped**
  (`dedupeBusyRanges()`), so the client never sees twins and the cell tooltip keeps counting one
  class. The engine itself keeps emitting twins — they are the honest facts it holds.
- Scenario: my own AMSE2003 practical half-hour whose cohort is also a selected cohort → one row,
  `mine = true`; the same half-hour viewed by another lecturer → one row, `mine = false`.

## Requirement R2 — One classification per cell

- The cell's class MUST be chosen by a single pure resolver from `{grid status, engine matches,
  engineBlocked}`, in this precedence:
  1. `pendingMine` (grid status 3, or any pending match with `mine`) → `cell-pending`;
  2. `pendingOthers` (grid status 4, or any **engine** pending match with `mine === false` — a
     **missing** 6th key is not "others" and MUST NOT produce an amber answer) → `cell-reserved`;
  3. `conflictMine` (any non-pending match with `mine`) → `cell-occupied cell-occupied--mine`;
  4. `blocked` (`engineBlocked` or grid status 1) → `cell-occupied`;
  5. lead-time rule → `cell-too-soon`; 6. otherwise `cell-available`.
- **Red cells only:** a `.cell-occupied` cell MUST be 3px iff **any** blocking row is own; the
  presence of an others row never removes that 3px (the red rule asks "is any row mine?", never "is
  any row not mine?"). The amber thicknesses are *not* decided here — they are fixed by state in R3
  (`cell-pending` = 3px, `cell-reserved` = no border), so a cross-family cell (my class + another
  staff's pending → amber, no border) and a grid-arm-only own pending (3px, no engine row) are both
  correct under this clause.
- Scenario: my own class plus another staff's pending in the same half-hour → rule 2 (amber, no
  border); my own class plus my cohort's class → rule 3 (red, 3px); another lecturer's class alone →
  rule 4 (red, no border).

## Requirement R3 — The visual law

- Pending family: `--color-tertiary-container` fill for both states; **3px `--color-tertiary`** border
  for own pending, **no border** for others'.
- Conflict family: `--color-error-container` fill for all; **3px `--color-error`** border for own
  conflicts (own class / own approved make-up), **no border** for cohort conflicts, the other
  lecturer's class sitting in the room, an overlapping block, and venues that cannot host the session
  type or headcount.
- Colours MUST come from the CSS custom properties; no literal colour, no new token, and
  `--color-warning` MUST NOT be used on this grid.
- The 3px modifier MUST be added to the base `cell-occupied` class, never replace it.
- A 3px border MUST NOT change the grid geometry or clip the cell's time label (`border-box`).

## Requirement R4 — The legend matches the grid

- The legend MUST render six rows whose swatches carry the **real** classes:
  `cell-available` (Available), `event-selection` (Your Current Selection), `cell-pending`
  (Pending (You)), `cell-reserved` (Pending (Others)), `cell-occupied cell-occupied--mine`
  (Conflict (Yours)), `cell-occupied` (Conflict (Others)).
- Each row MUST keep a `data-tip`. Emphasis rides **capitals** (`FREE SLOT — CLICK`, `SELECTED`,
  `WAITING`, `CANNOT BOOK`, `VENUE CONFLICT`, `COHORT CONFLICT`) — a `data-tip` is text, so a markup
  tag would print literally.
- The **Conflict (Others)** tip MUST be **two lines**, separated by a newline character inside the
  string (the engine renders it with `pre-line`). It MUST NOT contain `<br>` or any other markup.
- The **Conflict (Yours)** tip MUST NOT claim cohort ownership; it names "your own Normal class or
  Replacement class" (the other pages' words) and the `thick border (3px)`.
- The **Conflict (Others)** tip MUST name exactly two causes, in the page's own vocabulary:
  **VENUE CONFLICT** (this slot is used by another class in this room) and, on the second line,
  **COHORT CONFLICT** (the related cohorts have another class at that time). It MUST NOT mention a room-type/suitability case —
  the user dropped that clause (2026-11-06); wrong-room cells stay red and are explained by the cell
  tooltip only.
- Both "yours" rows MUST state the thickness as `thick border (3px)`; the Pending (Others) row states
  `no border`.
- The bar MUST show an ownership hint reading `thick border (3px) = yours · no border = others'`,
  including a **borderless** demo swatch — the 0.5px `osd-thin` demo MUST NOT appear on this page.
- The shared partial MUST keep its current output byte-identical for `ownershipHint => true`
  (`cohort-timetable`, `venue-timetable`), including the 0.5px demo and its wording.

## Requirement R5 — Counters keep their meaning

- The summary counters MUST keep counting by state, not by thickness: an own-pending cell counts as
  Pending; an others'-pending cell counts as Reserved → Unavailable; every red cell counts once as
  Occupied.
- Scenario: fixing a mis-coloured others'-pending cell MUST move one unit from Pending to Unavailable
  and leave the total unchanged.

## Requirement R6 — No writes, no cross-page change

- No schema change, no seed change, no demo-DB write; the browser receipt works on the disposable
  testing DB with its own fixture, cleaning up exactly the rows it inserted **and restoring any seeded
  row it modified (`time_slots.status`) to its prior value**.
- No other page's rendering, booking rules, tooltips or legend text changes.

## Verification map

| Requirement | Evidence |
|---|---|
| R1 | `MakeUpBusyVectorTest` (flag per producer, viewer default), `ReplacementArrangementTest` (6-key tuples, merged twin, legacy `''` key) |
| R2 | `window.classifyCell()` cases in `tests/arrangement-slot-ownership.spec.ts` (rules 1–4 + the three crossing cases + stale 5-key tuple) |
| R3 | Computed `border-top-width` + class on real cells (own pending 3px / others' pending 0px; own class 3px / cohort 0px), light + dark screenshots |
| R4 | Legend row/class/tip assertions in the spec (the Conflict (Others) tip is two lines split on a newline and holds no `<>`; the Conflict (Yours) tip claims no cohort and states 3px; the Available and Selection tips carry their capitals); `UiLegendBarTest` for the partial's two forms plus the rendered HTML of `cohort-timetable` / `venue-timetable` (testing DB, session-authenticated) |
| R4 | Legend-swatch sanity: `.event-selection:hover` swaps its border to `--color-error` (`theme.css:2541–2546`). **Decided:** the legend swatch keeps its primary border on hover via one scoped rule (`legend-swatch` only, the grid's deselect affordance untouched), so hovering the row never reads as a conflict |
| R5 | Resolver test (every state maps to exactly one counter class) + changelog note (the summary bar is commented out today) |
| R6 | Spec header guard (`abortUnlessTestingTarget`), fixture cleanup, demo-facing files untouched |
