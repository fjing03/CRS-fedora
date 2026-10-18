# Tasks: span-aware-approval-flow

**Baseline:** frozen proposal + design (D1–D13) + specs (decision-flow/display-reads/
notifications). Every task ≤2h. Gates standing per task batch: `php -l` after each file;
`composer run lint:check` + `vendor/bin/phpstan analyse app --memory-limit=1G` at slice ends;
demo DB `class_replacement` NEVER written (CodingMAIN §98/134) (all work on `class_replacement_testing` —
 grill Q10=A; `php artisan migrate:fresh --seed` fine there; TRUNCATE venues CASCADE if unique
 thieves bite).

## Slice A — shared infrastructure (no behavior change)

- [ ] **T1 — Extract `App\Services\ReplacementRequestMapper`** (spec display-reads R3; design D5):
      move the request-row → §2.9-shape logic into `toRow(ReplacementRequest $r, Carbon
      $semesterStart, string $requestedBucket, ?ValidityResult $validity = null)`. Data-driven
      junction fallback (junction empty → LEGACY anchor render + `slotValidity='valid'` +
      `conflictReason=null`) — ANY bucket, no branching; `requestedBucket` governs only
      bucket-labeled fields (`reviewedBy/At`). Point `ApiReadController::myRequests()` and
      `MyRequestHistory::requestRow()` at it. BYTE-IDENTICAL outputs: run the pinned API tests
      (`SpanReservationTest` D7.3/D7.5, `ReplacementHistoriesTest:205–212`, `ReplacementFlowTest`)
      + extend the legacy-anchor fixture with a CANCELLED row (anchor fallback, `valid`, no
      conflict — display-reads scenario 6 becomes an explicit assertion)
      — all green before slice end. §10.0.6 promotion entry recorded in the changelog (the file is born at T11 — entry
      written there per T11).
- [ ] **T2 — `App\Services\ApprovalValidity::evaluate()`** (spec decision-flow R4; design D6):
      `ValidityResult{valid: bool, reason: ?string}` value object; 2-case map — (a) row moved →
      **'already decided'**; (b) span drift (member missing / status ≠ pending) → the existing
      `slot_not_available` named-time copy (same strings the submit path renders). The version
      leg surfaces ONLY via the flip guard (never at render). Unit tests (testing DB).
- [ ] **T3 — Gates checkpoint.** lint + phpstan 0 + phpunit still 193+new green (the mapper
      swap must move zero semantics).

## Slice B — decision actions (the core)

- [ ] **T4 — `App\Actions\ApproveRequest`** (spec decision-flow R1; design D1/D11/D13): the
      10-step txn verbatim (request-row lock → ordered junction lock + `count>=1` guard →
      check ALL → version-checked flips (throw-out-of-txn) → junction stays → approve stamp
      (`approver_id`,`decided_at`) → summary audit `action='approved'` w/ `details` plan
      (+`approver_note` key only when non-blank, D13) → per-slot flip audits → original-release
      (T5) inside the txn → mails after commit via T7). Q13: NO ownership guard.
- [ ] **T5 — original-release inside T4** (spec decision-flow R3; design D3): nested
      `DB::transaction` (SAVEPOINT) around the `class_exceptions official_event` write
      (pre-check EXISTS for original session + REQUEST row's week; catch →
      'already-stamped: continue'); identity-pinned flips of the original's `occupied` venue
      rows → `available` (version-checked; never location matching). Audit rider inside D1's
      summary audit.
- [ ] **T6 — `App\Actions\RejectRequest`** (spec decision-flow R2; design D2): same lock shape;
      release family = VERBATIM flip+delete ONLY (cancel's skip semantics NOT copied);
      `status='rejected'` + `approver_id` + `decided_at` + reason VERBATIM (empty/whitespace
      reason → blocker); summary audit `action='rejected'` (details + reason,
      `time_slot_id = anchor`) + per-flip audits; junction DELETE; mails after commit.
- [ ] **T7 — decide-side mails (hook-only; spec notifications R1 #2 #3):** after-commit
      `ReplacementDecided` queue (capture-at-QUEUE-TIME — pre-release snapshot of span min/max,
      venue, decision, reason, PL name) + (approve-only) `ReplacementApprovalNotice` to
      affected cohorts' students, own job, no ordering contract. Mailables + plain Blade views
      + `ShouldQueue` (database conn); NO real SMTP (log mailer; Q7=A).
- [ ] **T8a — Fixture + core decision tests** (spec scenarios; testing DB only): fixture
      helper (span + PL + proposer; setval max(id)+1 — mirrors `SpanReservationTest`); approve
      span-4 happy path (flips/version/junction/audit count contract); approve+note
      (`approver_note` present) vs no-note (key absent); reject (chip reason verbatim; empty
      reject blocked; junction gone; slots back incl. version++); reject 'Other' free-text
      stored VERBATIM (any typed text — no server preset list).
- [ ] **T8b — Bulk, race, D7 tests** (spec scenarios): bulk×3 FCFS with an injected stale
      middle row (row 2 loser; rows 1/3 decided; NO undo) + **one shared reason landing in
      EVERY bulk-rejected row's `rejection_reason`**; race double-approve
      (exactly-one-winner); D7 stamp-guard (existing row NOT duplicated), original rows freed,
      sibling-class rows untouched; reject-doesn't-stamp.

## Slice C — queue page

- [ ] **T9 — `App\Livewire\RequestApproval` + view** (spec display-reads R1 + R2; design D7/D12):
      feature-tested data sourcing + gates (the spec's scenario bullets): 1 pending + 1 decided
      row from real fixtures, NO mock data in the payload; student/lecturer → 403 (pl gate);
      pending FCFS rows + decided bucket (approved/rejected; `completed` filter honestly empty)
      from real rows via T1/T2 mappers; frozen template parity 100% (bulk + notes modal +
      confirm popups + NO undo; filters/sort/rpp); modal open parity
      `classList.add('show')+display:flex`. `php artisan serve` + stale-cache ritual (two
      commands) before any manual verify; Playwright smoke on `/request-approval-ui`
      (placeholder-free render, badge classes present).
- [ ] **T10 — wire actions into the page** (decision-flow R1/R2 payload shape): OK/conflict
      modal rows per the existing `ReplacementArrangement` convention; bulk = server-side FCFS
      loop calling T4/T6 (polite per-row failure); **ONE shared reject reason threaded to EVERY
      bulk-reject call (frozen proposal item 1 + D10)**; NO Livewire JS surface beyond the
      frozen interactions.
- [ ] **T11 — page-changelog**: `page-changelogs/request-approval-changelog.md` (new page;
      D12 divergence ledger — undo dropped, bulk sequenced, notes→audit, decided-bucket
      sources, honest `completed` empty; the §10.0.6 mapper promotion entry).

## Slice D — approved-display junction switch (6 consumers; design D4)

- [ ] **T12 — Timeline move branch** (`ResolvesTimetableTimeline`): approved events read the
      junction min/max (eager-load `requestSlots.timeSlot`; NO per-row query); span-1 rows
      byte-identical; pending branch untouched.
- [ ] **T13 — Mapper switch** (`MyRequestHistory::requestRow()` + `ApiReadController::
      myRequests()`): approve the approved bucket ON to the mapper's junction read
      (pending bucket naming generalized to `spanSlotsByRequest`); `ReplacementHistoriesTest`
      stays byte-green (legacy anchor-only pending row → anchor render).
- [ ] **T14 — venue-slots API approved block** (`ApiReadController`): approved anchor join →
      junction min/max, full-run state-4 marking; span-1 byte-green.
- [ ] **T15 — `ReplacementHistory` (student cohort rows):** same junction map for approved
      cohort-scope rows.
- [ ] **T16 — Read-model feature tests** (spec scenarios; testing DB): approved span renders
      "14:00 – 16:00" across timeline/history/API/venue-slots/student rows; legacy pending
      anchor-only row byte-identical; cancel auto-revert untouched.

## Slice E — submit-side hook + queue ops

- [ ] **T17 — `SubmitReplacementRequest` + FR 4.15 hook** (notifications R1 #1): queue
      `ReplacementSubmitted` to every `is_pl` PL after a successful reserve (after-commit);
      NO change to OCC semantics or the flat return payload.
- [ ] **T18 — `tests/Feature/EmailQueueTest.php`** (spec notifications R3 + design D9):
      `Mail::fake`+`Queue::fake` enumeration tests (recipients per the Q7 table:
      submit→PL(s), approve→proposer+cohort students dedup, reject→proposer only; Q13
      decide-own case; multi-cohort dedup) + the SEPARATE no-`Queue::fake` real-worker drain
      test (NFR 1.4: `queue:work --once` consumes within one cycle, <1 min).
- [ ] **T19 — Runbook doc** (notifications R4): `docs/queue-and-emails.md` (dev = log mailer;
      worker ops; jobs persist until drained; SMTP flip = `.env` MAIL_* only).

## Slice F — close-out

- [ ] **T20 — Full gates.** `composer run lint:check`; `vendor/bin/phpstan analyse --memory-limit=1G` (the standing long form; the composer types
      script still dead at 128M);
      `php artisan test` (baseline 193 + new suite; TC70 grep-invert guard respected);
      Playwright suits green (venue + new approval smoke); stale-cache ritual after blade
      edits. Demo DB untouched (status checks via tinker only).
- [ ] **T21 — Docs + closure.** `CodingMAIN.md` wave-status row (Objective 3 FCFS dashboard →
      ✅; Objective 4 notifications → ✅ log-mailer), `docs/README.md` index entry for the
      runbook, changelog cross-links; implementer completion note in `review-log.md`;
      `change-status.sh`; report to user → `/sdd-verify` → suggest `/sdd-archive`.
