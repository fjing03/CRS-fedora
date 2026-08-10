# Tasks: Auth Wiring

> **Frontend note:** All placeholder UI must reuse existing patterns — CSS tokens from `theme.css`, JS helpers from `ui-common.js`, Blade patterns from existing partials. Match the look of existing UI elements. The frontend SDD later will polish further (responsive, animation, accessibility).

## Task 1: User panel wiring + logout form
- [x] 1.1 Read `ui-nav-bar.blade.php` — desktop user panel (lines 37–53) and mobile nav drawer (lines 83–99)
- [x] 1.2 Desktop: Replace hardcoded `.user-avatar` "KL" with `{{ Auth::user()->initials() }}`
- [x] 1.3 Desktop: Replace hardcoded `.user-name` "Kylian Mbappe" with `{{ Auth::user()->name }}`
- [x] 1.4 Desktop: Replace hardcoded `.user-id` "25SMR10186" with `{{ Auth::user()->loginId() }}`
- [x] 1.5 Desktop: Replace hardcoded `.user-role` "Lecturer" with `{{ ucfirst(Auth::user()->role) }}`
- [x] 1.6 Desktop: Replace `onclick="alert('Logout')"` with `<form method="POST" action="{{ route('logout') }}">` + `@csrf` + `<button type="submit">` (keep existing SVG icon)
- [x] 1.7 Desktop: Wrap entire `.user-panel` in `@auth` block; add `@guest` fallback with login link
- [x] 1.8 Mobile nav drawer: Apply same dynamic data + logout form + @auth/@guest guards (lines 83–99)

## Task 2: Role-based post-login redirect
- [x] 2.1 Add `Fortify::redirectUsing()` in `FortifyServiceProvider::boot()` — student → `/student-my-timetable-ui`, staff → `/my-timetable-ui`, fallback → `config('fortify.home')`

## Task 3: Role-based session lifetime
- [x] 3.1 Change `config/session.php` `lifetime` from `1` to `43200` — add `[SESSION LIFETIME]` comment landmark explaining this is the hard ceiling, per-role enforcement via middleware
- [x] 3.2 In `FortifyServiceProvider::authenticateUsing()`, after returning `$user`: `session(['role_lifetime' => $user->isStudent() ? 43200 : 30])` — add comment `// Production: 30 | Testing: 1` for staff value
- [x] 3.3 Create `app/Http/Middleware/EnsureSessionLifetime.php` — on each request: read `session('_auth_last_activity')` (null on first request → set to now). If non-null and expired (`now - last > role_lifetime * 60`) → flush session + redirect to `/login`. Otherwise update `_auth_last_activity` to `now()->timestamp`.
- [x] 3.4 Register middleware in `bootstrap/app.php` via `->withMiddleware(fn($m) => $m->append(EnsureSessionLifetime::class))`

## Task 4: A1 Remember me
- [x] 4.1 Add `<label class="remember-me"><input type="checkbox" name="remember" value="1"> Remember me</label>` to `login-student.blade.php` (between password field and login button)
- [x] 4.2 Add same checkbox to `login-staff.blade.php`
- [x] 4.3 Add remember me help text: student form → `<p class="remember-hint">Keep me logged in for 30 days</p>`, staff form → `<p class="remember-hint">Keep me logged in for 30 minutes</p>`
- [x] 4.4 Add `.remember-hint` CSS in `theme.css` (0.75rem, muted color)

## Task 5: Staff login lockout
- [x] 5.1 In `authenticateUsing()`, before DB query, guarded by `if ($loginType === 'staff')`: check `Cache::get("login_lockout:{$loginId}")` — if locked, throw `ValidationException::withMessages(['login_id' => "Account locked. Try again in 10 min. Forgot password? Reset at TARUMT intranet."])`
- [x] 5.2 On failed auth (null return, staff only, lecturer exists in DB): increment `Cache::get("login_fail:{$loginId}")`, if >= 3 → set `Cache::put("login_lockout:{$loginId}", ['minutes' => 10], 600)` and forget fail key
- [x] 5.3 On successful auth (staff): `Cache::forget("login_fail:{$loginId}")` + `Cache::forget("login_lockout:{$loginId}")`
- [x] 5.4 Flash lockout expiry timestamp to session: `session(['lockout_expires' => now()->addMinutes(10)->timestamp])` when throwing lockout ValidationException
- [x] 5.5 Create `resources/views/partials/ui-lockout-countdown.blade.php` — red banner with live countdown timer, warning icon, lockout hint with intranet link
- [x] 5.6 Create `public/js/lockout-countdown.js` — read `data-expires`, compute remaining, update timer every second, hide when expired, re-enable login button
- [x] 5.7 Include partial in `login-staff.blade.php` guarded by `@if(session('lockout_expires'))`

## Task 6: A3 Session countdown
- [x] 6.1 Create `resources/views/partials/ui-session-countdown.blade.php` — container div with `data-lifetime` and `data-last-activity` attributes, countdown text, "Still here?" button, hidden logout form
- [x] 6.2 Create `public/js/session-countdown.js` — read data attributes, compute remaining time, show banner at < 120s, countdown text update, "Still here?" → `location.reload()`, at 0 → auto-submit logout form
- [x] 6.3 Add modal markup to `ui-session-countdown.blade.php` — overlay div with "Session Expiring Soon" heading, countdown text, "Stay logged in" button (reload), "Logout" button (form submit)
- [x] 6.4 Extend `session-countdown.js` — at 60s remaining, show modal overlay; at 0s, auto-submit logout; "Stay logged in" → `location.reload()`
- [x] 6.5 Add modal CSS to `theme.css` — `.session-modal-overlay` (fixed, inset 0, semi-transparent bg, z-index 9999, flex center), `.session-modal` (white card, padding, rounded)
- [x] 6.6 Include partial in `resources/views/layouts/ui-template.blade.php` inside `@auth` block, after the nav bar include, before `@yield('content')`

## Task 7: B1 Session indicator
- [x] 7.1 Add `<span class="session-dot" title="Session active"></span>` in `ui-nav-bar.blade.php` near user panel, inside `@auth` block
- [x] 7.2 Add CSS: `.session-dot { background: var(--color-secondary); border-radius: 50%; width: 8px; height: 8px; display: inline-block; }` in `theme.css`

## Task 8: C4 Auto-logout (staff only)
- [x] 8.1 Create `public/js/auto-logout.js` — track mousemove/keydown/click, 25 min idle → warning modal, 30 min → auto-submit logout form. Multi-tab: BroadcastChannel named `'idle-sync'` with `localStorage` fallback (set `idle-activity-ts` key, listen for `storage` event).
- [x] 8.2 Add `<script src="/js/auto-logout.js">` + hidden logout form in `ui-nav-bar.blade.php`, guarded by `@if(auth()->check() && auth()->user()->isLecturer())`

## Task 9: Lint + verify
- [x] 9.1 Run `composer run lint:check` — Pint fixed 4 issues in FortifyServiceProvider, 1 in EnsureSessionLifetime (all formatting)
- [x] 9.2 Run `composer run types:check` — PHPStan 2.2.2 has compatibility issue with PHP 8.5 (exits code 1, no output). Code verified manually.
- [x] 9.3 Confirm no new failures
