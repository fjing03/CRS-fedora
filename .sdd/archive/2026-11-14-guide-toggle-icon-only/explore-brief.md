# Explore brief — guide toggle as one icon

> Created 2026-11-12. Planning only; no live code changed.
> Baseline: current `fedora-backend` with the shared `ui-guide-block` partial.
> Reference: the My Timetable header's single question-mark icon at the top
> right.

## Problem

Every page that uses `partials.ui-guide-block` renders a full-width
`How to use this page ▾` bar under the page header, and every page header also
prints a `.page-desc` line. The user wants:

- the guide control to be **one icon** at every viewport from 320px to desktop,
  matching the reference image's top-right question-mark button;
- **every page description** moved from the header into the guide content.

The guide content keeps its expand/collapse behaviour; only the trigger and
the description placement change.

## Final solution

- **Shared change**, not page-local: all 8 live pages opt in. Seven carry a
  description; `replacement-arrangement` has no description and gets the icon
  with guide items only.
- `partials.ui-page-header` gets optional `guideToggle`. When true, the header
  becomes a flex row (`.page-header-main` + icon); the icon is in normal flow,
  right-aligned, 40×40px desktop / 44×44px at `<=768px`, 18px SVG.
  - `aria-controls="guideBlock"`, `aria-label` from `guideLabel` (default
    `How to use this page`), `aria-expanded` synced by the inline handler.
  - Open state gets a visible tint.
  - The 8 live pages stop passing `descriptionHtml` to the header.
- `partials.ui-guide-block` gets optional `showToggle` (default true) and
  `guideDescription` / `guideDescriptionHtml`:
  - `showToggle=false` removes the full-width bar but keeps `#guideBlock`,
    `.guide-content` and the list.
  - The description renders at the top of `.guide-content`, above the items,
    via `guideDescriptionHtml`.
  - A description-only block with no items is valid for the student pages and
    omits the empty `<ul>`.
  - `.page-header-main` uses `min-width:0; flex:1 1 auto`.
- Extract the question-mark SVG to `partials/ui-guide-icon.blade.php`; include
  it from both the page-header icon and the legacy full toggle.
- The page-header label is `guideLabel`; the six existing guide pages drop
  their unused `guideTitle`.
- The guide content stays below the page header and expands inline.
- No JS file change: the icon uses a small inline handler to toggle the class
  and update `aria-expanded`.

## Pages

Existing guide pages (add header icon + description into guide, drop
`descriptionHtml` from header, `showToggle=false`):

1. `my-timetable`
2. `venue-timetable`
3. `cohort-timetable`
4. `replacement-home`
5. `replacement-arrangement` (no description; items only)
6. `request-approval`

Student pages (new description-only guide block + header icon):

7. `student-my-timetable`
8. `replacement-history` (Class Replacements)

Design templates under `resources/views/ui-design-templates/` are frozen mocks
and stay out of scope.

## Rejected approaches

| Approach | Why rejected |
|---|---|
| Page-local override only | User chose the shared control for all pages |
| Move the toggle with JS | Extra DOM mutation and a load flash; Blade can render it correctly |
| Absolutely position the existing full bar | Fragile with the empty replacement-arrangement header |
| Modal/popover for the guide content | The user asked for the toggle/description move, not a content redesign |
| Keep the old bar on some pages | Mixed UI; user chose shared and all 8 pages |
| Leave student descriptions in the header | User chose all 8 pages |

## Cross-module contract

- `partials.ui-page-header`: new optional `guideToggle`; no change when omitted.
- `partials.ui-guide-block`: new optional `showToggle` and description fields;
  no change when omitted.
- New `partials/ui-guide-icon.blade.php`: one SVG source.
- `public/css/theme.css`: header flex, icon button, description block,
  content-only guide styles.
- 8 live page includes: header flag, guide flag, description move; student
  pages gain a guide block.
- Existing `#guideBlock` id and `.guide-block.open` state stay the same.
- Affected specs: `tests/venue-timetable.spec.ts` and
  `tests/Feature/StudentReplacementHistoryParityTest.php` require updates for
  the moved description and the new header argument.

## Open questions

- None. All 8 pages, inline guide content, description above the items.
