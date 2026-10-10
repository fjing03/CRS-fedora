# Design — Playwright UI Smoke Suite

> **Status:** Draft — NOT frozen. Review round 1 pending.
> **Frozen:** proposal.md. **Baseline:** explore-brief.md D1–D8.

---

## 1. Technical Approach

A project-local Playwright (Chromium) test suite in `tests/e2e/`, run via `npm run
test:e2e`. The config declares a `webServer` that boots `php artisan serve` on port
8000 and reuses an existing server if one is already up. Every public route is
visited; per-page checks assert (a) no console `error` messages, (b) no uncaught JS
exceptions, (c) no failed or 4xx/5xx HTTP responses, (d) a key landmark element
renders. Failures produce a screenshot + trace in `test-results/`.

The suite is a **gate**: any error found in Blade/JS/CSS is fixed in this same change
(D7) until all 9 pages pass.

## 2. Files

### New

| File | Purpose |
|------|---------|
| `playwright.config.js` | Runner config: testDir `./tests/e2e`, Chromium only, webServer, trace/screenshot on failure |
| `tests/e2e/helpers/page-check.js` | Shared `checkPage(page, path, keySelector)` — loads a route, attaches console/pageerror/requestfailed/response listeners, asserts key element |
| `tests/e2e/home.spec.js` | `/` + `/replacement-home-ui` |
| `tests/e2e/login.spec.js` | `/login/student` + `/login/staff` |
| `tests/e2e/timetable.spec.js` | `/my-timetable-ui` + `/cohort-timetable-ui` + `/student-my-timetable-ui` |
| `tests/e2e/arrangement.spec.js` | `/replacement-arrangement` |
| `tests/e2e/history.spec.js` | `/my-request-history-ui` |

### Modified

| File | Change |
|------|--------|
| `package.json` | devDependency `@playwright/test` `~1.62.1` (matches global 1.62.1 — reuse cached browsers); script `"test:e2e": "playwright test"` |
| `.gitignore` | Add `test-results/`, `playwright-report/`, `/playwright/.cache/` (all three per frozen proposal §3) |

### Fix-if-needed (D7)

`resources/views/**` (Blade), `public/js/ui-common.js`, `public/js/mock-data.js`,
`public/css/theme.css` — only touched when the suite finds an error.

## 3. Architecture Decisions

| ID | Decision |
|----|----------|
| A1 | **ESM modules** — `playwright.config.js` and all specs use `import` (package.json `"type": "module"`; a CJS `require()` would crash with `ERR_REQUIRE_ESM`) |
| A2 | **Version pin** `"@playwright/test": "~1.62.1"` — matches the globally-installed 1.62.1, maximizing reuse of cached Chromium; post-install `npx playwright install chromium` as fallback |
| A3 | **Shared helper** `checkPage()` — DRY: one place attaches the 3 error listeners + key-selector assertion; specs stay tiny |
| A4 | **webServer config** — `command: 'php artisan serve --port=8000'`, `url: 'http://127.0.0.1:8000'`, `reuseExistingServer: true`, `timeout: 60_000` |
| A5 | **Error listener semantics** — console `error`-level only (warnings tolerated); `requestfailed` + responses with status ≥ 400 fail the page; CSS/JS/JSON and document requests all counted |
| A6 | **Key selector per page** — minimal landmark (e.g. timetable grid, login form) chosen from each template's actual DOM; page title also asserted |
| A7 | **No visual assertions** — smoke only; screenshots written only on failure as diagnostics |
| A8 | **Parallelism** — `fullyParallel: true` in config (safe: stateless pages, single shared webServer); no `workers` override (Playwright default) |
| A9 | **Output dir** `test-results/` (gitignored) — Playwright default for failure artifacts |

## 4. Data Flow

```
npm run test:e2e
 └─ playwright test (config A1–A9)
     ├─ webServer: php artisan serve --port=8000 (A4)
     ├─ per spec: checkPage(page, path, keySelector)
     │    ├─ listeners: console(page), pageerror(page), requestfailed(page), response(page)
     │    ├─ page.goto(baseURL + path, waitUntil: 'networkidle')
     │    ├─ assert keySelector visible (timeout 10s)
     │    └─ collect errors → test fails if any
     └─ on failure: screenshot + trace → test-results/
```

`baseURL: http://127.0.0.1:8000` from config; specs use relative paths.

## 5. Key Selectors (initial, verify at apply time)

| Route | Key selector |
|-------|--------------|
| `/` | `h1` (welcome hero) |
| `/login/student` | `form[action="/login"]` containing `#login_id` input |
| `/login/staff` | `form[action="/login"]` containing `#login_id` input |
| `/replacement-home-ui` | table `tbody tr` (request list) |
| `/my-timetable-ui` | `.timetable` table |
| `/my-request-history-ui` | table `tbody tr` |
| `/replacement-arrangement` | `.timetable` + legend |
| `/cohort-timetable-ui` | `.timetable` + semester bar |
| `/student-my-timetable-ui` | `.timetable` |

> Selectors refined against real DOM during apply (Task 3); key-element check stays
> minimal per A6.

## 6. Dependencies

- Node.js + npm (present — vite/tailwind already in package.json)
- PHP + `php artisan serve` (present)
- Playwright Chromium — `npx playwright install chromium` if not cached
- No backend/DB involvement; no new runtime PHP dependencies

## 7. Troubleshoot Loop (D7)

1. Run suite → failures list page + error type.
2. Inspect: console message, stack trace, failed request URL, screenshot/trace.
3. Fix offending file (Blade template inline JS, `ui-common.js`, `mock-data.js`, `theme.css`).
4. Re-run targeted spec → full suite until green.
5. Changelog entries in `page-changelogs/*.md` for every touched page file.
