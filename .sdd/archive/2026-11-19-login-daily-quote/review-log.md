# Review log — login-daily-quote

Compact lane (SDD cost cut, 2026-11-17): one artifact, one review round.

## Artifact Round 1 — 2026-11-19

Reviewer: `sdd-reviewer` subagent, **no model pinned** → inherited the session model
`opencode/deepseek-v4.1-flash-free` (the same model as the orchestrator). The user was told
this is a same-model pass and accepted it as the single gate for this lane. Recorded here so
the gate is not mistaken for an independent review.

Baseline: the decisions frozen in the 2026-11-19 grilling (delivered in the reviewer prompt);
no `explore-brief.md` existed. Judge scope: the single `spec-and-tasks.md` as
proposal + design + specs + tasks.

### 🔴 Fixed

- **T6 DB safety had no mechanism.** `phpunit.xml:29` pins
  `DB_DATABASE=class_replacement_testing` (the user's protected manual data since
  2026-11-14), and the neighbouring `tests/Feature/Auth/AuthenticationTest.php:18` uses
  `RefreshDatabase` — the exact shape a copy would inherit and wipe. T6 now forbids
  `RefreshDatabase`/`DatabaseMigrations`/factories (the page reads no DB), names the run
  command against an agreed scratch DB, and requires printing `current_database()` first.
- **T7's "read-only, no logins" was factually wrong.** `playwright.config.ts` gives the
  `chromium` project `dependencies: ['setup']`, and `tests/auth.setup.ts` performs four real
  logins before any spec runs. T7 now mandates
  `npx playwright test <spec> --project=chromium --no-deps` with `CRS_BASE=:8000` and an
  explicit "never `:8030`".
- **PHP quality gates were missing.** Added T8: `composer run lint:check` plus
  `vendor/bin/phpstan analyse --memory-limit=1G` (never `composer run types:check` — no
  memory limit, dies at 128M). `phpstan.neon` is level 7 over `config/`, so the helper's
  return array is now explicitly typed with an `is_array()` guard.

### 🟡 Addressed

- **The 700px gate was unmeasured.** The card already spends ~520–600px before the quote and
  the footer is in flow. AC5 now asserts the Log in button's bottom edge is inside the
  viewport at exactly 769×700; decision 3 records the pre-agreed contingency to raise the
  gate to the measured value (740/760) rather than ship a failing button.
- **AC6's second clause could not fail.** "Reachable without scrolling past the quote" is
  vacuous while the block is `display: none`; replaced with a measurable assertion.
- **The colour grep was unscoped and cited a sanction that does not exist here.** The login
  template already has legacy hex/rgba and no FOUC guard (that lives in
  `layouts/ui-template.blade.php`). Verification now greps only added lines via
  `git diff -U0`.
- **AC7's "stays legible" was subjective.** Replaced with a ≥4.5:1 measurement in both
  themes, plus the pre-agreed fallback `--color-on-surface` at the same size (light mode is
  the risk, ≈3.5:1 estimated over the brightened photo).
- **Two implicit contracts.** Added decisions 12 and 13: the layout resolves and passes
  `$quote` (the partial never reads the clock/config), and the config stores a bare filename
  while the partial emits a root-relative `/images/quotes/<file>`.
- **AC4 could pass while shipping 2.4 MB.** The `.svg` sources sit in the same directory, so
  a naive existence check would accept them. AC4 now requires the `.png` suffix and a < 5 KB
  size.
- **Duplicated `Asia/Kuala_Lumpur` literal.** The helper now uses the existing
  `App\Support\LocalTime` (`TZ`, `today()`) instead of a second literal.
- **Clock leakage in tests.** `setTestNow()` has no automatic reset; T6 now requires the
  `tearDown(): Carbon::setTestNow()` shape from `LocalCalendarRulesTest`.
- **Over-proof and missing kid gloves in T7.** Trimmed to the two edge heights + a normal
  desktop + a phone; phone capture uses `hasTouch`/`isMobile`; theme set via
  `addInitScript` + `localStorage`, not by clicking the toggle.
- **Two CSS house-style points.** The ink flip uses the template's own `.light` idiom, and
  the base `display: none` rule must precede the media-query reveal in the same `<style>`.
  The `*` reset already zeroes figure/blockquote margins, so `.login-quote` owns its spacing
  (which is also what makes AC9 true on an empty day).

### 🔴 Outstanding

- None. The three blockers and ten non-blocking findings are folded into
  `spec-and-tasks.md`; the reviewer's own verdict was that the lane can go straight to apply
  once they were.

**Verdict:** no blocking issue left by a same-model pass. Not an independent review — the
execution checks (T6 boundary test, AC5 measured fold, AC7 contrast) carry the real weight.

## Apply + verify — 2026-11-19

All nine tasks applied. Nothing was committed (no push instruction given).

**Executed**
- T1 — 7 ink PNGs generated; `-channel A -level 1%,100%` was added to strip the
  background rectangle each source SVG bakes in (corner alpha 0 for six of seven).
- T2–T5 — config, helper, partial, wiring and scoped CSS.
- T6 — 19 tests / 180 assertions pass on the scratch DB.
- T7 — 8 browser tests pass; `--no-deps` used so the four real setup logins never ran.
- T8 — pint and PHPStan level 7 clean; zero hex/rgb in added template lines.
- T9 — changelog written.

**Deviations, all recorded**

1. **PHPStan caught dead code in T3.** My `@var` annotation already asserted `$rows` was an
   array, so the `is_array()` guard was unreachable. Removed the annotation so the guard
   does the narrowing (the fix the reviewer's level-7 warning predicted, in the opposite
   direction from what was expected).
2. **Quote colour changed to `--color-on-surface`.** Light mode measured **3.17:1** with
   `--color-on-surface-variant` — below the 4.5:1 floor. This is the fallback decision 5
   pre-agreed. Re-measured: dark 10.19:1, light 8.39:1. No new colour was invented.
3. **The author line took the same token at 0.9 opacity.** At 11px it also needs 4.5:1, and
   hierarchy is carried by size and weight instead.
4. **The 700px gate held; the contingency was not needed.** Measured at exactly 769×700 the
   Log in button's bottom edge is at **650px of 700** — 50px of headroom.
5. **The browser spec measures with animations disabled.** The card's `fadeSlideUp`
   transform distorts `boundingBox()`, and the looping `bgZoom` backdrop means
   `getAnimations().finished` never resolves. With `animation: none` the `both` fill paints
   the final state, which is what the assertions are about.

**Not done:** archive (pending), commit (pending instruction), push (never without an
explicit request).
