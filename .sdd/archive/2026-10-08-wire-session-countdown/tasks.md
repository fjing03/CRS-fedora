# Tasks — wire-session-countdown

**Frozen baselines:** proposal.md, design.md, explore-brief.md. Each task ≤ 2 h.
Execute in order; gates after T4; changelog in T5.

## T1 — Layout include (`resources/views/layouts/ui-template.blade.php`)
- [ ] Add `@include('partials.ui-session-countdown')` on its own line directly AFTER
  the commented-out logout-modal include block, still inside the
  `@if(!isset($hideNav) || !$hideNav)` guard (design §4).
- [ ] Nothing else changes in this file.

## T2 — Partial JS (`resources/views/partials/ui-session-countdown.blade.php`)
- [ ] Append one self-contained `<script>` IIFE after the existing markup (markup and
  its ids/attrs stay byte-identical — no edits to existing 26 lines).
- [ ] Guards: exit silently if `.session-countdown` root or `data-lifetime` missing.
- [ ] Baseline: `remaining = duration − (now − data-last-activity)`; 1 s `setInterval`.
- [ ] Thresholds per design §2 table: bar revealed at ≤ 300 s remaining or
  `duration ≤ 300`; modal at ≤ 60 s; `#countdown-min` = ceil minutes,
  `#modal-countdown` = seconds.
- [ ] Reveal via inline `el.style.display` toggling on BOTH the bar root and
  `#sessionModal` ('none' ↔ 'flex') — NO class-based reveal (design §2 reveal rule).
- [ ] Livewire baseline reset: if `window.Livewire` exists, `Livewire.hook('commit', …)`
  updates the baseline to now (try/catch guarded); also listen for
  `livewire:navigated` with the same reset (belt-and-braces per design §2);
  non-Livewire pages unaffected.
- [ ] Single logout path `fireLogout()`: `fired` flag + `clearInterval` +
  submit `#countdown-logout-form`; `fired` also disables both logout forms' submit
  controls; a submit-capture listener on the modal's id-less form sets `fired` first
  (race guard per design §2).

## T3 — CSS (`public/css/theme.css`)
- [ ] Append one commented block implementing design §3's table for
  `.session-countdown`, `.btn-extend`, `.session-modal-overlay`, `.session-modal`,
  and the scoped `.session-modal-actions .btn-primary` / `.btn-secondary`.
- [ ] Tokens ONLY (no hex/rgb); use exactly the tokens listed in design §3 —
  `--color-outline` (NOT the non-existent `--color-outline-variant`).
- [ ] Bar `z-index` below the modal overlay.

## T4 — Tests (`tests/auth-full.spec.ts`)
- [ ] In the gated session-expiry describe, re-add the two client tests per design §5:
  (a) client countdown auto-logout at 0 → `waitForURL('**/login/staff')` (85 s
  timeout) → `/my-timetable-ui` bounces to login (assert URL only, no flash text);
  (b) extend keeps session alive: wait for bar/modal visible → click `.btn-extend` →
  reload lands authenticated (URL not a login page).
- [ ] Existing server-expiry test and the 15 ungated tests: untouched.

## T5 — Gates + changelog
- [ ] Temporary window: set staff lifetime `30 → 1` in
  `app/Providers/FortifyServiceProvider.php` (single value, per the established
  pattern), restart server (isolated `pkill -f "[a]rtisan serve" || true` as its own
  command + `rm -f storage/framework/views/*.php`), run
  `PW_SHORT_LIFETIME=1 npx playwright test tests/auth-full.spec.ts --grep "session expiry"`
  → 3/3.
- [ ] Revert lifetime to `30`, restart server; run `php artisan test` → **115/115**
  (phpunit gate per design §6.3 — proves the temporary edit reverted cleanly);
  full `tests/auth-full.spec.ts` (15 pass, expiry tests skipped) +
  `tests/nav-identity.spec.ts` (3/3, zero console errors).
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → 0 errors.
- [ ] Append one row to `page-changelogs/backend-automated-by-ai.md`.
