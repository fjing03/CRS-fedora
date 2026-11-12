# Proposal — Replacement Arrangement top zone

> **Status:** proposed · 2026-11-11
> **Change:** `replacement-arrangement-top-zone`
> **Explore baseline:** `.sdd/changes/replacement-arrangement-top-zone/explore-brief.md`
> **Visual contract:** `../ui-prototype/replacement-arrangement-top-zone-preview.html`

## Why

The top of `/replacement-arrangement` mixes two different jobs in one place:

1. **One dense conflict strip** (`#subjectInfo`) already carries icons, a
   total badge and a head tooltip, but it still prints course, type, week,
   date, time, venue, cohorts and student count in one crowded row. The user
   must read the whole row to find one fact.
2. **One mixed toolbar** holds Subject, Slot, Venue, capacity, description,
   Week and Earliest bookable. The class being replaced and the new
   replacement schedule look like one group.

The user confirmed a two-card replacement after reviewing the current system
and the earlier prototype. The timetable grid, booking rules, selection
summary and submit path must stay unchanged.

## What changes

### 1. Conflict Schedule card

- Replace the long strip with a card.
- Heading: `Conflict Schedule`, red text (light: `--color-error`; dark:
  `--color-on-error-container`), warning icon, 13px / 600 uppercase. Keep the
  DOM text `Conflict Schedule`; apply the uppercase in CSS.
- Subject and Slot side by side:
  - Subject: 44px high, 45% width, standard dropdown.
  - Slot: 55% width, two lines:
    - line 1: type + week/date, only the type token blue;
    - line 2: time `@` venue, with the venue code in a compact pill.
- Details row below the selectors, expanded by default:
  - summary shows the normal text when it fits, short form on overflow;
    re-measure when the row becomes visible, not only on window resize;
  - Reason badge (`Public Holiday` / `Class Cancelled`) with detail only on
    the badge;
  - Cohorts: one neutral badge per cohort, full name on hover, 6px gap, wrap;
  - Students: one neutral badge, `24` or `7 + 18 = 25`;
  - three columns at `1 : 1.5 : 0.8`.

### 2. Replacement Schedule card

- Heading: `Replacement Schedule`, primary text (light: `--color-primary`;
  dark: `--color-on-primary-container`), swap icon, 13px / 600 uppercase.
- Replacement venue:
  - reuse the real shared `VenueDropdown` and its nested
    Type / Block / Floor / Room panel;
  - trigger shows room code + muted seat count (`B111` + `35 seats`);
  - keep the description chip; remove the separate capacity chip.
- Replacement week:
  - keep the current arrows, week select and `Earliest bookable`;
  - real labels: `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`.

### 3. Slot picker

- The Slot **trigger** is the two-line field from §1 — do not keep a one-line
  trigger.
- Inside the open panel, keep the old one-label **option row** layout:
  type + week/day/date + time range + venue + shortened cohorts + badge.
- Badge text becomes `Public Holiday` / `Class Cancelled`.
- Badge hover shows the holiday name or cancellation reason.
- Trigger and panel both use the full Slot field width.

### 4. Shared visual rules

- Cards use `--radius-lg`, 1px outline, `--shadow-sm`, padding `12px 16px`,
  12px gap.
- Field labels are 10.5px / 700 uppercase muted.
- Shared 11px tooltip.
- No fact icons inside the cards.
- Details is the only collapse/expand control; badge/cohort details are
  hover-only with no extra keyboard tab stops.

### 5. Empty state

- No subject chosen: Subject shows `Select a subject`; Slot and Details are
  hidden; the Replacement card stays visible with its defaults.
- Subject chosen but no conflicted slot: Slot stays hidden; Details stays
  hidden. Do not show the old `Select a Subject first` / `Then pick a Conflict
  Slot` hint lines.
- Subject and slot chosen: show the Slot control and the Details row.
- No separate “select a subject first” sentence.

### 6. Shared DOM contract

- **Move the controls; do not copy them.** Keep every existing ID:
  `subjectSelector`, `slotPicker`, `slotTrigger`, `slotTriggerText`,
  `slotPanel`, `slotTooltip`, `buildingSelectorDropdown`, `venueChipGroup`,
  `arrVenueDescription`, `weekSelector`, `earliestBtn`, `summaryTip`,
  `submitTip`.
- `#subjectInfo` remains the Conflict Schedule card mount. It contains the
  heading, the Subject/Slot controls and the Details row. The heading stays
  inside it.
- `renderTitleSummary()` must **not** rebuild the whole card with `innerHTML`.
  It updates only the dynamic parts (heading text/tooltip state, Details
  summary/body). Rebuilding it would wipe the selectors and their listeners.
- The Slot trigger's format law stays in `slotTriggerLabel()`; every writer
  (anchor, subject change, placeholder reset) renders the same two-line format.
- Keep `Select a slot to replace` as the empty trigger text.
- Keep `.toolbar-restructure` as the outer wrapper around the two desktop
  cards. It is the mobile hide hook.
- Keep `.toolbar-filters .week-nav` around the week nav inside the Replacement
  card. It is the mobile relocation hook.
- Keep `.toolbar-filters` only as the hook, not as a layout: scope or neutralise
  its legacy flex rules inside the Replacement card.
- Under `body.arrangement-mobile-mode`, `#subjectInfo` drops its desktop card
  chrome (border/radius/shadow/padding) and hides the desktop heading. Keep the
  Details row inside the mobile Original class details; mobile owns the
  presentation.
- The mobile `RELOCATION_PLAN` / `DESKTOP_CONTAINERS` stay unchanged. Apply
  after the mobile lane is fully applied; verify the mobile relocation still
  finds every hook.

## In scope

- `resources/views/livewire/replacement-arrangement.blade.php`: top-zone
  markup, page-local CSS, `renderTitleSummary()` details updates, Details
  rendering, Slot trigger formatting, Slot picker badge detail, venue trigger
  label.
- `app/Services/LectureSessionCatalog.php` (and one test): additive
  `cohortFullNames` for the cohort-badge hover. Approved by the 2026-11-11
  full-design confirmation. No schema change. If the page already has a
  full-name source, reuse it instead.
- `page-changelogs/replacement-arrangement-changelog.md`: dated entry.
- Optional shared change in `public/js/ui-common.js` **only if** the shared
  venue trigger label needs an opt-in option; prefer a page-local override.
- Spec updates for the changed top zone:
  `arrival-grid-no-pin.spec.ts`, `session-arrival-pin-guard.spec.ts`,
  `arrangement-earliest-week-default.spec.ts`, `default-pin.spec.ts`,
  `arr-slot-original-venue-sync.spec.ts`, `replacement-strip-anchor.spec.ts`,
  `cohort-venue-replace-now.spec.ts`, `venue-cross-week-handoff.spec.ts`,
  `venue-restrictions-enforcement.spec.ts`, `rearrangement-toolbar-tooltips.spec.ts`.
  In `rearrangement-toolbar-tooltips.spec.ts`, update the trigger start-shape,
  the month-first date shape, and the venue-tip wording. Playwright runs only on
  the user's word.

## Out of scope

- Mobile/phone layout — owned by `arrangement-mobile-day-view`.
- The timetable grid, legend, selection summary, booking rules, submit path,
  cancel-class lane, API surface.
- Other pages' venue dropdown behaviour.
- The desktop prototype file itself.

## Success criteria

1. The top area shows two cards. The old strip and mixed toolbar layout are
   gone; `.toolbar-restructure` remains only as the outer mobile-hide wrapper.
2. Subject, Slot, Venue, Week and Earliest still work exactly as before.
3. The first conflicted slot is still auto-selected on subject change; the
   venue still syncs to the selected slot.
4. Details is expanded by default; it collapses/expands and shows the correct
   reason, cohort badges and student badge.
5. The summary shows the full text when it fits and the short form only when
   it overflows.
6. The Replacement venue opens the real shared nested dropdown; the trigger
   shows code + seats; the trigger tooltip carries the full room name + capacity;
   the description chip works; no capacity chip.
7. Week options already produced by `populateWeekSelect` read
   `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`; verify, do not re-implement.
8. Slot picker badges and tooltips match the Reason badge wording.
9. No horizontal overflow at 1280 / 1024 / 900.
10. The empty state matches the rules: both cards visible, Subject placeholder,
    Slot and Details hidden, no old hint lines.
11. The listed top-zone specs are updated to the new selectors; no new
    failures. Playwright runs only on the user's word.

## Dependencies and constraints

- **Apply gate:** `arrangement-mobile-day-view` must be fully applied and
  verified before this lane starts applying. Explore/propose may continue now.
  Re-check line numbers and shared controls at apply time.
- **Desktop only:** mobile is not part of this lane.
- **Venue dropdown:** the shared component owns the dropdown design; only the
  page-local trigger label should differ.
- **Cohort full names:** add the additive `cohortFullNames` field (in scope) so
  each badge can show the full programme name. If the page already has a
  full-name source, reuse it; otherwise fall back to the cohort label.
- **Plain conflict case:** `extractSlotsForSubject()` also returns occurrences
  with `status === 'conflict'`. The slot panel currently labels them `Conflict`,
  but the card's Reason column has no value for them. Design must state the
  fallback (neutral `Conflict` badge, dash, or hide the row) before apply. Do
  not silently call a plain clash `Class Cancelled`.
- **Theme:** use only `theme.css` tokens; no hardcoded colours.
- **No new dependencies; no DB/schema changes.**
- **Ops:** after the Blade change run `php artisan view:cache` +
  `php artisan view:clear`, then restart through `scripts/dev-serve.sh` per
  `AGENTS.md`.

## Risks

| Risk | Mitigation |
|---|---|
| Two cards add height before the grid | Keep the cards compact; Details expanded once, not repeated facts |
| Shared blade conflict with the mobile lane | Apply only after the mobile lane is fully applied/verified |
| Mobile relocation hooks removed | Keep `.toolbar-restructure`, `.toolbar-filters .week-nav`, `#subjectInfo` and every control ID; verify mobile relocation after apply |
| Existing top-zone specs assert the old strip/chip | Update the listed specs in this lane; Playwright only on the user's word |
| Shared `VenueDropdown` label changes affect other pages | Page-local opt-in only; keep other pages byte-identical |
| Full cohort names unavailable in the payload | Decide before apply: additive field or label fallback |
| Summary measurement unreliable | Measure actual `scrollWidth` vs `clientWidth`; re-measure on resize and when the row becomes visible |
| Two tooltip sources on the reason | Badge only; remove the heading tooltip |

## Alternatives rejected

See `explore-brief.md` for the full list. The main rejected options:

- old one-line strip + single toolbar (mixed purposes remain);
- three separate cards (more height);
- one merged card (loses the two-job separation);
- Details in a modal (hides context, extra step);
- copying the venue dropdown markup (drifts from the system);
- a generic `Conflict` reason (user ruled only `Public Holiday` /
  `Class Cancelled`).

## Approval

Proposal stage only. No system code has been changed. Apply starts only after
the user approves the full artifact chain and the mobile lane is clear.
