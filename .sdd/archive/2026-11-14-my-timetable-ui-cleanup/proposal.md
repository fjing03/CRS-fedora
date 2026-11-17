# Proposal — My Timetable UI cleanup (summary cards, guide modal, semester chrome, week select)

> **Status:** active · 2026-11-13
> **Change:** `my-timetable-ui-cleanup`
> **Reference:** `ui-prototype/my-timetable-mobile-preview.html`
> **Prompt:** `ui-prototype/my-timetable-responsive-prompt.md`

## Why

The approved responsive prompt already says the desktop page must stay ~97%
identical, with named exceptions. Three of those exceptions were only ever
delivered in the prototype:

1. the summary cards keep the prototype's 96px flip cards with three short
   bullets and a per-card info button;
2. the guide is now an icon-only opener whose content belongs in a modal, not
   an inline bar;
3. the semester progress bar and week subtitle were removed from the prototype
   but still render on the live pages.

The prototype also shows the week selector on a tinted surface
(`--color-surface-variant`), while the live `.week-select` is white. The
prototype is the agreed visual reference, so the live pages should match it
rather than run a second design.

## What

Four additive UI changes across the timetable pages:

| # | Change | Pages |
|---|---|---|
| 1 | Summary cards match the prototype: 96px flip cards, left-aligned three-bullet backs, a 44px per-card info button that opens the full explanation modal | My, Student, Cohort, Venue Timetable, My Replacements, Request Approval |
| 2 | The guide icon opens a modal containing the page description and guide items; the inline guide block no longer renders | My, Student, Cohort, Venue Timetable, My Replacements, Request Approval |
| 3 | Remove `#semesterProgress` (label + track + fill) and `.week-subtitle`/`#weekSubtitle` | My, Student, Cohort (none), Venue Timetable |
| 4 | Week select uses `--color-surface-variant` as its resting background and `--color-surface` on hover, with the existing chevron, text and focus ring unchanged | all timetable pages that render `.week-select` |

## Scope

### In scope

- `resources/views/partials/ui-summary-bar.blade.php` — prototype markup:
  `role="group"`, `tabindex="0"`, `.summary-description` with a three-bullet
  `<ul>`, `.summary-info` button, `aria-label` naming the flip.
- `resources/views/partials/ui-guide-modal.blade.php` (new) — one shared modal
  shell with the guide description and items; opened by the header icon.
- `resources/views/partials/ui-page-header.blade.php` — the guide icon opens the
  modal instead of toggling `#guideBlock`; keeps `aria-haspopup="dialog"`,
  `aria-controls`, `aria-expanded`.
- `resources/views/partials/ui-guide-block.blade.php` — no longer used by the
  four timetable pages; keep the partial unchanged for any other caller.
- Four Livewire timetable views — remove the inline guide include, the
  `#semesterProgress` markup, and the `#weekSubtitle` div; pass their existing
  description/items to the guide modal.
- `public/css/theme.css` — 96px card faces, left-aligned backs, bullet list,
  info button, `modal--summary` width, and the tinted `.week-select` resting
  background.
- `public/js/ui-common.js` — one shared summary-card controller: flip on
  hover/tap/Enter/Space, open the full explanation from the info button, and
  restore focus/back state.
- `page-changelogs/` — one dated entry per touched page.

### Out of scope

- Any summary value, calculation, payload field or `valueId`. The DOM ids stay
  exactly as they are so page scripts keep writing to them.
- Any database change, route change, new dependency or new icon library.
- The Replacement Arrangement selected-block cards (`.sel-summary-card`), which
  are a different component despite the name.
- The remaining page that includes `ui-summary-bar` (`replacement-history`).
  It keeps today's cards; only the four timetable pages, My Replacements and
  Request Approval are in this pass. The partial itself stays
  backward-compatible.
- Removing `.semester-progress` / `.week-subtitle` CSS from `theme.css` while
  `replacement-arrangement`'s commented-out block still references the names.

## Impact

| File | Change |
|---|---|
| `resources/views/partials/ui-summary-bar.blade.php` | Prototype card markup; keeps every `valueId`; new info button |
| `resources/views/partials/ui-guide-modal.blade.php` | New shared modal |
| `resources/views/partials/ui-page-header.blade.php` | Icon opens the guide modal |
| `resources/views/livewire/my-timetable.blade.php` | Drop guide block, progress, subtitle |
| `resources/views/livewire/student-my-timetable.blade.php` | Same |
| `resources/views/livewire/cohort-timetable.blade.php` | Drop guide block (no progress/subtitle existed) |
| `resources/views/livewire/venue-timetable.blade.php` | Drop guide block, progress, subtitle |
| `resources/views/livewire/replacement-home.blade.php` | Guide modal + flip cards |
| `resources/views/livewire/request-approval.blade.php` | Guide modal + flip cards |
| `public/css/theme.css` | Card, bullet, info-button, summary-modal and week-select rules |
| `public/js/ui-common.js` | Shared summary-card flip + explanation controller |

No PHP model, controller, migration or test data change.

### Risks

| Risk | Mitigation |
|---|---|
| A page script writes to a `valueId` and the card markup breaks it | Keep every `valueId` and the `.summary-value` wrapper byte-compatible; only add siblings |
| Removing the progress/subtitle DOM throws in `updateProgress()` / `updateWeekSubtitle()` | Both helpers already null-check; keep them and keep the calls, so the removal is inert |
| The guide modal duplicates ids across pages | One modal per page, id `guideModal`; the header icon is the only opener |
| The guide content is lost | The description and item arrays move into the modal unchanged |
| The week select turns into a different control | Only `background`/`background-color` change; chevron, padding, height, focus ring and option colours stay |
| Other summary-bar callers regress | The partial stays additive: no required new arguments; pages that do not pass bullets keep a plain description |
| The flip hides content on touch | Tap flips; the info button is always reachable on the back; Escape/overlay close restores the same card |

## Acceptance criteria

1. The six pages show 96px summary cards with a left-aligned back
   face containing at most three short bullets, and the numbers/labels are
   unchanged.
2. Hovering a card flips it; tapping flips it on touch; Enter/Space flips it
   from the keyboard. The info button opens a full explanation without flipping
   the card, and Back/Close restores the same card and focus.
3. The header guide icon opens a modal containing the page's description and
   every guide item; no inline guide bar remains on the six pages. The modal
   closes with the ×, the overlay or Escape.
4. `#semesterProgress` and `.week-subtitle` do not render on the four timetable
   pages, and no script error appears when the week changes.
5. Every `.week-select` on the timetable pages has a `--color-surface-variant`
   resting background and a `--color-surface` hover background, with readable
   text and the existing chevron.
6. No summary value, calculation, filter, week navigation or class popup changes
   behaviour.
