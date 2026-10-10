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
