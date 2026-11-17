# Tasks — My Timetable UI cleanup

> Change: `my-timetable-ui-cleanup`
> Frozen inputs: `proposal.md`, `design.md`, `specs/timetable-cleanup/spec.md`
> **Applied 2026-11-13; records reconciled 2026-11-14** — see `apply-log.md`.
> Every box below is ticked against verified code, not against memory.

## T1 — Summary card partial (1h)

- [x] In `resources/views/partials/ui-summary-bar.blade.php`, emit the prototype
      card structure: `role="group"`, `tabindex="0"`, `data-summary`,
      `aria-label="<label>: hover, tap or press Enter to flip"`.
- [x] Keep `id="{{ $card['valueId'] }}"` on `.summary-value`.
- [x] Back face: `<div class="summary-description">` containing
      `<ul class="summary-points">` when `$card['bullets']` is present; else the
      existing description string.
- [x] Add the `.summary-info` button with `data-summary-info`, `data-tip` and an
      accessible name.
- [x] Keep every existing caller working: `bullets` is optional.

## T2 — Summary card CSS (0.5h)

- [x] `public/css/theme.css`: `.summary-card` / `.summary-card-inner` 96px;
      left-aligned `.summary-card-back`; `.summary-points`; `.summary-info`;
      `.modal--summary` width.
- [x] Do not alter the existing value-colour map or responsive columns.

## T3 — Summary controller (1h)

- [x] In `public/js/ui-common.js`, add one `SummaryCards` controller with a
      single delegated `document` listener for click and keydown.
- [x] Flip on click/Enter/Space; stop propagation on `.summary-info`; open
      `#summaryExplainModal` with the long copy; restore the card and focus on
      close.
- [x] Null-safe when the modal or cards are absent (other pages).

## T4 — Guide modal partial (1h)

- [x] Create `resources/views/partials/ui-guide-modal.blade.php` using the house
      modal shell (`modal-overlay`, `modal`, `modal-header/body/footer`).
- [x] Render `$guideDescriptionHtml` in `.page-desc` and `$guideItems` in
      `.guide-list`.
- [x] Add a `GuideModal` open/close helper (display flex, focus close, lock
      scroll, Escape/overlay close, restore focus).

## T5 — Header icon wiring (0.5h)

- [x] `ui-page-header`: `.guide-toggle-icon` opens `#guideModal`
      (`aria-haspopup="dialog"`, `aria-controls`, `aria-expanded`).
- [x] Remove the `#guideBlock` toggle from the icon.
- [x] Keep the partial's non-opted callers unchanged.

## T6 — Four pages (1h)

- [x] My, Student, Cohort, Venue: delete the `ui-guide-block` include; pass the
      same description and items to `ui-guide-modal`.
- [x] My, Student, Venue: delete `#semesterProgress` and `#weekSubtitle`.
- [x] Leave `updateProgress()` / `updateWeekSubtitle()` calls in place.

## T6b — My Replacements and Request Approval (0.75h)

- [x] `replacement-home` and `request-approval`: swap `ui-guide-block` for
      `ui-guide-modal` with the same description/items.
- [x] Pass `'guideModal' => true` to `ui-page-header` on both.
- [x] Add `flipCards` to both summary-bar calls, with three bullets and the
      existing long formula moved to `explanation`.
- [x] These two pages have no `#semesterProgress` / `#weekSubtitle`; leave their
      week filter alone and do not add the timetable's tinted select.

## T7 — Week select surface (0.25h)

- [x] `.week-select` background → `var(--color-surface-variant)`.
- [x] `.week-select:hover` background → `var(--color-surface)`; keep the border
      change.
- [x] Leave `option`, chevron, padding, height and focus ring untouched.

## T8 — Changelogs (0.5h)

- [x] Add a dated entry to `page-changelogs/my-timetable-changelog.md`,
      `student-my-timetable-ui-changelog.md`, `cohort-timetable-changelog.md`
      and `venue-timetable-ui-changelog.md`.

## Dependencies

- T2/T3 depend on T1. (satisfied — applied in one pass, 2026-11-13)
- T5/T6/T6b depend on T4. (satisfied)
- T8 depends on T1–T7. (satisfied)

## Definition of done

UI-01–UI-08 are met. The six pages show 96px flip cards with three bullets and
an info button, the guide opens in a modal, `#semesterProgress` and
`#weekSubtitle` are gone from the timetable pages, and the week select uses the
tinted surface. No summary value, filter, navigation or modal behaviour changed.
No DB, route or dependency change.

**Met — measured 2026-11-14.** All six pages: 5 flip cards at 96px, 5 info
buttons, 5 `ul.summary-points`; `#guideModal` present, `#guideBlock` absent;
`#semesterProgress` absent. `week-select--tinted` on My, Student, Cohort and
Venue; deliberately absent on My Replacements and Request Approval. Zero page
errors. `/student-class-replacements` — outside this lane's six — keeps its 88px
cards and inline guide, confirming the change is opt-in rather than global.
