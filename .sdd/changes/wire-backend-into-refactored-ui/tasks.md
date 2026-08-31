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

- [ ] 0.1 `config/app.php`: add `'mock_fallback' => env('APP_MOCK_FALLBACK', false)` (D10)
- [ ] 0.2 Create minimal `app/Models/Semester.php` (D9, no migration)
- [ ] 0.3 `routes/web.php`: D10 branch helper + 9 closures → `->middleware(['auth','role:…'|'pl'])`, auth first

## Phase 1 — Slice A: RBAC + timetables

- [ ] 1.1 RED `tests/Feature/RouteGateMatrixTest.php`: 9 routes × guest/student/lecturer/PL (rbac spec)
- [ ] 1.2 Apply buckets per rbac spec — `auth` (cohort-timetable), `role:student` (student pages ×2), `role:lecturer` (×5), `pl` (request-approval); 1.1 green
- [ ] 1.3 `app/Livewire/MyTimetable|CohortTimetable|StudentMyTimetable` + views: own sessions (FR 2.2), detail modal incl. conflicted (FR 2.3), own-cohort scoping (FR 1.2), statuses (FR 1.3) read-only
- [ ] 1.4 §10.0 table-A legend, empty week, Conflict styling in all 3 views
- [ ] 1.5 Update 3 page-changelogs (D10 divergence duty)

## Phase 2 — Slice B: replacement flow

- [ ] 2.1 RED `tests/Feature/ReplacementFlowTest.php`: concurrent submit one winner, loser row removed + audit survives (D5); `hrtime()` ≤500 ms (NFR 1.1)
- [ ] 2.2 `app/Livewire/ReplacementHome` + view: own classes, start replacement (FR 2.4)
- [ ] 2.3 `app/Livewire/ReplacementArrangement` + view: venue default (FR 2.5), lazy recalc (FR 2.6), cellState + derived Reserved (FR 4.11, D11), contiguous block (§3)
- [ ] 2.4 `app/Livewire/MyRequestHistory|UpcomingReplacements|VenueTimetable` + views: FR 2.15 / 1.4 (week ≥ current) / 2.14 + venue+week selection (S6)
- [ ] 2.5 `app/Actions/SubmitReplacementRequest` + `StoreReplacementRequest`: FR 2.9, OCC FR 4.8–4.10, D5 order, audit both (FR 4.12); cancel actions FR 2.10/2.16
- [ ] 2.6 2.1 green; update 5 page-changelogs

## Phase 3 — Slice C: approval + email + audit

- [ ] 3.1 RED `tests/Feature/ApprovalWorkflowTest.php`: reject w/o reason fails (FR 3.6); stale-slot re-check governs (FR 3.4); FCFS order (FR 3.2)
- [ ] 3.2 `app/Livewire/RequestApproval` + view: queue columns (FR 3.3) + validity (FR 3.4)
- [ ] 3.3 `app/Actions/ApproveRequest|RejectRequest`: FR 3.5–3.6; D7 release via class_exceptions `official_event` + audit detail; audit rows PL identity/timestamp/slot/reason (FR 3.7, FR 4.12)
- [ ] 3.4 `app/Mail/ReplacementSubmitted` (FR 4.15) + `ReplacementDecided` (FR 2.13) + cohort students (FR 1.9), queued (FR 4.16); `queue:work` runbook; 3.1 green; changelog

## Phase 4 — Verification & docs

- [ ] 4.1 Per-slice: `vendor/bin/pint --test` + `phpstan --memory-limit=1G` + `phpunit` green
- [ ] 4.2 Smoke: curl 9-route auth matrix; `queue:work --once` <1 min (NFR 1.4)
- [ ] 4.3 Suites stay green: OCCValidator, MatrixIntersectionEngine, Middleware, DbIntegrityConstraints
- [ ] 4.4 Docs: `CodingMAINfedora.md` §11; divergence notes
