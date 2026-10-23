# Explore brief — week-memory-until-logout

Source: the user's words (2026-10-20, reworded version is AUTHORITATIVE —
copy nearly verbatim below) applied to **the 4 timetable pages**:
my-timetable, cohort-timetable, venue-timetable, student-my-timetable.
(The arrangement page is NOT one of the 4 — its own already-frozen lane
`arrangement-earliest-week-default` stands untouched.)

## The user's law (their reworded sample flow, authoritative)

> the week memory is session long, from the user login until they logout.
>
> user log in --> at page A, navi to TODAY's week (default) --> user
> prev/next/select week or click TODAY --> saves it **(version 1)** -->
> go to page B = navi to TODAY's week (default) [user pre/next/select
> week or click TODAY --> saves it] --> go back to page A --> auto-nav
> to the saved weeks **(version 1)** --> user change week --> saves it
> **(version 2)**, and so on --> until log out, then forgot the memory.

Decoded semantics (all user-vouched):

1. **Memory = per-PAGE.** Page B never inherits page A's week; page A's
   own saved version (latest by page A's saves only) survives page-A
   exits within the login session, and a revisit auto-navs to it.
2. **Default = TODAY** — the page's own today family — on a page's FIRST
   visit of the login session (and after logout, always).
3. **Save triggers = prev / next / select / TODAY-click** — each such
   navigation writes that page's current version.
4. **Logout forgets** — after a logout (or expiry) the memory must not
   survive: a fresh login re-lands EVERY page on TODAY regardless of
   what the earlier session was doing.

## The fact map (reads, 2026-10-20)

- **"TODAY" differs per page in WHERE it is computed from** — but is ONE
  family: my-timetable (`MyTimetable.php:26` `$this->week =
  currentTimelineWeekIndex() + 1`), student (`StudentMyTimetable.php:27`
  same), cohort (`CohortTimetable.php:86` `currentWeek` =
  `currentTimelineWeekIndex()`) all mint today SERVER-side
  (`ResolvesTimetableTimeline.php:123`); the venue computes today
  CLIENT-side (`currentWeekIndex()`, the U2a declared bridge). The law's
  default = each page's EXISTING today (no clock unification).
- **Current memory wiring (reviewer-corrected round 1)**: my-timetable
  (load :254 + class-method saves) and student (load :146 + saves) read a
  **localStorage** key that survives logout — the exact thing #4 forbids;
  venue **saves AND loads already** (saveWeek :743 wired + `weekNav.load()`
  at :766 before `populateWeekSelect` :769, wired since the 2026-10-09
  SDD) — its only defect = the STORAGE SCOPE (localStorage, cross-login);
  cohort: its prev/next/select handlers (:399/:405/:411) call the CLASS
  methods which internally **save** to `localStorage['cohortTimetableWeek']`
  (ui-common :353/:367/:384) — its only gap = **`load()` is never
  called** (no restore; a pure-view landing). The goToday bindings save
  where the class method is used (:416).
- **The Today button exists and is bound on all 4 pages**: my/student/
  venue call `weekNav.initTodayBtn()` (the class `#todayBtn` → class
  `jumpToToday`, which SAVES at ui-common :340); the cohort binds its own
  `goToday` → `weekNav.jumpToToday()` (:416, saves too). So "click TODAY
  → saves" is already live once the storage is re-scoped.
- **Login/logout architecture**: login = Fortify
  (`FortifyServiceProvider.php` — the :123 `AuthenticateUsing` hook is
  where a server-side per-login marker gets minted; the same hook's
  `login_type` demonstrably survives Fortify's post-login session
  regeneration ✓ the same proof shape); logout = the `Logout` action
  (`Session::invalidate()`) and expiry = `EnsureSessionLifetime`
  (`invalidate()` too) — BOTH kill the server session, so any
  session-bound marker set at login dies at logout BY CONSTRUCTION
  (expiry included; no client logout hook needed).
  **The register/auto-login edge**: registering (CreateNewUser)
  auto-logs-in WITHOUT hitting `AuthenticateUsing` → no stamp → the
  mechanism degrades to a today-only session (load gate) AND the save
  gate (round-1 🔴4 fold) keeps writing NO un-stamped junk.
- **Harness facts**: no student Playwright auth state exists
  (`auth.setup.ts` covers staff 5770/4363/5425) — staff get 403 on
  `/student-my-timetable-ui` (`auth-full.spec.ts`), so a student live
  row needs a real student login. RESOLVED (reviewer round 1): the demo
  student is a seed invariant (`25RSD0001`, `TimetableSeedInvariantsTest`
  T-6 + the login page placeholder; the repo's documented demo password
  convention — look the credential up in the seed test at apply, do not
  paste it in artifacts), and the student's `role_lifetime` = 30 days
  (43200 min) so expiry never bites the student legs. Option B (a live
  student row) = feasible without touching auth.setup (an in-spec login
  flow); option A remains the cheap default.
- The stale-cache ritual + `--grep-invert "TC70"` standing guards apply.

## Rejected approaches

1. **Keep localStorage memory across logins** (today's my/student shape)
   — directly violates #4 (logout forgets). REJECTED.
2. **A client logout-hook clear** (wipe the keys before the Logout
   request redirects) — covers the modal path but MISSES the expiry path
   (`EnsureSessionLifetime`'s redirect bypasses every client hook), and
   a crashed/stale tab would leak memory past logout. REJECTED.
3. **One global week shared by all 4 pages** — violates the decoded
   per-page semantics (page B starts at TODAY; page A's version is
   page A's only). REJECTED.
4. **The earliest-bookable floor on these pages** — SUPERSEDED: the
   user's law sets TODAY as the default for the 4 (the earliest floor
   stays the arrangement's own frozen lane). REJECTED for this change.
5. **A login-minted browser cookie as the scope marker** — same stamp
   idea but a cookie rides across tab closes; sessionStorage + a server
   session stamp is the narrower, per-tab, one-honest-owner shape.
   REJECTED (cookie variant).

## Final solution (mechanism)

- **Server**: one line in the Fortify login hook — at every successful
  login, mint `session(['login_week_stamp' => <uniquestamp>])`. Logout
  and expiry `invalidate()` the session → the stamp (and the session's
  right to remember) dies server-side. A fresh login mints a NEW stamp.
- **Shared carrier**: `WeekNavigator` gains a session-scoped storage
  mode (one mechanism, promoted ONCE per the 3+-page duplication rule —
  all 4 pages use the same class):
  - ctor gains the `options` param: `{ sessionScoped: true, loginStamp }`;
  - `save()` in that mode writes
    `sessionStorage['weekmem-<key>@<stamp>'] = week`;
  - `load()` in that mode reads ONLY
    `weekmem-<key>@<stamp>` (a stamp mismatch or absent key ⇒ NO
    position ⇒ the page stands on its own today); localStorage is never
    read in this mode (my/student's current cross-login memory dies by
    construction);
  - the Today/prev/next/select family calls are untouched — they all
    funnel the class `save()`.
- **Page wiring (php-blade only)**: each of the 4 blades ups the
  `sessionScoped` option with `@json(session('login_week_stamp'))`;
  venue adds its FIRST-EVER `load()` call after `_currentWeek` init
  (resume > today > default); cohort adds its first save family + one
  `load()`; my/student keep their load() calls (now stamp-scoped).
- **The old localStorage keys** (`weekNav-weekSelect` for my/student,
  `venueTimetableWeek`, `cohortTimetableWeek`) = dead entries, never
  read by the session mode; no cleanup (honest minimalism).

## Cross-module data flow after

login (Fortify) mints the stamp → page A boots, binds stack `stamp` →
nav → `save()` writes A's version → page B (fresh key) → TODAY → nav →
B's version → back to A → A resumes version N → **logout**
(`Session::invalidate()`) → fresh login (a new stamp) → A/B/C/D all see
a stamp mismatch → TODAY for everyone ✓.

## Known open questions

1. **The student page's live Playwright rows need a student credential**
   (the auth states are staff-only). Options: the user supplies a demo
   student login for the spec; OR the student page is verified by the
   class-level mechanism + a wiring diff receipt (the same one-line
   ctor as the other three). ASK THE USER before apply.
