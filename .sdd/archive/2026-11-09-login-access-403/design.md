# design — `login-access-403` (2026-11-07)

## D1 — one map for "where does this role land"

New `App\Support\DefaultPage`:

```php
DefaultPage::for(?User $user): string   // student → /student-my-timetable-ui
                                        // lecturer (PL included) → /my-timetable-ui
                                        // guest / unknown role → /
```

Three call sites read it — the login response, the `guest` middleware target, the 403 page's
"Go Back" — so they cannot drift. `LoginResponse`'s two hardcoded strings were the only copy
before this change.

## D2 — the login pages become `guest`-only

`routes/web.php`: `->middleware('guest')` on both routes. `bootstrap/app.php`:
`$middleware->redirectUsersTo(fn (Request $request) => DefaultPage::for($request->user()));`

Why the framework middleware rather than a new class: it is the same mechanism Fortify already
uses on `/login`, so a signed-in user opening any login-shaped URL behaves identically. Note
Fortify also applies `guest` to the password-reset views — those now land on the role page
instead of `/`, which is the intended reading of "send them to their correct default page".

The `role` middleware and `CheckRole` are untouched: guests still get the login redirect from
`auth` (asserted), the wrong role still gets `abort(403)`.

## D3 — the 403 page

- `.error-title { color: #fff; }` (the user's ruling).
- `Go Back` → `{{ \App\Support\DefaultPage::for(auth()->user()) }}`: staff → `/my-timetable-ui`,
  student → `/student-my-timetable-ui`, guest → `/` (unchanged, though a guest cannot reach 403).

## D4 — verification plan

PHPUnit: guests see both pages; each role is redirected from both pages and from Fortify's
`/login`; the map itself; staff/student 403 bodies carry the right href; the `.error-title`
rule's colour is white; a guest never sees 403. Playwright (real browser, both roles): landing
pages, the three login URLs, the 403 pages and a real "Go Back" click.
