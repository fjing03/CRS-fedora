# Proposal: Wire Backend into Refactored UI

## Why

Upstream UI refactor (merge fd8c403, ~29k lines) delivers 9 page templates rendering purely from `window.MockData`. The backend is complete and tested — `MatrixIntersectionEngine`, `OCCValidator` (+`OCCResult`), full timetable schema + seeders — but nothing is connected. All 9 UI routes in `routes/web.php` are public closures, violating FR 4.13/4.14. This change converts pages to Livewire components on real DB data and gates every route.

## Scope

### In scope

**Slice A — RBAC gates + real timetables**
1. Gate all 9 UI routes with `auth` + `role:`/`pl` middleware, per CodingMAIN §6 matrix — all-roles (`auth` only): cohort-timetable (Student ✅/Lecturer ✅/PL ✅); student-only: student-my-timetable, upcoming-replacements; lecturer+PL (`role:lecturer` — PL already passes, its role column is `lecturer`): my-timetable, replacement-home, replacement-arrangement, my-request-history, venue-timetable; PL-only (`pl`): request-approval.
2. Livewire components in `app/Livewire/`: `MyTimetable`, `CohortTimetable`, `StudentMyTimetable` on real Eloquent data (`ClassSession`, `SessionCohort`, `ReplacementRequest`).
3. `MockData` kept as config-switchable read-only demo fallback (user decision 2026-08-31).

**Slice B — replacement flow**
4. Components `ReplacementHome`, `ReplacementArrangement` (venue change recalculates via `MatrixIntersectionEngine::findAvailableSlots()` ≤500 ms), `MyRequestHistory`, `UpcomingReplacements`, `VenueTimetable`.
5. Submit: Form Request + `SubmitReplacementRequest` action → `OCCValidator::validateAndReserve()`; conflict alert to loser (FR 4.10).
6. `CancelPendingRequest` (FR 2.10) and `CancelClass` with reason (FR 2.16) actions.

**Slice C — approvals, email, audit**
7. `RequestApproval` FCFS queue (earliest `submitted_at` first) showing proposer, subject, affected cohort(s), proposed time + venue (FR 3.3) and pre-computed slot validity (FR 3.4); `ApproveRequest` / `RejectRequest` (mandatory reason) actions.
8. Mailables `app/Mail/`: `ReplacementSubmitted` (PL, FR 4.15), `ReplacementDecided` (proposer FR 2.13; approval also emails affected-cohort students FR 1.9), queued (`QUEUE_CONNECTION=database`, `php artisan queue:work`).
9. Audit rows via existing `AuditLog` model on every transition (FR 4.12).

### Out of scope
- New UI page designs; real SMTP (log mailer per locked decision); Playwright e2e authoring; passkey/2FA changes; deleting `mock-data.js`; migrations.

**Schema note:** `time_slots.status` CHECK allows only `available`/`pending`/`occupied`. "Reserved by Others" (FR 4.11) is derivable (slot `pending` + active request's proposer ≠ viewer, per `uq_replacement_requests_active_block`) — no migration needed; confirm in design.

## Capabilities

New (one spec each under `specs/<name>/spec.md`): `rbac-route-gating`, `timetable-data-wiring`, `replacement-flow-wiring`, `approval-workflow-wiring`. Modified: none (no prior specs).

## FR/NFR traceability

| Ref | Covered by |
|-----|-----------|
| FR 1.2–1.4 | Student pages on real data (A/B) |
| FR 2.2–2.10, 2.14–2.16 | Lecturer flow end-to-end (A/B) |
| FR 3.1–3.7 | PL FCFS queue + decisions (C) |
| FR 4.3–4.12 | Engine + OCC calls, audit rows; services unchanged |
| FR 4.13–4.14 | Middleware gates on all 9 routes; ownership scoping enforces 2.11 (cannot view/edit others') + 2.12 (cannot approve/reject) |
| FR 4.15–4.16, 2.13, 1.9 | Queued mailables, log mailer |
| NFR 1.1, 1.4, 2.1 | ≤500 ms recalc; job <1 min; middleware on all dashboard routes |

## Affected Areas

| Area | Impact |
|------|--------|
| `routes/web.php` | Modified — 9 closures → Livewire + middleware |
| `app/Livewire/` | New — 9 page components |
| `resources/views/ui-design-templates/`, `resources/views/livewire/` | Modified — render conversion |
| `app/Http/Requests/`, `app/Actions/` | New — submit/approve/reject/cancel |
| `app/Mail/`, `app/Jobs/` | New — 2 mailables + dispatch |
| `app/Services/*`, `database/migrations/` | Unchanged |
| `public/js/mock-data.js` | Kept (fallback switch) |

## Risks

| Risk | L | Mitigation |
|------|---|------------|
| Livewire 4 learning curve | Med | Start with read-only Slice A; follow starter-kit patterns |
| Slot-grid vanilla JS → Livewire regression | High | Port cell classes 1:1; keep §10.0 UI rules; per-page visual pass |
| 500 ms recalc budget (NFR 1.1) | Med | Engine indexed + tested; `wire:model.lazy` + debounce; assert timing in test |
| MockData fallback divergence | Med | Config switch, read-only; record divergence in design.md |

## Rollback

Chained commits per slice; revert slice-by-slice on `fedora-backend`. Branch `backup/fedora-backend-pre-merge` preserves the pre-refactor backend. Code-only rollback — no DB operations; mock templates stay on disk until each converted page verifies.

## Success criteria

- [ ] All 9 routes: guest → login redirect, wrong role → 403, correct role → real DB data
- [ ] Venue change recalculates grid ≤500 ms (measured in Feature test)
- [ ] OCC concurrent-submission test: exactly one winner, one conflict alert, both outcomes in `audit_logs`
- [ ] FCFS order verified; approve/reject updates slot + request + audit + queued email
- [ ] `queue:work` processes notification job <1 min (NFR 1.4); log-mailer output visible
- [ ] `composer run lint:check` + `types:check` + `phpunit` green after each slice
