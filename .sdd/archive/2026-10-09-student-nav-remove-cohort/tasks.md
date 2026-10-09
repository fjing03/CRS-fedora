# Tasks — student-nav-remove-cohort

Frozen baselines: proposal.md (R1 PASS), design.md (R1 PASS). Each task ≤ 2h.

## T1 — Nav whitelist
- [x] `partials/ui-nav-bar.blade.php`: delete line 12 (student `cohort-timetables` entry). Line 17 + route `nav` key untouched.

## T2 — Route gate
- [x] `routes/web.php`: `/cohort-timetable-ui` mw `['auth']` → `['auth', 'role:lecturer']`.

## T3 — Feature tests
- [x] `RouteGateMatrixTest.php`: docblock buckets; `/cohort-timetable-ui` stays in `ALL_ROUTES`; add to `LECTURER_ONLY`; delete L108 + L119 special-cases (L120 survives).
- [x] `NavIdentityTest.php:102`: `assertDontSee('href="/cohort-timetable-ui"', false)`; L99 comment "three" → "both".

## T4 — Playwright spec
- [x] `tests/nav-identity.spec.ts` test 2: whitelist loop 3 → 2; cohort link added to the absent list; direct student visit to `/cohort-timetable-ui` must not render the cohort page (403 / no cohort h1).

## T5 — Changelogs
- [x] `cohort-timetable-ui-changelog.md`: 2026-10-09 entry — page becomes lecturer/PL-only (nav + route gate), FR 1.2 intact via Student My Timetable.
- [x] `auth-wiring-changelog.md`: student nav whitelist 3 → 2 entry.
- [x] `student-my-timetable-ui-changelog.md`: one-line postscript pointing at the auth-wiring entry.

## T6 — Gates (design §8)
- [x] Guarded server refresh (standalone `pkill -f "[a]rtisan serve" || true` + `rm -f storage/framework/views/*.php`; never `pkill -9 php`).
- [x] `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` 0 · `php artisan test` all green (129 + updated).
- [x] Playwright: nav-identity 3/3 · timetable-wiring 5/5 · venue-db 4/4.
- [x] Records-intact: no DB writes; no migration/seed files in the diff.

## T7 — Commit
- [x] `feat(nav): drop Cohort Timetables from the student side — nav whitelist + route gate` (partial + routes + tests + changelogs).

## T8 — Verify & archive
- [x] Verify review → archive to `.sdd/archive/` → `docs(sdd): archive student-nav-remove-cohort`.
- [x] NO push without explicit user authorization.
