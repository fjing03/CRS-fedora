# Explore brief — Replacement Arrangement top zone

> Created 2026-11-11. This is the completeness checklist for the proposal.
> No system code was edited during explore. The user confirmed the full design
> in conversation; the prototype is the visual contract.

## Problem

The top of `/replacement-arrangement` currently has two problems:

1. **One long conflict strip.** `#subjectInfo` renders a single line:
   `Conflict Schedule · AMSE1003 — Software Engineering (T) · Week 8 · … · B111 · DSF1 (S1) G1 · 25`.
   Facts are not grouped; the user must read the whole line to find one fact.
2. **One mixed toolbar.** Subject, Slot, Venue, capacity, description, Week and
   Earliest bookable sit in one bar. “The class being replaced” and “the new
   replacement schedule” look like one group.

The user is also not satisfied with the earlier three-block prototype; it was
treated as reference only.

## Final solution — two cards

```
┌─ Conflict Schedule ───────────────────────────────────────────────┐
│  ⚠ Conflict Schedule                                              │
│  Subject                              Slot                        │
│  [ AMSE1003 — Software Engineering ▾ ] [ (T) Week 8 · 9 Nov … ▾ ]│
│  Public Holiday · DSF1 (S1) G1 · 24 students                  ▾   │
│  Reason  [Public Holiday]   Cohorts [DSF1 (S1) G1]   Students [24]│
└───────────────────────────────────────────────────────────────────┘

┌─ Replacement Schedule ────────────────────────────────────────────┐
│  ⇄ Replacement Schedule                                           │
│  Replacement venue                    Replacement week             │
│  [ B111 · 35 seats ] [i]   [ ‹ ] [ Week 8 · 9 Nov 2026 ~ … ] [ › ]│
│                                      [ Earliest bookable ]         │
└───────────────────────────────────────────────────────────────────┘
```

### Confirmed visual contract

| Item | Decision |
|---|---|
| Cards | `--radius-lg`, 1px `--color-outline`, `--shadow-sm`, padding `12px 16px`, 12px gap |
| Headings | 13px / 600, uppercase, `0.08em`, 14px icons |
| Conflict heading | Red: `--color-error` light / `--color-on-error-container` dark; warning icon |
| Replacement heading | Primary: `--color-primary` light / `--color-on-primary-container` dark; swap glyph |
| Field labels | 10.5px / 700, uppercase, `0.08em`, muted |
| Subject | 44px high, 45% width, 13px / 600, native dropdown |
| Slot | 55% width, 44px high, two lines |
| Slot line 1 | 13px / 600; only `(L/T/P)` is blue (same theme switch) |
| Slot line 2 | 12px / 500 muted; venue code in 11px / 700 pill, padding `2px 6px`, pill radius, `surface-variant` |
| Details | Expanded by default; divider + rotating chevron; summary 12.5px / 600 muted |
| Details columns | `1 : 1.5 : 0.8` |
| Details labels | 10.5px / 700 uppercase muted |
| Details values | 12.5px / 400 |
| Reason | Exact `badge badge-holiday` / `badge badge-reason`; text only `Public Holiday` / `Class Cancelled`; detail only on badge |
| Cohorts | One `badge badge-reason` per cohort; code visible; full name on hover; 6px gap; wrap |
| Students | One `badge badge-reason`; `24` single, `7 + 18 = 25` multiple |
| Summary | Full text when it fits; short form only on overflow |
| Venue | Real shared `VenueDropdown` + nested Type/Block/Floor/Room panel; trigger code 13/600 + seats 12/500 muted; description chip; no capacity chip |
| Week | Current arrows/select/Earliest; real labels `Week 8 · 9 Nov 2026 ~ 15 Nov 2026` |
| Slot picker | Old one-label row; badge `Public Holiday` / `Class Cancelled` + detail tip; trigger and panel 100% |
| Tooltips | Shared 11px; badge/cohort hover only; no extra tab stops |
| Fact icons | None inside the cards |
| Empty state | Both cards visible; Details hidden until a slot |

### Summary text mapping

| Case | Full (when it fits) | Short (overflow only) |
|---|---|---|
| One cohort | `Public Holiday · DSF1 (S1) G1 · 24 students` | `Public Holiday · 1 cohort · 24` |
| Multiple | `Public Holiday · 2 cohorts · 25 students` | `Public Holiday · 2 cohorts · 25` |

### Reason mapping

| Source fact | Badge | Detail tooltip |
|---|---|---|
| Public holiday | `Public Holiday` (`badge-holiday`) | holiday name, e.g. `Deepavali Holiday (In Lieu)` |
| Cancelled class | `Class Cancelled` (`badge-reason`) | cancellation reason, e.g. `Lecturer on Medical Leave` / `Official Event` |

### Slot picker badge

- Text: `Public Holiday` or `Class Cancelled`.
- Hover: same detail as the Reason badge.
- Keep the old one-label row (type + week/day/date + range + venue + short
  cohorts + badge).

## Rejected approaches

| Approach | Why rejected |
|---|---|
| Keep the old one-line strip + single toolbar | The mixed-purpose and plain-text problems remain |
| Three separate cards (earlier prototype) | More height; pushes the timetable down |
| Merge everything into one card | Loses the “class being replaced” vs “new schedule” separation |
| Details in a modal | Hides context; extra step |
| Always show all details | Tall, busy card |
| One thick swatch for Pending/Conflict legend | Already settled in a separate legend update; not part of this lane |
| Copy the venue dropdown markup | Drifts from the system; use the real shared component |
| Generic “Conflict” reason | The user ruled that the reason is only `Public Holiday` or `Class Cancelled` |
| Blue venue code / bold code / icon | User chose the compact surface-variant pill |

## Cross-module data flows

- `renderTitleSummary()` — the only top-zone renderer today. It reads
  `currentCourse`, `ctxCourse`, `anchorSlot`, `statusTip`, cohorts and totals.
  It becomes the card's dynamic updater: `#subjectInfo` is the card mount
  (heading + selectors + Details); the renderer updates only the heading and
  Details parts. It must not rebuild the selectors with `innerHTML`.
- `courseCtx()` — occurrence-grain context (`type`, `cohorts`,
  `cohortCounts`, `studentCount`).
- `buildSubjectDropdown()` / `applySubjectTip()` / `onSubjectChange()` /
  `applySubjectChange()` — subject selection and auto-pin. Do not reimplement.
- `renderSlotPicker()` / `slotTriggerLabel()` / `applySlotTip()` /
  `syncVenueFamily()` / `commitSlotSelection()` — slot selection and venue
  sync. Keep behaviour; change trigger formatting and badge detail.
- Shared `VenueDropdown` (`public/js/ui-common.js`) — nested Type / Block /
  Floor / Room panel. Keep the include and component; page-local trigger label.
- `populateWeekSelect('weekSelector', { ranges:false … })` — already produces
  `Week 8 · 9 Nov 2026 ~ 15 Nov 2026`.
- `LectureSessionCatalog` — cohort labels + counts; full cohort names may need
  an additive payload field if the page does not already have them.

## Dependencies and open questions

- **Apply gate:** `arrangement-mobile-day-view` must be fully applied and
  verified before this lane starts applying. Explore and propose may continue
  now. At apply time, re-check the page's line numbers and shared controls
  because the mobile work may have moved them.
- **Mobile hooks stay:** keep `.toolbar-restructure`,
  `.toolbar-filters .week-nav`, `#subjectInfo`, every control ID and
  `Select a slot to replace`. The mobile relocation plan is not changed.
- **Plain conflict case:** `extractSlotsForSubject()` also returns
  `status === 'conflict'`. The panel currently labels it `Conflict`; the card
  Reason has no value for it. Design must state the fallback (neutral
  `Conflict` badge, dash, or hide) before apply.
- **Open:** full cohort names. The current payload exposes cohort labels and
  counts; the prototype uses full programme names. If the real page lacks a
  full-name field, either add an additive `cohortFullNames` field or use the
  label as the tooltip. Ask before adding server payload.
- **Open:** exact class name for the slot type token; implementation may differ
  from the prototype's `.ra-slot-type`.
- **Open:** the venue trigger label override. The shared `VenueDropdown` owns
  the label; prefer a page-local opt-in rather than changing other pages.
- **Not in scope:** mobile/phone, grid, legend, selection summary, booking
  rules, submit path, cancel-class lane, API.
