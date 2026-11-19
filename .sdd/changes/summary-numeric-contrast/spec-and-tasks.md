# Summary Cards: numeric contrast

Status: applied and verified locally. Independent review returned HTTP 429; the owner approved the light pending foreground and explicitly waived independent review. This is the numeric portion of `docs/ui-contrast-2026-11-17.md` (C1–C3, C6 and the positive part of C8), not the whole audit.

## Decision proposed

- Keep every card fill, border, status family, label, count and type size. Use four semantic foreground roles for shared live flip-card values. Alias blue, red and green to the existing per-theme `--color-on-primary-container`, `--color-on-error-container`, and `--color-on-success-container`. Include My Replacements' blue `card-duration` and remove its page-local number overrides so there is one owner.
- Keep pending amber. Use the existing `--color-on-tertiary-container` in dark; for light, derive a darker amber foreground with `color-mix(in srgb, var(--color-on-warning-container) 80%, var(--color-on-tertiary))`. Both mix inputs are existing foreground tokens (`#8A5800` and `#1A1000` in light). No new hardcoded colour, status hue, or fill.
- Use the pending foreground for the Pending number and positive `+N`; the suffix renders at 13.64–16.12px/600, so require 4.5:1, not 3:1. Existing amber ink `--color-on-warning-container` alone reached only 3.94:1 on the light green Total card. Zero-suffix logic is untouched.
- Out of scope: C1's already treated modal tab, C4/C5 urgency text, C7 card labels, C9 filter chips, control outlines, and legacy plain-card templates.

## Candidate evidence (browser only)

All seven routes at 1440 and 390, in both themes; empty Request Approval used its existing rendered shell, and missing positive suffixes were sampled locally in the browser. Light pending on white: 2.87:1 → 7.76:1. Dark blue on Venue: 1.79:1 → 5.64:1. Worst sampled suffix: light 1.43:1 → 5.06:1; dark 4.76:1 remains 4.76:1. All 140 value and 52 suffix observations met their size-specific contrast targets in the candidate. No app code or database content was changed in this trial.

## Tasks / acceptance

- [x] Apply only after the choice and independent review (or owner waiver). Scope all replacements to `.summary-section--flip` and add matching page-changelog entries.
- [x] Measure before and after on all seven live hosts at 1440 mouse and 390 touch, both themes. Composite the actual translucent card fills. Require at least 3:1 for eligible large values and 4.5:1 for the smaller suffix, with card backgrounds/borders and content unchanged. A browser-only sample may exercise positive suffixes or the empty Request Approval shell; do not create data.
- [x] Update audit status and correct its suffix size. Capture representative renders, record evidence, and commit only the scoped changes. No database content writes or push.

## Verification

After the actual CSS shipped locally, the same 28 Chromium contexts reproduced the trial exactly.
All 192 observations passed; fills, font sizes and labels matched baseline. No Blade markup or
counting logic changed (the My Replacements Blade edit only removed redundant inline CSS).
Pint and PHPStan passed. Representative screenshots and logs: `/tmp/crs-summary-contrast/`;
raw matrices: `/tmp/crs-summary-contrast-{before,mixed,after}.json`.
