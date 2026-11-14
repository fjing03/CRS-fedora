# Explore brief — Replacement Arrangement top zone Round 2

> Created 2026-11-12. This is the completeness checklist for the proposal.
> Baseline: commit `7692d80` (the two-card top-zone change).
> Visual contract: `../ui-prototype/replacement-arrangement-top-zone-preview.html`.
> No live code is edited during explore.

## Problem

The first top-zone change created two cards, but it still mixes two jobs:

1. **Conflict Schedule** still owns the Course/Slot controls **and** the
   Reason/Cohorts/Students facts. The facts crowd the schedule card and have
   no independent collapse behaviour.
2. **Replacement Schedule** has a long label ("Replacement venue" / "Replacement
   week"), a week select without a reliable custom arrow on the prototype, and
   a venue panel that can overflow the viewport.
3. The **Selection Summary** still sits below the grid even when it is empty.
   It duplicates the job of the new conditional fourth card in the prototype.
4. The first change's two-card layout has no compact side rails for the
   schedule and details cards, so the top zone cannot give space back to the
   schedule the user is working on.

The user approved the Round2 prototype and chose to **move the existing
Selection Summary into the top zone**, not create a second summary.

## Final solution

### Desktop cards

```
┌─ Conflict Schedule 40% ─┐ ┌─ Class Details 25% ─┐ ┌─ Replacement Schedule 35% ─┐
│ Course [...........]    │ │ Reason  [Public ..] │ │ Venue [B100 ....] [i]      │
│ Slot   [(P) Week ..]    │ │ Cohorts [DSF1 ..]   │ │ Week  [Week 9 · ....]      │
│                         │ │ Students [24]       │ │ [ Earliest bookable ]      │
└─────────────────────────┘ └─────────────────────┘ └────────────────────────────┘
```

- Three setup cards use `minmax(0, 8fr) minmax(0, 5fr) minmax(0, 7fr)` — the
  user's 40 / 25 / 35 split — with the existing 12px gap.
- At `max-width: 1100px`, the three cards stack in one column.
- The card chrome stays `--radius-lg`, 1px `--color-outline`, `--shadow-sm`,
  padding `12px 16px`.

### Class Details card

- Extracted from the Conflict Schedule card into its own `section`, with a new
  card root `#classDetailsCard`; the existing `#details` stays the inner
  details container.
- Keeps `#detailsBody`, `#detailsToggle`, `#detailsSummary`, `#detailReason`,
  `#detailCohorts` and `#detailStudents`.
- Always visible on desktop. With no Course/Slot it shows the new
  `#detailsEmpty` guidance, hides the inner `#details`, the body and the
  chevron. With a Course/Slot it shows Reason / Cohorts / Students, expanded
  by default; the user can collapse it independently.
- Desktop: collapsing folds it into a **56px** vertical rail labelled
  `Class Details`; reopening restores the card. Mobile is a later lane, but
  one `.ra-class-details` relocation entry keeps the facts in the mobile
  Original class panel.
- Header is the muted info icon + `Class Details`, same 13px / 600 uppercase
  law as the other cards.

### Conflict Schedule card

- Holds only Course and Slot. The `#subjectInfo` mount stays the conflict card.
- Keeps `#conflictCollapse`, `#conflictCardBody`, `#conflictCollapsedSummary`,
  `#conflictSummaryLine1/2`, `#subjectSelector`, `#slotPicker`,
  `#slotTrigger`, `#slotTriggerText`, `#slotTriggerLine1/2`, `#slotPanel`.
- Desktop: collapsing folds it into a **56px** vertical rail. The existing
  collapsed summary (`CODE (T) · W17 · Fri, 19 Nov` + time/venue) stays.
- Arrow law matches Class Details:
  - below 1101px: open = up, closed = down;
  - desktop: open = left, closed = right (the side-rail direction).
- The duplicate course-name line under Course is not restored; the select is
  the only course name.

### Replacement Schedule card

- Visible field labels are **Venue** and **Week** (drop the word
  “Replacement” from the labels).
- Keeps the shared `VenueDropdown` and week nav, the description chip and the
  existing IDs (`#buildingSelectorDropdown`, `#venueChipGroup`, `#weekSelector`,
  `#earliestBtn`).
- Week select keeps the full system option label
  (`Week 9 · 16 Nov 2026 ~ 22 Nov 2026`); no separate range line. The live
  mobile year-trimming remains a mobile-lane concern.
- `Earliest bookable` stretches to the full card width.
- Venue panel:
  - above 768px, keep the four columns; each column is fixed at `150px` with
    `10px` side padding (panel about 603px including borders);
  - clamp the panel 12px inside the viewport by shifting it sideways; use
    `max-width: calc(100vw - 24px)`;
  - 769–900px keeps this clamped four-column panel; `<= 768px` is the mobile
    lane and is not changed in this lane;
  - the panel is page-local; other pages' venue dropdowns stay byte-identical.

### Conditional Selection Summary

- **Move** the existing `#selSummary` block from below the grid into the top
  zone as the fourth card; remove the lower copy. The live `.sel-summary`
  markup ships, not the prototype's `.ra-selected-slot` markup.
- Keep every existing ID and control: `#selSummary`, `#summaryEmpty`,
  `#summaryGrid`, `#summaryInfo`, `#infoTotal`, `#infoDuration`,
  `#infoBuilding`, `#summaryTip`, `#submitTip`, `#submitRequestBtn`, and the
  `.sel-summary`, `.sel-summary-card`, `.sel-summary-info`,
  `.sel-summary-action` classes.
- Hide the card with the wrapper `has-summary` class when there is no selected
  replacement slot; do not use `hidden` on `#selSummary` (the mobile adapter
  snapshots that attribute).
- `>= 1320px`: fourth grid column fixed at **280px**; setup cards use
  `8fr 5fr 8fr` plus the 280px card.
- `1101–1319px`: the three setup cards keep their 40 / 25 / 35 row and the
  Summary drops below on row 2 (`grid-column: 1 / -1`). This is a deliberate
  deviation from the prototype's all-stack band; it preserves the setup row
  at 1200px and matches the user's "stacks below 1320px" ruling.
- `<= 1100px`: one column; Summary is the last row.
- The prototype's preselected `B100` example is **not** copied to live; the
  live card is driven by the real saved selection.

### Week-select arrow

- The live `.week-select` already gets its chevron from `theme.css`. The
  prototype lost it because a page-local `background:` shorthand erased the
  image. Round2 uses `background-color` only, so `background-image` survives.
- No new icon dependency; the arrow stays the shared 10×6 chevron.

## Desktop and mobile scope

- **In scope now:** desktop (`>= 1101px`) card layout, side rails, summary move,
  venue panel, labels and arrows; the existing `<= 1100px` stack remains.
- **Out of scope now:** the live mobile top-zone redesign. The mobile lane owns
  the phone layout; preserve `.toolbar-restructure`, `.toolbar-filters
  .week-nav`, `#subjectInfo` and every control ID so relocation still finds
  them.
- One compatibility entry is added to `RELOCATION_PLAN`:
  `.ra-class-details` → `[data-arrangement-mobile-context-facts]`, after
  `#subjectInfo`, so Reason/Cohorts/Students do not disappear from the mobile
  Original class panel. The card's desktop heading/chrome is hidden on mobile.
- `arrangement-mobile.js`: add only that one `.ra-class-details` entry; no
  other relocation-plan change.
- A separate mobile follow-up will apply the prototype's phone patterns.

## Rejected approaches

| Approach | Why rejected |
|---|---|
| Keep the two-card layout | The facts still crowd the schedule card; no side rails |
| Duplicate the Selection Summary | Two totals/actions to keep in sync |
| Keep the lower summary and skip the fourth card | Deviates from the approved prototype |
| Compact venue picker at all widths | The user chose the four-column panel above 768px |
| Horizontal scroll inside the venue panel | Hides columns instead of fitting them |
| Fixed arrow directions | The user asked for the Class Details-style flexible arrow |
| Change mobile now | `arrangement-mobile-day-view` owns the phone layout |

## Cross-module flows and hooks

- `renderTitleSummary()` / `renderDetails()`: move the Details mount into the
  Class Details card without rebuilding the selectors; preserve the existing
  Course/Slot state and empty guidance.
- `updateSelectionSummary()` / `updateCounter()`: add/remove the top-zone
  fourth-card visibility class; keep `#summaryGrid`, `#summaryInfo`, totals,
  Tip and Submit behaviour unchanged.
- `#subjectInfo` remains the Conflict Schedule mount; the Class Details card is
  a sibling, not a child of `#subjectInfo`.
- `.toolbar-restructure` remains the desktop card wrapper and mobile hide hook.
- `.toolbar-filters .week-nav` remains the mobile relocation hook.
- Shared `VenueDropdown`: page-local column width/padding and panel fit only.
- `WeekNavigator` / `populateWeekSelect`: no data changes; labels already come
  from `weekData`.
- `arrangement-mobile.js`: add only the one `.ra-class-details` relocation
  entry; no other relocation-plan change.

## Open questions

- None for desktop. The mobile follow-up owns its own brief.
