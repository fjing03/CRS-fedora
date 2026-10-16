# Design: full-block-reservation

**Frozen proposal:** `proposal.md` (5 rounds, frozen 2026-10-16; round-5 folds included). **Baseline:** `explore-brief.md` (re-verified 2026-10-16; item 7 withdrawn round-4).
**Source anchors (verified 2026-10-16):** `SubmitReplacementRequest.php:33` (invoke, 5th param `?string $remarks` → `int $span = 1` is the 6th, backward compatible), `:47–76` (anchor resolve/pre-check/engine/occ); `compensateLoser` `:100–107`; `OCCValidator.php:21–67` (validateAndReserve; conflict paths flip NOTHING); `auditConflict` `:69`; `CancelPendingRequest.php:27–58` (transaction, `whereKey ≈:35`, version flip, audit); `MatrixIntersectionEngine.php:172–213` (`validateSlot`; lecturer/cohort busy vectors per slot); `ReplacementArrangement.php:461–590` (`submitBlocks`: block loop `:478–588`, slot resolve `:540–552`, validator `:556–567`, action call `:569`, results `:571–577`), `conflictText ≈:618`, `venueSlotsByWeekPayload` ownership map `≈:326–332`; `VenueTimetable.php:255–285` (pending join + orphan defence `≈:272–277` + "Amendment 2b" comment `:254–260`), `mergeTwinEvents ≈:394`; `ApiReadController.php:135–170` (`myRequests` anchor join ≈`:143`) + `≈:685–712` (venue-slots map join ≈`:688`, cell loop `:693+`); migration `2026_08_03_000009:21`; blade cap sites: boot clamps `≈:2562`/`≈:3399`, resize clamp `≈:3143`, `MAX_SELECTION` couplings `≈:2564`/`≈:3399`, banner anchor clamp `≈:2373` (OUT of family), stale 4-HOUR comments `≈:1187–1189`/`≈:3395–3397`.

## D0 — Schema: `replacement_request_slots` (junction = LIVE-reservation receipt)

Additive migration `2026_10_16_000001_create_replacement_request_slots_table.php`:

```php
Schema::create('replacement_request_slots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('replacement_request_id')->constrained('replacement_requests')->cascadeOnDelete();
    $table->foreignId('time_slot_id')->constrained('time_slots')->cascadeOnDelete(); // matches the legacy column's cascadeOnDelete
    $table->unique('time_slot_id'); // LIVE reservation: one live link per slot, ever
    $table->timestamps();
});
```

- **Release deletes the rows** (`CancelPendingRequest`; future approval reuses the same release). Cancel keeps the REQUEST row (`cancelled`) — cascade fires only on DELETE, so retention would poison the freed slot forever (resubmit re-INSERTs the same `time_slot_id` → unique violation). The "receipt" = the LIVE reservation; history lives in `audit_logs` (per-slot release rows) + the request row.
- `time_slot_id` UNIQUE mirrors the per-slot partial unique's guarantee at the junction grain.
- **Approved bucket (round-5 🟡):** the venue-slots API map marks pending AND approved — the legacy anchor join is RETAINED for the approved bucket (nothing writes `approved` at HEAD; the join stays so an approved request's marking never silently disappears); the new junction reads are pending-geometry-only.
- `app/Models/ReplacementRequest.php` gains `requestSlots(): hasMany(ReplacementRequestSlot::class)` (additive); a minimal `ReplacementRequestSlot` model (timestamps, no extra fillables beyond the FKs).

## D1 — Span derivation: endHour, reject-not-clamp

`submitBlocks` per block: `$rawSpan = (int)($block['endHour'] ?? 0) − (int)($block['startHour'] ?? 0);` — the blade's `endHour` is EXCLUSIVE (`h < endHour` loops; `span-N` = end−start), so this is the slot count exactly.
- `if ($rawSpan < 1 || $rawSpan > 4) → 'invalid_span'` loser (D5 N/A — pre-request, no write, no audit row; same pre-request shape as `invalid_week`).
- **No clamping** (round-1 🔴4): a 3h ask is REJECTED, never silently shortened.
- `submitBlocks` casts with `(int)` silently — acceptable: the reachable validation surface is the derived value (tests pin the derivation).
- Passed onward as `int $span` to the action.

## D2 — `SubmitReplacementRequest::__invoke(..., int $span = 1)`

1. Ownership/week/slot checks unchanged (`:36–55`).
2. **Span bounds guard (round-1-verify 🟡2 — the action is a public entry):** `if ($span < 1 || $span > 4) return $fail('invalid_span')` — pre-request, no write, no audit row (same shape as `invalid_week`); direct callers (tests, future surfaces) are covered even though `submitBlocks` already guards in D1. D9's bounds tests (0/5) target BOTH surfaces: via `submitBlocks` (D1) and via a direct action call (this guard).
3. **Span pre-resolve (steady-state named-time path):** precede it with the `invalid_span` bounds guard (round-5 fold — a malformed payload never reaches business pre-checks), then query the span's slots (same venue/day/week, `start_time` ordered with an `id` tiebreak): `$slots = TimeSlot::where('semester_id',…)->where('week_number',…)->where('venue_id',…)->where('day_of_week',…)->whereIn('start_time', $startTimes)->orderBy('start_time')->get();`
   - count < span → `slot_not_available` with the computed expected time of the first missing slot **and `failing_kind = 'missing'`** (no row to read — derive `start = anchor.start + k×30`)."
   - any non-`available` → `slot_not_available` with that slot's `start/end` in the return keys **and `failing_kind = 'occupied'`** (row-backed — truthful).
4. Engine pre-check (D4) over the full span.
5. Request create unchanged (anchor column populated).
6. `occ->validateAndReserve(..., $span)` (D3).

**Return shape (round-2 🟡 → frozen; round-3 🔴 extension):** `['ok' => bool, 'conflict_reason' => ?string, 'request_id' => ?int, 'failing_start' => ?string, 'failing_end' => ?string, 'failing_kind' => ?string]` — `request_id` preserved; the new keys null unless a slot-availability loser produced them (pre-check steady-state OR OCC race). **`failing_kind` Discriminator (round-3 🔴):** `'occupied'` (row-backed loser — a truthful named time) vs `'missing'` (no row exists — the derived EXPECTED time; the copy must not claim a booking). OCC-race losers: OCCResult gains NO kind param — the ACTION stamps the kind in its `! $result->success` branch: it copies `$result`'s `failing_start`/`failing_end` into the return keys and stamps `failing_kind = 'occupied'` UNCONDITIONALLY (a raced row always exists — row-backed; the action reads the extended OCCResult signature). D9's span-1 mirror ALSO asserts the rendered named-time copy (round-5 fold — the copy path covered at every span). Pre-resolve losers stamp their own kind (`'occupied'`/`'missing'`, D2 step 3); every NON-slot-availability path returns `failing_kind = null` (byte-compatibility).

## D3 — `OCCValidator::validateAndReserve(..., int $span = 1)` — TWO-PHASE (mandated)

ONE transaction, **no flip ever precedes a conflict exit** (round-1 🔴3):

1. Request validation unchanged (pending + anchor identity — `replacement_time_slot_id` === anchor).
2. **Lock ALL span rows:** `TimeSlot::whereIn('id', $slotIds)->orderBy('id')->lockForUpdate()->get()` (ordered → no deadlock pairing).
3. **Check ALL statuses first** (per-slot `status !== 'available'` → collect the FIRST failing slot; continue scanning or break — the audit writes after). Any failure → `auditConflict(...)` (SINGLE row — round-3 🟡) with the reason token BOUND ADAPTIVELY (round-4 🔴): `span === 1` → `slot_not_available` (HEAD byte-identical — `OCCValidator.php:36` unchanged); `span > 1` → `span_overlap` (the frozen race token — it had NO producer before this binding). `details` carries the SAME token + the first failing slot's `failing_start`/`failing_end` (the frozen AC-1/Q8 contract) — `details.conflict_reason` becomes permanent history, so the token mint point is HERE and only here. → `OCCResult::conflict(reason, failingSlot, failingStart, failingEnd)` → transaction COMMITs with ZERO flips (nothing was written). **The existing action pass-through (`SubmitReplacementRequest.php:90–92`) is the ONLY transport — `conflictReason` flows INTO the flat return unmodified; no action-side re-production of tokens.**
4. **Flip all — then THROW on any post-first-flip failure (per-branch rollback contract, round-1-verify 🔴2):** per-slot version-checked raw UPDATE (`available→pending`, version+1). **A peri-flip branch that normal-returns would COMMIT the already-flipped slots** → compensateLoser deletes the request → orphaned pendings → the partial-flip trap recreated by the design's own loser path. Therefore: any failure at step 4+ (an `affected === 0` — kept as the OCC invariant though "theoretically impossible" under lock, a junction `insert()` failure — e.g. `unique(time_slot_id)` from a non-OCC concurrent writer, an audit-insert failure) **THROWS a RuntimeException subtype out of the transaction closure** — the peri-flip catch re-throws the SAME class (never swallowed, never converted, never a non-RuntimeException type that would bypass the action's `catch (\RuntimeException)` and turn a loser into a 500 + uncompensated request row) → the txn (including the validator's own step-3 audit attempt and all staged flips) ROLLS BACK fully — and the **single conflict audit row is re-written AFTER the rollback, in the catch**, outside the transaction (best-effort: if that audit insert itself fails, the re-throw proceeds — the invariant is audit-first-then-throw, not exception-in-exception). The audit table has no transaction coupling; the "conflict-audit survives D5" contract at `SubmitReplacementRequest.php:12–28` still holds because compensation hasn't yet run (and runs after the audit re-write — `compensateLoser` pre-nulls `replacement_request_id` on the SURVIVING audit row, then deletes the request). The re-written audit payload pins to the `auditConflict` shape (round-3 💡): the reason it rolled back on + the failing slot's id/times when known. The audit-then-throw ordering is the named invariant (audit-then-normal-return commits partial flips; audit-then-throw rolls the row back — both forbidden).
5. Junction rows: insert LAST, after all flips verified (one row per slot) — minimizes the insert-failure window.
6. Success audit: **one row per slot flip** (`old_status available`, `new_status pending`, per-slot `time_slot_id`, `details: ['version' => …]`) — round-1 Q4.
7. Return `OCCResult::success(...)` (anchor slot; the junction carries the rest). Defensive guard: `count($locked) === $span` after the lock step (row-gone paranoia; the step-3 status checks already covered it).

`OCCResult` gains optional `failing_start`/`failing_end` (nullable) + a `conflict(reason, ?TimeSlot $slot = null, ?string $failingStart = null, ?string $failingEnd = null)` signature extension (backward compatible: existing 1-arg calls unchanged — round-3 💡 wording fix).

## D4 — `MatrixIntersectionEngine::validateSlot(..., int $span = 1)`

- Existing per-slot vectors stay; the span version evaluates the same vectors for EACH slot in the span (`for k in 0..span-1`: the k-th contiguous slot's start/end — anchor.start + k×30 min, same day).
- `validateSessionType`/`validateWeekNumber`/`validateCohortIds` unchanged (called once).
- Holiday check per slot day (same day) — unchanged semantics.
- Optional hoist (💡, not perf-critical): fetch lecturer/cohort busy vectors once per span instead of per slot — N ≤ 4.
- Day-end fit: implied by contiguity (the resolve step only finds grid slots); the engine's per-slot loops keep the defensive check.

## D5 — `CancelPendingRequest` — junction release (two-phase mirror)

ONE transaction (round-4 🟡 — pinned: all-or-nothing like reservation):

1. Request guards unchanged (`:27–32`).
2. **Resolve the release set:** junction rows of the request (fallback: `[replacement_time_slot_id]` for legacy anchor-only requests — they keep working).
3. **Lock ALL release-set slots ordered** (`WHERE id IN (…) ORDER BY id FOR UPDATE`).
4. **Release all:** per-slot version-checked flip (`pending→available`). **Skipped-slot branch (round-1-verify 🟡3):** a release-set slot that is NOT `pending` (already released/occupied/miraculously flipped) SKIPS the version flip and gets NO flip audit row (mirroring `CancelPendingRequest`'s existing `$slot !== null && $slot->status === 'pending'` guard) — writing `old pending/new available` for an unflipped slot would be a false audit line; junction deletion proceeds regardless.
5. **Delete the junction rows** (the release completes the "receipt" lifecycle).
6. Request → `cancelled` + `decided_at` (unchanged) + the existing summary audit row.

## D6 — Conflict copy + transport (round-2/3/5)

- `conflictText(string $reason, ?string $failingStart = null, ?string $failingEnd = null, ?string $failingKind = null)` (backward compatible) — **routing table (round-3 🔴, pinned):**
  - `slot_not_available` + `failing_kind='occupied'` → named-time copy *"Your {span}-slot ({hours}h) block runs into an existing booking at {start}–{end} — pick another slot."* (row-backed — truthful).
  - `slot_not_available` + `failing_kind='missing'` → bounds-style *"Part of the block falls outside the booking grid — adjust the selection."* — **times dropped** even though the transport carries the derived expected times.
  - `slot_not_available` + no kind + no times → canned *"That slot was just taken."* (unchanged).
  - `span_overlap` → conflictText GAINS this arm (NEW at this change — no prior code exists for it); the named-time copy, interpolating the transported failing times; the token is produced by the validator (D3 step 3, `span > 1`).
  - `invalid_span` → the bounds copy (D1).
  - `reserve_interrupted` → the intervention copy ("The booking could not be completed — try again." family — tasks Round-1 🟡6): the action's peri-flip catch maps the rolled-back txn to this USER-FACING reason; `concurrent_reserve_conflict` stays the POST-rollback forensic AUDIT token only (never user-facing) — the two tokens are surface-specific and never cross-asserted.
  - **Table closure (round-4 🔴): any unlisted combination (including `failing_kind = null` with times present) → the canned fallback** — and the D2 mandatory kind stamp renders the null-kind+times state UNREACHABLE.
- **Transport token (round-3 🔴):** `submitBlocks` result rows carry the reason string + the failing-kind token (`:571–577` row shaping → `conflictText` ≈`:618` → Submission-Conflict modal rows) — without it the kind dies before the copy.
- (the duplicate round-2 block above was consolidated into the routing table — round-4 cleanup)

## D7 — Read-model family (round-4 scope)

1. **Venue page (`VenueTimetable` events query `:255–285`):** pending slots ⟕ junction (one row per reserved slot) ⟕ requests → **group by REQUEST**: one event per request, `start` = anchor's slotIndex, `id` = the ANCHOR slot id (frozen choice — round-3 💡), `end` = `start + junctionCount − 1`, facts from the request's ORIGINAL session (unchanged); orphan defence semantics preserved (a pending slot with NO request/junction link emits nothing — unchanged); the "Amendment 2b" comment becomes span-aware.
2. **Arrangement ownership read (`venueSlotsByWeekPayload` ≈`:326–332`):** the ownership map derives from the junction (`replacement_request_slots` ⟕ pending requests → `proposer_id` per slot id) instead of the anchor column — a span's tails render status 3 "mine" on the owner's grid, 4 "reserved" elsewhere.
3. **`myRequests()` (Api ≈`:135–170`):** derive the span per request from the junction (min/max slot start/end or count) and carry `rt_start_time`/`rt_end_time` as the TRUE range. **Bucket split pinned (round-2 🟡1): the junction read is pending-geometric ONLY — approved AND cancelled keep the legacy anchor join** (cancel deletes the junction rows; a cancelled 4-slot request's junction-min would be NULL, and the history page's legacy `Carbon::parse($slot->start_time)` is a hard failure if the legacy read is dropped for non-pending buckets).
4. **Venue-slots API map (Api ≈`:685–712`):** the cell-marking loop derives per-request spans via the junction (mark `anchor..anchor+span−1`, pending geometry) — the approved bucket keeps the legacy anchor join (D0); splitting the loop narrows the legacy join to **approved-only** explicitly (pending rows must not be marked twice).
5. **My-request-history page (`app/Livewire/MyRequestHistory.php` — `requestRow` ≈`:69`, date/time/venue fields `:103–109`) — round-1-verify 🔴1 (design soft-freeze under the frozen item-8 umbrella "EVERY anchor-keyed consumer"):** pending rows derive `replacementDate`/`replacementTime`/`replacementVenue` from the junction (min/max `start_time`/`end_time` + span count) instead of the anchor attribute — a 4-slot pending request must NOT read "08:00–08:30" on the proposer's own history page. The legacy `replacementTimeSlot` join/attribute is RETAINED for the approved bucket (nothing writes `approved` at HEAD — same retention rationale as D0); the pending eager-loads gain `requestSlots` (on `ReplacementRequest.php::requestSlots()`). Verified non-consumers (approval-change territory): `ResolvesTimetableTimeline:226` + `ApiReadController:453` read anchors ONLY in their approved branches.

## D8 — Picker/budget cap family (grill Q5 + round-2 🟡)

- Both boot clamps: `Math.min(Math.max(hrs, 0.5), 2)` hours → `BLOCK_SPAN = Math.round(hrs * 2)` ≤ 4 (keep the floor; a 3h `&duration=` shrinks to 2h).
- Resize clamp (`slotSpan`, blade ≈`:3143`): `Math.min(…, 4)` slots.
- `MAX_SELECTION = Math.max(BLOCK_SPAN, 4)` → constant 4 under the cap (the multi-week budget = total slots; a 1h class can still book twice; a 2h block uses the whole budget). Comments updated ("the global budget stays 4").
- **Stale comments riding the edits** (`≈:3395–3397` "clamped to 0.5–4h" → 0.5–2h, and `≈:3134` "same clamp family as the URL branch (0.5–4 h → ≤8" → 0.5–2h / ≤4 — it sits directly on the edited resize clamp); the `≈:1187–1189` block describes the MAX_SELECTION/BLOCK_SPAN defaults ("4 slots (2 hours)") and is already cap-true (optional tidy).
- **OUT of the family (pinned round-2):** the banner anchor clamp (≈`:2373`) — display-only of the original class's true length; a non-FOCS 3h original must NOT lie.

## D9 — Tests (grill Q7 — `class_replacement_testing` only)

- `tests/Feature/OccConcurrencyTest.php` extends: 4-slot winner (exactly 4 junction rows; all slots pending), mid-span race loser (`span_overlap` as `conflict_reason` + `failing_kind='occupied'`; ZERO slots pending, anchor available — the partial-flip trap test; single conflict audit row carrying the `span_overlap` token + the failing slot's id + times in details — round-4 🔴 binding), ordered-lock success. **Byte-compat mirror: a span-1 race still surfaces `slot_not_available`** (the validator's default path untouched).
- New span feature test: bounds (**exact out-of-bounds values: derived span 0, negative, and 6 (the 3h ask) → `invalid_span`; a LEGAL span of 3 (1.5h) must PASS** — round-2 🟡3; no write/no audit row on rejects), non-contiguous/missing mid-slot: reason `slot_not_available` (frozen item-10 string) **+ `failing_kind='missing'` + the derived expected `failing_start` present in the keys + the rendered bounds-style copy; the paired occupancy assertion: occupied mid-span → the named-time copy + `failing_kind='occupied'`; byte-compatibility: every non-slot-availability path returns `failing_kind = null`** (round-3 🔴), `span_overlap` named-time assertion via the action's return keys (`span_overlap` + `failing_kind='occupied'` + `failing_start`/`failing_end` present — same key family as the race test, one token, round-4 🔴), cancel-releases-N (junction rows gone, slots available, version++ each, per-slot audit rows), legacy anchor-only cancel keeps working (test-constructed only — note: at HEAD nothing creates anchor-only pendings anymore; the demo DB holds zero requests).
- Engine: span-vector tests (busy at tail slot → whole submit rejected pre-reserve); `validateSlot` default-1 callers byte-unchanged.
- Read-model: venue page one-coalesced-amber-span (4-slot pending request); arrangement ownership (owner sees 3s, others see 4s; post-cancel free everywhere); **`MyRequestHistory` pending rows show the true 2h range (junction-derived); post-cancel the row correctly reflects the ANCHOR-ONLY read (release deletes the junction rows — the frozen AC-4 fold); a cancelled span request renders the canned/anchor read per the bucket split (round-2 🔴1)**; `myRequests()` true range (pending) + the cancelled bucket keeps the anchor join (round-2 🟡1 assertion); API map marks the full span.
- PHPUnit suite ≥ 178 green (re-derive the baseline at apply); Playwright: `tests/ui-regression.spec.ts` **TC-1.4** re-pointed ("of 4", the designed contract change); all other suites regress-green — **remember the TC70 retirement guard**: TC70 must never execute before retirement (`--grep-invert "TC70"` on any full-suite run, per the crs-workspace rules).
- **Step-4/5 rollback-path test (round-1-verify 🔴2):** the mid-span loser test asserts zero junction rows AND zero `pending` slots after a peri-flip failure too — the `unique(time_slot_id)` violation is injectable in the testing DB for step 5; the step-5 forensic token is pinned `concurrent_reserve_conflict` for ALL throw-causes (round-5 fold — `details.conflict_reason` is permanent history; without it the audit row has no natural token); a genuine step-4 flip-under-lock is unreachable by construction (`lockForUpdate` + status re-checks — exercised by the throw contract + code-path review, noted so verify doesn't hunt an impossible fixture). The peri-flip test adds (round-2 🟡2): the compensated request row is GONE, the re-written conflict audit row SURVIVES with `replacement_request_id` NULLed (FR 4.12), and the surfaced loser reason is a non-500 failure (`reserve_interrupted` action-side shape; the action-side `reserve_interrupted` vs audit-side `concurrent_reserve_conflict` split is pre-existing, left as-is, test-pinned).

## Dependencies

Migration → `ReplacementRequestSlot` model → OCC/two-phase → action span flow → conflict copy → read-model family (VenueTimetable + ReplacementArrangement ownership + ApiReadController×2 + **MyRequestHistory**) → cap family → tests. The blade cap family is independent of the backend (can land in the same commit set as the read-model).

## Commit split (planned)

`feat:` migration + action/OCC/engine/cancel (backend write path) · `feat:`/`fix:` submitBlocks + conflictText + read-model family (including `MyRequestHistory`'s pending-row span read) · `ui:` cap family + comment updates · `test:` suites · `docs:` changelogs (arrangement + venue + my-request-history). Never push without the user's explicit say-so.
