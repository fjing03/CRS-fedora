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
