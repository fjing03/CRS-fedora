# Tasks — week-memory-until-logout

- [x] T1. `app/Providers/FortifyServiceProvider.php` — mint
  `session(['login_week_stamp' => uniqid('wk', true)]);` at the login
  success family (:123 — design D1's comment verbatim).
- [x] T2. `public/js/ui-common.js` — WeekNavigator: the 6th ctor param
  `options { sessionScoped, loginStamp }`; the `_weekMemKey`; the
  session-scoped branches FIRST in `save()` and `load()` (design D2's
  exact shapes; the shipped localStorage bodies verbatim in the
  localStorage branch; my/student's cross-login memory dies).
- [x] T3. `resources/views/livewire/my-timetable.blade.php` +
  `student-my-timetable.blade.php` — the ctor wiring; their 2-arg ctors
  extend to the 6-arg shape with THREE null placeholders
  (`new WeekNavigator(MockData.semester, weekData, null,
  'weekNav-weekSelect', null, { sessionScoped: true, loginStamp:
  @json(session('login_week_stamp')) })`) — the derived storageKey
  ('weekNav-weekSelect') MUST stay as-is (round-1 💡3: a silent rename
  would flip restore semantics; the per-page invariant for my/student
  rides on role gating — same human, only their role's pages). Their
  `load()` calls stay (now stamp-scoped).
- [x] T4. `resources/views/livewire/venue-timetable.blade.php` — the
  ctor wiring ONLY (NO new load: its `weekNav.load()` already exists at
  :766 before `populateWeekSelect` :769 — it becomes stamp-scoped via
  T2's mechanism; round-1 🔴1).
- [x] T5. `resources/views/livewire/cohort-timetable.blade.php` — the
  ctor wiring + its FIRST `weekNav.load();` after the `_currentWeek`
  init and BEFORE the first populateWeekSelect (:519) and
  buildTimetable (:534/:553) — the first paint consumes the restored
  week (round-1 🔴2). Its page-side saves = ALREADY LIVE via the class
  methods (prev/next/select :399+ / goToday :416) — add NOTHING on the
  write side; the :268 faculty-switch re-anchor untouched.
- [x] T6. NEW `tests/week-memory-until-logout.spec.ts` — the rows per
  design D5 (with the round-1 ⚠ shapes): the MARKER-GUARDED once-per-tab
  add-init (never a bare sessionStorage.clear() — it would kill the
  trip's just-saved keys at every goto/reload); row 1 asserts the
  SELECTOR (never window.currentWeek) and pre-selects the cohort pair
  (TC-2.x pattern) before its assert; row 2 = the two-page trip, one
  tab ONLY (a new tab would hand a fresh sessionStorage and nil the
  trip); row 3 = the wrong-stamp rebind = today; row 4 = the
  today-click saves.
- [x] T7. The gates: `composer run lint:check` +
  `vendor/bin/phpstan analyse --memory-limit=1G` + the phpunit family
  (230 baseline — a PHP file touched) + the Playwright battery EXACTLY
  as design D5 lists (`--grep-invert "TC70"`; C2's fail = the
  documented pre-existing noise). Stale-cache ritual + re-auth if
  needed.
- [x] T8. Dated changelog entries: `page-changelogs/my-timetable-changelog.md`,
  `page-changelogs/venue-timetable-ui-changelog.md`,
  `page-changelogs/cohort-timetable-changelog.md` (the LIVE one — the
  2026-10-20 last-visit-dd entry is its head; NOT the 2026-10-10
  cohort-timetable-ui file), AND
  `page-changelogs/student-my-timetable-ui-changelog.md` (the file
  EXISTS on disk — committed unconditionally, was wrongly hedged before
  round 1), each with the law's summary + the until-logout contract.
- [x] T9. USER DECIDED (2026-10-20): the student page gets a REAL live
  row — the in-spec dance per design D6: fresh-context staff login →
  save a week → the REAL logout → `/login/student` as the seed student
  (`25RSD0001`, the credential read from the seed test at apply) → her
  page lands on TODAY (the cross-logout proof in one walk) → her
  nav+save receipts. No auth.setup change; the logout = the app's own
  UI path; no DB writes (TC70 untouched).
