# Explore brief — wire-session-countdown

Generated 2026-10-08, post auth-browser-test sweep. Source of the task: the sweep
proved the client countdown is an unfinished stub (user decision: build it now as a
small SDD change).

## Problem

`resources/views/partials/ui-session-countdown.blade.php` exists (26 lines) but is:
1. **Included nowhere** — `grep -rn "ui-session-countdown" resources/views/` → 0 hits.
2. **JS-less** — markup references `#countdown-min`, `#modal-countdown`, hidden
   `#countdown-logout-form`, but no script drives them.
3. **Unstyled** — `.session-countdown`, `.session-modal-overlay`, `.session-modal`,
   `.btn-extend` have zero rules in `public/css/theme.css`.

The server side it depends on was fixed earlier today (commit `8ea332c`):
`EnsureSessionLifetime` now runs after `StartSession` in the web group, updates
`session('_auth_last_activity')` per request, and expires the session after
`session('role_lifetime')` minutes (staff 30, student 43200), redirecting to the
correct portal with "Session expired. Please log in again."

## Final solution (mapping table)

| Concern | Decision |
|---|---|
| Include point | `layouts/ui-template.blade.php`, directly after `@include('partials.ui-logout-modal')` (line 37) |
| JS placement | Self-contained inline `<script>` inside the partial (house precedent: `ui-nav-bar` partial ships its own inline script; single-use feature → no ui-common.js promotion) |
| CSS placement | New block appended to `public/css/theme.css`, custom-property tokens only (AGENTS.md §10.0) |
| Remaining-time formula | `remaining = data-lifetime*60 − (Date.now()/1000 − data-last-activity)` |
| Tick | 1 s `setInterval`; updates `#countdown-min` (integer minutes, ceil) and `#modal-countdown` (seconds) |
| Countdown bar visibility | `remaining ≤ 300 s` OR `data-lifetime*60 ≤ 300 s` (short/test lifetimes — the bar is immediately meaningful) |
| Modal | shown when `remaining ≤ 60 s`; `#modal-countdown` counts down per second |
| Auto-logout at 0 | `document.getElementById('countdown-logout-form').submit()` → POST `route('logout')` → `LogoutResponse` redirects to the correct portal (cookie `login_type`) |
| Extend ("Still here?" / "Stay logged in") | `location.reload()` — the fixed middleware refreshes `_auth_last_activity` server-side on reload, so remaining restarts at full lifetime |
| Guards | abort silently if the partial's root element or `data-lifetime` is missing; clear the interval once submitted (double-submit guard) |
| Guests | partial content is `@auth`-gated already — no change needed |

## Rejected approaches and why

- **Put the JS in `ui-common.js`** — single-use feature; partial self-containment
  matches the `ui-nav-bar` precedent; promotion rule (3+ duplications) not met.
- **Server-push / polling for expiry** — the middleware's per-request
  `_auth_last_activity` refresh already defines expiry; client-side computation from
  server-rendered `data-*` attributes is exact enough (±1 s) and dependency-free.
- **Change the server middleware timing** — out of scope; it was just fixed and
  tested (auth-full.spec.ts server-expiry test closes the page so client JS cannot
  interfere — still valid after this change).

## Interactions with existing features

- `ui-logout-modal` + `logout-modal.js` are DISABLED (commented out in ui-template;
  nav trigger disabled). Countdown's modal is independent (different ids/classes);
  no conflict.
- `auth-full.spec.ts` server-expiry test closes the page during idle → unaffected.
- Dark mode: tokens switch automatically; `.session-modal-overlay` must use a token
  (e.g. `--color-inverse-surface`/`--color-bg`-derived) not hardcoded hex.

## Test plan (will be added to tests/auth-full.spec.ts, gated PW_SHORT_LIFETIME=1)

1. **Auto-logout at 0**: login staff → stay on page → within lifetime+slack the page
   auto-POSTs logout → lands on `/login/staff`; `/my-timetable-ui` then bounces to login.
2. **Extend works**: with modal visible → click `.btn-extend` → page reloads → still
   authenticated (NOT on a login page) → countdown restarts.
3. Existing server-expiry test must still pass (page closed during idle).

## Known open questions

- None blocking. Bar-visibility threshold (300 s) is a design decision by the agent;
  the 60 s modal threshold is fixed by the existing stub markup.
