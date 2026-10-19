# Tasks — lt-venue-capacity-rule

- [x] T1. `app/Services/LectureSessionCatalog.php` — `ruleMap()`: the L row →
  `['tutorial', 'lecture_hall']`; the T row → `['tutorial',
  'lecture_hall']` (P row byte-stable). Update the map's docblock (the law +
  the 2026-10-19 user ruling reference).
- [x] T2. `app/Services/MatrixIntersectionEngine.php` — `eligibleVenues()`:
  (a) the select list becomes `->get(['id', 'room_code', 'capacity',
  'room_type'])` (the CSV column leaves the select; add a one-line comment
  that the column remains reference inventory + ApiReadController's audit
  read is untouched); (b) the predicate consults
  `app(LectureSessionCatalog::class)->ruleMap()[$sessionType] ?? []` over
  `room_type` (unknown session type → no venues). Update the method's
  docblock (Vector 4 = room-type law + capacity).
- [x] T3. `app/Livewire/ReplacementArrangement.php` — refresh the
  `venuesPayload()` rawRoomType comment (`rides along` → "the client filter
  + fallback read the map over room_type") + the `blockedVenuesPayload()`
  docblock (the "allowed_session_types" wording retires). NO payload-shape
  change (byte-identical provider shapes).
- [x] T4. `app/Livewire/VenueTimetable.php` — comment refresh only
  (`rawRoomType` docblock; the D0/D1 comment lines at the venue payload).
  NO logic edit (the pool widens through the map).
- [x] T5. `public/js/ui-common.js` — add `venueTypeWording(rawType)`: the
  D5 snippet PLUS the Round-2 adopted unknown-guard (unknown rawType → '';
  labs → 'Practical (P)'; dual rooms → 'Lecture & Tutorial (L/T)').
- [x] T6. `resources/views/livewire/replacement-arrangement.blade.php` —
  rewrite `venueEligible()` per design D4 (fail-closed on a missing map;
  `rawRoomType` verdict; capOk/dipOk unchanged; the B005-Diploma hide
  stays — P-only rooms keep their sentence 'only' via the rawType branch);
  rewrite the two law comments; wire the zero-fit notice /
  booking-intent banner / type-reason sentence + the `requiredTypeHuman()`
  adapter to the shared helper.
- [x] T7. `resources/views/livewire/venue-timetable.blade.php` —
  `sessionTypesHuman` maps through `venueTypeWording`; the "hosts …"
  sentence wrapper branches the 'only' suffix on the raw lab types. NO
  other copy edits (the `roomTypeHuman` framing map stays).
- [x] T8. `tests/Feature/LectureSessionCatalogTest.php` — update the pinned
  L/T rows (designed contract change), keep every other pin byte-identical.
- [x] T9. `tests/Feature/ReplacementArrangementTest.php` — update the
  blocked-list assertion (`B005` leaves it for the P session; the fixture
  CSV stays) + the docblock note (room-type law).
- [x] T10. NEW `tests/Feature/LtVenueCapacityRuleTest.php` — D6.4's rows:
  L(20) on tutorial B002 fixture → eligible; T(20) on B110 → eligible;
  P(10) on tutorial → rejected; capacity violation → rejected; unknown
  session type → empty; `sessionAllowedForVenue('L','tutorial', …AMSE2003
  fixture…) → true`, AND the VenueTimetable component payload gains the
  class on a fixture venue (the producer-pool receipt carries BOTH halves;
  never assert a "no-T-in-LH" absence as law).
- [x] T11. `tests/venue-intent-modal.spec.ts` — verify the B009 zero-fit
  pin survives byte-identically (the P sentence keeps "only"); update ONLY
  if the sentence wrapper shifted it (declared contract change either way,
  logged in the changelog).
- [x] T12. `docs/venue-restrictions.md` — (the docs preamble also notes
  ApiReadController as the CSV column's remaining DIRECT read — no edit
  expected; the R1 amendment explains the deliberate divergence) — R1 rows → `L → tutorial,
  lecture_hall` / `T → tutorial, lecture_hall`; a dated "user clarification
  2026-10-19" note with the verbatim ruling; the preamble's live-code list
  gains the venue page's producer-modal pool row; §3's producer line notes
  the widening.
- [x] T13. `page-changelogs/replacement-arrangement-changelog.md` +
  `page-changelogs/venue-timetable-ui-changelog.md` — dated entries (dd
  widening; the wording helper promotion; the spec pin status).
- [x] T14. Gates — `composer run lint:check`; `vendor/bin/phpstan analyse
  --memory-limit=1G`; full phpunit; Playwright in-scope suites =
  `tests/venue-intent-modal.spec.ts` only (⛔ the crs-workspace TC70
  guard — never run the TC70 suite). Then the read-only tinker attestation
  probe (success criterion 2).
