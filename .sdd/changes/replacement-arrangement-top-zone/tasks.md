# Tasks — Replacement Arrangement top zone

> Change: `replacement-arrangement-top-zone`
> Apply status: **not started** (explore/propose only)
> Frozen inputs: `proposal.md`, `design.md`, `specs/top-zone/spec.md`

## Apply gate

- [x] T1. Confirm `arrangement-mobile-day-view` is fully applied and verified (0.5h)
  - Re-check the page's line numbers and the mobile hooks:
    `.toolbar-restructure`, `.toolbar-filters .week-nav`, `#subjectInfo`,
    `#subjectSelector`, `#slotPicker`, `#buildingSelectorDropdown`,
    `#venueChipGroup`, `#earliestBtn`, `#summaryTip`, `#submitTip`.
  - Do not start editing while that lane is still applying.
  - Confirm `tests/arrangement-mobile.spec.ts` is green on
    `class_replacement_testing`. Do not run it at the same time as that lane's
    test server on :8030.

> **Atomic apply batch:** T2 → T10 lands in one pass; nothing is verified or
> committed before T10. The renderer still overwrites `#subjectInfo` until T8,
> and the two-line trigger is not visible until T10. T4–T7 can only be checked
> after that.

## Markup

- [x] T2. Wrap the top zone into two cards (1.5h)
  - `.toolbar-restructure` becomes the column wrapper around both cards.
  - `#subjectInfo` is the Conflict Schedule card mount: heading, selectors,
    Details inside it.
  - Add `#conflictCollapse` on the heading (default expanded), a
    `#conflictCollapsedSummary` two-line block, and wrap the selectors +
    Details in `#conflictCardBody`.
  - Move Course/Slot/Venue/Week controls; keep all IDs except the intentionally
    removed `#venueCapacity`.
  - Keep `.toolbar-filters .week-nav` as the mobile hook.
  - Keep `#arrVenueDescription`, `#slotTriggerText`, `#slotTooltip`.
  - Remove the old `.conflict-strip-wrap` class/margin.
  - Delete the `#venueCapacity` span, its `capA` writer block and
    `.badge-capacity`; leave `#arrVenueDescription` alone. That description
    badge is a kept pre-existing control — do not strip its icon or chip.
  - Keep `Select a slot to replace` in the trigger.

- [x] T3. Build the Details markup (1h)
  - `#details` is a plain `div` toggled by `#detailsToggle` (not a native
    `<details>`), hidden by default with `aria-expanded="false"`.
  - `#detailsToggle`, `#detailsSummary`, `#detailsBody`.
  - Body labels `Reason`, `Cohorts`, `Students`; columns `1 : 1.5 : 0.8`.
  - No fact icons.

## CSS

- [x] T4. Card, heading and label styling (1h)
  - Remove the dead `.conflict-strip` / `.cs-*` rules and `.badge-capacity`.
  - Cards: `--radius-lg`, 1px outline, `--shadow-sm`, padding `12px 16px`,
    12px gap.
  - Headings: 13px / 600 uppercase `0.08em`; 14px warning/swap icons.
  - `html.light` overrides for red/blue.
  - Field labels: 10.5px / 700 uppercase `0.08em` muted.

- [x] T5. Course/Slot layout and two-line trigger (2h)
  - Course 44px, 45%; Slot 55%, 44px.
  - Both field backgrounds `--color-surface-variant`; Slot panel stays
    `--color-surface`.
  - `#slotTriggerText` flex-column container, `flex:1; min-width:0`, child
    spans `#slotTriggerLine1` / `#slotTriggerLine2`.
  - Override `.slot-dd` max-width 384px, trigger height 36px, span
    nowrap/ellipsis, panel `min-width:512px` → `width:100%; min-width:0`.
  - Line 1 13px / 600; only `.ra-slot-type` blue.
  - Line 2 12px / 500 muted; venue pill 11px / 700, `2px 6px`, 1px outline,
    normal text. It uses `--color-surface` inside the variant slot trigger and
    `--color-surface-variant` in the collapsed summary.
  - Venue pill must not inherit the trigger span ellipsis.

- [x] T6. Details, badges and summary CSS (1.5h)
  - Summary 12.5px / 600 muted, nowrap + overflow hidden.
  - Body labels/values as T3; `#detailCohorts` wrapping flex, 6px gap.
  - `.badge badge-holiday` / `.badge badge-reason` usage.
  - Badges stay non-focusable spans: no `tabindex`, no `<button>`.
  - Divisor + chevron rotation; hover/focus.
  - `#conflictCollapse` is a small icon button on the heading right;
    `#conflictCardBody[hidden]` is `display: none`.
  - `#conflictCollapsedSummary` is hidden unless the card is collapsed: line 1
    13px / 600, line 2 12px / 500 muted, venue pill as desktop. Disabled arrow
    is visibly disabled; the summary shows on mobile too.
  - `body.arrangement-mobile-mode`: `#subjectInfo .ra-block` drops chrome;
    the red heading stays visible.
  - Mobile only: `#subjectSelector` and `#slotPicker` are `width: 100%`;
    `.ra-original-controls` is block. `#subjectSelector` is 52.8px high
    (+20%); the Slot stays 44px.
  - Mobile only: `#detailsBody` is a wrapping row of Reason / Cohorts /
    Students; each item keeps its label/value, wraps as a whole item, and keeps
    labels and badges left-aligned. Mobile badges: 34px high, 48px min-width,
    14px side padding, pill radius, 13px / 600, centred; the student count is a
    short pill, not a circle. If
    `#detailsToggle` is a button/summary, give it a 44px minimum target.

- [x] T7. Venue trigger label CSS (0.5h)
  - `.ra-venue-code` 13px / 600; `.ra-venue-seats` 12px / 500 muted; 8.8px gap
    between the code and the seat count; the dropdown arrow sits at the far
    right.
  - Venue trigger and week select background `--color-surface-variant`;
    their panels/options stay `--color-surface`.
  - Keep a literal space between the two spans.

## JS — card and details

- [x] T8. Convert `renderTitleSummary()` to dynamic-only updates (2h)
  - Keep every call site and the `.no-subject` toggle.
  - Do not rebuild `#subjectInfo` with `innerHTML`.
  - Do not write `#slotTriggerText`; the slot writer owns it.
  - Remove the heading `data-tip`.
  - Keep `refreshArrangementMobile('original')`.
  - Add the `#conflictCollapse` toggle: default expanded, toggles
    `#conflictCardBody` hidden, flips `aria-expanded` and the arrow, and leaves
    the Details open/closed state alone.
  - Add `renderCollapsedSummary()`: line 1 `CODE (T) · W8 · Fri, 19 Nov`, line 2
    `2:00 PM ~ 3:00 PM @ venue pill`; update on Course/Slot changes. Disable the
    arrow until a Course exists; a Course with no Slot shows the code only.
    Hide the summary when expanded and on mobile.

- [x] T9. Add `reasonFacts(slot)` and render Details (2h)
  - On the first slot, unhide `#details`, add `open`, set
    `aria-expanded="true"`, show the body and rotate the chevron.
  - One helper, cancelled before holiday; used by card and panel.
  - Reason badge: Public Holiday / Class Cancelled / plain clash `Conflict`
    (neutral, no detail).
  - Use `statusTip` facts for real slots and the virtual anchor.
  - Cohorts: one badge per cohort, label text, `cohortFullNames[i]` tip,
    label fallback.
  - Students: one badge, `24` or `7 + 18 = 25`; `—` when empty.
  - Summary full/short: `reason` is the **same token as the Reason badge**
    (`Public Holiday`, `Class Cancelled` or `Conflict`). Use these literal
    examples: `Public Holiday · DSF1 (S1) G1 · 24 students`,
    `Public Holiday · 1 cohort · 24`, `Conflict · DSF1 (S1) G1 · 24 students`,
    `Conflict · 1 cohort · 24`. Set full, measure, swap; debounced resize +
    visibility re-measure; reopen after no-slot but keep manual collapse.

## JS — slot trigger and panel

- [x] T10. Add the single trigger writer (1.5h)
  - `slotTriggerLabel(slot)` → `{line1, line2}`; line 1 HTML includes the blue
    type token; line 2 HTML includes the venue pill. On mobile, line 1 trims
    the year: `(T) Week 8 · Wed, 11 Nov`.
  - `writeSlotTrigger(slot)` is the only writer of `#slotTriggerText`; it owns
    the two child spans. With `null`, write a single text node
    `Select a slot to replace` (no child spans).
  - Wire all five sites: anchor, course change, `renderSlotPicker()`
    placeholder (`null`), `commitSlotSelection()`, `selectSlot()` else branch.
  - `writeSlotTriggerFromAnchor()` returns `false` on no anchor, `true` after
    writing.
  - Keep `applySlotTip()`.

- [x] T11. Slot picker badge and tooltip (1h)
  - Use `reasonFacts`; `Public Holiday` / `Class Cancelled` / `Conflict`.
  - Keep `.slot-dd-item-badge` sizing; add `badge-holiday` / `badge-reason`
    colour classes; drop inline `statusBg` / `statusColor`.
  - Add `data-tip`; when present, suppress `#slotTooltip` while over the badge.
  - Keep the live month-first row layout.

## JS — shared venue dropdown

- [x] T12. Add the opt-in venue label renderer (1h)
  - `VenueDropdown` constructor: `this.triggerLabel = opts.triggerLabel || null`.
  - `_updateTrigger()`: when present, write label with `innerHTML`; otherwise
    keep the existing path byte-identical.
  - Keep the existing tooltip branch unchanged.
  - No-selection early return keeps `textContent = 'Select a venue'`.
  - Arrangement page passes the `B111` + seats renderer.

## Payload

- [x] T13. Add `cohortFullNames` (1h)
  - `LectureSessionCatalog::sessionEntry()` and course-level `build()`:
    `programme_name · Year N Sem N Group N`.
  - No schema change; no new query (relation already loaded).
  - Add/extend `tests/Feature/LectureSessionCatalogTest.php`; run
    `php artisan test --filter=LectureSessionCatalogTest`. Never
    `--env=testing`.
  - Fallback to cohort label when absent.

- [x] T14. Carry holiday/cancellation detail (0.5h)
  - `holidayName` from `weekData[].days[].holidayLabel` / holidays.
  - `cancelledReason` from `e.cancelledReason`.
  - Virtual anchor uses the same `reasonFacts`.

## Mobile compatibility

- [x] T15. Finish the scoped mobile rules (1h)
  - Replace the dead mobile `.conflict-strip`/`.cs-*` block with
    `#subjectInfo .ra-block` / Details rules.
  - `#subjectInfo .ra-block` chrome dropped; the red `Conflict Schedule`
    heading stays visible, with `#conflictCollapse` as a 44px top-right button;
    the card can collapse, the summary shows, and `#conflictCardBody` hides.
  - Mobile date labels trim the year (`Tue, 17 Nov`), including the Slot
    trigger line 1 and the week select options
    (`Week 9 · 16 Nov ~ 22 Nov`); desktop keeps the year.
  - Drop `.conflict-strip-wrap`; card gap stays 12px.
  - `.toolbar-filters` kept only as the selector; neutralise its flex rules
    inside the Replacement card.

## Tests and close-out

- [x] T16a. Readiness gates and `#subjectInfo` text (1h)
  - Files: `arrival-grid-no-pin`, `session-arrival-pin-guard`,
    `arrangement-earliest-week-default`, `default-pin`, `cohort-venue-replace-now`,
    `venue-restrictions-enforcement`.
  - Replace every `page.waitForSelector('.conflict-strip')` with
    `page.waitForSelector('#subjectInfo')` — `#subjectInfo` is mounted and
    visible. Never use `#detailsSummary` as a boot gate; it is hidden until a
    slot exists.
  - Update `#subjectInfo` text reads for the day-first two-line trigger.
  - The mount now contains the native course select, so its text always holds
    every course option. In `replacement-strip-anchor` and
    `venue-restrictions-enforcement`, read `#subjectSelector`'s selected option
    (or scope to the Slot/Details region) instead of proving the course from
    the mount text.
- [x] T16b. Venue label family and capacity (1h)
  - File: `arr-slot-original-venue-sync`.
  - Per assertion, keep the same room code and use **that room's** seat count
    from the payload (`B010 28 seats`, `B111 80 seats`, …). Do not hardcode
    `35 seats`; that is the prototype's illustrative number.
  - `#venueCapacity` assertions → trigger seat text.
  - `:121-123` still splits on the first space; keep the room code.
- [x] T16c. Trigger shape and row text (1h)
  - Files: `arr-slot-original-venue-sync`, `replacement-strip-anchor`,
    `venue-cross-week-handoff`, `rearrangement-toolbar-tooltips`.
  - Trigger start-shape → starts with `(T) Week`; day-first; no joined
    ` · ` between date and range.
  - `range @ venue` shape; `#subjectInfo` text.
  - Panel row date stays month-first; no edit needed unless the badge text is
    asserted.
  - Playwright runs only on the user's word.

- [x] T17. Changelog and static checks (0.5h)
  - Add `page-changelogs/replacement-arrangement-changelog.md` entry.
  - `php artisan view:cache` / `view:clear`.
  - Restart via `scripts/dev-serve.sh`.
  - `node --check` for the inline JS; PHP is touched only by T13.

- [x] T18. Verify (1.5h)
  - **Verified 2026-11-12:** static gates, 17/17 core top-zone specs and
    51/51 mobile specs passed on `:8030`. Four unrelated/time-dependent
    checks are recorded in `review-log.md`.
  - Desktop: two cards, theme colours, 45/55, two-line trigger, venue pill,
    Details full/short, badges, nested venue panel, week labels, empty state.
  - Whole-card arrow collapses/expands the Conflict Schedule and keeps the
    Details state; on mobile the arrow stays as a 44px top-right button, the
    summary shows, and the card body hides.
  - Collapsed summary shows `CODE (T) · W8 · Fri, 19 Nov` plus the time/venue
    line; arrow is disabled until a Course exists; Course with no Slot shows the
    code only.
  - Arrival `?session=` and panel pick both fill the two-line trigger.
  - Check `reasonFacts` with a synthetic slot carrying both `holidayName` and
    `cancelledReason`; cancelled wins. Do not seed the demo DB.
  - 1280 / 1024 / 900: no overflow; `.toolbar-filters` neutralised.
  - Desktop `.conflict-strip`/`.cs-*` and `#venueCapacity` are gone.
  - Badges are non-focusable spans; no new tab stops.
  - Spot-check one non-arrangement venue page (label + `Replacement VENUE
    Selector: … (Capacity: N seats)` tip).
  - Mobile: after the mobile lane, relocation still works; `#subjectInfo .ra-block`
    chrome neutralised; Details row in Original class; venue text reads
    `B111 <capacity> seats`.
  - Mobile layout: Course/Slot full width, Details items in one wrapping row,
    44px targets; no overflow or clipped text at 320 / 390 / 768.
  - Run `tests/arrangement-mobile.spec.ts` on `class_replacement_testing` after
    this change. It must be green, and not run at the same time as the mobile
    lane's server.

## Follow-up after this change (not part of this apply)

- [ ] Live mobile pop-ups: after `arrangement-mobile-day-view` is finished,
  add the four bottom sheets and the `venueDescription` tap to the real phone
  page. Do not start this before the mobile lane is verified.
- [ ] Live mobile venue picker: replace the wide nested panel on phones with
  the `Choose venue` bottom sheet (search + collapsible Block/Floor groups);
  keep the desktop panel unchanged. Same follow-up window.
- [ ] Live mobile `Colours & borders`: wire the tool to the four-state legend
  sheet (Available / Selection / Pending / Conflict). Same follow-up window.
