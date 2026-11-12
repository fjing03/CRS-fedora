# Replacement Arrangement — top zone: Conflict Schedule + Replacement Schedule

> **Status: READY — full top-zone pass.**
> All design decisions below were confirmed by the user on 2026-11-11.
> This replaces the old one-line conflict strip **and** the single mixed
> toolbar with two cards. If PI's work is still uncommitted in the same blade,
> wait for a clean tree before editing.
>
> **Reference prototype:** `../ui-prototype/replacement-arrangement-top-zone-preview.html`
> — it is the visual contract for both cards. Do not edit the prototype while
> implementing the real page.

---

## Section 1 — What the user wants (plain English)

The top of `/replacement-arrangement` becomes **two cards**:

1. **Conflict Schedule** — the class being replaced and the Course/Slot
   selectors, plus an expandable Details row.
2. **Replacement Schedule** — the new venue and week controls.

The timetable grid, legend, selection summary, booking flow and all booking
rules stay as they are.

### 1. Card style

- `--radius-lg`, 1px `--color-outline` border, `--shadow-sm`,
  padding `12px 16px`.
- 12px gap between the two cards.
- No fact icons inside the cards.

### 2. Conflict Schedule card

- Heading: **Conflict Schedule**, 13px / 600, uppercase, `0.08em`,
  warning icon 14px, plus a second arrow button on the right that
  collapses/expands the whole card.
  - Light mode: `--color-error`.
  - Dark mode: `--color-on-error-container`.
  - The whole-card arrow is a real button, expanded by default, with
    `aria-expanded` and a clear label. It is separate from the Details arrow.
- Field labels: `Course` and `Slot` above the controls, 10.5px / 700,
  uppercase, `0.08em`, muted.
- **Course** — standard dropdown, 44px high, 45% of the row, 13px / 600.
- **Slot** — 55% of the row, 44px high:
  - Line 1: `(T) Week 8 · Wed, 11 Nov 2026` at 13px / 600.
    Only the type token `(L/T/P)` is blue:
    - light: `--color-primary`;
    - dark: `--color-on-primary-container`.
  - Line 2: `1:00 PM ~ 2:00 PM @ [B111]` at 12px / 500, muted.
    The venue code is a compact outlined pill: 11px / 700, padding `2px 6px`,
    pill radius, normal text colour. Inside the variant slot trigger it uses
    `--color-surface`; in the collapsed summary it uses
    `--color-surface-variant`. The `@` stays outside the pill.
- Both field backgrounds use `--color-surface-variant` so they stand apart
  from the `--color-surface` card. The venue trigger and week select use the
  same field background; the slot and venue panels stay `--color-surface`.
- The Slot field stays hidden until a course is chosen.
- **Details** below the selectors:
  - Expanded by default; click the summary row to collapse.
  - The heading arrow collapses the whole card and keeps the Details
    open/closed state unchanged.
  - Thin divider above the row, rotating chevron.
  - Summary: 12.5px / 600, muted.
  - Summary text: show the normal form when it fits; use the short form only
    when the normal text would overflow.
    - One cohort normal: `Public Holiday · DSF1 (S1) G1 · 24 students`
    - One cohort short: `Public Holiday · 1 cohort · 24`
    - Multiple normal: `Public Holiday · 2 cohorts · 25 students`
    - Multiple short: `Public Holiday · 2 cohorts · 25`
  - Expanded body: three columns at `1 : 1.5 : 0.8` on desktop.
  - Mobile only: the three items share one row and wrap as whole items to the
    next line when they do not fit; do not stack them one per line. Keep each
    label and its badge left-aligned inside the item. Badges: 34px high, 48px
    min-width, 14px side padding, pill radius, 13px / 600, centred; the student
    count is a short pill, not a circle.
  - Labels: `Reason`, `Cohorts`, `Students` at 10.5px / 700, uppercase,
    `0.08em`, muted.
  - Values: 12.5px / 400.
  - **Reason**: exact my-replacements conflict badge
    (`badge badge-holiday` or `badge badge-reason`).
    - Text is only `Public Holiday` or `Class Cancelled`.
    - The holiday name or cancellation reason rides the badge's hover tip.
    - The heading has **no** tooltip; the badge is the only reason tooltip.
  - **Cohorts**: one neutral grey `badge badge-reason` per cohort, 6px gap,
    wrapping to the next line when needed. Each badge shows the cohort code
    and carries its full name on hover.
  - **Students**: one neutral grey `badge badge-reason`:
    - one cohort: `24`
    - multiple: `7 + 18 = 25`
  - Badge/cohort details are hover-only; do not add extra keyboard tab stops.

### 3. Replacement Schedule card

- Heading: **Replacement Schedule**, 13px / 600, uppercase, `0.08em`,
  swap glyph 14px.
  - Light mode: `--color-primary`.
  - Dark mode: `--color-on-primary-container`.
- Field labels: `Replacement venue` and `Replacement week`, same style as the
  Course/Slot labels.
- **Replacement venue**:
  - Use the **real shared `VenueDropdown` component** and the existing
    `partials.ui-venue-dropdown` markup. The nested
    Type / Block / Floor / Room panel is the current system design.
  - Do **not** copy the dropdown markup or CSS into the page.
  - Page-local trigger label: room code at 13px / 600 normal, followed by
    seat count at 12px / 500 muted, e.g. `B111` + `35 seats`.
  - Trigger tooltip: full room name + capacity.
  - Keep the icon-only room-description chip exactly as today.
  - Remove the separate capacity chip. No capacity chip remains in the row.
- **Replacement week**:
  - Keep the current arrows, week select and green `Earliest bookable`
    button exactly.
  - Week options use the real system format:
    `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`.

### 4. Empty state

- Both cards stay visible before a course is chosen.
- Course shows `Select a course`; Slot is hidden.
- Details is hidden until a slot exists.
- The Replacement Schedule card stays visible with its default venue and week.
- Do not add a separate “select a course first” sentence.

### 5. Slot picker

- Keep the old one-label row layout plus the status badge.
- Trigger and panel are both `100%` of the Slot field width.
- Status badge text: `Public Holiday` or `Class Cancelled`.
- Badge hover shows the detail:
  - holiday name, e.g. `Deepavali Holiday (In Lieu)`;
  - cancellation reason, e.g. `Lecturer on Medical Leave` or `Official Event`.
- Keep the real row facts: type + week/day/date + time range + venue +
  shortened cohorts + badge.

### 6. Tooltips

- Use the shared `data-tip` tooltip: 11px, inverse surface, above the element,
  clamped to the viewport.
- No new tooltip style.

### 7. Everything else stays

- Grid, grid lock until a course is chosen, auto-select of the first
  conflicted slot, slot picker selection, venue sync, week navigation,
  selection summary, submit path, and the legend.
- Existing IDs and existing JS call sites stay working.

---

## Section 2 — The plan is a guide, not a contract

The implementation route below is a suggestion. Class names and exact CSS
placement may differ. What must not change is the two-card structure, the
confirmed visual details, the preserved behaviour, and the real shared venue
dropdown.

Guardrails:

- Do **not** reimplement selection logic. Keep `onSubjectChange()`,
  `applySubjectChange()`, `commitSlotSelection()`, `syncVenueFamily()`,
  `renderSlotPicker()` selection, `writeSlotTriggerFromAnchor()` and the
  existing submit path.
- Keep `renderTitleSummary()` as the Conflict Schedule renderer and keep every
  existing call site working. Put the new card template there, and add a
  separate details renderer if that is cleaner. The Replacement Schedule card
  is mostly static markup moved from the toolbar; it does not need a new
  renderer.
- Use the shared `@include('partials.ui-venue-dropdown')` and
  `VenueDropdown` class. If the page-local trigger label needs a new option,
  add an **opt-in** shared option; do not change the default for other pages.
- Keep the shared `ui-week-nav` partial and `populateWeekSelect()`.
- Keep the grid lock side effect (`.no-subject`) and the status tooltip removal
  from the heading.
- Colors only from `public/css/theme.css` tokens. No hardcoded hex/rgb.
- Page-local CSS in the blade; do not move shared styles unless unavoidable.
- No DB/schema changes, no new dependency.
- Desktop only. The phone layout is owned by the `arrangement-mobile-day-view`
  lane. On mobile, the Course select is 52.8px high (+20%); the Slot stays
  44px. Date labels trim the year (`Tue, 17 Nov`), including the Slot trigger
  and the week select options (`Week 9 · 16 Nov ~ 22 Nov`); desktop keeps the
  year. Mobile venue follow-up: replace the wide nested panel with a
  `Choose venue` bottom sheet (search + collapsible Block/Floor groups); desktop
  stays unchanged. Wire mobile `Colours & borders` to the four-state legend
  sheet (Available / Selection / Pending / Conflict).
- Do **not** edit `../ui-prototype/replacement-arrangement-top-zone-preview.html`
  — it is the reference.
- Do **not** touch `.sdd/changes/bulk-cancel-class/` or the cancel-class lane.
- Commit only the files this task touches; update
  `page-changelogs/replacement-arrangement-changelog.md`.
- Playwright stays paused unless the user explicitly asks.

---

## Appendix — Measured anchors and suggested route (advisory)

Measured 2026-11-11 on `fedora-backend` HEAD `5b0b9ff`. Re-verify before
editing; PI may have moved lines.

### Current state

| Fact | Where |
|---|---|
| Conflict strip mount | `resources/views/livewire/replacement-arrangement.blade.php:1187` (`#subjectInfo`) |
| Mixed toolbar | same blade `:1189` (`.toolbar.toolbar-restructure`) |
| Shared venue dropdown include | same blade `:1206` (`partials.ui-venue-dropdown`, `buildingSelector`) |
| Venue chips | same blade `:1211`–`:1213` (`#venueChipGroup`, `#venueCapacity`, `#arrVenueDescription`) |
| Week nav include | same blade `:1221` (`ui-week-nav`, `weekSelector`) |
| Strip CSS | same blade `:200`–`:252` (`.conflict-strip`, `.cs-*`) |
| Slot picker CSS | same blade `:320`–`:365` (`slot-dd-trigger`, `slot-dd-panel`, width) |
| Top-zone renderer | `renderTitleSummary()` `:3203` |
| Occurrence context | `courseCtx()` `:3088` |
| Course dropdown | `buildSubjectDropdown()` `:3341`; `applySubjectTip()` `:3359` |
| Course change | `onSubjectChange()` `:3368` → `applySubjectChange()` `:3930` |
| Slot picker | `renderSlotPicker()` `:4276`; `slotTriggerLabel()` `:4473` |
| Venue sync | `syncVenueFamily()` `:4512`; `commitSlotSelection()` `:4549` |
| Shared venue component | `public/js/ui-common.js` `VenueDropdown` ~`:3802`; label writer `_updateTrigger()` ~`:4267` |
| Week options | `populateWeekSelect()` in `ui-common.js`; real labels = `weekData.label + ' · ' + weekData.range` |
| Venue payload | `app/Livewire/ReplacementArrangement.php:398` (`code`, `name`, `short_name`, `type`, `capacity`, `description`) |
| Cohort data | `ctxCourse.cohorts` + `ctxCourse.cohortCounts`; `LectureSessionCatalog::sessionEntry()` |
| Shared time helpers | `ui-common.js` `hours` (30-min slots), `to12h()`, `add30min()` |
| Reference prototype | `../ui-prototype/replacement-arrangement-top-zone-preview.html` |

### Suggested changes

1. **Markup — two cards**
   - Replace the `#subjectInfo` mount and the single `.toolbar-restructure`
     with two card sections:
     - `Conflict Schedule` — heading, Course/Slot row, Details.
     - `Replacement Schedule` — heading, venue field, week field.
   - Move the existing controls into their cards; keep their IDs:
     `subjectSelector`, `slotPicker`, `slotTrigger`, `slotPanel`,
     `buildingSelectorDropdown` (shared partial), `weekSelector`,
     `earliestBtn`.
   - Keep the `ui-venue-dropdown` include and the `ui-week-nav` include.
   - Remove the separate capacity chip; keep the description chip
     (`#arrVenueDescription` on the real page) and its wiring.

2. **CSS — page-local**
   - Remove the old `.conflict-strip` / `.cs-*` rules once the new card is in
     place.
   - Cards: `--radius-lg`, 1px outline, `--shadow-sm`, padding `12px 16px`,
     12px gap.
   - Headings: 13px / 600, uppercase, `0.08em`; 14px icons.
     Add `html.light` overrides for the red and primary colours:
     - base/dark: soft red / light blue;
     - light: strong red / button blue.
   - Field labels: 10.5px / 700, uppercase, `0.08em`, muted.
   - Course/Slot row: `grid-template-columns` ~`45% / 55%`; 44px controls.
   - Slot line 1: 13px / 600; only `.ra-slot-type` / the type token is blue.
   - Slot line 2: 12px / 500 muted; venue code compact outlined pill 11px /
     700, `2px 6px`, pill radius; `surface` inside the variant trigger,
     `surface-variant` in the collapsed summary.
   - Details: summary 12.5px / 600 muted; `1 : 1.5 : 0.8` body grid;
     labels 10.5px / 700; values 12.5px / 400.
   - Collapsed summary: line 1 13px / 600, line 2 12px / 500 muted, venue
     pill as desktop; hidden unless collapsed.
   - Whole-card arrow: small icon button at the heading right;
     `#conflictCardBody[hidden]` is `display: none`.
   - Mobile: keep the red `Conflict Schedule` heading visible, with
     `#conflictCollapse` as a 44px top-right button; the card can collapse, the
     summary shows, and the body hides.
   - `#detailCohorts` as a wrapping flex row with 6px gap.
   - Slot panel width `100%`; slot trigger width `100%`.
   - Venue trigger seats: 12px / 500 muted, room code 13px / 600 normal;
     8.8px gap between the code and the seat count; the dropdown arrow sits at
     the far right.

3. **JS — renderer**
   - Keep `renderTitleSummary()` as the entry point. Replace its
     `.conflict-strip` template with the Conflict Schedule card.
   - Heading status tooltip: remove it. The reason detail lives only on the
     Reason badge. Keep the `.no-subject` toggle.
   - Whole-card collapse: `#conflictCollapse` toggles `#conflictCardBody`
     hidden, flips `aria-expanded` and the arrow, and does not change the
     Details state. Default expanded.
   - Collapsed summary: `#conflictCollapsedSummary` shows
     `CODE (T) · W8 · Fri, 19 Nov` and `2:00 PM ~ 3:00 PM @ venue pill`;
     hidden when expanded. Disable the arrow until a Course exists;
     a Course with no Slot shows the code only. The same summary is shown when
     collapsed on mobile.
   - Details renderer:
     - default expanded;
     - summary full/short measured with
       `summaryEl.scrollWidth > summaryEl.clientWidth`;
     - Reason badge with `badge badge-holiday` / `badge badge-reason` and a
       `data-tip` only on the badge;
     - Cohorts: one `badge badge-reason` per cohort, each with its own
       full-name `data-tip`; join with no separator; flex gap 6px;
     - Students: one `badge badge-reason` with `24` or `7 + 18 = 25`.
   - Slot trigger:
     - write two lines instead of one;
     - line 1: type + week/day/date; line 2: time + ` @ ` + venue pill;
     - keep `applySlotTip()` and the existing anchor writer working.
   - Slot picker:
     - keep the row layout;
     - change the cancelled badge text to `Class Cancelled`;
     - add `data-tip` with the holiday name / cancellation reason.
   - Venue dropdown:
     - use the real `VenueDropdown` component and nested panel;
     - keep the page-local trigger label `code` + muted seats;
     - keep the room-name/capacity tooltip and the description chip.
   - Week select: the existing `populateWeekSelect('weekSelector', { ranges:false … })`
     already gives `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`; do not re-format it.
   - Add a debounced resize re-render for the Details summary full/short state.

4. **Do not touch**
   - The grid, legend, selection summary, submit path, booking rules or
     class-cancel lane.
   - Other pages' venue dropdown default behaviour.
   - The prototype.

### Suggested files

- `resources/views/livewire/replacement-arrangement.blade.php` (markup, CSS,
  renderer)
- `page-changelogs/replacement-arrangement-changelog.md`
- `public/js/ui-common.js` only if the shared venue trigger label needs an
  opt-in option; prefer a page-local override
- No `public/css/theme.css` change unless a token is genuinely missing
- Tests/specs only if their selectors change

### Verification

1. `php artisan view:cache` and `php artisan view:clear`; restart through
   `scripts/dev-serve.sh` per `AGENTS.md`.
2. Manual on `/replacement-arrangement`:
   - no course → both cards visible, Course placeholder, Slot and Details
     hidden, Replacement card with default venue/week;
   - pick a course → first conflicted slot auto-selects; Details opens;
   - Slot line 1 shows `(T) Week N · Day, DD Mon YYYY` and only the type is
     blue; line 2 shows `time ~ time @ [venue]`;
   - Details summary shows the full text when it fits and the short form when
     it overflows;
   - Reason badge shows `Public Holiday` or `Class Cancelled` with the correct
     hover detail;
   - Cohorts: one neutral badge per cohort, full name on hover, 6px gap, wrap;
   - Students: one neutral badge, `24` or `7 + 18 = 25`;
   - Replacement venue opens the real nested Type/Block/Floor/Room panel;
     trigger reads `B111` + muted `35 seats`; description chip works; no
     capacity chip;
   - Week options read `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`;
   - slot picker badges read `Public Holiday` / `Class Cancelled` and show the
     detail on hover;
   - no horizontal overflow at 1280 / 1024 / 900.
3. Update the Playwright spec only; do not run it until the user asks.
