# Explore Brief — Playwright UI Smoke Suite

> **Change:** `playwright-ui-smoke-suite`
> **Status:** Explore complete — locked decisions D1–D8, ready for `/sdd-propose`.
> **Grilling dates:** 2026-08-06

---

## 1. Problem Statement

The frontend is a mock/UI phase with 9 Blade template pages driven by `theme.css`,
`ui-common.js`, and `mock-data.js`. The only previous Playwright verification was an
**ad-hoc one-off** (2026-08-01, `oop-js-consolidation-changelog.md`) covering 7 pages.
Since then, 14 SDD changes landed (replacement-home dashboard, my-request-history,
mobile-responsive, cohort-timetable, toast-undo-bar, etc.), and 2 routes
(`/student-my-timetable-ui`, `/`) were **never** verified. The `const hours`
redeclaration SyntaxError from the changelog proves pages can silently break
(missing console error check). There is no repeatable regression gate.

**Goal:** A committed, repeatable Playwright smoke suite that loads every public UI
page, fails on any page error, and is the troubleshooting tool: errors found → fixed
in the same change until green.

## 2. Locked Decisions (D1–D10)

| ID | Decision | Rationale |
|----|----------|-----------|
| D1 | **Deliverable:** committed E2E smoke suite via full SDD lifecycle | Repeatable gate; catches regressions (e.g. `const hours` redeclaration) |
| D2 | **Page scope:** all 9 public routes — `/`, `/login/student`, `/login/staff`, `/replacement-home-ui`, `/my-timetable-ui`, `/my-request-history-ui`, `/replacement-arrangement`, `/cohort-timetable-ui`, `/student-my-timetable-ui` | `/dashboard` EXCLUDED (auth middleware, out of mock phase) |
| D3 | **Error definition:** (a) console `error`-level messages, (b) JS page exceptions, (c) failed requests (network failures + 4xx/5xx responses), (d) key element presence per page | Covers the failure modes that previously escaped detection |
| D4 | **Tooling:** project-local `@playwright/test` devDependency in `package.json`; `npm run test:e2e` script; config + specs committed | Self-contained, reproducible, standard practice |
| D5 | **Server:** `webServer` in `playwright.config.js` — `php artisan serve --port=8000`, `url: http://127.0.0.1:8000`, `reuseExistingServer: true` | Zero manual steps; also works with manually-running server |
| D6 | **Sequencing:** planning artifacts (explore/propose/design/spec/tasks) now; **apply after** the UI update pull lands (~30 min, from another repo & branch) | Write/run tests once against final code; no double-fixing |
| D7 | **Troubleshoot loop:** errors found by the suite are FIXED by the agent in this same change, re-run until all 9 pages green | User request: "troubleshoots" + "u fix" |
| D8 | **Browser + artifacts:** Chromium only; on failure auto-screenshot + trace saved to `test-results/` (gitignored); specs under `tests/e2e/` | Fastest, sufficient for mock UI; debuggable failures |

## 3. Rejected Approaches (and WHY)

| Approach | Rejected because |
|----------|------------------|
| One-off ad-hoc Playwright script (no committed artifacts) | Not repeatable; no regression value; the 7-page check from 2026-08-01 proved ad-hoc checks get forgotten |
| Full deep-interaction suite (sort, pagination, week arrows, modals, filters) | Large scope; smoke coverage first; deep interactions can be a follow-up change |
| Global Playwright binary only (no package.json devDependency) | Fragile — breaks on any machine without the global install |
| Manual server start (`php artisan serve` by hand before tests) | Friction, easy to forget; `webServer` auto-start is standard |
| Write + run tests against current code, re-verify after the UI update | Double work — errors fixed now may already be fixed by the update |

## 4. Cross-Module Data Flows

```
npm run test:e2e
  └─ playwright test (config: tests/e2e specs)
       ├─ webServer auto-start: php artisan serve --port=8000
       ├─ browser goto → http://127.0.0.1:8000/{route}
       │    └─ Laravel Route::view → resources/views/ui-design-templates/*.blade.php
       │         └─ @extends layouts.ui-template
       │              └─ static assets: public/css/theme.css, public/js/ui-common.js,
       │                 public/js/mock-data.js, public/images/*
       ├─ page.on('console'|'pageerror'|'requestfailed'|'response') → assert no errors
       └─ on failure → test-results/ (screenshot + trace, gitignored)
```

No backend/DB interaction — pages are static templates (mock phase).

## 5. Page → Spec Mapping (initial)

| Route | Spec file | Key element assert |
|-------|-----------|--------------------|
| `/` | `home.spec.js` | welcome hero renders |
| `/login/student` | `login.spec.js` | student login form |
| `/login/staff` | `login.spec.js` | staff login form |
| `/replacement-home-ui` | `home.spec.js` | request list table |
| `/my-timetable-ui` | `timetable.spec.js` | timetable grid |
| `/my-request-history-ui` | `history.spec.js` | history table |
| `/replacement-arrangement` | `arrangement.spec.js` | selection summary + legend |
| `/cohort-timetable-ui` | `timetable.spec.js` | cohort grid + semester bar |
| `/student-my-timetable-ui` | `timetable.spec.js` | student timetable grid |

## 6. Risks / Notes

- **UI update incoming** (~30 min, from another repo & branch — remote/branch names
  unknown until user signals). Apply phase starts AFTER the merge.
- `/student-my-timetable-ui` and `/` were never Playwright-verified — highest
  regression risk; likely where errors will surface.
- `package.json` gains one devDependency (`@playwright/test`) + one script —
  no runtime impact on the app.
- `test-results/` must be added to `.gitignore`.
- Laravel must be able to serve without `npm run build` (static templates — verified
  via `layouts.ui-template` loading static assets directly).
- Playwright browsers: `npx playwright install chromium` may be needed post-install.

## 7. Open Questions (deferred, non-blocking)

- Q-1: Remote/branch name of the incoming UI update — resolved at pull time (D6).
- Q-2: Whether `/dashboard` (auth) gets a logged-in smoke test later — out of scope now.
- Q-3: Deep interaction coverage (sort/pagination/modals) as a follow-up change — deferred.
