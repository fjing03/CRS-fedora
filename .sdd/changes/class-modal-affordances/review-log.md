# review-log — class-modal-affordances

## proposal Round 1 — 2026-10-24 (verbatim key findings)
- 🔴1 the ui-cancel-class-modal include re-homing missing from impact scope.
- 🔴2 the fixture regime decision missing; the persistence/honesty
  semantics unrecorded.
- 🟡 the footer-right positioning on pages lacking .modal-footer-right;
  the venue row set undercommitted ("possibly"); the cohort guard-context
  sentence; the RETIRED typo; the 14-row count (the file carries 14
  test() rows, not 15).
- Verdict: "Not ready to proceed".

## proposal Round 2 — 2026-10-24
### Fixed
- 🔴1: the include re-homing added to item 1 + the impact scope.
- 🔴2a: the FIXTURE REGIME pinned (re-anchored to the real-DB deck, Actors
  verified: 5770 owns BMIT2013 L Mon B009 + AMCS2093; 2873 owns AMIT2014;
  per-row receipts; never faked).
- 🔴2b: the PERSISTENCE DECISION recorded — the user's verbatim intent:
  RESTORE-FIRST (the CancelClass action stays unrouter). The 'NO JS
  changes' claim made conditional (the venue toggle = page JS).
- 🟡 folded: the footer-right contract for pages without the section
  (+ the student-pages blast-radius bound); the venue row set committed
  in full; the cohort guard-context note (design to pin the source rows);
  RETIRED→RETAINED; 14-row count corrected.
### Verdict pending re-review.

## proposal Round 2 (post) + Round 3 — 2026-10-24
The round-2 re-review found ONE new 🔴: the venue blade lacks the
MockData.currentUser hydration (the ownership guard reads it) — folded
as a ONE-LINE bridge addition in item 1, plus sentence-level folds:
my-timetable's include sits beside its INLINE modal (not the shared
partial), the anchoring weeks' status facts are part of the fixture
contract, the student-pages inventory sentence clarified (no wiring, the
partial's default footer). Round 3 re-review tabled (small pass expected).
### 🔴 Outstanding
- none (pending the round-3 read).

## proposal Round 3 — 2026-10-24
### Fixed
- The regime paragraph rewritten: the day-of-week anchor corrected (DB
  day_of_week 1 = TUESDAY — the earlier "Mon B009" was the mock's
  day-label slip) + the anchoring weeks' status facts pinned as the
  fixture contract + the venue named explicitly in the footer contract
  (a first-class page carrying BOTH restored buttons).
### 🔴 Outstanding
- pending the round-4 read (expected: freeze).

## proposal Round 4 — 2026-10-24
### Verdict (verbatim)
"PASS — proposal.md is ready to freeze. … freezing now is the correct
call" (round 4/5). The student-page ambiguity fixed declaratively (they
do NOT gain the cancel-modal include). **proposal.md FROZEN.**

## design Round 1 — 2026-10-24
### Verdict (verbatim)
"PASS with the three 🟡 folds before/at apply." — folded declaratively:
the payload `mine` fallback note (D2), the Blade footer-order note (the
@hasSection reads after the include), the per-page onCancelled port
columns. **design.md FROZEN (round 1).**

## tasks Round 1 + Round 2 — 2026-10-24
- R1 findings: one 🔴 + three 🟡 (the head was elided in my read; the R2
  fresh read stated them: the missing T8 ledger-run task vs the header
  reference; the include-add wording on the blades the UI never received;
  the T5 reconciliation with the frozen proposal's row set; the named
  partial + the style re-homing home).
- R2 verdict: "NOT ready as-is — one 🔴 + three 🟡 … expect PASS next
  read". The four fixes applied (T8 added; the include-add steps made
  explicit on ALL THREE blades; T5 the home DECIDED; the partials named
  in the BEFORE clauses; the receipts home = the spec's comment lines).
- The 💡 folded: T7 names the exact gate file set.

## tasks Round 3 — 2026-10-24
### Verdict (verbatim head)
"🟡 PASS — ready to freeze. No critical issues … none block proceeding to
/sdd-apply." The three 🟡 folded: the lint gate named exactly
(composer run lint:check + phpstan-1G), the header parenthesis closed,
"running" the ritual. **tasks.md FROZEN (round 3).** ALL FOUR ARTIFACTS
FROZEN — ready for /sdd-apply.

## apply receipts — 2026-10-24 (T1–T7)
- T1/T2/T3: the three live blades wired (renderButton + the include + the
  footers BEFORE the include; cohort = BOTH buttons venue-only; my/venue's
  currentWeek for the runCallback; the venue got BOTH buttons + the
  ONE-LINE currentUser bridge; the payload events got the additive
  sessionId (the grid's id = the SLOT id — the handoff needs session ids)).
- T3's gates re-verified: the W5 Wed B002 destination is PAST → the S17
  honest skip receipt (the S5 guard IS the consumed row's behavior); the
  statement of the anchor replacement week = the W5 receipt.
- T4: the 14 rows.REANCHORED — the file storageState = 5770; ME = the DB
  name 'Lim Jia Zheng'; the faculty walk receipt (the AMIT2014 = the 2873
  row) + the counter-example cohort walk; the S3/S12/S13b/S17 honest
  skips (the regime clause); the S9/S11-s condition = the live E2A facts
  (the 'no longer in the timetable' hint = the OLD mock's store — the
  bridge solved); S10 = the SESSION-shape handoff receipt (?session=24&
  week=11&from=my-timetable — the pinned-occurrence shape).
- T5: the cohort/venue rows green (the DFT2(S1)G1 label COMPACT — the
  spaced regex initially missed; the AMIT2014 2873 rows + the venue
  B107's W10 rows); the venue's ROUTE = /venue-timetable-ui (the ui-safe
  redirect; the probe caught the 404).
- T7: gates — pint passed; phpstan-1G 0; phpunit 258/258; the spot
  battery 25/25 --grep-invert TC70; the lane spec 6/6; the cancel-class
  family 13 exec + 4 honest skips.
### Verdict
READY — the review-log verify receipt appended (the apply follows).
