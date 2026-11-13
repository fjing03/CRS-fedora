# Review log — cancelled-slot-reuse

## Proposal Round 1 — 2026-11-13

Independent read-only pi subprocess, model `commandcode/z-ai/glm-5.3-flash`; exit 0. Scope: Batch 1 only; no artifacts previously frozen.

## 🔍 Review Report: `cancelled-slot-reuse` (Batch 1 — proposal.md only)

*Scope: proposal.md in full, against explore-brief.md (baseline) and sdd.yaml. No artifacts frozen; design/spec/tasks intentionally absent and not penalised.*

### Brief ↔ proposal trace (all 9 user choices)

| Brief choice | Proposal coverage |
|---|---|
| 1 interval-only effect | §3.2 ✓ |
| 2 release room + lecturer + cohort | §3.1, §3.7, §3.8 ✓ |
| 3 both blocks on all pages, scoped | §4 table ✓ |
| 4 stack, cancelled above, row grows | §4 ✓ |
| 5 same class may re-request original slot | §3.6 ✓ |
| 6 single + whole-day alike, existing closure kept | §3.2, §3.4 ✓ |
| 7 fresh-only one-off cleanup, separately approved | §6, §2 ✓ |
| 8 plain green freed cells | §4 Arrangement grid ✓ |
| 9 hover task as separate part | §5 ✓ |

No non-goal violations found; the "not a leave calendar" and "superseded keep-room-blocked rule" boundaries from the brief are respected (§3.2, §5). Screenshots correctly treated as non-evidence of double booking (§1). Approval limits are explicit (§2).

### 🔴 Critical Issues
None. I could not find a scope, behaviour, or safety contradiction that would force rework. The write-gate structure (proposal → design/spec/tasks review → separate cleanup approval with printed target and receipt) is internally consistent.

### 🟡 Should Fix (before or during spec; not proposal blockers)

- **Cleanup ownership criterion is the linchpin of the one dangerous write, and "still owned by" is undefined.** §6.4 says release "only the original recurring reservation still owned by each cancelled occurrence" and never free a pending/approved request's cells "even if a legacy `class_session_id` still points to the old class" — but the proposal doesn't say what *does* distinguish an original reservation from a request-held one when ids are legacy-drifted. The refuse-and-report guard is good, but if the ownership test is wrong, every row fails (cleanup does nothing) or the guard is bypassed. This criterion should be pinned down as a named, testable rule in design, not left as prose. Why it matters: this is the exact write that touches the live demo DB.
- **Runtime release idempotency/scope is unstated.** §3.1 releases "its original room/time reservation" — nothing covers the occurrence whose reservation rows are missing, already released, or co-held with an approved make-up of another class (possible after legacy data drift). Acceptance 3 covers not freeing *later* bookings, but the runtime action's behaviour when there is nothing (or something unexpected) to release should be defined in spec: no-op gracefully vs fail, and never touch another class's cells. A silent wrong-scope release is the likeliest production failure of Part A.
- **Summary-card hazard is flagged but left as "follow their documented formulas"** (§4). If any card formula derives totals from rendered blocks/lanes rather than data, stacking lanes could inflate counts. Worth one explicit spec line: card totals computed from data only, layout lanes never counted. Cheap to state now, expensive to debug later.

### ✅ What's Done Well
- §3.8 ("merely hiding a red block is not a release") and the §3 example table pre-empt the classic frontend/backend disagreement failure mode.
- §6 rollback boundary is unusually honest: it distinguishes cell restoration from class reactivation and forbids blind re-occupation or snapshot-restore over newer work.
- Cleanup rehearsal explicitly includes already-released occurrences and reused cells, and the testing-only checker is called out rather than silently worked around.
- Part B is correctly fenced: hover explains active holders only, must not resurrect the old block rule or annotate green cells.

### 💡 Optional Suggestions
- §3.1's "Cancel one class occurrence → release…" could name the transaction/notification-after-commit ordering from the brief's module-boundary note, so the spec doesn't re-derive it (the brief already commits to it).
- §4's "deterministic order to be defined in design" is fine, but adding one concrete tie-break hint (e.g., start time, then class id) would save a design round-trip.

### ⚖️ Review Verdict
**PASS.** The proposal is internally consistent, fully traces to the brief's nine user choices, respects the write/approval gates, and contains no blocking contradiction. Proceed to Batch 2 (design) with the three 🟡 items carried forward as explicit spec inputs — the cleanup ownership criterion and runtime release-scope rule being the two that must not survive into spec as vague prose.


### 🔴 Outstanding

None. Proposal batch passed and is frozen. This is a document-review gate, not user approval to implement.

### 🟡 Carried forward to design/spec review

- Define a named, testable ownership rule for cleanup, including stale original class ids on reused slots.
- Define runtime no-op/refusal behaviour for missing, already released and unexpectedly held original cells; cancellation release must be transactional with notifications after commit.
- Pin data-based summary counts, independent of layout lanes, and deterministic stack tie-breaks.

## Stop boundary

Only the proposal, discussion record, metadata and this review log were created at the proposal gate. The user subsequently authorised planning through tasks, stopping before SDD Apply.

## Design Round 1 — 2026-11-13

Independent read-only pi reviewer; model `commandcode/z-ai/glm-5.3-flash`; exit 0. Full report follows.

All verification reads are complete. Here is the review report.

---

## 🔍 Review Report: `cancelled-slot-reuse` (Batch 2 — design.md)

**Scope:** `design.md` in full, validated against frozen `proposal.md` and `explore-brief.md`. `specs/` and `tasks.md` intentionally absent and not penalised. Baselines: explore-brief (9 choices), Proposal Round 1 🟡 carry-forwards, `sdd.yaml` (status: proposed, batch: proposal, frozen). No edits, no execution. **This is the design-freeze gate, not implementation approval.**

**Method note:** every substantive technical claim in D2–D10 was checked against the named current application files: `CancelClass.php`, `CancelClassBatch.php`, `ApproveRequest.php`, `ReleaseRequestSlots.php`, `SubmitReplacementRequest.php`, `OCCValidator.php`, `MatrixIntersectionEngine.php`, `ReplacementRequestMapper.php`, `ResolvesTimetableTimeline.php`, `CancelsClasses.php`, `VenueTimetable.php`, `StudentMyTimetable.php`, `ReplacementArrangement.php`, `replacement-arrangement.blade.php`, `venue-timetable.blade.php`, `ui-common.js`, `theme.css`, `routes/console.php`, and the `time_slots` / `audit_logs` migrations.

### Claim-validation results (all verified against real code)

| Design claim | Verdict |
|---|---|
| `ApproveRequest::releaseOriginal()` frees occupied rows by `class_session_id`+week with no newer-request exclusion (`ApproveRequest.php:286–310`) | ✅ Verified — and the hazard is worse than stated: legacy rows released by the *old* approval path keep a stale `class_session_id` (it is never cleared), so after an exact-original-slot rebooking, `releaseOriginal` would flip the approving request's **own landing cells** back to available while its junction rows survive — a corrupt state. The classifier's junction-ownership-wins rule (D3) is the correct countermeasure. |
| `CancelClassBatch` closes the source request but never frees original cells; non-strict `ReleaseRequestSlots` skips non-`pending` cells | ✅ Verified. Consequence confirmed live: closing an **approved** source request today deletes junctions while its `occupied` landing cells are skipped (`ReleaseRequestSlots.php:71–76`) — permanently stuck, invisible cells (orphan defence hides them in `VenueTimetable.php:~430`). D2's opt-in occupied-release policy is a real bug fix, not gold-plating. |
| Venue request pass depends on null `class_session_id`; released rows retain stale ids | ✅ Verified (`VenueTimetable.php` `$reserved = $slots->where('class_session_id', null)`; `releaseOriginal` never clears ids; OCC never touches the column). |
| `buildTimetableGrid` last-write-wins slotMap; `myTeachingStats` dedupes by `di:start` (`ui-common.js:905–920, 1077–1085`); venue same-start twin reducer merges by severity | ✅ Verified — `reduceTwins` would in fact merge a cancelled history block and a new pending block sharing `di:start` (a `cancelled` status isn't even in its severity map). The `eventKey` contract is the right fix. |
| Lecturer/cohort busy vectors exclude exception pairs; room availability reads slot state (`MatrixIntersectionEngine.php:443–460, 601–615`) | ✅ Verified — supports the release rule without engine changes. |
| Lock order: existing paths are request→slots (`ApproveRequest`, `ReleaseRequestSlots`, `OCCValidator` slots `orderBy('id')`); proposed session→request→slots introduces no cycle; "no slot-holder may later acquire a session lock" holds across all current callers | ✅ Verified. |
| `spanFromAnchor` legacy null/0 = one cell; `replacement_span` rides the request row | ✅ Verified (`ReplacementRequestMapper.php:~330`). |
| `cancelClassDay` currently runs withdrawals *outside* the batch transaction and even **after a refused batch** (`CancelsClasses.php:~200–215`) | ✅ Verified — D4's one-outer-transaction + no-withdraw-after-refusal fix matches the proposal's all-or-nothing boundary and fixes a real partial-failure path. |
| `class_cancelled` is a legal audit CHECK value; receipts ride `details` JSON with no CHECK change | ✅ Verified (widened 2026-08-24, 2026-10-27). |
| Testing checker `everyFiveMinutes` targets only `DB_DATABASE=class_replacement_testing` (`routes/console.php:23–30`); `--env=testing` ban meaningful (no `.env.testing`) | ✅ Verified. |
| `classifyState` precedence unchanged; the venue-only arm (`gridStatus===1`) currently has no holder naming; desktop grid-arm holder threading already exists via `cellData[3]` library indices; mobile timeline is a separate composition | ✅ Verified — D8's "thread ids into desktop AND mobile" is accurate about what's missing. |
| `venueSlotsByWeekPayload` exists and already carries holder ids; `roomBeforeNextEvent` lives in `venue-timetable.blade.php:470` | ✅ Verified. |
| `arrangement-cancelled-room-hover` folder exists with only `sdd.yaml` | ✅ Verified. |
| Proposal Round 1 🟡 carry-forwards addressed: named ownership rule (D3 ORIGINAL-RESERVATION), runtime no-op/refusal taxonomy (D3 table), data-based summaries + deterministic tie-breaks (D6/D7) | ✅ All three landed. |

Also checked: the lane algorithm (D6) is sound — cancelled-first allocation + the "strictly greater than every directly overlapping cancellation" constraint guarantees cancelled-above; half-open `[start, end+1)` makes the "09–10 and 10–11 may share a lane" claim consistent; the partial unique index `time_slots_no_double_book_idx` (`WHERE status IN (pending, occupied)`) is what makes D3's "exactly one physical row" and `mismatched occupied owner` meaningful (see 🟡2 below).

### 🔴 Critical Issues

1. **D5 cannot distinguish a user cancellation from an approval `official_event` stamp — and the reason value is shared.** `official_event` is both a user-selectable `CancelClass::REASONS` value and the stamp `releaseOriginal()` writes. D9 correctly says "not every `official_event` exception is a user cancellation" and keys the cleanup on `class_cancelled` audit identity — but D5's history builder says only "recurring session plus matching exception/cancellation evidence" and its overlap-retention rule ("retain its cancelled history if ANY in-scope active event overlaps… covers … another class reusing the old room") does not say **which originals** participate. Two implementers will diverge concretely:
   - Read one: only pairs with a `class_cancelled` audit emit history → approval-stamped pairs keep today's suppression in every case.
   - Read two: any exception emits history → every approved make-up whose old room/time later gets booked by another class (or by same-slot rebooking, possible **today** via the existing release-then-resubmit flow) suddenly renders a phantom red "Official Event" cancelled block above the landing, and `computeSummary`'s `Classes`/`hours` (`total = events.length − cancelled`) drops those counts — a visible behavior and card-number regression the frozen proposal never authorised (§4 scopes stacking to *cancelled* history; "Existing behaviour for approved make-ups at non-overlapping positions is otherwise unchanged" would silently acquire an overlapping-position exception that isn't in the proposal).
   It also risks contradicting the D9 inventory rule with a different discriminator in D5. **Fix:** one decision in design — cancellation history is emitted only for occurrences evidenced by a `class_cancelled` audit (same identity rule as D9); approval-stamped pairs never emit history and their suppression is unchanged in all cases. Without it, specs will encode the wrong behaviour and D7's "assert current no-reuse baseline stays identical" becomes untestable.

### 🟡 Should Fix

1. **D4's session-lock mandate vs `SubmitReplacementRequest`'s documented no-outer-transaction structure.** The action's header explicitly commits to "NO outer transaction around request-create + reserve — the validator commits its own audit rows; nesting would turn them into savepoints," and `OCCValidator` catches peri-flip failures *outside* its own transaction to re-write the surviving `occ_conflict` audit (FR 4.12). Holding a class-session lock across create+OCC requires a wrapping transaction, which changes: savepoint semantics for OCC's inner txn, where the conflict-audit rewrite lands, and how `compensateLoser()`'s pre-null-FK + delete interacts with the outer commit/rollback. It is workable, but D4 asserts the locking without addressing this documented structure — an implementer who naively wraps the action can silently lose FR 4.12's surviving conflict audit or alter loser-path compensation. Add one paragraph naming the mechanism (wrapping transaction + where the conflict audit must survive, or an alternative serialisation such as a short advisory/session-lock re-check).

2. **Duplicate *available* rows: `available` no-op or `ambiguous` refuse?** The partial unique index only constrains `pending`/`occupied` — the schema permits multiple `available` rows for the same coordinates. D3's ambiguity list says "duplicate rows" without qualification; a literal reading classifies a cell with two matching `available` rows as `ambiguous` → runtime cancellation refuses the whole selected batch. If duplicates can legitimately exist in imported grids, that's an over-strict refusal; pin that duplicate **available** rows classify as `available` (reported, no-op) and reserve ambiguity for duplicate/mismatched **occupied** rows (schema-impossible duplicates stay a defensive report).

3. **Student `cancelledReason` retention is ambiguous.** Today `StudentMyTimetable` receives `cancelledReason` (public `ReasonLabel` text) through `buildEventsByWeek`, and the proposal's privacy table says students get "no staff-only cancellation reason." D5 says "Student projections omit staff-only reason/detail fields" without saying whether the existing generic `ReasonLabel` label stays on student events. Dropping it would be a visible change to the student timetable; keeping it is presumably intended. State it, so the spec doesn't accidentally change existing student-visible content.

### ✅ What's Done Well

- The design's central diagnosis is correct *and understated*: the `releaseOriginal` stale-id self-release hazard and the approved-request stuck-cell bug are real, current-code defects, and D3/D4's junction-ownership-wins classifier fixes both without weakening any OCC guard.
- D3's evidence hierarchy (junction > legacy id; audit provenance; "contradictory data is a report, not a repair opportunity") is exactly the refusal-not-repair posture the frozen proposal demanded for the one dangerous write.
- D6's lane algorithm is genuinely deterministic, preserves true spans, and the base-availability-row idea cleanly resolves the venue "history covers a bookable cell" conflict without letting history geometry affect selection (`roomBeforeNextEvent` exclusion included).
- D7 pins data-based card formulas with a concrete arithmetic example, closing Proposal Round 1's third carry-forward.
- D9's fingerprint-then-recalculate apply gate, `class_cancelled`+`maintenance=true` receipt (legal CHECK value verified), and rollback rules honour the proposal's separate-approval boundary and the demo-DB absolutes.
- D10 matches the real gates: testing-only DB selection, the 5-minute checker's fixture hazard, TC70 exclusion, `phpstan --memory-limit=1G`, `node --check`, honest pre-existing-failure reporting.

### 💡 Optional Suggestions

- Add a test-fixture note for the transitional state before the legacy cleanup runs: Part B's hover on a still-occupied cancelled row will name the cancelled class with public state `NORMAL` — acceptable, but D10 should assert it once so it isn't later "fixed" into a privacy or wording regression.
- `myTeachingStats` matches the lecturer by display name (`MockData.currentUser.name`), not id — pre-existing; server-side scoping in D5 makes it near-redundant, worth a one-line note so the name filter isn't treated as an identity contract.
- The schema-legal but unused `class_cancel_undone` audit value has no interaction with this change (no undo path exists in `app/`); a sentence in D9 confirming the inventory ignores it would pre-empt a spec-time question.

### ⚖️ Review Verdict

**FAIL — design gate not passed this round.** One 🔴 must be resolved before freeze: D5 needs the explicit cancellation-history discriminator (audit-evidenced user cancellations only; approval-stamped pairs never emit history), because it decides visible behaviour, summary-card values, and consistency with D9's inventory rule, and the underlying data cannot be distinguished any other way. The three 🟡s should be resolved in the same pass (D4's lock mechanism paragraph, duplicate-available classification, student reason retention) — they are cheap in design and expensive as spec rework.

Everything else is substantively sound: the ownership classification, lock ordering, shared-lane layout, summary contract, and cleanup safety all validate against the current code, and no frozen-proposal scope or non-goal is violated. Once the 🔴 (and ideally the 🟡s) are folded into design.md, the batch should be re-reviewed and can then freeze for Batch 3. This verdict is not, and does not imply, approval to implement, run the cleanup, commit, or push.


### 🔴 Fixed after review (pending re-review)

- D5 now requires audit-evidenced user cancellation, shared with D9; approval-only stamps cannot create history.

### 🟡 Addressed after review (pending re-review)

- D4 now uses shared advisory serialisation while preserving Submit/OCC's no-outer-transaction and surviving conflict-audit contract.
- D3 explicitly treats duplicate all-available rows as reported no-ops; non-available duplicate coordinates remain ambiguous.
- D5 preserves the current public student reason label and excludes private detail fields.
- D8 states neutral fallback for pre-cleanup occupied cancelled originals; D9 excludes undo and maintenance receipts as source evidence.

### 🔴 Outstanding

Round 1 failed; fixes were submitted to independent re-review. No implementation or data-write approval.

## Design Round 2 — 2026-11-13

Independent read-only pi reviewer; model `commandcode/z-ai/glm-5.3-flash`; exit 0.

All reads complete — design.md (current post-fix text), review-log.md (Round 1 findings + claimed fixes), frozen proposal.md, explore-brief.md, plus code verification of the four focus areas (`SubmitReplacementRequest.php`, `OCCValidator.php`, `ApproveRequest::releaseOriginal`, `ReleaseRequestSlots`).

## 🔍 Review Report: `cancelled-slot-reuse` (Batch 2 — design.md, Round 2 re-review)

*This round re-reviews the fixed `design.md`. `proposal.md` is frozen (baseline), `explore-brief.md` is baseline. `specs/` and `tasks.md` intentionally absent and not penalised. Design gate only.*

### Round 1 fix verification (the four focus areas)

**1. Cancellation discriminator (Round 1 🔴) — fixed.** D5 now builds cancellation history only from "recurring session plus a current exception AND a non-maintenance `class_cancelled` audit identifying that exact session/week, using the same discriminator as D9," and states "`official_event` alone is not proof" with "approval-stamped pairs without user-cancellation audit evidence never gain cancelled history from overlap; their existing suppression stays unchanged in all cases." This resolves both divergent readings from Round 1: read-one behaviour (audit-evidenced only) is now the single decision, it protects the D7 "current no-reuse baseline stays identical" assertion, and D5/D9 use the *same* conjunctive rule (exception + audit), so the history builder and the cleanup inventory can never disagree. The maintenance-receipt exclusion ("Inventory must ignore maintenance receipts as cancellation-source evidence") closes the self-referential loop once cleanup starts writing `class_cancelled` receipts. Consistent with proposal §4's "cancelled history block remains above the approved replacement" (that sentence governs user cancellations, which is what the design now implements) and with "existing behaviour for approved make-ups … otherwise unchanged."

**2. Duplicate available classification (Round 1 🟡2) — fixed.** D3's `available` row now reads "All matching physical rows already available (including duplicate available rows), regardless of stale original ids … Report duplicate available rows, otherwise no-op; do not bump versions or clear ids just to tidy data," and ambiguity is reserved for "duplicate coordinates with any non-available row." The over-strict-refusal hazard (a legal multi-`available` cell aborting a whole runtime batch) is gone, and the no-tidy-data rule prevents gratuitous version bumps on the live DB.

**3. Student label retention (Round 1 🟡3) — fixed.** D5: "Student projections retain today's generic public `ReasonLabel`/`cancelledReason` label, but omit staff-only free-text reason/detail fields … rather than merely hiding text in JS." No accidental visible regression on the student timetable, and the server-side omission (not JS hiding) matches proposal §4's privacy table.

**4. Advisory lock compatibility (Round 1 🟡1) — fixed, and verified against the code.** D4 now prescribes a *session-level* `pg_try_advisory_lock` in the same namespace/key inside `SubmitReplacementRequest`, released in `finally` on the same connection, while cancellation/approval use `pg_try_advisory_xact_lock`. Code confirms this is coherent with the documented structure:
- `SubmitReplacementRequest` truly has NO outer transaction (header, lines 28–31); the action already receives the source `ClassSession`, so a session-id advisory key is available at that point; `compensateLoser()` runs in its own transaction *after* OCC's transaction has ended, so FR 4.12's surviving conflict-audit rewrite and the pre-null-then-delete order are untouched. No wrapping transaction is needed and none is mandated.
- `OCCValidator` holds only ordered slot row locks (`lockForUpdate … orderBy('id')`) inside its own transaction; it never takes a request row lock inside it (plain MVCC read), and its post-rollback audit write happens after the slot locks release. So "no path holding a slot may subsequently acquire advisory/source-session locks" holds: every path is advisory→(sessions→requests→)→slots or advisory→slots, with try-locks (no blocking waits) — no cycle exists between cancellation, approval, submit/OCC, and the request→slots reject/withdraw/auto-reject paths, which D4 explicitly leaves unsequenced with session locks.
- Race analysis holds end-to-end: submit-vs-cancel on the *same* source session serialises on the advisory key; submit-vs-cancel/approve on *different* sessions with overlapping landing/original cells is covered by OCC's ordered slot locks + version-checked flips against cancellation's conditional old-status/id/version predicates, plus D3's post-lock re-classification (`request_held` → protected, proven supersede → safe skip). The Round 1-verified pre-existing defect (approved closure skipping `occupied` landing cells, `ReleaseRequestSlots` non-strict path) is correctly fixed by the opt-in policy accepted only by `CancelClassBatch`, with defaults unchanged.

### 🔴 Critical Issues (implementation blockers)

None. I could not find a remaining substantive defect in the current design. Every Round 1 blocking finding is resolved in the text, and the four focus areas validate against the actual code, not just the design's own claims.

### 🟡 Should Fix

None at blocker grade. The previously carried 🟡s are all resolved; no new consistency drift was introduced by the fixes (D5's retention rule, D3's table, D4's lock paragraph, D8's neutral fallback and D9's undo/maintenance exclusions all cohere with each other and with the frozen proposal and brief choices 1–9).

### ✅ What's Done Well

- The lock design is genuinely cycle-free *and* minimal: it serialises the one race that slot locks cannot cover (source-cancellation vs source-submission request-state mutation) without nesting OCC or touching the documented no-outer-transaction contract, the `finally` unlock covers both thrown and ordinary refusal paths, and the connection-drop note is correct PostgreSQL behaviour.
- D3's capture-before-closure ordering ("Capture candidate sets before closure so deleted links cannot make a landing cell look like an original reservation") closes a subtle junction-deletion-vs-classification ordering bug that would otherwise corrupt classification during approved-request closure.
- The discriminator is stated once (D5) and referenced (D9) instead of duplicated, so history rendering and cleanup inventory cannot drift apart in later batches.
- D9's fingerprint-then-recalculate apply gate and D10's gate list still match the repo absolutes exactly (fresh-only allowlist, `--env=testing` ban, `docs/demo-db-data-changes.md` receipt, `phpstan --memory-limit=1G`, TC70 exclusion).

### 💡 Optional Suggestions

- D4 could name the submitter's advisory key explicitly ("the request's source class-session id"). It is strongly implied by "the SAME namespace/key" plus D4's first paragraph, and `$session` is already in scope in the action — one clause removes any chance an implementer keys it off the anchor slot id instead, which would silently dissolve the cancellation-vs-submission interlock.
- The D8 pre-cleanup neutral-hover transitional state is now specified; a one-line assertion in D10 ("hover on a still-occupied cancelled original with no active holder yields the neutral fallback") would keep it from being "fixed" into a wording/privacy regression during Part B.

### ⚖️ Review Verdict

**PASS — the design gate can be frozen.** No 🔴 remains; the Round 1 blocker and all three 🟡s are substantively resolved and verified against current code. Proceed to Batch 3 (specs) with the two 💡 items as optional polish. As before, this is a document-review gate: it is not approval to implement, run the cleanup, commit, or push.


### 🔴 Outstanding

None. Design is frozen after Round 2 PASS. Two optional clarifications (source-session advisory key and transitional hover assertion) will be explicit in specs/tasks. User authorisation remains planning through tasks only; stop before Apply.

## Specs Round 1 — 2026-11-13

Independent read-only pi reviewer; model `commandcode/z-ai/glm-5.3-flash`; exit 0.

## 🔍 Review Report: `cancelled-slot-reuse` (Batch 3 — specs/cancelled-occurrence-reuse/spec.md)

*This round reviews the spec only; `proposal.md` and `design.md` are frozen, `explore-brief.md` is the completeness baseline, `tasks.md` intentionally absent and not penalised. Baseline also includes the Proposal Round 1 🟡 carry-forwards and Design Round 2 PASS verdict with its two 💡 commitments ("explicit in specs/tasks"). No edits, no execution.*

### Trace against frozen decisions

| Design source | Spec coverage |
|---|---|
| D3 ORIGINAL-RESERVATION rule, mixture handling, out-of-range rows, provenance hierarchy | R1 (S01–S04) — full, including the refusal taxonomy and "stale id does not override contradictory audit" test trace |
| D4 transaction/lock/cancellation/approval/same-slot reuse | R2 + R3 (S05–S12) — full |
| D5 identity/discriminator/scope/suppression | R4 (S13–S16) — full |
| D6 lane algorithm + render/jump/venue/print contracts | R5 (S17–S21) — full |
| D7 summary contract | R6 (S22–S23) — full, arithmetic example carried |
| D8 Part B hover | R7 (S24–S25) — full |
| D9 cleanup | R8 (S26–S30) — full |
| D10 gates | R9 — condensed but correct |

**Design Round 1/2 fix verification in the spec:**
- 🔴 cancellation discriminator → S13 (exception **+** non-maintenance `class_cancelled` audit; `official_event` alone never creates history; same discriminator as cleanup) ✅
- 🟡 duplicate *available* rows → S02 (reported no-ops, no version bumps/id clearing; ambiguity reserved for any non-available duplicate) ✅
- 🟡 student `ReasonLabel` → S15 (public label retained, staff-only free text omitted server-side) ✅
- 🟡 advisory serialisation vs no-outer-transaction → S12 (session-level source key, finally-release on success/refusal/exception, Submit/OCC contract preserved, two-connection race test) ✅
- 💡 advisory key = source class-session id, not anchor slot id → **explicitly in S12** ✅ (Design Round 2's first carry-forward landed where promised)
- 💡 transitional neutral hover → behaviour present in S25 (see 💡2 below for the test-trace note)

I also verified the spec's one new concrete code fact against the repo: the 680px details-modal width is real (`request-approval.blade.php:12,166`, `replacement-home.blade.php:288`), so S19's pin is accurate, not invented.

### 🔴 Critical Issues (implementation blockers)

None. Every scenario traces to a frozen D2–D10 decision, no scenario contradicts a proposal §3 example-table row, non-goal, or brief choice, and no frozen decision is silently reinterpreted. The spec also correctly carries the proposal's own safety framing (write-gate boundaries in the header, R9 stop boundary).

### 🟡 Should Fix (non-blocking; recommend as declarative soft-freeze appends before tasks.md)

1. **Runtime per-cell release receipt is absent from the spec.** D4 requires recording before/after identity, status, owner and version in the cancellation audit's `original_release` receipt (and available/protected rows as no-ops with reason). Spec S01 states the slot flips and version bump but not the receipt; S28 covers only the *cleanup* receipt. Why it matters: `tasks.md` review maps scenarios → tasks; a required observable output that exists only in frozen design can be dropped from the task list and never verified. One sentence in S01 or S08 (e.g., "each release SHALL be receipted in the cancellation audit's `original_release` evidence") closes it without touching decisions.
2. **D9's "no scheduled invocation and no controller/render call" is not restated.** S28 covers "no page-read repair" and S27's fail-closed gates, but the scheduler wiring path — the one way this dangerous write could run unattended — has no SHALL in R8. Cheap to add; it is a safety constraint, not a mechanism.
3. **Approval-path exception stamping rule is only half-traced.** D4: "Stamp the original exception only if absent; never replace a user's cancellation reason with `official_event`." S11 covers "preserve the original cancellation reason/audit" for cancelled originals, but the stamp-if-absent side (approval on a non-user-cancelled original) has no scenario. Low rework risk (design is explicit), but it is a behavioural SHALL with no test trace key.

### ✅ What's Done Well

- The spec is a genuine *delta*: every R1–R8 scenario is a testable trace key with a concrete fixture (four-cell one-hour class, two-connection race, 09–11 double-count arithmetic, 1440/768/390 matrix), not a prose re-narration of design.
- S04's test requirement ("a matching stale class id does not override contradictory audit evidence") pins the single most dangerous mis-implementation as a mandatory test, matching the Design Round 1 live-verified hazard.
- S12 explicitly forbids anchor-slot-id keying for the advisory lock — this pre-empts the exact mistake the Design Round 2 reviewer warned would "silently dissolve the interlock."
- S22/S23 enforce data-derived summaries and even bind PHP and JS totals to the same fixture facts, closing the last Proposal Round 1 carry-forward.
- S26's "Scope SHALL never be inferred from screenshots or fixed ids" carries the proposal's session-67 warning into enforceable text.

### 💡 Optional Suggestions

1. R9 could name the transitional hover assertion ("still-occupied pre-cleanup cancelled original yields neutral fallback") as an explicit test item alongside the release-helper/advisory-unlock regressions; S25 is the behavioural key, but Design Round 2 flagged this as a regression risk worth one named test.
2. D5's defensive rule "original slot rows claimed by an active request are not also emitted as normal recurring events" has no spec counterpart; it is an edge case (an occupied original cell cannot normally be pending-held), so a declarative sentence in S14 would suffice if tasks review wants exhaustive coverage.

### ⚖️ Review Verdict

**PASS — spec freeze gate cleared.** The spec is consistent with every frozen decision, covers all nine brief choices through the frozen chain, and resolves all prior 🔴/🟡 carry-forwards at the right layer. The three 🟡 items are declarative omissions (all already decided in frozen `design.md`), fixable by soft-freeze appends that would not change any decision; I recommend appending them before `tasks.md` is written so the task mapping gets trace keys for them. This verdict is a document gate only — not approval to implement, run the cleanup, commit, or push.


### 🟡 Addressed — declarative soft-freeze additions

- S08 now names runtime per-cell original_release receipts and no-op reasons (already required by D4).
- S28 now names the no-scheduler/no-controller constraint (already required by D9).
- S11 now includes ordinary approval stamp-if-absent (already required by D4).
- S14 states active-request rows are not also normal recurring events; R9 names the transitional hover assertion (already required by D5/D8/D10).

### 🔴 Outstanding

None. Spec batch frozen after Round 1 PASS and these declarative additions. No decision changed; no application tests or writes were run.

## Tasks Round 1 — 2026-11-13

Independent read-only pi reviewer; model `commandcode/z-ai/glm-5.3-flash`; exit 0.

All reads and repo verifications are complete. Here is the review report.

---

## 🔍 Review Report: `cancelled-slot-reuse` (Batch 4 — tasks.md only)

**Frozen status:** `proposal.md`, `design.md`, `specs/cancelled-occurrence-reuse/spec.md` are frozen; `explore-brief.md` is the baseline; `review-log.md` rounds 1–5 (proposal R1, design R1–R2, specs R1) were read and their carry-forwards checked for landing. This round reviews `tasks.md` only. All boxes are intentionally unchecked and are **not** penalised. This is a document-freeze gate — PASS here is **not** implementation approval.

**Method:** every S01–S30/R9 trace line was cross-checked against the frozen spec; the dependency graph was walked for cycles and gate placement; every named code seam in the tasks was verified against the repo; the transaction/ownership text of each Part A task was compared sentence-by-sentence against frozen D3/D4.

### Seam verification (all real, none invented)

| Task seam | Repo evidence |
|---|---|
| `CancelClassBatch`, `CancelClass`, `ApproveRequest::releaseOriginal`, `ReleaseRequestSlots`, `SubmitReplacementRequest::compensateLoser`, `RejectRequest`, `CancelPendingRequest`, `AutoRejectRequest` | `app/Actions/*.php` all exist (`releaseOriginal` at `ApproveRequest.php:251`, `compensateLoser` at `SubmitReplacementRequest.php:303`) |
| `OCCValidator`, `MatrixIntersectionEngine`, `ReplacementRequestMapper::spanFromAnchor` | `app/Services/*` (`spanFromAnchor` at `ReplacementRequestMapper.php:462`) |
| `CancelsClasses`, `ResolvesTimetableTimeline` | `app/Concerns/*`, consumed by MyTimetable/CohortTimetable/StudentMyTimetable/VenueTimetable/ReplacementHome/ReplacementArrangement — matches A05/A07/A11/A12/A15's "callers" |
| `VenueTimetable` PHP counters, `venueSlotsByWeekPayload`, `classifyState`, `roomBeforeNextEvent`, `data-sid`/`data-rid` | `VenueTimetable.php:161–310`, `ReplacementArrangement.php:586`, `replacement-arrangement.blade.php:3882`, `venue-timetable.blade.php:855`, `ui-common.js:365–406` |
| `buildTimetableGrid`, `myTeachingStats` | `public/js/ui-common.js:841`, `:1068` |
| New names (`OriginalOccurrenceReservation`, `ReleaseOriginalOccurrenceSlots`, `layoutTimetableDay`, `classes:release-cancelled-slots`) | Declared additions in D2/D6/D9; `app/Services/` and `app/Actions/` are real homes for them; no advisory lock exists in `app/` today, so A04's "add one encapsulated boundary" is accurate |

### 🔴 Critical Issues (implementation blockers)

1. **Four units bundle multi-unit work and cannot honestly fit the ≤2h rule — the task list fails its own hard artifact requirement as written.** The header itself says oversized units must be subdivided through the artifact process; pre-splitting now is one cheap edit, doing it mid-Apply is frozen-file churn plus a likely extra review round. Worse, compression pressure on these specific units directly endangers acceptance coverage, because their "targeted test/check" IS the scenario matrix:
   - **A09 (1.5h) is the blocker.** It bundles ~17 full-stack scenarios: 5 reuse combinations each through availability → submit → approve, 9 S10 blockers (room/lecturer/cohort/venue/capacity/holiday/notice/span/active-request), plus busy-vector, freed-facts and uncancelled-week assertions — all with frozen dates/RefreshDatabase fixtures. Even at expert speed this is 2.5–4h. The realistic failure mode is not slippage — it is silently dropping scenarios from the S09/S10 matrix to fit the bound, which is exactly the coverage this task exists to prove. **Split before freeze** (e.g., A09a = five-combination reuse matrix; A09b = independent-blocker + assertion battery).
   - **A02 (1.5h).** The ORIGINAL-RESERVATION service including lifecycle-provenance validation **plus** a six-category classifier fixture matrix (originals, duplicate-available, orphan links, stale owner/newer request, malformed claims, contradictory audits) is the hardest single artifact of the change — realistically 3–4h. If compressed, the fixture categories are what get trimmed, and S04 (the refuse-don't-guess rule, the linchpin of every safety claim) is the first casualty. **Split** (e.g., service/classification vs provenance+fixture matrix).
   - **A20 (1.5h).** A full guarded E2E lifecycle (cancel → green cell → request → approve → two blocks → reject/withdrawal/auto-rejection cleanup → card arithmetic → privacy) with SQL fixtures, target guards and fixture id/status verification is 3–5h including debugging. The task already anticipates "split fixture/helpers if needed" — make the split explicit now (lifecycle receipt vs card/privacy receipt).
   - **A10 (1.5h) — borderline, same defect class.** Deterministic two-connection race receipts plus forced exception/rollback-notification checks are the fiddliest tests in the suite; likely 2–3h. Pre-split or accept a documented overrun — but decide now, not at Apply time.

   Fixing this requires no decision-level change to any frozen artifact — it is a mechanical rebalance inside this batch.

### 🟡 Should Fix (non-blocking but recommended before freeze)

1. **A14's "executable unit-style JS tests" have no defined execution mechanism, and A21's gate list never runs them.** The repo's only test runner is Playwright (`package.json` — no vitest/jest, and new dependencies are banned); `node --check` in A21 is syntax-only. `layoutTimetableDay` will live in a browser-global script, so a Node-based test hits an immediate import/export question — two implementers will diverge (plain `node` assertion script vs folding into A15's Playwright DOM fixtures vs reaching for a banned runner). Name the mechanism in A14 (e.g., a zero-dependency `node:test`/plain-assert script that loads the function explicitly, or fold the assertions into A15's DOM fixture checks) and add its run to A21's (and B03's) gate list so the shuffle-invariance/comparator tests are actually a gate, not a promise.
2. **A21 at 1.5h is optimistic** for the full gate battery + multi-viewport/theme Playwright receipts + changelog + scope verification. It self-declares the "stop/split rather than conceal" rule, which is the right guard — but consider pre-splitting gate-running from changelog/scope reconciliation so honesty isn't under time pressure.

### ✅ What's Done Well

- **Coverage is complete and the map is honest.** I traced every task's `Trace:` line back to the spec: all S01–S30 and R9 have at least one owning task, the summary table's mappings match the trace lines (no phantom or orphaned scenarios), and the prior rounds' carry-forwards are all landed: runtime `original_release` receipts → A03/A05; no-scheduler constraint → C01; stamp-if-absent → A08; transitional neutral hover → B02; advisory key = source class-session id → A04.
- **Dependency graph is acyclic with correct gate placement.** G1 gates A01 (and B01 explicitly requires "G1 must cover Part B"); G2 gates only O01/O02; G3 gates only A22/B04; Part A and Part B commit gates are separate (A22/B04) with B04 protecting the parallel hover-lane folder; O01–O03 sit behind C03 + explicit user request, matching the proposal's separate-cleanup boundary.
- **Transaction/ownership safety carries D3/D4's hardest rules verbatim into actionable tasks:** capture-before-closure (A05), nested helpers reuse held locks and never acquire new lower-order locks (A07, preserving the verified cycle-free order), finally-unlock on the same connection while preserving submit's no-outer-transaction/conflict-audit/compensation contract (A04), opt-in occupied-release policy used only by CancelClassBatch with pinned caller defaults (A06 — and all three named default callers exist), protecting the approving request's whole landing set (A08).
- **The stop-before-Apply boundary is unambiguous:** every box unchecked, reviewer PASS explicitly disclaimed as non-approval, no edits/tests/writes/commits in this session, and the O-section fenced behind G2.

### 💡 Optional Suggestions

- A20 depends on C02, but its lifecycle content (booking/display on the testing DB) doesn't need the cleanup CLI's apply mode; the dependency is stricter than necessary and makes A20's schedule hostage to C02. Loosening to C01 (guard/facility exists) would be enough if the lifecycle never invokes apply — worth one look.
- Proposal §7 lists "later context updates" among deliverables; no task owns them. If they belong to the standing handover/session process rather than SDD, one line saying so would prevent a dangling expectation.

### ⚖️ Review Verdict

**FAIL to freeze as-is — one mechanical fix loop from PASS.** No coverage gap, no dependency cycle, no unsafe transaction design, no wrong seam, and no conflict with any frozen artifact was found. The single blocker is the ≤2h rule violation in A02/A09/A20 (A09 being the clear worst), plus the unpinned A14 JS-test mechanism and its gate gap. All fixes are confined to `tasks.md` (the current batch — no frozen-artifact edits, no decision changes). After the splits and the mechanism pinning, a scoped re-review limited to the restructured units should clear the batch for freeze.

This verdict is a document gate only: it does not authorise implementation, the demo cleanup, a commit, or a push, and the planning stop before Apply stands.


### 🔴 Fixed after review (pending re-review)

- Split A02/A02b geometry vs ownership/provenance fixtures; A09/A09b reuse vs blocker matrix; A10/A10b race harness vs source/fault receipts; A20/A20b/A20c approved lifecycle vs ending lifecycle vs card/privacy evidence.
- Updated dependent task ids and scenario mappings without changing frozen decisions.

### 🟡 Addressed after review (pending re-review)

- A14 names the existing Playwright browser/page.evaluate mechanism and tests/timetable-lane-layout.spec.ts; A21/B03 explicitly run it, without a new dependency.
- Split gate execution A21 from evidence/changelog/context reconciliation A21b.
- Removed the unnecessary cleanup CLI dependency from the booking/display lifecycle test; A21 still waits for testing rehearsal C03.

### 🔴 Outstanding

Round 1 failed; restructured tasks were submitted for independent re-review. No Apply or database writes authorised.

## Tasks Round 2 — 2026-11-13

Independent read-only pi reviewer; model `commandcode/z-ai/glm-5.3-flash`; exit 0.

All reads are complete — tasks.md (current post-fix text), the last two review-log rounds (Tasks Round 1 findings + claimed fixes), frozen spec, frozen design D2/D6/D7/D10, and repo verification of the A14 mechanism claims. Here is the re-review report.

---

## 🔍 Review Report: `cancelled-slot-reuse` (Batch 4 — tasks.md, Round 2 re-review)

**Frozen status:** `proposal.md`, `design.md`, `specs/cancelled-occurrence-reuse/spec.md` are frozen baselines; `explore-brief.md` is the completeness baseline. This round re-reviews the restructured `tasks.md` only — no frozen-artifact content is challenged. All boxes are intentionally unchecked and not penalised. Read-only; no Apply, test execution, or edits performed.

### Round 1 fix verification (the six focus areas)

**1. A02/A02b split — fixed.** A02 now owns geometry + base classification (exact semester/source/week/coordinates, half-cell coverage, original/all-available-including-duplicates/missing/non-available-duplicate, out-of-range reporting) with focused fixtures; A02b owns ownership/provenance (request-held/ambiguous via junctions + mapper fallback, claim coordinate/state/lifecycle validation) with the adversarial matrix. Critically, A02b explicitly says **"Pin every S04 refusal category rather than trimming the matrix"** — the exact failure mode Round 1 predicted (S04 as first casualty of compression) is now named as a hard instruction inside its own dedicated unit. Every S04 category traces: missing (A02), duplicate non-available (A02), out-of-range (A02), mismatched occupied owner/unexplained pending (A02b), orphan/inactive links (A02b), malformed claims (A02b), lifecycle-inconsistent evidence (A02b), and the mandatory stale-id-vs-contradictory-audit test lands in A02b's "stale owner/newer request … contradictory audits" fixtures. A03 correctly depends on A02b, so the release action never consumes a half-built classifier.

**2. A09/A09b split — fixed.** A09 = the five S09 reuse combinations (parameterised, shared fixture builders, explicit testing DB, frozen dates/RefreshDatabase); A09b = the ten S10 blocker cases reusing A09's fixtures "without duplicating seed logic." This is exactly the split Round 1 prescribed. The freed-facts assertion ("freed arrangement cells have normal available facts") sits in A09, and A09b's "uncancelled weeks stay occupied" matches S10's independent-blocker wording verbatim. Five parameterised full-stack flows at 1.5h remains the tightest unit in the list (see 🟡 below), but the ~17-scenario bundle that made Round 1 a blocker is gone.

**3. A10/A10b split — fixed.** A10 isolates the fiddly part (deterministic two-connection harness, repeatable barriers, bounded waits, "not sequential mocks" — matching S12's explicit requirement) and A10b reuses that harness for same-source submit/cancel and approve/cancel contention plus lock-leak ("Assert no source advisory lock leaks" — S12's unlock paths) and forced exception/rollback-notification receipts. No duplicated harness work.

**4. A20/A20b/A20c split — fixed.** A20 = guarded fixture + approved lifecycle (SQL fixtures with exact receipt-target guard, safe pending starts outside checker expiry, fixture id/status self-verification, "Do not stop/change checker"); A20b = withdrawal/rejection/auto-rejection endings, with the cron guard ("an explicit auto-reject invocation … must not modify cron"); A20c = card/scope/privacy/layout evidence reusing A20 helpers and A19's screenshots rather than rebuilding them. Round 1's 💡 about the over-strict C02 dependency is also resolved: A20 now depends on A09b, A10b, A17–A19 — no cleanup-CLI dependency — while A21 still waits for C03's testing rehearsal.

**5. A21/A21b split — fixed.** Gate execution (lint, PHPStan 1G `--no-progress`, `php artisan test` on `class_replacement_testing`, `node --check`, `view:cache`, scoped Playwright, exact `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030` guards, TC70 excluded, honest failure recording) is separated from evidence/changelog/context reconciliation (A21b, which also owns the Proposal §7 "later context updates" that Round 1 flagged as dangling). A21's "stop/split rather than conceal" rule survives, and A21b explicitly forbids "calling unfinished gates passed."

**6. A14 mechanism — fixed and verified against the repo.** The task now names the real, existing mechanism: `tests/timetable-lane-layout.spec.ts` run by the existing Playwright runner (`package.json` — `npx playwright test`, `@playwright/test` is the only test dep; no vitest/jest; new deps banned), using `page.evaluate` against the **served** `public/js/ui-common.js` (loaded as a plain browser-global script by `layouts/ui-template.blade.php:51`), with synthetic arrays. This is an established repo pattern — dozens of existing specs call window globals via `page.evaluate` (e.g., `window.buildTimetable`, `window.firstBookableDay`, `window.selectWeek`), and ui-common.js already exposes helpers as window globals (`window.totalStudentsLabel`, `window.cohortBreakdown`). "No new runner, dependency or module export is needed" is accurate, and the design D2.4/D6 contract (pure `layoutTimetableDay`, half-open `[start,end+1)`, cancelled-first `(start,end,eventKey)` comparator, shuffle invariance, no mutation) is carried into A14's assertions without reinterpretation. **A21 explicitly runs `tests/timetable-lane-layout.spec.ts` in its gate list, and B03 runs it too** — the Round 1 "promise, not a gate" gap is closed on both Part A and Part B sides.

### Dependency graph and coverage re-check

- **Graph is acyclic with gates intact:** A01(G1)→A02→A02b→A03→A05(+A04)→A06→A07; A08(A03+A04); A09→A09b→A10(A04)→A10b; A11→A12→A13; A14(A11)→A15→A16(A13)→A17; A18; A19; C01→C02→C03; A20(A09b, A10b, A17–A19)→A20b/A20c→A21(+C03)→A21b→A22(G3); B01(A13, A22, G1-covers-Part-B)→B02→B03→B04(G3); O01(C03, A21b, explicit user request)→O02(G2)→O03. No cycles; G2 touches only O-tasks; separate A22/B04 commit gates; B04 protects the parallel hover-lane folder.
- **Coverage table matches the trace lines.** All S01–S30 and R9 rows reference only tasks that exist, and spot-checking the restructured ids: S01–S04 → A02, A02b, A03, A06, A08 ✓; S12 → A04, A10, A10b ✓; S17 → A14, A15, A19 ✓; S21 → A19–A21 ✓; S26–S30 → C01–C03 + O-tasks with "O02 only with G2" qualifiers ✓; R9 → A01, A21, A21b, B03 ✓. No phantom or orphaned scenario.

### Safety scan across the full list

The hardest D3/D4 rules survive the restructuring verbatim: capture-before-closure (A05), nested helpers reuse held locks and never acquire lower-order locks (A07), finally-unlock on the same connection with submit's no-outer-transaction/conflict-audit/compensation contract preserved (A04), opt-in occupied-release used only by CancelClassBatch with the three default callers pinned (A06 — all three exist in `app/Actions/`), protecting the approving request's whole landing set + stamp-if-absent (A08, S11). Cleanup stays fenced: C01 dry-run default with no scheduler/controller registration (S28), C02 fail-closed fresh-only with demo-DB-never tests (S27), C03 testing-only rehearsal, O-tasks behind explicit user request + G2, O02's receipt appended without staging other sessions' rows, O03 forbidding partial restore and implicit reactivation (S30). The stop-before-Apply boundary is unambiguous and now reinforced by the three named later gates G1/G2/G3.

### 🔴 Critical Issues (implementation blockers)

**None.** Every Round 1 blocker is substantively resolved in the text; the splits are decision-free restructures confined to the current batch; the A14 mechanism is real and gated on both sides. No coverage gap, no dependency defect, no frozen-artifact conflict, no unsafe seam remains.

### 🟡 Should Fix (non-blocking)

- **A09 (1.5h) remains the tightest unit** — five end-to-end availability→submit→approve flows even with parameterisation and a shared builder is realistically 1.5–2.5h. The header's stop/subdivide rule covers it, and the unit boundary is already at the right seam (a further split would separate availability+submit from approve, which fragments S09's agreement assertion). Acceptable as frozen; flagging so Apply treats A09 as the first candidate for an honest overrun/split rather than scenario-trimming.

### ✅ What's Done Well

- The splits were done exactly where Round 1 diagnosed compression pressure, with the coverage-critical instruction ("pin every S04 refusal category rather than trimming the matrix") written *into* the freed unit.
- A02b→A03 and A09→A09b→A10→A10b ordering means no downstream task ever consumes an unproven classifier or unbuilt harness.
- The A14 mechanism choice (Playwright + page.evaluate + served ui-common.js, no export) is the repo's own dominant pattern — two implementers cannot diverge on it now, and `node --check` is correctly demoted to the syntax-only role it actually has.
- A20's loosened dependency and A20c's "retain A19 screenshots" note remove real duplicated work without weakening any scenario's evidence.

### 💡 Optional Suggestions

- The coverage table's S06/S07 rows could also list A20b (its trace covers S06–S08 browser-level receipts). The task Trace lines already carry this, so this is table polish only.
- One clarifying clause in A14 that `layoutTimetableDay` is exposed as a window global (like the existing `window.totalStudentsLabel` precedent) would remove the last micro-ambiguity in how page.evaluate reaches it.

### ⚖️ Review Verdict

**PASS — the Batch 4 task list can be frozen.** No 🔴 remains; all six focus areas validate against both the frozen chain and the actual repo (runner, served script, page.evaluate precedent, gate lists). The single 🟡 is a disclosed tightness with an existing honest-overflow rule, not a defect. Proceeding to freeze `tasks.md` does **not** authorise implementation, the demo cleanup, a commit, or a push — G1/G2/G3 and the planning stop before Apply all stand as written in the artifact.


### 🟡 Disclosed implementation caution

A09 is a tight work unit. Retain all five reuse cases; stop/subdivide if it exceeds the task bound. Do not trim scenarios to meet an estimate.

### 🔴 Outstanding

None. Tasks frozen after Round 2 PASS. All S01–S30 and R9 have owning tasks and all boxes remain unchecked.

## Planning finish — stop before SDD Apply

Proposal PASS Round 1; design PASS Round 2; specs PASS Round 1 (declarative omissions appended); tasks PASS Round 2. All planning batches are frozen. Only artifacts in this change folder were written during this continuation. No application/test code, service, database cleanup, commit or push was executed. No application test results are claimed: independent subprocesses reviewed documents/code read-only.

G1 (explicit implementation approval), G2 (exact separately approved fresh-only cleanup write) and G3 (commit authority) remain closed. Existing dirty application/docs files and the parallel hover folder are not this planning batch's implementation. At that planning gate, Apply awaited the user. The user subsequently explicitly approved “u may sdd apply.”; authority and implementation progress are in apply-log.md.

## Apply foundation code audit — 2026-11-13

Independent read-only pi subprocess, model commandcode/z-ai/glm-5.3-flash, exit 0. This reviews only A02/A02b/A03/A04, not the remaining implementation or the full SDD Verify gate.

## Audit verdict: **PASS — foundation slice is safe as an isolated deliverable**

Scope respected: planner + release action + receipt/exception + lock boundary + submit wrapper + 3 test files only. No cancellation/approval/timetable integration expected or penalised.

### Ownership classification (D3) — sound

- `OriginalOccurrenceReservation.php` classifies strictly from geometry (semester/week/day/venue/30-min `expectedStarts`), never week-or-room alone; out-of-range source-id rows are reported via `outOfRange`, never classified or freed (verified: `isAtCoordinates` semester check, `relevantRows` fetch).
- Request ownership outranks stale `class_session_id` in both arms — junction receipts are coherence-checked as one contiguous, same-room/day/week run opened by the request's own anchor, with per-cell state matching (`pending→pending`, `approved→occupied`); the anchor/span fallback goes through `ReplacementRequestMapper::spanFromAnchor()` (the documented mapper — no second span arithmetic). This matches the pinned tests (`test_a_pending_request_holds_its_cells_over_a_stale_class_id`, `test_the_junction_less_anchor_span_fallback_holds_cells`).
- Malformed-claim omissions checked and **not found**: midnight-wrapping/non-monotone fallback spans → `malformed_fallback` refusal (tested); unreadable/vanished receipt member → `orphan_link` fail-closed; pending-claim-on-occupied → `claim_state_mismatch`, not owner proof (tested); two distinct owners on one cell → `conflicting_claims` (tested); live claim on an available row → ambiguous (tested, both arms).
- Provenance law implemented correctly: only transition audits (`old_status != new_status`, both set, request-family actions) disqualify a pristine original; `occ_conflict` attempts and `class_cancelled` receipts are correctly excluded (both tested). A stale occupied row with lifecycle evidence → `provenance_contradicted`.

### Release action (D3/D4) — no stale/partial-release path

- `ReleaseOriginalOccurrenceSlots` refuses outside a transaction, refuses refusing plans, and every flip conditions on **full identity geometry + status + owner + exact version**; `affected === 0` → `stale_member` throw, caller rollback erases the batch (tested with forced version drift, owner drift, and geometry-drift-without-version-bump). Available/request-held cells are byte-identical no-ops; stale ids on available duplicates are not tidied (tested).
- Repeat release is a structured no-op with zero version bumps (tested with real post-release versions).
- Receipt carries per-cell before/after status/owner/version plus no-op reasons — matches D4's `original_release` evidence contract; audit composition correctly left to the future caller.

### Lock boundary (D4) — correct and fail-safe

- `OccurrenceWriteLock` keys the fixed `0x43525357` ('CRSW') namespace by **source class-session id** (never anchor slot); session-level try-lock for submit, transaction-level xact-lock (ascending, deduped, transaction-required) for cancellation/approval; try-only, contention throws `OccurrenceWriteLockContended` before any work.
- `SubmitReplacementRequest` wrapper preserves the documented NO-outer-transaction structure, maps contention to the existing `slot_not_available` flat loser shape, and the finally-release covers success/refusal/exception — plus the D4 purge fail-safe (unverifiable release disconnects the *captured* connection, never masks the caller's outcome; both tested via the aborted-peer-transaction shape).
- Boolean normalisation (`'f'`/0/native false) handled once in `truthy()`.

### Tests

The three files genuinely pin S01–S04 cell-by-cell, the no-op/atomic-release matrix, and the two-connection bidirectional lock interlock — not sequential-mock-only.

### Non-blocking observations (record only, no action required)

1. `OriginalOccurrenceReservation.php` fallback branch: the `spanFromAnchor() === null` path is unreachable (the mapper returns null only when the anchor is null, which is handled just above) — dead condition, harmless.
2. A junction-less request whose anchor row's semester contradicts the request's own semester is silently skipped (`continue`) rather than flagged ambiguous; since such an anchor cannot cover the occurrence regardless, this is not unsafe.
3. Advisory-lock reentrancy (same backend re-taking its own key) is a theoretical false-acquire path; D4's "no path holding a slot may acquire advisory locks" rule plus the submit-first lock ordering neutralise it in the planned callers.

No BLOCKING defects. The slice does not imply feature completion — no cancellation/approval/summary/UI integration exists, consistent with the stated scope.


### 🔴 Outstanding

No blocking defect in the isolated foundation slice. Whole change remains incomplete: cancellation/approval integration, shared timetable/hover work, cleanup tooling and complete verification are still open. Shared-file ownership must be coordinated before UI edits. Combined targeted regression run had one fixture collision; see apply-log.md (not a clean/full-suite claim).

## A08 implementation re-review — 2026-11-13

Independent read-only pi subprocess, model `commandcode/z-ai/glm-5.3-flash`, exit 0. Report: `/tmp/crs-reuse-approval-rereview-output.jsonl`. **PASS for A08 only**, not whole-feature Verify.

The reviewer traced the prior legacy-span blocker through the corrected canonical full-claim validation, complete landing flips and refusal-before-write checks. Junction-less stored spans are fully occupied without fabricated junctions; malformed/missing tails refuse atomically. The locked original plan protects the approving request's own landing, original releases use full conditional identity/owner/version checks, and existing cancellation reasons survive. Source/request/slot lock order and after-commit notification boundaries were checked.

No blocking findings. Non-blocking observations: duplicate pending coordinates have no special approval fixture (the active-coordinate unique index prevents this through legal writes); an occupied foreign claim is not directly exercised in this approval-specific slice. Existing classifier/release coverage and final scope review remain relevant. The reviewer did not execute tests; passing test receipts belong to main's apply log.

## A11–A19 implementation review — 2026-11-13

Independent read-only pi subprocess, model `commandcode/z-ai/glm-5.3-flash`, exit 0. Report: `/tmp/crs-reuse-ui-rereview-output.jsonl`. **PASS for display/payload slice only**, not whole-feature Verify.

Reviewed scope-before-overlap, exact cancellation evidence, server-side student privacy, event-key deduplication, deterministic half-open lanes, truthful fail-closed room-cell matrix, source/request interaction selectors, identity-based summaries and the narrowly scoped mobile base-offer styling. No blocking code finding. Minor test gap: venue proven-user-cancelled plus no active overlap has no specifically named suppression test, though personal suppression and venue approval-stamp-only arms are covered. Main's actual full/browser/static receipts are in apply-log.md; the reviewer did not run tests.

## B01/B02 implementation review — 2026-11-13 continuation

Independent read-only pi subprocesses using commandcode/z-ai/glm-5.3-flash; no DB/test/edit tools. Round 1 **REQUEST CHANGES**: /tmp/crs-reconcile-b/part-b-review-r1.txt (full JSON in part-b-review-output.jsonl). Two confirmed tooltip-proof gaps: refused junction-less claims marked only their anchor unknown, not all landing tails; discovery missed fallback claims whose anchor was available or an available twin.

The coding agent corrected discovery using the actual requestSlots relation, grid-semester/anchor-tuple bounds and shared claim proof. Refused evidence now marks the full input intersection of landingSlotIds neutral. Added three explicit tests: drifted tail plus uncancelled covering class; available anchor with still-held tails; available anchor twin with a uniquely proven pending run. No second geometry policy or read-time write was introduced.

Round 2 **PASS for this backend/fallback slice**: /tmp/crs-reconcile-b/part-b-rereview-output.jsonl. Main's 98/98 tests / 1,712 assertions, lint and PHPStan 1G receipts were read, not rerun by the reviewer. Earlier clean frontend findings remain: merge by factual library identity, shared 12-hour formatter/public vocabulary, safe data-tip text and existing mobile snapshot/textContent flow; classification, labels, eligibility and colours are unchanged. Browser fixture was explicitly excluded from this rereview while being corrected separately. This is not full-feature Verify or a browser pass claim.

## B03 and older regression fixture review — 2026-11-13 continuation

Independent read-only safety/truthfulness review **PASS**: /tmp/crs-reconcile-b/browser-review-r1.txt, full browser-review-output.jsonl. Reviewed exact testing guards, natural source identity, real CSR submit seam and cohort/lecturer disjointness, correct active calendar scope when the picker is empty, registered conditional flips, locked all-or-none scoped cleanup, genuine full spans, escaped synthetic stage, preserved booking rules and explicit failure rather than skipped cases. Calendar/hover-capture corrections were present. The reviewer did not run tests or claim final browser results.

Main subsequently ran the seven relevant browser specs together: **52/52 (49 cases + 3 shared auth setups)**, no skips/unrun cases, /tmp/crs-reconcile-b/integrated-browser.txt. Main inspected all four actual desktop/phone light/dark holder captures from room-holder-results-r5; new source/test/changelog work is uncommitted. These scoped passes do not close the known unrelated history-shape error, remaining Part A delivery evidence, final SDD Verify, B04 or operational cleanup authority.






