# Spec: display-reads (queue page + approved-bucket junction switch + shared mapper)

**Frozen baseline:** proposal items 1 + 5 + 9 + item 1's queue-data-sources block; design
D4, D5, D6, D7, D12. **FR anchors:** FR 3.2/3.3/3.4 (queue order + row data + pre-computed
validity), FR 3.5/3.6 (the modal surfaces), §10.0 (color/legend law), 99.9%-UI rule.

## Requirements

**R1 — RequestApproval page (design D7).**
Route `/request-approval-ui` (already gated `auth` + `pl` — NO route edit; the
`class_exists` branch flips D10→real component automatically). The Livewire component renders
100% the frozen template's layout/copy/interactions:
- pending rows: ALL pending requests, FCFS `submitted_at` ASC (FR 3.2);
- decided rows: approved/rejected (the `completed` filter stays an honest EMPTY state — out of
  scope leg) feed the status filter, Approved/Rejected summary cards and the detail modal
  (`reviewedBy` = approver name via `approver_id`, `reviewedAt` = `decided_at`);
- each row: proposer name, subject, cohort labels + counts, class date/day/time/venue,
  replacement date/time (span-aware via the shared mapper R3) / venue, remarks, status,
  `slotValidity` + `conflictReason` (R4 mapping);
- per-row Approve/Reject + bulk Approve-/Reject-Selected (decision-flow spec); confirm
  popups verbatim incl. the approve NOTES modal (single + bulk) and NO undo;
- filters/sort/rpp parity with the frozen template (render-side only; no server pagination
  contract beyond the component's payload).

**R2 — Queue data sourcing (proposal item 1's blocks).** Both buckets read the same
`replacement_requests` table — pending (action rows) and decided (cards/modal). NO second
data layer. `viewedAt`/mock-only fields render honestly empty where the frozen template
accepts null.

**R3 — Shared row mapper (design D5).** `ReplacementRequestMapper::toRow(
ReplacementRequest $r, Carbon $semesterStart, string $requestedBucket,
?ValidityResult $validity = null): array` produces the frozen `requests` §2.9 shape ONE way for
all consumers. Pin:
- `slotValidity`/`conflictReason` ride the passed `ValidityResult` when given (callers that
  already evaluated R4/decision-spec); otherwise the mapper evaluates itself (standalone use);
- junction-miss rows (rejected — deleted at reject; cancelled — deleted at cancel) render
  `replacementDate/Time/Venue` from the LEGACY anchor slot AND pin
  `slotValidity='valid'`, `conflictReason=null` (RR2 💡 — a decided row is past contention);
- junction-present rows render min/max spans ("14:00 – 16:00" format — byte-identical with the
  pinned API tests);
- cohort labels + counts reuse the existing `STUDENT_COUNTS` lookup (no second source);
- promotion: the existing `ApiReadController::myRequests()` mapper and
  `MyRequestHistory::requestRow()` point AT the shared mapper — outputs BYTE-IDENTICAL
  (existing format tests keep passing); the promotion entry lands in this change's changelog
  (§10.0.6).

**R4 — Approved-bucket junction switch (design D4; Q3=A).** Six consumers read the junction
min/max for APPROVED (and join the existing pending path) instead of the legacy anchor join:
① `ResolvesTimetableTimeline` move branch — the replacement event's `start/end` from junction
min/max (+ eager-load `requestSlots.timeSlot`; NO per-row query in the loop); ②
`MyRequestHistory::requestRow()` — the existing pending branch generalizes (approved joins it);
③ `ApiReadController::myRequests()` — one junction map keyed for pending+approved
(`spanSlotsByRequest` naming), `reviewedBy` already maps the approver; ④ the venue-slots API
approved marker block — junction min/max, full-run mark (state 4), anchor-only join retires;
⑤ the RequestApproval decided bucket (via R3 mapper); ⑥ `ReplacementHistory` student rows —
cohort-scoped reads gain the same junction map. Byte-identical for span-1 rows (junction min/max
=== anchor) keeps every pinned existing format test green.

**R5 — Divergence ledger (design D12).** Every real-backend divergence from the mock's client
scripts is recorded in `page-changelogs/request-approval-changelog.md`: undo toasts dropped
(decisions final), bulk sequenced server-side (per-row results), notes captured to the audit,
decided-bucket sources, `completed` filter's honest-empty state.

## Scenarios (test-pinned at tasks)
- queue renders 1 pending + 1 decided row from real DB fixtures (mock data absent from the
  payload); pl gate: student/lecturer → 403 via existing middleware;
- approved span-4 shows "14:00 – 16:00" in the decided bucket + history + API row + timeline
  move event (4-slot block) + venue-slots full-run mark + student cohort row — vs the anchor
  fallback a rejected row renders (span gone, 30-min anchor shown, `valid` badge);
- mapper swap: PENDING the existing API/history format tests stay green after the mapping
  moves (byte-identical contract);
- cancelled row: anchor fallback, `valid`, no conflict noise.
