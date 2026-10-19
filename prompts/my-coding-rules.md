# My Coding Rules — READ BEFORE ANY CODING OR DESIGN TASK

> The always-read form of the user's coding rules for `CRS-fedora-backend`.
> Distilled from `CodingMAIN.md §10` (Coding Conventions + UI Design Rules, user-mandated 2026-08-01)
> and `AGENTS.md` standing rules. On any conflict, **CodingMAIN.md §10 wins** — and tell the user
> this file has drifted so it can be re-synced. Last synced: 2026-10-19.

## 1. UI Design Rules — non-negotiable, every page, every change

1. **Colors: tokens only.** All colors come from the CSS custom-property set in `public/css/theme.css` (`--color-bg`, `--color-surface`, `--color-primary/secondary/tertiary/error` + their `-container`/`-on-*` variants; defined once for dark + once for light). **NEVER hardcode hex/rgb/rgba** in a page's `@section('page-styles')` — use `var(--color-...)`. A new color needed → add the token to `theme.css` ONCE.
   - Sole sanctioned exception (FOUC guard) in `resources/views/layouts/ui-template.blade.php`: `html.dark { background: #0D1B2A; }` / `html.light { background: #F0F3F7; }` — if the theme bg token ever changes, update BOTH together (grep `html.dark`). No other hardcoded color is permitted.
   - Slot-grid colors fixed token-mapped: Green=`--color-secondary`, Red=`--color-error`, Yellow=`--color-tertiary`, Grey=`--color-outline-strong`, Blue=`--color-primary`.
2. **Same name + same color for the same meaning — everywhere.** The canonical pairs below are the single source of truth: never give a concept a different label or color on another page, never invent a synonym. Identical component = identical class name across pages (`.badge`, `.legend-item`, `.summary-card`, `.filter-select`, `.cell-code`, `.empty-state`, …), defined once in `theme.css`.
   - Timetable blocks use the **frozen two-axis language**: `event-mine` / `event-others` / `event-mine-pending` / `event-others-pending` / `event-conflict` / `event-public-holiday`; ownership hint *"thick border (3px) = your classes · thin border (0.5px) = others'"*; tooltips render `name · lecturer · status` via `data-name` + `data-tip2`.

### Canonical legend / status → color map (single source of truth)

Two semantic contexts share one palette — green=free/ok, red=conflict/occupied, yellow=pending, blue=primary, grey=reserved/neutral.

**A. Read-only timetable legend** (block classes in `theme.css`):

| Block class | Fill token | Meaning |
|-------------|-----------|---------|
| `event-mine` / `event-others` | `--color-secondary` (green) | scheduled class (border weight = ownership) |
| `event-mine-pending` / `event-others-pending` | `--color-tertiary` (yellow) | awaiting PL approval |
| `event-conflict` | `--color-error` (red, 3px border) | YOUR conflict or public-holiday class |
| `event-public-holiday` | `--color-error` (red, hairline) | others' conflict / holiday |

**B. Replacement-arrangement slot grid legend** (booking interface, slot states — Wave 3b):

| Label | Token | Meaning |
|-------|-------|---------|
| Available | `--color-secondary` (green) | satisfies all constraints |
| Your Current Selection | `--color-primary` (blue) | active pick |
| Pending (You) | `--color-tertiary` (yellow) | your submitted request awaiting PL |
| Reserved by Others | `--color-surface-variant` (grey) | another lecturer's pending request |
| Occupied / Class on Public Holiday | `--color-error` (red) | locked / blocked |

**C. Request-history status badges** (`.status-*`):

| Label | Container tokens |
|-------|------------------|
| Pending | `--color-tertiary-container` / `-on-tertiary-container` |
| Approved | `--color-secondary-container` / `-on-secondary-container` |
| Rejected | `--color-error-container` / `-on-error-container` |
| Cancelled | `--color-surface-variant` / `-on-surface-variant` |
| Completed | `--color-primary-container` / `-on-primary-container` |

Adding a new status/legend item = **ONE row** in the relevant table above + **ONE class** in `theme.css`, then reuse it everywhere.

3. **Utilise OOP concepts** (§2 below): no copy-paste — reuse layout / partials / shared modules.
4. **Minimise plain text, maximise icon buttons.** Icon buttons for self-evident actions (edit ✎, cancel ✕, approve ✓, view 👁, chevrons ‹ ›, sort ▲▼, theme-toggle, notifications) with `title`/`aria-label` for accessibility; visible text only for page title, table headers, status badges, and the key data. Reuse the inline SVG set in `resources/views/flux/icon/` — no ad-hoc SVGs per page.
5. **Don't overwhelm — detail goes in modals.** Page surface = grid/table + filters + summary cards + primary actions. Anything "nice to know" rather than "need to scan" opens in a modal behind an icon button, never inline.
6. **Promote-on-3rd-duplication (DRY/OOP).** A markup block / CSS class / JS helper identical across 3+ pages → promote to `resources/views/partials/`, `theme.css`, or `ui-common.js` and refactor the duplicates away. Record every promotion in the SDD `design.md` "Promoted to shared" section.
7. **Minimise steps — fewest clicks.** Pre-select sensible defaults; no page-hopping for a single task; >~3 clicks → rethink the flow.
8. **Confirm critical actions.** Delete / submit / cancel / approve / reject → confirmation popup BEFORE executing.
9. **Mobile responsive on every page** (≤768px breakpoint): nav drawer, card layout for tables, 2-col summary cards, flex-wrap legend, bottom-sheet modals, ≥44×44px touch targets (WCAG 2.5.5), swipe week navigation, `clamp()` typography, safe-area insets, full-width inputs, viewport meta with `viewport-fit=cover`. Shared CSS in `theme.css`, JS helpers in `ui-common.js`.
10. **Toast/undo bar for critical actions.** Bottom-left toast with success message + **Undo**, auto-dismiss after **5 seconds** — `showToast(message, undoCallback, duration)` in `ui-common.js`, CSS in `theme.css`.

## 2. OOP at every layer (FYP rubric evaluates this)

- **Inheritance** — every page `@extends('layouts.ui-template')` filling `@section('content')`, `@section('page-styles')`, `@section('page-scripts')`, `@yield('title')`. Eloquent models extend `Model`; `User` extends `Authenticatable`; middleware extends base classes; migrations use anonymous `class extends Migration`.
- **Composition** — Blade partials via `@include`: `ui-nav-bar` (with `activeNav`), `ui-summary-bar`, `ui-page-header`, `ui-week-nav` (incl. `showPrint`), `ui-guide-block`, `ui-legend-bar` (incl. `ownershipHint`), `ui-empty-state`, `ui-grid-table`, `ui-class-detail-modal`, `ui-cancel-class-modal`, `ui-today-btn`, `ui-venue-dropdown`, `ui-logout-modal`.
- **Encapsulation & DRY — shared homes:**
  - `public/css/theme.css` — ALL shared CSS (page-specific styles stay in `@section('page-styles')`).
  - `public/js/ui-common.js` — shared JS helpers (`buildTimetableGrid` two-axis default rendering + span coalescing, `WeekNavigator`, `computeSummary`, `buildReplacementNote`, `openClassModal`, `CancelClass`, `showToast`, theme helpers).
  - `public/js/mock-data.js` — `window.MockData` single source of truth. Treat it as **read-only** (`slice()`/spread before mutating); new data = ONE new section in `mock-data.js`, never an inline page dataset. Real-data pages override `MockData.currentUser` / semester / holidays from the server payload.
  - **NEVER** copy-paste nav bar, tables, or helpers into a new page.
  - Theme toggle: `localStorage('theme')` = `dark`/`light`, `<html class="dark">` default; login pages use `.login-theme-toggle`.
- **No business logic in controllers/views** — core logic lives in service classes (NFR 5.3): `App\Services\MatrixIntersectionEngine`, `App\Services\OCCValidator`.
- **No new dependencies** — reuse Flux / Livewire / Tailwind + `theme.css` + `ui-common.js`.

## 3. PHP / Laravel conventions

- PSR-4 (`App\`); anonymous classes for migrations; `protected function casts()` for attribute casting.
- Route model binding; Eloquent relationships typed with return types + PHPDoc.
- Role gating follows the existing middleware pattern (`CheckRole`, `CheckPl`) — no new gating styles.
- Layout by convention: Fortify actions in `app/Actions/Fortify/`, concerns/traits in `app/Concerns/`, Livewire components in `app/Livewire/`.
- **Quality gates before finishing any PHP-touching task**: `composer run lint:check` AND `vendor/bin/phpstan analyse --memory-limit=1G`. **NEVER** `composer run types:check` (crashes at the default 128M).

## 4. Naming

- Blade templates: `kebab-case-UI-design-template.blade.php` for UI mocks; `layouts/ui-template` for the real layout.
- Tables: `snake_case` plural; models: `StudlyCase` singular.
- CSS classes: semantic kebab-case (`.col-code`, `.badge`, `.summary-card`, `.filter-select`).
- Service classes: `StudlyCase` in `App\Services\`.

## 5. Git & commits

- Primary working branch: **`fedora-backend`**. Conventional commits only: `ui:`, `feat:`, `fix:`, `refactor:`, `oop:`, `style:`, `docs:`, `test:`.
- Never commit secrets.
- **Push ONLY when the user explicitly says "push"** — `origin` (`fjing03/CRS-fedora.git`) is the only push target; `upstream` is PULL-ONLY. Verify `git remote -v && git branch --show-current` before any push.
- Every change runs through SDD: `.sdd/changes/<change-name>/` (`proposal.md`, `design.md`, `tasks.md`, `review-log.md`) → verify → archive to `.sdd/archive/`. **No proposal, no change** — and review gates are honest (a real reviewer round or an explicitly logged user waiver; never self-declare frozen).

## 6. Hard session-ops absolutes live elsewhere — obey both, don't duplicate

The demo-DB sanctity rules (never re-seed `class_replacement_fresh`; tests → `class_replacement_testing`), the ⛔TC70 `--grep-invert` guard, the stale-cache restart ritual (two separate commands; never `pkill -9 php`), server :8000 + re-login 5770, honest zeros, and the after-UI-change changelog requirement are owned by **`AGENTS.md`** + the pi skill **`crs-workspace`** — read those too before acting.
