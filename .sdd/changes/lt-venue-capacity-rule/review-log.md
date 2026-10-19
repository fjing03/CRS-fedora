# Review log — lt-venue-capacity-rule

## proposal Round 1 — 2026-10-19
### 🔴 Fixed
 - Undeclared pinned-test break: `ReplacementArrangementTest::test_grid_busy_vectors_and_ineligible_venues_follow_d4_rule_1` (block=venue B005 lab+CSV L,T) — declared in the proposal's test-delta list with the honest fix framing (assertion updated, fixture untouched).
 - “byte-stable” overclaim now dataset-scoped (the suite's lab-fixture pin IS the counterexample).
### 🟡 Addressed
 - The three “hosts X sessions only” copy sites declared + routed through one shared wording helper (promote-on-3rd).
 - `page-changelogs/venue-timetable-ui-changelog.md` added to scope (the page's producer pool widens with the map).
### 🔴 Outstanding
 - (found in this round, fixed pre-round-2: none pending)
### Verdict: fix loop → round 2.

## proposal Round 2 — 2026-10-19
### 🔴 Fixed
 - The client dd filter (`replacement-arrangement.blade.php::venueEligible()`) is now an explicit impact-scope row (driven by the shared rule map + rawRoomType; stale law comment rewrite included).
 - The false non-goal about display derivations resolved: B005-BUCKETED conflict derivations stay untouched; map-riding pools widen as DESIGNED fallout with a receipt test (B100 admits AMSE2003; venue-page pool entry).
 - Impact table repaired: the venue-timetable blade row, the docs row, TWO changelog rows; the stray copy bullet absorbed into the table; ApiReadController listed as a consumer audit.
### 🟡 Addressed
 - The Playwright pin `tests/venue-intent-modal.spec.ts:124` ('hosts Practical (P) sessions only') added as a designed contract change.
### 🔴 Outstanding
 - (pending round 3 verdict)

### Verdict: proposal FROZEN after round 3 (🟡-1 TWO→THREE landed declaratively; 🟡-2 receipt wording fixed; log note: round-2's :124 = the locator line, actual pin :126 — cosmetic, left as-is).

## design Round 1 — 2026-10-19
### 🔴 Fixed
 - D5's helper home was a blade script block the other route never executes → relocated to `public/js/ui-common.js` as `venueTypeWording(rawType)` (one canonical phrase string, no word-order variants).
### 🟡 Addressed
 - D5's unresolved "only" draft rhetoric → single executable decision: sentence wrappers branch on rawType; "only" attaches ONLY to the P sentence (B009 pin survives byte-identically).
 - D2 gains the select-list edit: `room_type` joins; `allowed_session_types` drops from the select (the column stays as D1's inventory; ApiReadController's direct read untouched).
 - D4 fail-open replaced with fail-closed (map absent → deny; parity with the engine's conservative unknown-type default).
 - Garbled token ("фактure") → "fixture"; `requiredTypeHuman` adapter note added (venueCode → raw lookup → helper).
### 🔴 Outstanding
 - (pending round 2 verdict)
 - Reviewer note (no unfreeze needed): the frozen proposal's ApiReadController line ("inventory reads the engine") is imprecise — it CSV-explodes the column directly; D3's "audit-noted, no edit" treatments it identically.

## design Round 2 — 2026-10-19
### 🟡 Addressed
 - roomTypeHuman map scoped OUT (stays page-local — not the promote set; user-frozen framing copy).
 - Success-criterion-2 attestation vehicle pinned: read-only tinker probe re-run at /sdd-verify.
### 💡 Adopted
 - Helper's unknown rawType → '' (never advertises a wrong law); capOk-vs-map fail philosophy note.
### Verdict: design FROZEN (no Outstanding).

## tasks Round 1 — 2026-10-19
### 🟡 Addressed
 - T5↔T6 reordered (helper first, then its consumer); dipOk typo fixed; P-sentence 'only' branch named in T6.
 - T10 gained the VenueTimetable component-payload assertion (the producer receipt's BOTH halves); L(15)→L(20) per D6.4.
 - T12 pre-loads the ApiReadController remaining-direct-read note; T14 names `venue-intent-modal.spec.ts` as the in-scope suite + the TC70 guard.
 - T6's "verbatim" ambiguity resolved: D5 snippet + the Round-2 unknown-guard, named explicitly.
### Verdict: tasks FROZEN.
