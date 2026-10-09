# Tasks — student-nav-label-parity

Frozen baselines: proposal.md (R1 PASS), design.md (R1 PASS). Each task ≤ 2h.

## T1 — Whitelist label
- [x] `partials/ui-nav-bar.blade.php:13`: student item 2 label `'Request History'` → `'Replacement History'` (key + href unchanged). Line 19 lecturer literal untouched.

## T2 — Override deletions
- [x] `ui-design-templates/replacement-history-UI-design-template.blade.php`: delete `'navItems' => [...]` from `@extends(...)`; keep `homeUrl`/`activeNav`/`pageKey`.
- [x] `ui-design-templates/student-my-timetable-UI-design-template.blade.php`: same deletion.
- [x] Grep-check: only `layouts/ui-template.blade.php:35` + `ui-nav-bar.blade.php:23` reference `$navItems` afterwards.

## T3 — Playwright parity assertion
- [x] `tests/nav-identity.spec.ts` test 2: insert design §3's snippet between the href assertions (L60-68) and the direct-visit check — bar labels on BOTH student pages + drawer labels = `['My Timetable', 'Replacement History']`.

## T4 — Changelogs
- [x] `auth-wiring-changelog.md`: primary entry (label rename + both override deletions + single-source note + Wave-3b guard).
- [x] `replacement-history-changelog.md`: page inherits shared nav.
- [x] `student-my-timetable-ui-changelog.md`: postscript correcting the stale "Request History" claim.

## T5 — Gates (design §6)
- [x] Guarded server refresh (standalone `pkill -f "[a]rtisan serve" || true` + view-cache clear; never `pkill -9 php`).
- [x] `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` 0 · `php artisan test` all green (129/129).
- [x] Playwright: nav-identity 3/3 · timetable-wiring 5/5 · venue-db 4/4.
- [x] Records-intact: no DB writes; no migration/seed files in the diff.

## T6 — Commit
- [x] `feat(nav): student nav label parity — 'My Timetable' + 'Replacement History' on both student pages` (partial + 2 templates + spec + 3 changelogs).

## T7 — Verify & archive
- [x] Verify review (record the Wave-3b guard in the ledger) → archive → `docs(sdd): archive student-nav-label-parity`.
- [x] NO push without explicit user authorization.
