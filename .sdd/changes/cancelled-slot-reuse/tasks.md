# Tasks — cancelled-slot-reuse

Date: 2026-11-13
Batch 4, revision 2: frozen after independent Round 1 PASS, 2026-11-14, against frozen revised proposal/design/specs (each Round 1 PASS). Prior revision passed Round 2. Proposal, design and specs are frozen. Later user approval opened G1 (Apply), then explicitly approved the older regression fixes and Part B. The separate Part A commit approval was exercised as b1f13fd. User subsequently approved two separate commits for the regression fixes and Part B; no push or demo cleanup is approved. See apply-log.md.

## Stop and approval gates

At the planning freeze, the user had authorised planning only. The later instruction “u may sdd apply.” opens G1 for code/testing. Only verified implementation units are checked below. Later commit approvals are recorded in apply-log.md; demo cleanup and push remain unapproved. Reviewer PASS alone is never implementation approval.

Later gates:

- **G1 — code Apply:** user explicitly authorises implementation against this frozen chain. Recheck dirty files/other sessions first.
- **G2 — demo cleanup:** separate approval for the exact fresh live plan, AFTER testing rehearsal and verified snapshot. G1 does not grant G2. Only class_replacement_fresh is eligible, never viva/demo twins.
- **G3 — commit:** obtain commit authority and stage only task-owned hunks. Separate Part A / Part B commits; no push without explicit push instruction. Never blanket-stage docs/demo-db-data-changes.md or ../ui-prototype/.

Each numbered task is one work unit of at most two hours, including its targeted test/check; stop and subdivide through the normal artifact process if a unit cannot fit. Estimates are planning bounds, not elapsed-time claims. Tests named below are proposed, not existing receipts.

## Part A — prerequisites and release safety

- [x] **A01 (0.5h) — Apply preflight, G1 required.** Re-read current rules/frozen chain, git status and shared-file diffs; record baseline failures and file ownership. Resolve overlap with guide/mobile/print lanes and arrangement-cancelled-room-hover's owner before editing shared files. Capture a read-only baseline of no-reuse event/card fixtures. Do not archive or tick other lanes/tasks. Depends: G1. Trace: R9, D1/D8/D10.

- [x] **A02 (1.5h) — planner geometry and base classification.** Add OriginalOccurrenceReservation with exact semester/source/week/coordinates and expected half-cell coverage. Implement original/all-available (including duplicates)/missing and non-available duplicate classification, plus out-of-range reporting. Add focused geometry/no-op fixtures. No release caller uses this until A02b completes. Depends: A01. Trace: S01, S02, S04.

- [x] **A02b (1.5h) — ownership/provenance classification and adversarial fixtures.** Complete request-held/ambiguous classification using junctions and mapper fallback for active junction-less claims; validate claim coordinates/state and lifecycle provenance. Test orphan/inactive links, stale owner/newer request, malformed claims, original/available/request-held mixtures and contradictory audits. Pin every S04 refusal category rather than trimming the matrix. Depends: A02. Trace: S03, S04.

- [x] **A03 (1.5h) — original release action and receipt.** Add ReleaseOriginalOccurrenceSlots consuming a locked validated plan, conditional status/owner/version flips, null owner only on changed cells, version+1, structured before/after/no-op reasons. Caller supplies legal audit action; no new schema/dependency. Test 30/60/120-minute spans, original/available/request-held mixtures and forced flip rollback. Depends: A02b. Trace: S01–S04, S08.

- [x] **A04 (1.5h) — source serialisation without nesting submit OCC.** Add one encapsulated advisory-lock boundary, fixed CRS namespace/source class-session key. Submit takes session-level try-lock before validation/create/OCC and finally-unlocks on the same connection; cancellation/approval use transaction-level try-locks. Preserve submit's no-outer-transaction, surviving conflict audit and compensateLoser semantics. Test unlock on success/refusal/exception and contender refusal on a second connection. Depends: A01. Trace: S12.

- [x] **A05 (1.5h) — cancellation transaction integration.** In CancelClassBatch acquire source advisory locks/session rows, source request rows and the union of original/landing slot rows in documented sorted order; revalidate provisional membership after locking. Capture/classify before deleting links. Integrate original release, audit receipts, no-op reasons and after-outer-commit notification seam. Test single and selected-day originals, unticked weeks/classes and duplicate cancellation refusal. Depends: A03, A04. Trace: S05, S08.

- [x] **A06 (1.5h) — cancellation-only approved landing release.** Extend ReleaseRequestSlots via explicit opt-in policy for proven attached pending/occupied landing ownership, used only by CancelClassBatch. Capture receipt membership before closure; protect newer holdings, refuse ambiguity and delete only the closed request's links. Pin existing strict/non-strict default behaviour for RejectRequest, CancelPendingRequest and AutoRejectRequest. Test pending and approved attached closures, reused cells and no orphan occupied tails. Depends: A05. Trace: S03, S06, S08.

- [x] **A07 (1.5h) — atomic selected-day withdrawals.** In CancelsClasses wrap the selected class batch plus checked make-up request withdrawals in one outer transaction. Precompute/lock the complete union of selected source sessions/requests/cells in D4 order BEFORE nested helpers; nested calls may reuse held locks, never acquire new lower-order locks. Fail/return consistently without proceeding after a refused batch. Test failed class batch makes no withdrawal, failed withdrawal rolls back all selections, ticked request-only leaves source untouched, unchecked rows survive and notification fan-out waits for outer commit. Depends: A05, A06. Trace: S05, S07, S08.

- [x] **A08 (1.5h) — safe approval original release.** Replace ApproveRequest's identity-only release loop with shared planner/action. Take ordered source/request and complete original+landing slot union before flips, revalidate and protect the approving request's entire landing set. Preserve existing exception/reason or stamp once if absent; keep junctions, decision audits and after-commit mail/notification seams. Test exact and partial original-coordinate reuse, stale class ids, original-vs-target weeks and ordinary approval stamp-if-absent. Depends: A03, A04. Trace: S03, S11.

- [x] **A09 (1.5h) — five-combination reuse feature matrix.** Add parameterised feature coverage for all five S09 reuse combinations through availability → submit → approve, using explicit testing DB, frozen dates/RefreshDatabase and shared fixture builders. Assert freed arrangement cells have normal available facts. Depends: A06–A08. Trace: S09, S11.

- [x] **A09b (1.5h) — independent-blocker feature matrix.** Add parameterised S10 room/lecturer/cohort/venue/capacity/holiday/Sunday/notice/span/active-request blocker cases. Assert active make-up busy vectors still block despite the source exception and uncancelled weeks stay occupied. Use the A09 fixtures without duplicating seed logic. Depends: A09. Trace: S10.

- [x] **A10 (1.5h) — deterministic two-connection race harness.** Build isolated testing receipt for two different sources competing on a released full span. Assert exactly one room winner, surviving loser OCC audit and no stranded request/link/cell. Establish repeatable barriers and bounded waits, not sequential mocks. Depends: A04, A09b. Trace: S12.

- [x] **A10b (1.5h) — source interlocks and fault receipts.** Reuse A10's harness for same-source submit/cancel and approve/cancel contention. Assert no source advisory lock leaks; add forced audit/flip exception and outer rollback notification checks using A05–A08's fixtures. Depends: A10. Trace: S08, S12.

## Part A — payload and shared layout

- [x] **A11 (1.5h) — shared event identity and cancellation discriminator.** Add shared identity/provenance helpers consumed by ResolvesTimetableTimeline, with current exception plus non-maintenance exact-pair class_cancelled evidence. Emit occurrence/request keys, merge same-key cohort facts once and keep true mapped spans. Pin user official_event vs approval-only official_event, missing evidence and maintenance receipt cases. Depends: A01. Trace: S13, S14.

- [x] **A12 (1.5h) — personal/cohort/student history scope.** Update shared timeline and its personal/cohort/student consumers for own-approved-original suppression regardless of overlap/landing location; scope before output. The 2026-11-14 correction is tracked by R202/R203 below; prior checked delivery is historical. Keep public student ReasonLabel while stripping private details server-side. Test pending→approved/withdrawn/rejected/auto-rejected lifecycle, same-source exact/partial overlap, other active reuse and unrelated cohort/staff exclusion. Depends: A11. Trace: S14–S16.

- [x] **A13 (1.5h) — venue history/request payload.** In VenueTimetable build history independently of occupancy, read requests via live receipt/mapper even with stale class ids, remove request-claimed rows from original normal output, and replace same-start merging with identity-based deduplication. Suppress an original whose own replacement is approved, including exception backfill regardless of overlap/landing room, while retaining other-staff modal restrictions. The 2026-11-14 correction is tracked by R202/R203 below. Test cancellation plus different-source room reuse and approved same-source exact/partial overlap with correct full spans. Depends: A11, A12. Trace: S13–S16.

- [x] **A14 (1.5h) — pure shared lane planner.** Add layoutTimetableDay to ui-common.js using connected overlap components, half-open intervals, cancelled-first deterministic start/end/key sort, first-fit lanes with every overlapping cancellation above active blocks. Add tests/timetable-lane-layout.spec.ts using the existing Playwright runner/browser context and page.evaluate to call the served ui-common.js helper with synthetic arrays. No new runner, dependency or module export is needed. Assert input shuffle invariance, exact/partial/multiple/adjacent spans, non-overlap lane reuse and no payload mutation. 2026-11-14 regression evidence adds the explicit pending 13:00–14:00 / cancelled 13:30–14:30 case and a 1,764-pair independent cell-occupancy oracle in both input orders; see apply-log.md. Use the normal guarded 8030 harness; A21 and B03 must execute this spec. Depends: A11. Trace: S17.

- [x] **A15 (1.5h) — default grid lane rendering.** Refactor buildTimetableGrid default path to render lane rows, shared day-header rowspan, true spans and stable event targets; keep status/ownership classes. Wire staff/student/cohort callers to the common contract without duplicating lane planning. Add DOM fixture checks for same-start distinct keys, aligned hour columns and no-overlap height. The 2026-11-14 regression check measures actual half-hour column positions for pending 13:00–14:00 / cancelled 13:30–14:30 and an adjacent 14:30–15:30 normal event, retaining phone-card visibility. Depends: A12, A14. Trace: S17, S18.

- [x] **A16 (1.5h) — venue custom rendering and selectable base row.** Extend shared custom-cell contract with lane context; adapt venue renderer without another lane algorithm. Expose a real free-cell base row only when needed below history; ignore cancelled/history geometry in roomBeforeNextEvent and span picking. Assert pending/occupied blockers still block, history never limits free full spans, and base rows add zero event identity. Depends: A13–A15. Trace: S18, S20.

- [ ] **A17 (1.5h) — independent interactions and notification targeting.** Preserve data-sid/data-rid and add eventKey; share click/Enter/Space opener and keep Escape/focus return. Update source jumps to occurrence/week and request jumps to request id. Browser-test each neighbour's modal, original/landing true spans, no private action leakage and no targeting the first same-cell neighbour. Keep 680px details width and existing footer dimensions. Depends: A15, A16. Trace: S15, S19.

- [x] **A18 (1.5h) — identity-based summaries and venue cell union.** Replace day/start overwrites in myTeachingStats and dedupe card inputs by eventKey. Keep pending/confirmed/conflict/holiday formulas and viewer scoping; verify PHP venue counters use real cell unions/current denominator. Test combined-cohort duplicates vs distinct coincident events, 09–11 cancellation plus pending arithmetic, zero base-row contribution and exact no-reuse baseline. Depends: A12, A13, A15, A16. Trace: S22, S23.

- [ ] **A19 (1.5h) — shared responsive/print compatibility.** Add only local token-based lane styles in theme.css; avoid global positioning changes. Verify 1440/768/390 widths, both themes, sticky header alignment, horizontal scrolling, multi-event content height and print row retention using scoped screenshots/print emulation. Do not implement print-v2 or redesign modals. Depends: A15–A18. Trace: S17–S21.

## Part A — cleanup facility (code/rehearsal only, NOT demo execution)

- [x] **C01 (1.5h) — read-only inventory and fingerprint.** Implement default-dry-run classes:release-cancelled-slots facility using shared classifier/discriminator. Show current_database, exact pairs/cells/versions, original/available/protected/ambiguous results and before/after plan. Include every scoped ownership input in fingerprint; maintenance/undo receipts are not source evidence. Test dry-run zero writes including zero audits/notifications. No scheduler/controller/render registration. Depends: A02b, A03, A11. Trace: S26, S28.

- [x] **C02 (1.5h) — fail-closed apply mode and receipts.** Require explicit apply/target/approved plan/verified snapshot reference; allow fresh only for live writes, testing only for rehearsals, refuse viva/demo/others. Acquire D4 locks, reclassify/fingerprint before writes, conditionally release approved originals and write legal maintenance receipts. Testing assertions cover missing gates, all-or-nothing fingerprint drift, protected junction/anchor-span claims, excluded ambiguous occurrences and no-op repeat with a fresh zero-change plan. Tests never connect to a demo DB. Depends: C01, A04–A08. Trace: S27–S29.

- [x] **C03 (1.5h) — operator cleanup/recovery runbook and testing rehearsal.** Write docs/cancelled-slot-reuse-cleanup.md covering printed target, current dry-run, approval boundaries, verified pg_dump -Fc/pg_restore --list with checksum, receipt validation and separately approved pre-reuse recovery. Provide testing-only rehearsal for eligible/missing/reused/ambiguous cells and drifted-version/reused rollback refusal; prove no partial restore and versions only increase. Rehearse recovery on testing, not demo; do not assume an old backup or stale plan is approved. Depends: C02. Trace: S27–S30.

## Part A — delivery verification

- [ ] **A20 (1.5h) — guarded E2E fixture and approved lifecycle.** Add guarded tests/cancelled-slot-reuse.spec.ts and shared fixture helpers at 8030: selected cancel → plain-green eligible room → same-class request → two independently targeted pending/original blocks → approved occupied full range with only the approved landing (original hidden, stored history retained). SQL fixtures require the exact receipt-target guard; choose pending starts safely outside checker expiry and verify fixture ids/status. Do not stop/change checker. Depends: A09b, A10b, A17–A19. Trace: S05, S09–S11, S14, S16, S19, S20, R9.

- [ ] **A20b (1.5h) — browser request-ending lifecycle receipts.** Reuse A20 helpers for withdrawal, rejection and automatic-rejection outcomes: landing disappears/releases while cancellation and correct original details survive. Keep deterministic testing-only clock/fixture handling; an explicit auto-reject invocation must itself verify testing target and must not modify cron. Depends: A20. Trace: S06–S08, S16.

- [ ] **A20c (1h) — browser cards/scope and layout evidence.** Reuse A20 helpers for summary arithmetic, own/other-staff and student scope/private-field assertions, and exact/partial/multiple lane targets. Retain screenshots from A19's viewport/theme/print checks rather than rebuilding them here. Depends: A20, A18, A19. Trace: S13–S15, S17–S19, S21–S23.

- [ ] **A21 (1.5h) — Part A gate execution.** Run composer run lint:check; vendor/bin/phpstan analyse --memory-limit=1G --no-progress; DB_DATABASE=class_replacement_testing php artisan test; node --check public/js/ui-common.js; DB_DATABASE=class_replacement_testing php artisan view:cache; scoped guarded Playwright including tests/timetable-lane-layout.spec.ts, tests/cancelled-slot-reuse.spec.ts, desktop/tablet/phone/light/dark/print and selected-day regressions. Every browser command uses CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030; exclude TC70. Record exact commands/pass counts and pre-existing failures. If gates exceed this unit, stop/split rather than conceal unfinished verification. Depends: A10b, A20b, A20c, C03. Trace: R9, all Part A scenarios.

- [ ] **A21b (1h) — Part A evidence/changelogs/scope reconciliation.** Update matching timetable/arrangement page changelogs and later project context/handover with implemented behaviour, exact receipts, known failures and separate unexecuted cleanup gate; preserve other sessions' hunks. Verify final scope against frozen artifacts and document any unfinished gates without calling them passed. Depends: A21. Trace: R9, proposal §7, D10.

- [ ] **A22 (0.5h) — Part A review/commit gate.** Present diff and receipts, confirm no demo cleanup executed, request G3 if not already granted. Stage only owned Part A source/tests/changelog hunks (including cleanup facility/runbook, not an operational demo write); conventional commit only if authorised. Do not stage another session's docs/demo-db-data-changes.md rows. No push. Depends: A21b, G3 for actual commit. Trace: proposal §9, D10.

## Part B — separate holder explanation delivery

- [x] **B01 (1.5h) — actual venue-only holder payload.** After agreement with the other hover lane's owner, thread actual active holder identities/text-library references into venue-only tooltip facts, prioritising request receipt over stale class id. Share existing formatter and dedupe factual identity across room/lecturer/cohort arms. Keep public state/allowed lecturer/cohort data and no private remarks/reasons/submission data. Add payload tests for own/other lecturer, no cohort overlap, pending/approved holders and multiple distinct holders. Depends: A13, A22; G1 must cover Part B. Trace: S24.

- [x] **B02 (1.5h) — desktop/mobile tooltip composition.** Update arrangement desktop and mobile to consume the same holder text and permitted status/time vocabulary. Preserve classifyState and real labels/eligibility; escaped data-tip/newlines only, shared 12-hour range formatter. Test unknown/ambiguous and still-occupied pre-cleanup cancelled original produce neutral Venue Conflict; freed cells have no new cancellation note and new genuine active holders are named instead. No cleanup/read-write or border/legend redesign. Depends: B01. Trace: S24, S25.

- [x] **B03 (1.5h) — Part B regression receipts/changelog/gates.** Add scoped guarded room-only hover desktop/mobile receipts for both themes, no shared lecturer/cohort, duplicate-arm deduplication, protected fields and unchanged booking answers. Run relevant feature tests, lint/PHPStan/JS/view checks, tests/timetable-lane-layout.spec.ts and arrangement browser regressions with exact testing target. Update arrangement changelog; record failures honestly. Depends: B02. Trace: S24, S25, R9.

- [x] **B04 (0.5h) — separate Part B commit gate.** Present diff/receipts and stage only owned Part B hunks with G3 approval, conventional commit separate from Part A. Leave parallel hover planning files and task list untouched unless user authorises reconciliation. Never push. Depends: B03, G3 for actual commit. Trace: proposal §5/§9.

## Later operational hold — not bundled with Apply

- [ ] **O01 (0.5h) — prepare exact live plan, no data write.** Only if the user requests legacy cleanup: verify implemented/tests-passed facility, read-only current fresh DB inventory, equivalent testing rehearsal receipts, printed target and row counts; prepare/verify a current pg_dump -Fc (pg_restore --list + checksum) per safety policy. Present exact proposed cell/field/version changes, no-ops and exclusions and request G2. Do not run apply while waiting. Depends: C03, A21b, explicit user request to prepare live operation. Trace: S26, S27.

- [ ] **O02 (1h) — separately approved fresh-only cleanup and receipt.** ONLY after G2 for the exact current plan: verify target/backup/approval once more, apply identity-scoped plan; abort on drift and return for fresh inventory/approval. Verify changes, protected fields/links/versions, other weeks and counts; append only this operation to docs/demo-db-data-changes.md and retain plan/operation receipt. Second inventory must have no repeat work. No viva/demo writes, notifications or scheduled run. Depends: O01 and G2. Trace: S28, S29.

- [ ] **O03 (0.5h) — rollout/recovery decision boundary.** Present final before/after receipt and reuse status. Do NOT automatically recover/reoccupy cells. If rollback is requested, take a new exact-state inventory and separate recovery approval; any drift/reuse forbids partial restore and requires user recovery decision. Preserve cancellations and monotonic versions. Code rollback is not class reactivation. Depends: O02; recovery execution is not authorised by this checkbox. Trace: S30.

## Scenario-to-task coverage

| Scenarios | Main tasks |
|---|---|
| S01–S04 original ownership/no-ops/ambiguity | A02, A02b, A03, A06, A08 |
| S05 single/day scope | A05, A07, A20 |
| S06 attached pending/approved requests | A06 |
| S07 request-only/atomic selected day | A07 |
| S08 receipts/rollback/notifications | A03, A05–A07, A10b, A20b |
| S09 reuse matrix; S10 blockers | A09, A09b, A20 |
| S11 approval/self-release protection | A08, A09, A20 |
| S12 concurrency/OCC/audit/lock lifetime | A04, A10, A10b |
| S13 cancellation discriminator | A11, A13, C01 |
| S14 identity/spans/request-owned row exclusion | A11–A13 |
| S15 scope/privacy | A12, A13, A17, A20c |
| S16 lifecycle/approved suppression | A12, A13, A20, A20b |
| S17 lane algorithm | A14, A15, A19 |
| S18 all render paths | A15, A16 |
| S19 interaction/jumps | A17 |
| S20 real selectable venue cells | A16, A20 |
| S21 responsive/theme/print | A19–A21 |
| S22/S23 summary identity/union/baseline | A18, A20c |
| S24 active room-only holders | B01, B03 |
| S25 presentation/fallback/free cells | B02, B03 |
| S26 dry-run/provenance/fingerprint | C01 |
| S27 gates/snapshot/rehearsal | C02, C03, O01 |
| S28 scoped writes/drift/no scheduler | C01, C02; O02 only with G2 |
| S29 repeat/receipt | C02, C03; O02 only with G2 |
| S30 safe rollback boundary | C03, O03 |
| R9 verification/stop | A01, A21, A21b, B03; all writes require G1/G2 as applicable |

**Original 2026-11-13 Apply checkpoint (historical):** 27/38 units checked, including B01–B04 after 98 passing backend checks, independent fallback rereview, and 8/8 new room-holder browser checks with inspected desktop/phone light/dark PNGs. The five older browser failures are reconciled: 24/24 combined checks now pass, including the three formerly unrun followers. The 26/26 lifecycle/layout/planner rerun passes; the unrelated known full-suite history-shape error and final Part A/whole-feature Verify remain open. Part A commit b1f13fd was approved and completed separately. User approved the two continuation commits: regression fixes ca98fb5, then the separate Part B delivery containing this update. No push or demo cleanup approval. Shared-file ownership and preflight snapshots are recorded in apply-log.md. Do not tick another lane or treat a scoped pass as final Verify.

## Revision 2 — own-approved-original suppression (2026-11-14)

User approved and reconfirmed: ONLY that occurrence's own Approved replacement hides its cancelled timetable block. Pending/rejected/cancelled/withdrawn/auto-rejected/no request retain it; unrelated-source approval never hides it. Proposal/design/specs revision 2 passed independent Round 1 reviews. Tasks revision 2 passed independent Round 1; user-approved implementation may proceed within R201–R205. These five refinement units are tracked separately from the original 38-unit delivery; whole-feature Verify remains open.

- [x] **R201 (0.5h) — preflight and protected testing.** Preserve unrelated tracked/untracked work and source snapshots. Keep the user's :8030/request 19/manual data and scheduler untouched. Save verified testing dump plus SELECT-only before-state. Force literal testing database, temporary socket/55439 and expected data_directory in an out-of-repo PHPUnit config/pre-test bootstrap; abort on any configured/queried target mismatch before a test write. Trace: D10/R9.
- [x] **R202 (0.5h) — shared server filtering.** In ResolvesTimetableTimeline and VenueTimetable's exception backfill, skip only own-approved source pairs (existing active-semester/session/source-week maps), regardless of original/landing overlap/week/day/room or other active reuse. Remove only now-unused overlap interval accumulation and update policy comments. Preserve landing full-span reader, still-visible provenance/privacy, generic identity API, pure JS planner and all booking actions/history records. Trace: D5/S14–S18/S22.
- [x] **R203 (1.5h) — backend state/geometry matrix.** Update four obsolete approved-retention cases without weakening landing/occupancy assertions. Add all-four-renderer pending/approved/rejected/cancelled/auto-rejected/no-request cases, own-approved exact/partial-earlier/partial-later/cross-week/day/room cases, unrelated-source approval and other-active-reuse controls. Assert exact keys, full spans, other source weeks and unchanged business records across reads. Run baseline against saved original payload sources using an isolated bootstrap override (not by rolling back the live app), then current targeted/related backend tests on the verified temporary PostgreSQL instance only. No tests against the manual cluster. Trace: S14–S16/S22/R9.
- [x] **R204 (1h) — browser consistency without manual writes.** Update stale own-approved expectations in tests/cancelled-slot-reuse.spec.ts, retaining pending/rejection/withdrawal cases, full-span/modal/occupancy checks and expected conflict removal. Collection-check it but explicitly do not execute its writing fixture on current manual data. Run existing read-only planner/layout specs with exact :8030/Chromium/one-worker/TC70 guards. Inspect actual approved request 19 on all four real pages, source absent/landing present at true time, with discovered cohort/student membership. Capture desktop/phone, light/dark evidence; retain history details. Restart only :8030 via sanctioned script if needed, preserving original testing connection. Trace: S15–S23/R9.
- [x] **R205 (1h) — scoped verification/review/receipts.** Run lint, PHPStan1G without suppressions, PHP/JS syntax, view compilation and whitespace checks. Independent read-only implementation review and fix/rereview if needed. Compare manual business state and unrelated diffs; stop temporary backend-test cluster only, leave :8030 running. Update matching changelogs, apply/review log, metadata and handover; report executed vs unexecuted checks. No new commit/push/archive/demo cleanup or whole-feature Verify claim. Trace: D10/R9.
