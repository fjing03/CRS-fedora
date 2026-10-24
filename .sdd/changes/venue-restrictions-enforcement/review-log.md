## proposal Round 1 — 2026-10-24
### 🟡 Addressed (reviewer's two should-fix)
- Doc rider widened: the proposal's impact-scope now pins the FULL doc fix
  (R3's AMIT2034 paragraph rewrite — the doc still carries the superseded
  labs-exclusive law, §4's phrase, R5's violation inventory reword,
  R6's AMCS1013 cross-ref reword) so the rider cannot be executed narrowly.
- Gate-composition ruling added: R2/R6 are venue-scoped overrides composited
  for EVERY session type — the lab widening never reaches past them
  (AMIT2033 L keeps B005 hidden for its D* cohorts).
- 💡 adopted: the ruleMap() stale comment flip added as an explicit file.

### Reviewer verdict (round 1, verbatim tail)
"Not ready to freeze yet — one focused revision round. No 🔴 issues ...
Before freeze: fold the expanded doc-rider list (🟡1) ... and add the
one-sentence gate-composition ruling (🟡2) ..."
## proposal Round 2 — 2026-10-24
### 🟡 Addressed (the reviewer's three missing rider bullets)
- R2's audit bullet, the file-preamble narrative, §3's layer-map rows added
  to the doc-rider enumeration (declarative; the receipt list is now
  exhaustive per both rounds).
### 💡 Adopted
- "Feature suite untouched in CODE but MUST STAY GREEN when run" clause.
- The R3 rewrite must state AMIT2034's place in the B006 roster explicitly.
### Verdict (round 2, verbatim head)
"Conditionally ready to freeze. No 🔴 issues … Condition: fold the three
missing rider bullets (🟡) plus the two 💡 phrasings into the proposal, then
freeze — these are enumeration/completeness additions, not decision-level
changes, so no downstream re-review chain is needed."
→ Condition met; **proposal.md FROZEN** (rounds: 2).
## design Round 1 — 2026-10-24
### 🔴 Fixed
- The module-source decision pinned in D2: NO extra param — the gate reads
  `$session->module->module_code` inside, FAIL-CLOSED (unloaded relation =
  base rows only, never widens, never throws).
### 🟡 Fixed
- Eager-load contract at the submit path pinned (`['module',
  'cohorts.programme']` loaded; fixture-verified).
- D2 stated as an explicit boolean FORMULA (no early-return chain)
  + acceptance anchors.
- The client fallback (consumeBookingIntent prefill) folded into the same
  client composition (one-source drift closed).
- The modal receipt fixed to a CONFLICTED networking-L fixture (the pool is
  conflicted-only ∩ venue-admitted); dangling parenthetical removed.
### 💡 Adopted
- `b006_venue_code` key; PHPDoc stale-sentence flips; the client R6
  programme extraction via alpha-prefix of cohortCodes; modal
  capacity wording aligned in the FROZEN proposal (declarative
  soft-freeze: "(the service gate excludes CAPACITY …)"); the
  cohort-less fail-open note.
### Reviewer verdict (round 1, verbatim head)
"Not ready to freeze — one focused design revision round."
## design Round 2 — 2026-10-24
### 🟡 Fixed
- D5 pinned R4's doc row = the DROPPED receipt (never "enforced") + the
  ruleMap comment must state the disposition.
- D6 gained the client-side NEGATIVE dropdown rows (BMIS2003/AMCS1013 —
  the only assert catching a client type-check drop).
### 💡 Adopted
- The ruleMap comment records b006_allowed_module_codes' current
  subsumption note; the suite host resolved in the tasks batch.
### Reviewer verdict (round 2, verbatim head)
"Conditionally ready to freeze. No 🔴 issues remain … After folding,
design.md may freeze and the next batch (specs/) may be created."
→ Condition met; **design.md FROZEN** (rounds: 2).
## design Round 2 (post) + specs Round 1 — 2026-10-24
### specs review findings → fixed
- 🟡 b006Widen's deliberate-redundancy intent sentence added (R2).
- 🟡 fixture #7 carries the DSF·1S1G1 cohort context (no vacuous pass).
- 🟡 4.2 names the load site (the action calls $session->load([...]) at
  SubmitReplacementRequest.php:49).
- 🟡 D0's stale ?string $moduleCode = null signature deleted; D1's array
  gained 'b006_venue_code' => 'B006' (declarative design soft-freeze
  reconciliation; no decision change).
### 💡 noted
- suite host resolved in the tasks batch.
### Reviewer verdict (verbatim)
"Ready to proceed into the tasks batch once the four 🟡 items are folded
in. All five findings are spec-local/declarative — no unfreeze of
proposal.md or design.md is required … After fixing, re-review the
current batch for confirmation and freeze the spec."
→ folded; **specs/ FROZEN** ( declarative confirmation round follows).
## tasks Round 1 — 2026-10-24
### 🟡 Fixed
- T7 now mirrors the proposal's master rider list VERBATIM + adds the three
  dropped receipts the reviewer verified missing (R2's audit bullet, the
  preamble narrative, §3's layer rows) AND reconciles the §2 slip (the
  doc's allowed_session_types row contradicts Q6; §2/§4 both covered now).
- T5 extended to fixture #14 (the gate-ignores-capacity assert) + hosts
  split (truth table → tests/Unit; engine rows → tests/Feature).
- T2's abandoned drafting fragment deleted.
- T4 typo (the usual stale-serve ritual).
### Reviewer verdict (round 1, verbatim head)
"Not ready to freeze — one focused fix round, tasks-only. No 🔴 issues …"

## tasks Round 2 (post) — 2026-10-24
Verdict: "PASS — tasks.md is ready to freeze." -> frozen. The three
optional one-liners folded post-freeze (declarative): the dsf2s1 row-set
wording, the "#14" typo, the one-row fail-closed gate assert. ALL FOUR
ARTIFACTS FROZEN.

## apply receipts — 2026-10-24 (all 8 tasks)

- T1 ruleMap keys + the fail-closed gate formula in place (spec R2 exact).
- T2 engine: sessionVenueEligible + the validateSlot optional-session
  switch + blockedVenuesPayload per session (a venues query HOISTED out of
  the loop — a perf slip caught before commit).
- T3 the caller passes $session + loadMissing(module, cohorts.programme).
- T4 the ONE client helper venueMatrixAllows; venueEligible + the
  consumeBookingIntent prefill delegate (the design's fallback drift
  closed in code).
- T5 the 14-row truth table (18 asserts incl. capacity-ignored +
  fail-closed fixtures) + the engine OCC rows (7) — the busy-vector trap
  in the fixtures was found by the first run and re-anchored (the slots
  moved to 14:00 clear of the session's own 08:00 cells).
- T6 Playwright 5/5 (the ?session=10 ctx = BMIS2003, not AMCS1013 — the
  assert corrected from the REPL DUMP, never invented); the modal browser
  row BLOCKED honestly (no conflicted networking-L on the demo deck) and
  covered by EnforcementProducerPoolTest (3/3) on the disposable deck.
- T7 the mock mirror (module entry + 3 dft2s1 rows dropped + the dsf2s1 P
  timing normalized to the DB's 2h) + the FULL doc rider
  (preamble/R2/R3+another / R4 = dropped / R5 / R6 / §2 row / §3 rows /
  §4 = LANDED).
- T8 gates: pint FIXED (import order) then passed; phpstan-1G 0; phpunit
  258/258 (LectureSessionCatalogTest's pre-R6 fixture pinned to RSD + the
  R6 negative added — the old 'combined DFT+DSF' era fixture now honors
  the fail-closed eager-load contract); scratch probes purged; scratch
  reviewed for invent-data hazards: none.

## verify — 2026-10-24 (post-apply audit, SDD verify recipe)

- Tasks 8/8 [x] verified in tasks.md.
- Spec R1.1/R1.2 (keys + no DB path) — grep receipts: the five keys in
  ruleMap; NO migration file in the diff; both allowed_session_types
  columns untouched (verified live earlier: 'P').
- Spec R2 — the ONE formula present in the gate (no early-return
  bypasses); the fail-closed module/cohort reads in place (the two
  dedicated fixtures prove both).
- Spec R3 — the 14-row truth table = SessionAllowedForVenueMatrixTest
  (18/18 incl. fixtures beyond) — PASS.
- Spec R4.1–R4.5 — the signature unchanged; validateSlot optional session
  + the action call site + the ONE client helper (three consumers
  delegate) + the per-session blockedVenuesPayload + findAvailableSlots
  untouched (the verify round ADDED the design D3 doc-comment line that
  the apply missed — the mode: preview vs authoritative is now stated in
  the source).
- Spec R5 (the silence law) — the blade diff carries zero copy strings;
  the b005-diploma-conflict family untouched (occurrenceFact greps clean
  in the diff).
- Spec R6 — the mock mirror + the full doc rider verified in the diff.
- Gates re-run on the amended tree: pint passed; phpstan-1G 0; the new
  suites 28/28.
### Verdict
PASS — ready to archive with /sdd-archive venue-restrictions-enforcement.
