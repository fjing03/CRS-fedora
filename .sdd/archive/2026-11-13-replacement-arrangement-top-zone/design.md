# Design — Replacement Arrangement top zone

> **Status:** design · 2026-11-11
> **Frozen proposal:** `.sdd/changes/replacement-arrangement-top-zone/proposal.md`
> **Baseline:** `.sdd/changes/replacement-arrangement-top-zone/explore-brief.md`
> **Visual contract:** `../../../../ui-prototype/replacement-arrangement-top-zone-preview.html`

## Goal

Replace the dense conflict strip and the single mixed toolbar with two cards:

1. **Conflict Schedule** — heading, Course/Slot selectors, expandable Details.
2. **Replacement Schedule** — venue and week controls.

Keep every booking rule, selection behaviour and shared component working.

## Non-goals

- Mobile layout (owned by `arrangement-mobile-day-view`).
- Grid, legend, selection summary, submit path, cancel-class lane, API.
- Other pages' venue dropdown default behaviour.
- The prototype file itself.

## 1. DOM contract

```
.toolbar-restructure                ← outer wrapper (column), mobile hide hook
  #subjectInfo                      ← Conflict Schedule card mount
    .ra-block
      .ra-block-head                ← heading + warning icon + whole-card arrow
        #conflictCollapse           ← button; collapses the whole card
      #conflictCollapsedSummary     ← Course/Slot summary shown only when collapsed
      #conflictCardBody             ← body wrapper; hidden when the card is collapsed
        .ra-original-controls
          #subjectSelector          ← existing native select
          #slotPicker               ← existing wrapper (hidden until course)
            #slotTrigger
              #slotTriggerText      ← two-line trigger content
            #slotPanel
        #details                    ← hidden by default in markup; opened on first slot
          #detailsToggle
          #detailsSummary
          #detailsBody
  .ra-block                         ← Replacement Schedule card
    .ra-block-head                  ← heading + swap icon
    .ra-replacement-venue
      #buildingSelectorDropdown     ← shared venue component
      #venueChipGroup
        #arrVenueDescription        ← keep; #venueCapacity removed
    .ra-replacement-week
      .toolbar-filters              ← kept only as a mobile hook
        .week-nav
          #weekSelector
          #earliestBtn
```

**Rules**

- Move controls; do not copy them. Keep every ID in the proposal's §6.
- `#subjectInfo` is the card mount. The heading, Course/Slot and Details all
  live inside it; `renderTitleSummary()` updates only the dynamic parts.
- Never rebuild `#subjectInfo` with `innerHTML`; that would wipe the selectors
  and their listeners.
- Keep `.toolbar-restructure` around the two desktop cards (mobile hide hook).
  Change it from the old row to a column/block wrapper; the mobile plan moves
  `#subjectInfo` out before hiding the remaining wrapper.
- Keep `.toolbar-filters .week-nav` around the week group (mobile relocation
  hook). Neutralise its legacy flex rules inside the Replacement card.
- Keep `Select a slot to replace` as the empty trigger text.
- Heading DOM text stays `Conflict Schedule`; uppercase comes from CSS.

## 2. Card styling

Page-local CSS only; tokens from `theme.css`.

| Element | Rule |
|---|---|
| `.ra-block` | `--color-surface`, 1px `--color-outline`, `--radius-lg`, `--shadow-sm`, padding `12px 16px` |
| `.toolbar-restructure` | `display: flex; flex-direction: column; gap: 12px` (not the old row) |
| Card gap | 12px |
| `.ra-block-head` | flex row, 6px gap, 9px bottom margin |
| `.ra-eyebrow` | 13px / 600, uppercase, `0.08em`, 14px icon |
| Conflict heading | warning icon; `--color-error` in light, `--color-on-error-container` in dark |
| Replacement heading | swap glyph; `--color-primary` in light, `--color-on-primary-container` in dark |
| `.ra-field-label` | 10.5px / 700, uppercase, `0.08em`, `--color-on-surface-variant` |
| Course select | 44px high, 45% column, 13px / 600; `--color-surface-variant` field background |
| Slot field | 55% column, 44px high; `--color-surface-variant` field background |
| Slot panel | `--color-surface` so it floats above the card |
| Slot line 1 | 13px / 600, `--color-on-surface`; only the type token is blue |
| Slot line 2 | 12px / 500, `--color-on-surface-variant` |
| Venue pill | 11px / 700, padding `2px 6px`, pill radius, 1px outline; `--color-surface` inside the variant slot trigger, `--color-surface-variant` in the collapsed summary |
| Replacement venue trigger | room code 13px / 600; seats 12px / 500 muted; 8.8px gap between them; arrow at the far right; `--color-surface-variant` field background |
| Week select | `--color-surface-variant` field background; panel/options stay `--color-surface` |
| Details summary | 12.5px / 600, muted |
| Details labels | 10.5px / 700, uppercase, muted |
| Details values | 12.5px / 400, `--color-on-surface` |
| Details columns | `1 : 1.5 : 0.8` |
| Badges | base `.badge`; `badge-holiday` red / `badge-reason` neutral |
| Tooltips | shared `data-tip` 11px |

`badge-reason` is the my-replacements cancellation-reason class. Reusing it for
the cohort and student chips is the user's ruling; do not rename it.

Theme overrides use `html.light`; the base rules are the dark tokens.

## 3. Conflict Schedule — markup and renderer

### 3.1 Static markup

- Heading: warning icon + `Conflict Schedule` + `#conflictCollapse` on the
  right.
- `#conflictCollapse` is a real button: default expanded, `aria-expanded="true"`,
  label `Collapse Conflict Schedule` / `Expand Conflict Schedule`, and it toggles
  `#conflictCardBody` `hidden`. On mobile the red heading stays visible and the
  arrow sits at the card's top-right as a 44px button.
- `#conflictCardBody` wraps the Course/Slot controls and the Details row.
- `#conflictCollapsedSummary` is shown only when the card is collapsed:
  - line 1: `BMSE6767 (T) · W8 · Fri, 19 Nov`; the type token is blue;
  - line 2: `2:00 PM ~ 3:00 PM @ <venue pill>`; the venue pill is the desktop
    11px / 700 surface-variant pill.
  - It is hidden when expanded; it is shown on both desktop and mobile when
    collapsed.
- The whole-card arrow is disabled until a Course is selected. If a Course is
  selected but no Slot exists, the card may collapse and line 1 shows the
  Course code only.
- `renderCollapsedSummary()` updates both lines on Course/Slot changes; it never
  rebuilds the card.
- Whole-card collapse and Details collapse are independent. Collapsing the card
  does not change the Details open/closed state.
- Course field: label + native `#subjectSelector`.
- Slot field: label + `#slotPicker` wrapper + two-line trigger + panel.
- Details: static markup starts hidden (`hidden`, `aria-expanded="false"`); the
  renderer unhides and opens it on the first slot (avoids an empty flash).

The selectors are static; do not render them from `renderTitleSummary()`.

### 3.2 `renderTitleSummary()`

- Keep every existing call site.
- Read the same `currentCourse`, `ctxCourse`, `anchorSlot`, `statusTip`, cohorts
  and totals.
- Update only:
  - `#detailsSummary` text;
  - `#detailsBody` content;
  - Details visibility (hidden until a slot exists).
- Do **not** write `#slotTriggerText` here; the slot trigger has its own writer.
  It no longer needs to derive/print `slotLine`.
- Do **not** change `#conflictCollapse` / `#conflictCardBody`; the whole-card
  collapse is user-owned.
- Remove the heading `data-tip`; the Reason badge owns the detail.
- Keep the `.no-subject` grid-lock toggle.
- Keep the `refreshArrangementMobile('original')` tail; it is the only
  `'original'` refresh.

### 3.3 Course / Slot behaviour

- Course change still calls `onSubjectChange()` / `applySubjectChange()`.
- Auto-select the first conflicted slot as today.
- Venue still syncs to the selected slot's own room.
- Slot picker selection still uses `commitSlotSelection()`.

### 3.4 Details

- Default expanded; `detailsToggle` collapses/expands body and flips
  `aria-expanded` + the chevron.
- Summary normal/short:
  - full single: `reason · cohort code · 24 students`;
  - short single: `reason · 1 cohort · 24`;
  - full multiple: `reason · 2 cohorts · 25 students`;
  - short multiple: `reason · 2 cohorts · 25`.
- Summary measurement: set the full text first, then measure
  `scrollWidth > clientWidth` and swap to short. Re-measure on a debounced
  window resize and when the row becomes visible (rAF after unhide or a
  `ResizeObserver`). The summary must keep `white-space: nowrap; overflow:
  hidden` for the comparison to mean anything.
- Details open rule: re-open when the row reappears after a no-slot state; keep
  a manual collapse across slot changes.
- Reason facts come from one shared `reasonFacts(slot)` helper (cancelled
  before holiday), used by the card and the panel row:
  - `Public Holiday` → `badge badge-holiday`, detail = holiday name;
  - `Class Cancelled` → `badge badge-reason`, detail = cancellation reason;
  - **plain clash** = absence of holiday and cancellation facts → neutral
    `badge badge-reason` with text `Conflict`, no detail (user ruling,
    2026-11-11). Never label a plain clash `Class Cancelled`. The summary reason
    token is also `Conflict`.
  - no slot at all → Details hidden; do not show an empty Reason cell.
- Cohorts: one `badge badge-reason` per cohort; code visible; `data-tip` full
  name; 6px gap; flex wrap.
- Students: one `badge badge-reason`; `24` or `7 + 18 = 25`.

### 3.5 Data details

- `cohortFullNames`: additive field in `LectureSessionCatalog::sessionEntry()`
  and course-level `build()`, built from
  `cohort.programme.programme_name + ' · Year N Sem N Group N'`.
- Fallback to `cohorts` label when the field is absent.
- Slot objects gain `holidayName` / `cancelledReason` where available:
  - holiday name from `weekData[week].days[day].holidayLabel` or
    `MockData.holidays`;
  - cancellation reason from `e.cancelledReason`.
- One shared `reasonFacts(slot)` helper returns `{ label, detail }` with
  **cancelled before holiday** precedence. It is used by the card, the panel row
  and both `data-tip`s, so they can never disagree.
- Use the same `statusTip` derivation (cohort timetable event, then holidays)
  inside `reasonFacts` for both real slots and the virtual
  `anchorSlotFromPinned()`, which carries no `status`. Do not let the handoff
  path ship a blank Reason.
- Plain clash = **no holiday and no cancellation facts** (regardless of
  `status`), so the virtual anchor classifies the same way as a picked slot.
- No slot at all → Details hidden; no empty Reason cell.

## 4. Replacement Schedule — markup and venue

- Heading: swap glyph + `Replacement Schedule`.
- Venue label + shared `@include('partials.ui-venue-dropdown')`; no copy.
- Description chip `#arrVenueDescription` stays; `#venueCapacity` is removed.
- Week label + `.toolbar-filters` hook + `ui-week-nav` partial.

### Venue trigger label

The shared `VenueDropdown` owns the trigger. Add an **opt-in**
`opts.triggerLabel` renderer:

```
triggerLabel(v) -> '<span class="ra-venue-code">B111</span>' +
                   ' <span class="ra-venue-seats">35 seats</span>'
```

- The renderer returns **only the label HTML**; `_updateTrigger()` keeps its
  existing tooltip branch byte-identical (`Replacement VENUE Selector: …`).
- The constructor copies it explicitly: `this.triggerLabel = opts.triggerLabel
  || null` (there is no `this.opts`).
- When `this.triggerLabel` is present, `_updateTrigger()` writes the label with
  `innerHTML`; otherwise it keeps `textContent` exactly as today.
- The no-selection early return keeps `textContent = 'Select a venue'`; it has
  no venue object to hand the renderer.
- Keep the literal space between the two spans; the mobile snapshot
  concatenates child text without adding one.
- When the option is absent, `_updateTrigger()` keeps its current path
  byte-identical.
- The arrangement page passes the renderer.
- All selection paths (`select()`, initial code, slot sync) go through
  `_updateTrigger()`; no page-local label observer.

### Week

- Keep `populateWeekSelect('weekSelector', { ranges:false … })`; it already
  produces `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`.
- Do not re-format or add a second builder.

## 5. Slot trigger and panel

### Trigger

- `#slotTriggerText` contains two child spans:
  `#slotTriggerLine1` and `#slotTriggerLine2`.
- `.slot-dd` becomes a column container with `width: 100%; max-width: none;
  flex-basis: auto; min-width: 0`.
- `.slot-dd-trigger` becomes 44px high with `padding: 4px 12px`.
- `#slotTriggerText` is the flex-column container (`display: flex;
  flex-direction: column; flex: 1; min-width: 0; gap: 1px`); its direct `> span`
  children carry nowrap/ellipsis. The nested venue pill sits inside line 2 and
  must not inherit the `.slot-dd-trigger span` ellipsis rule.
- Override the old rules: `.slot-dd` max-width 384px, trigger height 36px,
  `.slot-dd-trigger span` nowrap/ellipsis, and `.slot-dd-panel` min-width 512px
  → `width: 100%; min-width: 0`.
- `slotTriggerLabel(slot)` becomes the single format law:
  - return `{ line1, line2 }` with
    - line1: `(T) Week 8 · Wed, 11 Nov 2026` (day-first, per rule 11). On
      mobile, trim the year: `(T) Week 8 · Wed, 11 Nov`. Use
      `slot.weekdayName || slotDayAbbrs[slot.day]` so one formatter serves both
      picked slots and the anchor;
    - line2: `1:00 PM ~ 2:00 PM @ <venue pill>`.
- **Line 1 is HTML**, not text: `<span class="ra-slot-type">(T)</span> Week 8 ·
  Wed, 11 Nov 2026`. Line 2 is HTML because it contains the venue pill.
- `writeSlotTrigger(slot)` is the **only** function that writes
  `#slotTriggerText`; it owns the two child spans:
  `<span id="slotTriggerLine1">…</span><span id="slotTriggerLine2">…</span>`.
  No writer sets `textContent = slotTriggerLabel(...)` directly.
  With `slot === null`, it writes a single text node
  `Select a slot to replace` (no child spans), so the spec's exact
  `toHaveText` stays true.
- **Every** write of `#slotTriggerText` goes through `writeSlotTrigger`, with
  the **full slot object** (`type, day, date, week, start, end, venue`):
  - `writeSlotTriggerFromAnchor()` — forward `anchorSlotFromPinned()` as-is,
    not the reduced `{week,start,end,date}`; return `false` when the anchor is
    `null`, `true` after writing;
  - `applySubjectChange()`;
  - `renderSlotPicker()` placeholder reset (`null`);
  - `commitSlotSelection()` — user pick + confirm-cancel restore;
  - `selectSlot()`'s reject/else branch that currently writes
    `Select a slot to replace` directly (`null`).
- Keep `applySlotTip()` data-tip on `#slotTriggerText`.
- Trigger and panel both `width: 100%`.

### Panel rows

- Keep the live old one-label row: type + week/day/date + range + venue + short
  cohorts + badge. Keep its month-first date shape (the live rows and the spec
  already use it); only the badge text/detail changes.
- Badge is fed by the same `reasonFacts(slot)`; keep the
  `.slot-dd-item-badge` sizing, add `badge-holiday` / `badge-reason` for colour
  (drop the inline `statusBg` / `statusColor` variables).
  - `Public Holiday` — red `badge-holiday`;
  - `Class Cancelled` — neutral `badge-reason`;
  - plain clash — neutral `Conflict` with `badge-reason`.
- Add `data-tip` from the same facts:
  - holiday name;
  - cancellation reason;
  - none for a plain clash.
- When the badge has a `data-tip`, hide `#slotTooltip` while the pointer is over
  the badge; a plain-clash badge (no tip) keeps the row tooltip.
- Keep the row's selected state and click behaviour.

## 6. Empty state

- No course: Course placeholder; `#slotPicker` hidden; `#details` hidden;
  Replacement card visible with defaults.
- Course but no conflicted slot: Slot and Details stay hidden.
- Do not print the old `Select a Course first` / `Then pick a Conflict Slot`
  hints.
- Keep `Select a slot to replace` in the hidden trigger for specs.

## 7. Mobile compatibility

- Keep `.toolbar-restructure`, `.toolbar-filters .week-nav`, `#subjectInfo`,
  every control ID and the mobile relocation plan.
- On mobile, the venue trigger opens a bottom sheet titled `Choose venue` with
  a search box and a Block/Floor grouped vertical room list. Each group header
  collapses/expands; one tap picks a room. The desktop nested panel is unchanged.
- The mobile `Colours & borders` tool opens the current system's four-state
  legend sheet: Available, Your Current Selection, Pending, Conflict.
- The mobile plan moves `#subjectSelector` / `#slotPicker` before
  `#subjectInfo`, so nesting is safe.
- Under `body.arrangement-mobile-mode`:
  - `#subjectInfo .ra-block` drops border/radius/shadow/padding;
  - the red `Conflict Schedule` heading stays visible, with
    `#conflictCollapse` as a 44px button at the card's top-right;
  - the card can collapse on mobile; `#conflictCardBody` hides and
    `#conflictCollapsedSummary` shows, using the same two lines;
  - the Details row stays inside the mobile Original class `<details>`;
  - `#subjectSelector` and `#slotPicker` are `width: 100%`;
    `.ra-original-controls` is block, so the desktop 45/55 split does not
    follow the moved controls;
  - `#subjectSelector` is 52.8px high on mobile (+20% from 44px); the Slot
    stays 44px high;
  - `#detailsBody` is a wrapping row of Reason / Cohorts / Students on mobile
    (the 1 : 1.5 : 0.8 grid is desktop-only); each item keeps its label/value
    and wraps as a whole item to the next line when it does not fit; labels and
    badges stay left-aligned inside each item;
  - mobile Details badges share one system: 34px high, 48px min-width, 14px
    side padding, pill radius, 13px / 600, centred text; the student count is a
    short pill, not a circle;
  - if `#detailsToggle` is a button/summary, it has a 44px minimum target;
  - date labels trim the year on mobile (`Tue, 17 Nov`), including the Slot
    trigger line 1 (`(T) Week 8 · Wed, 11 Nov`) and the week select options
    (`Week 9 · 16 Nov ~ 22 Nov`); desktop keeps the year.
- Replace the old mobile `#arrangementMobile #subjectInfo .conflict-strip /
  .cs-*` rules with the new `#subjectInfo .ra-block` / Details rules; they
  otherwise become dead CSS and the mobile Original class loses its wrapping.
- Drop the `.conflict-strip-wrap` class from `#subjectInfo` (and its 12px
  bottom margin); the `.toolbar-restructure` column gap owns the spacing, so the
  cards do not get 24px.
- This is a compatibility pass, not a mobile redesign. The mobile lane keeps
  ownership of the phone layout.
- After the mobile lane is fully applied, verify relocation and run
  `tests/arrangement-mobile.spec.ts` on `class_replacement_testing`. Do not run
  it at the same time as the mobile lane's test server.

## 8. Tests/specs

Update the listed specs in the proposal. Key changes:

- `.conflict-strip` readiness gates → card root or `#detailsSummary`;
- `#venueCapacity` assertions → venue trigger seat text;
- `.venue-dd-label` assertions (`/^B0\d\d? — /`) → new `B111` + `35 seats`
  label; update the label-text reads in `arr-slot-original-venue-sync`;
- slot trigger start-shape → starts with `(T) Week`;
- month-first trigger date assertions → day-first;
- `arr-slot-original-venue-sync:30,69` and `replacement-strip-anchor:27`:
  the old joined `Week 14 · Dec 24, 2026 · 12:00 PM ~ 1:00 PM` string becomes
  the two-line trigger; update those reads.
- `replacement-strip-anchor:23` and `venue-cross-week-handoff:47-50` read
  `#subjectInfo` text; the trigger is now inside the mount, so update their
  date/venue expectations to the day-first two-line text.
- `venue-cross-week-handoff` date/venue contiguity → the new
  `1:00 PM ~ 2:00 PM @ B110` shape;
- `#subjectInfo` heading assertion → DOM text unchanged (uppercase in CSS).
- `venue-cross-week-handoff:50` (`1:00 PM @ B110`) survives as a substring of
  line 2; `arr-slot-original-venue-sync:121-123` splits the label on the first
  space, so `B111` still falls out of `B111 35 seats`. Keep those edits out of
  the diff.
- The venue tooltip stays the existing `Replacement VENUE Selector: …` shape;
  do not rewrite it. The panel row date shape stays month-first.

Playwright runs only on the user's word.

## 9. Rejected in design

| Option | Why rejected |
|---|---|
| Rebuild `#subjectInfo` with `innerHTML` | Wipes selectors/listeners; duplicate IDs |
| Copy the venue dropdown | Drifts from the shared design |
| Page-local label observer for the venue | Fragile; misses initial/anchor paths |
| Keep heading tooltip + badge tooltip | User chose badge only |
| One-line Slot trigger | User confirmed two lines |
| Generic `Conflict` for all reasons | False for holiday/cancelled |
| Label plain clash `Class Cancelled` | False reason |

## 10. Risks

| Risk | Mitigation |
|---|---|
| Plain clash reason | Neutral `Conflict` badge, no detail (user ruling) |
| Full cohort names absent | Additive `cohortFullNames`; label fallback |
| Virtual anchor has no status | Use the same `statusTip` derivation for real slots and the anchor |
| Summary measured while hidden | Re-measure on resize and visibility |
| Mobile card chrome | Scope to `#subjectInfo .ra-block` / `.ra-block-head` |
| `.toolbar-filters` legacy layout | Scope/neutralise inside the card |
| Shared venue label option | Opt-in only; other pages byte-identical |
| Anchor trigger reduced object | Forward the full slot object through every writer |

## 11. Verification

- `php artisan view:cache` / `view:clear`; restart via `scripts/dev-serve.sh`.
- Add the `page-changelogs/replacement-arrangement-changelog.md` entry.
- Add/extend `tests/Feature/LectureSessionCatalogTest.php` for
  `cohortFullNames`.
- Keep `renderTitleSummary()`'s `refreshArrangementMobile('original')` tail.
- Desktop: two cards, headings, theme colours, 45/55 selectors, two-line Slot,
  venue pill, Details summary full/short, badges, venue nested panel, week
  labels, empty state.
- Mobile: after `arrangement-mobile-day-view` lands, re-check relocation and
  the neutralised `#subjectInfo .ra-block` chrome.
- Update the listed specs; run only on the user's word.
