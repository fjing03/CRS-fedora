
## Proposal Round 1 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴1 sequencing premise: U2a's SDD dir is ACTIVE (only arrange-intent-session-choice archived) — origin line corrected: U2a feature code live at HEAD, its SDD verify/archive pending; merge overlap rehearsed (confined to the venue read-model region U2a's artifacts don't touch).
 - 🔴2 rendering mechanism: per-slot events + `mergeTwinEvents` can NEVER coalesce (buckets by di:start; same-cell twins only) — item 8 rewritten to **one event per REQUEST** (`end = start + span − 1`, junction-keyed; the blade's `span-N`/`colSpan` renders it). AC 4 achievable.
 - 🔴3 OCC two-phase discipline mandated: lock ALL span rows → check ALL statuses (record first failing time) → only then flip all + junction + audits; per-slot check-flip loops FORBIDDEN (mid-span conflict would COMMIT flipped slots → orphaned pendings → the hole recreated in the loser path); two-phase loser test added (zero pending slots left, anchor available).
 - 🔴4 clamp vs reject contradiction: `clamp(1,4)` wording struck — raw span derived, out-of-bounds → `invalid_span` loser before any write (a 3h ask is REJECTED, never silently shortened).
### 🟡 Addressed
 - Picker-cap sites enumerated (both boot clamps, resize clamp 8→4, MAX_SELECTION coupling — block-span vs global-budget distinction); &duration=3h handoffs auto-shrink to 2h.
 - Impact omissions added: `app/Services/OCCResult.php` (failing-slot time fields) in scope; `app/Http/Requests/StoreReplacementRequest.php` explicitly untouched (bound stays action-level).
 - Line anchors re-derived (±2 drift noted; reviewer-precision recorded).
### 🔴 Outstanding
 - (re-review pending)

## Proposal Round 2 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴 (r2) `tests/ui-regression.spec.ts` TC-1.4 hard-asserts "Selected 6 of 6 slots" for a `duration=3` handoff — under the 4-cap it reads "of 4" and fails: impact scope now names TC-1.4 as a DESIGNED contract change (updated by this change, decision-note in changelog; tests item 10 re-points it) instead of the false "regress-only, stays green" claim.
### 🟡 Addressed
 - 🟡2 time-transport chain named in this batch: OCCResult gains failing slot start/end; the ACTION's flat return is enriched (`failing_start`/`failing_end`) so the times survive the boundary; `conflictText`'s `span_overlap` interpolation fed from those keys (otherwise AC 1's "blocking time named" silently degrades).
 - 🟡3 the pinned-anchor display clamp (≈:2373) marked explicitly OUT of the cap family (display-only of the original class's true length — capping it would make the banner lie about non-FOCS 3h originals).
 - 💡 folded: `invalid_span` gets its own `conflictText` branch (bounds errors deserve a bounds message, not the generic fallback); the non-integer span test re-spec'd to its reachable surface (submitBlocks coerces `(int)` silently — assert the derived value).
### 🔴 Outstanding
 - (re-review pending)

## Proposal Round 3 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴1 junction UNIQUE poisons released slots: cancel KEEPS the request row (cascade fires only on DELETE) → released slots could never be junction-linked again (every resubmit → unique violation → poisoned forever). DECIDED: junction rows are DELETED at release (cancel; future approval reuses) — the junction holds LIVE-reservation linkage only; history stays in audit_logs + the request row; unique(time_slot_id) stays valid; read-model needs no live-filtering.
 - 🔴2 AC 1's named-time unreachable for the steady-state loser (the pre-reserve status check returned null times; span_overlap existed only in the OCC race window): the transport chain now covers BOTH paths — the action's span pre-check records the failing slot's times into the flat-return keys, and conflictText interpolates when present (canned copy otherwise); engine VECTOR losers keep their existing copy. AC 1 reworded.
### 🟡 Addressed
 - 🟡3 loser audit granularity pinned: the two-phase LOSER emits the current single conflict audit row (first failing slot id + details); per-slot rows only on success.
 - 💡 boot-clamp formula keeps the existing Math.max(hrs, 0.5) floor (top-capped at 4).
 - 💡 request_id key preserved in the enriched return shape (explicitly stated).
 - 💡 missing sdd.yaml created (bookkeeping).
### 🔴 Outstanding
 - (re-review pending)

## Proposal Round 4 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴1 arrangement-grid ownership read is ANCHOR-keyed (`venueSlotsByWeekPayload` ≈:326–332 whereIn anchor ids): a span's tail slots 2..N render status 4 "someone ELSE's" even on the owner's grid. IN scope now: junction-derived ownership read (slot → request's proposer); explore-brief's "verify only" claim withdrawn; region + test added (owner sees 3s, others see 4s).
 - 🔴2 two API surfaces missed (ApiReadController): (a) `myRequests()` ≈:135–170 displays the anchor's 30-min time only → 2h pending shows as 30-min (junction-derived span in the read); (b) the venue-slots API map ≈:685–712 marks only the anchor's half-hour → tails UNMARKED = bookable via API (junction-derived cell marking anchor..anchor+span−1). Both + their tests in scope; AC 3/4 extended to cover every surface.
### 🟡 Addressed
 - AC 3 now covers the arrangement grid post-cancel; my-request-history changelog named; junction FK on-delete pinned at design; D7 approved-span future warning in non-goals; the two stale 4-HOUR-clamp comments ride the cap edit; `ReplacementRequest::requestSlots()` model note added.
 - 💡 boot-clamp floor kept; MAX_SELECTION max() harmless.
### 🔴 Outstanding
 - (re-review pending — round 5 is the MAX_ROUNDS cap)

## Proposal Round 5 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🟢 Verdict: FREEZE-ELIGIBLE — 0 🔴 outstanding
All round-1–4 fixes verified landed against live code; no phantom fixes. Frozen with the reviewer-recommended folds:
### 🟡 Folded at freeze (declarative / soft-freeze)
 - AC 4 gains "(while the reservation is live)" — post-cancel anchor-only reads are CORRECT (release deletes junction rows; never re-retain for cancelled requests — round-3's unique-poison lesson).
 - Surface 2's `approved` bucket pinned: the legacy anchor join is RETAINED for approved (or handed to the future approval change); tests pin pending-only geometry.
 - Future-approval warning names ALL approved-branch span consumers (venue page APPROVED branch, ReplacementHistory ≈:78–103, ResolvesTimetableTimeline family).
 - Docs list gains venue-timetable-ui-changelog.md.
 - 💡 carried to design batch: missing-mid-slot canned-copy fallback (or derived expected time); anchor-only pre-check times policy; MAX_SELECTION comment simplification; blade comment anchors drift.
**PROPOSAL FROZEN — 2026-10-16 (5 reviewer rounds; 0 outstanding).**

## Design Round 1 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴1 missed pending-state anchor-keyed read: `MyRequestHistory.php` (`requestRow` ≈:69, fields ≈:103–109) — a 4-slot pending request would render "08:00–08:30" on the history page. D7 gains item 5 (junction-derived date/time/venue for pending rows; legacy `replacementTimeSlot` retained for the approved bucket; eager-load `requestSlots`) + D9 test + dependencies/commit-split move. Verified approval-branch-only consumers (`ResolvesTimetableTimeline:226`, `ApiReadController:453`) stay in the approval change's territory.
 - 🔴2 D3 post-first-flip loser path: "same loser path" for step-4/5 failures would NORMAL-RETURN → COMMIT staged flips → compensateLoser → orphaned pendings (the trap recreated by the design itself). Per-branch rollback contract pinned: any peri-flip failure THROWS out of the txn (full rollback incl. any audit attempt); the single conflict audit row is re-written AFTER the rollback in the catch; junction inserts go LAST; the throw-then-audit-outside ordering is the named invariant. D9 adds the injectable-unique violation test (step 4 noted as unreachable-by-construction under lock).
### 🟡 Addressed
 - 🟡1 missing-mid-slot copy: occupied→named time; missing→bounds-style copy ("falls outside the booking grid"), frozen reason string kept (`slot_not_available`) — no "runs into an existing booking" lie.
 - 🟡2 action-level span guard added (D2 step 2: `invalid_span` pre-request, no write/audit); D9 bounds tests target BOTH surfaces (submitBlocks + direct action call).
 - 🟡3 D5 skipped-slot branch pinned (non-pending slots skip flip + NO false flip audit; junction deletion proceeds).
 - 🟡4 proposal non-goals FK wording fixed via soft-freeze (CASCADE, not RESTRICT — matching 2026_08_03_000009:21).
 - 💡 single comment edit (:3395–97); :1187–89 optional tidy; D7.4 approved-only join narrowing; defensive count($locked)===span guard; TC70 retirement guard repeated in D9.
### 🔴 Outstanding
 - (re-review pending)

## Design Round 2 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴 (r2) the missing D9 MyRequestHistory read-model test — the design's only incomplete round-1 landing: D9 now pins the 2h range on pending rows + the post-cancel anchor-only read per the frozen AC-4 fold + the cancelled-bucket assertion (round-2 🟡1).
### 🟡 Addressed
 - 🟡1 D7.3 bucket split pinned: junction read is pending-geometric ONLY; approved AND cancelled keep the legacy anchor join (a cancelled request's junction-min would be NULL; the history page's `Carbon::parse` is a hard failure if the legacy read is dropped).
 - 🟡2 D3 throw endpoint pinned: RuntimeException subtype, re-thrown untouched at the catch boundary so the action's `reserve_interrupted` catch stays the sole loser surface (a non-RuntimeException would 500 + uncompensated request); the catch's audit re-write is best-effort before the re-throw; the peri-flip test adds the compensated-request-gone / audit-survives-with-nulled-id / non-500 assertions.
 - 🟡3 D9 bounds values corrected: exact out-of-bounds (0, negative, 6 = the 3h ask) + an explicit LEGAL span-3 (1.5h) must pass; non-integer stays at its derivable surface.
 - 💡 anchor-only pendings unreachable at HEAD (test-constructed only); MAX_SELECTION comment tidy.
### 🔴 Outstanding
 - (re-review pending)

## Design Round 3 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴 D2/D6 missing-slot discriminator: the return shape gains `failing_kind` ('occupied' row-backed vs 'missing' derived-expected) — D2 step 3 annotated per sub-case; D6 becomes a 4-arg routing table (occupied → named time; missing → bounds-style with times DROPPED; no-kind+no-times → canned; span_overlap unchanged — OCCResult gains NO kind param, the kind rides action-side); submitBlocks result rows carry the reason + kind token (otherwise it dies before the copy); D9 asserts reason + kind + keys + rendered copy, the occupancy pairing, and byte-compat (null kind on every other path).
### 🟡 Addressed
 - 💡 folds: OCCResult 1-arg wording fix; the catch's re-written audit payload pinned to the auditConflict shape; `:3134` comment rides the resize-clamp edit; D7.1 event `id` = anchor slot id frozen.
### 🔴 Outstanding
 - (confirmation re-review pending)

## Design Round 4 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed (the span_overlap production gap)
 - D3 step 3: the race reason token BOUND ADAPTIVELY — span 1 → `slot_not_available` (byte-identical HEAD), span > 1 → `span_overlap` (frozen race token; previously had NO producer — two implementers could mint different tokens and one D9 test could never pass); details carry the same token + failing times; the ACTION pass-through (:90–92) named the ONLY transport (no action-side re-production).
 - D2 return shape: the race-path kind stamp is UNCONDITIONAL — the `! $result->success` branch copies the failing times + stamps `failing_kind='occupied'` ("may be"/null-alternative struck).
 - D6 table closed: `span_overlap` arm is NEW (the "unchanged" wording was false); unlisted combos (incl. null-kind+times) → the canned fallback — the D2 stamp makes that state unreachable; the stale reason-only duplicate sentence deleted.
 - D9 loser-test sentences reconciled to ONE token (`span_overlap` + `'occupied'` + times) for span>1 races + the byte-compat span-1 mirror (`slot_not_available`).
### 🟡 Addressed
 - (round-4 folded) the stale D6 duplicate removed — same edit.
### 🔴 Outstanding
 - (final confirmation review pending — round 5 of the cap)

## Design Round 5 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🟢 Verdict: FREEZE-ELIGIBLE — 0 🔴 outstanding
Round-4 fixes verified verbatim-coherent against live HEAD; no gold-plating flags (the step-4 unreachability note survived). Frozen with the declarative folds:
### 🟡 Folded at freeze
 - D9's span-1 mirror gains the rendered-copy assertion (the copy path covered at every span).
 - The step-5 forensic token pinned `concurrent_reserve_conflict` for all throw-causes (permanent-history audit rows without a natural token).
 - D2: `invalid_span` orders before the duplicate/holiday pre-checks; the resolve keeps the `+id` tiebreak.
 - 💡 carried: none blocking.
**DESIGN FROZEN — 2026-10-16 (5 reviewer rounds; 0 outstanding). Next batch: specs/ → tasks.md.**

### (Design freeze folds — landing note)
The D2 tiebreak fold landed with the round-5 line-edit pass; the step-5 forensic token fold landed inline. The span-1 rendered-copy assertion lands in tasks.md's D9 item (declarative; same content) — recorded here so method-by-method freeze hygiene stays honest.

## Specs Round 1 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴1 R7.2 collapsed the race-test shape into a one-liner (couldn't distinguish validator-produced span_overlap from peri-flip production; missed winner + audit-survives assertions) — replaced with the reviewer's full three-part double-arm shape: (a) winner untouched, (b) loser token per arm (span-1 → slot_not_available byte-shape; span>1 → span_overlap single-producer; NO throw/reserve_interrupted/concurrent_reserve_conflict markers = exactly the peri-flip boundary), (c) either loser: request deleted, ZERO flips/junction/flip-audits, audit survives id-nulled, A's span untouched.
### 🟡 Addressed
 - R1.2 junction uniqueness invariant declared declaratively; R5.3 reworded to the bucket-semantics wording; R6.2 budget note; 💡 D-number footnote (D5 compensation vs design-D5; proposal-D1 vs design-D1); the "reseves" typo.
 - 💡 carried: R1.1's loser taxonomy flattening is left to tasks.md wording (no decision).
### 🔴 Outstanding
 - (void — reviewer already pre-approved the fold wording; FREEZE.)
**SPECS FROZEN — 2026-10-16 (1 round; 0 outstanding). Next batch: tasks.md.**

## Tasks Round 1 — 2026-10-16 (sdd-reviewer via pi subprocess, glm-5.3-flash)

### 🔴 Fixed
 - 🔴1 Phase-2 ordering inversion: T5 (OCC two-phase) consumed the T6 OCCResult extension before it existed — cannot compile. Pinned executable order: **T3 → T6 → T7 → T4 → T5** (extensions before consumers; the T-numbering keeps the D-section mapping via an explicit order note).
### 🟡 Addressed
 - 🟡5 T15 atomized into T15a–T15e (bounds / winner+race double-arm / steady-state+kind discipline / peri-flip rollback / cancel+engine) — each ≤ 2h, each mapped to R7 numbers so verify checks off by number; the D5 disambiguation guard rides the letters carrying loser tests.
 - 🟡6 token duality pinned: `slot_not_available` (span 1) vs `span_overlap` (span>1) mutually exclusive at the validator mint point; surfaced `reserve_interrupted` vs audit-only `concurrent_reserve_conflict` split never cross-asserted; T4 gains the mapping note + T9's routing table gains the `reserve_interrupted` branch; **design.md D6 gets the transport branch via soft-freeze (declarative)** — the frozen table lacked it.
 - 🟡1–🟡4 folds distributed into T15a–e (bounds rendering + kind absent for span_overlap/invalid_span; kind discipline; both-token arms; cancel arms + engine vectors).
 - 🟡6b: the auto-shrink (&duration=3 → 4 slots/2h) Playwright fold landed in T17 (TC-1.4's update itself) instead of leaving it untested.
 - T16 split T16a (Livewire surfaces) / T16b (API + history).
 - 💡: T3 carries the "kind threading continued in T9" pointer; gates deliberately consolidated at T18 (noted); T17's no-other-spec verdict recorded in T18's gate result.
### 🔴 Outstanding
 - (confirmation re-review pending)

## Tasks Round 2 — 2026-10-16 (sdd-reviewer confirmation + fix pass)

### 🔴 Fixed
 - 🔴1 T9's routing table was MISSING the `reserve_interrupted` arm (the review-log recorded a Tasks-Round-1 edit that hadn't landed) — one-line fold, mirroring the design-D6 soft-freeze wording verbatim.
### 🟡 Addressed
 - 🟡1 the Phase-2 execution-order note cleaned (the "T5-engine → T6-action" garble): plain "T3 → T6 → T7 → T4 → T5" with the D-section mapping.
 - 🟡2 T15c's R-citation corrected: R7.3 (steady-state pair) + the R7.9 byte-compat clause (not R7.9 alone).
### 🔴 Outstanding
 - (final confirmation pending — reviewer pre-approved freeze after this 3-line pass)

## Tasks Round 3 — 2026-10-16 (sdd-reviewer confirmation + fix pass)

### 🔴 Fixed
 - 🔴1 T15c's citation now reads R7.3 + the R7.9 byte-compat clause (the round-2 fix had been recorded but NOT landed — phantom detected and honestly corrected at the gate).
### 🟡 Addressed
 - 🟡1 the Phase-2 execution-order note is CLEAN now (plain "T3 → T6 → T7 → T4 → T5" + the D-section mapping — the "T5-engine/T6-action" garble removed).
 - 🟡2 T15e's engine cite corrected to R3.1 + R7.9.
 - Honest landing record: this entry supersedes the round-2 entry (verified line-level at the gate).
### 🔴 Outstanding
 - (none — reviewer pre-approved freeze after these ≈3 declarative lines)

**TASKS FROZEN — 2026-10-16 (3 rounds): proposal ×5, design ×5, specs ×1, tasks ×3. All four artifacts hold real freezes; `/sdd-apply` cleared.**

## USER AMENDMENT (copy context view "B") — 2026-10-16
User decision on the race/pre-check named-time copy: show not only the collision cell but the HOLDER'S full held range — *"…runs into an existing booking at 12:30–13:00 (part of a held 2-hour block, 12:30–14:30) — pick another slot."* (cell-only fallback when no live link is resolvable). Landed declaratively into design D6 (both named-time arms + transport keys `holder_start`/`holder_end`) + spec R4/R7 + tasks T4/T9/T15b-d folds (same commit set). No code yet — apply implements it atomically.

## USER AMENDMENT — context view "B" — 2026-10-16 (user decision on the race/pre-check loser copy)
The named-time copy now appends the HOLDER's live range — *"…runs into an existing booking at 12:30–13:00 (part of a held 2-hour block, 12:30–14:30) — pick another slot."* — via the return's new `holder_start`/`holder_end` keys (one junction lookup per failing occupied slot; cell-only fallback when no live link resolves; `span_overlap` arms identical). Landed declaratively: design D6 (occupied + span_overlap arms + transport bullet), spec R4/transport/R7.2(b), tasks T4/T9/T15b/T15c. Recorded in review-log; code lands atomically at `/sdd-apply` (no unfreeze needed — user-directed, pre-implementation).

## Apply completion note — 2026-10-17 (implementer)
T1–T14 landed: migration + model hasMany; the OCC **two-phase reservation** with the per-branch rollback
contract (peri-flip failures THROW a RuntimeException subclass out of the txn; the single conflict audit row
re-written AFTER the rollback with the `concurrent_reserve_conflict` forensic token + parsed failing facts,
`replacement_request_id` pre-nulled so compensation cannot double-erase it); the ACTION passes the span,
derives `endHour`-driven bounds (invalid_span pre-request, no write/audit), does the span pre-resolve
(`failing_kind` occupied/missing) and the `assertSet('span>1' === span_overlap)` adaptive token binding;
the engine validates the full span (vector hoist); the cancel releases the span (skip non-pending rows, one
audit per actual flip); conflictText's 4-arg routing table (named-time + holder-context amendment "B"); the
read-model family (venue one-event-per-request via the junction; arrangement ownership via the junction;
apiReadController's myRequests span + venue-slot covers; MyRequestHistory's pending-row span);
the blade cap family (0.5–2h clamps, resize clamp 4, MAX_SELECTION 4, TC-1.4 re-pointed).
Gates at close-out: pint ✓ phpstan 0 ✓ phpunit 181/181 ✓ Playwright 165/165 ✓ (demo DB untouched all
along: 0 requests/0 reserved pre-existing → the additive migration ran on the demo with the user's notice).
The full-block fixtures for VenueTimetableTest craft 4-slot interior runs via craftSpanAt.
Remaining: the SDD closure (T19) — final status + reviewer confirmations in verify.
