# Design — wire-session-countdown

**Date:** 2026-10-08 · **Frozen baseline:** proposal.md (batch 1, PASS), explore-brief.md

## 1. Component overview

No new components. Three touch points:

| File | Change |
|---|---|
| `resources/views/partials/ui-session-countdown.blade.php` | keep existing markup (26 lines, ids/attrs unchanged); append one self-contained `<script>` block |
| `resources/views/layouts/ui-template.blade.php` | add `@include('partials.ui-session-countdown')` after the commented-out logout-modal include block, inside the `hideNav` guard |
| `public/css/theme.css` | append one `/* ── Session countdown (partial: ui-session-countdown) ── */` block |

## 2. JS contract (inline script in the partial)

IIFE, no globals leaked (except nothing — fully enclosed). Runs once per page load.

```
root      = document.querySelector('.session-countdown')
guard     = exit silently if !root || !root.dataset.lifetime
lifetime  = parseInt(root.dataset.lifetime)          // minutes (staff 30, student 43200)
lastAct   = parseInt(root.dataset.lastActivity)      // unix seconds, server-rendered
duration  = lifetime * 60                            // seconds
baseline  = lastAct                                  // client-side "server's last activity"
```

**Decision — Livewire commit reset: YES (cheap + correct).** Because the server
refreshes `_auth_last_activity` on every request (incl. Livewire AJAX), the client
baseline should track it. If `window.Livewire` exists, register
`Livewire.hook('commit', () => { baseline = Math.floor(Date.now()/1000); })`. Also
listen for `livewire:navigated` (full Livewire page navigations re-render the layout
anyway — the hook is belt-and-braces for AJAX-only commits). Guarded in
try/catch + feature check so non-Livewire pages are unaffected. This resolves the
proposal's recorded divergence limitation for v1 at ~4 lines.

**Tick (1 s setInterval):**

| remaining (s) | UI |
|---|---|
| `> 300` | bar hidden, modal hidden |
| `≤ 300` or `duration ≤ 300` | bar shown (`#countdown-min` = `Math.ceil(remaining/60)` min) |
| `≤ 60` | modal `#sessionModal` shown; `#modal-countdown` = remaining (s) |
| `≤ 0` | fire logout once (below) |

**Reveal mechanism — inline style only.** The stub ships inline
`style="display: none;"` on BOTH the `.session-countdown` root and `#sessionModal`;
inline styles beat class selectors, so JS toggles `el.style.display` directly
(bar root: `'none'` ↔ `'flex'`; modal: `'none'` ↔ `'flex'`). No `.is-open` class —
that would be a silent no-op against the inline styles.

`remaining = duration − (now − baseline)`; bar-vs-modal thresholds evaluated every
tick so the bar can also appear on short/test lifetimes immediately.

**Logout (single path):**

```
function fireLogout() {
  if (fired) return; fired = true;
  clearInterval(timer);
  document.getElementById('countdown-logout-form').submit();  // POST route('logout')
}
```

`fired` also disables BOTH forms' buttons (`disabled = true`) — the modal's id-less
manual Logout form gets a submit-capture listener that sets `fired` first, killing
the interval so a final-second race cannot double-POST (stale CSRF → 419).
After `fireLogout()`, no further client action — `LogoutResponse` redirects to the
correct portal (staff → `/login/staff`, student → `/login/student`).

**Extend:** both extend affordances are plain `onclick="location.reload()"` in the
stub — kept as-is. Reload → server middleware refreshes `_auth_last_activity` →
fresh `data-last-activity` on the re-rendered page → remaining restarts at full
lifetime. No JS changes needed for extend.

## 3. CSS contract (theme.css append, tokens only)

| Class | Key rules |
|---|---|
| `.session-countdown` | fixed, bottom bar (e.g. `position:fixed; bottom:0; left:0; right:0;`), `display:none` default (JS toggles inline `style.display` — see §2), `background: var(--color-surface-variant)`, `color: var(--color-on-surface-variant)`, `border-top: 1px solid var(--color-outline)`, small font, flex layout with `.btn-extend` right-aligned, `z-index` below the modal |
| `.btn-extend` | compact button: `background: var(--color-secondary)`, `color: var(--color-on-secondary)` (both tokens verified to exist), `border-radius`, hover slightly darkened via `filter: brightness(.95)` (no hardcoded hex) |
| `.session-modal-overlay` | `position:fixed; inset:0; display:none;` (JS toggles inline `style.display:flex`), `align-items:center; justify-content:center;`, `background: color-mix(in srgb, var(--color-bg) 70%, transparent)` (token-derived scrim, dark-mode-safe) |
| `.session-modal` | `background: var(--color-surface)`, `color: var(--color-on-surface)`, `border: 1px solid var(--color-outline)`, `border-radius`, padding, max-width ~24rem, column flex; `h3` in `var(--color-error)` (expiry = error semantic per §10.0 legend); `.session-modal-actions` row with gap |
| `.session-modal-actions .btn-primary` | `background: var(--color-primary)`, `color: var(--color-on-primary)` |
| `.session-modal-actions .btn-secondary` | `background: var(--color-error-container)`, `color: var(--color-on-error-container)` |

All tokens above verified present in `theme.css`. No hex/rgb literals. Dark mode:
automatic via token switching; the scrim uses `--color-bg` mix so it works in both
themes.

## 4. Include placement (ui-template)

```blade
{{-- LOGOUT MODAL DISABLED ... --}}
@include('partials.ui-session-countdown')   {{-- NEW: after the commented block --}}
```

Inside the `@if(!isset($hideNav) || !$hideNav)` guard — `hideNav` pages intentionally
excluded (proposal §scope 1). The partial is `@auth`-gated internally; guests render
nothing.

## 5. Test design (tests/auth-full.spec.ts, gated PW_SHORT_LIFETIME=1)

Re-added in the session-expiry describe block:

1. **`client countdown auto-logs-out after role_lifetime`** — login 5425 → stay on
   the page → `page.waitForURL('**/login/staff', { timeout: 85_000 })` (auto-submit
   at 0 ≈ 60 s) → then `/my-timetable-ui` bounces to login (session dead).
2. **`extend keeps the session alive`** — login 5425 → wait for `.session-modal` (or
   bar) visible (≤ 65 s at 1-min lifetime) → click `.btn-extend` → page reloads →
   `page.url()` is still under `/my-timetable-ui` (authenticated) and NOT a login
   page.

The existing **server-expiry** test (page closed during idle) is untouched and must
still pass — the countdown JS cannot run in a closed page, so server and client
paths remain independently verified.

## 6. Verification plan

1. `PW_SHORT_LIFETIME=1` + temporary 1-min staff lifetime → run the 3 expiry tests
   (server, client auto-logout, extend) → all green.
2. Revert lifetime to 30 → full `tests/auth-full.spec.ts` (15 pass, expiry skipped) +
   `tests/nav-identity.spec.ts` 3/3 (zero console errors on `ui-template` pages).
3. phpunit 115/115, phpstan-1G 0.
4. Manual visual check: bar/modal on a real page at short lifetime; dark-mode toggle.
5. Blade changes: clear stale compiled views (`rm -f storage/framework/views/*.php`)
   and restart the dev server before verifying — isolated `pkill -f "[a]rtisan serve"
   || true` as its own command, then `php artisan serve --port=8000`.

## 7. Out of scope (unchanged from proposal)

Server middleware, disabled `ui-logout-modal`/`logout-modal.js`, Livewire-aware
extend (reload is sufficient), student lifetime.
