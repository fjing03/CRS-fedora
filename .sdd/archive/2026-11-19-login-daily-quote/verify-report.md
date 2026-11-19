# Verify report — login-daily-quote

**Date:** 2026-11-19
**Change:** `.sdd/changes/login-daily-quote/` (compact artifact)
**Result:** PASS — every acceptance criterion has evidence.

## Acceptance criteria

| # | Criterion | Evidence | Result |
|---|---|---|---|
| AC1 | Both logins render `<figure class="login-quote">` between title and form | `test_the_quote_sits_between_the_page_title_and_the_login_form` — `assertSeeInOrder([page-title, figure, <form>])`; also confirmed on the served page | ✅ |
| AC2 | Each ISO weekday shows its own quote, author and avatar, on both routes | `test_both_login_pages_show_the_days_quote_author_and_avatar` — 14 cases (7 × 2) | ✅ |
| AC3 | Sun 23:59 MYT → Ronaldo; Mon 00:00 MYT → Messi; UTC never consulted | Two boundary tests frozen at `15:59` / `16:00 UTC`, each asserting the right name present **and** the wrong one absent | ✅ |
| AC4 | Avatars exist, end in `.png`, under 5 KB | `test_all_seven_avatars_exist_as_small_png_files`; real sizes 1,579–2,403 B | ✅ |
| AC5 | Visible at 1440×900 / 1280×800 / 769×700; avatar loads; no overlap; no overflow; button inside the viewport at the gate | Browser spec — 3 viewports × 2 routes, plus the fold test: button bottom **650 / 700** | ✅ |
| AC6 | Hidden at 769×699, 768×800, 390×844; button reachable without scrolling | Gates spec (hidden) + `login-quote-below-gate.spec.ts` (quote height exactly **0**, button bottom 611 / 541 / 571) | ✅ |
| AC7 | Tokens only; ink flips with theme; ≥4.5:1 in both themes | Zero hex/rgb in added lines (`git diff -U0`); filter flip asserted per theme; contrast **10.19:1** dark, **8.39:1** light | ✅ |
| AC8 | No new JS file, no DB access, no migration, no new dependency | `git status` shows no JS/asset-pipeline/migration/composer changes; helper and partial read no DB | ✅ |
| AC9 | A weekday with no row renders nothing | `LoginQuote::forDay(0)` and `forDay(8)` both `null`; partial's `@if ($quote)` guards the whole figure; config asserted to hold exactly keys 1–7 | ✅ |

## Gates

- **Feature tests:** 19 passed, 180 assertions — `DB_DATABASE=class_replacement_quote_test` (a throwaway DB created for this run, dropped afterwards; `class_replacement_testing` never touched).
- **Browser:** 11 passed across three specs, `--project=chromium --no-deps` against `:8000`. `:8030` never used.
- **Pint:** clean. **PHPStan level 7:** clean for `app/`, `config/`, test file.
- **Demo DBs:** untouched. No migration, no seeder, no schema change.

## Deviations from the plan (all justified, all logged)

1. **Quote colour is `--color-on-surface`, not `--color-on-surface-variant.`** Light mode measured
   3.17:1; this is the fallback decision 5 pre-agreed. Re-measured 8.39:1.
2. **The author line uses the same token at 0.9 opacity** — at 11px it needs the same 4.5:1,
   and hierarchy is carried by size and weight.
3. **A third spec file was added** (`login-quote-below-gate.spec.ts`) because AC6's second
   clause was otherwise unmeasurable — the reviewer flagged it as vacuous during planning.
4. **PHPStan removed dead code** in the helper: the `@var` annotation made the `is_array()`
   guard unreachable; the guard now does the narrowing.
5. **Browser measurements disable animations** — the card's entry transform distorts
   `boundingBox()`, and the looping backdrop means `getAnimations().finished` never resolves.

## Not verified

- The quote text's attributions are unverified copy (the web-search tool returned HTTP 402).
  Recorded as a known limit in the changelog.
- Only Chromium was exercised. No Firefox/WebKit run was planned or performed.

## Verdict

PASS. Ready to archive.
