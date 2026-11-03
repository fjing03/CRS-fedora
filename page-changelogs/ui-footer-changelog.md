# System-wide footer (`ui-footer-global`)

**Created:** 2026-11-03 · SDD lane `.sdd/changes/ui-footer-global/` (proposed, designed and
reviewed 2026-11-01; applied 2026-11-03).

## What it is

ONE quiet, centered strip that ends every page — identity, version, ownership, the honest
demo-data disclaimer, a contact and the repository link. It replaces "the app ends
abruptly after the content" with a single line that satisfies three standing rules at once:
*minimise plain text* (one strip, not per-page clutter), *honest claims* (the demo
disclaimer is the document-level twin of the honest-zeros law), and *composition*
(one shared partial wired from the layouts, never inline per page).

## Contents (segments ①–⑥, gap-spaced — no `·` text separators)

1. `TAR UMT Class Replacement System`
2. version chip **`v1.0`** — the `@php($version = 'v1.0')` constant lives **inside**
   `partials/ui-footer.blade.php` and nowhere else (single-writer law: a future bump edits
   exactly one line).
3. `© 2026 Poong Foo Jing · Bachelor in Information Technology (Honours) (Software Systems
   Development) · TAR UMT Sabah (RSD)`
4. `Demo dataset — all names & schedules simulated`
5. `poongfj@gmail.com` — icon-led `mailto:` (`mail` icon), real `<a>`, `aria-label` + `title`
6. `https://github.com/fjing03/CRS-fedora` — icon-led (`folder-git-2`), `target="_blank"`,
   `rel="noopener noreferrer"`, `aria-label` + `title`

No JavaScript. No PHP logic beyond the version constant and the optional `fixed` switch.

## Files

| # | File | Change |
|---|---|---|
| F1 | `resources/views/flux/icon/mail.blade.php` | **new** — Lucide *mail*, cloned structure from `folder-git-2.blade.php`; the shared icon set goes 4 → 5 |
| F2 | `resources/views/partials/ui-footer.blade.php` | **new** — segments ①–⑥ + the version constant; `$fixed` prop |
| F3 | `resources/views/layouts/ui-template.blade.php` | one `@include('partials.ui-footer')` inside `.app-container`, right after `@yield('content')` |
| F4 | `resources/views/layouts/login-template.blade.php` | one `@include('partials.ui-footer', ['fixed' => true])` after the `.login-card` close, before the inline script |
| F5 | `public/css/theme.css` | ONE additive `.ui-footer*` block at EOF (tokens only — no hex/rgb) |

## Design laws honoured

- **Tokens only**: `--color-on-surface-variant` (text), `--color-on-surface` (link hover),
  `--color-outline` (hairline + chip border), `--color-bg` (strip background),
  `--radius-pill` (chip, the `.semester-chip` precedent). Zero literal colours.
- **Per-breakpoint full bleed**: `.app-container` x-padding is `16px 24px` on desktop and
  `10px 12px` at ≤768px, so the strip mirrors it (−24px / **−12px**). Without the mirror it
  overhangs 12px per side and is clipped by `body { overflow-x: hidden }` — this was review
  round 1's blocker and is now a permanent coupling.
- **Bottom-pinned**: `margin-top: auto` inside the flex-column `.app-container`, so a short
  page still ends with the strip at the bottom and a long page flows it after the content.
- **Auth variant**: `position: fixed; left/right: 0; bottom: 0; margin: 0; z-index: 5`,
  above the pointer-events-none background layers and below `.login-card` (z 10) and the
  theme toggle (z 20). Written **after** the media mirror on purpose (equal specificity,
  later wins) so `margin: 0` neutralises the bleed there.
- **Print**: `@media print { .ui-footer { display: none; } }`.

### The one conditional in the frozen design (recorded honestly)

The design specified `background: transparent` for the auth variant. Eyeballed over the
login photo at 12px, that failed AA legibility in **light** mode (dark-grey text on grass),
so the prompt's §4 sanctioned tool was used: a **token-only `color-mix` scrim**,
`background: color-mix(in srgb, var(--color-bg) 62%, transparent)`. No hex, no new token,
and it darkens the strip under the light text in dark mode too.

## Scope exclusion (deliberate, so "system-wide" stays honest)

The Fortify/Flux **settings surfaces** (`pages/settings/*`) render through the starter-kit
`x-pages::settings.layout` shell, **not** `ui-template`/`login-template`. The footer
therefore covers **app pages + login/auth pages only**. Wiring the settings shell is a
separate decision for a separate lane — it was consciously excluded at review, not
overlooked.

## Receipts (2026-11-03)

- `composer run lint:check` → **passed** · `vendor/bin/phpstan analyse --memory-limit=1G` →
  **0 errors** · full suite on `class_replacement_testing` → **312 passed**, 1667 assertions.
- Live on `:8000` (lecturer 5770), `scripts/dev-serve.sh 8000` (never `pkill` + `artisan serve`):
  - **login page** — strip `position: fixed`, full width 0→1400, transparent-turned-scrim
    background, `cardBottom 753 < strip top 827` (no overlap), legible in **dark and light**;
  - **app pages** (my-timetable / cohort-timetable / request-history) — in flow, 0→1400
    full bleed, `document.scrollWidth === 1400` (**no horizontal overflow**);
  - **bottom-pinned** on the short page: strip bottom 884 vs viewport 900 (the container's
    16px bottom padding) — proof that `margin-top: auto` works;
  - **390px** — `left 0 · right 390 · scrollWidth 390`, child edges 12…378 (the −12px mirror
    holds), wrapping to 6 quiet lines, nothing clipped;
  - **print** — `display: none`.
  Screenshots: `/tmp/opencode/footer-login-light.png`, `footer-login-dark.png`,
  `footer-390-bottom.png`, `footer-strip-desktop.png`.

## Follow-ups (not part of this lane)

- The CRS-demo twin (`:8001`, `../CRS-demo`) receives the footer through its own git pull.
- If the settings surfaces should ever carry it, that is a new lane (different shell).
