# Apply log — tooltip-viewport-clamp

**Applied 2026-11-14, commit `65f1dc1`** (lane files staged with the blade hunks only —
`venue-timetable.blade.php` carries another session's uncommitted venue-picker work at
`+138,224` / `+479,22` / `+615,251` / `+1150,74` region offsets; only the tooltip hunks went
in, plus the new test and this lane's docs).

## What changed

| File | Hunk |
|---|---|
| `resources/views/livewire/venue-timetable.blade.php` | `.available-tooltip` gains `max-width: min(340px, calc(100vw - 24px))` + `box-sizing: border-box` |
| same | `showAvailableTooltip()` now stores `tooltip._anchorCell` and calls one `placeAvailableTooltip()`; the keyboard (`B`) path no longer uses a centred fallback |
| same | new `placeAvailableTooltip()` — measure at `left: 8px`, clamp to `[8, innerWidth - width - 8]`, flip above the cell when there is no room below, `transform` cleared |
| same | `resize` listener re-places after layout (`requestAnimationFrame`) while the box is shown |
| same | `openIntentSessionModal()` hides the tooltip first (overlay z 999 vs tooltip 9999) |
| `tests/venue-book.spec.ts` | new `U2a-T18` |
| `page-changelogs/venue-timetable-ui-changelog.md` | 2026-11-14 entry |

## Correction found while applying (recorded in design §3)

The first clamp loop ended **exactly on the window edge**: a shrink-to-fit fixed box takes its
width from the space left of it, so moving it left gave it more room, it grew, and the next
clamp moved it again. Measuring once at `left: 8px` — the widest the window allows — makes the
width final, and the final clamp then leaves room for exactly that width. Re-measured after the
fix: right margin 8px, not 0.

A second attempt hid the box when a narrowed window scrolled the cell out of sight. That was
rejected on reflection: the box carries the Arrange action for a cell the user just clicked, so
it stays readable, clamped inside the window. Scroll still closes it (unchanged).

## Verification actually performed

Read-only probes against the **demo** page (`http://127.0.0.1:8000/venue-timetable-ui?venue=B101`,
staff 5425, week selected by matching `18 Nov`). No `#tooltipBookBtn` / `#intentArrangeBtn` click,
so nothing was written to any database.

| Viewport | Right-edge cell → box | Bottom-edge cell → box | `B` shortcut | Inside |
|---|---|---|---|---|
| 1440×900 | `[1092,1432] × [524,619]`, margin 8 | `[153,493] × [776,871]`, margin 29 | same as right-edge | yes |
| 1024×768 | `[676,1016] × [528,623]`, margin 8 | `[153,493] × [513,608]`, margin 160 | same | yes |
| 768×700 | `[26,366] × [362,478]`, margin 402 | same | same | yes |
| 390×844 | `[26,366] × [434,550]`, margin 24, w 340 | same | same | yes |
| 360×640 | `[16,352] × [332,448]`, margin 8, w 336 | same | same | yes |

- **Live resize while open:** 1440×900 → 900×700 put the box at `[552,892] × [597,692]`
  (right margin 8, bottom margin 8) instead of leaving it at 1432.
- **Bottom-edge flip:** in a 520px-tall window the box opened **above** the cell
  (`[153,493] × [340,435]`, cell bottom 520).
- Screenshots: `/tmp/crs-evening/clamp2-{1440,1024,768,390,360}.png`, `clamp-flip.png`;
  probe JSON `/tmp/crs-evening/clamp2.json`.

## Honest gaps

- **`U2a-T18` has not been executed.** `tests/venue-book.spec.ts` imports
  `throwUnlessTestingTarget`, so it refuses to run outside the `:8030` testing lane, and that
  lane cannot authenticate right now — `class_replacement_testing` holds **0 users**, so
  `.auth/staff-5770.json` is stale ("These credentials do not match our records"). Its
  assertions were proven by the equivalent probe above; the spec itself needs one run once the
  testing lane can log in again. Do not "fix" that database without the user's permission.
- Backend tests were **not** run for this lane (PHP-only verification, and `phpunit.xml` targets
  the protected manual database). Static gates were: PHPStan (`--memory-limit=1G`) 0 errors,
  `composer run lint:check` clean, `git diff --check` clean.
