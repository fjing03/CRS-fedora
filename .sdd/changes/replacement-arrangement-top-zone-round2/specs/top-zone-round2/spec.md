# Spec — Replacement Arrangement top zone Round 2

> Change: `replacement-arrangement-top-zone-round2`
> Status: frozen · 2026-11-12
> Source: frozen `proposal.md` + reviewed `design.md`
> Baseline: commit `7692d80`

## R1 — Three setup cards plus conditional Summary

The page shall render three desktop setup cards above the timetable —
**Conflict Schedule**, **Class Details** and **Replacement Schedule**
(`[aria-labelledby="replacementHeading"]`) — and a fourth **Selection
Summary** card only while a replacement slot is selected.

**Scenario: default load, no selection**
- Given the lecturer opens `/replacement-arrangement`
- Then the three setup cards are visible
- And the Selection Summary card is not displayed
- And the old two-card-only layout is gone

**Scenario: a slot is selected**
- Given a Course and a Conflict Slot exist and the lecturer selects a
  replacement slot
- Then the Selection Summary card is displayed as the fourth card
- And clearing the last selection hides it again

**Scenario: mobile**
- Given the viewport is `<=768px`
- Then the mobile day view remains the owner of the phone layout
- And the desktop card stack is hidden as today.

## R2 — Desktop grid and breakpoints

The wrapper `.toolbar-restructure.ra-top-zone` carries `id="topZone"` and the
state classes `has-summary`, `conflict-collapsed`, `details-collapsed`.

All four cards keep `--radius-lg`, a 1px `--color-outline` border,
`--shadow-sm` and `12px 16px` padding via `.ra-block`; the rail variant uses
`12px 8px`.

| Width | No summary | Summary |
|---|---|---|
| `<=1100px` | one column | one column; Summary last |
| `1101–1319px` | `8fr 5fr 7fr`; rails replace the relevant track with `56px` | same three-card setup row; Summary spans row 2 full width; rails still apply to the setup row |
| `>=1320px` | `8fr 5fr 7fr`; rails replace the relevant track with `56px` | `8fr 5fr 8fr 280px`; rail variants use `56px` for the collapsed setup card |

**Scenario: 1360px no summary**
- Then each setup track is within 1px of `total × fr ÷ 20` for `8fr 5fr 7fr`
- And each of the four cards uses the `.ra-block` chrome (`--radius-lg`,
  1px `--color-outline`, `--shadow-sm`, `12px 16px` padding).

**Scenario: 1360px with summary**
- Then the setup cards use `8fr 5fr 8fr` and the Summary is exactly 280px.

**Scenario: 1200px with summary**
- Then the setup cards keep `8fr 5fr 7fr` on row 1
- And the Summary spans row 2 from column 1 to the last column.

**Scenario: 1100px**
- Then every card is full width in one column.

**Scenario: rail states at 1200/1360**
- Given a Course/Slot exists
- When Conflict and/or Class Details are collapsed
- Then the collapsed card's track is 56px and the remaining cards take the
  freed space; no other card changes state.

## R3 — Side rails

- Conflict Schedule and Class Details fold into a 56px vertical rail on
  desktop.
- Rail card padding is `12px 8px`; the head becomes a centered
  `column-reverse` stack, the toggle loses `margin-left:auto`, and the eyebrow
  is vertical (`writing-mode: vertical-rl`).
- The Conflict two-line collapsed summary is shown in the `<=1100px`
  collapsed card; it is hidden inside the desktop 56px rail.
- Details collapse and Conflict collapse are independent; the wrapper classes
  must not be derived from each other.
- At `<=1100px` the rail classes do not shrink a card.

## R4 — Class Details card

- New root `section.ra-block.ra-class-details#classDetailsCard`; inner
  `#details` remains a separate node.
- Keep `#detailsToggle`, `#detailsBody`, `#detailsSummary`, `#detailReason`,
  `#detailCohorts`, `#detailStudents`.
- The card is always visible on desktop.
- No Course/Slot: `#detailsEmpty` is shown; `#details`, the toggle and the
  body are hidden; `.has-slot` is absent; `aria-expanded=false`,
  `aria-label="Expand Class Details"`.
- Course/Slot present: `#detailsEmpty` is hidden; `.has-slot` is present;
  `#details` and the toggle are shown; the body opens by default;
  `aria-expanded=true`, `aria-label="Collapse Class Details"`.
- A manual collapse is preserved across slot changes.
- `#detailsSummary` remains mounted but `hidden`; the card shows no second
  summary line.
- Body layout: stacked on desktop; at `<=1100px` a wrapping row of whole
  items.
- Heading: muted info icon + `Class Details`, 13px / 600 uppercase. The
  `--muted` modifier is `color: var(--color-on-surface-variant)`.

**Scenario: empty**
- Given no Course/Slot
- Then the card and heading are visible, the guidance is visible, and no
  chevron/body is visible.

**Scenario: filled**
- Given a Course/Slot
- Then Reason/Cohorts/Students render and the card opens **only** on the
  empty→filled transition; a later slot change leaves the manual open/closed
  state alone.
- And `#detailsSummary` stays `hidden` with no visible second line.

**Scenario: manual collapse**
- Given a filled card open
- When the lecturer clicks the chevron
- Then the body hides, the card folds to the rail, and `aria-expanded=false`
- And changing to another slot keeps the body collapsed.

## R5 — Conflict Schedule card

- `#subjectInfo` remains the Conflict card mount and contains only its
  heading, `#conflictCollapse`, `#conflictCardBody`, the collapsed summary and
  the Course/Slot controls.
- Keep `#conflictCollapse`, `#conflictCardBody`,
  `#conflictCollapsedSummary`, `#conflictSummaryLine1/2`, `#subjectSelector`,
  `#slotPicker`, `#slotTrigger`, `#slotTriggerText`, `#slotTriggerLine1/2`,
  `#slotPanel`.
- Desktop collapse sets the wrapper `conflict-collapsed` and the card's
  `is-collapsed`; the body hides and the arrow left/right state updates.
- No duplicate course-name line is added.

## R6 — Replacement Schedule labels and controls

- Visible labels are **Venue** and **Week**.
- Keep `#buildingSelectorDropdown`, `#venueChipGroup`, `#weekSelector`,
  `#earliestBtn`.
- The week select shows `Week 9 · 16 Nov 2026 ~ 22 Nov 2026`; no separate
  range line.
- The week select keeps its shared 10×6 chevron; page-local background
  overrides use `background-color`, not the `background` shorthand.
- `.ra-replacement-controls` is a full-width column; the week filters are a
  full-width column; `#earliestBtn` is full width and centred.
- The Conflict card stacks Course over Slot; both controls fill the card
  content width.
- The Venue trigger fills the venue row (no `216px` cap); the description
  chip stays at the row tail.

**Scenario: 1360px filled page**
- Then Course is above Slot, both at the Conflict card content width.
- And the Venue trigger fills the venue row.
- And `#earliestBtn` width equals the Replacement card content-box width
  (`clientWidth − 2×1px border − 2×16px padding`), within 1px.

## R7 — Venue panel fit

- Above 768px every visible `.venue-col` is exactly 150px wide; the
  `.venue-col-item` carries `10px` side padding (the column keeps its
  `4px 0` vertical padding). All four are 150px once a room is picked; the
  panel is about 603px including borders.
- The panel is capped at `calc(100vw - 24px)` and shifts sideways to remain
  12px inside the viewport; no column is clipped and there is no horizontal
  scroll.
- At 769–900px the clamped four-column panel is used.
- At `<=768px` the mobile lane is unchanged; the 150px/panel rules do not
  apply.
- Other pages' venue dropdowns are unchanged: `venue-timetable`'s `.venue-col`
  still resolves `min-width: 200px` from `theme.css` and is not 150px.

**Scenario: 1360px, all four columns**
- Given the panel is opened at 1360px
- When Type, Block and Floor are picked so all four columns are visible
- Then the panel's left edge is at least 12px and its right edge at most
  `viewport − 12px`
- And every column is 150px.

## R8 — Selection Summary moved into the top zone

- Move the existing `#selSummary` block into the top zone; remove the lower
  copy.
- `#selSummary` is the fourth card with `.ra-block .ra-selection-summary
  .sel-summary`; old margin/border/radius/shadow are dropped so the 12px grid
  gap is the only spacing.
- Keep `#selSummary`, `#summaryEmpty`, `#summaryGrid`, `#summaryInfo`,
  `#infoTotal`, `#infoDuration`, `#infoBuilding`, `#summaryTip`, `#submitTip`,
  `#submitRequestBtn` and the `.sel-summary*` classes.
- `updateCounter()` toggles `has-summary` on `#topZone`; no `hidden` attribute
  is set on `#selSummary`.
- CSS hides the card with
  `.ra-top-zone:not(.has-summary) .ra-selection-summary { display:none }`.
- At 280px the interior is compact: header, one-column cards, stacked info
  rows, wrapped Tip/Submit, no internal overflow.

**Scenario: empty**
- Then the Summary card is not displayed.

**Scenario: restored selection on first paint**
- Given a saved selection
- Then `updateCounter()` runs during boot and the card is visible without a
  manual interaction.

**Scenario: clear the last selection**
- Then the card hides and the existing totals/actions stay valid.

## R9 — Arrows and week chevron

- Below 1101px both card chevrons point up when open and down when closed.
- On desktop both point left when open and right when closed (the rail
  direction).
- The toggle `aria-label` and `aria-expanded` stay in sync:
  `Collapse …` when open, `Expand …` when closed.
- The week select keeps its shared background-image chevron.

**Scenario: toggle each card**
- Then the glyph and `aria-label` pair match the state.

## R10 — Mobile compatibility

- The live mobile top zone is not redesigned.
- Add exactly one `RELOCATION_PLAN` entry:
  `.ra-class-details` → `[data-arrangement-mobile-context-facts]`, after
  `#subjectInfo`.
- `DESKTOP_CONTAINERS` remains unchanged; `.toolbar-restructure` still hides
  the desktop stack.
- On mobile, the Class Details desktop heading/chrome is suppressed; the card
  uses transparent chrome.
- With no slot, the card is hidden on mobile and `#detailsEmpty` is not shown
  in the Original class panel.
- With a slot, the relocated card shows Reason/Cohorts/Students even if the
  desktop body had been manually collapsed.
- `.ra-class-details` is added to `T23_NODES`.
- `tests/arrangement-mobile.spec.ts` 769px now expects the empty Summary to
  be `display:none`.

## R11 — DOM and function contract

- `#topZone` exists; one closure-level `const topZone` is declared above the
  first consumer.
- `#subjectInfo` and `#classDetailsCard` are siblings; `#details` remains
  inside `#classDetailsCard`.
- `renderTitleSummary()` never rebuilds cards with `innerHTML`; it updates the
  dynamic Details/summary parts and never hides `#classDetailsCard` on
  desktop.
- `updateCounter()` owns `has-summary`; `updateSelectionSummary()` builds the
  summary cards.
- No duplicate Summary, no duplicate card markup, no copied venue panel.

## R12 — Preserved and superseded behaviour

Preserved:
- Auto-select first conflicted slot, slot picker, venue sync, week navigation.
- Booking rules, submit path, legend, mobile relocation contract.
- `cohortFullNames` and the shared venue label.

Supersedes Round1:
- R1 two cards → three setup cards + conditional Summary.
- R4 Details row inside the Conflict card → separate Class Details card.
- R9 heading, selectors and Details stay inside `#subjectInfo` → Details
  moves to `#classDetailsCard`; the heading/selectors stay in `#subjectInfo`.
- R10 Selection Summary unchanged → moved and conditional.
- The Round1 "leave RELOCATION_PLAN untouched" ruling → the single
  `.ra-class-details` entry.

## Out of scope

- Phone top-zone redesign, compact venue sheet at `<=768px`.
- Grid, legend, booking rules, submit path, cancel-class lane, API, database.
- Other pages' venue dropdowns.
- The prototype file itself.
