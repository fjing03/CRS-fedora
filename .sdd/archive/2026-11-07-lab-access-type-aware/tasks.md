# tasks — `lab-access-type-aware` (2026-11-07)

- [x] **T1 — re-verify the population (read-only, both DBs).** Population query from the
      handover against `class_replacement_fresh` and `class_replacement_testing`; assert the
      two agree row-for-row; confirm the baseline counts (1 semester / 101 sessions /
      42 modules / no duplicate module code / no session without cohorts); state the
      over-grant list before the change.
- [x] **T2 — `ruleMap()`.** Replace `lab_access_module_codes` + `b006_allowed_module_codes`
      with `lab_access` (`module => ['L'?, 'T'?]`); keep `b006_allowed_module_codes` as
      `array_keys($labAccess)`; update the PHPDoc shape; record the superseding ruling in the
      comment (the 2026-11-03 per-subject sentence stays visible, dated).
- [x] **T3 — `sessionAllowedForVenue()`.** Both additive arms test the session type against
      the module's entry; `$inWidenRows` fail-closed guard stays; R6 block untouched.
- [x] **T4 — the client mirror.** `venueMatrixAllows()` reads `lab_access[moduleCode]` for
      both arms; fail-closed arms untouched.
- [x] **T5 — matrix test.** Flip `sp17`/`sp18` to false; add AMSE1003, AMCS1043, AMCS1013
      (`L` vs `T`) and BMIT2013 `T` @ B006; keep `sp8` true.
- [x] **T6 — seed invariant.** Rewrite T8 as both-direction over `(module, type)`, plus the
      AMCS1013 partial assertion and the derived-key equality.
- [x] **T7 — runtime receipt.** New `tests/Feature/LabAccessTypeAwareGateTest.php` on the
      real seed: AMSE1003 `L` denies all five labs, its `T` allows B009; AMCS1013 `L` denies
      B006, its `T` allows it.
- [x] **T8 — docs.** `docs/venue-restrictions.md` R3 dated entry + supersede marker + new
      type-aware roster; `dataset/DATASET-NOTES.md` §3 prose.
- [x] **T9 — prove the invariant bites.** Remove one map pair → T8 fails; restore. Add a
      fake pair (`BMCS2053 => ['L']`) → T8 fails; restore.
- [x] **T10 — gates.** `php artisan test --filter 'SessionAllowedForVenue|TimetableSeedInvariants|LtVenueCapacity|MatrixIntersection|LectureSessionCatalog|LabAccessTypeAware'`,
      `composer run lint:check`, `vendor/bin/phpstan analyse --memory-limit=1G`.
- [x] **T11 — report.** Files changed, before/after verdict table, test counts, tinker proof.
