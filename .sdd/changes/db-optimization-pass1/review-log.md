# Review Log — db-optimization-pass1

## Proposal Round 1 — 2026-08-24

### Findings

🔴 None — no implementation blockers.

🟡 P0.1 rationale factually wrong + criterion untestable as written: OCCValidator.php:27 is an in-memory comparison after a PK fetch (line 25); zero `WHERE replacement_time_slot_id` queries exist (ApiReadController.php:143/:688 are JOINs to time_slots.id). Rejustify via FK-enforcement scans under D3 restrict + future reverse lookups, and name which query Success Criterion #1 EXPLAINs.

🟡 Success criteria omit project-mandated Pint lint:check (AGENTS.md standing rule; phpstan absence already justified). Add checkbox.

🟡 Affected Areas misses tests/Unit/MatrixIntersectionEngineTest.php + tests/Feature/MatrixIntersectionEngineTest.php — headcount-source flip (P2.6) will likely break their fixtures.

🟡 down() re-adding original action CHECK fails if class_cancelled rows exist at rollback time (safe today — grep clean — but state the assumption).

🟡 Seeder-edit guardrail: phase1 froze users/students/lecturers data incl. "no seeder edits"; state explicitly that the DatabaseSeeder edit must leave those rows identical (writes only cohorts.student_count).

🟡 Intent nits: audit_logs FKs are NO ACTION (not cascade) so audit history is delete-blocked, not silently erased; D3 leaves proposer_id/semester_id cascading — state the exclusion as deliberate.

✅ All 7 approved changes mapped (P0.1/P0.2/P0.3-D1/P1.4-D3/P2.5/P2.6/P2.7), nothing smuggled; frozen tables respected; STUDENT_COUNTS (DatabaseSeeder.php:22-37), requiredHeadcount() (MatrixIntersectionEngine.php:292-297), action CHECK (audit_logs line 31), partial-unique precedent (time_slots lines 37-41) all verified verbatim; P0.2 backed by real queries (ApiReadController.php:138/:378); FR 2.15/2.16/4.12 confirmed vs CodingMAIN.md §7; approach PG-sound; ~400/450 words.

**Verdict**: PASS

## Design Round 1 — 2026-08-24

### Findings

🔴 None — PASS.

🟡 CodingMAIN.md §5 schema note missing from design deliverables → added design §7 (docs update section); must carry into tasks.md.

🟡 Cohort.php fillable addition is a new affected file vs frozen proposal inventory → acknowledged; tasks.md must list it explicitly so apply-review doesn't flag as smuggled scope (design §4 already specifies it).

🟡 up() zero-student-cohort loud-fail precondition undocumented → added to design §1(f)+(g) decisions.

💡 DFT2(S1)G1 headcount prose said 30, actual STUDENT_COUNTS value 28 → fixed in §5 fixture table.

💡 sprintf key-format duplication of cohortCode() noted → deferred (extract only on 3rd caller per house promote-on-3rd rule).

✅ Decision-drift: all 7 items map 1:1; DDL sound (audit_logs_action_check name verified live; dropForeign→foreign() symmetric; partial unique verbatim vs proposal; down() inverse order w/ CHECK-before-dropColumn); dual-path backfill respects seeder-authoritative guardrail; NULL-aware fallback preserves legacy behavior; test plan covers all 8 SCs + proposer-cascade exclusion test; SPECS-BATCH-SKIP confirmed OK by reviewer (single capability/migration).

**Verdict**: PASS — Batch 2 frozen.

## Tasks Round 1 — 2026-08-24

Note: sdd-reviewer subagent unavailable (provider network_error ×2). Orchestrator performed the review inline as fallback, same checklist.

### Findings

🔴 None — PASS.

🟡 Forecast block contained extra "Chain strategy: pending" line — invalid per phase-common §E (only the 4 delivery_strategy values allowed; nothing else recorded) → removed.

🟡 T1.1 compressed `dropForeign(['a'], ['b'])` two-arg notation is not valid Blueprint API → rewritten as the exact two-call form from design §1(a).

✅ Traceability complete: SC#1→T6.2, SC#2→T5.1+T6.3, SC#3→T5.2+T6.3, SC#4→T5.4+T6.3, SC#5→T3/T4, SC#6→T6.1, SC#7→T6.4, SC#8→T6.5; design §1–§7 each covered by exactly one task; carried obligations honored (header sanctioned-file note + T2.1 Cohort.php, T7.1 CodingMAIN §5); ordering safe (migration T1 precedes seeder-dependent T2); commands real (`composer run lint:check`, `php artisan test`, `--pretend`, `migrate:fresh --seed`); forecast guard lines verbatim + ~360-line estimate plausible vs scope; zero decision-level additions beyond frozen design.

**Verdict**: PASS — Batch 3 frozen. APPLY-READY: YES
