# Design: Wire Backend into Refactored UI

## Technical Approach

Convert the 9 mock pages to **Livewire 4 full-page components** on real Eloquent data in 3 chained slices (A: RBAC + timetables → B: replacement flow → C: approvals + email + audit), per the frozen proposal. Services (`MatrixIntersectionEngine`, `OCCValidator`, `OCCResult`) and migrations are **unchanged**; the design only wires them. `MockData` stays as a config-switchable read-only fallback (deviation stamped 2026-08-31). `MAIL_MAILER=log` + `QUEUE_CONNECTION=database` already set in `.env`; `jobs` table exists — no migration.

## Architecture Decisions

| # | Decision | Choice | Alternatives rejected | Rationale |
|---|----------|--------|----------------------|-----------|
| 1 | Layout integration | `render()->extends('layouts.ui-template', ['activeNav'=>…,'pageKey'=>…])->section('content')` (verified in vendor `SupportPageComponents`: section-based layouts supported) | `#[Layout]` with `$slot` wrapper (would fork the layout, breaking DRY) | Zero layout changes; nav/toast/mock-data script tags all inherited |
| 2 | Page CSS/JS in Livewire views | Per-page CSS moves into a `<style>` block inside the component view (the layout's `@yield('page-styles')` is NOT filled by `->extends()`); page JS is replaced by `wire:` directives | Moving page CSS into theme.css (bloats shared file) | Self-contained components; visual rules preserved 1:1 |
| 3 | Middleware order | `['auth', 'role:…'/'pl']` — auth first | `role:` alone | Binding review-log note: `CheckRole` alone redirects guests to `/`; `auth` fires first so guests get the FR 4.14 redirect to `login.student` (`bootstrap/app.php:30` `redirectGuestsTo`) |
| 4 | Slot-state derivation | Private `cellState()` in `ReplacementArrangement` + ONE grouped query for active pending requests; no accessor on `TimeSlot` | Scope/accessor on model; new service class | Proposal freezes `app/Services/*` as unchanged; component-local derivation keeps it that way; single query avoids N+1 |
| 5 | Submit transaction boundary | NO outer `DB::transaction` around request-create + `OCCValidator::validateAndReserve()`; on OCC conflict, compensating delete of the just-created request row — **order matters:** first `UPDATE audit_logs SET replacement_request_id = NULL WHERE replacement_request_id = <loser request id>` (conflict audit row SURVIVES — FR 4.12), THEN `DELETE` the request row; deleting/nulling the audit row itself is FORBIDDEN; no `nullOnDelete()` migration (out of scope) | Wrapping both in one transaction (would roll back the validator's committed conflict audit row, breaking FR 4.12) | `OCCValidator` opens its own transaction and audits BOTH outcomes; nesting would turn it into a savepoint and discard the loser's audit row; `audit_logs.replacement_request_id` FK is NO ACTION, so delete without the pre-null violates the constraint |
| 6 | Approve / reject / cancel transitions | Mirror the OCC pattern: `lockForUpdate` → raw `UPDATE time_slots SET status=?, version=version+1 WHERE id=? AND version=?` (affected=0 ⇒ retry/conflict) | Plain Eloquent save (loses OCC protection) | FR 4.8 applies to every transition of the state machine, not just reservation |
| 7 | Original-block release on approval | Insert `class_exceptions` (class_session_id, week_number, reason) for the original block; log the true cause (`replaced by request #N`) in `audit_logs.details` | Widen the reason CHECK (needs migration — out of scope); change engine (frozen) | `reason` CHECK allows only the 5 FR 2.16 values; `official_event` is the least-wrong label; `class_exceptions` is the only mechanism the (unchanged) engine reads to free a block; truth preserved in audit trail |
| 8 | Validation rules source | Form Request classes own `rules()`; Livewire components call `Validator::make($payload, StoreX::rules())` | Livewire inline rules (duplicates); injecting HTTP FormRequests into Livewire (awkward) | Single source of rules (DRY), works for both HTTP and Livewire callers |
| 9 | Semester lookup | New minimal `app/Models/Semester.php` + `Semester::active()` (start_date ≤ today, latest first, fallback id 1) | `DB::table('semesters')` scattered in components | No model exists today; a model is not a migration; keeps components Eloquent-idiomatic |
| 10 | MockData fallback switch | `config/app.php` → `'mock_fallback' => env('APP_MOCK_FALLBACK', false)`. Branch lives ONLY in `routes/web.php`: when true, the old closure → legacy `ui-design-templates` view (file untouched); when false, the Livewire component. Transitional rule during Slice A→B: any route whose component does not exist yet serves the legacy view regardless of the flag (component-class existence check), so wiring one page never breaks the others. Same middleware applied either way | Branch inside components rendering legacy templates (nesting a section-based template inside a Livewire layout breaks) | Single-point branch; legacy templates byte-identical on disk; RBAC still enforced in fallback mode |

## Route table transformation (per slice)

Old: 9 public closures at `routes/web.php:15–49`. New (URIs unchanged, names added):

| Route (URI) | Old | New component (`app/Livewire/`) | Middleware (ORDER) | Slice |
|---|---|---|---|---|
| `/my-timetable-ui` | closure | `MyTimetable` | `['auth','role:lecturer']` | A |
| `/cohort-timetable-ui` | closure | `CohortTimetable` | `['auth']` | A |
| `/student-my-timetable-ui` | closure | `StudentMyTimetable` | `['auth','role:student']` | A |
| `/upcoming-replacements-ui` | closure | `UpcomingReplacements` | `['auth','role:student']` | A* |
| `/replacement-home-ui` | closure | `ReplacementHome` | `['auth','role:lecturer']` | B |
| `/replacement-arrangement` | closure | `ReplacementArrangement` | `['auth','role:lecturer']` | B |
| `/my-request-history-ui` | closure | `MyRequestHistory` | `['auth','role:lecturer']` | B |
| `/venue-timetable-ui` | closure | `VenueTimetable` | `['auth','role:lecturer']` | B |
| `/request-approval-ui` | closure | `RequestApproval` | `['auth','pl']` | C |

*Gate installed in Slice A (all gates ship in A); the component itself lands in B (mock template still served via the fallback-style closure until then). Slice A gates ALL 9 routes immediately — FR 4.13/4.14 closed from A.

## Component architecture

Views: `resources/views/livewire/<kebab>.blade.php` (new dir; converted from `resources/views/ui-design-templates/`, which stays untouched for the fallback).

| FQCN | Slice | Key queries | User actions (methods) | Calls |
|---|---|---|---|---|
| `App\Livewire\MyTimetable` | A | `ClassSession` where lecturer=me (+module, venue, cohorts), minus `class_exceptions` for week; pairwise overlap = conflicted | click class → detail modal; "Start replacement" → link | — |
| `App\Livewire\CohortTimetable` | A | faculty→programme→cohort tree; `ClassSession` join `session_cohorts` | select cohort/week | — |
| `App\Livewire\StudentMyTimetable` | A | my cohort via `Student`; sessions + cancelled flags + pending/approved request statuses | view only | — |
| `App\Livewire\ReplacementHome` | B | my requests count by status; my conflicted sessions | navigate cards | — |
| `App\Livewire\ReplacementArrangement` | B | session detail; `Venue` list; engine grid (below) | `updatedVenueId`, `selectSlot`, `openSubmit`, `submit()` | `SubmitReplacementRequest` |
| `App\Livewire\MyRequestHistory` | B | `ReplacementRequest` where proposer=me, `orderByDesc('submitted_at')` (idx `proposer_submitted`) | `cancel($id)` → confirm modal | `CancelPendingRequest` |
| `App\Livewire\UpcomingReplacements` | B | approved requests whose original session's cohorts ∋ my cohort, week ≥ current | view only | — |
| `App\Livewire\VenueTimetable` | B | `ClassSession` where venue; `TimeSlot` status + active requests | select venue/week | — |
| `App\Livewire\RequestApproval` | C | `ReplacementRequest` pending `orderBy('submitted_at')` (idx `status,submitted_at` exists); per row `MatrixIntersectionEngine::validateSlot()` (FR 3.4 chip) | `approve($id)` (confirm); `reject($id)` → mandatory-reason modal | `ApproveRequest`, `RejectRequest` |

### Slice B arrangement data flow (NFR 1.1)

```
venue select ──wire:model.live.debounce.250ms──▶ updatedVenueId($id)
   │  (250 ms debounce + engine <500 ms budget; grid = whole `render()`)
   ▼
findAvailableSlots(me, sessionCohortIds, semesterId, week, sessionType, duration, venueId)
   │  duration = original session (end−start), multiple of 30
   ▼
runs[] (each = contiguous 30-min cells ≥ duration) ──▶ cellState() per grid cell
   ▼
click green cell → selectSlot(runIndex) → whole contiguous block → 'selected' (blue)
   ▼
submit() → SubmitReplacementRequest → OCCValidator::validateAndReserve(anchor slot id)
   ├─ success → toast + request row + PL email queued
   └─ conflict (FR 4.10) → error toast (`--color-error-container`) + grid re-derivation
        showing winner's pending as 'reserved' (grey) from this viewer
```

**Cell-state derivation (`cellState`)** — precedence order:

| # | Derived state | Condition | Legend label | Token (§10.0 table B) |
|---|---|---|---|---|
| 1 | `occupied` | slot.status = `occupied` **or** slot excluded by engine busy vectors | Occupied / Class on Public Holiday | `--color-error` |
| 2 | `reserved` | slot.status = `pending` AND active pending request (partial unique `uq_replacement_requests_active_block` set) proposer ≠ viewer | Reserved by Others | `--color-surface-variant` |
| 3 | `pending` | slot.status = `pending` AND proposer = viewer | Pending (You) | `--color-tertiary` |
| 4 | `selected` | slot.id ∈ selected run's `time_slot_ids` | Your Current Selection | `--color-primary` |
| 5 | `available` | slot in engine result window | Available | `--color-secondary` |

Query: `ReplacementRequest::whereIn('replacement_time_slot_id', $slotIds)->where('status','pending')->pluck('proposer_id','replacement_time_slot_id')` — one grouped lookup feeding rules 2–3.

## State machine (UI action × slot/request state → FR 4.8–4.11)

| UI action (role) | Slot before | Slot after | Request after | Audit `action` | Email |
|---|---|---|---|---|---|
| Submit (lecturer/PL) — winner | available | **pending** (version+1, raw UPDATE) | created `pending` | `submitted` (occ=success) | `ReplacementSubmitted` → PLs |
| Submit — OCC loser | available (untouched) | unchanged | created then deleted (compensation: null `audit_logs.replacement_request_id` FIRST → conflict audit row survives, THEN delete request; see D5) | `submitted` (occ=conflict) | none; FR 4.10 alert |
| Cancel (owner, FR 2.10) | pending | **available** (version+1) | `cancelled`, decided_at=now | `cancelled` | none |
| Approve (PL, FR 3.5) | pending | **occupied** (version+1) + `class_exceptions` row releases original block (D7) | `approved`, approver_id, decided_at | `approved` | `ReplacementDecided` → proposer + affected-cohort students (FR 1.9/2.13) |
| Reject (PL, FR 3.6, mandatory reason) | pending | **available** (version+1) | `rejected`, rejection_reason, approver_id, decided_at | `rejected` | `ReplacementDecided` → proposer |
| Cancel class (owner, FR 2.16) | n/a (master timetable) | slot unaffected | n/a | `class_cancelled` | none |

`occupied` is terminal in the UI (no actions rendered). Derived `reserved` (rule 2 above) is view-only — never a DB status. `request.status='completed'` stays unused (post-occurrence lifecycle is out of scope).

## Actions / Form Requests

| Class | Validation (rules in Form Request) | Authorization | Transaction |
|---|---|---|---|
| `SubmitReplacementRequest` + `StoreReplacementRequest` | session owned; week 1–14; anchor slot exists + `status=available` + `validateSlot()` true (engine pre-check); remarks optional | `session.lecturer_id == auth::id()` (FR 2.11 scoping); students blocked by route + policy | NO outer txn; compensating delete on conflict (D5: pre-null audit FK, then delete request; audit row must survive) |
| `CancelPendingRequest` | request exists, `status=pending` | `proposer_id == auth::id()` (FR 2.10) | single txn: request + slot version++ |
| `CancelClass` | reason ∈ the 5 CHECK values (select); week 1–14; unique (session, week) not violated | `session.lecturer_id == auth::id()` (FR 2.16) | txn: `class_exceptions` insert + audit `class_cancelled` |
| `ApproveRequest` | request `status=pending`; slot re-lock + version check | PL only — route `pl` + re-check `lecturer.is_pl` in action (FR 2.12: lecturers can never reach it) | txn: request→approved + slot→occupied (version++) + exception + audit + job dispatch |
| `RejectRequest` | `rejection_reason` **required, min 3 chars** (FR 3.6) | same as Approve | txn: request→rejected + slot→available (version++) + audit + job dispatch |

## Email design (FR 4.15/4.16/2.13/1.9)

| Mailable | Recipients | Subject | Queue |
|---|---|---|---|
| `App\Mail\ReplacementSubmitted` | all PL users (`Lecturer` where `is_pl`) | `[CRS] Replacement request #N submitted by {name}` | `ShouldQueue`, connection `database`, queue `emails` |
| `App\Mail\ReplacementDecided` | proposer always; **if approved**, also every user of affected cohorts (`Student` join `users` where `cohort_id` ∈ session cohorts) | `[CRS] Replacement request #N {approved\|rejected}` | same |

Dispatch points: submit (on success) / approve / reject actions. Runbook: `.env` already `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`; start `php artisan queue:work --queue=emails`; NFR 1.4 (<1 min) verified in test via `--once` drain. Tests use `Mail::fake()` + `Queue::fake()` (`phpunit.xml` already `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`).

## RBAC / 403

`users.role` CHECK = `('student','lecturer')` → PL passes `role:lecturer`; PL-ness = `lecturers.is_pl` via `pl`. Middleware ORDER per D3. Wrong role → existing themed `resources/views/errors/403.blade.php` (no change). Ownership scoping (FR 2.11/2.12) enforced in actions (table above) — a lecturer with a crafted request never sees another's rows because every query filters by `proposer_id`/`lecturer_id`.

## MockData fallback switch

Config D10. When `APP_MOCK_FALLBACK=true`, each of the 9 routes serves the untouched legacy template through the same middleware. Divergence duty: every page conversion logs "Mock → real" mappings (and dropped mock-only fields, e.g. `MockData.urgencyReferenceDate`) in the matching `page-changelogs/<page>-changelog.md`.

## Testing strategy per slice

| Test | Slice | Approach | Existing tests |
|---|---|---|---|
| `Feature\RbacRouteGatingTest` | A | Matrix over 9 routes × guest/student/lecturer/PL: guest → `login.student` redirect (FR 4.14), wrong role → 403, right role → 200 | `MiddlewareTest` **stays** (alias-level) |
| `Feature\TimetableWiringTest` | A | Seeded timetable renders for each role; student sees only own cohort | `MatrixIntersectionEngineTest` **stays** |
| `Feature\ReplacementFlowTest` | B | Submit success path; loser path (conflict + audit row + compensating delete); cancel; ≤500 ms assertion: `hrtime()` around `updatedVenueId` on the seeded dataset; duration-aware contiguous selection (§3 edge cases: 2h block, empty result FR 4.4/4.6) | `MatrixIntersectionEngineTest` **stays** |
| `Feature\OccConcurrencyTest` | A/B | Exactly-one-winner via two connections: conn A `lockForUpdate` + hold, validator on conn B blocks then reports `slot_not_available`/`concurrent_reserve_conflict`; both outcomes in `audit_logs` | `OCCValidatorTest` **stays** (sequential) |
| `Feature\ApprovalWorkflowTest` + `Feature\EmailQueueTest` | C | FCFS order assertion; approve/reject transitions (slot+request+audit+exception); mandatory reject reason (422 on empty); `Queue::fake` push assertions; `queue:work --once` drain with log/array mailer < 1 min | `DbIntegrityConstraintsTest` **stays** |

`composer run lint:check` + `types:check` + `phpunit` green after each slice (proposal success criteria).

## Promoted to shared

1. **`resources/views/partials/ui-slot-cell.blade.php`** — slot-grid cell markup + `cell-*` classes used by `ReplacementArrangement` AND `VenueTimetable` (2 live consumers on conversion + reuse rule for future pages; promoted at first duplication because the cell markup is the highest-regression-risk block per proposal Risk 2).
2. **`partials/ui-confirm-modal.blade.php`** — styled "Are you sure?" shell consumed by cancel-request, cancel-class, submit, approve, reject (rule §10.0.8; replaces per-page `confirm()` copy across 5+ call sites).
3. **`ui-common.js` → `Livewire.on('notify', …)` toast bridge** — one helper wiring Livewire `->dispatch('notify')` events to the existing `ToastManager` (`toast.show()`); all 9 components share it.

## Toast/undo bar

| Action | Toast | Undo semantics |
|---|---|---|
| Submit request | "Request submitted — awaiting PL approval" | **Undo → `CancelPendingRequest`** (FR 2.10 makes this safe) |
| Cancel pending request | "Request cancelled" | none (already terminal) |
| Cancel class | "Class cancelled for week N" | none (audit-recorded) |
| Approve | "Request #N approved" | none — irreversible; undo would contradict the audit trail + student emails |
| Reject | "Request #N rejected" | none (same) |
| OCC conflict (FR 4.10) | error toast, no undo | "Try again" via grid refresh |

## Mobile view

No new mobile rules — the upstream templates already implement the §10.0.9 mandate (see `page-changelogs/mobile-responsive-changelog.md`: drawer, card layout, bottom-sheet modals, toast position, swipe weeks). Conversion plan: (a) copy each template's `@media (max-width:768px)` CSS block **unchanged** into the component's `<style>`; (b) keep all `data-*`/class hooks used by `ui-common.js` mobile init (`initMobileNav` runs from the untouched layout); (c) per-page visual pass at 375 px after each slice with a checklist in the page changelog. Risk acknowledged in proposal Risk 2; no planned divergences.

## File Changes

| File | Action |
|---|---|
| `routes/web.php` | Modify — 9 closures → component routes + middleware (D10 branch) |
| `config/app.php` | Modify — add `mock_fallback` |
| `app/Livewire/*.php` (9), `app/Models/Semester.php` | Create |
| `resources/views/livewire/*.blade.php` (9) | Create — converted from `ui-design-templates/` |
| `app/Http/Requests/{StoreReplacementRequest,CancelRequest,CancelClassRequest,DecideRequest}.php` | Create |
| `app/Actions/{SubmitReplacementRequest,CancelPendingRequest,CancelClass,ApproveRequest,RejectRequest}.php` | Create |
| `app/Mail/{ReplacementSubmitted,ReplacementDecided}.php` | Create |
| `resources/views/partials/{ui-slot-cell,ui-confirm-modal}.blade.php`, `public/js/ui-common.js` | Create / Modify (promotions) |
| `resources/views/ui-design-templates/`, `public/js/mock-data.js`, `app/Services/*`, `database/migrations/` | **Unchanged** |

## Threat Matrix

| Boundary | Applicability |
|---|---|
| Documentation-like paths | N/A — no docs/executable classification |
| Git repository selection | N/A — no VCS automation in this change |
| Commit state / push state | N/A — commits stay manual per slice (repo convention) |
| PR commands | N/A |
| **Web route table** (the design's own routing boundary) | Applicable — every route gains auth gates; RED tests = `RbacRouteGatingTest` matrix (guest/student/lecturer/PL × 9 routes) before Slice A merge |

## Open Questions

- [ ] For specs/tasks: confirm `class_exceptions.reason='official_event'` as the original-block release label (D7) vs. a tiny future CHECK-widening migration adding `'replaced'`.
- [ ] Anchor-slot-only reservation: OCC reserves the clicked (anchor) slot; the remaining cells of a multi-cell block stay `available` for others. Consistent with the frozen single-slot validator signature, but flag in `replacement-flow-wiring` spec as a documented prototype limitation.
