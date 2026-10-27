# tasks — `b005-diploma-allowed` (2026-10-27)

## T1 — the rule source
- `LectureSessionCatalog::ruleMap()`: delete `b005_no_diploma_pattern` +
  `b005_venue_code` (both keys) + the docblock's B005 line.
- `sessionAllowedForVenue()`: delete the R2 deny block + re-home the
  comment.
- `conflictFacts()` ④: delete the B005 arm + re-home.
- `payloadFor()`/`build()`'s phpdocs: the B005 mentions strike.

## T2 — the render conflict
- `ResolvesTimetableTimeline::venueRestrictionConflict()` deleted;
  `status:` :485 → `'normal'`; the docblock re-homes.
- `ReplacementArrangement.php` :297's `$venueRestricted` branch +
  `ReplacementHome.php` :76's — both cleaned (the badge string dies
  with the branch).
- **`VenueTimetable.php` :191's severity arm** (the reviewer's 🔴1):
  the `$this->venueRestrictionConflict($session) ? 3 : …` term dies —
  severity rides pending-2/0 only.
- The phpdoc/README mentions strike (`VenueTimetable.php` :52/:93,
  `ReplacementHome.php` :23, `LectureSessionCatalog.php` :25/:39-40/:127
  /:286/:312, `MatrixIntersectionEngine.php` :409).

## T3 — the client
- `replacement-arrangement.blade.php` `venueMatrixAllows()`: the B005
  deny arm (:3209-3215) deleted; the comment re-homes.
- `ui-common.js`: the b005 references strike (grep receipt).

## T4 — the tests re-cut
- The 7 phpunit/Test files + 4 Playwright spec anchor files re-point
  per design D6; the additions stay honest (the R2 negative becomes
  the positive; the fixture trigger re-points where necessary).
- `php artisan test` green (262+).

## T5 — the docs + the changelogs
- `docs/venue-restrictions.md` R2 → REPEALED with the ruling quoted;
  the FULL CROSS-REFERENCE SWEEP per design D7 (grep-driven, 9+ lines).
- `docs/README.md` :7 + `AGENTS.md` :13 (the standing rule the reviewer
  flagged 🔴) + `docs/demo-db-data-changes.md`'s superseded line.
- `CodingMAIN.md`'s mentions.
- **the venue description AMEND** (design D7's decision): the demo-DB
  UPDATE + VenuesSeeder + the mock-data.js :134 mirror — the description
  loses the `DEGREE students only.` clause (B005 only), one sheet paste
  for the user to confirm.
- `page-changelogs/` rides ×5 + the shard receipt.

## T6 — the debug-mode 500 blocker (design D8 — the shard)
- NEW `resources/views/partials/page-data-script.blade.php` (the shared
  payload-sharding partial, >60KB threshold).
- The 5 replacement-family blades include it (the inline `@json($payload)`
  scripts die).
- The playwright receipts: all the replacement pages 200 in debug mode;
  the PAGE_DATA contract unchanged; the picker's Lab drill offers B005.
- ONE `feat:` commit; not pushed.
