# Proposal — guide toggle as one icon, with page descriptions inside

> **Status:** frozen · 2026-11-12
> **Change:** `guide-toggle-icon-only`
> **Explore brief:** `.sdd/changes/guide-toggle-icon-only/explore-brief.md`
> **Reference:** My Timetable header, top-right question-mark icon

## Why

The shared guide currently renders a full-width `How to use this page ▾` bar
under every page header, and every page header separately prints a
`.page-desc` line. The user wants:

- one question-mark guide icon at every width from 320px to desktop;
- every page description moved into the guide content, above the guide items.

The guide content and its open/closed behaviour stay inline.

## What changes

### 1. Page header gets an optional icon

`partials.ui-page-header` accepts `guideToggle` (default `false`). When true it
renders one icon-only button:

- The header becomes a flex row: `.page-header-main` (title/chips) plus the
  icon; the icon is `flex-shrink: 0` and right-aligned in normal flow. No
  absolute positioning and no title-padding arithmetic.
- The icon is a 40×40px button on desktop and 44×44px at `<=768px`; the SVG is
  18×18px.
- `aria-controls="guideBlock"`, `aria-label` comes from `guideLabel`
  (default `How to use this page`), and `aria-expanded` matches the block.
- Click toggles `#guideBlock.open` and updates `aria-expanded`.
- The open-state icon gets a visible tint/background via
  `[aria-expanded="true"]`.
- When `guideToggle` is omitted, the header renders no guide icon; the
  `.page-header-main` wrapper and flex layout still apply, but the rendered
  layout is unchanged (asserted by the existing header tests).
- The 8 live pages stop passing `descriptionHtml` to the header; the header
  partial keeps support for other callers.

The icon is in normal flow, so the replacement-arrangement page's intentionally
empty `.page-header` (its title lives in the top bar) still gets a clean
right-aligned icon with no overlap and no special positioning.

### 2. Guide block gets the page description

`partials.ui-guide-block` accepts:

- `showToggle` (default **true**): when false, the full-width `.guide-toggle`
  bar is not rendered; `#guideBlock`, `.guide-content` and the list remain.
- `guideDescriptionHtml` (default empty): rendered as a
  `.guide-description` block at the top of `.guide-content`, before the list.
  Use the same rich HTML the page used to pass as `descriptionHtml`.
- When `guideItems` is empty, omit the `<ul class="guide-list">` entirely, so a
  description-only guide has no empty-list padding or border.

The content-only block gets `.guide-block--content`, removes the full-bar
margin/border, and restores top padding so the description/list is not flush.
A guide block with a description but no items is valid (the student pages).

### 3. One glyph and one label source

- Extract the question-mark SVG into
  `partials/ui-guide-icon.blade.php`; include it from both the page-header icon
  and the existing full guide toggle. No duplicated SVG markup.
- The page-header label is `guideLabel` (default `How to use this page`). The
  six existing guide pages drop their now-unused `guideTitle` argument; the
  guide item arrays stay.

### 4. Opt in all 8 live pages

Add `'guideToggle' => true` to the page-header include and
`'showToggle' => false` + the page's description to the guide-block include on:

1. `resources/views/livewire/my-timetable.blade.php`
2. `resources/views/livewire/venue-timetable.blade.php`
3. `resources/views/livewire/cohort-timetable.blade.php`
4. `resources/views/livewire/replacement-home.blade.php`
5. `resources/views/livewire/replacement-arrangement.blade.php` (no description;
   guide items only)
6. `resources/views/livewire/request-approval.blade.php`
7. `resources/views/livewire/student-my-timetable.blade.php` (new description-only
   guide block; no guide items)
8. `resources/views/livewire/replacement-history.blade.php` (new description-only
   guide block; no guide items)

Only pages that include `partials.ui-guide-block` may pass `guideToggle`; the
student pages get the block in this change, so the contract holds.

### 5. CSS

- `.page-header`: flex row, `align-items:flex-start`,
  `justify-content:space-between`, 12px gap; `.page-header-main` keeps the
  title/chips together with `min-width:0; flex:1 1 auto` so a long 320px title
  cannot push the icon.
- `.page-desc` is no longer rendered on the 8 live pages; its rule can stay for
  other callers.
- `.guide-toggle-icon`: 40px square, flex-centred, token border/background,
  hover/focus-visible, `[aria-expanded="true"]` open tint, 44px at `<=768px`.
- `.guide-description`: muted body copy at the top of the guide content; keep
  rich `.info-keyword` styling.
- `.guide-block--content`: transparent/full-width content wrapper; no old bar
  chrome; top padding restored.
- The icon-only button is used at **all** widths from 320px up; no separate
  desktop bar.
- The open state remains client-only; a Livewire round-trip may re-render it
  closed (existing behaviour). Do not add a second state mechanism.

## In scope

- `resources/views/partials/ui-page-header.blade.php`
- `resources/views/partials/ui-guide-block.blade.php`
- new `resources/views/partials/ui-guide-icon.blade.php`
- `public/css/theme.css`
- the 8 live pages above
- `page-changelogs/` entries for affected pages (one shared entry where the
  page has no changelog)
- tests for the shared icon, 320/390/768/desktop geometry, the 8 opt-ins,
  description placement, open-state ARIA and non-opted pages
- named spec fixes: `tests/venue-timetable.spec.ts` (`.page-desc` read moves
  to `.guide-description` after opening the guide) and
  `tests/Feature/StudentReplacementHistoryParityTest.php` (the one-line header
  include pin must accept the new `guideToggle` argument).

## Out of scope

- Guide content wording or item changes beyond moving the existing page
  description.
- Modal/popover guide redesign.
- Design-template copies under `resources/views/ui-design-templates/`; they are
  frozen mocks and keep the old bar/description. They still inherit the
  `.page-header` flex + `.page-header-main` wrapper, so a visual check is
  required; no guide icon is added there.
- Backend, database, booking rules, other shared components.

## Success criteria

1. At 320/390/768/desktop all 8 pages show exactly one question-mark guide
   icon, not the full-width text bar.
2. Every page description that exists (**7 of 8**; Replacement Arrangement has
   none) appears inside the open guide content, above the guide items; the
   page header no longer prints `.page-desc` on those pages.
3. Clicking the icon expands/collapses the same guide content inline and
   updates `aria-expanded`; the open icon has a visible state.
4. At 320 and 390px the icon's bounding box does not intersect the title or
   chips, and the title is not clipped; the icon is a 44px target at
   `<=768px`.
5. Non-opted pages/templates render no guide icon and keep their old bar and
   description; a header-only page has no dangling `aria-controls`.
6. No guide item text changes; no JS file change.
7. The glyph and label each have one source; the student pages render a valid
   description-only guide.
8. The guide content's first row keeps its top padding; `.guide-description`
   renders rich HTML safely.

## Risks

| Risk | Mitigation |
|---|---|
| Shared partial changes other pages | Opt-in flags default false/true; only 8 pages pass them |
| 320px title/icon overlap | Normal-flow flex row, no absolute positioning; bounding-box test |
| Description appears twice | Remove `descriptionHtml` from the 8 header includes |
| Duplicate toggles | `showToggle=false` removes the full bar on opted-in pages |
| ARIA desync | Inline handler updates `aria-expanded` with the class |
| Two glyphs/labels drift | One `ui-guide-icon` partial; one `guideLabel` owner |
| Livewire round-trip closes guide | Existing client-only state; documented, not "fixed" with a second mechanism |
| Purpose sentence hidden until guide opens | Accepted by user 2026-11-12; description now lives in the guide by request |

## Approval

- User chose shared across all pages on 2026-11-12.
- User wants one icon at 320px through desktop.
- User chose all 8 pages, including the two student pages, for the description
  move.
