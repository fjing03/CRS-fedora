# Proposal — Replacement Arrangement top zone Round 2

> **Status:** frozen · 2026-11-12
> **Change:** `replacement-arrangement-top-zone-round2`
> **Baseline:** commit `7692d80` (the verified two-card top zone)
> **Explore brief:** `.sdd/changes/replacement-arrangement-top-zone-round2/explore-brief.md`
> **Visual contract:** `../ui-prototype/replacement-arrangement-top-zone-preview.html`

## Why

The two-card top zone separated the replacement controls from the conflict
strip, but it still packs the selected class's facts into the Conflict Schedule
card and leaves the Selection Summary below the grid even when there is nothing
to show. The approved Round2 prototype fixes that by giving the facts their own
card, giving the two schedule cards independent side rails, and moving the
existing Summary into a conditional fourth card.

The user chose:

- **Move** the existing Selection Summary into the top zone; do not create a
  second summary.
- Apply the desktop changes now; the live phone top zone stays with the
  `arrangement-mobile-day-view` owner and gets a separate follow-up.

## 1. Desktop grid law

The wrapper is `.toolbar-restructure.ra-top-zone`. State is carried by the
wrapper classes `has-summary`, `conflict-collapsed` and `details-collapsed`.
A card never resizes its own track.

| Width | No summary | Summary showing |
|---|---|---|
| `<= 1100px` | 1 column; all cards full width; rail classes do not create a rail | 1 column; Summary is the last row |
| `1101–1319px` | `8fr 5fr 7fr`; conflict rail `56px 5fr 7fr`; details rail `8fr 56px 7fr`; both `56px 56px 7fr` | Same three-card setup row; Summary spans row 2 (`grid-column: 1 / -1`) and is full width |
| `>= 1320px` | `8fr 5fr 7fr`; conflict rail `56px 5fr 7fr`; details rail `8fr 56px 7fr`; both `56px 56px 7fr` | `8fr 5fr 8fr 280px`; conflict rail `56px 5fr 8fr 280px`; details rail `8fr 56px 8fr 280px`; both `56px 56px 8fr 280px` |

- The setup-card split is 40 / 25 / 35 when the Summary is not in the row.
- At 1101–1319px the Summary **drops below** the three setup cards. This
  corrects the prototype's all-stack band; it preserves the 40 / 25 / 35 row
  at 1200px and matches the user's "stacks below 1320px" ruling.
- At `>= 1320px` the Replacement card grows from `7fr` to `8fr` when the
  Summary appears; that reflow is intentional.
- At `<= 1100px` the Summary is the last stacked card. The rail classes do not
  shrink a full-width card.
- Card chrome stays `--radius-lg`, 1px `--color-outline`, `--shadow-sm`,
  padding `12px 16px`.

## 2. Class Details card

- New card root: `section.ra-block.ra-class-details`, `id="classDetailsCard"`,
  `aria-labelledby="classDetailsHeading"`. It is a sibling of `#subjectInfo`.
- Keep the existing inner `id="details"`, `#detailsToggle`, `#detailsBody`,
  `#detailsSummary`, `#detailReason`, `#detailCohorts`, `#detailStudents`.
  `id="details"` stays on the inner details container, **not** on the card
  root.
- New empty node: `#detailsEmpty` with
  `Select a course and conflict slot to see the class details.` It is hidden
  when a slot exists.
- The card is always visible on desktop. The empty branch of
  `renderTitleSummary()` must:
  - keep `#classDetailsCard` visible;
  - show `#detailsEmpty`;
  - hide `#details`, the toggle and the body;
  - remove `.has-slot`;
  - never set `hidden` on `#classDetailsCard`.
- With a slot: hide `#detailsEmpty`, add `.has-slot` to `#classDetailsCard`,
  show `#details`, expand the body by default, show the chevron, and allow
  independent collapse.
- Heading: muted info icon + `Class Details`, 13px / 600 uppercase.
- Details body stacks vertically in the narrow 25% card; at `<= 1100px` it
  wraps as one row of whole items.

## 3. Conflict Schedule card

- `#subjectInfo` stays the Conflict card and contains the heading,
  `#conflictCollapse`, `#conflictCardBody`, the collapsed summary and the
  Course/Slot controls only.
- Keep `#conflictCollapse`, `#conflictCardBody`, `#conflictCollapsedSummary`,
  `#conflictSummaryLine1`, `#conflictSummaryLine2`, `#subjectSelector`,
  `#slotPicker`, `#slotTrigger`, `#slotTriggerText`, `#slotTriggerLine1`,
  `#slotTriggerLine2`, `#slotPanel`.
- Desktop collapse uses the wrapper class `conflict-collapsed` and a **56px**
  rail; the collapsed summary keeps its two-line `CODE (T) · W17 · Fri, 19 Nov`
  + `time ~ time @ venue` shape.
- The duplicate course-name line is not restored; the Course select is the
  only course name.
- Arrow law:
  - below 1101px: open = up, closed = down;
  - desktop: open = left, closed = **right** (the prototype's
    `.conflict-collapsed` rule); Class Details uses the same law.

## 4. Replacement Schedule card

- Visible labels become **Venue** and **Week** (drop “Replacement” from those
  two labels).
- Keep the shared `VenueDropdown`, description chip, week nav and IDs:
  `#buildingSelectorDropdown`, `#venueChipGroup`, `#weekSelector`,
  `#earliestBtn`.
- Week select keeps `Week 9 · 16 Nov 2026 ~ 22 Nov 2026`; no separate range
  line.
- `Earliest bookable` stretches to the full card width.
- Venue panel:
  - each `.venue-col` is `width: 150px; min-width: 150px; max-width: 150px`
    with `10px` side padding; the panel is therefore about **603px** including
    its borders;
  - the panel is page-local and capped at `max-width: calc(100vw - 24px)`;
  - above 768px the four-column panel stays and shifts sideways so it remains
    12px inside the viewport; at 769–900px the clamped four-column panel is
    still used;
  - `<= 768px` is the mobile lane; this change does not add the compact sheet;
  - other pages' venue dropdowns stay byte-identical.
- The week-select chevron must survive page-local background overrides: use
  `background-color`, never the `background` shorthand.

## 5. Conditional Selection Summary

- **Move** the existing `#selSummary` block into the top zone as the fourth
  card. Its live markup is the one that ships; the prototype's
  `.ra-selected-slot` / `.ra-selection-btn` markup is **not** copied.
- `#selSummary` is the card root and gets `.ra-block.ra-selection-summary`
  alongside `.sel-summary`; drop the old `margin-top`, border, radius and
  shadow so the 12px grid gap is the only spacing and the card chrome matches
  the other three.
- Keep every existing ID and control: `#selSummary`, `#summaryEmpty`,
  `#summaryGrid`, `#summaryInfo`, `#infoTotal`, `#infoDuration`,
  `#infoBuilding`, `#summaryTip`, `#submitTip`, `#submitRequestBtn`, and the
  `.sel-summary`, `.sel-summary-card`, `.sel-summary-info`,
  `.sel-summary-action` classes used by tests.
- Visibility: `updateCounter()` toggles `.has-summary` on the top-zone
  wrapper. `updateSelectionSummary()` builds the cards. **Do not use the
  `hidden` attribute on `#selSummary`** — the mobile adapter captures and
  restores `container.hidden`.
- CSS: `.ra-top-zone:not(.has-summary) .sel-summary { display: none; }`.
  The existing `#summaryEmpty` node stays for JS compatibility but is never
  shown in the top zone.
- At 280px, compact the live summary: header `12px 14px`, one selection-card
  column, info rows stacked, Tip + Submit stacked or wrapped, no internal
  overflow.
- `>= 1320px`: 280px fourth column. `1101–1319px`: Summary spans row 2 full
  width. `<= 1100px`: last stacked card.
- The prototype's preselected `B100` example is prototype-only; live uses the
  real saved selection.

## 6. Arrows and labels

- Both card arrows use the flexible rotation in §2/§3.
- The `.week-select` custom arrow remains the shared 10×6 chevron.
- No new icons or dependencies.

## 7. Mobile compatibility

- Do not redesign the live mobile top zone in this lane.
- Because `#details` leaves `#subjectInfo`, add **one** `RELOCATION_PLAN`
  entry: `.ra-class-details` → `[data-arrangement-mobile-context-facts]`,
  placed after `#subjectInfo`. This preserves Reason/Cohorts/Students in the
  mobile Original class panel instead of silently deleting them.
- Hide the new card's desktop heading/chrome on mobile so the context facts
  keep their current row. With no slot, hide the whole card on mobile
  (`body.arrangement-mobile-mode .ra-class-details:not(.has-slot)`); do not
  show `#detailsEmpty` inside the Original class panel. With a slot, the
  relocated card shows Reason/Cohorts/Students.
- `tests/arrangement-mobile.spec.ts` is updated for the now-conditional
  Summary display at 769px. The mobile layout itself is not redesigned.
- Preserve `.toolbar-restructure`, `.toolbar-filters .week-nav`, `#subjectInfo`
  and every control ID so relocation still finds them.

## 8. DOM and function contract

- **Move controls; do not copy them.** Existing IDs stay unique.
- `#subjectInfo` is the Conflict card only; `#classDetailsCard` is its
  sibling. `#classDetailsCard` is the always-visible card; `#details` is the
  inner details container.
- `.toolbar-restructure` remains the card wrapper and mobile hide hook.
- `.toolbar-filters .week-nav` remains the mobile relocation hook.
- `renderTitleSummary()` must not rebuild the card with `innerHTML`; it updates
  the dynamic Details and collapsed-summary parts only.
- The live function names are `updateSelectionSummary()` and
  `updateCounter()`; the latter owns `.has-summary`.
- The shared `VenueDropdown` owns the panel markup; only page-local column
  width/padding and fit logic change.

## 9. Supersedes Round 1

This proposal deliberately replaces these frozen Round1 requirements:

- **R1 "two cards"** → three setup cards plus the conditional Summary.
- **R4 "Details row inside the Conflict card"** → separate Class Details
  card; the inner `#details` remains the details container.
- **R9 "heading, selectors and Details stay inside `#subjectInfo`"** →
  Details leaves `#subjectInfo`; one mobile relocation entry is added.
- **R10 "Selection Summary unchanged"** → moved into the top zone and hidden
  when empty.
- **Round1 review "leave `RELOCATION_PLAN` untouched"** → the single
  `.ra-class-details` entry above is the deliberate exception.

## In scope

- `resources/views/livewire/replacement-arrangement.blade.php`: top-zone
  markup, page-local CSS, Details placement, Summary move, collapsed state,
  arrows, labels and venue panel fit.
- `public/js/arrangement-mobile.js`: one `.ra-class-details` relocation entry
  and any hook-preservation fix; no mobile redesign.
- `page-changelogs/replacement-arrangement-changelog.md`: dated entry.
- Tests: `tests/arr-slot-original-venue-sync.spec.ts`,
  `tests/default-pin.spec.ts`, `tests/rearrangement-toolbar-tooltips.spec.ts`,
  `tests/replacement-strip-anchor.spec.ts`,
  `tests/venue-cross-week-handoff.spec.ts`,
  `tests/arrangement-earliest-week-default.spec.ts`,
  `tests/arrival-grid-no-pin.spec.ts`,
  `tests/clear-guards-cross-week.spec.ts`,
  `tests/session-arrival-pin-guard.spec.ts`,
  `tests/arrangement-mobile.spec.ts` and the new
  `tests/top-zone-round2.spec.ts` matrix spec.
- SDD artifacts for this change.

## Out of scope

- Live mobile top-zone redesign or compact venue sheet at 768px and below.
- The timetable grid, legend, booking rules, submit path, cancel-class lane,
  API surface and database.
- Other pages' venue dropdowns.
- The desktop prototype file itself.
- Any schema or demo-DB write.

## Success criteria

1. Grid matrix passes: at 1360px no summary `8/5/7`, summary `8/5/8 + 280`;
   at 1200px no summary `8/5/7`, summary `8/5/7` with Summary full-width on
   row 2; at 1100px one column; rails at 1200/1360 use 56px tracks.
2. Class Details is always mounted and visible; empty guidance with no slot;
   Reason/Cohorts/Students and independent collapse with a slot.
3. Conflict Schedule contains only Course/Slot and its collapsed summary;
   collapsing it does not change the Class Details state.
4. Conflict arrow matches Class Details: up/down below 1101px and left/right
   on desktop (closed = right).
5. Labels read `Venue` and `Week`; Earliest bookable is full width; the week
   select keeps its system label and chevron.
6. Above 768px every visible venue column is exactly 150px; all four are
   150px once a room is picked, and the panel stays 12px inside the viewport
   without clipping a column; other pages are unchanged.
7. The existing `.sel-summary` is the fourth top card, hidden when empty via
   `.has-summary`, 280px at 1320px and above, row 2 full width at
   1101–1319px, and stacked at 1100px and below.
8. `#selSummary`, `#summaryEmpty`, `#summaryGrid`, `#summaryInfo`,
   `#infoTotal`, `#infoDuration`, `#infoBuilding`, `#summaryTip`, `#submitTip`,
   `#submitRequestBtn` and every existing control ID still work; no duplicate
   summary.
9. The mobile Original class panel still shows Reason/Cohorts/Students through
   the new `.ra-class-details` relocation entry; `tests/arrangement-mobile.spec.ts`
   is updated for the conditional desktop Summary at 769px.
10. The named top-zone specs, including the new `tests/top-zone-round2.spec.ts`
    matrix spec, pass; no new failures caused by this change.

## Dependencies and constraints

- Desktop baseline commit `7692d80`; this change applies on top of it.
- `arrangement-mobile-day-view` remains the mobile owner.
- Use only `theme.css` custom-property tokens; no hardcoded colours.
- No new dependencies; no DB/schema changes.
- After the Blade change: `php artisan view:cache` + `view:clear`, then restart
  through `scripts/dev-serve.sh`.

## Risks

| Risk | Mitigation |
|---|---|
| Moving `#selSummary` breaks summary JS | Keep every ID and `updateSelectionSummary`/`updateCounter`; test remove/Submit/Tip |
| `hidden` fights the mobile adapter | Use the wrapper `has-summary` class, never `hidden` on `#selSummary` |
| Moving Details breaks mobile facts | Add the single relocation entry and update the mobile spec |
| Three cards push the grid down | Reuse compact card padding; side rails give space back |
| Class Details and Conflict states get coupled | Separate wrapper classes and `aria-expanded`; test all four states |
| Venue panel shift detaches from trigger | Clamp only when needed; keep trigger anchor when it fits |
| Date/arrow regressions | Keep day-first `weekData` law; test open/closed at mobile and desktop |

## Approval

- User approved the Round2 prototype and the Summary move on 2026-11-12.
- User chose desktop now, mobile follow-up on 2026-11-12.
- The `.ra-class-details` relocation entry is a compatibility hook, not a
  mobile redesign.
