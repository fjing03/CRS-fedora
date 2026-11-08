# review-log — `login-access-403` (2026-11-07)

## What was found before changing anything

- `/login/staff` and `/login/student` were plain route closures with **no middleware**, so a
  signed-in user could open them (the reported bug). Fortify's own `/login` was already
  `guest`-protected, but its redirect target was the framework default `/`.
- The role → default-page pair lived in ONE place (`LoginResponse`, two hardcoded strings), so
  introducing a third consumer (the 403 page) would have created a second copy.
- `CheckRole` (the 403 producer) and the `auth` middleware were correct and are untouched: a
  guest is redirected by `auth` to `route('login.student')`, and a wrong role gets `abort(403)`.
  (A guess that the guest reached `CheckRole`'s `redirect('/')` arm was wrong — the test run
  corrected it: `tests/Feature/AccessDeniedPageTest::test_guests_are_still_redirected_…`.)

## Changes

As in `proposal.md` §Scope, plus one follow-up the verification forced (below).

## The follow-up: the white title vs the light card

The user asked for a white "Access Denied" title. Measured in a real browser:

| Viewer | Card | Title |
|---|---|---|
| staff (cookie `login_type=staff`, dark card) | `rgb(42,44,46)` | white — as asked, readable |
| student (`light` class) | pale page background (the card's `background: var(--color-surface)` resolves to nothing on this standalone page) | **white on white → invisible** |

User's decision (option A): keep white where the card is dark and let the light card carry a
dark title — `.light .error-title { color: #1c1e1f; }`. Re-measured: the student's title is now
`rgb(28,30,31)` and readable; the staff's is still `rgb(255,255,255)`.
(A second, pre-existing cosmetic quirk stays: the 403 page loads no theme stylesheet, so the
"light" card is effectively transparent and its message line is faint. Out of scope; recorded
here so it is not mistaken for this change's doing.)

## Receipts

| Check | Result |
|---|---|
| `--filter 'LoginAccessControl'` | 8 / 8 |
| `--filter 'AccessDeniedPage'` | 5 / 5 (the white rule + the light-card override) |
| `--filter '…|AuthenticationTest|MiddlewareTest|RouteGateMatrix|DashboardTest'` | 33 / 33 |
| full suite (before the last CSS line) | 435 tests, 432 passed, **3 errors** = the known Saturday-only fixtures (Sunday vs `day_of_week 0–5` CHECK), identical on untouched code |
| `composer run lint:check` | passed |
| `phpstan analyse --memory-limit=1G` | 0 errors |
| `php artisan view:cache` | cached successfully |

**Browser (real Chromium, `:8030`, testing DB):**

```
GUEST    /login/staff  -> 200 stays     GUEST /login/student -> 200 stays
STAFF    landed /my-timetable-ui        STUDENT landed /student-my-timetable-ui
STAFF    /login/staff  -> /my-timetable-ui        STUDENT /login/staff -> /student-my-timetable-ui
STAFF    /login/student-> /my-timetable-ui        STUDENT /login/student -> /student-my-timetable-ui
STAFF    /login        -> /my-timetable-ui        STUDENT /login -> /student-my-timetable-ui
STAFF    403 on /student-my-timetable-ui, title rgb(255,255,255), Go Back -> /my-timetable-ui
STUDENT  403 on /replacement-arrangement,  title rgb(28,30,31),  Go Back -> /student-my-timetable-ui
```

Both "Go Back" buttons were really clicked and landed on the right page.
Screenshots: `/tmp/opencode/403-staff.png`, `/tmp/opencode/403-student.png`.

## Not done (on purpose)

- No change to `CheckRole`, `EnsureSessionLifetime`, lockout/limits, or any other route.
- The 403 page's missing theme stylesheet was left alone.
