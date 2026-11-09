# proposal — `b005-diploma-allowed` (2026-10-27)

## Why (the user's ruling)

"i think let's remove the B005 restrictions, by allowing Diploma cohorts
to use it too." — the R2 law (B005 no-Diploma) dies as a RULE. The
standing AMIT2034/P session #3 (DSF-only 21 students, Wed 11–13 @ B005)
then renders **clean** on every surface (no more perpetually-red block,
no more conflict popups, no more hidden B005 in the picker) — the reason
the user has cited repeatedly this session.

## The rule's current anatomy (grep-verified, 4 code sites + docs)

| Layer | File / anchor | Key |
|---|---|---|
| rule source | `LectureSessionCatalog::ruleMap()` — `b005_no_diploma_pattern` + `b005_venue_code` | data |
| PHP gate | `LectureSessionCatalog::sessionAllowedForVenue()` — the R2 deny block (~:248) | code |
| conflict WHY | `LectureSessionCatalog::conflictFacts()` ④ — 'Venue Restriction (B005 no-Diploma)' (~:525) | code |
| render conflict | `ResolvesTimetableTimeline::venueRestrictionConflict()` (+ ReplacementArrangement/ReplacementHome calls, `status: 'conflict'`) | code |
| client matrix | `replacement-arrangement.blade.php` `venueMatrixAllows()` — the `b005_venue_code` deny arm (:3209-3215) | code |
| tests | 13 test files grep `B005/b005` — 28 hits (`VenueRestrictionConflictTest`, `EnforcementEngineValidateSlotTest`, `EnforcementProducerPoolTest`, `LectureSessionCatalogTest` (the ONE `b005_no_diploma_pattern` shape assertion), `LtVenueCapacityRuleTest`, `ReplacementArrangementTest`, `ReplacementHomeTest`, `SessionAllowedForVenueMatrixTest` + the 4 Playwright spec anchors) | tests |

## Scope (the 4 code sites + the tests + the docs)

1. **ruleMap(): the two b005 keys DIE** (`b005_no_diploma_pattern`,
   `b005_venue_code`) — the shape loss is the honest statement that the
   rule is REMOVED (not stubbed); all consumers go in the same change
   (the fail-closed matrix would otherwise deny everything).
2. **sessionAllowedForVenue(): the R2 deny block DELETED** — the VOID
   where it stands must never produce a hidden-lab drift (the lab
   widening logic above it composes on its own).
3. **conflictFacts() ④ DELETED** — with the rule dead, no B005 session
   can produce a venue-conflict fact.
4. **venueRestrictionConflict() DELETED** + both call sites' `status`
   and the derive-branch clients cleaned (`ReplacementArrangement.php`
   :297 / `ReplacementHome.php` :76).
5. **the client matrix's B005 arm DELETED** (the blade's
   `venueMatrixAllows()` :3209-3215) — B005 re-enters the visible picker
   for every session eligible by type+module (type-row 'lab' per ruleMap
   P; the OLD AMCS2093 hidden-B005 report stays — that was a capacity
   truth when the session had DFT/DSF cohorts over capacity; now with
   21/22 students it is legit).
6. **tests re-cut** (the honest editor): the R2 receipts swap from
   "B005 denies D* cohorts" to "B005 admits a D*-cohort session when the
   type row allows" (a positive receipt is more useful than a deletion);
   `b005_no_diploma_pattern`'s shape assertion dies; the Playwright
   extension specs' B005 arms re-point (AMCS2093's picker receipt flips
   from "B005 hidden" to "B005 offered").
7. **docs**: `docs/venue-restrictions.md` R2 section re-homes under a
   'REPEALED (2026-10-27, the user's ruling)' heading with one sentence
   of what remains (nothing — R3/R4/R6's scope are unrelated and STAY);
   `CodingMAIN.md`'s R2 mentions strike if any.

## The standing data that changes meaning

- AMIT2034/P session #3 (DSF2-only, 21 ≤ 28) — renders CLEAN immediately.
- NO DB writes (the venues table's `allowed_session_types`/description
  stay as their own reference inventory — untouched, per the same
  deliberate Q6 precedent).

## Acceptance

1. `php artisan test` 262+ green with the re-cut receipts (the R2 misses
   now positive).
2. Playwright: the AMIT2034(P) block at W4 without the conflict styling
   (clean surface); the arrangement picker shows B005 for the
   AMCS2093(L) session whose capacity now fits.
3. The rule map's PHP + JS shapes agree (no residual b005 keys
   anywhere — the grep receipt).
