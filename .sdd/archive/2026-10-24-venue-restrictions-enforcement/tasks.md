# tasks — `venue-restrictions-enforcement`

Each ≤ 2 h. Order matters: the rule source first, then consumers, then
receipts. All tasks read the FROZEN spec
(`specs/venue-eligibility-matrix/spec.md`) as the acceptance ledger;
gates on the blade = the stale-serve ritual (two commands) + pint +
phpstan-1G.

- [x] T1 — rule source (`app/Services/LectureSessionCatalog.php`): add the
  five new keys to `ruleMap()` (incl. `b006_venue_code`); upgrade
  `sessionAllowedForVenue()` to the ONE boolean formula (R2 of the spec:
  module read inside, fail-closed; R6 + R2 deny composition for every
  type); flip the stale inline comments ("PLANNED-NEW" → the enforcement
  receipt + R4's dropped disposition; the PHPDoc "client+modal filtering
  only" sentence).
- [x] T2 — engine (`app/Services/MatrixIntersectionEngine.php`): NEW
  `sessionVenueEligible(ClassSession $session, Venue $venue): bool`;
  `validateSlot` gains the trailing `?ClassSession $session = null`
  (the `:207`-site switch when non-null); doc-comment line on
  `findAvailableSlots` (unchanged contract) + `blockedVenuesPayload` in
  `app/Livewire/ReplacementArrangement.php` switches to
  `sessionVenueEligible` (data-only for the grid).
- [x] T3 — the authoritative caller
  (`app/Actions/SubmitReplacementRequest.php`): pass `$session` into
  `validateSlot`; ensure
  `$session->load(['module','cohorts.programme'])` at :49 (the fixture
  must prove the degrade path: unloaded relation = base-rows-only).
- [x] T4 — client blade
  (`resources/views/livewire/replacement-arrangement.blade.php`): the
  ONE client composition helper (reads `window.__ruleMap` keys; alpha-
  prefix programme extraction per spec R4.3) consumed by
  `venueEligible(v, ctx)`, `buildVenueFilter()` AND
  `consumeBookingIntent`'s prefill predicate; zero copy changes; the
  standing stale-serve ritual before any manual verification.
- [x] T5 — service tests (NEW): the R3 truth table fixtures #1–#14
  (inv. #14 = the gate-IGNORES-capacity assert: headcount 15 vs a 28-seat
  venue still ALLOWs at gate level — the engine rejects) data-driven
  against `sessionAllowedForVenue` — host `tests/Unit`;
  the engine `validateSlot` rows — host `tests/Feature` (they hit
  TimeSlot/DB): positives/negatives (B006 non-networking L; B006
  non-FOCS; B005 D*; the byte-compat null-session call; the #14
  gate-ignores-capacity receipt; the degrade-path fixture).
- [x] T6 — Playwright rows (NEW spec file, staff storage states):
  (a) the modal receipt — owner of a conflicted AMIT2033 L on a lab
  venue page → the session appears in the pool (bmis/AMCS negatives
  unchanged); (b) the arrangement dropdown — networking L ctx → labs
  present; BMIS2003/AMCS1013 L ctx → labs absent (the client-side
  NEGATIVE; the only assert catching a client type-check drop).
- [x] T7 — bookkeeping: `public/js/mock-data.js` AMIT2034 → DSF-only
  truth (module entry cohorts/counts 15; the `dft2s1` AND `dsf2s1` AMIT2034 row
  sets normalized to the DSF-only truth (the stale counts/groups too); `docs/venue-restrictions.md` the FULL frozen rider,
  mirroring the proposal's master list VERBATIM (no re-enumeration
  from memory): statuses (R4 = the DROPPED receipt, never
  'enforced'); ⚠️1 (ruleMap = the sole authority, no migration); R3's
  AMIT2034 paragraph rewritten (AMIT2034 = the FOURTH code of the
  B006 roster; the labs-exclusive law repealed); §4's AMIT2034
  phrase; R2's audit bullet ('combined DFT+DSF' → the DSF-only·15
  receipt); the preamble narrative (B005/R6 'DOC-ONLY' prose); §3's
  layer-map rows (engine row ≠ 'R1 only' anymore); R5's violation
  inventory reword (type-vs-room violations, silently tolerated);
  R6's AMCS1013 cross-ref reword (TYPE mismatch, not the population
  gate); AND §2's `allowed_session_types` DB-facts row ('historical
  guard; R1 supersedes when enforcement lands' is EXACTLY WRONG
  under Q6 — nothing widens, the column stays 'P' as reference
  inventory + the ruleMap-is-authority note).
- [x] T8 — gates: spot battery `--grep-invert "TC70"`; Feature suite RUN
  and green (`VenueRestrictionConflictTest` + seed invariants); pint;
  phpstan-1G; phpunit full re-run; the page-changelogs entry for the
  arrangement page (data-behavior receipt) and OPENCODE-style lane
  receipts (review-log append); ONE conventional commit, explicit
  paths, NEVER push.
