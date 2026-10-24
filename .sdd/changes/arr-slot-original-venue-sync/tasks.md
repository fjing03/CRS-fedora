# tasks — `arr-slot-original-venue-sync`

- [x] T1: `commitSlotSelection` restructure — session-change branch carries
  the re-derive + the opts.auto eligibility fallback (verbatim); the
  ORIGINAL-VENUE SYNC runs under `__bootDone && !opts.auto` on every manual
  pick (orig offered → orig; not offered → fallback only-when-dead; equal →
  parity refire; none → build). Plus the read-only `window.__venueSelected()`
  Test-5 hook in the boot tail.
- [x] T2: NEW spec `tests/arr-slot-original-venue-sync.spec.ts` — 5 rows
  (the multi-week Gap-A variant + the orig-not-offered row documented
  BLOCKED with the probe receipts, never invented).
- [x] T3: gates — spec 5/5 ×2, spot battery 25/25 (--grep-invert "TC70"),
  pint passed, phpstan-1G 0, phpunit 230/230.
- [x] T4: changelog dated entry at the TOP of
  `page-changelogs/replacement-arrangement-changelog.md` + review-log
  receipts.
- [x] T5: the ROUND-2 amendment (the 68/14 whole-red sighting): syncVenueFamily shared helper + the applySubjectChange post-boot tail call (the auto-pin's branch-1 early-return was found while probing); the commit-time ctx race absorbed by the helper's fresh buildVenueFilter.
- [x] T6: round-2 gates rerun (spec 9/9 x2 incl. the NEW 68-sighting row; battery 25/25; pint/phpstan-1G 0/phpunit 230/230).
