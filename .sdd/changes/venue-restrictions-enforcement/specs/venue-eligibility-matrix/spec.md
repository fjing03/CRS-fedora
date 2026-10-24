# spec — capability `venue-eligibility-matrix`

The single contract for the ALLOW/DISALLOW matrix
(`venue-restrictions-enforcement`). owning proposal/design are FROZEN;
requirement numbering here is the acceptance ledger for
`/sdd-verify`.

## R1 — the rule keys ship from ONE source

1.1 `LectureSessionCatalog::ruleMap()` carries, as JSON-able data:
`lab_access_module_codes = ['AMIT2033','BMIT2154','BMIT3084','AMIT2034']`,
`b006_allowed_module_codes = [same four]`, `b006_focs_programmes =
['DFT','DSF','RSD']`, `b006_banned_programmes = ['RAF','RBU']`,
`b006_venue_code = 'B006'` (NEW), plus the unchanged existing keys
(P/T/L rows, `b005_no_diploma_pattern`, `b005_venue_code`).
1.2 NO DB column/table changes; NO config file; the keys reach the pages
through the existing `PAGE_DATA.ruleMap` plumbing unchanged.

## R2 — the gate formula (the ONE composition; no implementer re-invention)

`sessionAllowedForVenue(sessionType, rawRoomType, session, venueCode)` —
unchanged signature — computes:

    allowed = (baseRow OR labsAdditive OR b006Widen)
              AND NOT b006PopulationDeny
              AND NOT b005DiplomaDeny

with:
- `baseRow`: `rawRoomType ∈ ruleMap[type]` (P→lab+cisco_lab; T→tutorial;
  L→tutorial+lecture_hall).
- `labsAdditive`: type ∈ {L,T} AND module ∈ `lab_access_module_codes` AND
  `rawRoomType ∈ {lab,cisco_lab}`.
- `b006Widen`: `venueCode === 'B006'` AND type ∈ {L,T} AND module ∈
  `b006_allowed_module_codes` (deliberately REDUNDANT with
  `labsAdditive` while the rosters coincide — kept separate so a future
  B006-roster divergence can never regress silently).
- `b006PopulationDeny`: `venueCode === 'B006'` AND ( NOT (every cohort's
  programme ∈ `b006_focs_programmes`) OR ANY cohort's programme ∈
  `b006_banned_programmes` ) (fail-closed on an EMPTY allow-hit even with
  cohorts present; vacuous-true only when the session has NO cohorts —
  recorded state, unreachable in the DB today).
- `b005DiplomaDeny`: `venueCode === 'B005'` AND ANY cohort programme_code
  matches `b005_no_diploma_pattern` (composed for EVERY type).
- Module source: `$session->module->module_code` read INSIDE the gate;
  fail-closed (unloaded/missing relation = in NO list; never widen, never
  throw).
- The gate is ONE boolean expression (no early-return chain that could
  bypass the deny gates); capacity is NOT part of this gate (engine-side).

## R3 — negative/positive truth table (the fixtures backing this spec)

| # | Context | Venue | Expected |
|---|---|---|---|
| 1 | AMIT2033 L (DFT·2S1G1) | B111 (lecture_hall) 80 | ALLOW (base row) |
| 2 | AMIT2033 L | B010 (lab) 28 | ALLOW (labsAdditive) |
| 3 | AMIT2033 L | B006 (cisco_lab) 32 | ALLOW (b006Widen) |
| 4 | AMIT2033 L, D* cohorts | B005 (lab) 28 | DENY (b005DiplomaDeny over the widening) |
| 5 | BMIS2003 L | B010 | DENY (no base row, not in any widen list) |
| 6 | BMIS2003 L | B006 | DENY (R6 population aside, no widen for the module) |
| 7 | AMCS1013 P (DSF·1S1G1 — the cohort MUST be in the fixture, else the R6 gate passes vacuous-true and proves nothing) | B006 | ALLOW (base P row + R6 gate hit) |
| 8 | AMCS1013 T | B006 | DENY (T, non-networking) — silently tolerated as an imported row, forbidden as a NEW booking |
| 9 | any RAF/RBU-cohort session | B006 | DENY (R6 banned) |
| 10 | AMIT2034 P (DSF·2S1G1) | B009/B010/B011 | ALLOW |
| 11 | AMIT2034 P | B005 | DENY (R2 — the standing violation is NOT bookable) |
| 12 | AMIT2034 T (15 std) | B005 | DENY (R2, D* cohort) |
| 13 | AMIT2034 L | B111 lecture_hall | ALLOW (the base L row stands; the old labs-exclusive DENY is repealed — the R4 note below) |
| 14 | any session (headcount 15 ≤ capacity 28) | B010 (lab) | ALLOW (rows); capacity is the engine's separate check (never this gate) |

> R-note on #13: the user's final revision — networking (incl.
> AMIT2034) L/T use non-lab venues AND labs; nothing is labs-exclusive.

## R4 — the three consumers apply R2 verbatim

4.1 Producer modal pool (`VenueTimetable::intentSessionOptions` →
`sessionAllowedForVenue`): unchanged signature; rows appear per R2/R3.
4.2 Submit-side engine: `validateSlot` gains the trailing optional
`?ClassSession $session`; when present the venue admission =
`sessionVenueEligible(session, venue)` = capacity ≥ requiredHeadcount
AND the gate; `SubmitReplacementRequest` passes its `$session`
(eager-load contract: the ACTION calls
`$session->load(['module','cohorts.programme'])` at `SubmitReplacementRequest.php:49`
— `$this->load` must NOT be swallowed by a duplicate `only` — a fixture proves
the degrade path (relation UNLOADED = base-rows-only, never widen/throw)). A failing gate =
the existing `engine_precheck_failed` loser shape; NO new UI copy.
4.3 Client matrix: `venueEligible(v, ctx)` + `buildVenueFilter()` +
`consumeBookingIntent`'s prefill predicate ALL read the composition from
`window.__ruleMap` (one client helper, no copy deadlines); the.client
programme extraction = the ALPHA PREFIX of the stripped `cohortCodes`
label, exact-matched.
4.4 `blockedVenuesPayload` uses the session-scoped gate (the grid's
per-venue "occupied" truth becomes module-conditioned, data-only).
4.5 `findAvailableSlots` (ApiReadController) UNTOUCHED (audit preview,
documented).

## R5 — the silence law (negative requirements)

5.1 NO new UI copy, flags, warnings, legend rows, chips anywhere.
5.2 The `b005-diploma-conflict` family bytes stay identical (auto: this
spec adds nothing to it).
5.3 No migration; both `allowed_session_types` columns byte-identical.

## R6 — bookkeeping receipts

6.1 `mock-data.js`: AMIT2034 module + row sets → DSF·2S1G1 only (15);
the `dft2s1` AMIT2034 rows removed.
6.2 `docs/venue-restrictions.md` rider lands per the frozen proposal
enumeration (incl. R4 = the DROPPED receipt, never "enforced"; R3's
AMIT2034 paragraph; R5/R6/§3/§2 wording; ⚠️1).
6.3 Stale in-code comments flip (ruleMap "PLANNED-NEW", catalog PHPDoc).
