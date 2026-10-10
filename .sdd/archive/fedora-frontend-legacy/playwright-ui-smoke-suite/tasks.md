# Tasks — Playwright UI Smoke Suite

> **Status:** Tasks for design.md + specs (frozen, Batches 2+3). This is **Batch 4** — COMPLETE.
> **Working tree:** `fedora-frontend` at `/home/jinglinux/tarumt/CRS-fedora`. Frozen: proposal.md, design.md, specs/ui-smoke-gate/spec.md.
> Each task ≤ 2 hours. **Gating event:** UI update pull from other repo/branch lands BEFORE Task 1 (D6). ✅ D6 satisfied.

---

## Task 1 — Repo prep: devDependency + gitignore + server sanity

**DoD:** `npm run test:e2e` script exists; `.gitignore` covers artifacts; server boots.

- [x] Verify the UI update is merged (user confirms pull; `git log` shows it)
- [x] `npm install -D @playwright/test@~1.62.1` (pin matches global 1.62.1 — reuse cached browsers)
- [x] Add `"test:e2e": "playwright test"` to `package.json` scripts
- [x] `.gitignore`: add `test-results/`, `playwright-report/`, `/playwright/.cache/` (E-11)
- [x] `npx playwright install chromium` if browsers not cached (`~/.cache/ms-playwright`)
- [x] Sanity: `php artisan serve --port=8000` serves `/` (curl 200); `npm run build` still succeeds (S-10)
- [x] Verify `git status` clean of unintended changes

## Task 2 — playwright.config.js (ESM, webServer)

**DoD:** Config file created (committed in Task 7); parses as ESM and lists cleanly (E-8, E-9, E-12).

- [x] `playwright.config.js` — ESM `import` (A1; `require()` would crash with ERR_REQUIRE_ESM)
- [x] `testDir: './tests/e2e'`, `baseURL: 'http://127.0.0.1:8000'`, Chromium project only (E-12)
- [x] `webServer`: `command: 'php artisan serve --port=8000'`, `url: 'http://127.0.0.1:8000'`, `reuseExistingServer: true`, `timeout: 60_000` (A4/E-1)
- [x] `fullyParallel: true`, no workers override (A8)
- [x] `use: { trace: 'retain-on-failure', screenshot: 'only-on-failure' }`; `outputDir: 'test-results'` (E-10)
- [x] Verify: `npx playwright test --list --pass-with-no-tests` parses config as ESM and lists cleanly (webServer boots only when tests run — E-1 verified with Task 3's first real run)

## Task 3 — Shared helper + first spec (timetable)

**DoD:** `checkPage()` helper works; one spec green end-to-end.

- [x] `tests/e2e/helpers/page-check.js` — ESM export `checkPage(page, path, keySelectors, expectedTitle)`:
  - attach listeners BEFORE goto (ordering per design §4): console (error-level only, E-3), pageerror (E-4), requestfailed (E-5), response (status ≥ 400, E-6)
  - `page.goto(baseURL + path, { waitUntil: 'networkidle' })`
  - assert key selectors visible within 10s (E-7) + `page.title()` matches expected (E-7)
  - collect errors; fail test listing console msgs / stacks / failed URLs
- [x] `tests/e2e/timetable.spec.js` — `/my-timetable-ui`, `/cohort-timetable-ui`, `/student-my-timetable-ui` (selectors: `.timetable`, `.timetable` + `.semester-bar`, `.timetable`)
- [x] Run: `npx playwright test tests/e2e/timetable.spec.js` → all 3 green
- [x] Confirm titles via a quick `page.title()` dump (match against template `@section('title')`)

## Task 4 — Remaining specs (home, login, arrangement, history)

**DoD:** All 9 routes covered (E-2); suite green.

- [x] `tests/e2e/home.spec.js` — `/` (h1), `/replacement-home-ui` (tbody tr)
- [x] `tests/e2e/login.spec.js` — `/login/student`, `/login/staff` (`form[action="/login"]` + `#login_id`)
- [x] `tests/e2e/arrangement.spec.js` — `/replacement-arrangement` (`.timetable` + `.legend`)
- [x] `tests/e2e/history.spec.js` — `/my-request-history-ui` (tbody tr)
- [x] Full run: `npm run test:e2e` → 9/9 green, exit 0 (S-1)
- [x] Verify no cross-page flakes on second consecutive run

## Task 5 — Gate self-verification (dev-only, reverted)

**DoD:** The gate provably catches every error class (S-2..S-5, proposal AC5). NOTHING committed.

- [x] S-2: inject `console.error('pw-test')` in one template → spec fails citing it → revert
- [x] S-3: inject redeclared const (ReferenceError) in one template → spec fails with stack → revert
- [x] S-4a: reference missing same-origin asset → 4xx listed in failure → revert
- [x] S-4b: reference unreachable origin asset (`http://127.0.0.1:9/x.png`) → requestfailed listed → revert
- [x] S-5: statically remove key element → selector timeout failure → revert
- [x] After each revert: grep the template for the injection marker (e.g. `pw-test`) — no injected line may remain, while legitimate fixes from earlier tasks stay in place (do NOT use `git diff` cleanliness as the check)
- [x] S-6: confirm `test-results/` contains screenshot + trace from the intentional failures

## Task 6 — Troubleshoot loop (D7)

**DoD:** Any real errors found by the suite are fixed; suite green.

- [x] Run full suite on the (merged) final UI code
- [x] For each failure: inspect console msg / stack / failed URL / screenshot / trace → locate offending file (template inline JS, ui-common.js, mock-data.js, theme.css)
- [x] Fix root cause (NOT test weakening)
- [x] Re-run targeted spec → full suite until 9/9 green
- [x] S-7: verify server reuse works (start artisan serve manually, run suite again — no boot conflict)

## Task 7 — Quality gates + changelog + commit

**DoD:** House rules satisfied; commit ready (S-9).

- [x] `npm run build` still succeeds (S-10)
- [x] Full suite green twice consecutively (no flake)
- [x] `page-changelogs/*.md` entry for every page file touched during apply (Tasks 3/4/6 fixes)
- [x] `.gitignore`/`package.json` diffs reviewed (only intended changes)
- [x] `git status` shows only intended files (config, specs, helper, package.json, package-lock.json, .gitignore, changelogs, SDD docs)
- [x] Commit: `test(e2e): add playwright UI smoke suite for 9 public routes`
