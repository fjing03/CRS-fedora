# My Coding Rules — READ BEFORE ANY CODING OR DESIGN TASK

> The always-read form of the user's coding rules for `CRS-fedora-backend`.
> Distilled from `CodingMAIN.md §10` (Coding Conventions + UI Design Rules, user-mandated 2026-08-01)
> and `AGENTS.md` standing rules. On any conflict, **CodingMAIN.md §10 wins** — and tell the user
> this file has drifted so it can be re-synced. Last synced: 2026-11-07 (late).

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
| `event-mine-replacement` / `event-others-replacement` | `--color-primary` (blue) | approved replacement (border weight = ownership; the my-timetable replacement hue extended to cohort + venue, user-mandated 2026-10-19) |
| `event-conflict` | `--color-error` (red, 3px border) | YOUR conflict or public-holiday class |
| `event-public-holiday` | `--color-error` (red, hairline) | others' conflict / holiday |

**B. Replacement-arrangement slot grid legend** (booking interface, slot states — Wave 3b):

| Label | Token | Meaning |
|-------|-------|---------|
| Available | `--color-secondary` (green) | satisfies all constraints |
| Your Current Selection | `--color-primary` (blue) | active pick |
| Your Pending | `--color-tertiary` (yellow, 3px) | your submitted request awaiting PL |
| Other Pending | `--color-tertiary` (yellow, no border) | another staff member's pending request |
| Your Conflict | `--color-error` (red, 3px) | your own class / own replacement |
| Others Conflict | `--color-error` (red, no border) | the room or the cohorts are taken |

The **cell's** hover label names the *reason* (`Venue Conflict`, `Cohorts Conflict`,
`Your Conflict`, `Your & Cohorts Conflict`, `Your Pending`, `Other Pending`) — chosen by one
resolver (`classifyState()` in the page script) from the busy rows' `mine` + `arms` and the grid's
`status 1`; the legend groups the non-yours red into `Others Conflict` on purpose. The words are
a real `<span class="cell-state-label">` in the cell (2 lines, 11 px), never the CSS overlay.

**C. Request-history status badges** (`.status-*`):

| Label | Container tokens |
|-------|------------------|
| Pending | `--color-tertiary-container` / `-on-tertiary-container` |
| Approved | `--color-secondary-container` / `-on-secondary-container` |
| Rejected | `--color-error-container` / `-on-error-container` |
| Cancelled | `--color-surface-variant` / `-on-surface-variant` |
| Completed | `--color-primary-container` / `-on-primary-container` |

**D. Day-countdown bands** (`.urgency-*` — `theme.css`, one rule for both pages):

| Band | Token | Meaning |
|------|-------|---------|
| `.urgency-high` | `--color-error` (red) | past or `Today` |
| `.urgency-mid` | `--color-warning` (amber) | 1–7 working days left |
| `.urgency-low` | `--color-success` (green) | 8+ working days left |

The band is chosen by `urgencyBandClass(days)` in `ui-common.js` — never by a page. Words:
`Today` · `1 Working Day Left` · `N Working Days Left` · `Overdue (N Working Day Ago)`;
working days = Mon–Fri minus the timetable's holidays. A pending request also shows the
make-up countdown beside the requested date; approved/completed print `—`.

Adding a new status/legend item = **ONE row** in the relevant table above + **ONE class** in `theme.css`, then reuse it everywhere.

3. **Utilise OOP concepts** (§2 below): no copy-paste — reuse layout / partials / shared modules.
4. **Minimise plain text, maximise icon buttons.** Icon buttons for self-evident actions (edit ✎, cancel ✕, approve ✓, view 👁, chevrons ‹ ›, sort ▲▼, theme-toggle, notifications) with `title`/`aria-label` for accessibility; visible text only for page title, table headers, status badges, and the key data. Reuse the inline SVG set in `resources/views/flux/icon/` — no ad-hoc SVGs per page.
5. **Don't overwhelm — detail goes in modals.** Page surface = grid/table + filters + summary cards + primary actions. Anything "nice to know" rather than "need to scan" opens in a modal behind an icon button, never inline.
6. **Promote-on-3rd-duplication (DRY/OOP).** A markup block / CSS class / JS helper identical across 3+ pages → promote to `resources/views/partials/`, `theme.css`, or `ui-common.js` and refactor the duplicates away. Record every promotion in the SDD `design.md` "Promoted to shared" section.
7. **Minimise steps — fewest clicks.** Pre-select sensible defaults; no page-hopping for a single task; >~3 clicks → rethink the flow.
8. **Confirm critical actions, and say when it is final.** Delete / submit / cancel / approve / reject → confirmation popup BEFORE executing. A decision that cannot be taken back also carries the shared sentence **"This action cannot be UNDONE."** — one partial, `resources/views/partials/ui-irreversible-line.blade.php`, included by the Approve, Reject, Cancel Request and Cancel Class modals. Reversible actions do not get it.
9. **Mobile responsive on every page** (≤768px breakpoint): nav drawer, card layout for tables, 2-col summary cards, flex-wrap legend, bottom-sheet modals, ≥44×44px touch targets (WCAG 2.5.5), swipe week navigation, `clamp()` typography, safe-area insets, full-width inputs, viewport meta with `viewport-fit=cover`. Shared CSS in `theme.css`, JS helpers in `ui-common.js`.
10. **Toast bar for critical actions.** Bottom-left toast with the success message, auto-dismiss after **5 seconds** — `toast.show(message, undoCallback, duration)` in `ui-common.js`, CSS in `theme.css`. **Undo is offered only where the action is genuinely reversible** (hiding a notification, clearing a selection). **Cancel Class is FINAL** (user ruling 2026-11-05: *"i give up all UNDONE function"*) — no undo button, no undo callback, no server undo route; the confirmation itself is the receipt. The cancellation reminder is **one-shot**: it may return on ONE later page load, never on every load, and a `?blocked=` arrival speaks first (its own message wins; the reminder keeps its turn for the next load).
11. **Dates and times speak one format — already shipped, keep it.** Date `5 Nov 2026` (no leading zero, day first); time `2:00 PM`; range `2:00 PM ~ 4:00 PM` (the tilde is the joiner; a hyphen is acceptable but never mix joiners in one element; the en dash `–` is not a range joiner). Day word `Thu` in compact lines (table cells, cards, chips, picker labels, tooltips) and `Thursday` in prose (sentences, questions, toasts, expanded modal rows). The 24-hour grid hour axis is a scale and stays 24-hour; notification tooltips stay short (no year). Emails carry the full date + 12-hour range (`Mon, 9 Nov 2026, 1:30 PM ~ 3:30 PM`) via `ReplacementRequestMapper::mailSchedule()`. Full audit + batch log: `docs/date-time-format-audit-2026-11-07.md`. New surface → use the standard, never invent a fourth shape.

## 2. OOP at every layer (FYP rubric evaluates this)

- **Inheritance** — every page `@extends('layouts.ui-template')` filling `@section('content')`, `@section('page-styles')`, `@section('page-scripts')`, `@yield('title')`. Eloquent models extend `Model`; `User` extends `Authenticatable`; middleware extends base classes; migrations use anonymous `class extends Migration`.
- **Composition** — Blade partials via `@include`: `ui-nav-bar` (with `activeNav`), `ui-summary-bar`, `ui-page-header`, `ui-week-nav` (incl. `showPrint`, opt-in `showEarliestBtn`), `ui-guide-block`, `ui-legend-bar` (incl. `ownershipHint`), `ui-empty-state`, `ui-grid-table`, `ui-class-detail-modal`, `ui-cancel-class-modal`, `ui-today-btn`, `ui-venue-dropdown`, `ui-logout-modal`.
- **Encapsulation & DRY — shared homes:**
  - `public/css/theme.css` — ALL shared CSS (page-specific styles stay in `@section('page-styles')`).
  - `public/js/ui-common.js` — shared JS helpers (`buildTimetableGrid` two-axis default rendering + span coalescing, `WeekNavigator`, `computeSummary`, `buildReplacementNote`, `openClassModal`, `CancelClass`, `toast.show`, theme helpers).
  - `public/js/mock-data.js` — `window.MockData` single source of truth. Treat it as **read-only** (`slice()`/spread before mutating); new data = ONE new section in `mock-data.js`, never an inline page dataset. Real-data pages override `MockData.currentUser` / semester / holidays from the server payload.
  - **One flash mechanic, tones — never a second animation.** `flashRowPulse(el, tone)` in
    `ui-common.js` is the only highlight: green (Earliest bookable / Notifications / Go to My Timetable /
    after-submit `?new-req=`), blue (Today column) and `'warning'` (the just-cancelled row,
    `bookable-flash--warning`). New highlight → a tone on this function + a CSS arm, never new JS.
  - **A cancelled request is displayed as "no active request", never as its old proposal** (user
    brief 2026-11-06). `ReplacementHome::buildRow()` flips an owner-cancelled row to the no-request
    state (Need to Arrange badge, em-dash REQUESTED cell, no Proposed tab) — the RECORD stays
    `cancelled` with its audit and the release. Normalise in the payload, not in three JS branches.
    The page's status filter therefore has **no Cancelled option** any more, and a filter saved before
    the removal is mapped onto Need to Arrange at restore.
  - **A JS-owned control must survive a Livewire round-trip.** A page component's re-render morphs the
    DOM from the server's HTML; anything the server renders EMPTY but JavaScript fills (the week
    selects, `#filterChips`) is deleted by that morph. Never park state there without a guard: the
    week selects are snapshot+restored around `pageLivewire().call()` by
    `withWeekLivewireGuard()` in `ui-common.js` (2026-11-06 — the blank-Week bug). If a restore must
    land before the caller's `.then()` (because that `.then()` reads the value), do it there, not in a
    `setTimeout`.
  - **A page function used by an inline `onclick=` in a shared partial must be exported on
    `window`** (`window.closeSlotWarning = closeSlotWarning;` beside the existing exports near the
    end of the page's `DOMContentLoaded` block). The page script runs inside that closure, so only
    the explicitly exported names are reachable from the partial's markup — the symptom is a modal
    that opens but refuses to close with *"… is not defined"*.
  - **A notification row's tooltip comes from ONE composer.** `App\Support\Notifications\NotificationTip`
    decides the kind from the tile `type` + stored title and prints the shared label set (Subject /
    schedules / `Rejection Reason` / `Reason` / `Request ID`), holiday-first reason in the Original
    line's parentheses, `Rejection Reason: Automatically Rejected by System` when `approver_id IS NULL`,
    students without the Staff lines, and the legacy `title — body` only for an unknown kind. New kind →
    a branch there + one receipt, never a page-local tip.
  - **Border weight = ownership, on the grid too.** The replacement-arrangement grid follows the
    two-axis language (colour = state, border = owner) with **0px** instead of the 0.5px hairline:
    amber = a pending reservation, red = a conflict; 3px = yours, no border = someone else's. One
    resolver (`classifyCell()`, exported on `window`) decides from BOTH arms, and ownership is
    **data** (the busy tuple's 6th key `mine`, the venue payload's `status 3` vs `4`) — never the
    holder text, never the colour. A red cell is 3px if ANY blocking row is mine; rows that are the
    same fact are merged in the payload so the tooltip keeps one block. A legend swatch must carry
    the real cell class, or it will drift from the grid.
  - **A `data-tip` is TEXT, not HTML.** The shared engine (`initDataTipTooltips` in
    `ui-common.js`) sets `textContent` and switches to `pre-line` when the string contains a
    newline, so a multi-line tooltip is `"line one\nline two"` — a `<br>` prints literally. Keep
    the copy in ONE server-side source (a status tip belongs in
    `App\Services\ReplacementRequestMapper::statusTip()`), never a page-local copy.
  - **NEVER** copy-paste nav bar, tables, or helpers into a new page.
  - Theme toggle: `localStorage('theme')` = `dark`/`light`, `<html class="dark">` default; login pages use `.login-theme-toggle`.
- **No business logic in controllers/views** — core logic lives in service classes (NFR 5.3): `App\Services\MatrixIntersectionEngine`, `App\Services\OCCValidator`.
- **No new dependencies** — reuse Flux / Livewire / Tailwind + `theme.css` + `ui-common.js`.

## 3. PHP / Laravel conventions

- PSR-4 (`App\`); anonymous classes for migrations; `protected function casts()` for attribute casting.
- Route model binding; Eloquent relationships typed with return types + PHPDoc.
- Role gating follows the existing middleware pattern (`CheckRole`, `CheckPl`) — no new gating styles.
- **A request keeps its own length.** A replacement slot is a 30-minute cell, and the release
  actions (reject / cancel / auto-reject) delete the junction rows that recorded how many cells a
  proposal covered. `replacement_requests.replacement_span` stores the width at submit and
  `ReplacementRequestMapper::spanFromAnchor()` is the ONLY place that turns a junction-less
  request into a time range (a null width = the legacy single cell). Never rebuild that shape in
  a second place — a duplicate is exactly what kept printing `11:00 AM ~ 11:30 AM` for a 2-hour
  rejection after the fix shipped.
- **A rule the page enforces must also exist in the action.** The browser is a convenience; the
  server is the truth. TWO rules were page-only until 2026-11-06 and both now live in
  `SubmitReplacementRequest`: FR 2.17's 3-working-day notice (`slot_too_soon`, on
  `App\Support\WorkingHours`) and D2's holiday landing rule (`holiday`). New gate → the action, the
  page copy, and one test; `tests/Feature/SubmitGatesTest.php` is that file.
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

The demo-DB sanctity rules (never re-seed `class_replacement_fresh`/`class_replacement_demo`; tests → `class_replacement_testing` only via `DB_DATABASE=…`; **⛔ never `--env=testing` any destructive command — no `.env.testing` exists, it silently boots `.env` = the demo DB**; **snapshot law: verified `pg_dump -Fc` before any demo-DB write and after any demo-worthy milestone**; every demo-DB edit user-gated + logged in `docs/demo-db-data-changes.md`; **schema migrations are additive-only on the demo DBs — snapshot → rehearse on `class_replacement_testing` → print the target → plain `migrate` (fresh, then demo) → row-count receipt**), the ⛔TC70 `--grep-invert` guard, **the fail-closed Playwright write-guard (`tests/helpers/receipt-target-guard.ts`: a writing spec refuses to run unless `CRS_MODE=testing`, `CRS_BASE=http://127.0.0.1:8030` and `CRS_ALLOW_SQL=1` are exactly right — never loosen it, never add a soft skip)**, the stale-cache restart (**`scripts/dev-serve.sh [port]`**; never `pkill -9 php`, never one combined kill-and-serve string), the port map (`:8000` dev · `:8001` demo twin · **`:8030` = disposable AI/E2E lane on `class_replacement_testing`, kill it after**) + re-login 5770, honest zeros, and the after-UI-change changelog requirement are owned by **`AGENTS.md`** + the pi skill **`crs-workspace`** + **`CodingMAIN.md` §4 Database safety** — read those too before acting.

## 7. How to talk to me (every session)

- Use simple, everyday English; avoid unnecessary technical terms and long explanations.
- When I must decide: explain it in plain terms, list the options with their main risks and benefits, and recommend one if appropriate — phrase the question as **"So the question in plain terms: …"**.
- Ask one clear question at a time, and do not proceed with an action that needs my approval until I confirm.
