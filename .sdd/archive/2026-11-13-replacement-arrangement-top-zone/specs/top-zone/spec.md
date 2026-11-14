# Spec — Replacement Arrangement top zone

> Change: `replacement-arrangement-top-zone`
> Status: proposed · 2026-11-11
> Source: frozen `proposal.md` + reviewed `design.md`

## R1 — Two-card top zone

The page shall render two cards above the timetable: **Conflict Schedule** and
**Replacement Schedule**.

**Scenario: default load**
- Given the lecturer opens `/replacement-arrangement`
- Then both cards are visible
- And the old one-line conflict strip and mixed toolbar layout are gone
- And `.toolbar-restructure` remains only as the mobile-hide wrapper.

## R2 — Card and heading styling

- Cards: `--radius-lg`, 1px outline, `--shadow-sm`, padding `12px 16px`,
  12px gap.
- Headings: 13px / 600, uppercase, `0.08em`, 14px icons.
- Conflict Schedule heading: red (`--color-error` light /
  `--color-on-error-container` dark), warning icon.
- Replacement Schedule heading: primary (`--color-primary` light /
  `--color-on-primary-container` dark), swap icon.
- DOM text stays `Conflict Schedule`; uppercase is CSS.
- The Conflict Schedule heading has a second arrow button on the right. It
  collapses/expands the whole card, is expanded by default, and flips
  `aria-expanded`.
- When collapsed, the card shows a two-line Course/Slot summary:
  - line 1: `BMSE6767 (T) · W8 · Fri, 19 Nov`; the type token is blue;
  - line 2: `2:00 PM ~ 3:00 PM @ [B111]`; the venue is the desktop pill.
  - The summary is hidden when expanded; it is shown on both desktop and
    mobile when collapsed.
- The whole-card arrow is disabled until a Course is selected. A Course with no
  Slot may collapse and shows the Course code only.
- Whole-card collapse and Details collapse are independent; collapsing the card
  preserves the Details open/closed state.
- No fact icons inside the cards.
- The whole-card arrow and the Details row are the only collapse controls.
- Badge/cohort details are hover-only; no extra keyboard tab stops.
- Tooltips use the shared 11px `data-tip` style.

## R3 — Course and Slot controls

- Course: 44px high, 45% width, native dropdown, 13px / 600.
- Slot: 55% width, 44px high, two lines.
- Line 1: `(T) Week 8 · Wed, 11 Nov 2026`; only the type token is blue.
- Line 2: `1:00 PM ~ 2:00 PM @ [B111]`; 12px / 500 muted; venue code in an
  11px / 700 outlined pill. It uses `--color-surface` inside the variant slot
  trigger and `--color-surface-variant` in the collapsed summary.
- Course and Slot field backgrounds use `--color-surface-variant` so they
  stand apart from the `--color-surface` card. The Slot panel stays
  `--color-surface`.
- Slot is hidden until a course is chosen.
- Course change auto-selects the first conflicted slot.
- Replacement venue syncs to the selected slot's room.
- No horizontal overflow at 1280 / 1024 / 900; the Slot panel is
  `width: 100%; min-width: 0`.

**Scenario: pick a course**
- Given no course is selected
- When the lecturer chooses a course with conflicted slots
- Then the first slot is selected automatically
- And the Slot control shows the two lines
- And the Replacement venue switches to that slot's room.

**Scenario: arrival with `?session=`**
- Given the page opens with a pinned session
- Then the Slot trigger shows the type, weekday, date and venue
- And the Details Reason is filled from the same facts as a picked slot.

**Scenario: pick a slot from the panel**
- Given a course with several conflicted slots
- When the lecturer picks a slot row
- Then the trigger updates to the two-line format
- And the Details and Replacement venue update for that slot.

## R4 — Details row

- Details is expanded by default; it collapses/expands.
- Summary is 12.5px / 600 muted.
- Summary full/short, with `reason` = the same token as the Reason badge:
  - full single: `Public Holiday · DSF1 (S1) G1 · 24 students`
  - short single: `Public Holiday · 1 cohort · 24`
  - full multiple: `Public Holiday · 2 cohorts · 25 students`
  - short multiple: `Public Holiday · 2 cohorts · 25`
  - plain clash full: `Conflict · DSF1 (S1) G1 · 24 students`
  - plain clash short: `Conflict · 1 cohort · 24`
- Use the full text when it fits; short only on overflow.
- Re-measure on debounced resize and when the row becomes visible.
- Re-open when the row reappears after a no-slot state; keep a manual collapse
  across slot changes.
- Body columns: `1 : 1.5 : 0.8`.
- Labels: `Reason`, `Cohorts`, `Students`, 10.5px / 700 uppercase muted.
  Values: 12.5px / 400.
- The toggle flips `aria-expanded` and the chevron.
- Empty Cohorts and Students show `—`, not `0`.

## R5 — Reason, Cohorts, Students

### Reason
- One shared `reasonFacts(slot)` with cancelled-before-holiday precedence.
- `Public Holiday` → `badge badge-holiday`, detail = holiday name.
- `Class Cancelled` → `badge badge-reason`, detail = cancellation reason.
- Plain clash (no holiday, no cancellation) → neutral `badge badge-reason`
  with text `Conflict`, no detail. Never `Class Cancelled`.
- Virtual pinned anchor uses the same facts; it has no `status`.
- The heading has no tooltip; the Reason badge is the only reason tooltip.

### Cohorts
- One `badge badge-reason` per cohort.
- Badge text = the existing cohort label (`cohorts[i]`, e.g. `DSF1 (S1) G1`).
- Badge `data-tip` = `cohortFullNames[i]`, falling back to the label.
- 6px gap; wrap to a new line.

### Students
- One `badge badge-reason`.
- One cohort: `24`.
- Multiple: `7 + 18 = 25`.
- The badge shows numbers only; cohort labels live in the Cohorts field.
- The cancelled-before-holiday precedence is checked with a synthetic slot
  object carrying both `holidayName` and `cancelledReason` passed to the
  page-global `reasonFacts`, or a `page.evaluate` `MockData` override. Do not
  seed the demo DB.

## R6 — Replacement Schedule

- Real shared `VenueDropdown` and nested Type / Block / Floor / Room panel.
- Trigger: `B111` at 13px / 600 + `35 seats` at 12px / 500 muted, with an
  8.8px gap between the code and the seat count; the dropdown arrow sits at the
  far right.
- Venue trigger and week select use `--color-surface-variant` so they stand
  apart from the `--color-surface` card. The venue panel and week options stay
  `--color-surface`.
- Trigger tooltip keeps the existing room name + capacity shape.
- Description chip `#arrVenueDescription` stays; `#venueCapacity` is removed.
- Week controls: current arrows, select and `Earliest bookable`.
- Week options: `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`.

## R7 — Slot picker

- Keep the live one-label option row (month-first date shape).
- Badge text: `Public Holiday` / `Class Cancelled` / `Conflict`.
- Badge uses the shared `reasonFacts`; holiday name or cancellation reason on
  hover; no detail for a plain clash.
- When the badge has a `data-tip`, suppress `#slotTooltip` while the pointer is
  over it; a plain-clash badge (no `data-tip`) keeps the row tooltip.
- Trigger and panel both full width of the Slot field.
- Keep selected state and click behaviour.

## R8 — Empty state

- No course: Course shows `Select a course`; Slot hidden; Details hidden;
  no old hint lines; Replacement card visible with defaults.
- Course but no conflicted slot: Slot and Details hidden.
- Keep `Select a slot to replace` in the hidden trigger.

## R9 — DOM and mobile contract

- Move controls; keep all IDs except the intentionally removed `#venueCapacity`,
  including `#subjectInfo`, `#slotTriggerText`, `#arrVenueDescription`,
  `#slotTooltip`.
- `#subjectInfo` is the Conflict Schedule card mount; the heading, selectors
  and Details stay inside it.
- `renderTitleSummary()` updates only dynamic parts; it never rebuilds the card
  with `innerHTML`.
- Keep `.toolbar-restructure` (column wrapper) and
  `.toolbar-filters .week-nav`; keep the mobile relocation plan unchanged.
- `.toolbar-filters` is kept only as the relocation selector; neutralise its
  legacy flex rules inside the Replacement card so it does not become a
  769–1200px full-width right-aligned row.
- Replace the dead mobile `.conflict-strip`/`.cs-*` block with
  `#subjectInfo .ra-block` rules; drop `.conflict-strip-wrap` so the gap is
  12px, not 24px.
- Under `body.arrangement-mobile-mode`:
  - `#subjectInfo .ra-block` drops border/radius/shadow/padding;
  - `#subjectInfo .ra-block-head` is hidden;
  - the red `Conflict Schedule` heading stays visible, with
    `#conflictCollapse` at the card's top-right as a 44px button; the card can
    collapse and its summary shows;
  - the Details row stays inside the mobile Original class `<details>`.
- Keep `renderTitleSummary()`'s `refreshArrangementMobile('original')` tail.

### Mobile compatibility acceptance (not a redesign)

- Under `body.arrangement-mobile-mode`, Course and Slot are `width: 100%`;
  the desktop 45/55 split does not follow the moved controls. The Course select
  is 52.8px high on mobile (+20%); the Slot stays 44px.
- On mobile, the venue trigger opens a `Choose venue` bottom sheet with search
  and a Block/Floor grouped room list; each group collapses/expands and one tap
  selects a room. Desktop keeps the nested panel.
- The `Colours & borders` tool opens the four-state legend sheet: Available,
  Your Current Selection, Pending, Conflict.
- The Details body is a wrapping row of Reason / Cohorts / Students on mobile;
  it does not keep the `1 : 1.5 : 0.8` columns at 320px. Each item keeps its
  label/value, wraps as a whole item to the next line when it does not fit, and
  keeps its label and badges left-aligned.
- Mobile Details badges share one system: 34px high, 48px min-width, 14px side
  padding, pill radius, 13px / 600, centred text. The student count is a short
  pill, not a circle.
- If `#detailsToggle` is a button or summary, it is at least 44px tall on
  mobile.
- Mobile date labels trim the year: `Tue, 17 Nov`, including the Slot trigger
  line 1 `(T) Week 8 · Wed, 11 Nov` and the week select options
  (`Week 9 · 16 Nov ~ 22 Nov`). Desktop keeps the year.
- At 320 / 390 / 768: no page overflow, no clipped text, and the trigger keeps
  its `data-tip` when it is clipped.
- Mobile venue text reads `B111 <capacity> seats`; the literal space is
  required.
- After this change lands, `tests/arrangement-mobile.spec.ts` is green on
  `class_replacement_testing`; do not run it at the same time as the mobile
  lane's server.

## R10 — Preserved behaviour

- Grid lock until a course is chosen.
- Auto-select, slot picker selection, venue sync, week navigation.
- Booking rules, submit path, legend, summary, cancel-class lane unchanged.
- Full cohort names via additive `cohortFullNames`; fallback to label.
- Pages without the venue opt-in render the identical label and
  `Replacement VENUE Selector: … (Capacity: N seats)` tip.
- The new venue trigger makes the room type tooltip-only; the mobile label text
  becomes `B111 35 seats`.
- No DB/schema change, no new dependency.

## Out of scope

- Mobile layout; grid; legend; selection summary; submit path; API;
  other pages' venue dropdown defaults.
