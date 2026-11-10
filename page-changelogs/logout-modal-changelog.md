## 2026-11-10 — PERMANENTLY REMOVED (user, code cleanup)

The feature is gone for good, on the user's word: *"Logout Modal JS: Permanently remove it."*
The modal had been inactive since 2026-10-18 (the nav buttons already submitted the form
directly), so what was left was dead weight — and a trap for anyone who reinstated it.

Removed:
- `public/js/logout-modal.js` (the whole file: countdown, don't-ask-again flag, button wiring).
- `resources/views/partials/ui-logout-modal.blade.php` (the shell it drove).
- The commented `@include` + the commented `<script src>` in `layouts/ui-template.blade.php`.
- The two commented `onclick="showLogoutModal()"` lines in `partials/ui-nav-bar.blade.php`.
- `tests/logout-modal-test-cases.md` (the manual cases for a feature that no longer exists).

**Logout still works the plain way:** both logout buttons (nav bar + mobile drawer) submit
`#logout-form` directly. Measured on the demo copy: clicking the nav logout lands on
`/login/staff`, zero console errors.

Pinned by `tests/Feature/CodeCleanupRemovalsTest.php` — the files must stay absent and
nothing may point at `showLogoutModal` / `logoutModal` / `logout_no_confirm` again.

# Changelog — Logout Modal

## Files Changed

### `resources/views/partials/ui-nav-bar.blade.php`
| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | Line 49 (desktop logout btn) | Modified | Replaced `onclick="alert('Logout')"` with `onclick="showLogoutModal()"` |
| 2026-08-03 | Line 98 (mobile drawer logout btn) | Modified | Replaced `onclick="alert('Logout')"` with `onclick="showLogoutModal()"` |
| 2026-08-13 | Desktop logout btn | Modified | Logout modal disabled → `onclick="document.getElementById('logout-form').submit()"` (direct logout) |
| 2026-08-13 | Mobile drawer logout btn | Modified | Logout modal disabled → `onclick="document.getElementById('logout-form').submit()"` (direct logout) |
| 2026-08-03 | End of file (line 105) | Added | Hidden logout form: `<form id="logout-form" method="POST" action="{{ route('logout') }}">` + `@csrf` |

### `resources/views/partials/ui-logout-modal.blade.php` (NEW)
| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | Entire file | Created | Modal markup: countdown bar, Cancel/Logout now buttons, "Don't ask me again" checkbox. Styles in `<style>` block. |

### `public/js/logout-modal.js` (NEW)
| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | Entire file | Created | `showLogoutModal()`: localStorage check, mobile drawer close, 5s countdown, auto-submit, button handlers, checkbox handler |

### `resources/views/layouts/ui-template.blade.php`
| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-03 | Line 26 | Added | `@include('partials.ui-logout-modal')` inside nav guard |
| 2026-08-03 | Line 36 | Added | `<script src="/js/logout-modal.js"></script>` after mock-data.js |
| 2026-08-13 | Line 27 | Commented out | `@include('partials.ui-logout-modal')` wrapped in `{{-- ... --}}` — logout modal deemed unnecessary |
| 2026-08-13 | Line 37 | Commented out | `<script src="/js/logout-modal.js"></script>` wrapped in `{{-- ... --}}` |

> **Status: DISABLED (2026-08-13)** — modal partial and JS are commented out in the layout.
> Files (`ui-logout-modal.blade.php`, `logout-modal.js`) kept on disk to allow easy re-enable.
> Re-enable by uncommenting both lines in `ui-template.blade.php` and restoring `onclick="showLogoutModal()"` in `ui-nav-bar.blade.php`.
