# Investigation — Livewire JS "Component not found: undefined" + the deprecated `Components` warning

Investigated 2026-11-01 evening (chat 2). Prompt: `prompts/Investigate Livewire JavaScript.md`.
NO code was modified. Running harness probes: `probes/livewire-console-probe*.cjs` (untracked, read-only).

## Environment (verified)
- Livewire `v4.3.3` (composer.lock), Laravel 13.17, the served bundle is the UNMINIFIED dist
  (`vendor/livewire/livewire/dist/livewire.js`, 15,853 lines — byte-equal to
  `http://127.0.0.1:8000/livewire-f983032f/livewire.js?id=657d57c2`, verified with `cmp`).
  The user's stack line `livewire.js:6737` maps exactly to `findComponent()` in that file.
- Demo DBs (`:8000`, `:8001`): both PRISTINE BLANK since the user's 22:12–22:25 cleanout
  (0 replacement_requests / 0 notifications / 0 class_exceptions — re-verified live).
- Demo observations from BEFORE the cleanout apply to the old rich record; code unchanged.

## Issue 1 — `Component not found: undefined` (livewire.js:6737)

### Where the throw can come from (the ONLY call sites, all enumerated or caught)
- `findComponent(id, strict)` — the thrower at :6737 (registry lookup `components[id]`).
- Strict callers:
  1. `findComponentByEl()` :6769 — walks `el`'s ancestor chain for `__livewire`; NO ancestor =
     `componentId === undefined` → throw `Component not found: undefined` (the EXACT user string).
     Its callers at :6173/:6187 (the `$wire` Alpine magic) and :14307/:14347 (morph/fragment) are
     wrapped in `try/catch` — the throw is SWALLOWED LAWFULLY (no-op proxy / null fallback).
  2. `get parent()` :6474-6477 region (Component getter, strict, NOT caught) — only reached for
     `wire:submit="$parent…"` directives; the app uses `wire:submit="method"` only
     (settings/profile, security, delete-user) — unexercised in this app.
  3. `magic("js")` :14191 (strict, not caught) — `$wire.js` feature; no app call sites — unexercised.
  4. `supportStreaming` :14731 — needs server STREAM payloads; the app has ZERO `wire:stream`.
  5. `supportSlots` :14889 — needs `slotFragments` effects; the app has ZERO `wire:island`/slot usage.
- App sweeps: `wire:island|wire:stream|wire:navigate($parent|$js)` greps — `wire:navigate` appears on
  guest auth pages only (login/register links); no `$parent` submits; no `$js(` usage.

### Why "undefined" occurred in the user's observed session (mechanism, CONFIRMED static + LIKELY trigger)
`findComponentByEl` on an element inside a DESTROYED/morphing island subtree (the bell + panel
islands re-render every 10 s poll and are `display:none`-morphed open/closed) yields no ancestor
`__livewire` → registry lookup misses with an undefined id → strict throw. LIVE copies of intent:
the `$wire` magic turn the miss into a harmless no-op proxy; a morph-turn the miss into a harmless
null. **The throw itself is expected, defensive, and internally handled** — DevTools' "Paused on
exception" surfaces it only because pause-on-exception is enabled (the throw is a STRING, not an
Error — DevTools pauses even when the app catches it).

### Runtime receipts (2026-11-01, blank canvas)
- 5 staff pages × poll cycle + panel open/close/toggles/dropdown + the exact deep-link
  `?notif-hl=s40&notif-w=7`: **0 strict misses**, **0 uncaught page errors** — measured by a
  route-patched served bundle that logs EVERY strict miss before the throw
  (`probes/livewire-console-probe3.cjs`).
- Student login (25DFT0001 → `/login/student`): /student-my-timetable-ui, /dashboard,
  /replacement-home-ui — 0 errors/warnings; one expected 403 on a staff-gated URL (role gate).
- Server log since the cleanout: no ERROR entries at all.
- NOT yet replayable: the data-rich chains (approve/reject/cancel request chains + notif-row
  hops) run pre-cleanout — the canvas is blank, so those flows cannot fire today. The user's one
  observed occurrence predates the cleanout.

### Functional-impact receipt
The same build + same flows passed ALL receipted suites tonight (bridge 14/14; island 9/9 incl.
open-panel + 10 s poll morphs; full phpunit 280/280) — no functional failure was ever observed
in connection with this throw.

## Issue 2 — "The Components object is deprecated. It will soon be removed."

### Source evidence (CONFIRMED: not the application, not Livewire)
- ZERO matches for `\bComponents\b` in `public/js/*.js` (ui-common, mock-data, session-countdown,
  lockout-countdown, auto-logout, logout-modal) and `resources/views/`.
- The served/checked Livewire bundle contains NO deprecation strings (`grep -c "is deprecated"` = 0).
- Clean-Chromium runs (no extensions) on the user's EXACT URL + 6 pages + interactions + 12 s poll
  cycles: **0 console messages of any type**, including this warning — twice (both probe runs).
- Clean-Firefox (student + staff pages) could not be driven headlessly by this machine's
  Playwright (no FF build); system FF launch not scriptable via Playwright — FF run unverified.
- The warning string is a browser-engine artifact keyed to `window.Components` access by NON-page
  code (typically browser extensions' content scripts / a legacy `with` or `typeof Components`
  guard in some extension). It is emitted by the browser, not by the page's scripts.

### Conclusion
The warning is ENVIRONMENT-SIDE (the user's own browser — most plausibly an extension). App code
and Livewire are not its source. Verification step for the user: open DevTools → the console line
→ its right-hand source link shows `extension://…`/`content-script`; disable extensions one by one.

## Relationship between the two issues
INDEPENDENT. Issue 1 is a Livewire-internal defensive lookup; issue 2 is a browser-environment
object touch. They share neither code path (grep + call-site enumeration) nor page timing.

## Proposed fixes (smallest, none today)
1. Issue 1: **No app fix required.** The app invokes no strict-path features; the throw is caught
   and lawfully handled. The ONLY user-visible effect is DevTools pausing. If it recurs and
   annoys, the smallest lever is the DevTools setting (Pause on exceptions → off / disable
   "pause on caught"), and the long-term lever is a routine Livewire upgrade AFTER the demo
   (constraint: no upgrade as the first response — unchanged).
2. Issue 2: No app fix. Run the user-side extension sweep; the warning disappears with the
   offending extension (or is provably a browser page-access artifact).

## Regression-test plan if either were ever "fixed" in code (not applicable today)
- Bridge suite `NotificationsBridgeTest` (14) + island spec (:8000) 9/9 + one poll-morph receipt.
- A manual staff pass: bell open/close × 2 poll cycles, the two toggles + type dropdown both ways,
  one row hop deep-link, approve/reject on a seeded testing-DB pair, one cancel-undo on :8030.
- Student pass: /student-my-timetable-ui + /dashboard bell rendering (already receipted probe).

## Status
| Issue | Status |
|---|---|
| `Component not found: undefined` | **Partially confirmed** — throw-site inventory + catch surfaces + zero-repro receipts are CONFIRMED; the exact triggering interaction belongs to the pre-cleanout data-rich session and awaits the canvas refill for a live replay. |
| Deprecated `Components` warning | **Confirmed** — not from the app or Livewire; environment-side (extension). Clean-browser evidence recorded; user-side sweep pending their browser. |
