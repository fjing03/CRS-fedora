# Spec: span-reservation (full-block-reservation)

**Baselines:** proposal.md FROZEN (5 rounds) · design.md FROZEN (5 rounds). This spec freezes REQUIREMENTS only — no new decisions.

## R1 — Reservation semantics

1. A submitted block reserves `span = endHour − startHour` contiguous slots (anchor..anchor+span−1, same venue/day/week) in ONE transaction: ordered `FOR UPDATE` locks → check ALL statuses → flip ALL (version-checked) → junction rows → per-slot success audits. **No flip may precede a conflict exit.** Junction invariant (declarative): a slot is live-linked by at most ONE request (`time_slot_id` UNIQUE — frozen D0).
   - Steady-state losers (missing/non-`available` slot in the pre-resolve): reason `slot_not_available`, no write, `failing_start`/`failing_end` + `failing_kind` (`'missing'` derived-expected / `'occupied'` row-backed) in the action's return. (Note: `slot_not_available` and `invalid_span` NEVER receive a kind outside these two — R1.1's D2 stamps + the unlisted-combos fallback cover the rest.)
   - OCC-race losers: reason token `span_overlap` when `span > 1` (bound adaptively in the validator: span 1 → `slot_not_available` byte-identical), ONE conflict audit row (the failing slot's id + times in `details`), ZERO flips.
   - Peri-flip failures (flip `affected === 0`, junction insert, audit insert): THROW a RuntimeException subtype out of the txn → full rollback (zero flips, zero junction rows, the step-3 audit attempt rolls back too) → the single conflict audit row is re-written in the catch (best-effort) with the `concurrent_reserve_conflict` forensic token for junction/audit-throw causes; the action's `reserve_interrupted` catch stays the sole surfaced loser path.
2. ANCHOR invariant: a released/flipped slot shares the request's fate via `replacement_request_slots` (the receipt of the LIVE reservation). Orphan defence reads remain sound.
3. Bounds: `span` < 1 or > 4 or non-integer-derived → `invalid_span` BEFORE any business pre-check duplicate/holiday; no clamping ever. A legal span of 3 (1.5h) passes.
4. Multi-block submits keep today's model: one request per block, each with its own span; the D1 duplicate-active-request and holiday pre-checks run per block unchanged.

## R2 — Release semantics (cancel; future approval reuses)

1. One transaction: lock the release-set (junction rows, fallback `[anchor]` for legacy requests) ordered by id → per-slot version-checked flip `pending→available` → per-slot audit rows (flip rows only) → **DELETE the junction rows** → request `cancelled` + decided_at (+ the existing summary audit row).
2. Non-`pending` release-set slots skip the flip AND the flip audit (no false rows).
3. Anchor-only legacy requests keep cancelling byte-identically.

## R3 — Validation / engine

1. `MatrixIntersectionEngine::validateSlot(..., int $span = 1)`: same vectors evaluated for EVERY slot of the span; busy vector at ANY slot (incl. tails) rejects pre-reserve; holiday check per slot; default-1 callers byte-identical.
2. OccValidator step order: request validation (pending + anchor identity) FIRST, unchanged.

## R4 — Copy + transport

1. `conflictText(reason, failingStart, failingEnd, failingKind)` routing table (design D6):
   - `slot_not_available` + `failing_kind='occupied'` → *"Your {span}-slot ({hours}h) block runs into an existing booking at {start}–{end} — pick another slot."*
   - `slot_not_available` + `failing_kind='missing'` → *"Part of the block falls outside the booking grid — adjust the selection."* (times dropped)
   - `slot_not_available` + no kind + no times → *"That slot was just taken."*
   - `span_overlap` → the named-time copy (transported failing times; produced by the validator, `span > 1`) + holder-context suffix. **USER AMENDMENT 2026-10-16 (option B):** occupied losers append the holder's live range — *"…at {start}–{end} (part of a held 2-hour block, {holderStart}–{holderEnd})…"* — via `holder_start`/`holder_end` transport keys; cell-only fallback when no live link resolves.
   - `invalid_span` → *"Blocks are 30 minutes to 2 hours — adjust the selection."*
   - Unlisted combos → the generic fallback (the D2 mandatory stamp makes `failing_kind=null` + times unreachable).
2. Transport: `submitBlocks` rows carry reason + failing-kind token; the action's flat return gains `failing_start`/`failing_end`/`failing_kind` (request_id preserved); `OCCResult` gains the failing-slot fields (NO kind param — stamped action-side).
3. `span_overlap`'s producer is the VALIDATOR (span > 1) — the only mint point; the action never re-produces.

## R5 — Read-model family (the junction's span reaches every anchor-keyed consumer)

1. Venue page: one event per REQUEST (anchor slot id, `end = start + span − 1`); blade `span-N` coalesces; the "Amendment 2b" comment becomes span-aware; orphan defence unchanged.
2. Arrangement grid ownership: junction-derived (slot → request's proposer); owner's span tails = status 3, others' = 4; post-cancel free everywhere.
3. `myRequests()`: junction-derived TRUE range for pending; approved + cancelled keep the legacy anchor join (the `rt_start_time`/`rt_end_time` output keys carry BUCKET semantics: junction range for pending, legacy anchor values for approved/cancelled).
4. Venue-slots API map: pending rows mark `anchor..anchor+span−1`; the legacy join is narrowed approved-only (no double-marking).
5. My-request-history page: pending rows show the junction-derived range; post-cancel rows correctly show the anchor-only read (rows are never re-retained).

## R6 — Selector caps

1. Derived `BLOCK_SPAN` (both boot clamps): hours floor 0.5, cap 2h (4 slots).
2. Resize clamp: 4 slots; `MAX_SELECTION` = 4 (stays the multi-week total-slots budget — a 1h class still books twice; a 2h block uses the whole budget); the two stale comments (`≈:3395–3397`, `≈:3134`) reword.
3. OUT: the banner anchor clamp (`≈:2373`) — display-only of the original length.

## R7 — Acceptance scenarios (verify-stage checklist; all in `class_replacement_testing`)

> Note: "D5" below names the compensation-delete helper in `SubmitReplacementRequest` — NOT design section D5 (CancelPendingRequest). Likewise the proposal's D1 duplicate-active pre-check ≠ design section D1 (span derivation). Spec text uses the code entities' names where ambiguity is possible.

1. Submit 4-slot span → 4 junction rows, 4 flips, 4 success audits; success path request row + anchor column populated.
2. Race (advisory-lock window) runs TWICE with the same three-part assertion shape — A holding a **span-1** block (byte-compat arm) and A holding a **span>1** block (new arm); identical except the loser token:
   - (a) Winner untouched: A's request stays `pending` (anchor populated, no decided_at); all `span` junction rows intact; per-slot flips unchanged; ZERO new audit rows written against A's slots.
   - (b) Loser token distinguishes the arms at EXACTLY one point, produced ONLY by the validator: `span == 1` → `slot_not_available` (byte-identical HEAD shape, `failing_kind='occupied'` + failing times); `span > 1` → `span_overlap` — minted ONLY by the validator (the single producer; the action pass-through never re-produces), details carrying the same token + the failing slot's id/times; the loser copy interpolates the transported failing times. Distinguishable from peri-flip breakage: NO exception at the action boundary — no throw, no `reserve_interrupted` surfaced, NO `concurrent_reserve_conflict` forensic token anywhere (those two markers belong exclusively to the step-5 peri-flip rollback shape).
   - (c) Either loser: B's request row DELETED (the compensation delete); ZERO slots of B's selection flipped to `pending` (pre-resolve conflict exit precedes any flip; no staged commits, no compensation); ZERO junction rows inserted; ZERO flip audit rows for B; the single conflict audit row survives with `replacement_request_id` NULLed; A's span untouched.
3. Two-phase loser: mid-span `occupied` → whole submit loses; ZERO slots pending; anchor available; single audit row w/ token+times; request deleted (D5); its audit row survives (`replacement_request_id` NULLed).
4. Peri-flip rollback (injectable `unique` violation on step 5): same outcomes; surfaced as `reserve_interrupted`; forensic token in details.
5. Bounds: span 0/negative/6 → `invalid_span`, no write/audit; span 3 → passes bounds (business checks may still fail).
6. Cancel 4-slot pending → 4 slots available (version++ each), per-slot audit rows, junction rows deleted, request `cancelled`.
7. Legacy anchor-only cancel works byte-identically (test-constructed fixture).
8. Read-model: venue page one amber span; arrangement statuses 3/4 per owner; `myRequests()` true range (pending) / anchor (cancelled); API map marks full span; history page true range pending / anchor-only post-cancel.
9. Byte-compat: engine/OCC `span=1` defaults; `validateAndReserve` 1-arg callers; `conflictText` 1-arg callers; non-slot-availability losers carry `failing_kind = null`.
