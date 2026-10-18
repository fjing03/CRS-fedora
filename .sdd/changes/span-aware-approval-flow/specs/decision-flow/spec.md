# Spec: decision-flow (approve / reject actions)

**Frozen baseline:** proposal.md items 1–4 + 10; design.md D1, D2, D3, D6, D10, D11, D13.
**FR anchors:** FR 3.5 (1-click approve), FR 3.6 (mandatory reject reason), FR 3.7 (complete
audit trail), FR 4.8–4.11 (OCC + state machine), FR 4.13 (PL rights).
**DB truth:** `users.role` ∈ lecturer/student + `lecturers.is_pl` flag (CheckPl);
`audit_logs.action` CHECK ∈ ('submitted','approved','rejected','cancelled','completed',
'occ_conflict','class_cancelled'); `audit_logs.occ_validation_result` ∈ ('success','conflict').

## Requirements

**R1 — ApproveRequest action contract (design D1).**
Input `(User $decider, int $requestId, ?string $approverNote = null)`. Returns
`['ok' => bool, 'conflict_reason' => ?string]` (the existing modal payload shape — no new
token names). Exactly ONE transaction:
1. `lockForUpdate` the replacement-request row (held for the WHOLE txn); `status !== 'pending'`
   → `['ok' => false, 'conflict_reason' => <D6 case-(a) text>]`, no write, no audit.
2. Ordered `SELECT * FROM time_slots WHERE id IN (junction members) ORDER BY id FOR UPDATE`;
   `count !== junction count` → `['ok' => false, ` <D6 case-(b) text>`]`, no write.
3. Check ALL members `status === 'pending'` + capture versions; any other status → case-(b),
   no write.
4. Flip ALL members `pending→occupied`, each `UPDATE … WHERE id = ? AND version = ?` advancing
   `version = version + 1`; ANY 0-affected → `throw RuntimeException` (txn dies; NOTHING stays
   flipped — the two-phase discipline; the catch maps the throw to the case-(b) loser text,
   request stays pending).
5. Junction rows STAY (the LIVE receipt; no delete).
6. `status='approved'`, `approver_id = decider->id`, `decided_at = now()`; `rejection_reason` untouched.
7. ONE summary audit row: `action='approved'` (LEGAL token), `occ_validation_result='success'`,
   `time_slot_id = anchor`, `old_status='pending'`, `new_status='occupied'`,
   `details` = plan (decision + per-slot ids) **+ `approver_note`
   only when a non-blank note was typed** (D13; blank → key ABSENT, never fabricated).
8. + one per-slot audit row per flip: `action='approved'`, own `time_slot_id`, old/new statuses.
9. original-release (D3 spec below) INSIDE the same txn.
10. After COMMIT: queue `ReplacementDecided` (proposer; notifications spec).

**R2 — RejectRequest action contract (design D2).**
Input `(User $decider, int $requestId, string $reason)`. Same `['ok', 'conflict_reason']`
shape. Reason rules: whitespace-trimmed non-empty (FR 3.6 — SERVER-side; the render-side modal
validation is UX only, the action is the last line); stored VERBATIM (`Other` = any typed text; the chips are UI-only — no server preset list). Same txn shape:
1. same request-row lock guard (case-(a) loser);
2. same ordered junction lock (case-(b) loser);
3. release validity = all members still `pending` (case-(b) if any drifted);
4. flip ALL `pending→available` version-checked (`version = version + 1`); throw-out-of-txn on
   any 0-affected;
5. DELETE the junction rows (verbatim `CancelPendingRequest` release family);
6. `status='rejected'`, **`approver_id = decider->id`** (no rejector column — RR2 🔴),
   `decided_at`, `rejection_reason VERBATIM`;
7. ONE summary audit `action='rejected'`, `occ_validation_result='success'`,
   `details` plan + reason; + per-flip audits `action='rejected'` own `time_slot_id`
   (`pending→available`);
8. NO class_exceptions stamp, NO original-room freeing (reject releases only the span);
9. After COMMIT: `ReplacementDecided` (with the reason).
10. The natural red occurrence auto-returns everywhere (cancelled/rejected never enter any
    pending map — D1 behavior already living in `ResolvesTimetableTimeline`).

**R3 — original-release inside approve (design D3).**
Although satisfied by R1's step 9, the release has its own acceptance criteria:
1. keyed on the ORIGINAL `class_session_id` AND the REQUEST row's `week_number` (the original
   occurrence's week) — NEVER the junction members' `time_slots.week_number`
   (demo canary: original W14 vs replacement W5);
2. `class_exceptions(reason='official_event')` write ONLY when no row exists for that pair;
   the write is wrapped in a nested `DB::transaction` (SAVEPOINT) so a UNIQUE violation
   (concurrent approve of a sibling request, or a future live `CancelClass` caller) rolls back
   ONLY to the savepoint and the txn CONTINUES ('already-stamped: goal achieved' — no 500);
3. flip the ORIGINAL occurrence's `occupied` venue-slot rows (`class_session_id = original`
   AND `week_number = original week` — NEVER location matching) to `available`, each
   version-checked (`version = version + 1`); rows already `available` are no-ops;
4. the exception write + freed slots ride D1's summary audit `details` (no separate audit row);
5. acceptance: another class's booking sharing the same room/time is NEVER freed (identity-
   pinned); the engine (`exceptionIdsInWeek`), timeline (`exceptionKeys` + approved-move), and
   venue page (row statuses) re-derive consistently with no further code.

**R4 — Shared validity predicate (design D6).**
`ApprovalValidity::evaluate(ReplacementRequest $r): ValidityResult` — `valid` or
`conflict(reason)`. THE 2-CASE pinned map (no third case):
- (a) request row moved (`status ≠ 'pending'`) → `'already decided'`;
- (b) span drift (junction member missing / `status ≠ 'pending'` / version mismatch) → the
  EXISTING `slot_not_available` named-time copy (the same string the submit path renders).
Day-end/holiday facts cannot change mid-flight (static rows). The badge on the queue and the
action gate call the same function — one source, zero drift (Q11).

**R5 — Q13 ratified (user ack 2026-10-17).** A PL may decide any pending request including
their own submission. No ownership guard. `ReplacementDecided` still queues to the proposer
(single recipient row — no duplication).

**R6 — Idempotence/race.** Two PLs approving the same request concurrently: exactly-one-winner
(the loser hits R1 step 1 or 4 and surfaces the case-(b)/case-(a) loser — no 500, no partial
flips). Reject racing approve serializes on the held request-row lock
(design D2: the stamp happens under the same lock).

## Scenarios (test-pinned at tasks)
- approve happy path span-4: 4 flips (+version each), 4 slots `occupied`, junction rows intact,
  summary+per-slot audits (5 rows total), `approved`/`approver_id`/`decided_at`;
- approve with note: `details.approver_note` present; without: key absent;
- bulk × 3: rows in FCFS order; row 2's slots mutated mid-cycle by a racing actor → row 2's
  loser result; rows 1/3 decided; NO undo;
- reject: chips-verbatim reason stored verbatim; empty/whitespace reject → blocked (action
  returns the FR 3.6 loser); junction rows deleted; all 4 slots `available` (+version each);
- reject (none-grilled edge): `Other` with typed text → stored verbatim;
- race: two concurrent approves → winner applied; loser polite conflict, zero partial flips;
- D7: stamp-guard (existing exception row NOT duplicated — savepoint-continue branch),
  original rows freed (`available`), sibling-class rows at the same room untouched;
- NFR 1.2 note: rides reused two-phase pieces (grill Q9 = NO stopwatch test — accepted residual).
