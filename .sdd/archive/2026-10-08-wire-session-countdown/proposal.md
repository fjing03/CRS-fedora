# Proposal — wire-session-countdown

**Date:** 2026-10-08 · **Status:** draft (batch 1) · **Size:** small (1 partial + 1 layout line + 1 CSS block + tests)

## Why

The client session-countdown feature exists as an unfinished stub:
`resources/views/partials/ui-session-countdown.blade.php` (26 lines of markup, no
JavaScript) is included by no layout and has no CSS. The 2026-10-08 auth browser-test
sweep proved the server-side session expiry now works (fix `8ea332c`) but the client
half — warning the user before expiry and auto-logging-out at expiry — is missing.
The user decision (2026-10-08): build it now as a small SDD change.

Without it, a user idle past `role_lifetime` (staff: 30 min) is abruptly redirected
to login with "Session expired" and loses any unsaved form state. With it, the user
gets a visible countdown, a 60-second warning modal, an explicit extend action, and a
clean auto-logout — matching the server behavior that already exists.

## What's in scope

1. **Activate the stub** — `@include('partials.ui-session-countdown')` in
   `layouts/ui-template.blade.php`, placed directly after the (commented-out)
   `ui-logout-modal` include block, still inside the existing
   `@if(!isset($hideNav) || !$hideNav)` guard. Pages passing `hideNav => true`
   (today only the replacement-arrangement design template) intentionally get no
   countdown bar and no client auto-logout.
2. **Drive it with JS** — a self-contained inline `<script>` inside the partial:
   compute remaining seconds from server-rendered `data-lifetime` /
   `data-last-activity`, tick 1 s, update `#countdown-min` and `#modal-countdown`,
   reveal the bar ≤ 300 s remaining (or immediately when lifetime ≤ 300 s), show the
   modal ≤ 60 s, auto-submit `#countdown-logout-form` at 0. Extend buttons reload the
   page (server refreshes `_auth_last_activity` → remaining restarts).
3. **Style it** — a new appended block in `public/css/theme.css` for
   `.session-countdown`, `.session-modal-overlay`, `.session-modal`, `.btn-extend`
   using only existing custom-property tokens (AGENTS.md §10.0: same name + same
   color for the same meaning; dark-mode-safe).
4. **Test it** — extend `tests/auth-full.spec.ts` with the client-countdown tests
   (gated behind `PW_SHORT_LIFETIME=1` + the temporary 1-min staff lifetime, same
   pattern as the server-expiry test): auto-logout at 0 lands on the correct login
   page with the session dead; extend keeps the user authenticated.

## What's explicitly out of scope

- Server middleware changes (just fixed + tested; contract unchanged).
- The disabled `ui-logout-modal` / `logout-modal.js` (separate deferred debt).
- Replacing `location.reload()` extend with Livewire-aware refresh.
- Student lifetime changes (43200 min stays).

## Success criteria

- On an authenticated `ui-template` page with a short lifetime, the countdown bar
  appears, the modal appears at ≤ 60 s, auto-logout fires at 0 → correct portal login
  page, and the session is provably dead afterwards.
- Extend keeps the user logged in (reload refreshes server-side last-activity).
- Zero console errors on pages using `ui-template` (nav-identity spec must stay 3/3).
- phpunit 115/115, phpstan-1G 0 unchanged; new Playwright tests green in the
  temporary-lifetime window.

## Risks / mitigations

- **Interference with Livewire requests** — the countdown only reads server-rendered
  attributes and submits a plain form; it does not touch Livewire lifecycle.
- **Client/server divergence on Livewire-interactive pages (accepted for v1)** —
  Livewire AJAX passes through the web group, so the server-side
  `_auth_last_activity` refreshes on `wire:` activity, while the client countdown is
  computed once at page render and never resets; a user active via Livewire for close
  to the full lifetime could be auto-logged-out of a still-valid session. Whether v1
  resets the countdown baseline on Livewire commits is a design decision (see
  design.md); the warning bar/modal still precedes any forced logout either way.
- **Double logout submission** — interval cleared + submitted flag before submit.
- **Test flakiness at 1-minute lifetime** — the modal appears immediately; assertions
  use generous waits (lifetime + 30 s slack), matching the existing expiry test's style.
