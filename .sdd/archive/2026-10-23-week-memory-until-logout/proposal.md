# Proposal — week-memory-until-logout

## Why (the user's law, their own sample flow)

On the FOUR timetable pages — **my-timetable, cohort-timetable,
venue-timetable, student-my-timetable** — the week should behave the way
the user described (their reworded flow is the contract of record):

- **Session-long memory**: from login until logout.
- **Per page**: page A's saved week belongs to page A; page B always
  STARTS at TODAY within the same session, even when page A already has
  a version.
- **Default = TODAY** on a page's first visit of the login session (and
  after any logout).
- **Save triggers**: prev / next / selector / TODAY-click — every such
  navigation writes that page's version.
- **Revisit = auto-nav to that page's own saved version** ("go back to
  page A → auto-nav to the saved weeks (version 1) → user change week →
  saves it (version 2), and so on").
- **Logout forgets** — every fresh login re-lands all of them on TODAY,
  regardless of the earlier session's weeks.

Verified reality (reviewer round 1): my-timetable and
student-my-timetable already load a memory that **survives logout**
(localStorage — what #4 forbids); the venue **saves AND loads already**
(`weekNav.load()` at :766 — the repo wired it on day one) — its defect =
the STORAGE SCOPE only; the cohort saves via the class methods
(prev/next/select :399+ → ui-common's save()) but **never calls
`load()`**. None of the 4 binds memory to the login session.

## In scope

- Server: mint a per-login marker (`login_week_stamp`) in the Fortify
  login hook — logout (`Logout`) and expiry (`EnsureSessionLifetime`)
  already invalidate the session, so the marker dies with it by
  construction (no client logout hook; expiry-safe).
- `WeekNavigator` (ui-common, ONE shared mechanism): a `sessionScoped`
  ctor option carrying the stamp; in that mode `save()`/`load()` use
  `sessionStorage['weekmem-<key>@<stamp>']` and localStorage is never
  read (my/student's cross-login memory dies by construction).
- The 4 blades' ctor wiring + the one honest gap: the COHORT gains its
  first `load()` call (landed BEFORE its first paint; the saves already
  funnel the class methods); the VENUE gains NO new wiring (its `:766`
  `load()` simply becomes stamp-scoped via the mechanism).
- A Playwright spec locking: today-on-fresh-login, per-page
  independence (a two-page trip), version bump on a second save, and
  the stamp-rebind forget (a tampered/old stamp = today); rows assert
  the weekSelector (never window.currentWeek) and hop pages within ONE
  tab; the today-click trigger saves (per the law's #2).
- `ui-regression.spec.ts`'s TC-2.x venue row: its localStorage slot
  assert becomes the session key (the row stays, expectations updated).
- Dated changelog entries for the touched pages (the student entry
  committed unconditionally — its changelog file exists).

## Explicitly NOT in scope

- The arrangement page (it has its own frozen lane
  `arrangement-earliest-week-default`; NOT one of the 4).
- The earliest-bookable floor on the 4 (superseded — TODAY is their
  default by this law).
- The cohort's faculty-switch "re-anchor to today" quirk (:268) —
  preserved as a deliberate within-page behavior.
- A global week shared across pages (violates the per-page decode).
- Any client logout-hook clear (misses the expiry path — the server
  session rebind is the contract's honest mechanism).

## Impact scope

| File | Change |
|---|---|
| `app/Providers/FortifyServiceProvider.php` | +1 line: mint `login_week_stamp` at the login hook |
| `public/js/ui-common.js` | WeekNavigator: the `options` ctor param + the session-scoped save/load branch (the shared mechanism, promoted once) |
| `resources/views/livewire/{my-timetable,cohort-timetable,venue-timetable,student-my-timetable}.blade.php` | the ctor wiring (+ the venue's one `load()`; the cohort's page-side save family + `load()`) |
| `tests/week-memory-until-logout.spec.ts` (NEW) | the law's rows (today / per-page independence / version bump / stamp-rebind forget) |
| `page-changelogs/my-timetable-changelog.md`, `venue-timetable-ui-changelog.md`, `cohort-timetable-changelog.md` (+ student on receipt) | dated entries |

## Success criteria

- Fresh login (or a stamp mismatch) → all 4 pages stand on TODAY (their
  own today family: the server's `currentTimelineWeekIndex()` for my/
  cohort/student, the client's `currentWeekIndex()` for the venue).
- Two-page trip in one session: page A navs+saves → page B starts at
  TODAY → back to A → A resumes ITS version → a second nav → version 2.
- Logout/expiry → a re-login → all 4 stand on TODAY (the old stamps'
  keys ignored).
- The existing families stay green: `default-pin` (its T18 rows run on
  the arrangement — outside the 4 — unchanged), `replace-now`,
  `venue-intent-modal`, `venue-cross-week-handoff`
  (`--grep-invert "TC70"`).
