# Tasks — b005-diploma-conflict

Frozen baseline: proposal.md (R1 PASS), design.md (R2 PASS + soft-freeze additions). Each task ≤ 2h.

## T1 — Evaluator + trait
- [x] Add `venueRestrictionConflict(ClassSession $s)` to `ResolvesTimetableTimeline` (§1 shape: `B005` AND any cohort `programme?->programme_code` starts with `D`; null-safe).
- [x] `baseEvent()`: `'status' => $this->venueRestrictionConflict($s) ? 'conflict' : 'normal'`.
- [x] Do NOT touch the cohort blade's pre-existing dead `isMine` branches.

## T2 — Venue view
- [x] `VenueTimetable.php`: add `'classSession.venue'` to the eager-load array (design §1 note).
- [x] Event builder: derived conflict overrides slot status (`conflict > pending > normal`, design §1).
- [x] Blade `statusClassFn`: conflict branch becomes owner-gated on the EXISTING `mine` key — `e.mine ? 'event-conflict' : 'event-public-holiday'`.

## T3 — Cohort blade
- [x] `cohort-timetable.blade.php` statusClassFn: NEW branch placed AFTER the existing holiday `isConflict` branch and BEFORE the pending branch: `e.status === 'conflict'` → `e.lecturer === MockData.currentUser.name ? 'event-conflict' : 'event-public-holiday'`.

## T4 — Feature tests (new `tests/Feature/VenueRestrictionConflictTest.php` + reuse patterns)
- [x] Session 38 (AMIT2034 B005 Wed 11:00) derives `conflict` on: my-timetable (acting as Daniel, user 10), cohort view (DFT cohort scope), student view, venue view (B005).
- [x] Negative RSD: B005 sessions with only `R*` cohorts stay `normal`.
- [x] Negative B006: networking (AMIT2033) AND non-networking (AMCS1013) B006 sessions stay `normal`.
- [x] Twin-merge severity: crafted normal+conflict twin rows merge → `conflict` (reflection, pattern of the existing merge test).
- [x] Precedence: pending request on session 38 → `pending`; removed → `conflict` (trait chain replacement > pending > conflict > normal).
- [x] `D*` prefix safety: assert no non-diploma `D*` programme code exists.

## T5 — Playwright (cheap additions only)
- [x] `venue-db.spec.ts`: B005 grid shows the Wed-11:00 block as `.event-conflict` for Daniel (login id `5652`; rate-limit ≤5 logins/min per ID). Update the spec header comment (3 → 4 tests).
- [x] No changes to other specs (parked mock spec untouched).

## T6 — Gates (mirror design §6)
- [x] Guarded server refresh if views stale (`pkill -f "[a]rtisan serve" || true` standalone command + `rm -f storage/framework/views/*.php`; NEVER `pkill -9 php`).
- [x] `composer run lint:check` clean · `vendor/bin/phpstan analyse --memory-limit=1G` 0 errors · `php artisan test` all green (123 + new).
- [x] Playwright: venue-db **4/4** · timetable-wiring 5/5 · nav-identity 3/3.
- [x] Records-intact: exact counts identical (no DB writes expected).

## T7 — Changelogs
- [x] `page-changelogs/venue-timetable-ui-changelog.md`: B005 conflict flag + owner-gated venue rendering entry.
- [x] `page-changelogs/my-timetable-changelog.md`: trait-derived conflict rendering entry (covers cohort/student pages).

## T8 — feat commit
- [x] `feat(venue): B005 diploma-cohort conflict — derived flag + owner-gated rendering` — component + trait + cohort blade + tests + changelogs (design §7 commit 1).

## T9 — Verify & archive
- [x] Verify review (review-log verdict) → archive to `.sdd/archive/` → `docs(sdd): archive b005-diploma-conflict`.
- [x] NO push without explicit user authorization.
