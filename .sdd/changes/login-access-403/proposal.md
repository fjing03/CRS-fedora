# proposal — `login-access-403` (2026-11-07, user brief `Login Access Control & 403 Error Page.md`)

## Two asks

1. **BUG** — a signed-in user can still open `/login/staff` and `/login/student`. They must be
   sent to their role's default page instead (staff → staff page, student → student page).
2. **UI** — on the 403 page: the "Access Denied" title becomes white, and "Go Back" stops
   pointing at a bare `/` — it returns the user to their own default page.

## What exists today (measured, not assumed)

| Piece | Where | Today |
|---|---|---|
| the two login pages | `routes/web.php` | plain closures, **no middleware** → reachable while signed in |
| guest → login redirect | `bootstrap/app.php` `redirectGuestsTo` | `/login/student` |
| role → default page | `app/Http/Responses/LoginResponse.php` | hardcoded `/student-my-timetable-ui` and `/my-timetable-ui` |
| the gate that produces 403 | `app/Http/Middleware/CheckRole.php` | `abort(403)` when the role code differs |
| the 403 page | `resources/views/errors/403.blade.php` | `.error-title` inherits body colour; `.btn-back` is `href="/"` |
| Fortify's own `/login` | `vendor/laravel/fortify` | already `guest`-protected, but its redirect target is the framework default |

## Scope

1. Both login routes gain the framework `guest` middleware.
2. The `guest` middleware's target becomes role-aware (one line in `bootstrap/app.php`) — this
   also makes Fortify's `/login` (and its reset-password views) land on the right page.
3. The role → default-page map moves to **one** class, `App\Support\DefaultPage` (staff /
   student / guest), used by the login response, the guest redirect and the 403 page.
4. The 403 page: `.error-title { color: #fff; }` and `Go Back` → `DefaultPage::for(auth()->user())`.

No change to the role gates themselves, to any other page, to authentication, or to the
password/lockout logic. Guests keep both login pages.

## Acceptance (the brief's list)

1. Signed-in staff cannot open either login page → staff default page.
2. Signed-in student cannot open either login page → student default page.
3. Staff on `/student-my-timetable-ui` → the 403 page (unchanged).
4. Student on a staff-only page → the 403 page (unchanged).
5. The "Access Denied" title is white.
6. "Go Back" goes to the right default page for each role.
7. Guests can still reach both login pages.
