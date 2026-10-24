# design — `venue-restrictions-enforcement`

## D0 — the governing principles (from the frozen proposal)

1. **ONE matrix read**: every gate lives in
   `LectureSessionCatalog::sessionAllowedForVenue($sessionType, $rawRoomType,
   ClassSession $session, string $venueCode)` (see D2 — NO extra param; the
   round-1 module-source decision) — consumers DELEGATE; nobody
   re-implements the composition.
2. **Data over schema**: the four new keys ride `ruleMap()`'s existing
   JSON-to-`PAGE_DATA` plumbing. No migration, no new columns/tables, no UI
   copy.
3. **Byte-compat for every pre-existing behavior** — the proposal's
   gate-composition ruling + the existing test ledger (modal pool shapes,
   `VenueRestrictionConflictTest`, seed invariants) must stay green.

## D1 — the rule source (final data shape)

`ruleMap()` gains (non-array string keys stay aside, as `b005_*` do today):

```php
return [
    'P' => ['lab', 'cisco_lab'],
    'T' => ['tutorial'],
    'L' => ['tutorial', 'lecture_hall'],
    // venue-restrictions-enforcement (the user's 2026-10-24 ALLOW/DISALLOW
    // law; doc R3/R6/R2 — coding-with-DFT2-unlinked receipts):
    'lab_access_module_codes' => ['AMIT2033', 'BMIT2154', 'BMIT3084', 'AMIT2034'],
    'b006_allowed_module_codes' => ['AMIT2033', 'BMIT2154', 'BMIT3084', 'AMIT2034'],
    'b006_focs_programmes' => ['DFT', 'DSF', 'RSD'],
    'b006_banned_programmes' => ['RAF', 'RBU'],
    'b006_venue_code' => 'B006',
    'b005_no_diploma_pattern' => '^\\s*D',
    'b005_venue_code' => 'B005',
];
```

Contract notes:
- `lab_access_module_codes` = additive labs for L/T only (P is already
  labs-only by the base rows; nothing widens).
- `b006_allowed_module_codes` = the L/T widening INTO B006 (for P the base
  row admits everyone; the population gate below applies to ANY type).
- The inline comment "R3/R4 stay PLANNED-NEW" is REPLACED by the
  enforcement receipt comment (the proposal's stale-comment flip).
- `b006_banned_programmes` is explicit-but-redundant (fail-closed culture:
  a future DFT lookup bug against the banned list must still deny).

## D2 — the ONE gate function (the upgrade-site)

`sessionAllowedForVenue()` upgrades IN PLACE (same file, same name, same
signature — `VenueTimetable.php:95` needs no edit; NO extra $moduleCode
parameter — the module source decision, per the round-1 🔴: the gate reads
`$session->module->module_code` INSIDE and is FAIL-CLOSED: an
unloaded/missing module relation reads as "in NO list" (base rows + the
venue gates only — never widened; never thrown).

The gate is ONE boolean formula, not a sequential step list (the round-1
🟡2 fix — an early-return step chain would bypass the venue gates exactly
when they matter):

```
allowed =
    ( ROW(type, rawRoomType)                                  // base R1 rows (unchanged)
      || (type ∈ {L,T} && module ∈ lab_access_module_codes
          && rawRoomType ∈ {lab, cisco_lab})                  // additive labs
      || (venueCode === b006_venue_code && type ∈ {L,T}
          && module ∈ b006_allowed_module_codes) )            // B006 L/T widening
  AND NOT (venueCode === b006_venue_code
      && NOT cohortsAllFOCS)                                  // R6 deny: EVERY cohort programme hits b006_focs_programmes and none hits b006_banned_programmes
  AND NOT (venueCode === b005_venue_code
      && anyCohortDiploma)                                    // R2 deny — composed for EVERY type (an AMIT2033 L with D* cohorts keeps B005 hidden)
```

Acceptance anchors of the formula (the canonical tests): an AMIT2033 L
with D* cohorts keeps B005 hidden; a non-FOCS cohort session keeps B006
hidden; a BMIS2003 L never gains any lab; a networking L GAINS every
lab (capacity stays engine-side, never here).

- **B006 identity = BY CODE via a NEW `b006_venue_code: 'B006'` key**
  (round-1 💡 adopted; asymmetric with `b005_venue_code`) — the sole
  `cisco_lab` per the R6 scope receipt (`VenuesSeeder.php:45`).
- **R6 programme extraction pinned (round-1 💡)**: server side reads the
  real `programme_code` column; the CLIENT reads the compact
  `cohortCodes` labels ("DFT2 (S1) G1"-family) via the ALPHA PREFIX
  (`/^[A-Z]+/` of the stripped label) matched EXACTLY against
  `b006_focs_programmes`/`b006_banned_programmes` (no prefix-collision:
  a distinct RAFS would extract as RAFS, never as RAF).
- **Cohort-less sessions**: the R6 deny is vacuous (no cohort → no
  population violation), fail-open for that key alone — no cohort-less
  class exists in the DB today (a seed bug if ever reachable); recorded,
  not guarded.
- Backward-compat anchors: today's B005 branch = the R2 deny verbatim
  (the type rows unchanged; a non-listed module in a non-gated venue
  reads exactly as today).
- The catalog PHPDoc's stale sentences flip in the same task (round-1
  💡): "doc rule, client+modal filtering only until venue-restrictions
  enforcement lands" + the ruleMap inline "R3/R4 stay PLANNED-NEW".

## D3 — engine wiring (the authoritative OCC-time path)

`MatrixIntersectionEngine` changes:

- **NEW `sessionVenueEligible(ClassSession $session, Venue $venue): bool`**
  — capacity vs `requiredHeadcount($session->cohorts…)` AND the catalog
  gate `sessionAllowedForVenue((string)$session->session_type,
  (string)$venue->room_type, $session, (string)$venue->room_code)`.
- **`validateSlot(...)` gains `?ClassSession $session = null`** (LAST
  parameter — the full-block span param stays positional). When
  non-null, the `:207`-site eligibility read
  (`eligibleVenues($headcount, $sessionType, $anchor->venue_id) !== []`)
  becomes `$this->sessionVenueEligible($session, $anchorVenue)`; when
  null → today's read verbatim. The ONE real caller
  (`app/Actions/SubmitReplacementRequest.php`, engine pre-check) passes
  its `$session` — THE enforcement point: an OCC-time booking of
  non-networking L/T into B006, non-FOCS cohorts into B006, or `D*`
  cohorts into B005 fails with an "engine_precheck_failed" loser (the
  existing written reason string; NO new UI copy — the silence law).
  **Eager-load contract (round-1 🟡1):** the action's success mailer
  already reads `$session->module` — the apply round must ensure the
  `$session` reaching `validateSlot` carries
  `['module', 'cohorts.programme']` loaded (the fail-closed gate
  DEGRADES to base-rows-only when unloaded — never widens, never
  throws) — verified with a fixture, not assumed.
- **`blockedVenuesPayload(Collection $sessions)`**
  (`ReplacementArrangement.php:461`) upgrades to
  `sessionVenueEligible($session, $venue)` per session × venue — the grid's
  "venue renders occupied" truth now module-conditioned. NOTE this is
  data-only: the grid renders the SAME cells/pictures; labs simply stop
  being "all-occupied" for networking L/T ctx.
- **`findAvailableSlots` (the ApiReadController audit/read API, `:57`)
  UNTOUCHED** — its contract is the headcount+type preview (it holds no
  ClassSession by design); its doc-comment gains a line naming the session-
  scoped wrapper as the authoritative flavor. Justification: O1's byte-compat
  principle + the API's readers treat it as an audit surface.
- **The arrangement CLIENT fallback's prefill predicate folds into the same
  client matrix (round-1 🟡3)** — the prefill (`consumeBookingIntent` in the
  blade, ~`:2596`) hand-rolls `rule[e.type]` + the B005 pattern over BASE
  rows only: a booking intent targeted at a lab for a networking L/T
  session would fail to prefill while the modal + dropdown admit it — the
  exact one-source drift this lane forbids. The fallback delegates to the
  SAME D4 client composition; no copy change.

## D4 — client matrix wiring (blade, data-level)

`venueEligible(v, ctx)` / `buildVenueFilter()` in
`replacement-arrangement.blade.php`:

- The base rows stay the backbone; the SAME composition as D2 (client
  mirror of the rule): labs for `ctx.code` ∈ `lab_access_module_codes`
  when `ctx.type` ∈ {L,T}; B006 for L/T needs the widen-roster hit; R6
  FOCS/banned read from `window.__ruleMap` (already shipped via
  `PAGE_DATA`); the B005 `D*` check exists — now expressed via the same
  composition order.
- Media opacity: `courseCtx()` already returns the module `code` (merged
  currentCourse + session facts) — no payload change; `PAGE_DATA.ruleMap`
  is one JSON carrying the new keys automatically.
- `#venueCountNote`, legends, all copy: UNTOUCHED (the silence law). The
  blockedVenuesPayload server change (D3) needs NO client edit — the grid
  data is computed server-side.

## D5 — execute the doc rider + bookkeeping (mirrors the frozen proposal list)

- `docs/venue-restrictions.md`: statuses → enforced **for the enforced
  rules ONLY — R4's row becomes the DROPPED receipt (repealed by
  venue-r3-r4-networking-iot-doc), NEVER "enforced", and the ruleMap
  comment replacement states R4's disposition (no IoT keys exist on
  purpose)**; ⚠️1 amended (ruleMap =
  the sole authority, no migration); R3's AMIT2034 paragraph rewritten to
  the additive revision (AMIT2034 named as the FOURTH code of the B006
  roster); §4's phrase; R5's inventory reword (violations, silently
  tolerated); R6's AMCS1013 cross-ref reword (type mismatch is the
  violation); the preamble narrative + §3 layer-map rows (the round-2
  enumeration).
- `mock-data.js`: AMIT2034 module entry + the `dft2s1`/`dsf2s1` row sets →
  DSF·2S1G1 only, 15 students (drop the DFT rows/counts).

## D6 — no new tests host, no frontend framework changes; the receipts map

- NEW `tests/Unit/*.php` (or Feature, matching the house layout): the
  matrix table from explore-brief §1 as a data-driven service test of
  `sessionAllowedForVenue` (networking L/T × lab rows; non-networking
  negatives; B006 L/T widen; R6 FOCS/banned; R2 over the widening; capacity
  NOT included — engine-side test for that).
- NEW Feature-level engine rows: `validateSlot` with `$session` —
  rejects listed negatives (B006 non-networking L; B006 RAF/RBU; B005 `D*`)
  + accepts the positives; byte-compat row for the null-session call.
- NEW Playwright rows: the modal receipt needs a CONFLICTED networking-L
  fixture (the pool = conflicted-only ∩ venue-admitted — a healthy session
  shows NOTHING under any mapping): owner-lecturer + an
  exception-cancelled AMIT2033 L occurrence, landing a LAB venue page →
  the session NOW appears in the pool (today: nothing); the same fixture
  on a non-networking L (BMIS2003) stays nothing. Arrangement dropdown
  rows BOTH directions: ctx networking L → labs in the filter; **the
  client-side NEGATIVE (brief §8): non-networking ctx (BMIS2003/
  AMCS1013 L) → labs ABSENT while the modal/server rows stay green —
  the only assert that catches a client type-check drop**.
- Standing: spot battery `--grep-invert "TC70"`; Feature suite run (green);
  pint; phpstan-1G; phpunit full re-run.

## Promoted to shared

Nothing new — the design rides the existing single-source gate
(`sessionAllowedForVenue`) and the existing payload plumbing (`ruleMap`
→ `PAGE_DATA`). The matrix upgrade itself IS the anti-duplication move
(the client previously mirrored only the type rows; it now mirrors the
extra keys from the same JSON — the R7-law drift guard).
