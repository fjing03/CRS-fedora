# Login daily motivational quote

Status: **applied and verified 2026-11-19**. One compact artifact (SDD cost cut,
2026-11-17).

Review round 1 was a `sdd-reviewer` subagent **without a pinned model**, so it inherited this
session's model (`opencode/deepseek-v4.1-flash-free`). The user accepted the same-model round
as the single gate for this lane; treat its clean pass as "no blocking issues found", not as
independent proof. Execution checks (TDD + the measured fold test) carry the real weight.

## Why

Both live login pages (`/login/staff`, `/login/student`) open on a bare form. One short
athlete quote per weekday, attributed with a name, gives the page a quiet daily touch without
competing with the title or the Log in button. "不要太耀眼, don't too big" is the design law.

## Scope

- **In:** `resources/views/layouts/login-template.blade.php` (one include + scoped CSS),
  new `resources/views/partials/login-quote.blade.php`, new `config/login-quotes.php`,
  new `app/Support/LoginQuote.php`, seven 64×64 PNG avatars under `public/images/quotes/`
  (source SVGs already copied there on 2026-11-19), one feature test, one Playwright spec,
  one page changelog.
- **Out:** the frozen `login-UI-design-template.blade.php`, the Fortify
  `pages/auth/login.blade.php`, the existing backgrounds/card design, mobile rendering,
  different quotes per role, DB/schema/migration, any new dependency or JS file.

## Decisions (locked)

1. **Surface.** One insertion serves both logins because both `@extends('layouts.login-template')`.
2. **Placement.** Inside `.login-card`, after `.page-title`, before the `<form>`.
3. **Visibility.** Visible only at `min-width: 769px` **and** `min-height: 700px`. Phones and
   short laptop windows hide it so the Log in button is never pushed below the fold.
   **Contingency (measured, not guessed):** if the at-769×700 check in AC5 shows the Log in
   button's bottom edge past the viewport, raise the height gate to the measured value
   (740 or 760) and record it in the changelog. Never ship the gate as-is with a failing
   button.
4. **Alignment.** Centred on the card axis, matching the logo and title.
5. **Weight.** Quote ~12.5px `--color-on-surface-variant`; author ~11px/600; 32px avatar at
   ~60% opacity with a 1px `--color-outline` hairline ring. No panel, shadow, quotation-mark
   glyph, emoji, icon, or new animation. It must read as a tagline, not body copy.
   **Measured legibility:** at least **4.5:1** for the quote text at its rendered position in
   both themes. Light mode is the risk (≈3.5:1 estimate over the brightened photo). If it
   misses, use `--color-on-surface` at the same 12.5px (≈10:1) — pre-agreed, so verify does
   not reopen the design.
6. **Day rule.** The weekday is resolved through the existing **`App\Support\LocalTime`**
   (`TZ = 'Asia/Kuala_Lumpur'`, `today()`), never a second `'Asia/Kuala_Lumpur'` literal and
   never `config('app.timezone')`. The boundary is `00:00 MYT`; the application timezone
   stays `UTC`.
7. **Automatic only.** No query parameter, no env override, no hidden UI. The page shows
   whatever day it is.
8. **Data.** `config/login-quotes.php` maps ISO weekday (1 = Monday … 7 = Sunday) to
   `quote`, `author`, `avatar`.
9. **Attribution.** Avatar **plus** a visible text name. A silhouette alone does not satisfy
   "must mention the quoter's name"; it is an accent, never the label.
10. **Asset rendering.** Seven supplied SVGs are VTracer traces (2.4 MB total, thousands of
    paths, mixed silhouette/line-art/portrait styles). Ship **64×64 single-ink transparent
    PNGs** (≈1.6–2.3 KB each) so the seven read as one family at 32px. Keep the SVGs as
    source art. One black-ink file per athlete; the dark default inverts it with
    `filter: invert(1)`, so no second file set.
11. **Voice.** Quote text is the user's supplied copy, reproduced exactly, including
    `isn't`/`It's` apostrophes. Attributions are unverified (the web-search tool was down);
    they are shown as supplied.
12. **Call contract.** The **layout** resolves the row and passes it down:
    `@include('partials.login-quote', ['quote' => \App\Support\LoginQuote::today()])`.
    The partial never queries the clock or the config itself.
13. **Asset path contract.** The config stores a bare filename; the partial emits the
    **root-relative** `/images/quotes/<file>` (the same shape as `/images/logo_banner.png`),
    not `asset()`.

## Quote table (config content)

| ISO | Day | Quote | Author | Avatar |
|---|---|---|---|---|
| 1 | Mon | You have to fight to reach your dream. You have to sacrifice and work hard for it. | Lionel Messi | `messi.png` |
| 2 | Tue | The dream is not the destination. It's the journey. | Kobe Bryant | `kobe.png` |
| 3 | Wed | I've failed over and over and over again in my life. And that is why I succeed. | Michael Jordan | `jordan.png` |
| 4 | Thu | Success is not an accident, success is actually a choice. | Stephen Curry | `curry.png` |
| 5 | Fri | Success isn't always about greatness. It's about consistency. | The Rock | `the-rock.png` |
| 6 | Sat | Tired is only in the mind. You tell yourself you're tired, you're gonna be tired. | LeBron James | `lebron.png` |
| 7 | Sun | Your love makes me strong. Your hate makes me unstoppable. | Cristiano Ronaldo | `cr7.png` |

## Acceptance criteria

1. `GET /login/staff` and `GET /login/student` each render one `<figure class="login-quote">`
   between the page title and the form on a desktop viewport.
2. With the clock frozen at each ISO weekday, the rendered quote, author and avatar filename
   match the table above — all seven days, both routes.
3. Frozen at Sunday `23:59` MYT the page shows Ronaldo; at Monday `00:00` MYT it shows Messi.
   The UTC clock is never consulted for the day.
4. Each avatar referenced by the config exists at `public/images/quotes/<file>`, **ends in
   `.png`** (the `.svg` sources live in that same directory and would otherwise satisfy a
   naive existence check), and is under 5 KB.
5. At 1280×800, 769×700 and 1440×900 the quote block is visible, the avatar image loads, the
   block does not overlap the title or the form, the page has no horizontal overflow, and at
   **exactly 769×700 the Log in button's bottom edge is inside the viewport**.
6. At 769×699, 768×800 and 390×844 the block is not rendered (`display: none`), and the Log
   in button is reachable at 768×800 without scrolling.
7. Both themes: the new CSS uses tokens only (no added hex/rgb/rgba — see the scoped
   verification), the avatar ink flips with the theme, and the quote text measures at least
   4.5:1 at its rendered position in both themes.
8. No new JS file, no DB access, no migration, no new Composer/npm dependency.
9. A weekday with no config row renders nothing (no empty `<figure>`, no stray margin) —
   the `*` reset in `theme.css` zeroes UA figure/blockquote margins, so spacing must be
   explicit on `.login-quote` itself.

## Contracts and house style (so two tasks cannot diverge)

- **Who computes:** layout → `LoginQuote::today()` → partial as `$quote` (decision 12).
- **Helper:** `App\Support\LoginQuote::forDay(int $isoDay): ?array` and `today(): ?array`,
  resolving the weekday with `LocalTime::today()->dayOfWeekIso`. The return array must be
  typed for PHPStan level 7 (`/** @var array{quote:string,author:string,avatar:string}|null $row */`
  plus an `is_array()` guard on the config read). Unknown or incomplete day → `null`.
- **Ink flip:** the template's own idiom is `.light .foo` (it has no `html.dark` rule).
  So: default (dark) `filter: invert(1)`, then `.light .login-quote img { filter: none; }`.
- **Rule order:** the `display: none` base rule must come **before** the
  `@media (min-width: 769px) and (min-height: 700px)` reveal in the same `<style>` block
  (equal specificity, last wins).
- **Avatars:** `<img src="/images/quotes/<file>" width="32" height="32" alt="" aria-hidden="true">`
  — decorative; the visible `<figcaption>` name carries attribution. Do not add `aria-label`
  to the image (it would announce twice).

## Tasks

- [x] **T1 — Avatars.** Generate the seven 64×64 single-ink transparent PNGs from the source
  SVGs with `rsvg-convert` + `magick` (alpha from luminance, ink black), write them to
  `public/images/quotes/`, and confirm each file is < 5 KB and visually comparable as a
  family at 32px.
- [x] **T2 — Config.** Add `config/login-quotes.php` with the table above, keyed 1–7.
- [x] **T3 — Domain helper.** Add `app/Support/LoginQuote.php` per the contract above. Use
  `LocalTime::today()`; no `'Asia/Kuala_Lumpur'` literal, no static mutable state, no DB.
- [x] **T4 — Partial.** Add `resources/views/partials/login-quote.blade.php` taking `$quote`:
  `<figure class="login-quote">` → `<blockquote>` → `<figcaption>` with the `<img>` contract
  and the author name. Renders nothing when `$quote` is `null`.
- [x] **T5 — Wire + CSS.** Include the partial in `layouts/login-template.blade.php` after
  `.page-title` using decision 12's form; add scoped styles to the template's existing
  `<style>` block per the house-style contract (rule order, `.light` idiom, explicit figure
  spacing). No hover-only affordance, so nothing to gate on pointer capability.
- [x] **T6 — Feature test (TDD, written first).** `tests/Feature/Auth/LoginDailyQuoteTest.php`:
  the seven weekdays, the Sunday→Monday midnight boundary in `Asia/Kuala_Lumpur`, both
  routes, and every avatar file present, `.png`, < 5 KB.
  **DB safety (hard):** the page reads no DB, so **no `RefreshDatabase`, no
  `DatabaseMigrations`, no factories or fixtures** — do not copy the neighbouring
  `AuthenticationTest.php` shape. `phpunit.xml:29` pins `class_replacement_testing`, which
  holds the user's manual data; run only against an agreed scratch database, e.g.
  `DB_DATABASE=<scratch> vendor/bin/phpunit --filter=LoginDailyQuote`, print
  `current_database()` first and keep `class_replacement_testing` out of scope.
  `--env=testing` stays forbidden.
  **Clock hygiene:** `setTestNow()` is not auto-reset — add
  `protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }`
  (the `LocalCalendarRulesTest` shape).
- [x] **T7 — Browser check.** Playwright spec for AC5/AC6/AC7, run **read-only**:
  `npx playwright test tests/<spec> --project=chromium --no-deps` with
  `CRS_BASE=http://127.0.0.1:8000`. `--no-deps` is mandatory: the `chromium` project depends
  on `setup`, and `tests/auth.setup.ts` performs four real logins. Never `:8030`.
  Viewports: 1440×900, 1280×800, exactly 769×700, 769×699, 768×800, 390×844.
  Phone capture uses `hasTouch: true, isMobile: true` (method law, 2026-11-17). Set the theme
  with `page.addInitScript` writing `localStorage('theme')`, not by clicking the toggle.
  Assert the Log in button's `boundingBox().bottom` at 769×700 and record the number.
- [x] **T8 — Quality gates.** `composer run lint:check` and
  `vendor/bin/phpstan analyse --memory-limit=1G` (never `composer run types:check`, which
  dies at the default 128M). Both must be clean for `app/`, `config/` and the test files.
- [x] **T9 — Docs + receipt.** `page-changelogs/login-daily-quote-changelog.md` (what, why,
  decision table, the measured fold number and gate value, contrast measurements, evidence,
  files touched), then archive this lane to `.sdd/archive/2026-11-19-login-daily-quote/`
  after verify. No commit or push without an explicit instruction.

## Verification

- Feature test green for all seven days plus the boundary (T6), run on the agreed scratch DB.
- Browser evidence for the six viewports and both themes (T7); the measured fold and contrast
  numbers logged — no Playwright re-run after a pure-CSS adjustment.
- Colour check scoped to new code: `git diff -U0 -- resources/views/layouts/login-template.blade.php`
  and grep **only the added lines** for `#[0-9a-fA-F]{3,6}` / `rgba?(` — expect zero hits.
  (The template already carries legacy hex; the FOUC guard lives in `layouts/ui-template.blade.php`,
  not here, so an unscoped grep would fail on day one.)
- `composer run lint:check` + PHPStan clean (T8).
- Both login pages visually checked on the running server (staff + student), no title/form
  overlap, footer still reachable.

## Open questions

None. The one residual risk — the seven avatars are stylistically inconsistent — is
accepted by flattening them to a single ink colour at 32px.
