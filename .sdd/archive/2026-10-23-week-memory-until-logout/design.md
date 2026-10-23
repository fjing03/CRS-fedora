# Design — week-memory-until-logout

## D1 — the server side (1 line, the memory's lifecycle anchor)

`app/Providers/FortifyServiceProvider.php` — the login success family
(:123's `session(['login_type' => $loginType]);` hook):

```php
// week-memory-until-logout: the per-login scope marker — WeekNavigator
// (sessionScoped mode) binds page memory to THIS stamp; every logout
// (Logout action) and expiry (EnsureSessionLifetime) invalidates the
// session, so the contract "fresh login = every page on TODAY" holds
// through every server path (no client logout hook needed).
session(['login_week_stamp' => uniqid('wk', true)]);
```

- Minted at EVERY successful login (the hook runs per login).
- `uniqid('wk', true)` — collision-proof across re-logins in the same
  browser tab (the sessionStorage survives tab-internal
  logout→login→logout; the STAMP is what makes old memories neutral).
- No other server touch: `Logout` + `EnsureSessionLifetime` already
  `invalidate()` — the stamp dies by construction.

## D2 — WeekNavigator gains the session-scoped mode (ONE shared mechanism)

`public/js/ui-common.js` — the class constructor signature becomes
`constructor(semesterData, weekData, selectId, storageKey, weekFilter, options)`
(new 6th param, `{ sessionScoped, loginStamp }`):

```js
this._sessionScoped = !!(options && options.sessionScoped);
this._loginStamp = (options && options.loginStamp) || '';
this._weekMemKey = 'weekmem-' + this._storageKey + '@' + this._loginStamp;
```

The storage bodies branch FIRST:

```js
save() {
    try {
        if (this._sessionScoped) {
            if (this._loginStamp === '') return;   /* SYMMETRIC gate (round-1).₄2 fix: the register auto-login path (CreateNewUser) never mints a stamp — no stamp = no memory writes (a today-only session, contract preserved) */
            sessionStorage.setItem(this._weekMemKey, this._currentWeek);
            return;   /* session mode: localStorage never touched */
        }
        localStorage.setItem(this._storageKey, this._currentWeek);   /* shipped, unchanged */
    } catch (e) { /* ignore */ }
}

load() {
    try {
        if (this._sessionScoped) {
            /* a stamp-less or mismatched context = memoryless → today */
            var sv = this._loginStamp ? sessionStorage.getItem(this._weekMemKey) : null;
            if (sv !== null && !isNaN(parseInt(sv, 10))) {
                this._currentWeek = Math.max(0, Math.min(this._semester.weeks - 1, parseInt(sv, 10)));
            }
            return;   /* no legacy migration in session mode */
        }
        … the shipped localStorage body VERBATIM …
    } catch (e) { /* ignore */ }
    /* the shipped filter-resolve tail stays (view pages carry no filter —
       inert here; the arrangement's wait-then-desktop family unaffected) */
}
```

- Per-page keys: `'weekmem-' + storageKey + '@' + stamp` — my/student
  default to `weekNav-weekSelect` (each its own key ✓ per-page versions),
  the venue's `venueTimetableWeek`, the cohort's `cohortTimetableWeek`.
- The `loginStamp` gate is SYMMETRIC (round-1 fix): empty stamp ⇒
  memoryless AND no-writes. The degenerate family is REAL: the
  register-auto-login path (CreateNewUser logs a fresh user in WITHOUT
  AuthenticateUsing ⇒ no stamp) — the contract degrades to an honest
  today-only session. All four pages are auth+role-gated, so an
  unauthenticated render is NOT reachable; the register auto-login is
  the only reachable empty-stamp case.
- `jumpToToday` (class) keeps `this.save()` (:340) — "TODAY-click saves
  today's position" for #2.
- The old localStorage keys become unread garbage in this mode; no
  cleanup (honest minimalism — no cross-pollution: different key names).
- PHP speake: phpstan/pint touch nothing here (ui-common = JS).

## D3 — the page wiring (4 blades, php-blade only)

All 4 pass the stamp through `@json(session('login_week_stamp'))`:

```js
/* each ctor gains ONE argument (options) — the same 2-field shape */
const weekNav = new WeekNavigator(MockData.semester, weekData, …
    …, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) });
```

- **my-timetable** (:133) + **student-my-timetable** (:85): their
  `weekNav.load()` stays; in session mode it reads ONLY the stamp-scoped
  store — the cross-login localStorage memory dies by construction.
- **venue-timetable**: NO NEW WIRING (round ‑1 block fold): its
  `weekNav.load()` ALREADY exists at **:766** (before
  `populateWeekSelect` :769; wired since the 2026-10-09 venue SDD — the
  original brief's 'never loads' was a missed read slice) — it becomes
  stamp-scoped by D2 alone; its today family (the U2a client
  `currentWeekIndex()`) stays the no-position default.
- **cohort-timetable** (:184–185): ONE gap only — its
  prev/next/select handlers (:399/:405/:411) call the CLASS methods,
  which ALREADY save (ui-common :353/:367/:384) — so the missing piece
  is its **first-ever `weekNav.load();`** after the `_currentWeek` init,
  landing BEFORE the first `populateWeekSelect` (:519) and the first
  `buildTimetable()` (:534/:553) — the boot's first paint must consume
  the restored `_currentWeek`. Its goToday (:416 → the class
  `jumpToToday`) already saves.

## D4 — the priority chain per page (frozen)

| Landing class | Stands on |
|---|---|
| First visit of the login session (no key for this page) | TODAY (the page's own today family) |
| In-session revisit with a saved position | THAT page's latest saved version (auto-nav) |
| After logout + re-login (a new stamp) | TODAY for all 4 (the old keys' stamp mismatches) |
| Not logged in (no stamp) | TODAY (degrades honestly) |

## D5 — verification vehicle

NEW `tests/week-memory-until-logout.spec.ts` (staff-5770).

PLAYWRIGHT SESSION-STORAGE FACTS the implementer holds (round ‑1):
(a) sessionStorage SURVIVES same-page navigations/reloads (a reload =
a valid 'coming back to page A' proxy); (b) storageState (.auth/*.json)
does NOT carry sessionStorage — every context boots memoryless anyway;
(c) `context.newPage()` hands a FRESH sessionStorage — hopping pages
through a second tab would nil the trip.

The clean-context enforcement = a MARKER-GUARDED once-per-tab add-init
(unconditional sessionStorage.clear() at every document start would
wipe row 2's just-saved keys mid-trip and boobytrap the row into
TODAY):

```js
await page.addInitScript(() => {
    if (sessionStorage.getItem('__wmcleared') === '1') return;   /* first document only */
    sessionStorage.setItem('__wmcleared', '1');
    sessionStorage.removeItem('weekmem-weekNav-weekSelect@WRONGSTAMP');   /* row-3 seeds */
    ['weekNav-weekSelect', 'venueTimetableWeek', 'cohortTimetableWeek'].forEach(k => localStorage.removeItem(k));
});
```

(row 2's trip runs WITHOUT the seed removal — the storageState boots it
memoryless already; rows 1/3 want the guaranteed-clean state.)

1. **Today-on-fresh-login**: plain `/my-timetable-ui` → assert the
   SELECTOR's value = the page's today (NOT `window.currentWeek` — the
   restored-load pages leave the window desync DOCUMENTED on
   my-timetable; DO NOT fix the desync here) ✓ and the venue/cohort
   equivalents — the cohort's row FIRST pre-selects the faculty +
   cohort pair (the ui-regression TC-2.x pattern; the weekSelect
   is guidance-disabled until a pinned cohort).
2. **The per-page two-page trip**: on my-timetable nav to a
   runtime-chosen week (≠ today) → `weekmem-weekNav-weekSelect@…`
   exists in sessionStorage → go to `/venue-timetable-ui` → the venue
   stands on ITS today (no inherit!) → back to my-timetable (reload =
   the "coming back" proxy + the honest "go to page B" hop) → the
   selector = page A's saved week (version 1) → nav again → reload →
   the selector = version 2 ✓.
3. **The stamp-rebind forget** (the logout proxy without a UI logout):
   seed `weekmem-…@WRONGSTAMP` → the landing = TODAY (the rebind
   contract: a new login mints a new stamp; an old-session key matches
   nothing) ✓. Recorded explanation in the row comment: the real
   logout path invalidates the server session → a re-login mints a new
   stamp → the identical mismatch fires (D1's construction).
4. **The today-click saves**: mid-session on my-timetable click
   `#todayBtn` → the stored value becomes today's index (the #2 trigger
   family's own receipt).

Regression set: `default-pin` 3 (the arrangement = untouched lane),
`replace-now`, `venue-intent-modal` (C2 = noise),
`venue-cross-week-handoff` 2, `session-arrival-pin-guard` 3 (its
`arrangementWeek` receipts = another lane), `venue-timetable.spec.ts`,
AND `ui-regression.spec.ts` — its TC-2.x VENUE row (Today-click →
reload → a localStorage slot assert) BREAKS under the change → UPDATE
that row's expectation to the session key; its other three rows
reference keys that never existed (pre-existing staleness — LEAVE,
report as such in the run receipt; not this change's nuisance). GATES: the PHP delta (D1 stamp) ⇒
`composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G`
+ the phpunit family 230 (a PHP file touched — the standing rule) + the
Playwright battery. Stale-cache ritual + re-auth if needed.

## D6 — the STUDENT page's live row (USER DECIDED 2026-10-20: the
in-spec walk)

No student auth state exists (`auth.setup.ts` covers the three staff
logins; staff get 403 on `/student-my-timetable-ui`). The user chose the
in-spec dance — **logout from the staff session, then login as the demo
student INSIDE the spec** (no auth.setup change):

1. fresh context boots as staff-5770 on `/my-timetable-ui` → nav to a
   runtime-chosen week (≠ today) — the staff session saves its version;
2. the robot runs the REAL logout path (the app's own Logout action via
   the UI) → redirected to the login screen;
3. the robot logs in via `/login/student` as the seed student
   (`25RSD0001` + the demo password documented in
   `tests/Feature/TimetableSeedInvariantsTest` T-6 — look it up in the
   seed test at apply, never paste passwords into artifacts); the
   `LoginResponse` lands students on `/student-my-timetable-ui`;
4. assert her page stands on TODAY (a NEW login session = a NEW stamp
   = the staff tab's leftover sessionStorage keys match nothing — the
   until-logout law PROVEN LIVE in one walk, cross-logout);
5. her page: nav to another week → save; the same-walk receipts as the
   staff rows (today-default → save → resume).

Move-parts worth knowing (the reason this dance was parked before): the
logout needs the REAL UI (the button → the action — no client shortcut;
misses nothing here because the SAME flow a human uses is what the test
runs), and the login form binds `login_type` per page (the student
login at `/login/student` posts it natively ✓ the LoginResponse handles
the redirect ✓). No DB writes anywhere in the walk (a session token
only; TC70 untouched).

## D7 — skipped batches

Condensed single-round batch review (explore-brief baseline; the
proposal/design/tasks together — the pattern of the last four frozen
rounds). `specs/` skip (≤2h family change; the priority chain + the
mechanism live here).

## S-history addendum (apply-era, 2026-10-23)
- **M5 mystery cornered (OpenCode follow-up)**: inside a `test.use({ storageState })` file, `browser.newContext()` INHERITS the file-level storageState — the dance's "bare" context booted with the live 5770 session, so the 4363 login was a double-login over an existing session and landed on the old identity's intended /dashboard (the "Welcome (5770)" hang). Fix: the dance context = `newContext({ storageState: { cookies: [], origins: [] } })` (explicitly empty jar). Spec 8/8 (the setup 3/3 + M1–M5); M5 = ~10–11 s. The `cookie: NONE` Playwright request capture = an instrumentation artifact, not app behavior. Fact for future specs: a genuinely bare context in this repo's tests ALWAYS declares the empty storageState explicitly.
- **T5's cohort quirk retained by contract**: the faculty-switch re-anchor ("a cohort switch re-lands TODAY") is the cohort page's own deliberate D3-family rule (the `onCohortChange` tail); the week-memory law's "revisit = resume" applies per page, not after a switch there.
