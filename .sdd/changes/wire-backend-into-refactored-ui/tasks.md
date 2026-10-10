# Tasks: Wire Backend into Refactored UI

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 2500–4000 |
| Delivery strategy | auto-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Focused test command | Runtime harness | Rollback boundary |
|------|------|----------------------|-----------------|-------------------|
| 1 | Slice A: RBAC + timetables | `phpunit --filter 'RouteGateMatrix'` | serve | revert slice A |
| 2 | Slice B: flow + OCC | `phpunit --filter 'ReplacementFlow'` | `queue:work --once` | revert slice B |
| 3 | Slice C: approval + email | `phpunit --filter 'ApprovalWorkflow'` | `queue:work --once` | revert slice C |

## Phase 0 — Foundation

- [x] 0.1 `config/app.php`: add `'mock_fallback' => env('APP_MOCK_FALLBACK', false)` (D10)
- [x] 0.2 Create minimal `app/Models/Semester.php` (D9, no migration)
- [x] 0.3 `routes/web.php`: D10 branch helper + 9 closures → `->middleware(['auth','role:…'|'pl'])`, auth first

## Phase 1 — Slice A: RBAC + timetables

- [x] 1.1 RED `tests/Feature/RouteGateMatrixTest.php`: 9 routes × guest/student/lecturer/PL (rbac spec)
- [x] 1.2 Apply buckets per rbac spec — `auth` (cohort-timetable), `role:student` (student pages ×2), `role:lecturer` (×5), `pl` (request-approval); 1.1 green *(historical — the cohort-timetable bucket was superseded to lecturer+PL before Slice A shipped; see 4.5 + amendment log entry 5)*
- [x] 1.3 `app/Livewire/MyTimetable|CohortTimetable|StudentMyTimetable` + views: own sessions (FR 2.2), detail modal incl. conflicted (FR 2.3), own-cohort scoping (FR 1.2), statuses (FR 1.3) read-only
- [x] 1.4 §10.0 table-A legend, empty week, Conflict styling in all 3 views
- [x] 1.5 Update 3 page-changelogs (D10 divergence duty)

## Phase 2 — Slice B: replacement flow

- [ ] 2.1 RED `tests/Feature/ReplacementFlowTest.php`: concurrent submit one winner, loser row removed + audit survives (D5); `hrtime()` ≤500 ms (NFR 1.1)
- [ ] 2.2 `app/Livewire/ReplacementHome` + view: own classes, start replacement (FR 2.4)
- [ ] 2.3 `app/Livewire/ReplacementArrangement` + view: venue default (FR 2.5), lazy recalc (FR 2.6), cellState + derived Reserved (FR 4.11; anchor-slot limitation per design Open Questions + D5 — the old "D11" citation is stale), contiguous block (§3) — grid axis = 30-min `time_slots` rows (2 h = 4 cells; see replacement-flow spec amendment 2b), NOT the mock-era hourly reading
- [ ] 2.4 `app/Livewire/MyRequestHistory|ReplacementHistory|VenueTimetable` + views: FR 2.15 / 1.4 (week ≥ current) / 2.14 + venue+week selection (S6) — VenueTimetable read path already shipped early (SDDs `2026-10-09-venue-timetable-db` + `2026-10-10-venue-event-blocks-db` + `venue-toolbar-row-parity`); do NOT re-do it, only add Slice-B write-path hooks if design requires
- [ ] 2.5 `app/Actions/SubmitReplacementRequest` + `StoreReplacementRequest`: FR 2.9, OCC FR 4.8–4.10, D5 order, audit both (FR 4.12); cancel actions FR 2.10/2.16
- [ ] 2.6 2.1 green; update 5 page-changelogs

## Phase 3 — Slice C: approval + email + audit

- [ ] 3.1 RED `tests/Feature/ApprovalWorkflowTest.php`: reject w/o reason fails (FR 3.6); stale-slot re-check governs (FR 3.4); FCFS order (FR 3.2) + RED `tests/Feature/EmailQueueTest.php`: queued dispatch on submit/approve/reject (`Queue::fake`), recipients per the design email table (`Mail::fake`), `queue:work --once` drain <1 min (NFR 1.4)
- [ ] 3.2 `app/Livewire/RequestApproval` + view: queue columns (FR 3.3) + validity (FR 3.4)
- [ ] 3.3 `app/Actions/ApproveRequest|RejectRequest`: FR 3.5–3.6; D7 release via class_exceptions `official_event` + audit detail; audit rows PL identity/timestamp/slot/reason (FR 3.7, FR 4.12)
- [ ] 3.4 `app/Mail/ReplacementSubmitted` (FR 4.15) + `ReplacementDecided` (FR 2.13) + cohort students (FR 1.9), queued (FR 4.16); `queue:work` runbook; 3.1 green; changelog

## Phase 4 — Verification & docs

- [ ] 4.1 Per-slice: `vendor/bin/pint --test` + `phpstan --memory-limit=1G` + `phpunit` green
- [ ] 4.2 Smoke: curl 9-route auth matrix; `queue:work --once` <1 min (NFR 1.4)
- [ ] 4.3 Suites stay green: OCCValidator, MatrixIntersectionEngine, Middleware, DbIntegrityConstraints
- [ ] 4.4 Docs: canonical `CodingMAIN.md` (restructured 2026-10-10 — the old `CodingMAINfedora.md` §11 target is archived); fix the stale `/upcoming-replacements-ui` row in CodingMAIN's mock-fallback page table (~:388, shipped route is `/replacement-history-ui`); divergence notes
- [ ] 4.5 `app/Livewire/CohortTimetable.php`: update docblock (lecturer/PL per amended rbac spec — it still cites "all roles"); explicit keep-or-strip decision on the unreachable `isStudent` student branch (middleware 403s students first, `RouteGateMatrixTest` L112) — keep = defense-in-depth + documented, strip = dead-code removal; no behavior change either way, RBAC matrix stays green

## Amendment log — batch 3 of 3 (2026-10-10 unfreeze)

Per `sync-upstream-fjing-ui` design §10 registered debt + the 2026-10-10 re-verification. Paper-only alignment; the apply phase starts at Phase 2 (Slice B) — Phase 0/1 are done.

1. **2.4 rename** `UpcomingReplacements` → `ReplacementHistory` + VenueTimetable early-ship stamp (read path live since the venue DB SDDs; Slice-B work is hooks only).
2. **3.1 gains the `EmailQueueTest` RED** — the frozen design Testing-strategy table already included it; tasks lagged.
3. **4.4 doc target corrected** — canonical `CodingMAIN.md` (restructured 2026-10-10); gains the stale `/upcoming-replacements-ui` row fix (CodingMAIN mock-fallback table ~:388) found by the batch-2 review.
4. **New 4.5** (batch-2 review carry): CohortTimetable docblock cites the superseded "all roles" bucket and holds an unreachable `isStudent` branch (middleware 403s students first) — docblock update + explicit keep-or-strip decision, no behavior change.
5. **Clarification (frozen design L132, recorded here per batch-2 review):** TimetableWiringTest's "student sees only own cohort" is satisfiable only via `StudentMyTimetable` — cohort-timetable-ui is lecturer/PL-gated.
6. **Supersession note (batch-3 review):** frozen `proposal.md` L12 (cohort as all-roles) and L17 (`UpcomingReplacements`) are likewise superseded by design amendments 1–2 + the rbac spec delta — recorded here to close the chain; the proposal is not unfrozen.
