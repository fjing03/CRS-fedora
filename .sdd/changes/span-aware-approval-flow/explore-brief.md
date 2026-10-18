# Explore Brief: span-aware-approval-flow

**Status:** explore complete 2026-10-17 (read-only — no system code touched, no commits).
**Origin:** Wave 3b's last unchecked third — Slice 3 of the now-archived (2026-10-17)
`wire-backend-into-refactored-ui` (27-round artifact shelved as HISTORICAL, pointer note in its
review-log). That artifact's approval spec was written against the SINGLE-slot data model; the
junction + span contract (`replacement_request_slots`, two-phase OCC, span read-model family) have
since redefined every approval semantic. This fresh change explores from CURRENT code.
**Ready for `/sdd-propose span-aware-approval-flow`.**

## 1. Ground truth at HEAD (verified live / line-referenced)

| Layer | Evidence | Behavior today |
|---|---|---|
| Route | `routes/web.php:79–84` — `/request-approval-ui`, mw `['auth','pl']`, legacy template registered | `class_exists('App\Livewire\RequestApproval')` is **FALSE** → the frozen mock template + `MockData` is what renders TODAY (D10 fallback), not a real page |
| PL gate | `bootstrap/app.php:25` `pl => CheckPl`; `app/Http/Middleware/CheckPl.php:20` (`user->isLecturer() && lecturer->is_pl`) | PL = lecturer role + `lecturers.is_pl = true`. **`users.role` CHECK only allows `student`,`lecturer`** — no DB-level PL value; the `is_pl` flag is the only PL signal. Demo has **exactly 1 PL** (staff **5425**, Surayaini) |
| Queue route test | `tests/Feature/RouteGateMatrixTest.php:23,38,50,121` pins the route + `pl` middleware already | RBAC red/green already settled — no route work needed |
| Request model | `app/Models/ReplacementRequest.php:29` fillable incl. `approver_id`,`rejection_reason`,`decided_at`; `:53 approver()` | all decision fields exist; status CHECK allows `pending,approved,rejected,cancelled,completed` (migration :34) |
| Slot state machine | `2026_08_03_000006_create_time_slots_table.php:26,48–51` — `status ∈ ('available','pending','occupied')`, `version` col, **partial unique no-double-book `WHERE status IN ('pending','occupied')`** | approve = `pending→occupied` flip per slot (version++); reject/cancel-back = `pending→available`. The partial unique self-heals: two approvals of the same slot can't both flip |
| Junction | `2026_10_16_000001_create_replacement_request_slots_table.php` — `replacement_request_id` FK cascade, `time_slot_id` **UNIQUE**, timestamps | the span's LIVE receipt; approve/reject release decisions read/write it |
| Submit path | `app/Actions/SubmitReplacementRequest.php` (SPAN_MIN/SPAN_MAX 1–4, span pre-resolve `failing_kind`, engine span vectors, OCC two-phase + peri-flip rollback; 12-test contract `SpanReservationTest`) | the pending request reserves anchor..anchor+span−1 as `pending` + junction rows + per-slot success audits. **No email is sent on submit today** (FR 4.15 unimplemented) |
| Cancel path | `app/Actions/CancelPendingRequest.php:45` — junction release loop (ordered lock, version-checked flips `pending→available`, per-flip audits, junction DELETE) | the exact RELEASE FAMILY reject should reuse (D6 symmetry) |
| Exception writer | `app/Actions/CancelClass.php:40–58` — `class_exceptions(create unique (session,week))` + audit | `ClassException::where('reason','official_event')` exists in the CHECK (5 values). **Demo DB has ZERO class_exceptions rows**; `CancelClass` currently has NO live caller (only an empty FormRequest stub) — dormant, awaiting UI |
| Approvals read | nil | no `ApproveRequest`/`RejectRequest`/`RequestApproval` in `app/` — everything below is write-path + read-path greenspace |
| Timetable read | `app/Concerns/ResolvesTimetableTimeline.php:224–226` (`approved !== null` → **original occurrence excluded**) + `:262–275` (replacement event added at the new slot, `status='replacement'`) | the approved MOVE read is ALREADY implemented for single-slot Approved rows — but keyed on `replacementTimeSlot` alone: an approved 2h span renders a **30-min** replacement block (junction not read in the approved branch) |
| API read | `app/Http/Controllers/Api/ApiReadController.php:174` (`myRequests` pending junction min/max), `:716` onward (venue-slots pending span cover state 3) + `:755` (**approved stays legacy anchor join**) | pending bucket junction-aware; approved bucket anchor-only — the round-5 fold "while the reservation is live" |
| History read | `app/Livewire/MyRequestHistory.php:79` pending junction span + `requestRow()` approved legacy | same pending/approved split |
| Student read | `app/Livewire/ReplacementHistory.php` (`render()` — cohort-scoped rows for the STUDENT timetable-updates surface, FR 1.4/1.5/1.9's data leg) | approved-by-cohort rows → another consumer that must learn spans |
| Venue read | `app/Livewire/VenueTimetable.php:153,176` — cells from `time_slots.status ∈ (occupied,pending)`; approved occupation surfaces via the flip itself | venue page: approve→occupied needs NO new join; cells turn dark `occupied` automatically |
| Frozen queue UI | `resources/views/ui-design-templates/request-approval-UI-design-template.blade.php` (1236 lines) — FCFS rows by `requestedAt` asc, `slotValidity` valid/conflict badge + `conflictReason`, bulk `Approve Selected`/`Reject Selected` (confirm popups), preset reject chips (:137), per-row + bulk actions, summary cards, filters persisted to localStorage, 100%-frozen layout | row shape (mock-data `requests` §2.9, :640+): `lecturer, requestedAt, courseCode/Name, classType, classDate/Day, timeStart/End, duration, venue, totalStudents, cohortCounts, cohorts, status, slotValidity, conflictReason, replacementDate/Time/Venue, reviewedBy/At, remarks` |
| Row-shape duplication | `ApiReadController::myRequests()` (:186–247) computes the SAME field set from real rows (incl. `STUDENT_COUNTS` cohort labels) | DRY §10.0.6 promotion candidate — the queue's own row mapper should NOT re-implement it (promote-*on-3rd-use* rule: myRequests + history row + approval queue = 3) |
| Mail/queue | `.env:37 QUEUE_CONNECTION=database`, `.env:49 MAIL_MAILER=log`; `phpunit.xml:32 MAIL_MAILER=array`; Laravel default `jobs/failed_jobs` migrations exist; `app/Mail/` does NOT exist | DB-backed queue ready (FR 4.16); log mailer = dev/demo benign path; `queue:work` runbook absent |
| NFR | 1.2 — OCC <100 ms beyond DB write (unbenchmarked); 1.4 — queued jobs drain <1 min of `queue:work` start | both testable; NFR 1.1 (<500 ms) already asserted in `ReplacementFlowTest` |
| Demo canary | request **#8 pending** — session 94 W14 (AMCS2093 T Thu 14:30 B107), junction 569+570 (W5 Thu 12:00–13:00 B002), submitted by 11 | one real APPROVABLE span in the demo DB (destructive if consumed — testing-DB only unless user re-approves) |

## 2. The approval flow — end-to-end shape (proposal-ready skeleton)

```
[PL queue rows — FCFS submitted_at asc, junction-min/max replacementTime + slotValidity badge]
        │ approve(requestId) / reject(requestId, reason)          [bulk = N× same action + confirm modal]
        ▼
RequestApproval (Livewire, pl)  ──  preliminary validity render = the SAME predicate as the
        │     approve action's stale-slot re-check (single source — compute once, badge + gate read it)
        ▼
ApproveRequest / RejectRequest (app/Actions/, ONE txn each):
   lock request FOR UPDATE + keyset check  →  re-check ALL junction slots
   (each still `pending` + version read; holiday/day-end unchanged — the EXACT predicate, shared)
   ├─ APPROVE:  flip ALL span slots pending→occupied (version-checked, throw-out-of-txn on mismatch
   │            — reborrow the two-phase discipline; junction rows STAY as the live receipt)
   │            → status=approved + approver_id + decided_at + summary decision audit
   │            + per-slot flip audits (FR 3.7 identity/timestamp/slot) + D7 original-release (Q2) 
   │            + ReplacementDecided(→proposer) queued + cohort update (FR 1.9, Q7)
   └─ REJECT:   mandatory reason (validated; preset chips + free text) → per-slot release loop
                (reuse CancelPendingRequest's release family verbatim) → status=rejected +
                decided_at + summary audit + per-flip audits + ReplacementDecided(→proposer)
```

Voluntary auto-reverts already hold: cancelled/rejected NEVER enter any pending map; reject
returns the original occurrence to all 4 surfaces (D1 verified both directions in the bug-hunt).

## 3. Decision points for grilling (one at a time, user decides)

| # | Question | Candidate answers + lean |
|---|---|---|
| Q1 | Scope: per-row actions only, or template's **bulk** approve/reject too? | Template promises bulk; lean IN (same action × N + one confirm modal) |
| Q2 | **D7 original-release semantics** — the old artifact says approve writes `class_exceptions official_event (session, week)`. Today that table is EMPTY in the demo and the conflicts that birth requests come from OTHER means (holidays, no exception at all). Risk: unique(session,week) → if an exception ALREADY exists (the reason the class was replaced), the write violates it; if NONE exists (holiday case), writing one DOUBLE-marks the occurrence (holiday + official_event) for every exception reader. Options: (a) write only when absent; (b) NEVER write — the approved-move exclusion already carries the release semantics at every read (timeline :224, engine exception-filter only matters when an exception exists); (c) write official_event + flip the original week's occupied venue-slot rows to `available` ("bookable by others" FR letter) — note `CancelClass` today does NOT flip rows, a consistency hole to rule on | grill with live demo data; lean (c) or (b) — decided with the user |
| Q3 | **Approved-bucket junction switch** — myRequests/history/timeline-move/ReplacementHistory approved reads are anchor-only; an approved span must render its true range ("14:00 – 16:00"). Options: (a) switch all approved consumers to junction min/max (recommended — one semantic, pending/approved share the junction); (b) keep the frozen round-5 legacy-anchor fold for approved ("while the reservation is live" — spans make that reading WRONG for display) | lean (a) |
| Q4 | Slot-flip audit granularity on approve | mirror full-block Q4: **one summary decision audit + one per-slot flip audit** (FR 3.7 wants slot IDs) |
| Q5 | Two-phase discipline on the approve flip | reuse validator-shape: ordered `FOR UPDATE` lock → check ALL (`span_overlap`-style abort → surface as `reserve_interrupted`-class stale-slot conflict, NOT a 500) → flip ALL version-checked. No new token names; reuse the existing family |
| Q6 | Reject reason: mandatory free text + preset chips? | FR 3.6 mandatory; template has preset chips — confirm the chip list at propose |
| Q7 | Emails: which + recipients | FR 4.15 `ReplacementSubmitted→PL(s)` (NOTE: submit-side notification is IN this change only if the user wants the full notification vertical — otherwise defer), FR 2.13 `ReplacementDecided→proposer` (approved/rejected + reason), FR 1.9 cohort students on approve. All ShouldQueue; MAIL_MAILER=log dev/`array` tests |
| Q8 | queue:work runbook + NFR 1.4 test (`--once` drain <1 min) | lean IN, docs + test |
| Q9 | NFR 1.2 OCC-overhead benchmark (<100 ms) | lean IN as a `SpanReservationTest`/approval-suite hrtime assertion |
| Q10 | Demo canary: approve live request #8 as the visible demo, or keep demo read-only? | destructive to the only pending demo request; default testing-DB only; user may choose the live approve as a presentation beat (snapshot-restore flow covers revert) |
| Q11 | Queue slotValidity source | computed by the SAME shared predicate the action enforces (one source for badge + gate), no parallel logic |
| Q12 | Where the queue's row mapper lives | extract the myRequests row-shape mapper into a shared helper (partial/service per §10.0.6 promotion rule — 3rd duplicate) |

## 4. Rejected approaches (already, WHY)

- **Amend the shelved wire-backend artifact instead of a fresh change** — its approval spec's data
  model (single FK `replacement_time_slot_id`, no junction, no span) contradicts current code at
  every decision level → full-chain unfreeze = re-authoring anyway (decision 2026-10-17).
- **Approve flips via raw UPDATE without version checks** — two PLs double-approving (or approve
  racing a cancel) must lose gracefully; the partial unique + version column exist exactly for
  this; a blind write is the exact class of bug `full-block-reservation` killed.
- **Separate validity predicate for badge vs gate** — drift between what PL sees and what approve
  enforces contradicts FR 3.4 ("PRE-computed slot validity"); one shared predicate.
- **Synchronous mail send inside the approve/reject txn** — FR 4.16 mandates queued; a slow SMTP
  would sit inside the OCC window (NFR 1.2 landslide).
- **Hiding requests whose span has drifted stale instead of showing the conflict badge** — the
  rejected-with-no-context UX the template already decided against (`slotValidity=conflict` row).

## 5. Key cross-module data flows

- Queue: `RequestApproval::render()` → PL-population from `replacement_requests` (pending × FCFS)
  × junction min/max × shared validity predicate → frozen row markup (Livewire real-data page
  replaces the D10 mock fallback route the moment the class exists).
- Decide: approval button → `ApproveRequest|RejectRequest` (txn family above) → picker/venue/
  timetable/API/history reads re-derive automatically from the slot statuses (state machine) +
  junction (approved bucket switch Q3a) + timeline move branch (already present, needs span-aware
  shape under Q3a).
- Notify: queued `ReplacementSubmitted` (submit action inserts the mail into `jobs`),
  `ReplacementDecided` (approve/reject inserts), cohort fan-out (Q7) — `queue:work --once` drains;
  tests use `Queue::fake`/`Mail::fake` (existing `EmailQueueTest` RED per shelved artifact's plan,
  rewritten for the span contract).
- Original-release (Q2): whatever the user decides — class_exceptions write + optional venue-slot
  row flip — must be visible to the engine (`exceptionIdsInWeek`), timeline (`exceptionKeys`), and
  venue page (row statuses) consistently; all three read sites are enumerated above.

## 6. Known open questions

- Does `ReplacementDecided` reach the proposer even when the DECIDING PL is the proposer? (self-
  approval ownership rule — PLs are also lecturers and may submit; FR silence → grill later)
- `completed` status in the CHECK is unused at HEAD — out of scope unless the user wants the
  "make-up delivered" leg (not Wave 3b's slice).
- Bulk actions' confirm copy + undo-toast callbacks were DROPPED for MyQueryHistory parity in the
  frozen template ("cannot be undone") — verify the same ruling is wanted on the approval surface.
- Holiday-masked pending rows (bug-hunt B3, P3) resurface as unreadable queue badges IF a holiday
  covers a requested week — LOW, note-only.

## Grilling decisions (2026-10-17, one at a time with the user)

| # | Question | Decision |
|---|---|---|
| Q2 | D7 original-release semantics | **(c): stamp + free-room, with one guard** — approval writes `class_exceptions` with reason `official_event` for (original session, week) ONLY when no exception exists yet (the unique `(class_session_id, week_number)` guard, detection pre-check); AND flips the ORIGINAL occurrence's occupied `time_slots` rows to `available` (version-checked) so the old room+time becomes bookable by others (FR Objective-3 letter). Effect is invisible on holiday weeks (W14 case) — verification lives in the tasks. |
| Q3 | Approved-bucket display | **A: full length everywhere.** All approved consumers switch to the same junction span read pending uses — timeline replacement block, `MyRequestHistory` row (replacementTime `14:00 – 16:00`), API `myRequests()` row, PL queue history bucket, student `ReplacementHistory` cohort rows. The frozen round-5 "approved keeps legacy anchor join while the reservation is live" fold is re-read as read-path-only and SUPERSEDED for display (the reservation was only live for pending). |
| Q4 | Audit granularity | **Confirmed (user "yes", 2026-10-17):** ONE summary decision audit per approve/reject + ONE per-slot audit row for each slot touched (identity, timestamp, slot id, action, reason when rejecting) — FR 3.7 satisfied twice over. |
| Q5 | Two-phase discipline on the flips | **Confirmed (user "yes", 2026-10-17):** ordered `FOR UPDATE` lock → check ALL → flip ALL version-checked, throw-out-of-txn on any mismatch; the loser PL surfaces the existing stale-slot conflict family (no new token names; mirrors `full-block-reservation`'s contract). |
| Q6 | Reject reasons | **Confirmed (user "yes", 2026-10-17):** preset chips VERBATIM from the frozen template (`Venue unavailable`, `Insufficient notice`, `Slot conflict`, `Lecturer unavailable`, `Other`); reason mandatory SERVER-side (empty reject can't submit); `Other` requires typed text; the reason string lands in `rejection_reason` verbatim. |
| Q7 | Email scope + sending | **A (user, 2026-10-17): all three Mailables NOW — but NOTHING departs.** `ReplacementSubmitted` (FR 4.15 → all `is_pl` PLs on submit), `ReplacementDecided` (FR 2.13 → proposer on approve/reject incl. reason), cohort-student fan-out (FR 1.9 on approve). All `ShouldQueue` (FR 4.16, `DATABASE_QUEUE`); `.env` stays `MAIL_MAILER=log` (dev/demo: log-file only, no SMTP, no real sends — flip to SMTP later, zero code change); phpunit keeps `MAIL_MAILER=array` (tests assert with `Mail::fake`/`Queue::fake`). |
| Q1 | Bulk scope | **Confirmed (user "yes", 2026-10-17):** bulk Approve/Reject WORKS — one confirm popup; per-request sequential processing in FCFS order; a stale row fails politely (its own conflict message) while the rest decide; bulk reject takes ONE typed reason applied to every ticked row (empty still impossible). |
| Q8/NFR 1.4 | Queue runbook + drain test | **Confirmed (user "yes", 2026-10-17):** runbook note (dev = log mailer, worker drains the jobs table; `queue:work` ops) + `queue:work --once` drain <1 min test. |
| Q9 | NFR 1.2 OCC benchmark test | **DECLINED (user, 2026-10-17):** no dedicated stopwatch test — NFR rides on reusing the existing two-phase pieces; recorded as an accepted residual. |
| Q11 | One validity predicate | **Confirmed (user "yes", 2026-10-17):** the dashboard badge and the approve/reject actions share ONE validity predicate (computed once, no drift). |
| Q12 | Shared row mapper | **Confirmed (user "yes", 2026-10-17):** ONE row-shape mapper extracted (existing API + history copies point at it) and the PL queue consumes it — promote-on-3rd-duplicate (§10.0.6); outputs byte-identical (existing format tests stay green). |
