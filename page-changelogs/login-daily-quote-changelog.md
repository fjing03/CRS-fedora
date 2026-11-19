# Login daily quote — changelog

**Lane:** SDD `login-daily-quote` (compact artifact, SDD cost cut 2026-11-17)
**Date:** 2026-11-19
**Scope:** Both live login pages (`/login/staff`, `/login/student`), which share
`layouts/login-template.blade.php`. No DB, no migration, no new dependency, no JS file.

## What changed

A single athlete quote now sits between the "Staff/Student Login" title and the login
form: the quote, then a 32px avatar with the athlete's name beside it. One quote per
weekday, rotating automatically at **00:00 Asia/Kuala_Lumpur**.

"It is decoration" drove every decision: it is hidden on phones and short windows, it
never animates, and it is set small enough to read as a tagline rather than a block of
copy.

## Decisions

| Topic | Decision |
|---|---|
| Surface | One insertion in the shared login layout; both logins inherit it |
| Placement | Inside the card, after `.page-title`, before `<form>` |
| Visibility | `min-width: 769px` **and** `min-height: 700px`; `display: none` otherwise |
| Alignment | Centred on the card axis, matching the logo and title |
| Day boundary | `Asia/Kuala_Lumpur` via `App\Support\LocalTime`; the app stays UTC |
| Overrides | None — no query parameter, no env flag |
| Storage | `config/login-quotes.php`, ISO weekday keys 1–7 |
| Attribution | Avatar **plus** a visible text name (a silhouette alone would not name the quoter) |
| Asset format | 64×64 single-ink transparent PNGs (~1.6–2.4 KB each); the seven supplied SVGs stay as source art |
| Ink | `invert(1)` by default (dark is the document's default theme), `filter: none` in light |

## Quote table

| Day | Quote | Author | Avatar |
|---|---|---|---|
| Mon | You have to fight to reach your dream. You have to sacrifice and work hard for it. | Lionel Messi | `messi.png` |
| Tue | The dream is not the destination. It's the journey. | Kobe Bryant | `kobe.png` |
| Wed | I've failed over and over and over again in my life. And that is why I succeed. | Michael Jordan | `jordan.png` |
| Thu | Success is not an accident, success is actually a choice. | Stephen Curry | `curry.png` |
| Fri | Success isn't always about greatness. It's about consistency. | The Rock | `the-rock.png` |
| Sat | Tired is only in the mind. You tell yourself you're tired, you're gonna be tired. | LeBron James | `lebron.png` |
| Sun | Your love makes me strong. Your hate makes me unstoppable. | Cristiano Ronaldo | `cr7.png` |

Attributions are the user's supplied copy; they were not independently verified (the
web-search tool returned HTTP 402 during planning).

## Files

**Added**
- `config/login-quotes.php` — the seven records
- `app/Support/LoginQuote.php` — `forDay()`, `today()`; null-safe, read-only
- `resources/views/partials/login-quote.blade.php` — `figure`/`blockquote`/`figcaption`
- `public/images/quotes/*.png` — seven 64×64 ink avatars
- `public/images/quotes/*.svg` — the seven supplied VTracer sources (kept as art source)
- `tests/Feature/Auth/LoginDailyQuoteTest.php` — 19 tests, 180 assertions
- `tests/login-daily-quote.spec.ts` — gates, fold, themes
- `tests/login-quote-contrast.spec.ts` — measured contrast

**Modified**
- `resources/views/layouts/login-template.blade.php` — one `@include` + a scoped
  `.login-quote` block in its existing `<style>`

`public/css/theme.css` was **not** touched: every colour came from existing tokens.

## Evidence

**Feature test** — `DB_DATABASE=class_replacement_quote_test vendor/bin/phpunit --filter=LoginDailyQuote`
→ 19 tests, 180 assertions, pass. Covers 7 weekdays × 2 routes, the quote's position in the
document, and both directions of the MYT midnight boundary (frozen in UTC, so it fails if
the code ever reads the app clock).

**Browser** — `CRS_BASE=http://127.0.0.1:8000 npx playwright test tests/login-daily-quote.spec.ts --project=chromium --no-deps`
→ 8 pass. Visible at 1440×900, 1280×800 and exactly 769×700; hidden at 768×800, 769×699 and
390×844; avatar decodes; no horizontal overflow; no title/form overlap.

**The fold measurement (the gate's own justification):** at exactly 769×700 the Log in
button's bottom edge sits at **650px of 700** — 50px of headroom. The 700px height gate
therefore holds and the spec's contingency (raise it to 740/760) was not needed.

**Contrast** — sampled against the actual painted pixel, not the computed background,
because the card is translucent over a photograph:

| Theme | Text | Background | Ratio |
|---|---|---|---|
| Dark | `rgb(224,230,237)` | `rgb(41,51,63)` | **10.19:1** |
| Light | `rgb(27,40,56)` | `rgb(190,195,198)` | **8.39:1** |

Light mode measured **3.17:1** with `--color-on-surface-variant` — below the 4.5:1 floor,
exactly where the lane spec predicted. The pre-agreed fallback was applied:
`--color-on-surface` at the same 12.5px. The author line took the same token with 0.9
opacity, because hierarchy at 11px comes from size and weight, not from a lighter colour.

**Quality gates** — `pint --test` clean; PHPStan level 7 clean for `app/`, `config/` and the
test file. Added lines in the template contain zero hardcoded hex/rgb (checked with
`git diff -U0` — the file's legacy colours are untouched and out of scope).

## Notes and known limits

- **The seven avatars are stylistically inconsistent** — silhouette, line art and portrait
  in one set. Flattening to a single ink colour at 32px is what makes them read as one
  family. Kobe's source is a two-tone negative-space poster, so its face is transparent and
  takes the background colour; on dark it reads as a stencil rather than a solid glyph.
  That is inherent to single-ink conversion, not a defect.
- **The partial does not read the clock**; the layout resolves the day once and passes it
  down, so both pages cannot disagree about which day it is.
- **An unknown weekday renders nothing** rather than an empty block. The config is asserted
  to hold exactly keys 1–7, so a blank day cannot ship silently.
- **`carbon::setTestNow()` is not auto-reset**; the test's `tearDown()` clears it.
- The lane's review round was a `sdd-reviewer` subagent with **no pinned model**, so it
  inherited the orchestrating session's model. The user accepted it as the single gate;
  recorded in `review-log.md` so it is not mistaken for an independent review.
