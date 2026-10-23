# Proposal: span-aware-approval-flow

**Change:** approve/reject the span (full-block) reservation flow end-to-end — the PL approval
dashboard, its actions, the D7 original-release, the approved-bucket display switch, and the
queued notification vertical. The last unchecked third of Wave 3b.
**Baseline:** `.sdd/changes/span-aware-approval-flow/explore-brief.md` — GRILLED 2026-10-17,
Q1–Q12 decided with the user; the decisions table there is binding for this proposal.
**Replaces:** Slice 3 of the archived `wire-backend-into-refactored-ui` (shelved HISTORICAL
2026-10-17, pointer note in its review-log) whose approval spec predates the
`replacement_request_slots` junction + two-phase OCC contract.

## Why

Wave 3b is two-thirds done (submit with spans + two-phase OCC; cancel with junction release —
`full-block-reservation`, archived 2026-10-17). A pending request can still never LEAVE pending
at HEAD:

- The approval route `/request-approval-ui` exists (`auth` + `pl` middleware, RBAC pinned in
  `RouteGateMatrixTest`) but `App\Livewire\RequestApproval` does not exist → the route serves the
  frozen MOCK template fed by `MockData` (D10 fallback), not real rows.
- No `ApproveRequest` / `RejectRequest` actions anywhere in `app/` — approved/rejected statuses
  exist in the DB CHECK but are unreachable. The demo's one planted request (#8) can only ever
  be cancelled.
- No Mailables exist; FR 4.15 (PL on submission), FR 2.13 (proposer on outcome), FR 1.9 (cohort
  students) and FR 4.16 (database-backed queue) are unimplemented.
- `class_exceptions` has zero rows in the demo; the frozen Objective-3 promise ("the original
  block is marked replaced/cancelled and its old time+room become bookable by others") has no
  implementer — and the single-slot design the shelved artifact wrote for it is wrong for spans.

User-facing increment: PLs decide requests (row + bulk, mandated mandatory reject reason, FCFS);
every decision flips the slot state machine (`pending→occupied` on approve with per-PL race
protection), frees the original room for others, shows the true span everywhere, and delivers
queued emails — while the demo DB stays untouched (testing-DB-only, grill Q10).

## In scope

All 11 items below carry their grill-ruling inline (brief §decisions — the user's word is law).

1. **`RequestApproval` Livewire page** (`app/Livewire/RequestApproval.php` +
   `resources/views/livewire/request-approval.blade.php`) — 100% the frozen template's layout,
   interactions and copy (99.9% UI rule): FCFS rows (`submitted_at` asc, FR 3.2) of ALL pending
   requests (PL sees everyone's — FR 3.1 Riders), proposer/subject/cohorts/proposed
   time+venue/remarks per row (FR 3.3), `slotValidity` valid/conflict badge + `conflictReason`
   (FR 3.4), per-row Approve/Reject (FR 3.5) + **working bulk** Approve-/Reject-Selected (Q1:
   FCFS-sequenced, stale row fails politely, ONE shared reject reason), reject modal with the
   frozen preset chips (`Venue unavailable`, `Insufficient notice`, `Slot conflict`,
   `Lecturer unavailable`, `Other`) + typed text, confirm popups, summary cards, filters/sort/
   rows-per-page parity. Once the class exists the route stops serving the D10 mock (the
   `class_exists` branch in `routes/web.php` flips it automatically — no route edit).
   **Undo-toast ruling (Q-rider, brief §6): NO undo** — decisions are final; the confirm
   popups carry the frozen "cannot be undone" copy; no status-restore callbacks are built.
   **Queue data sources:** the pending bucket (FCFS) feeds the action rows; the DECIDED bucket
   (approved/rejected rows — the `completed` filter stays an honest empty state, that leg is
   out of scope) feeds the status filter, the Approved/Rejected summary cards and the detail
   modal's Reviewed By/At (identity from `approver_id`, time from `decided_at`); both buckets
   read the same `replacement_requests` table — no second data layer. CANCELLED rows (self-
   cancel — the junction was deleted at cancel time) share the same LEGACY anchor fallback the
   rejected bucket uses (item 3) — one rendering rule for every junction-miss row.
2. **`ApproveRequest` action** (`app/Actions/ApproveRequest.php`) — ONE transaction:
   request-row lock (`FOR UPDATE`, idempotence/stale guard, only-pending passes) → ordered
   `WHERE id IN (junction slots) ORDER BY id FOR UPDATE` lock → check ALL (each still `pending`
   + version read) → flip ALL `pending→occupied` version-checked, throw-out-of-txn on any
   mismatch (Q5: the two-phase discipline reborrowed; the loser surfaces the EXISTING stale-slot
   conflict family, no new tokens, never a 500) → junction rows STAY (the LIVE receipt) →
   `status=approved` + `approver_id` + `decided_at` → ONE summary decision audit + ONE audit per
   flipped slot (Q4: FR 3.7 identity/timestamp/action/slot) → D7 original-release (item 4) →
   queued `ReplacementDecided` (FR 2.13) + cohort fan-out (FR 1.9).
3. **`RejectRequest` action** (`app/Actions/RejectRequest.php`) — mandatory reason enforced
   SERVER-side (empty reject cannot submit; `Other` chip requires typed text) (Q6); the release
   family reuses the `CancelPendingRequest` junction-release shape VERBATIM incl. the junction
   DELETE (ordered lock, version-checked `pending→available` flips, per-flip audits) →
   `status=rejected` + **`approver_id` = the deciding PL** (no separate rejector column exists
   — one identity slot for decide-rows, RR2 🔴) + `decided_at` + `rejection_reason` VERBATIM →
   same audit family (FR 3.7 — reason included) → queued `ReplacementDecided` with the reason.
   **Rejected-row span display:** after the junction DELETE the decided bucket renders a
   rejected row's replacement range from the LEGACY anchor slot fallback (the mapper's
   junction-miss path — RR2 🟡; rejected means NOT reserved, so the receipt is gone by design).
   Cancel-logic parity means the natural red
   occurrence returns on all surfaces automatically (D1, already built).
4. **D7 original-release = Q2 "(c) stamp + free-room, with one guard"** — on APPROVE:
   (i) write `class_exceptions(reason='official_event')` for (original session, request week)
   ONLY when no exception row exists yet (the unique `(class_session_id, week_number)` pre-check
   guard — existing exceptions are never duplicated); (ii) flip the ORIGINAL occurrence's
   `occupied` venue-slot rows to `available` (version-checked) so the old room+time becomes
   bookable by others (Objective-3 letter). **Row identity is pinned (review RR1 🟡5):** the
   flip keys on `class_session_id =` the original session AND `week_number =` the request's
   week — NEVER on room/time matching — so another class's legitimate booking in the same
   room cannot be freed; rows not carrying the original session's own booking are untouched
   (and no `pending` rows can sit in the way — the partial unique index forbids co-reservation).
   Engine (`exceptionIdsInWeek`) + timeline (`exceptionKeys` + approved-move) + venue page (row statuses) then read consistently with no
   further edits. Holiday-week occurrences make the freeing invisible (W14 case) — noted, not a
   defect.
5. **Approved display = junction everywhere (Q3=A)** — approved consumers switch from the legacy
   anchor join to the junction min/max span: timeline replacement block shape
   (`ResolvesTimetableTimeline` move branch), `MyRequestHistory` row, `ApiReadController::
   myRequests()` row, **the venue-slots API endpoint's approved marker block**
   (`ApiReadController`'s approved anchor join switches to the junction so an approved 2h span
   marks the FULL run, not 30 min), **the `RequestApproval` queue's decided/history bucket**
   (the decided rows behind the Approved/Rejected cards + detail modal), and student
   `ReplacementHistory` cohort rows. Outputs for existing single-slot rows stay byte-identical
   (the pending bucket already proved the pattern); the round-5 "approved keeps legacy anchor"
   fold is superseded FOR DISPLAY.
6. **Emails — all three Mailables, queued, NOTHING really sends (Q7=A)** —
   `ReplacementSubmitted` (FR 4.15 → all `lecturers.is_pl` PLs; queued on submit inside
   `SubmitReplacementRequest`), `ReplacementDecided` (FR 2.13 → proposer on approve/reject incl.
   the reason), cohort-student fan-out (FR 1.9 → the affected cohorts' student emails, approve
   only). All `ShouldQueue` on the `database` connection (FR 4.16); `.env` stays
   `MAIL_MAILER=log` — dev/demo writes to the log file ONLY; switching to real SMTP later is a
   pure `.env` flip with zero code change. Tests use `Mail::fake`/`Queue::fake`.
7. **Queue runbook + NFR 1.4 drain test (Q8)** — short doc note (dev = log mailer; worker ops;
   `php artisan queue:work`) + a test that drives a real worker on the test connection
   (`queue:work --once`), asserting the queued email job is consumed-and-sent within that
   single drain cycle and the elapsed time stays <1 minute (the NFR 1.4 letter).
8. **ONE shared validity predicate (Q11)** — the `slotValidity` badge on the queue and the
   approve/reject actions' stale-slot re-check are the SAME code path (compute once; badge and
   gate cannot drift).
9. **ONE shared row mapper (Q12)** — the request row-shape (subject, cohorts + counts, times,
   venue, replacement span, requester, statuses) is extracted into one shared mapper; the
   existing `ApiReadController::myRequests()` mapper and `MyRequestHistory::requestRow()` point
   at it and the PL queue consumes it too (§10.0.6 promote-on-3rd-duplicate). Byte-identical
   outputs for the existing consumers (their format tests pin that).
10. **Queue row sorting/bulk semantics tests (Q1/Q2/Q3 riders)** — in the test suite below.
11. **Tests** (testing DB `class_replacement_testing` only — Q10=A; demo DB NEVER written):
    - `tests/Feature/ApprovalWorkflowTest.php`:
      · reject-without-reason fails (FR 3.6) — empty submit blocked server-side; `Other`
        requires typed text;
      · stale-slot re-check governs (FR 3.4) — the racing-actor mutation leaves the request
        pending with a polite conflict and ZERO partial flips;
      · FCFS order (FR 3.2) — the queue populates and bulk processes in `submitted_at` order;
      · bulk semantics (Q1 rider — landing here): one confirm → sequential execution; a stale
        row fails politely (its own conflict message; the row stays pending) while the rest
        decide; ONE shared reject reason covers every ticked row; bulk is N× the single action
        with NO undo;
      · approve happy path (single + span): `approved` + approver identity + `decided_at`,
        per-slot `occupied` flips version++-, junction rows retained, ONE summary audit + one
        per-slot audit each (FR 3.7);
      · multi-PL race: two PLs approve concurrently → exactly-one-winner; the second surfaces
        the existing stale-slot conflict family (no new tokens).
    - D7 release tests (single home here — RR2 🟡; item 4 has NO tests section): stamp-guard
      (a pre-existing exception row is NOT duplicated — the guard branch); the original
      session's OWN booked rows flipped `available` (bookable by others; identity-pinned per
      item 4 — another class's rows are untouched); reject does NOT stamp/release (only the
      span back to `available`).
    - `tests/Feature/EmailQueueTest.php` (uplifted from the shelved artifact's planned test —
      bootstrapped new here for the span contract): queued dispatch on submit/approve/reject
      (`Queue::fake`), recipients per the grill Q7 table (`Mail::fake`), `queue:work --once`
      drain (NFR 1.4 — item 7's contract).
    - Read-model display tests: approved junction span on timeline/history/API/student rows ✚
      byte-identical regressions for existing shapes.
    - Gates at close-out: `composer run lint:check`, `vendor/bin/phpstan analyse
      --memory-limit=1G`, `php artisan test` (baseline 193 + new), Playwright suites (frozen
      template-side unchanged; the new page gets a minimal smoke test), stale-cache ritual.

## Explicitly out of scope

- **Real SMTP delivery** — `MAIL_MAILER=log` stays; the switch is documented, but no
  infrastructure beyond it (no new deps, no service integrations).
- **Any demo-DB write** — request #8 stays PENDING (the live exhibit the PL decides in front of
  an audience, Q10=A). No snapshot work this change.
- **`completed` status leg** ("make-up delivered") — unused CHECK value, not Wave 3b.
- **Bug-hunt B3** (pending readability when a holiday masks the occurrence) — P3 cosmetic, later.
- **In-app notifications panel wiring** — the frozen bell/panel (`partials/ui-notifications-panel.blade.php`) STAYS MOCK-FED (user ruling 2026-10-17, option A: emails carry the notification duty per FR 4.15/2.13/1.9/4.16; real-panel wiring = a planned follow-up mini-SDD, option B).
- **Submit-side UI changes** — FR 4.15 is a server-side queued-mail hand-off only; the frozen
  submission surfaces are untouched.
- **NFR 1.2 stopwatch benchmark** — grill Q9=NO: no dedicated OCC-overhead test; the perf
  promise rides on reusing the already-tested two-phase pieces (accepted residual).
- **Shelved artifact amendments** — `wire-backend-into-refactored-ui` stays archived; its
  pointer note documents the supersession.
- **Self-approval ownership rule** — flagged-open in the brief (FR silent), tracked as **Q13**
  (grill slot, pending user ack — review-log Round 1): this change ships the BEHAVIOR-NEUTRAL
  default (a PL may decide any pending request incl. their own submission; the proposer still
  receives `ReplacementDecided`). If the user later rules otherwise, it is a small follow-up
  guard, recorded in the brief's open questions.

## Impact scope

| Area | Touch |
|---|---|
| New actions | `app/Actions/ApproveRequest.php`, `app/Actions/RejectRequest.php` |
| New shared mapper | `app/Services/ReplacementRequestMapper.php` (placement finalized at design; the §10.0.6 promotion entry is ON design.md's agenda) |
| New Livewire page | `app/Livewire/RequestApproval.php` + `resources/views/livewire/request-approval.blade.php` |
| New mail | `app/Mail/ReplacementSubmitted.php`, `app/Mail/ReplacementDecided.php`, cohort leg design at design-time (queued, no renders yet beyond plain views) |
| Read models | `app/Concerns/ResolvesTimetableTimeline.php`, `app/Livewire/MyRequestHistory.php`, `app/Livewire/ReplacementHistory.php`, `app/Http/Controllers/Api/ApiReadController.php` (approved → junction; mapper extraction) |
| Submit hook | `app/Actions/SubmitReplacementRequest.php` (+ queued submit mail only — no semantic change) |
| Releases | D7 slot flips + exception write (approve path); no DB migration needed (all structures exist) |
| Tests | `tests/Feature/ApprovalWorkflowTest.php`, `tests/Feature/EmailQueueTest.php` (new); read-model test extensions in existing files |
| Docs | `docs/` queue runbook; `CodingMAIN.md` wave-status row; `page-changelogs/request-approval-changelog.md` (new page) |
| Gates standing | pint · phpstan 0 · phpunit ≥193+new · Playwright (TC70 guard honored) · testing-DB only |
