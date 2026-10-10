# Proposal — Playwright UI Smoke Suite

> **Status:** Draft — NOT frozen. Review round 1 pending.
> **Baseline:** explore-brief.md (locked decisions D1–D8).
> **Repos:** `CRS-fedora`, branch `fedora-jing`.

---

## 1. Why This Change Is Needed

The frontend mock phase has 9 public Blade-template pages driven by `theme.css`,
`ui-common.js`, and `mock-data.js`. The last Playwright verification was a
**one-off ad-hoc check** (2026-08-01) covering 7 pages. Since then 14 SDD changes
landed, adding new pages (`/student-my-timetable-ui`, `/` welcome) that were
**never verified**, and JS refactors that can silently break pages (the `const hours`
redeclaration SyntaxError from the changelog broke a whole page script until caught).
There is no committed, repeatable regression gate. An incoming UI update from another
repo & branch will land shortly, making a fast verification + troubleshoot loop valuable.

**Goal:** A committed Playwright smoke suite that loads every public UI page, fails on
any console error / JS exception / failed request / missing key element, and serves as
the troubleshooting tool — with the agent fixing any discovered errors until all pages
are green (locked decision D7).

## 2. Scope

### In scope

| Item | Detail |
|------|--------|
| Playwright config | `playwright.config.js` — Chromium only (D8), `webServer` auto-start `php artisan serve --port=8000` with `reuseExistingServer: true` (D5), screenshot+trace on failure. Config + specs use **ESM `import` syntax** (package.json declares `"type": "module"`) |
| Test specs | `tests/e2e/*.spec.js` covering all 9 public routes (D2), each asserting no console errors, no JS exceptions, no failed/4xx/5xx requests, and a key element per page (D3) |
| npm integration | `@playwright/test` devDependency in `package.json`, `test:e2e` script (D4) |
| Git hygiene | `test-results/`, `playwright-report/`, `test-results` artifacts gitignored (D8) |
| Troubleshoot loop | Fix any errors found in Blade templates / `ui-common.js` / `mock-data.js` / `theme.css` until all 9 pages pass (D7) |

### Explicitly out of scope

- `/dashboard` (auth-middleware route — mock phase has no logged-in E2E)
- Deep interaction coverage: sorting, pagination, week arrows, modals, filters, toasts
- Visual regression / screenshot diffing
- Firefox / WebKit browser matrix
- Backend / API / DB testing (pages are static templates)
- CI pipeline integration (local-only runner for now)

## 3. Impact Scope

| File / module | Type | Effect |
|---------------|------|--------|
| `playwright.config.js` (new) | Config | Test runner + server lifecycle |
| `tests/e2e/*.spec.js` (new) | Tests | 9 routes, grouped into ~5 spec files |
| `package.json` | Modify | +1 devDependency, +1 script |
| `package-lock.json` | Modify | lockfile update |
| `.gitignore` | Modify | +`test-results/`, `playwright-report/`, `/playwright/.cache/` |
| Blade templates (`resources/views/**`) | Fix-if-needed | Only touched when suite finds errors (D7) |
| `public/js/ui-common.js`, `mock-data.js`, `public/css/theme.css` | Fix-if-needed | Only touched when suite finds errors (D7) |
| `page-changelogs/*.md` | Modify | Changelog entry for any fixed pages |
| `.sdd/changes/playwright-ui-smoke-suite/` | Docs | SDD artifacts (this change) |

## 4. Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Incoming UI update (another repo & branch) changes page structure mid-change | Apply phase starts only AFTER the pull lands (D6); tests written against final code |
| Playwright browsers not installed on this machine | `npx playwright install chromium` as part of task list |
| Port 8000 already in use | `reuseExistingServer: true` tolerates a running server |
| Page selectors brittle to future UI changes | Key-element assertions kept minimal (page title / main landmark only) |
| npm install could break existing vite/tailwind deps | `@playwright/test` is dev-only; verify `npm run build` still works after install |
| `php artisan serve` needs PHP running | Verify server boot in Task 1 before writing specs |

## 5. Acceptance Criteria

1. `npm run test:e2e` runs the full suite against `http://127.0.0.1:8000` with zero manual setup.
2. All 9 public routes are visited — `/`, `/login/student`, `/login/staff`, `/replacement-home-ui`, `/my-timetable-ui`, `/my-request-history-ui`, `/replacement-arrangement`, `/cohort-timetable-ui`, `/student-my-timetable-ui` — each with 0 console errors, 0 JS exceptions, 0 failed/4xx/5xx requests, and its key element rendering.
3. Any error found is fixed (Blade/JS/CSS) and the suite is re-run to green.
4. Failure artifacts (screenshot + trace) are produced in `test-results/` when a test fails.
5. A page with an injected error fails the suite (self-verification of the gate in a dev-only manual check — not committed).
6. `npm run build` (vite) still succeeds after the devDependency addition.
