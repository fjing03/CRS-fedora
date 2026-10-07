
---

## Post-Wave-1 diagnostic addendum — 2026-10-07 (chat finding, not yet reviewed)

User-reported: logging in as staff `5425` shows the panel as `5770` ("Lim Jia Zheng").

**Diagnosed first-hand (root cause, no session-corruption):**
- Login is real: `POST /login` with `5425` / `Tarumt@2026` → `302 → /my-timetable-ui`; `Lecturer` row exists in DB; session binding correct.
- **`resources/views/partials/ui-nav-bar.blade.php` is fully static mock-shell HTML**: avatar `LJZ`, name `En. Lim Jia Zheng`, ID `5770`, role `Lecturer` hardcoded in BOTH the desktop user-panel (:43 area) and the mobile nav-drawer (:90 area); zero `auth()` reads in the partial.
- Effect: every authenticated user (student, lecturer, PL) sees the same mock identity. Also visible: `public/js/mock-data.js:80 currentUser` carries the same hardcoded identity.

**Scope consequence for this change (candidate fix list):**
1. `ui-nav-bar.blade.php` — wire `auth()->user()` (session answer with name/staff_id/role) or `$activeNav`-injected identity into panel + drawer; avatar initials derived from real name.
2. Confirm every layout that includes the partial receives the identity (rather than reading static defaults).
3. Grep sweep for other hardcoded-identity surfaces (`5770`, `LJZ`, currentUser) once wiring lands.

**Status:** diagnostic only — must feed this change's proposal/design when unfrozen (design:31,52 still names `UpcomingReplacements` → needs the 3-batch unfreeze per design §13 of sync-upstream-fjing-ui).
