# explore-brief — `venue-restrictions-enforcement` (grilling receipts, 2026-10-24)

## 0. The scope decision (Q1, user option 1)

ONE change: all rule families — R1 base + B006 allow/disallow + AMIT2034
module law + R6 population gate + R2 server-side — coded at ALL THREE layers
in one pass, because they share the single rule source (`ruleMap()`), the
same three consumption sites, and one test battery. This lane = the
booking-time rules layer of Wave 3b. NO UI flags, NO flags removal — see §2.

## 1. THE FINAL RULE MATRIX (the frozen contract; all tables/dims, no "e.g.")

Base (EVERY module, unchanged — the `ruleMap()` rows already in code):

| Session type | Allowed room_types (base R1) |
|---|---|
| `P` | `lab`, `cisco_lab` |
| `T` | `tutorial` (lh-l-only-revert: lecture hall = L only) |
| `L` | `tutorial`, `lecture_hall` |

Module-conditioned exceptions (NEW keys in `ruleMap()` — the approved shape):

| Key | Value | Effect |
|---|---|---|
| `lab_access_module_codes` | `['AMIT2033','BMIT2154','BMIT3084','AMIT2034']` | these modules' **L/T may ALSO use labs** (`lab`+`cisco_lab`: B005, B009, B010, B011, B006) — additive to the base rows, NOT replacing them |
| `b006_allowed_module_codes` | same four codes | the L/T widening into **B006 specifically** (its P admission is already the base R1 for everyone) |
| `b006_focs_programmes` | `['DFT','DSF','RSD']` | **R6 population gate**: any B006 occupant, ANY type, must carry only FOCS cohorts |
| `b006_banned_programmes` | `['RAF','RBU']` | the never side of R6 (redundant with the allow-list but explicit, fail-closed) |
| `b005_no_diploma_pattern` | `^\\s*D` (existing) | **R2 — now ENFORCED engine-side too**: B005 barred when ANY cohort programme starts with `D*` (client + modal already have it) |
| `b005_venue_code` | `B005` (existing) | the R2 gate key |

Per-module outcomes under the matrix (campus facts):

| Module | L rooms | T rooms | P rooms |
|---|---|---|---|
| Networking (AMIT2033, BMIT2154, BMIT3084, AMIT2034) | halls/tutorials **+ labs** (incl. B006) | tutorials **+ labs** (incl. B006) | labs only (R1 base) — B005 additionally R2-gated per cohort |
| Everyone else (BMIS2003, AMCS1013, BMIT3173, BMIT3273 …) | halls/tutorials only | tutorials only | labs only (incl. B006, population-gated) |

Reality anchors (live DB, verified 2026-10-24):

- AMIT2034 = **DSF·2S1G1 only (15 students)** — DFT2 was unlinked from it on
  2026-10-13 (`TimetableSeedInvariantsTest` receipt); the session rows: P in
  B005 (Wed 11–13 — the standing R2 violation), L + T in B010 (Mon 13:30–16:30).
- B006's occupants today: AMIT2033 L/T/P, BMIT2154 L/T/P, BMIT3084 L/T/P
  (networking, legal under the widening), BMIS2003 L/P, BMIT3173 P,
  BMIT3273 P, AMCS1013 T/P — ALL FOCS cohorts (R6-clean TODAY; AMCS1013's
  DSF·1S1G1 satisfies the gate).
- Existing rows that would violate the matrix: AMCS1013 **T**@B006 and
  BMIS2003 **L**@B006 (non-networking, type-vs-room) and AMIT2034
  **P**@B005 (R2, DSF cohort) — see §2's silence law.

## 2. The SILENCE law (Q3/Q4, user: "silently allow R1&R2; don't mention in system")

- The rules gate the **replacement booking write path only** (engine + dropdown
  + modal). Imported timetable rows are DOM/DB FACTS: never re-roomed, never
  flagged, never derived-conflicted **by this lane**.
- The pre-existing `b005-diploma-conflict` family (AMIT2034 P@B005 card/flag/
  modal reason "Venue Restriction (B005 no-Diploma)") **stays byte-identical**
  (Q4 = leave as-is; it is a prior approved SDD surface; its specs +
  `VenueRestrictionConflictTest` + seed invariants untouched).
- NO new derived-conflict families for lab-type violations or R6.
- `venueCountNote` copy and all UI wording unchanged (99.9%-UI rule; only the
  admission DATA behind the same UI changes).

## 3. Data home + identity (Q5, user option 1 + amendments)

- `LectureSessionCatalog::ruleMap()` gains the NEW keys above (JSON-able;
  ships via `PAGE_DATA.ruleMap` unchanged plumbing). NO DB columns, NO
  registry table, NO config file (rejected §5). The client and modal read
  the map as data like today.
- **Networking identity = the frozen 4-code list.** Adding a module later =
  the user edits the doc + `ruleMap()` (no admin UI in this lane, none
  planned; an admin registry someday migrates the data home behind the same
  consumer contract).

## 4. The three layers/consumers (who calls who — cross-module flows)

1. **Submit-side engine (authoritative, OCC-time)** —
   `MatrixIntersectionEngine::eligibleVenues($headcount, $sessionType, $venueId)`
   (call sites: engine `:57` validate path, `:207`, `ReplacementArrangement.php:461`).
   GAP the design must solve: the engine signature lacks **module code +
   session/cohort context** — the matrix needs a session-scoped eligibility
   (delegate through a rule check carrying the session entity/facts, reusing
   the R2 pattern check). Proposal/design territory; the MATRIX is frozen here.
2. **Arrangement client matrix** — `replacement-arrangement.blade.php`
   `venueEligible(v, ctx)` + `buildVenueFilter()`; `courseCtx()` already
   carries the module `code` + `type` + `cohorts` + headcount — read the new
   keys; `#venueCountNote` copy unchanged (its count data gains labs).
3. **Producer modal + fallback** — `VenueTimetable.php:95`
   `sessionAllowedForVenue(sessionType, rawRoomType, session, venueCode)` —
   already receives the whole session; extend its internals (rule read +
   gates) with no signature change.
- Read-only inventory (`ApiReadController`'s direct `allowed_session_types`
  read) UNTOUCHED — Q6 = no migration, the column stays `'P'` for B006 with
  the doc note carrying the module-conditioned truth.

## 5. Rejected approaches and WHY (the record)

- **Slicing into 2 lanes** (R2 second) — engine/client drift window between
  slices; double test batteries; the single rule source argues one pass.
- **AMIT2034 strict labs-only (my first Q2 form)** — SUPERSEDED by the user's
  revision: networking L/T keep their R1 rooms; no labs-exclusive module.
- **`modules.category` / registry table** — schema surgery for a frozen
  4-code list; consumers already read `ruleMap()`.
- **Column-widening migration (Q6 opt 2)** — cannot express module
  conditioning (would over-admit non-networking L/T); a demo-DB write purely
  for display truth; ruleMap is already the engine's authority.
- **Data repairs / re-rooms of violating rows** — diverges from the imported
  source PDF; the user defers repairs ("if I've time in future"). The lane
  records the disposition list (§1) — zero DB writes.
- **Removing the existing B005 flag family (Q4 opt 2)** — kills an approved
  surface + the modal's offering for AMIT2034 P; contradicts "leave as-is".
- **New derived-conflict families for the silently-tolerated violations** —
  the silence law forbids them.
- **Doc-side priority model (pre-2026-10-24)** — retired by the user in the
  `venue-r3-r4-networking-iot-doc` round 2 (ALLOW / DISALLOW only).

## 6. Bookkeeping riders inside the lane

- `public/js/mock-data.js`: AMIT2034's stale combined fixture (DFT+DSF, 50,
  the `dft2s1` rows) → the DSF-only truth (15; the `dsf2s1` rows) — the R7
  drift-guard law ("update both or drift is on the editor").
- `docs/venue-restrictions.md`: amend ⚠️1 (Q6 = no migration; ruleMap is the
  sole authority) + flip R3/R4 status words from DOC-ONLY/PLANNED-NEW to the
  enforcement receipts; §4 updates.

## 7. Out of scope (explicit)

- Any demo-DB write (no migration; snapshot ceremony not needed).
- Any new UI flag/warning/conflict surface; any copy change beyond data.
- The R4/IoT directives (dropped — `venue-r3-r4-networking-iot-doc`).
- An admin UI for the module/code lists.
- The `b005-diploma-conflict` family's behavior (frozen; specs locked).
- Booking-path features outside venue eligibility (waves/proposals like
  `full-block-reservation` remain their own lanes).

## 8. Test + receipt plan (acceptance agreed Q7)

- NEW spec family (three layers): service tests for
  `sessionAllowedForVenue` + the engine's session-scoped eligibility
  (matrix table above incl. R6/R2/engine-negatives); Playwright rows:
  the producer modal gains labs for networking L/T ctx; the arrangement
  dropdown gains labs for the same ctx; non-networking ctx unchanged rows
  (BMIS2003/AMCS1013).
- Standing gates: spot battery `--grep-invert "TC70"` (the described reds
  stay red-labeled); Feature suite green (`VenueRestrictionConflictTest`,
  seed invariants untouched); pint + phpstan-1G(0); phpunit re-run declared.
- Bookkeeping receipts: the mock mirror diff; the doc diff.

## 9. Open questions carried into proposal (NONE block the matrix)

- The engine session-scoped shape (signature change vs new helper) — design
  territory; the matrix + gates are frozen here.
- Whether `b006_banned_programmes` stays an explicit key or folds into the
  FOCS allow-list (fail-closed semantics) — design preference.
- AMIT2034's L (15 std) also admissible in a tutorial room per the revised
  law (L→tutorial is base-R1 legal) — odd-looking but per the user's words.
