# tasks — `login-access-403` (2026-11-07)

- [x] T1 — read the existing auth/role plumbing (login routes, `CheckRole`, `LoginResponse`,
      Fortify's guest routes, the 403 view) before changing anything.
- [x] T2 — `App\Support\DefaultPage` (staff / student / guest), one map.
- [x] T3 — both login routes gain `guest`; `bootstrap/app.php` makes its target role-aware.
- [x] T4 — `LoginResponse` reads the map (no duplicated strings).
- [x] T5 — 403 page: white `.error-title`, role-aware `Go Back`.
- [x] T6 — PHPUnit: `LoginAccessControlTest` (8 cases) + `AccessDeniedPageTest` (4 cases).
- [x] T7 — gates: focused suites, pint, phpstan, `view:cache`.
- [x] T8 — browser receipt, both roles (landing, login URLs, 403, real Go Back click).
