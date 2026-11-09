# review-log — cancelled-blocks-stay-visible

## Round 1 — 2026-10-27 (glm-5.3-flash)
### 🔴 Fixed (proposals folded)
 - 🔴1 legacy `official_event` release-stamp backfill: the pair-join UPDATE
   (release stamps coexist with an APPROVED replacement request for the same
   session+week) added as a user-paste safety sheet; verified the demo DB
   currently holds 0 class_exceptions rows (the backfill is a no-op today
   pasted for drift protection).
 - 🔴2 the ui-common.js scope cell re-cut into FOUR named touch points
   (:726 + :754 the two upstream day-event filters — cancelled events are
   discarded BEFORE the renderer today; :805 the class family; :650 the
   note; :2343 eventStatusLabel — the EXISTING cancelled branch gains the
   reason, no duplicate arm).
 - 🔴3 decision 2 re-machined: the :890 conflicts tally gains
   `|| e.status === 'cancelled'`; total/hours EXCLUDE cancelled (the
   honest-numbers law + the frozen #sumTotal=6 receipt); FOUR call sites
   named (the 3 filter blades + cohort :463 already unfiltered = untouched).
### 🟡 Folded
 - the tip law locked to ONE form (`{cohort · CANCELLED — ANNUAL LEAVE}` via
   the existing :801 uppercase-topper); the eventStatusLabel cancelled
   branch is a modification not a new arm; `pendingByMakeupSlot` renders the
   amber block TODAY for cancelled+proposed (the skip only ate the request
   week) — the new test's receipt re-stated to the correct baseline; the
   rejected-teardown status-filter receipt pinned; the CHECK paste runs both
   statements in one psql session with a `\d class_exceptions` verify.
### 💡 Carried into design
 - the state matrix row; sdd.yaml created.
### Verdict: NOT ready to apply — READY for design drafting with mandatory carry-overs (all landed above). Round 2 pending on design.md.

## Round 2 — 2026-10-27 (design round-1)
### 🔴 Fixed
 - 🔴1 D7's ×9 re-key claim was factually WRONG — the 7 ReplacementFlowTest
   stamps are occurrence-PIN fixtures (live facts for
   SubmitReplacementRequest, no approve involved); ALL re-keys STRUCK; the
   suite stays 258/258.
 - 🔴2 no migration deliverable existed — SUPERSEDED: the discriminator design
   re-cut to the READ-TIME approved-pair derivation, which deletes the CHECK
   widen, the migration, the ApproveRequest edit, the backfill AND the demo
   paste in one stroke (the D7 release stamp's dual duty becomes harmless:
   the approved pair decides, not the reason string).
 - 🟡3 the legacy-state permanent hazard → the derivation handles it (the
   approved pair skips regardless of the stamp reason); D2 row-5 dead row
   STRUCK; D8 the sheet STRUCK; the :226→:228 anchor drift noted for the
   implementer.
### Verdict pending round 3.

## Round 3 — 2026-10-27 (design superesse confirmed + 4 impl blockers)
### 🔴 Fixed
 - 🔴1 D1's control flow: the exception'd branch ALWAYS `continue`s, emits
   BOTH blocks on a same-week makeup (a total blank today — the R1/R2
   receipt caveat pinned in D7#2); the pending path can never see an
   exception'd week (branch ordering); no-fallthrough double-push killed.
 - 🔴2 D6 re-machined: THREE edits (the :890 tally term + the 3 blade
   filters removed + `myTeachingStats` gated) — the filter removal and the
   gating between them stop the conflicts-starving and the hours-inflating.
 - 🔴3 the FIFTH D5 touch point: the cohort page's own two-axis
   statusClassFn (:508) gains the cancelled arm FIRST — the neutral-else
   broken law ① fixed on the cohort surface.
 - 🔴4 D7/D9 re-cut: `TimetableWiringTest`'s empty-state receipt
   (:243, 'public_holiday' W3 NO request = the red-cancelled state) FAILS
   under the derivation and is UPSERTED (the red block renders); the gate
   shape = 257 + 1 re-cut + 4 new.
### 🟡 Folded
 - the fact's COMPOUND reason shape vs the bare `cancelledReason` label
   pinned in D7#1; the handled-null pinned in D7#2; the inertness receipt
   (cellRender branch feeds arr+venue, no cancelled payloads); the anchor
   re-pin covenant (re-grep from the file at apply: :731/:788/:890/myTeachingStats/:2338/:650); the bool map dies (one `$exceptionRow` keyBy);
   `ReasonLabel::OVERRIDES['replaced']` = dead-but-harmless, marked.
### Verdict: the round-4 delta check pending; the FREEZE walk after it.

## Round 4 — 2026-10-27 (delta-only)
 - 🔴 none. The 4 round-3 folds verified present + decision-correct.
 - 🟡 folded in the freeze walk: the D1 snippet carries the amber emission +
   the always-continue; the D9 arithmetic 257+1+4=262.
 - VERDICT: READY — proceed to /sdd-apply. FREEZE walk: proposal decisions
   locked by law defaults (students INCLUDED, summary INCLUDE-with-tally,
   the block note YES) pending the user's sight; design frozen; tasks to be
   written next.

## Round 5 — 2026-10-27 (tasks first-cut receipt)
 - tasks.md written against the frozen design (T1–T6); the amber-helper
   shape clarified in D1 (hoist-or-inline left to the implementer).
 - FREEZE reached: the proposal + design + tasks frozen; status → frozen;
   the lane awaits the user's `proceed` for /sdd-apply.

## Round 5.5 — 2026-10-27 (post-freeze user decision — no unfreeze, decision-level polish)
 - USER: 'remove Reason' — the tip = `cohort · CANCELLED`, the note = `(Cancelled)`.
 - D5 touch point ④ = NO EDIT now (the existing :2338 arm already returns plain
   'Cancelled'); ③ = the plain note; the payload `cancelledReason` (the home
   badge label law) STAYS riding; the popup's fact family untouched.
 - D2 tip/note receipt rows re-keyed; D7's test receipt notes the TIP law.
 - Review-log + artifacts frozen apportioned. The apply walk (T1–T6) inherits.

## Round 6 — 2026-10-27 (final micro-check)
 - all blockers resolved (D5 ③④ plain/NO-EDIT + T3 clean).
 - the acceptance stray line GOT the replace (grep confirms no stale
   'Cancelled — Reason' text anywhere) — the soft-freeze condition
   discharged; `ReasonLabel` stall: the OVERRIDES `replaced` stays marked
   dead-but-harmless.
 VERDICT: READY-FOR-APPLY (the freeze state).

## APPLY — 2026-10-27 (T1–T6)
 - T1 the loop head re-cut (the $approvedByPair map + the $exceptionRow
   keyBy + the cancelled render + the same-week amber hoist into
   buildAmberEvent — shared with the pending path, DRY).
 - T2 ReasonLabel (app/Support) + the two delegations.
 - T3 the four ui-common touch points + the cohort statusClassFn arm.
 - T4 the tally + myTeachingStats gating + the 3 blade filters removed.
 - T5 the 4-new + 1-upsert receipts; two pre-existing assertions
   root-caused (the baked ':1' id + the static empty-state assertDontSee)
   and fixed with receipts.
 - T6 changelogs ×4; gates: pint/phpstan/node --check/phpunit **262/262**
   (the predicted 257+1+4 shape exactly); NO Playwright (the user's
   regime); ONE feat: commit `e74b496`; NOT pushed.
 - The implementer deltas vs the design: buildAmberEvent hoisted as a
   helper (the D1 implementer-choice receipt); the amber emission also
   de-duplicated the pending path. The old test insights folded with
   receipts (the baked ':1' + the assertDontSee objectivity). NO other
   deviation. status → applied; verify.walkConclusion@gmail:
NEXT = /sdd-verify on the user's demand; the user tests manually.
