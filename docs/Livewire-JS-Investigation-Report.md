# Report — Livewire JS Investigation (continued session, 2026-11-01 evening)

Investigation task: `prompts/Investigate Livewire JavaScript.md`.
Continued from a PI chat session that stopped on connection errors — that session left no
artifacts on disk, only the prompt, so the full checklist was re-run from scratch.
Full evidence record: `docs/livewire-js-investigation.md` (call-site line numbers, probe scripts,
receipt outputs). No code was modified anywhere.

## A. Summary (both issues in simple English)

1. `Component not found: undefined` — Livewire's own internal "look up a component" helper was
   asked about a component that has already been removed from the page (its ID became
   `undefined`). Livewire throws this on purpose as a defensive check, and the app's code paths
   catch it and carry on with a harmless fallback. The Debugger only *pauses* on it because
   pause-on-exception was enabled — it is not an app crash.
2. "The Components object is deprecated. It will soon be removed." — a browser-engine message
   produced when some code touches the legacy `window.Components` global. That callee is **not**
   the app's JavaScript, **not** Livewire's bundle, and **not** any project file; clean browsers
   with no extensions never produce it on any page of this app.

## B. Findings

| Item | Root cause | Affected scope | Status |
|---|---|---|---|
| `Component not found: undefined` (livewire.js:6737) | `findComponentByEl()` resolves an element inside a destroyed/morphing island subtree (the bell + panel islands re-render every 10 s poll and are display-morphed open/closed); no live `__livewire` ancestor → `id === undefined` → strict registry throw — **lawfully caught** in the reachable paths | Livewire-internal only; staff pages with the bell/panel islands; zero functional impact | Partially confirmed |
| Deprecated `Components` object | `window.Components` touched by browser-side code (a browser extension's content script) — the identifier exists nowhere in the app code or its dependencies | Every page the user opens in their own browser; app-independent | Confirmed (not app-caused) |

## C. Evidence (highlights)

- Served `livewire.js` is byte-equal to the unminified dist (`vendor/livewire/livewire/dist/livewire.js`,
  15,853 lines, verified with `cmp`) — the user's stack line 6737 = `findComponent()`.
  Livewire **v4.3.3** (`composer.lock`).
- **Exhaustive throw-site inventory**: 8 strict call sites enumerated in the dist. The two
  `undefined` sources are `findComponentByEl` (ancestor walk) and the slots/stream paths. Every
  site reachable from this app's markup is inside `try/catch` no-op fallbacks; the uncaught ones
  (`Component.parent` getter, `$wire.js` magic, streaming, slots) are **not exercised**:
  zero `wire:submit="$parent…"`, zero `$js(`, zero `wire:stream`, zero `wire:island`;
  `wire:navigate` appears only on guest auth links.
- **Instrumented runtime proof**: a route-patched served bundle logging *every* strict miss before
  the throw scanned 5 staff pages × poll morphs, panel open/close, both toggles + the type
  dropdown, and the exact deep link `?notif-hl=s40&notif-w=7` → **0 misses, 0 uncaught page
  errors** (probe: `probes/livewire-console-probe3.cjs`).
- **Clean-Chromium console** (no extensions): **0 console messages of any type** on every page,
  twice (probe runs 1 + 2) — the deprecation warning never fires there.
- **Student-side scope check**: logged in as 25DFT0001 via `/login/student`; student pages render
  clean (0 warnings); one expected 403 on the staff-gated URL (the role gate working). `Components`
  warning absent there too.
- **Server log**: no ERROR entries since the 22:25 demo cleanout — the throw is client-internal.
- **Functional impact**: the same build + flows passed all receipted suites tonight — bridge
  suite 14/14, island spec 9/9 (incl. open-panel survival of 10 s poll morphs), full PHPUnit
  280/280. No functional failure has ever been observed alongside this throw.

## D. Relationship between the issues

**Independent.** Issue 1 is a Livewire-internal defensive lookup; issue 2 is a browser-environment
object touch by non-page code. They share neither code path (grep + call-site enumeration) nor
timing.

## E. Proposed fixes (nothing to change today)

1. **Issue 1**: no app fix required — the throw is Livewire's own defensive design and the app
   lands it inside lawful catch surfaces. If it pauses the user again, the smallest lever is the
   DevTools setting **Pause on exceptions → off** (or unchecking "pause on caught exceptions").
   A Livewire upgrade remains a later, deliberate step only (per the task constraint: no upgrade
   as a first response).
2. **Issue 2**: no app fix. User-side verification: in DevTools the console line's right-hand
   source link shows the true caller (`extension://…`); disable extensions one by one and the
   warning retires with the offending one.

## F. Regression testing (if either ever became an app-side change)

- Bridge suite `tests/Feature/NotificationsBridgeTest.php` (14 tests) + island spec
  `tests/notifications-island.spec.ts` (9) + one full poll-morph receipt.
- Manual staff pass: bell open/close across 2 poll cycles, both toggles + the type dropdown each
  way, one row-hop deep link, approve/reject on a seeded testing-DB pair, one cancel-undo on :8030.
- Student pass: `/student-my-timetable-ui` + `/dashboard` bell rendering (already receipted by the
  probe).

## G. Final status

| Issue | Grading |
|---|---|
| `Component not found: undefined` | **Partially confirmed** — the throw-site inventory, the catch surfaces, and the zero-repro runtime receipts are CONFIRMED; the one observed occurrence belongs to the pre-cleanout rich-data session (row hops + decision chains) and can only be replayed live once the blank canvas refills with real submits. |
| Deprecated `Components` object | **Confirmed** — environment-side (a browser extension); not the app, not Livewire. Clean-browser evidence recorded; the user-side extension sweep is pending in their own browser. |
