# Tasks — lh-l-only-revert

- [x] T1. `app/Services/LectureSessionCatalog.php` — the T row →
  `['tutorial']` (L/P rows untouched); the docblock re-dates (2026-10-20's
  narrowing rides the 2026-10-19 note); `LectureSessionCatalogTest`'s
  pinned T row retunes (the designed contract change).
- [x] T2. `public/js/ui-common.js` — `venueTypeWording`'s per-key split
  (lab/cisco → 'Practical (P)'; lecture_hall → 'Lecture (L)'; tutorial →
  'Tutorial (L/T)').
- [x] T2b. THE SENTENCE WRAPPER split (the D2's "only" semantics): the
  venue-timetable page's hosts sentence (blade :404, `sessionTypesHuman`
  + the wrapper) and the replacement-arrangement page's own hosts line
  (`requiredTypeHuman` / the zero-fit + the intent-banner's `${req}` line)
  BOTH branch the 'only' suffix on the map verdict per raw type:
  lab/cisco → 'sessions only.'; lecture_hall → 'sessions only.'; tutorial
  → 'sessions.' (the L/T dual pair keeps no 'only'). The helper stays
  PHP-free (ui-common only); the venue page calls it via the shared JS
  (NOT a PHP branch).
- [x] T3. `tests/Feature/LtVenueCapacityRuleTest.php` — the T-in-LH rows
  revert (T@B110 rebuffed; L@B110 stays; the producer receipt flips to
  FALSE-polarity; retitled).
- [x] T4. `docs/venue-restrictions.md` — the R1 T row narrows + the dated
  2026-10-20 note; changelogs ×3; gates (lint/check + the in-scope
  playwright); the demo DB untouched. + then `/sdd-verify
  occurrence-week-pin-truthfulness` runs against the new law.
