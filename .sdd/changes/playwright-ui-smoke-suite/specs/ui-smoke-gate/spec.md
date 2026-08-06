# Spec — UI Smoke Gate (capability)

> **Status:** Spec for design.md (frozen, Batch 2). This is **Batch 3** — pending review.
> **Capability:** Playwright smoke verification of all 9 public UI routes (D2/D3).
> **Component under test:** the committed suite at `tests/e2e/` + `playwright.config.js`.

---

## 1. Description

A Playwright (Chromium) smoke suite that loads all 9 public UI pages and fails any
page that produces a console error, an uncaught JS exception, a failed or 4xx/5xx
HTTP response, or a missing key landmark. Run via `npm run test:e2e`. Errors found
are fixed in this same change until the whole suite is green (D7).

## 2. Requirements

| ID | Requirement | Source |
|----|-------------|--------|
| E-1 | `npm run test:e2e` boots `php artisan serve` on port 8000 automatically (or reuses a running one) and runs the suite | D5, A4 |
| E-2 | All 9 public routes are visited exactly once per full run: `/`, `/login/student`, `/login/staff`, `/replacement-home-ui`, `/my-timetable-ui`, `/my-request-history-ui`, `/replacement-arrangement`, `/cohort-timetable-ui`, `/student-my-timetable-ui` | D2 |
| E-3 | Console `error`-level messages fail the page (warnings tolerated) | D3, A5 |
| E-4 | Uncaught JS exceptions (`pageerror`) fail the page | D3, A5 |
| E-5 | Failed network requests (`requestfailed`) fail the page | D3, A5 |
| E-6 | HTTP responses with status ≥ 400 fail the page | D3, A5 |
| E-7 | Each page asserts its key landmark renders within 10s (see §3 selectors) **and its `<title>` matches the expected page title** | D3, A6 |
| E-8 | `test:e2e` script + `@playwright/test ~1.62.1` devDependency committed in package.json | D4, A2 |
| E-9 | Config + specs use ESM `import` syntax | A1 |
| E-10 | Failures produce screenshot + trace in `test-results/` (gitignored) | D8, A9 |
| E-11 | `.gitignore` covers `test-results/`, `playwright-report/`, `/playwright/.cache/` | proposal §3 |
| E-12 | Suite runs on Chromium only; Firefox/WebKit not configured | D8 |
| E-13 | No visual-diff, no deep interactions (sort/pagination/modals), no CI wiring | proposal §2 out-of-scope |

## 3. Key Selectors (per E-7)

| Route | Selector |
|-------|----------|
| `/` | `h1` |
| `/login/student` | `form[action="/login"]` + `#login_id` |
| `/login/staff` | `form[action="/login"]` + `#login_id` |
| `/replacement-home-ui` | `tbody tr` |
| `/my-timetable-ui` | `.timetable` |
| `/my-request-history-ui` | `tbody tr` |
| `/replacement-arrangement` | `.timetable` + `.legend` |
| `/cohort-timetable-ui` | `.timetable` + `.semester-bar` |
| `/student-my-timetable-ui` | `.timetable` |

## 4. Scenarios

> Test DB = none (static templates). Server = `php artisan serve` via webServer or
> pre-existing on 127.0.0.1:8000.

### S-1 Fresh suite run — all green
- Given server up (auto or reused) and no page errors
- When `npm run test:e2e` completes
- Then exit code 0, all 9 routes visited, 0 failures

### S-2 Console error detection
- Given `/my-timetable-ui` has an injected `console.error('x')` in its template script
- When the suite runs
- Then the timetable spec fails with the console message in the error output

### S-3 JS exception detection
- Given a template script throws `ReferenceError` at load (e.g. redeclared const)
- When the suite runs
- Then the page's spec fails with the exception stack in the error output

### S-4 Failed request detection
- Given a template references a missing same-origin asset (e.g. `/images/logo_banner.png` absent)
- When the suite runs
- Then the page's spec fails listing the 4xx URL (exercises the response listener, E-6)
- And given a template references an unreachable origin asset (e.g. `http://127.0.0.1:9/x.png`)
- Then the page's spec fails via the `requestfailed` listener (exercises E-5)

> S-2..S-5 are **dev-only manual demonstrations** of gate semantics (proposal AC5) —
> error injection is reverted; nothing of the sort is committed.

### S-5 Missing key element
- Given a page renders without its landmark (key element statically removed from the template, no JS error involved)
- When the suite runs
- Then the page's spec fails with a selector timeout (isolates the E-7 path)

### S-6 Failure artifacts
- Given a failing spec
- Then `test-results/` contains a screenshot (`.png`) and trace (`.zip`) for that spec

### S-7 Server reuse
- Given `php artisan serve` already running on port 8000
- When the suite starts
- Then it reuses the running server (no second boot error, `reuseExistingServer: true`)

### S-8 ESM compliance
- Given the config and specs files
- Then they parse as ESM (no `require()` calls; import syntax only)

### S-9 Troubleshoot loop (acceptance for D7)
- Given the suite fails on N pages
- When the agent fixes the offending Blade/JS/CSS and re-runs
- Then all specs pass; every touched page has a `page-changelogs/*.md` entry

### S-10 Build integrity
- Given `@playwright/test` added to devDependencies
- Then `npm run build` (vite) still succeeds (no runtime impact)
