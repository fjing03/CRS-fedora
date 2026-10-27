# tasks — `mock-statics-die`

- [x] **T1 — the payload short name** (D1, the FOUR sites per the R1 🔴4):
  the trait helper (`shortVenueName`); `venuesPayload()`; `VenueTimetable`'s
  `$venuesJs` (:135); the `$venue` local (:566-573); the null-branch array
  (:128); both phpdoc generics (:577-578) — the server-stamped law.
- [x] **T2 — the widget** (D2): `_updateTrigger` reads the payload's
  `short_name` (label) + `name` (tooltip-full); the map fallbacks deleted.
- [x] **T3 — the deletions** (D2/G2/G3): the `venueRealNames` / `cohorts` /
  `arrangementWeeks` blocks deleted from mock-data.js + the comment sweep
  (:66, the §2.5a docblock); the e2e fixture re-cut (full-suite :507-520: the
  arrangementWeeks arm retires + the test title renamed 'MockData has
  venueSlots'; the API-level fixtures untouched); the grep
  receipts 0-remaining.
- [x] **T4 — the receipts** (D4): the picker Playwright re-run (the SHORT
  label + the FULL tooltip byte-stable across surfaces) + the compact
  phpunit trio + the full suite + pint/phpstan/node --check.
- [x] **T5 — the record-keeping** (D5): the changelogs (the venue pages +
  the cohort page note); ONE `feat:` commit; no push.
