
## proposal Round 1 — 2026-10-09
### 🔴 Outstanding
- (none — conditions carried into design.md, per reviewer: twin-merge severity interaction 🔴-1, status precedence chain 🟡-1, B006 negative test 🟡-3, cohort owner-gating rationale 🟡-2)
### 🟡 Addressed
- Optional notes acknowledged for design: approved-replacement-in-B005 edge, `D*` prefix assertion, `event-public-holiday` dual-meaning precedent.

**Verdict: PASS** — proposal.md is FROZEN as of this round.

## design Round 1 — 2026-10-09
### 🔴 Fixed (this pass, before re-review)
- **🔴-1 cohort owner-gating mechanism**: `e.isMine` does not exist in trait payloads (the cohort blade's mine/pending branches are pre-existing dead code). Fix chosen: **option (b)** — name-comparison gate in the NEW conflict branch only (`e.lecturer === MockData.currentUser.name`, the upstream template idiom; `MockData.currentUser` verified set at cohort-timetable.blade.php:101). Dead `isMine` branches untouched (pre-existing, out of scope). Venue row gates on its own existing `mine` key; key names now stated per page.
### 🟡 Addressed
- 🟡-1: venue pending-vs-conflict tie-break pinned (derived conflict overrides slot-pending too, consistent with the reduceTwins map); the factually wrong "no pending in venue view" claim corrected.
- 🟡-2: null-safe programme access (`$c->programme?->programme_code`) for phpstan.
- Optionals: §2 phrased as defensive-contract (real twins impossible pre-Slice-B); test 6 phrased "no non-diploma D* code" (not "exactly").
### 🔴 Outstanding
- (none — pending Round 2 confirmation)

## design Round 2 — 2026-10-09
### 🟡 Addressed (soft-freeze additions before freeze)
- Cohort conflict branch placement pinned: AFTER the existing holiday `isConflict` branch (holiday wins, matching modal precedence ui-common.js:920–925).
- Venue eager-load note: add `classSession.venue` to avoid lazy-load N+1 in `venueRestrictionConflict`.
### 🔴 Outstanding
- (none)

**Verdict: PASS** — design.md is FROZEN as of this round.

## tasks Round 1 — 2026-10-09
### 🟡 Addressed (declarative, before apply)
- T6 + design §6: venue-db gate count 3/3 → 4/4 (T5 adds one Daniel-login test to venue-db.spec.ts; header comment updated; rate limit unaffected — fresh ID).
- Commit mapping: explicit feat-commit task added (component + trait + cohort blade + tests + changelogs) before the archive task.
- T5 named venue-db.spec.ts explicitly (not the parked mock spec).
### 🔴 Outstanding
- (none)

**Verdict: PASS** — tasks.md is FROZEN as of this round. Proceed to apply.

## apply + verify — 2026-10-09
### Gates (final)
- pint ✅ · lint:check ✅ · phpstan --memory-limit=1G 0 ✅ · phpunit 129/129 (845 assertions; +6 conflict tests) ✅ · venue-db 4/4 (live Daniel-5652 owner view of the conflict block) ✅ · timetable-wiring 5/5 ✅ · nav-identity 3/3 ✅.
- Records-intact: no DB writes — `git show --stat c7a2cc6` contains no migration/seed files; RefreshDatabase confined writes to the testing DB.
### Apply-time deviations (ledgered, all behaviour-neutral)
1. **phpstan nullsafe → plain arrow**: design §1 / tasks T1 specified `$c->programme?->programme_code ?? ''`; shipped `$c->programme->programme_code` — the `programme` relation is neverNull (phpstan `nullsafe.neverNull` ×4); phpstan 0 confirms. Design §1 erratum applied in the archived copy.
2. **Venue slot-id vs session-id test matching**: venue payload events carry SLOT ids (`'id' => $slot->id`), so the four-pages test matches the venue part by `AMIT2034` + type `P` instead of session id (self-documented at the site).
3. **Attendee-cohort student selection**: cohort/student tests pick a student from session 38's actual first cohort (dataset-robust — an arbitrary DFT student may not attend the combined lecture).
4. **pint FQCN**: `\ReflectionClass` fully-qualified inline per pint's `fully_qualified_strict_types`.
5. **B006 negative test strengthened** beyond T4's wording: asserts NO B006 session ever derives conflict (networking and non-networking exhaustively) — a strengthening, not a deviation.
### Note
- User's parallel commit `f37307e` (seed RSD3G2 students) landed between the SDD docs commit and the feat commit — touches only `R*` cohort data, cannot flip the B005+`D*` derivation; gates ran at `c7a2cc6` HEAD (after it), so results are valid for the shipped tree.
### ✅ Verdict: VERIFY PASSES
