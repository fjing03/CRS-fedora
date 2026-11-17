# Spec — guide toggle as one icon

> Change: `guide-toggle-icon-only`
> Status: frozen · 2026-11-12
> Source: frozen `proposal.md` + `design.md`

## R1 — One icon at every width

- All 8 opted-in pages render exactly one `.guide-toggle-icon` and no
  `.guide-toggle` full-width bar.
- The icon is 40×40px desktop and 44×44px at `<=768px`, with an 18px SVG.
- It is used from 320px to desktop; no desktop text bar remains.
- The icon sits in normal flow at the page header's right; it is not
  absolutely positioned.

**Scenario: 320/390/768/desktop**
- Given an opted-in page at each width
- Then the icon is 44×44px at 320/390/768 and 40×40px on desktop
- And at 320/390 its box does not intersect the title/chips and the title is
  not clipped.

**Scenario: desktop**
- Then the icon is 40×40px at the header's right.
- And `.guide-block--content .guide-content` keeps at least 8px top padding
  when open.

## R2 — Page descriptions move into the guide

- Every page that has a `.page-desc` (7 of 8; `replacement-arrangement` has
  none) stops rendering it in the header.
- The same rich HTML appears as `.guide-description` at the top of the open
  `.guide-content`, before the guide list.
- The description is hidden while the guide is closed.
- A description-only guide (the two student pages) renders no `<ul>`.

**Scenario: open a guide with a description**
- Then `.guide-description` contains the moved copy and any `.info-keyword`
  markup, and the header has no `.page-desc`.

**Scenario: student description-only guide**
- Then the description shows and no empty `.guide-list` is rendered.

## R3 — Open/close and ARIA

- Static icon markup: `type="button"`, `aria-controls="guideBlock"`,
  `aria-expanded="false"`, `aria-label` from `guideLabel` (default
  `How to use this page`).
- Clicking toggles `#guideBlock.open` and writes `aria-expanded` to match.
- The open icon has a visible tint (`[aria-expanded="true"]`).
- `#guideBlock` and `.guide-block.open .guide-content` remain the content
  state owner.
- The open state is client-only; a Livewire round-trip may close it.

**Scenario: click twice**
- Then the content opens/closes and `aria-expanded` follows.

## R4 — Opt-in and backward compatibility

- The 8 live pages pass `guideToggle` and `showToggle=false`; the 7 with a
  description pass `guideDescriptionHtml`.
- `ui-page-header` and `ui-guide-block` defaults leave non-opted callers
  unchanged: no icon, old full bar, description still rendered by the header
  if provided.
- A page without a guide block never passes `guideToggle` (no dangling
  `aria-controls`).
- Only one shared question-mark SVG partial exists; only `guideLabel` owns the
  label.

**Scenario: non-opted render**
- A Blade render of `partials.ui-guide-block` without flags yields one
  `.guide-toggle` and zero `.guide-toggle-icon`; the bar contains
  `span.guide-icon`, its `onclick`, and the shared SVG at `width="16"`.
- A Blade render of `partials.ui-page-header` without `guideToggle` yields
  zero `.guide-toggle-icon` and still renders `.page-desc` when a description
  is passed.

**Scenario: existing live-page specs**
- `nav-identity`, `pages-parity`, `request-approval`, `venue-db`,
  `timetable-wiring` and `arrangement-mobile` stay green without assertion
  edits (except the two named update specs).

## R5 — Preservation

- No guide item text changes.
- No JS file change; inline handler only.
- The old `.guide-icon` wrapper and full-bar onclick remain for non-opted
  callers.
- No backend, DB, booking-rule, or other-page change.
- Design templates keep the old bar and inherit the flex header; no icon.

## Acceptance

- `tests/guide-toggle-icon.spec.ts` covers all 8 pages, geometry, ARIA,
  descriptions, student description-only guides and the Blade render fallback.
- `tests/venue-timetable.spec.ts` reads the description from the open guide.
- `tests/Feature/StudentReplacementHistoryParityTest.php` accepts the new
  header argument while keeping the page identity.
