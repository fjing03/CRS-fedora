# Design — ui-footer-global

## Files (5)

| # | File | Kind |
|---|---|---|
| F1 | `resources/views/flux/icon/mail.blade.php` | NEW — ONE icon joins the shared set (Lucide *mail*, `Credit: Lucide (https://lucide.dev)` header; structure cloned verbatim from `folder-git-2.blade.php`: same `@props(['variant' => 'outline'])`, same `Flux::classes` variant matcher, same svg wrapper attributes) |
| F2 | `resources/views/partials/ui-footer.blade.php` | NEW — the partial (segments ①–⑥, gap-wrapping, single-writer version) |
| F3 | `resources/views/layouts/ui-template.blade.php` | EDIT — one `@include('partials.ui-footer')` line **inside `.app-container`**, right after `@yield('content')` |
| F4 | `resources/views/layouts/login-template.blade.php` | EDIT — one `@include('partials.ui-footer', ['fixed' => true])` line after the `</div>` closing `.login-card` |
| F5 | `public/css/theme.css` | EDIT — ONE additive `.ui-footer*` block appended at EOF (comment header; token colors only; incl. the ≤768px full-bleed mirror — see Placement mechanics) |

## Placement mechanics (verified anchors)

- **App pages** — `.app-container` is already `display: flex; flex-direction: column; min-height: 100vh; padding: 16px 24px` (`theme.css:608–615`). The footer, as its LAST child with `margin-top: auto`, is **bottom-pinned when content is short** and flows after content when long.
- **Per-breakpoint full-bleed coupling (review round 1):** the container's x-padding is NOT constant — `theme.css:1285–1286`: `@media (max-width: 768px) { .app-container { padding: 10px 12px; padding-top: 66px; } }`. The footer mirrors it **per breakpoint**, inside the same T4 block: `margin-left/right: -24px` (desktop) / `-12px` (≤768px). Without the mirror the strip overhangs by 12px per side and gets clipped by `body { overflow-x: hidden }` (`theme.css:156`).
- **Auth pages** — the login body centers the card (`align-items/justify-content: center; overflow: hidden`, `login-template:39–49`); a document-flow footer would be misplaced there, so the auth variant is `position: fixed; left: 0; right: 0; bottom: 0; margin: 0; z-index: 5` (above `.bg-layer`/`.bg-overlay`, both pointer-events-none fixed layers; `.login-card` z:10 / `.theme-toggle` z:20 sit higher — no stacking conflict) with `background: transparent`. Its margins are inert by construction (`left/right: 0`), so the bleed coupling can't leak into the auth variant.
- Identity/owner/notice segments render in the center; links always real `<a>` (keyboard reachable; `aria-label` + `title` per §10.4 rule).

## Token map (verified present in BOTH themes)

| Use | Token | Anchor |
|---|---|---|
| Footer text | `var(--color-on-surface-variant)` | :25 (dark) / :92 (light) |
| Link hover text | `var(--color-on-surface)` | :22 |
| Hairline border + chip border | `var(--color-outline)` | :57 |
| Strip background (app variant) | `var(--color-bg)` (fades into the body — strip reads as one quiet line) | :18 |
| Chip radius | `var(--radius-pill)` (999px, defined both themes :78/:142; precedent `.semester-chip` :645) | — |

No hardcoded colors anywhere in the block.

## Wrapping law

`flex-wrap` with **gap only — no `·` text separators**: separators orphan at line starts after a wrap; gap-only wrapping keeps every segment intact. Estimated 2 visual lines at ≥1280px viewports, ≤3 on mobile.

## Version chip — single-writer law

`@php($version = 'v1.0')` lives INSIDE the partial; no other file names the version. Future bumps edit exactly one line.

## Icon law

The shared set (`resources/views/flux/icon/`, Lucide-credited) currently holds 4 icons; this lane adds ONE (`mail`). Usage: `<flux:icon.mail />` / `<flux:icon.folder-git-2 />` standalone component syntax (Flux anonymous components under `views/flux/icon/`; the `<flux:icon.*>` syntax is proven in-repo in `pages/settings/*.blade.php`).

## Scope exclusion (review round 1, 🟡 — consciously logged)

The Fortify/Flux settings surfaces (`pages/settings/*`) render through the starter-kit `layouts/app` machinery (`x-pages::settings.layout`), NOT `ui-template`/`login-template`. They are **consciously excluded** (no F6): the user's spec = app pages + login/auth pages; the settings shell is the vendor scaffold with its own visual language. This exclusion is ALSO logged in the new changelog so the "system-wide" claim stays honest. A follow-up lane may dress them later if the user asks.

## Promoted to shared

- `partials/ui-footer.blade.php` — NEW shared component, wired from `layouts/` (never per-page).
- `.ui-footer*` classes — added ONCE to `theme.css` (no per-page styles).
- `flux/icon/mail.blade.php` — added to the shared icon set.

## NON-goals

No JS, no PHP logic, no routes/DB, no vendor edits, no app-page edits, no push. Settings surfaces excluded (see Scope exclusion). Also record in the changelog.
