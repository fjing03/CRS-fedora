# explore-brief — Student side system health check (2026-11-08)

> Investigation only (`/sdd-explore`). **No code, CSS, DB, config or data was changed.**
> UI evidence comes from the disposable testing DB (`:8030`, seeded) because the demo DBs are
> read-only for agents; **data** evidence comes from read-only SQL + `tinker` (`buildEventsByWeek`
> replayed on the demo DB, no writes).

## What was checked

Student pages: `/student-my-timetable-ui`, `/replacement-history-ui`.
Backend: `App\Livewire\StudentMyTimetable`, `App\Livewire\ReplacementHistory`,
`App\Concerns\ResolvesTimetableTimeline` (shared with the staff pages),
`public/js/mock-data.js` + `public/js/ui-common.js` (`DateHelper`, `WeekNavigator`,
`computeSummary`, `buildTimetableGrid`), `resources/views/partials/ui-nav-bar.blade.php` +
`ui-notifications-panel.blade.php`, `app/Support/Notifications/NotificationEmitter.php`,
`config/app.php` (timezone), the routes' role middleware, and the demo DB (cohorts, sessions,
requests, exceptions, notifications).

## Confirmed issues

### S1 🔴 "Today" on the student timetable is **5 Oct 2026** (Week 3)

- Every other live page clears the mock-era "today" anchor with `MockData.mockNow = null;`
  (`my-timetable`, `cohort-timetable`, `venue-timetable`, `replacement-arrangement`,
  `replacement-home`, `request-approval`, `replacement-history` — 7 pages).
  **`resources/views/livewire/student-my-timetable.blade.php` (bridge at :68–76) never does**, so
  `DateHelper.getTodayMs()` (`public/js/ui-common.js:2419`) falls back to
  `MockData.mockNow = new Date(2026, 9, 5)` (`public/js/mock-data.js:62`).
- Measured in a browser (1440×950, testing DB):
  - staff `/my-timetable-ui`, week 7 → day column `Sun 8 Nov 2026` carries the **Today** badge;
  - student `/student-my-timetable-ui`, week 7 (**the correct real current week, 2–8 Nov**) →
    **no** day carries Today;
  - student clicks the **Today** button → lands on **`Week 3 · 5 Oct 2026 ~ 11 Oct 2026`**, with
    `Mon 5 Oct 2026` badged **Today** and 22 `today-cell`s — exactly the reported symptom.
- Blast radius on that page only (all resolve through `getTodayMs()`): the Today button
  (`WeekNavigator.jumpToToday`), the day "today" flag/badge/column, `renderLeadTimeNote`'s
  `fullyPast` and `generateWeekData`'s `today`/`firstBookable` flags. The **landing week is
  correct** (server-side `currentTimelineWeekIndex()`), which is why the page looks right until
  Today is pressed or the "today" column is expected.
- One-line cure (matches the other seven pages): `MockData.mockNow = null;` in that bridge.

### S2 🟡 The server's "today" is UTC; the campus is Asia/Kuala_Lumpur (+8)

`config/app.php:81` → `'timezone' => 'UTC'`. Measured: `now()` = `2026-11-08 05:44` (UTC) while
KL = `2026-11-08 13:44`. For the first 8 hours of every KL day the server's date is the **previous**
day, so:

- the arrival week (`currentTimelineWeekIndex()`) is one week low early on a Monday;
- `WorkingHours::noticeCutoff()` / `isTooSoon()` (FR 2.17, 3 working days) and
  `AutoRejectRequest` (3 working hours) are 8 hours loose — a slot closer than the rule allows can
  pass, and a due auto-reject fires 8 hours late;
- the derived **Completed** boundary (`ReplacementRequestMapper`) flips a day late.
`App\Support\LocalTime::TZ` exists but is display-only. **Not a quick fix:** existing rows are
`timestamp without time zone` written in UTC, so flipping the app timezone mixes old/new data — it
needs its own decision + verification.

### S3 🟡 Replacement History hides cancelled and rejected requests

`App\Livewire\ReplacementHistory::render()` filters `whereIn('status', ['pending','approved'])`.
Demo data, cohort `RSD2(S1)G2`: **approved = 1, cancelled = 1** → the page shows **1 row**; the
cancelled request never appears. The timetable still shows the cancelled class block, so the
student is not misled about the class — but the "why is nothing happening with my replacement"
answer is missing. The staff equivalent (My Replacements) *does* keep cancelled rows (shown as
"no active request"). Whether this is intended should be confirmed with the user.

### S4 🟢 A student notification cannot jump to its week

`NotificationEmitter` writes student rows with `link = '/student-my-timetable-ui'` (no query).
Neither student page reads `?week=`, and no student page implements the `notif-hl` highlight the
staff pages use. A "class cancelled, week 14" notice therefore lands the student on the current
week with no jump and no flash. (Both *timetable* pages ignore `?week=` — staff's included — so
this is a shared gap, not a student-only one.)

### S5 🟢 Tidying

- `StudentMyTimetable` passes `notifCount => 0` to the layout; nothing consumes it (the bell island
  computes its own unread total). Dead parameter.
- The legacy mock template `ui-design-templates/student-my-timetable-UI-design-template.blade.php`
  is still served when `APP_MOCK_FALLBACK=true` (default `false`) — it would render mock data,
  including the 5 Oct date. Not active; noted so nobody flips the flag blind.

## Verified working (no change proposed)

- **Data linkage:** every student has a cohort; every cohort's `student_count` equals its real
  student rows; the DFT student's 11 sessions all belong to `DFT2(S1)G1`.
- **Cancelled classes:** `class_exceptions` → red block with the reason label (RSD student,
  session 24 week 7 `medical_leave`).
- **Approved replacements:** land in the **slot's** week with the landing venue/time, the original
  date in the note and `requestId` on the event (RSD student: `BMSE2163 P · Fri B010 10:00–11:00`,
  request 27, original `24-Dec-2026`); the replaced occurrence is correctly suppressed
  (exception + approved pair).
- **Pending requests:** the amber block sits in the **proposed slot's** week (DFT student,
  request 28 → week 9, `B111 11:00–13:00`), the origin occurrence keeps the "(Proposed)" hint.
- **Holidays:** week 8 Mon (Deepavali) and week 14 Thu/Fri (Christmas) render the holiday badge and
  red blocks; the summary's Conflicts count matches the real conflict blocks (2 and 4).
- **Summary cards, print button, class-detail modal (view-only), keyboard/swipe handlers, bell +
  panel** (`"You're all caught up"` when empty), **empty state**, **responsive 390 px** (no
  horizontal overflow on either page), **access control** (student → staff page = 403 with the
  student "Go Back"; student → login pages = redirected), **no JS console errors**.
- **Role differences that are correct:** students have no Arrangement / Approval / Venue pages and
  no write actions (FR 1.5–1.8 read-only) — a reasonable role split, not a bug.

## Recommended fix order (for the user's approval)

1. **S1** — one line, removes the reported bug. (Highest impact, lowest risk.)
2. **S3** — decide: should a cancelled/rejected replacement appear in the student history?
3. **S2** — schedule the timezone decision properly (config + data semantics + tests).
4. **S4/S5** — polish; bundle with a later student-side pass.

## S1 — APPLIED (2026-11-08)

Fix: one line in the student timetable's data bridge, matching the other seven pages —
`MockData.mockNow = null;` (`resources/views/livewire/student-my-timetable.blade.php:77`).

Verified in the browser (testing DB, 1440×950):
- landing `Week 7 of 14 · 2 Nov 2026 ~ 8 Nov 2026` with **`Sun 8 Nov 2026` badged Today**;
- pressing **Today** keeps Week 7 (it used to jump to Week 3 · 5 Oct 2026);
- screenshot `/tmp/opencode/student-today-fixed.png`.

Receipts:
- `tests/student-today.spec.ts` (read-only, signs in as the seeded student): asserts the page
  clears the anchor, exactly one day column carries Today and it shows the REAL date, and the
  Today button keeps that day. **1/1 green**; red-check (line removed) fails with
  *"the student page must clear MockData.mockNow"*.
- `tests/Feature/MockNowClearedTest.php` (new invariant): every live Blade that assigns
  `MockData.semester` must also clear the anchor — 8 bridges + the anchor sanity check =
  **9/9**; red-check with the line removed fails on `student-my-timetable.blade.php`.
- `composer run lint:check` passed, `phpstan` 0 errors, `view:cache` OK.

S2/S3/S4/S5 remain **open** (untouched).

## S3 — DECIDED: leave as it is (user ruling, 2026-11-08, option A)

The page keeps its **pending + approved** scope. Cancelled and rejected requests stay off the
student history on purpose: the timetable already shows the cancelled class block with its reason,
so the student is not misled about whether the class runs — and the history stays a list of
*outcomes*, not of every attempt. No code change; recorded here and in
`page-changelogs/replacement-history-changelog.md` so a future pass does not "fix" it as a bug.

## S4 + S5 — APPLIED (2026-11-08)

- **S4:** `NotificationEmitter` writes `/student-my-timetable-ui?week=N` for both student fan-outs
  (`approvalNotice`, `classCancelled`); `StudentMyTimetable::mount()` honours `?week=` (1-based;
  junk / out-of-range / absent → today's week). Measured: `?week=9` → Week 9, `?week=14` → Week 14,
  `?week=99` / `?week=abc` / none → Week 7, and the param wins over a remembered week.
  Tests: `StudentTimetableWeekParamTest` 4/4 + the link assertion in `NotificationsBridgeTest`.
- **S5:** the dead `notifCount => 0` layout parameter is gone (nothing read it). The legacy mock
  template note stays as documentation only — `APP_MOCK_FALLBACK` is still `false`.

## Re-exploration (2026-11-08, second pass) — what the first pass missed

The first pass checked the history page's ROW COUNT, not its CONTENTS. Re-reading the brief found
four more issues; two are wrong information shown to students:

- **S10 (fixed):** the Original Slot column reused the replacement's week and day → a week-14
  Thursday class printed as `Week 8 · Fri, 13 Nov`; the Hours card counted the replacement's length.
- **S11 (fixed):** the New Slot time string read the anchor cell only → `1:00 PM ~ 1:30 PM` for a
  13:00–14:00 replacement (duration said 1 hr).
- **S7 (APPLIED 2026-11-08, user's option A):** the page used to open with the week filter on the
  CURRENT week, so a student whose only replacement was in a later week saw 0 rows and 0 / 0 / 0
  cards. It now opens on All Weeks ("Show past" still off; Today and the week arrows still narrow
  it; a saved filter still wins). Measured on both demo students: 1 row and correct cards.
- **S6 (open):** `PAGE_HOME` is `/` on all four timetable pages (staff's included), so the logo
  leaves the app for the public welcome page; `/dashboard` behind it is a bare placeholder.

S10 + S11 receipts: `tests/Feature/ReplacementHistoryRowTest.php` 3/3 (+ red-check fails all 3),
browser receipts on the demo data, pint/phpstan/view:cache clean.

## S8 — APPLIED (2026-11-08)

`partials/notif-row.blade.php` appends the row's own stored `week` to a student row's bare
`/student-my-timetable-ui` link at read time (no data write, no backfill; a new row keeps the week it
already carries; staff rows are untouched). Tests: 3 in `NotificationsBridgeTest` (legacy row gains its
week / new row never double-appended / staff row never gains one), red-check fails on the first. Demo
data receipt: user 112's rows render `?week=7` and `?week=14`, click lands on that week, zero console
errors. Side note: an approved-replacement notice stores the CLASS's week (14) — the row's own body
says "W14" — not the week the replacement actually lands in (8); landing is consistent with the
wording, and changing it to the replacement's week would be a product decision.
