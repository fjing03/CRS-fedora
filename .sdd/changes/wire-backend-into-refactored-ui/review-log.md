## proposal Batch 1 — Round 1 — 2026-08-31

### 🔴 Fixed
(none — Round 1, first batch)

### 🟡 Addressed
(none — Round 1, first batch)

### 🔴 Outstanding
- **C1 — cohort-timetable route gated student-only contradicts locked RBAC matrix** (proposal.md, Scope → Slice A item 1). CodingMAIN §6 grants "View cohort timetables" to Student ✅ / Lecturer ✅ / PL ✅; no other route provides lecturers a cohort view and the shared nav exposes it to all roles, so `role:student` bakes a lecturer/PL 403 into Slice A middleware (decision-level conflict with FR 4.13). Fix: re-bucket as all-roles (`auth` gate suffices) or record as a deliberate dated user-decision deviation, as done for MockData.

### 🟡 Advisory
- **A1 — Slice C claims FR 3.1–3.7 but item 7 omits FR 3.3/3.4** (queue columns proposer/subject/cohorts/time/venue; pre-computed slot validity display). Name them so `approval-workflow-wiring` spec covers the full page.
- **A2 — FR 2.11/2.12 untraceable** (lecturer cannot view/edit others' requests; cannot approve/reject). Add to the FR 4.13–4.14 traceability row or a scoping note so specs define ownership-scoping acceptance criteria.

## proposal Batch 1 — Round 2 — 2026-08-31

### 🔴 Fixed
- **C1 — cohort-timetable RBAC bucket** ✅ verified. Slice A item 1 now buckets cohort-timetable all-roles (`auth` only) per CodingMAIN §6 "View cohort timetables" (Student ✅/Lecturer ✅/PL ✅, FR 1.2); student-only narrowed to student-my-timetable + upcoming-replacements; lecturer+PL (`role:lecturer`) covers my-timetable, replacement-home, replacement-arrangement, my-request-history, venue-timetable (verified row-by-row vs §6); PL-only `pl` for request-approval. Code-verified: `users_role_check` = ('student','lecturer') so PL passes `CheckRole('lecturer')`; PL-ness via `lecturers.is_pl` in `CheckPl`.

### 🟡 Addressed
- **A1** ✅ Slice C item 7 names FR 3.3 (proposer/subject/cohorts/time/venue) + FR 3.4 (pre-computed slot validity); FCFS earliest `submitted_at` index-backed.
- **A2** ✅ FR 4.13–4.14 row traces 2.11/2.12 via ownership scoping.

### 🔴 Outstanding
(none)

**VERDICT: PASS — proposal.md FROZEN as Batch 1 baseline.** Design note carried to design.md: middleware order `['auth','role:...']` so guests hit FR 4.14 login redirect before CheckRole's '/' redirect.

## design Batch 2 — Round 1 — 2026-08-31

### 🔴 Fixed
(none — Round 1, first batch)

### 🟡 Addressed
(none — Round 1, first batch)

### 🔴 Outstanding
- **D1 — D5 compensating delete violates the `audit_logs` FK on every OCC conflict** (design.md D5 row, State machine "Submit — OCC loser", Actions `SubmitReplacementRequest`). Code-verified: `audit_logs.replacement_request_id` = `nullable()->constrained('replacement_requests')` with NO ON DELETE action (NO ACTION), and OCCValidator::auditConflict() commits the conflict audit row referencing the loser's request id BEFORE returning. Compensating delete of the request row therefore throws QueryException on EVERY conflict → loser gets 500 instead of FR 4.10 toast; success criterion 3 fails. Fix (within D5 boundary, no unfreeze): null `audit_logs.replacement_request_id` FIRST, then delete request row; explicitly forbid deleting/nulling the audit row itself. No nullOnDelete() migration (frozen out of scope).

**VERDICT: FAIL — fix-loop, Round 2 required.**

## design Batch 2 — Round 2 — 2026-08-31

### 🔴 Fixed
- **D1 — D5 compensating delete vs `audit_logs` FK NO ACTION** ✅ verified in all three places, mutually consistent: (1) Decisions row 5 — `UPDATE audit_logs SET replacement_request_id = NULL ...` FIRST (conflict audit row SURVIVES — FR 4.12), THEN `DELETE` the request row; deleting/nulling the audit row FORBIDDEN; no `nullOnDelete()` migration. (2) State machine "Submit — OCC loser" — null-FIRST-then-delete, cross-refs D5. (3) Actions `SubmitReplacementRequest` — pre-null audit FK, then delete request. Code-verified: audit_logs FK = NO ACTION (`2026_08_03_000010:19`); `OCCValidator::auditConflict()` commits conflict row before returning (app/Services/OCCValidator.php:69–83). Success criterion 3 now achievable.

### 🟡 Addressed
- **D10 transitional rule** ✅ Slice A→B: routes whose component class does not exist yet serve the legacy template regardless of flag (component-class existence check), same middleware — wiring one page never breaks the others.
- **D2 wording** ✅ layout `@yield('page-styles')` (ui-template.blade.php:20).

### 🔴 Outstanding
(none)

**VERDICT: PASS — design.md FROZEN as Batch 2 baseline; proceed to Batch 3 specs.**

## specs Batch 3 — Round 1 — 2026-08-31

### 🔴 Fixed
(none — Round 1, first batch)

### 🟡 Addressed
(none — Round 1, first batch)

### 🔴 Outstanding
- **S1 — timetable-data-wiring attributes FR 1.4 to the wrong page; Upcoming Replacements behaviorally unspecified** (specs/timetable-data-wiring/spec.md:41–49). FR 1.4 upcoming-replacement details bound to Student My Timetable, but frozen proposal Slice A.1/B.4 + design component table assign FR 1.4 to the separate UpcomingReplacements route/component (StudentMyTimetable carries only sessions + FR 1.3 statuses). No other spec covers UpcomingReplacements → Batch 4 would misplace FR 1.4 rendering; page ships with zero acceptance criteria. Fix spec-only: extend Purpose; keep FR 1.3 chips on Student My Timetable; move/reword FR 1.4 requirement + scenario to Upcoming Replacements (optionally add FR 1.3 status-chip scenario).

### 🟡 Advisory
- **S2 — approval-workflow-wiring "Reject with reason" scenario lacks GIVEN** (spec.md:51–54).
- **S3 — replacement-flow-wiring Req 1 ambiguity** (spec.md:9–11): "list … plus own request history and venue timetables" reads as Home hosting them; reword to "MUST provide access to".

**VERDICT: FAIL — fix-loop, Round 2 required (S1 mandatory; S2/S3 recommended).**

## specs Batch 3 — Round 2 — 2026-08-31

### 🔴 Fixed
- **S1 — FR 1.4 re-attributed; Upcoming Replacements now specified** ✅ Purpose names all four pages; Student My Timetable keeps FR 1.2 + FR 1.3 (status-chip scenario) + read-only FR 1.5–1.8; new standalone requirement carries FR 1.4 with happy + exclusion scenarios. Matches frozen design component split (UpcomingReplacements = Slice B, student-only, approved-only, own cohort).

### 🟡 Addressed
- **S2** ✅ "Reject with reason" now full GIVEN/WHEN/THEN.
- **S3** ✅ Req 1 reworded to "MUST provide access to".

### 🟡 Advisory (applied at freeze, declarative)
- **S4** ✅ word budgets: timetable-data-wiring 761→617 `wc -w` (prose compressed, zero requirements/scenarios removed); replacement-flow 652→649.
- **S5** ✅ "current or future weeks (week ≥ current)" pinned per design.md:52.
- **S6** noted → carried to Batch 4 tasks (VenueTimetable selection scenario derivable from design component table).

### 🔴 Outstanding
(none)

**VERDICT: PASS — specs/ FROZEN as Batch 3 baseline; proceed to Batch 4 tasks.md.**
**Correction (freeze-time counts):** S4 final `wc -w`: timetable-data-wiring 761→**645**, replacement-flow 652→**649**; approval 605, rbac 611 — all <650. All requirements/scenarios preserved verbatim in count-relevant substance (wording-only compression).

## tasks Batch 4 — Round 1 — 2026-08-31

**Process note:** tasks.md authored inline by orchestrator (executor subagent spawn failed 2×: "model admission check failed"); reviewer subagent ran normally. Deviation recorded for transparency.

### 🔴 Fixed
(none — Round 1, first batch)

### 🟡 Addressed
(none — Round 1, first batch)

### 🟡 Advisory (non-blocking; compensated by frozen design/specs at apply time)
- **T1 — spec→task citation gaps**: engine semantics FR 4.3–4.7 + no-slots empty state (FR 4.4/4.6) not cited in 2.3; FR 2.7/2.8 grid interaction uncited; ownership scoping FR 2.11/2.12 + student direct-action denial (rbac Reqs 3–4) uncited in 2.5/3.3; mock-fallback gating scenarios have no test task. Mitigation: design.md Actions table + data-flow + D10 govern at apply.
- **T2 — design "Promoted to shared" files have no tasks** (`partials/ui-slot-cell`, `partials/ui-confirm-modal`, `ui-common.js` notify bridge): apply agent must honor design File Changes.
- **T3 — per-slice static gate placement**: pint/phpstan only in Phase 4.1; backstopped by AGENTS.md standing rule; apply should treat as standing per-slice gates.
- **T4 — 0.3 vs 1.2 bucket ambiguity**: if 0.3 installs full buckets, 1.1 RED is born green; end state identical either way.
- **Granularity**: 1.3/2.3/2.4/2.5 likely >2h each (word budget bundling) — acceptable for auto-chain.
- **Word count**: ≈525 manual (<530); `wc -w` = 527.

### 🔴 Outstanding
(none)

**VERDICT: PASS — tasks.md FROZEN as Batch 4. SDD package COMPLETE (proposal + design + specs + tasks, all reviewed & frozen). Ready for sdd-apply — hand T1/T2 to the apply agent as attention items. Slice order: A → B → C (auto-chain, no decision needed).**

## apply Slice A — 2026-08-31 (orchestrator-executed)

Executor delegation unavailable (model admission failures) → tasks 0.1–1.5 executed inline by the orchestrator. **All tasks done:** 0.1 `mock_fallback` config; 0.2 `Semester` model (+ relations; also created missing `Holiday`/`ClassException` models — additive, no migrations); 0.3 routes D10 branch + auth-first middleware; 1.1 RED observed (3 failures: 403-bucket 200s) → 1.2 buckets green; 1.3 three Livewire components + views; 1.4 canonical legend/empty-state/conflict styling inherited via shared engine + server data; 1.5 changelogs updated.

### Implementation notes (declarative record)
- Shared engine kept: components feed real semester/holidays/events into `ui-common.js` helpers (MockData-compatible bridge) — visual 1:1, Risk-2 mitigation; page glue inline per D2 (single root `<div class="lw-page">` per Livewire requirement).
- Promotion executed: `App\Concerns\ResolvesTimetableTimeline` (events builder + week scaffolds + JS registries) shared by all 3 components (§10.0.6 — 3rd duplication rule).
- T4 honored: auth-only routes first → RED observed → buckets → green (RouteGateMatrixTest 4 tests, 45 assertions).
- Blade gotcha fixed: a CSS comment containing a literal `@section(...)` directive broke the component root — reworded.

### Verification (Slice A gates — all green)
- `vendor/bin/phpunit` **104/104** (94 prior + RouteGateMatrixTest 4 + TimetableWiringTest 6)
- `vendor/bin/phpstan analyse --memory-limit=1G` **0 errors** (full paths)
- `vendor/bin/pint --test app/ routes/ tests/ resources/views/livewire` **passed**
- Smoke: guests → 302 `login.student` (FR 4.14); authenticated Livewire render OK; D10 branch verified (6 B/C routes still serve legacy closures)

## Amendment batch 1 — design.md — 2026-10-10 (unfreeze round 1)

Registered debt: `sync-upstream-fjing-ui` design §10 — "3-batch unfreeze + re-review before any Wave 3 apply". Wave 3b green-lit after the 2026-10-10 re-verification (full suite 130/130 after a one-off `npm run build` on MSI; scope, queue infra, OCC FK trap, rename debt, demo-DB honesty all confirmed).

### 🔴 Outstanding
- (none)
### 🟡 Addressed (applied before freeze)
- **VenueTimetable slice-label drift** (component-table row still "B" though its read path shipped early via `2026-09-30-venue-timetable-db` era SDDs — actual: `2026-10-09-venue-timetable-db` + `2026-10-10-venue-event-blocks-db` + `venue-toolbar-row-parity`) — inline stamped on the row so the Slice-B executor doesn't re-do it.
- **Historical line-number citation** (design.md:24 "closures at web.php:15–49") reworded as explicitly pre-change state.
### 💡 Noted (carried to batch 2)
- `specs/replacement-flow-wiring/spec.md:37` cites "design D11" for the anchor-slot limitation, but the decisions table is D1–D10 (content lives in Open Questions + D5) — fix the citation while that spec is unfrozen.
### Verdict
**PASS — batch 1 FROZEN** (round 1; reviewer verified all claims line-by-line against `routes/web.php`, `RouteGateMatrixTest`, `CodingMAIN.md:378`, AGENTS.md caveat, and D5's three reinforcement sites).

## Amendment batch 2 — specs — 2026-10-10 (unfreeze round 2)

### 🔴 Outstanding
- (none)
### 🟡 Addressed (applied before freeze)
- **Citation slip fixed in working tree:** amendment evidence said "CodingMAIN.md §10 page table" — the Page Inventory & Routes table is §9 (§10 is Coding Conventions). Fixed in rbac spec's Amendments section; the same slip inside frozen design.md L189 is noted here, NOT unfrozen.
- **Belt-and-braces citation added** to the "Student blocked from the staff cohort view" THEN line (§6 matrix footnote + §9 page table) so FR 1.2's resolution is self-contained.
### 🟡 Carried to batch 3 (from round-2 review)
- `app/Livewire/CohortTimetable.php` docblock still cites "all roles (rbac-route-gating spec)" and contains a now-unreachable student branch (`isStudent` L33/L60, own-cohort-only L92–94 — middleware 403s students first, test L112). Batch 3 adds tasks: docblock update + explicit keep-or-strip decision (keep = defense-in-depth).
- `CodingMAIN.md:388` mock-fallback table still lists `/upcoming-replacements-ui` — docs fix task added to batch 3.
- design.md L132 (TimetableWiringTest approach, "student sees only own cohort") is satisfiable only via StudentMyTimetable — one clarifying line in the batch-3 tasks note, no design unfreeze.
### Verdict
**PASS — batch 2 FROZEN** (round 1; reviewer confirmed the amended spec text now passes against `RouteGateMatrixTest`, matches frozen design.md verbatim, and no stale terms remain outside amendment self-references).

## Amendment batch 3 — tasks.md — 2026-10-10 (unfreeze round 3, final)

### 🔴 Outstanding
- (none)
### 🟡 Addressed (applied before freeze)
- **Task 1.2 historical parenthetical** — the checked-off Phase-1 task still described cohort-timetable as `auth`-only (all-roles); annotated as superseded (lecturer+PL, see 4.5 + amendment log 5) so spec-vs-task cross-checks don't trip.
- **Proposal supersession note** (amendment log entry 6) — frozen proposal.md L12/L17 carry the same stale bucket/rename; recorded as superseded by design amendments 1–2 without unfreezing the proposal, closing the residual-reference chain.
### 💡 Noted (no action)
- Residual inventory after all 3 batches: review-log/design amendment self-references only; design.md L189 §-number slip recorded round 2 (frozen); CodingMAIN.md:378 parenthetical tightening folded into task 4.4's adjacent edit during apply.
### Verdict
**PASS — batch 3 FROZEN. Package internally consistent (design ↔ specs ↔ tasks ↔ code); apply phase may start at Phase 2 (Slice B).**

## Amendment addendum 2b — grid granularity — 2026-10-10 (user-reported, round 1)

User catch: spec.md:37 said "a 2-hour class needs 2 adjacent green cells" (mock-era hourly reading) but the slot axis is 30-minute. A class starting 09:30 is unrepresentable hourly.

### Verified evidence
- `TimeSlotsSeeder.php:18-19`: hours 8–17 × minutes [0,30] → **20 half-hour rows/day** (08:00–18:00); ×6 days ×14 weeks = 120/venue (matches archived 23×14×120).
- Demo DB (psql, this session): **19 of 101 sessions start at :30** (19 end at :30; 21 unique touch :30).
- Shipped venue grid: 30-min rows, display-only span-N (`venue-timetable.blade.php:198` `span-` + end−start+1 half-hours → **span-4 = 2h**; SDD `venue-block-span-coalescing`).
- Mock template's own math is half-hour ("duration in hours × 2 = 30-min slots, defaulting to 4 slots (2 hours)", template:1156; `add30min`/`hours[]`) — the spec prose was the outlier, not even the mock.
- Design already correct (design.md:63/65 "multiple of 30" / "contiguous 30-min cells") → no design unfreeze.

### Applied
- spec.md Slot-grid-semantics: cells = 30-min `time_slots` rows, 2h = 4 cells, :30 classes representable; Two-hour scenario GIVEN updated; NEW scenario "Half-hour-start class is selectable" (09:30, never snapped); anchor-slot limitation now per half-hour slot.
- tasks.md 2.3: stale "(FR 4.11, D11)" citation fixed (same family as batch 2's spec fix; `explore-brief.md:67` also carries D11 — frozen, fold in if ever touched) + 30-min axis pointer.
- CodingMAIN FR 2.7/2.8 confirmed granularity-neutral by review — task 4.4 scope unchanged.

### Verdict
**PASS — addendum 2b FROZEN.** Implementer contract for task 2.3 is now unambiguous: 30-min cells, 2h = 4, never snapped to the hour.

## Discoverability follow-up — frozen proposal banner — 2026-10-10 (no reviewer round)

External critique accepted: the "the summary is outdated" notes lived in design (2nd) and tasks (last) but not in `proposal.md` (1st) — the document readers start at. Freeze discipline kept the proposal CONTENT unmodified; the fix is a dated banner at the top of the proposal (the only addition), pointing to the authoritative amendments (design amendment log items 1–2, tasks amendment log entry 6, amended rbac spec, review-log rounds). Banner pointers verified to resolve in the committed artifacts. No contract content changed → no reviewer round (recorded here per house honesty; rounds 1–3 + 2b remain the binding reviews).

## apply Slice B core — 2026-10-10 (tasks 2.1 + 2.5)

Actions layer first (TDD): RED observed (9 errors: missing classes + one harness fix), then implementation, then green.

### Implemented
- `app/Actions/SubmitReplacementRequest` — FR 2.11 ownership → week 1–14 → anchor availability → engine `validateSlot()` pre-check → create pending row → `OCCValidator::validateAndReserve()`; loser paths (validator conflict OR lock-wait abort caught as RuntimeException — PDOException subclass) run the **D5 compensating delete in the stamped order**: pre-null `audit_logs.replacement_request_id` (conflict audit row survives, FR 4.12), then delete the request row, inside its own small transaction that does NOT wrap the validator.
- `app/Actions/CancelPendingRequest` — FR 2.10 + FR 2.11 scoping; D6 release (lockForUpdate + raw version++ UPDATE), request → `cancelled` + `decided_at`, audit `cancelled`.
- `app/Actions/CancelClass` — FR 2.16; reason ∈ the 5 CHECK values; unique (session, week) via QueryException → false; audit `class_cancelled` with details.
- `app/Http/Requests/{StoreReplacementRequest,CancelClassRequest}` — static `rules()` per D8 (verified legal: base FormRequest declares no rules()). `CancelRequest` not created — zero-shape input (invokable id); guards live in the action. `DecideRequest` deferred to Slice C.
- Tests: `ReplacementFlowTest` (10) + `OccConcurrencyTest` (1, design testing-table row) — the race uses a second connection holding the row lock + `SET lock_timeout` so the loser's validator aborts deterministically; asserts D5 compensation + exactly-one-winner + winner audit.

### Test-infra notes (for future slices)
- Sessions repeat weekly → lecturer "busy weeks" = all 14; anchor search must key on free (week, day) pairs then scan the session's own venue (20 candidates).
- Seeded `time_slots.version` starts at **1**, not 0.
- Sequences are non-transactional: request-creating tests reset `replacement_requests`/`class_exceptions`/`audit_logs` in setUp (house pattern per OCCValidatorTest's class_sessions setval) or TimetableWiringTest's literal `"requestId":1` breaks.

### Verification (Slice B core gates)
- `composer run lint:check` ✓ · `phpstan --memory-limit=1G` **0 errors** ✓ · full `php artisan test` **141/141** (was 130; +11 new) ✓
- phpstan findings fixed properly: dead catch → `\RuntimeException` (live via validator's `@throws ModelNotFoundException`; covers lock-timeout PDOException), `decided_at` assignment → fill() (house convention).
- Records-intact: test-only DB (`class_replacement_testing`); demo DB untouched.
- Remaining in Slice B: 2.2 ReplacementHome, 2.3 ReplacementArrangement, 2.4 histories (UI conversion), 2.6 changelogs.

## apply Slice B pages — 2026-10-10 (tasks 2.2–2.4 + 2.6)

### Implemented
- **2.2 `ReplacementHome`** — own classes (conflicted included: B005×Diploma via the promoted protected `venueRestrictionConflict`/`baseEvent` concern helpers), cancelled-week + active-request badges, request counts by status (server-rendered summary values — `ui-summary-bar` gained an optional `value`), search (`wire:model.live`, URL-persisted), FR 2.14/2.15 access links, class-detail modal + direct Arrange buttons both emitting the shared `goToReplacement` URL contract (`from=replacement-home`). Divergences documented: sort/pagination/urgency column are mock-era.
- **2.3 `ReplacementArrangement`** — first interactive Livewire page (design D2 flow): mount resolves the `goToReplacement` params (dead/tampered links → honest "No class selected", never another's session), venue defaults to the original (FR 2.5), whole-render grid re-derivation (FR 2.6/NFR 1.1), server-rendered 20×6 grid of the 30-min `time_slots` axis (addendum 2b) with D4-precedence cell states; green = only cells inside a duration-fitting engine run; free fragments render neutral with honest tooltip (never fake red). Contiguous block selection from the anchor (§3) with near-run-end rejection; confirm modal → D8 rules → `SubmitReplacementRequest` (OCC + D5); FR 4.10 conflict → error toast + re-derivation. New additive engine read `busyRanges()` (single source of truth for D4 rule 1). Empty grid → "No available slots" (FR 4.4/4.6).
- **2.4 `MyRequestHistory` / `ReplacementHistory`** — own requests newest-first + cancel-pending behind a confirm modal (FR 2.10/2.15); student cohort-scoped approved replacements, slot week ≥ current, view-only (FR 1.4). Divergences documented (bulk-cancel, week-nav, sort = mock-era).
- **2.6** — 5 changelogs updated; tasks checkboxes stamped; shared-partials promotions recorded: `ui-summary-bar` `value`, `ui-empty-state` `visible`, concern helpers private→protected.

### Verification (slice gates)
- `composer run lint:check` ✓ · phpstan **0 errors** ✓ (27 initial findings fixed at the cause: Carbon 3 signed `diffInMinutes`, explicit null branches instead of phpstan-invisible nulls — no ignores, no casts) · full `php artisan test` **156/156** (was 141; +15: Home 5, Arrangement 6, Histories 4) ✓
- Sequences reset in new tests; demo DB untouched (testing DB only).
- Remaining Slice B: none. Next: Slice C (Phase 3) — approval queue, decide actions, queued mailables.

## apply RESTORATION — 2026-10-10 (tasks 2.2/2.3 pages rebuilt as faithful bridges)

### Trigger
User rejected the first 2.2/2.3 page conversions: the UI had been rebuilt
instead of reused ("100% use my UI design"). The frozen design lives in
ui-design-templates/ (== /class-replacement-system originals).

### Correction
Both pages are now the frozen mock templates byte-for-byte; the Livewire
components are DATA BRIDGES emitting the mock's exact data shapes from the
real DB (conflictedClasses rows, venueSlots triples [week-keyed],
cohortTimetable conflict events, courses/venues/semester), plus real submit
(submitBlocks → D8 → SubmitReplacementRequest). Documented deviations are
one-liners: week-keyed venueSlots lookup, originalVenue dropdown default,
Lab room types in the capacity filter, DOMContentLoaded wrap + window
exports (the my-timetable pattern — mock-data.js parses after content),
window.weekData export for populateWeekSelect. The invented summary cards /
server-rendered grid / remarks modal from the first pass are gone.

### Verification
- ReplacementHomeTest (5) + ReplacementArrangementTest (6) assert the bridge
  payload + the real submit (request/slot/audit, FR 4.10 conflict, FR 2.11
  refusal); suite **156/156**; pint + phpstan 0.
- Live on the demo DB with the user's exact URLs: home shows 4 real AMCS2093
  PH occurrences with designed badges/urgency/summary; arrangement auto-picks
  the subject + original slot (W7 · Nov 09 · B110), real strip totals
  (28 + 22 = 50), real occupancy, lead-time boundary, contiguous block
  selection, frozen confirm modal. No demo-DB writes.
- Sequence-reset lesson applied to all new test files (TimetableWiringTest's
  literal requestId:1 stays green).

## apply RESTORATION — 2026-10-10 (tasks 2.4 pages rebuilt as faithful bridges)

### Correction (same trigger as the 2.2/2.3 restoration)
MyRequestHistory + ReplacementHistory were also rebuilt UIs; both are now the
frozen mock templates byte-for-byte with data bridges only:
- mrh: MockData.requests ← real own rows (mock shape incl. ISO datetimes,
  occurrence dates from the real calendar, spaced cohorts + counts); the
  frozen single/batch cancel modals now persist through CancelPendingRequest
  (D6 + audit) — the mock's undo-toast dropped per the frozen "cannot be
  undone" copy; rows the backend refuses stay visible.
- rh: MockData.replacementHistory ← real my-cohort rows (approved + pending;
  pending renders "Awaiting PL approval" with no new slot — the frozen
  semantic); cohort chip + empty-state text carry the student's REAL cohort
  (two documented DATA patches); the frozen week/show-past filter is
  client-side (FR 1.4); view-only unchanged.

### Verification
- ReplacementHistoriesTest rewritten for the bridge payloads + real cancel
  (persist/audit/version bump, decided-request refusal, batch returns only
  cancelled ids, cohort scoping, view-only) — 7 tests.
- Gates: suite **159/159**, pint ✓, phpstan **0**.
- Live on the demo DB: mrh frozen toolbar/chips/empty CTA as lecturer 5770;
  rh frozen week-nav/Show Past/summary as student 25DFT0001 with the real
  cohort chip — zero console errors on both.

## apply FIX — 2026-10-11 (arrangement grid honesty: D4 rule 1 wired)

Live demo testing exposed the gap the original bridge documented away ("green
= venue-free; the server re-validates"): the grid offered cells the engine's
FR 4.3–4.7 pre-check refuses → "free cell → Submission Conflict" UX. The
frozen design's D4 rule 1 already mandates rendering engine-excluded cells
occupied; the engine even shipped busyRanges() for that UI purpose.

- Bridge now emits busyByWeek (engine lecturer+cohort vectors per subject,
  exception-excluded per week; '' key = lecturer-only) + blockedVenues
  (Vector 4 reused verbatim: type + headcount eligibility of the resolved
  session — the URL handoff picks the exact session, same resolution as
  submitBlocks).
- View: one documented BRIDGE PATCH branch in the frozen cellRender chain
  (renders the design's own cell-occupied styling). Engine busy/eligibility
  methods made public (read-only reuse).
- Tests: +1 (busy vectors incl. exception-freed week, blocked venues incl.
  type + capacity refusals, '' key semantics) — suite 160/160, pint ✓,
  phpstan 0.
- Live (5425, AMSE2003): B110 fully red for the P class; Wed 12–1 PM red via
  the cohort's 11:00–13:00 class; zero console errors.

## apply FIX — 2026-10-11 (occurrence identity: D1/D7 week semantics + handoff redesign)

Live demo testing (first real submit) exposed three gaps the mock-era data
could never show. User decisions this round:

1. **Home filter (D1)** — occurrences with an active (pending/approved)
   request leave the "needs arrangement" list; they return on reject/cancel.
   Spec-conformant: D1's own partial unique is per (class_session_id,
   week_number) — the home now honours it.
2. **week_number = the occurrence being replaced (D1/D7), NOT the slot's
   week.** submitBlocks accepts a context {sessionId, occurrenceWeek}; the
   handoff pins it. The engine needed no change (validateSlot already
   evaluates the slot in its own week — cross-week slots were always sound).
   Legacy no-pin entries keep the old slot-week behaviour.
3. **Handoff redesign (user decision: "use ID")** —
   `?session={id}&week={n}&from=…` replaces the code/date string schemes on
   replacement-home + my-timetable Replace Now + cancel-flow Arrange Now;
   server resolves + ownership-checks (FR 2.11; fabricated ids → null →
   graceful fallback). Venue-timetable "Book" keeps its venue+date+time
   intent. Central builder `goToReplacementFor` promoted to ui-common.
4. **ID policy (user decision)** — quotable refs where people reference
   things: Class Ref row in the shared Class Details modal; Request No.
   already in the history modal + approval-id in the PL queue (both frozen
   design); internal ids never rendered in tables.

- Tests: +3 (home D1 filter + identity rows; cross-week occurrence
  targeting; D1 duplicate refusal; foreign-session refusal) — suite
  **165/165**, pint ✓, phpstan **0**.
- Live (5770): home hides the covered occurrence; handoff
  `?session=97&week=8` → grid opens W8, AMCS2093 pre-selected, B110
  defaulted; foreign session id → null; Class Ref #97 in the modal; zero
  console errors.
- Open (unchanged): the anchor-slot prototype limitation (spec-documented)
  still reserves only the clicked half-hour cell — the "30-minute
  replacement" the demo surfaced. Awaiting the user's decision on amending
  the frozen Open Question (full block reservation) vs deferring past
  Slice C.

## apply FIX — 2026-10-11 (evening): the pin never reached the server + explore P0

User re-verified the morning's fixes via Playwright and they FAILED live.
Root causes, found by stepping the real journey:

1. **Context key mismatch (critical)** — client `{sessionId, week}` vs server
   `$context['occurrenceWeek']` → every UI submit silently unpinned
   (slot-week semantics). Server accepts both keys now; test mirrors the
   client shape. This is why the user's real requests landed as W9/W5.
2. **Week navigation never rebuilt the grid (explore P0 A1)** — missing
   `window.buildTimetable` export; every other week showed the boot week's
   cells (22 stale PH columns etc.). Exported; pinned jump reordered after
   populateWeekSelect (early rebuild crashed the boot on the empty selector).
3. **Post-submit morph wipe** — submitBlocks re-rendered + morphed the DOM,
   emptying the grid and clobbering the dropdowns ("failed submit → empty
   grid"). Now `#[Renderless]`.
4. **Success aftermath** — all-ok submit navigates to
   /my-request-history-ui (user's designed flow) after a brief toast beat.
5. **Honest messages** — block-fit refusal no longer claims the slot is gone;
   D2 (user decision): holiday-day slots refused server-side.
6. **A5 (user decision)** — toast Undo inert without a callback (hidden +
   handler stripped).
7. **Static-asset staleness** — ui-common.js changed without bumping its
   `?v=` cache-buster, so browsers ran stale JS all day (masked every JS-side
   fix). Both script tags now bust via `filemtime` (layout + login template).
8. Ops note: `artisan serve` served stale PHP after an edit (logger entries
   from removed code kept appearing) — a restart cleared it; after ANY PHP
   edit, restart the server before trusting behavior.

Verification (live, 5770, real UI only): pinned arrival ✓; week-nav rebuild ✓
(0 stale PH columns; req#6's slot rendered `cell-pending`); submit →
**request week = 8 with slot in week 9** ✓; auto-navigation to history ✓;
failed submit leaves the grid intact ✓; D1 duplicate refused with the friendly
message ✓; home hides the covered W8 row while pending and restores it on
cancel ✓; zero console errors. Suite **166/166**, pint ✓, phpstan **0**.

Test traces on class_replacement_fresh (both already cancelled via the app;
slots released to available): requests 5 + 6, audit_logs 6–9. Cleanup:

```sql
DELETE FROM audit_logs WHERE replacement_request_id IN (5, 6);
DELETE FROM replacement_requests WHERE id IN (5, 6);
UPDATE time_slots SET status = 'available', version = 1 WHERE id IN (27878, 27852);
```

## apply FIX — 2026-10-12: occurrence-grain context (explore A2+A3+U5b-a, standalone)

User decision C: standalone (not bundled into the other session's plan).
The course context was module-grain (coursesPayload groupBy module_id, type
from $first, headcount summed across L+T+P) while the design is
occurrence-grain. Server: events carry sessionId; courses carry a per-session
map; busyByWeek/blockedVenues keyed per session (docblock keys int|string).
Client: commitSlotSelection re-derives context/pin/URL per pick (user
decision A: the URL tracks the picked occurrence); ineligible venue falls
back to the occurrence's own venue; picker items show (L)/(T)/(P) badges
(user decision B: a label, not a step). extractSlotsForSubject initially
dropped the new sessionId field — caught live (pick changed nothing), fixed.
PHP-opcache/stale-serve note repeated: after ANY edit, restart artisan serve.

Live proof: T-entry → (T)/28/B107; P-pick → (P)/B011/?session=49&week=14;
L-pick → (L)/50/B110/?session=97&week=8; submit under L-pick filed session 97
week 8 (req#7, cancelled + cleaned up after). Suite 167/167, phpstan 0.
Test traces: req#7 (cancelled via the app; its slot already released) — cleanup:
DELETE FROM audit_logs WHERE replacement_request_id = 7;
DELETE FROM replacement_requests WHERE id = 7;
UPDATE time_slots SET status = 'available', version = 1 WHERE id = 28451;

## apply FIX — 2026-10-12 (b): venue-type matrix + pin-vs-favourite fight

User decisions: P → Labs only; T → Tutorial only (Labs + Halls hidden); B006
stays visible for non-networking/IoT (priority, not a ban); B005 hidden for
D* cohorts (the doc's enforced no-Diploma rule). Implemented UI-side via one
`venueEligible` helper (filter + fallback). Engine eligibleVenues (submit
side) still reads allowed_session_types — the full server-side matrix is
docs/venue-restrictions.md's future enforcement SDD (Wave 3b).

Second root cause found by tracing VenueDropdown.select: the mock's default
slot priority (favourite/recent/first) fired after the handoff pin and fought
it — a stale favourite flipped the context AND venue on every pinned entry.
The URL pin now outranks the defaults. Debug trace removed after use.

Suite 167/167, phpstan 0. Live: T→B107 holds; P→B006 (B005 D-skipped, Types
column "Lab" only); L→B110 (Types column "Lecture Hall" only).
