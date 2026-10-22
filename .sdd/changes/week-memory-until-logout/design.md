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

## D6 — the STUDENT page's verification gap (open, user-decided)

No student auth state exists (`auth.setup.ts` covers the three staff
logins). Options for the apply:
**A.** a collective class-level receipt + the wiring diff (all four
pages carry the identical D3 line — the mechanism is proven on the three
staff rows; the student adds NOTHING new beyond its one ctor arg), or
**B.** the user hands a demo student credential for a real live row.
PRESENT both at apply; the default = A (the honest cheap path), B on the
user's word.

## D7 — skipped batches

Condensed single-round batch review (explore-brief baseline; the
proposal/design/tasks together — the pattern of the last four frozen
rounds). `specs/` skip (≤2h family change; the priority chain + the
mechanism live here).
