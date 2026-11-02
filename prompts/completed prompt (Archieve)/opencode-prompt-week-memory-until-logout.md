# OpenCode handoff — `week-memory-until-logout` (mid-apply, STOPPED on the user's word)

**STOP ORDER CAME HERE** — the apply = paused mid-gates (NOT committed). This file = the complete chat-context handoff for OpenCode to pick it up. Do NOT restart blind: read this in full first.

## 1. The repo + the state

- Repo: `/home/philler/Desktop/tarumt/degree/y3s1/BMCS3404 PROJECT I (4)/CRS-fedora-backend` — branch `fedora-backend`.
- ⚠️ **Confusion trap**: there is a SECOND checkout at `/home/philler/Desktop/FYP/CRS-fedora-backend` — the PI's shell drifted there several times (tmp probes ran, got MODULE_NOT_FOUND). `cd` back to the tarumt path before ANY command. All tmp scratch files in /tmp and in the CENTRAL repo root = deletable (listed below).
- HEAD = `d84f1a2` (the arrival-grid-no-pin proposal docs). The working tree (below) = UNCOMMITTED week-memory T1–T5 + the spec — this state is VALID mid-apply; the tests' M1–M4 = green against it.

## 2. The change: `week-memory-until-logout` (SDD frozen at `.sdd/changes/week-memory-until-logout/` — read all 5 artifacts first)

The user's law (2026-10-20, verbatim in the explore-brief): the week memory = **session long, per page**, login→logout:
1. Fresh visit of a page in the login session = **TODAY** (my/cohort/student = the server's `currentTimelineWeekIndex()`; venue = the client's `currentWeekIndex()`).
2. prev/next/select/Today-click **saves that page's version** under `weekmem-<key>@<login_week_stamp>` in sessionStorage (per-page, session-scoped via a per-login stamp).
3. Revisiting a page in-session auto-navs to ITS own saved version.
4. **Logout/expiry forgets** — a fresh login stamps anew, so every previous session's key matches nothing.

The applied edits (T1–T5, all in the working tree):
- T1 `app/Providers/FortifyServiceProvider.php:130` — the stamp mint `session(['login_week_stamp' => uniqid('wk', true)]);` inside the `authenticateUsing` hook's success block (after `login_type`).
- T2 `public/js/ui-common.js` — `WeekNavigator` ctor grows a 6th `options { sessionScoped, loginStamp }`; `_weekMemKey = 'weekmem-<storageKey>@<stamp>'`; `save()`/`load()` branch FIRST on `_sessionScoped` (sessionStorage write; the load reads ONLY the matching stamp; localStorage NEVER touched in that mode — my/student's cross-login memory dies by construction; a stamp-less session = memoryless-and-no-writes, e.g. the register auto-login).
- T3 `my-timetable.blade.php` + `student-my-timetable.blade.php` — the ctors = `new WeekNavigator(MockData.semester, weekData, null, null, null, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) })` (the derived key `weekNav-weekSelect` MUST stay).
- T4 `venue-timetable.blade.php` — the ctor gains the options ONLY (its `load()` already existed at :766 — do NOT add another).
- T5 `cohort-timetable.blade.php` — the ctor + its FIRST `weekNav.load()` right after `_currentWeek = currentWeek` (BEFORE the first populate/buildTimetable); the `:268` faculty-switch re-anchor quirk = DELIBERATE, kept.
- T6 `tests/week-memory-until-logout.spec.ts` (NEW, 5 rows) — M1 today+selector-save, M2 the two-page trip, M3 the stale-stamp rebind, M4 the venue-resume + the COHORT-revisit-re-lands-TODAY receipt (the cohort's OWN freeze: a cohort switch re-lands today — the `onCohortChange` tail's re-anchor, the last-visit-dd D3 family — documented by the page, NOT a bug), M5 the dance.
- T9 = CLOSED (the user decided): the student-page live row = the in-spec dance.

Review-log additions live in `.sdd/changes/week-memory-until-logout/review-log.md` (the decode history). T8 = NOT done (4 changelog entries pending — listed below).

## 3. Test state (the last runs)

- `npx playwright test tests/week-memory-until-logout.spec.ts --grep-invert "TC70"` → **M1 ✓, M2 ✓, M3 ✓, M4 ✓, M5 ✘**.
- M5 = the dance row (RUNS LAST in the file by design — the = the row order = M4-venue-cohort then M5-dance; keep that order). Its own isolated-context walk (a standalone cjs replica) = **GREEN**: staff-4363 login → my-timetable → nextWeek (saves) → the REAL logout → /login/student as 25RSD0001 → her page = TODAY (cw 4, sel 4) with the 4363-leftover key INERT in the tab storage. The MECHANISM = proven end-to-end.
- **THE M5 SPEC BLOCKER (the mystery to corner)**: in the harness, after the `.logout-btn` click, `p.waitForURL(/login\/staff/)` = **Timeout** and the snapshot = "Welcome En. Lim Jia Zheng, Lecturer (5770)" + "Log out" — the logout never completes in the SPEC walk, while the SAME dance = green as a cjs. Probes so far: the logout POST goes out `cookie: NONE` (Playwright header capture may omit cookies on page-initiated navs — an instrumentation artifact suspicion, not proof), the response = 302 → /login/staff, `ctx.cookies()` right after showed `laravel-session` ABSENT (the session jar rotated/emptied mid-flight). No page errors. The test-level timeout = raised to 90s with `test.setTimeout` (the walk = 3 logins).
- The candidate checklist for the next walker: (a) diff the SPEC child-context (created via `browser.newContext()` inside a `test.use({ storageState: 5770 })` test) vs the standalone cjs context — capture `ctx.cookies()` + a `page.on('response')/('request')` dump at EVERY step of both; (b) check whether the CSRF token on the nav-bar's `logout-form` (rendered once per page load) goes stale after the in-session week navs — try `page.evaluate(() => document.getElementById('logout-form').submit())` instead of the `.logout-btn` click; (c) the `.logout-btn` = TWO rendered (the topbar + the drawer clone) — the `.first().click()` = current; verify which one the HANDLER actually fires under the harness; (d) the Fortify limiter (5/min per login_id|type|ip) — the dance = ~3 logins across the file, but the earlier runs seeded failures — check the `login_fail:*`/`login_lockout:*` Cache keys; (e) the `test.use({ storageState: 5770 })` on the parent test + the `browser.newContext()` child = check for any base-context cookie bleed via the shared `browser` fixture.
- M4 note (the dance's staff leg) = **4363, NOT 5770**: Laravel's login `regenerate()` tears down the requesting context's OLD session row — logging in as 5770 from a fresh context KEPT killing the shared storageState session (the M5's main-context pages were bouncing to the student login mid-battery). 4363 = auth.setup's L-only identity. **Keep it 4363.**

## 4. What remains (the plan steps 2–5 as prescribed)

- **Step 2 (Spec)**: corner M5 per section 3's checklist; get 5/5 green in one run.
- **Step 3 (T7 gates)**: `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` · `phpunit` (the 230 baseline — a PHP file is touched) · the Playwright battery: `arrival-grid-no-pin` (3), `session-arrival-pin-guard` (3), `arrangement-earliest-week-default` (3), `default-pin` (3), `replace-now`, `venue-cross-week-handoff` (2), `venue-intent-modal` (C2 = the documented noise), `venue-book`, `venue-timetable` (TC34/35 = the documented baseline rows), `ui-regression` (TC-2.x staleness documented) — all `--grep-invert "TC70"`; the ritual before runs (pkill serve → rm storage/framework/views/*.php → serve with PHP_CLI_SERVER_WORKERS=8; re-run `npx playwright test tests/auth.setup.ts --project=setup` when states expire).
- **Step 4 (commit)**: the file set = T1–T5 + the spec + the 4 changelogs below + the review-log; message per the house conventional style; **NEVER push without the user's explicit "push"**.
- **T8 (still pending)**: dated changelog entries — `page-changelogs/my-timetable-changelog.md`, `venue-timetable-ui-changelog.md`, `cohort-timetable-changelog.md` (the LIVE one), `student-my-timetable-ui-changelog.md` (exists) — the law's summary + the until-logout contract + the M4/M5 receipts.
- **Scratch cleanup before commit**: delete `m5-decode.cjs`, `probe-sess.cjs`, `tmp-*.cjs`, `tmp-*.php`, `tmp-cohort-page.png`, `auth-*.spec-helper` strays in the repo root (keep `prompts/pi-prompt-venue-cross-week-handoff.md` — the user's own file).
- **After apply**: `timetable-landing-policy` needs its slim-down (today-default + session law already shipped by this change); then the verify/archive day on the user's word.

## 5. The standing absolutes (from AGENTS.md + the session chain)

- **TC70** (`tests/venue-timetable.spec.ts:690–730`) must NEVER execute — always `--grep-invert "TC70"`.
- The demo DB `class_replacement_fresh` = never re-seeded, never `migrate:fresh`; Route-C repairs = USER-RUN ONLY (pgAdmin). Tests use `class_replacement_testing`.
- `php artisan serve` ritual = TWO separate commands (pkill → rm compiled views → serve); NEVER `pkill -9 php`.
- Never push without the user's explicit "push". Conventional commits. Never commit passwords (the demo PW `Tarumt@2026` lives in `tests/auth.setup.ts` already — do not spread it into artifacts).
- The identities: 5770 = Lim Jia Zheng (user 11), 4363 = the L-only lecturer (user 13), 5425 = the demo PL (user 1), 25RSD0001 = the seed student (user 119).

## 6. The sibling lanes (the apply queue as the user ordered)

1. `arrival-grid-no-pin` — APPLIED (`94c6916`), receipts in the changelog.
2. **`week-memory-until-logout` — THIS HANDOFF (paused mid-gates, T8 pending).**
3. `timetable-landing-policy` — hold: it predates today's laws; slim-down/reconcile before any apply.

*Handoff written by the tarumt PI on the user's STOP order (2026-10-23). Everything above = receipts-first; the next walker = read, verify one step, then move.*
