# proposal — venue-restrictions-enforcement (Wave 3b booking rules, SDD-law)

## Why

Today the venue-eligibility truth lives in ONE rule source
(`LectureSessionCatalog::ruleMap()` + `sessionAllowedForVenue()`) that only
carries R1's type→room rows and the B005 pattern — and only the CLIENT +
producer modal consume it; the authoritative submit-side engine
(`MatrixIntersectionEngine.eligibleVenues`) reads the map but knows nothing
about module identity, cohorts, or the R6 population gate. Meanwhile the
campus's real rules were finalized today (2026-10-24) in
`docs/venue-restrictions.md` (R3 = plain ALLOW/DISALLOW, AMIT2034's module
law, R6 = FOCS-only B006 gate, R4/IoT dropped, R2 engine-side) but remain
DOC-ONLY: a lecturer can book a replacement into a room the campus would
never allow, and the engine would happily validate it. Also the demo DB's
live rows already expose the shape of the debt (AMCS1013 T@B006,
BMIS2003 L@B006, AMIT2034 P@B005) — enforced correctly, the system stops
*creating* new debt while staying silent about the old one (the user's
explicit instruction, 2026-10-24).

## What this change does

Encodes the user-approved ALLOW/DISALLOW matrix as DATA in `ruleMap()`
(same single source, JSON-shipped to the pages — no schema, no admin UI)
and runs it at all three eligibility layers. **Gate-composition ruling
(2026-10-24): R2 (B005 no-Diploma) and R6 (B006 FOCS gate) are
venue-scoped overrides composited AFTER/ON TOP OF the session-type rows for
EVERY session type — L, T and P alike; the lab widening never reaches past
them (e.g. an AMIT2033 L is labs-eligible generically but B005 stays hidden
for its D* cohorts; B006 requires FOCS cohorts for every type).**

1. **The rule source** — `LectureSessionCatalog::ruleMap()` gains JSON-able
   keys:
   - `lab_access_module_codes: ['AMIT2033','BMIT2154','BMIT3084','AMIT2034']`
     — these modules' **L/T may ALSO use any lab** (`lab` + `cisco_lab`,
     i.e. B005/B009/B010/B011/B006), additive to R1's rows (their ordinary
     halls/tutorials stay legal);
   - `b006_allowed_module_codes: [same four]` — the L/T widening roster for
     B006 specifically (its P admission is already base-R1 for everyone);
   - `b006_focs_programmes: ['DFT','DSF','RSD']` and
     `b006_banned_programmes: ['RAF','RBU']` — the **R6 population gate**
     composited at check time for ANY B006 occupancy attempt;
   - the existing R2 keys stay (pattern `^\s*D`, venue B005).
   P sessions remain pure R1 (labs only) for every module; networking
   identity = the frozen 4-code list (adding a module later = user edits
   the doc + this array; no admin UI in this change).

2. **The three consumers** — the same matrix is applied by:
   - **submit-side engine** (authoritative, OCC-time):
     `MatrixIntersectionEngine::eligibleVenues(...)` — the design must give
     the check module/venue/cohort context (its callers today pass
     headcount+type+venue only); a session-scoped eligibility helper
     reusing the R2 pattern check is the anticipated shape (final call =
     design.md);
   - **client matrix** (`replacement-arrangement.blade.php`
     `venueEligible(v, ctx)` + `buildVenueFilter()`) — `courseCtx()`
     already carries the module `code`/`type`/`cohorts`; `#venueCountNote`
     copy unchanged (only the admission data changes);
   - **producer modal + fallback** (`VenueTimetable.php:95`
     `sessionAllowedForVenue(sessionType, rawRoomType, session, venueCode)`)
     — already receives the whole session; no signature change.

3. **Silence law (the user's "silently allow, don't mention")** — this
   change adds NO new UI surface: no flags, no warnings, no legend rows,
   no copy for R1/R2 violations. Imported violating rows stay as-is until
   the user repairs them (see Out of scope). The pre-existing
   `b005-diploma-conflict` flag family is frozen byte-identical (a prior
   approved SDD surface; its specs and test ledger untouched).

## Explicitly OUT of scope

- Any migration or demo-DB WRITE (Q6 = no migration; both
  `allowed_session_types` columns stay byte-identical; the doc's ⚠️1 note
  is amended instead).
- Any UI/copy change (99.9%-UI rule): no new flags/conflict families, no
  wording, no new chips; `venueCountNote`, legends, status labels
  untouched.
- The `b005-diploma-conflict` family's behavior (frozen; specs locked).
- R4/IoT (dropped by `venue-r3-r4-networking-iot-doc`); no IoT keys.
- Re-rooming/repairing the demo dataset (the user's future task):
  AMCS1013 T@B006, BMIS2003 L@B006, AMIT2034 P@B005 remain, silently.
- The wave's other lanes (`venue-page-book-affordance`,
  `full-block-reservation`, …).
- An admin UI for the module lists.

## Impact scope (files)

- `app/Services/LectureSessionCatalog.php` (rule data + the per-module
  check in `sessionAllowedForVenue`);
- `app/Services/MatrixIntersectionEngine.php` (+ its `eligibleVenues`
  callers: engine validate path `:57`, `:207`,
  `app/Livewire/ReplacementArrangement.php:461`);
- `app/Livewire/VenueTimetable.php` (modal pool filter — data-level only);
- `resources/views/livewire/replacement-arrangement.blade.php`
  (`venueEligible`/`buildVenueFilter` — data-level only);
- `public/js/mock-data.js` (bookkeeping only: AMIT2034 rows → the DSF-only
  truth — the R7 drift-guard law);
- `docs/venue-restrictions.md` — the doc rider is WIDE (mirrors explore
  brief §6): flip the rule statuses to enforced; amend ⚠️1 (Q6 = no
  migration, ruleMap = the sole authority); **rewrite R3's AMIT2034
  paragraph** — the doc still carries the SUPERSEDED labs-exclusive law
  ("L/T/P MUST run in a lab; R1's rows overridden for this module") that
  the 2026-10-24 revision repealed (networking L/T keep their R1 rooms,
  labs additive); **rewrite §4's AMIT2034 phrase** the same way; **reword
  R5's violation inventory** (AMCS1013 T@B006 and BMIS2003 L@B006 ARE
  type-vs-room violations now — silently tolerated, not "legal");
  **reword R6's AMCS1013 cross-ref** (its violation is the TYPE mismatch,
  not the population gate — its DSF·1S1G1 satisfies FOCS); AND three more
  verified-stale statements the round-2 audit found: **R2's audit bullet**
  ("combined DFT+DSF lecture" — the DSF-only·15/DFT-unlinked fact the code
  + mock already carry), **the file-preamble narrative** ("B005's no-Diploma
  derivation is client-side only today. R6 … likewise DOC-ONLY"), and
  **§3's layer map rows** ("Submit-side engine | R1 only" / producer-modal
  "R1 + R2"). The doc is
  behind the 2026-10-24 grilling decisions — this change brings it
  forward as part of the enforcement receipt (when rewriting R3, state
  AMIT2034's place in the B006 allow roster explicitly — the fourth code,
  not just the networking trio);
- **The rule source's stale comment** — `ruleMap()`'s inline comment says
  "R3/R4 stay PLANNED-NEW"; flipping that comment is part of the code
  tasks (otherwise the codebase gestures at doc-only rules it enforces);
- tests (NEW: service tests for `sessionAllowedForVenue` + the engine
  session-scoped eligibility; Playwright rows: producer modal + arrangement
  dropdown behaviors; the battery with `--grep-invert "TC70"`), the Feature
  suite is untouched in CODE but MUST STAY GREEN when run (brief §8:
  `VenueRestrictionConflictTest`, seed invariants — run, not skip).

## Acceptance picture (from /sdd-explore, user-agreed)

- Venue page "Select a Session" modal: networking L/T ctx now offers labs
  (the service gate excludes CAPACITY — the engine enforces it at submit;
  and the pool = conflicted-only ∩ venue-admitted); non-networking L/T ctx unchanged.
- Arrangement venue dropdown: same deltas; AMIT2034 P keeps B005 hidden
  (R2 client check — pre-existing).
- Engine: rejects non-networking L/T into B006, non-FOCS cohorts into B006,
  `D*` cohorts into B005 (R2 composes over the lab widening — networking
  L/T keep B005 hidden exactly like AMIT2034 P does) — silently (a
  rejection is an absence of the venue from eligible lists, no UI chrome).
- Zero visible copy changes; zero DB writes.
