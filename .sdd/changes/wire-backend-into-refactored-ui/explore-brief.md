
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

---

## Post-3a queued addendum — 2026-10-08 (standing rule for Slice B/C + all future upstream merges)

**Real-records guard (user-approved queue):** the user will insert REAL database records (CSV + MD sources) before Slices B/C resume. Two standing obligations feed this change's proposal/design and every future upstream-merge change:

1. **Records-intact check (mandatory, every upstream merge):** capture row counts for ALL tables (esp. user-supplied: users/lecturers/students/cohorts/class_sessions/venues/modules + any new real-record tables) BEFORE and AFTER the merge; assert equality. Any drift = the merge touched data paths it must not → stop and inspect. Cheap `psql` count snapshot + diff; goes into the merge change's tasks as a gate.
2. **No re-seed on the demo DB:** after real records land, `php artisan migrate:fresh --seed` on `class_replacement` is FORBIDDEN (wipes user data). The testing DB (`class_replacement_testing`) is unaffected — Feature tests seed their own copy per test. Import must ship as a re-runnable script (source CSV/MD kept in-repo) so a reset can rebuild.

*(Addendum vehicle follows the post-Wave-1 precedent above; to be absorbed when this change's artifacts are unfrozen for B/C — together with the `UpcomingReplacements` → `ReplacementHistory` rename unfreeze.)*

## Addendum — 2026-10-08 (venue page pre-empted by standalone change)

The `/venue-timetable-ui` page (Slice B's `VenueTimetable` component, design route
table) is being implemented by the standalone SDD change `venue-timetable-db`
(READ path: DB-backed grid/summary/details; booking write path stays with Slice B).
When this package's Slice B tasks are unfrozen: mark the venue-timetable component
task ABSORBED by that change; the booking affordances it deliberately disabled
become Slice B's write-path scope. Records-intact guard + no-re-seed rules above
remain standing.
