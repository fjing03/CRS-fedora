# design — `b005-diploma-allowed` (2026-10-27)

<!-- review round 1 folded: the VenueTimetable:191 severity arm + the
     AGENTS.md/doc-sweep completeness + the description-truth AMEND
     decision + the named test rows + the conditional fixture re-point
     (never manufacture). -->

## D1 — the rule dies as DATA, not a flag

`ruleMap()` loses BOTH b005 keys. Every consumer composes over the map —
the removal propagates the law change without a wiring edit downstream
(one authority per the venue-restrictions-enforcement D2 precedent).

## D2 — the PHP gate block

`sessionAllowedForVenue()`: delete the R2 deny block. The label/seed
docblock re-homes: "B005's row was R2 (no-Diploma) until the user's
2026-10-27 repeal — the remaining gates are the type rows, the B006
composite, and the engine's capacity check".

## D3 — the conflict-WHY arm

`conflictFacts()` ④ (the B005 × D* fact) DELETED. No B005 session makes
the conflicted-only fallback offer (the 2026-10-15 policy) render —
no repair is needed since no violation can exist.

## D4 — the render conflict + the payload status

`venueRestrictionConflict()` deleted; THREE derive-branch callers cleaned
(the reviewer r1's 🔴1 catch — the proposal's anatomy table missed the
third):
1. `ResolvesTimetableTimeline` :485 — `status: 'conflict'` → the branch
   dies (the payload renders `'normal'` unconditionally);
2. `ReplacementArrangement.php` :297 + `ReplacementHome.php` :76 — the
   `$venueRestricted` branches die with the VOICE string (the venue
   restriction badge label is a payload STRING, not a CSS class);
3. `VenueTimetable.php` :191 — the **severity-3 arm** (the venue grid's
   `$severity = $this->venueRestrictionConflict($session) ? 3 : …`)
   loses its first term (the pending-2/0 arms stay — a pending request
   on the venue grid still rides severity 2).

## D5 — the client matrix

`venueMatrixAllows()`'s B005 arm (:3209-3215) DELETED. `window.__ruleMap`
naturally stops carrying the b005 keys once the PHP shape loses them
(the same `@json` one-source precedent). No fail-closed hazard: the arm
is a conditional deny, not a required accept — deleting it widens the
accept set (the intended direction).

## D6 — the tests' honest re-cut (the reviewer r1's named set — 5 files + 7 phpunit)

The R1/R3/R4/R6 receipts STAY (they govern other rules). The b005
receipts re-cut POSITIVE (never merely deleted — a manufacturer would
turn a removed law into a silent hole; the reviewer's ②):

- `VenueRestrictionConflictTest` — the D*-cohort-derived-conflict
  receipts invert to the positive (the D*-cohort session at B005
  renders normal/no conflict); ADD the anti-bird guard (grep receipt:
  no `'B005 no-Diploma'` payload string can return).
- `EnforcementEngineValidateSlotTest` — the concrete rows flip: any
  b005-networking-L/T denial row asserts the accept (the R2 arm was the
  ONLY blocker for those rows: the R3/R4/R6 arms compose on); the
  reviewer's named `sp4_networking_L_into_B005_r2_denial`-family rows
  re-point to the accept.
- `EnforcementProducerPoolTest` — the named pool rows
  (`test_networking_l_pool_at_b005…`-family) assert B005's presence in
  the pool for a D*-cohort networking L/T (the additive lab widening
  now reaches).
- `LectureSessionCatalogTest` — the `b005_no_diploma_pattern` shape
  assertion dies (the key no longer exists); the matrix rows that
  expected the deny assert the absence.
- `LtVenueCapacityRuleTest` / `SessionAllowedForVenueMatrixTest` — the
  b005 mentions re-point (positive or struck, whichever narrates the
  receipt better).
- `tests/pages-parity.spec.ts` :74-75 — the fixture trigger re-point
  **CONDITIONALLY** (the reviewer's ②(b) catch — never manufacture):
  FIRST verify 5652's W1 carries a public-holiday/pending
  conflicted-occasion for the Replace-Now affordance receipt; if absent,
  re-point to the named alternative trigger (the AMIT2034 W4 slot stays
  but asserts the CLEAN state + the Replace-Now affordance via the
  pending arm). The implementer receipts the verification in the
  review-log before the edit.
- `tests/venue-db.spec.ts` :161/:179 — the B005-owner-gated conflict
  receipts DIE (the law is repealed); the file's other venue receipts
  stay.
- `tests/class-modal-affordances.spec.ts` :99-102 — the B005 conflict
  was its navigation VEHICLE — re-point to a surviving conflict
  trigger (the W8 medical-leave exception session 23, the fixtures'
  other anchor) or the Affordances' own pending arm.
- `tests/venue-timetable.spec.ts` :318 — comment-only strike.
- `tests/venue-restrictions-enforcement.spec.ts` — the R3/R4/R6
  receipts stay; the b005 arm flips to the positive receipt.
- `tests/replacement-*` (ReplacementArrangementTest /
  ReplacementHomeTest) — the venue-restriction badge receipts invert
  (a D*-cohort session renders normal with the badge string dead).

## D7 — the docs + the description-truth decision

- `docs/venue-restrictions.md`: R2's section re-homes to a REPEALED
  banner + the user quote; the SWEEP law (the reviewer's 🟡): every R2
  cross-reference strikes/re-homes too (:13 the client-matrix
  description line, :16 the enforcement banner, :33 the byte-identical
  family, :55/:63 the R2-still-applies clauses, :103/:111 the narrative
  block mentions, :146 the positive restatement, :164 the programme
  table's R2 cell, :189 the composite sentence) — grep-driven, not
  section-driven.
- `docs/README.md` :7's index line flips.
- `AGENTS.md` :13's standing rule (the reviewer's 🔴2 — auto-loaded
  into every future session): flip to "B005 no-Diploma REPEALED
  2026-10-27 (Diploma cohorts allowed) — SDD b005-diploma-allowed".
- `docs/demo-db-data-changes.md`: one dated superseded line rides the
  2026-10-27 receipt entries (the history stays, the TOC narrative
  flips).
- `CodingMAIN.md`: grep + strike any R2/P-B005 mention.
- **the venues.description truth (the reviewer's 🟡③ — decided**:
  AMEND). `Computer Lab. For PRACTICAL Class. DEGREE students only.
  (B005)` carries a false claim the moment Diploma sessions are legal
  — and the standing AMIT2034(P) row sits directly on it. The demo DB
  + VenuesSeeder + mock-data.js's §2.5 mirror :: the description
  rewritten WITHOUT the DEGREE clause: `Computer Lab. For PRACTICAL
  Class. (B005)`. Three data sites (one sheet paste + the seeder + the
  mock mirror), same values — the same-name-same-value discipline.
- `page-changelogs/`: the venue/arrangement/home/cohort pages ride a
  "the standing AMIT2034 conflict renders clean; the venue description
  loses the DEGREE clause" note.

## D8 — the debug-mode 500 blocker (discovered during the apply round — SIDE-STEPED AND FIXED)

Trying the T4 Playwright receipts surfaced the 5 DIFFERENT 500ables
`DOMDocument::loadHTML(): Argument #1 ($source) must not be empty` at
`vendor/livewire/livewire/src/Features/SupportMultipleRootElementDetection/SupportMultipleRootElementDetection.php:40`
— Livewire's DEBUG-mode root-element detector strips each
`<script>…</script>` element with a lazy `.*?</script>` regex; the
arrangement page's single `<script>window.PAGE_DATA = @json($payload);</script>`
(element ~1.04MB) overruns `pcre.backtrack_limit` (1,000,000), preg_replace
returns NULL, and `loadHTML(null→'')` throws the ValueError. The page
500s EVERY DEBUG-MODE LOAD — PRE-DATING THE REPEAL, but ONLY became a
receipt-blocker because the T4 probes hit it.

The fix is SHARED (the Promote-on-3rd law):
- NEW `resources/views/partials/page-data-script.blade.php` — the ONE
  shared partial that shards a payload's JSON into ≤16KB `-safe cut at
  non-escape boundaries`
  `<script>` elements (`window.PAGE_DATA_chunks.push({json_encode($chunk)})`)
  and re-joins with `JSON.parse`; only actual >60KB payloads shard (the
  small pages emit the same single script as before).
- The 5 replacement-family blades (`replacement-arrangement`,
  `replacement-home`, `my-request-history`, `request-approval`,
  `replacement-history`) include the partial instead of the inline
  `@json($payload)`.
- The consumer contract (window.PAGE_DATA keys) is UNCHANGED; `wpd`
  receipts: `Object.keys(window.PAGE_DATA)` = the 12-key contract.

## Tests added for the gap

None — the suite's 262 already cover R3/R4/R6's retention, and the R2
missing-receipt test is the DAMNING proof the law is GONE (the SDD
"deletion receipts" precedent from mock-statics-die's fixture re-cut).
