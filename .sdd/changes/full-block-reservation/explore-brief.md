# Explore Brief: full-block-reservation (Fix 3 + U5a)

**Status:** exploration complete 2026-10-13 (read-only; no code touched). Ready for `/sdd-propose full-block-reservation` whenever the sdd-reviewer agent is back.
**Origin:** bug-hunt Fix 3 (user-reported) + U5a canonical-span decision (absorbed here by user's earlier sequencing decision).
**Absorbs:** U5a (canonical span). **Defers to a separate future change:** consumer-side honest copy for matrix-ineligible booked venues ("is conflicted" wording, found 2026-10-13 during U2a verification) — touches the same consumer flow but is copy-only; decide at propose time whether to fold in.

## The hole (all confirmed live at HEAD, with evidence)

A 2h (4×30-min) replacement reserves and renders as ONE 30-min slot — the other 3 slots stay legally bookable by others. Live instance: req #1, B110 W4 Thu 12:00 (slot 27309 held; 12:30–14:00 free to anyone).

| Layer | Evidence | Behavior today |
|---|---|---|
| Submit | `app/Actions/SubmitReplacementRequest.php:33,:76` | reserves ONLY `anchorSlotId` via one `validateAndReserve()` |
| OCC validator | `app/Services/OCCValidator.php:21–67` | single-slot txn: lock 1 row → status check → version-checked flip `available→pending` → 1 audit row |
| Cancel | `app/Actions/CancelPendingRequest.php:33–47` | releases ONLY `replacement_time_slot_id` |
| Engine pre-check | `app/Services/MatrixIntersectionEngine.php:172–212` | `validateSlot()` validates ONLY the anchor cell (lecturer/cohort/venue vectors over 1 slot) |
| Client payload | `app/Livewire/ReplacementArrangement.php:482–550` | `submitBlocks` receives `{week, day, startHour, venue}` — **no span field; server never learns the block size** |
| Client knowledge | `replacement-arrangement.blade.php:1187–1195, :2912–2915, :3161` | `BLOCK_SPAN` derived from `&duration=` URL param (hrs×2), default 4; **user may explicitly resize the block up to 8 slots** in the slot panel (frozen UX) |
| Schema | `2026_08_03_000009_create_replacement_requests_table.php:21` | single `replacement_time_slot_id` FK; **no span/duration column** |
| Venue read-model | `app/Livewire/VenueTimetable.php:183–228` | one amber event per pending slot row, keyed to its request via `replacement_time_slot_id` |
| Slot protection | `2026_08_03_000006:37–41` | partial unique index per slot `(venue, day, start, week) WHERE status IN ('pending','occupied')` — protects individual slots, no span awareness |

### 🔴 The trap a naive fix walks into
`VenueTimetable.php:194–200` — **orphan defence**: a `pending` slot with no request row → *emit nothing*. If full-span reservation flips slots 2..N to `pending` without a link back to the request, the venue page would render those slots as neither amber nor occupied → they fall through to the free-cell branch → **post-U2a they render as BOOKABLE `cell-available`**. The fix MUST give every reserved slot a request linkage, or it moves the double-book hole from the DB into the UI.

## Recommended fix shape (proposal-ready)

1. **Junction table `replacement_request_slots`** (additive migration): `(replacement_request_id FK cascade, time_slot_id FK, unique(time_slot_id), timestamps)`. Every reserved slot FKs its request; the legacy anchor column stays (backward compat + existing rows). Makes cancel exact, approval-ready (future D7), and the venue read-model trivial.
2. **Client sends the span**: add `span` (slots, 1–8) to the block payload in `submitBlocks`; server validates `1 ≤ span ≤ 8`, integer.
3. **`SubmitReplacementRequest` gains a span parameter**: resolve the N contiguous slots (anchor + next start-times same venue/day/week), reserve all-or-nothing in ONE transaction with `SELECT … WHERE id IN (…) ORDER BY id ASC FOR UPDATE` (ordered locking → no deadlock), per-slot status check + version-checked flip, junction rows created, audit rows per flip (schema's `time_slot_id` stays populated per row; span carried in `details`). Loser path: txn atomic → nothing to compensate beyond the existing D5 request delete.
4. **Engine validates the whole span**: extend `validateSlot()` with an optional `int $span = 1` (backwards-compatible) — anchor..anchor+span−1 all `available`, lecturer/cohort busy vectors checked across the full span, venue vector unchanged (one venue), plus a day-end fit check (`anchor.end + (span−1)×30min ≤ day end`).
5. **`CancelPendingRequest` releases the span** via the junction (ordered by slot id, per-slot version++), audited. Orphan defence keeps its safety net.
6. **Venue page second pass** (`VenueTimetable.php:183–228`): key the request join through the junction; emit one event per reserved slot (existing per-slot shape) → `mergeTwinEvents` coalesces them into one amber span block (the "defensive parity" merge already in place). Update the stale "exactly ONE anchor half-hour slot (spec Amendment 2b)" comment.
7. **Arrangement grid pending blocks**: per-slot client data (`venueSlotData`) — contiguous reserved slots render contiguous amber automatically; verify only.
8. **Existing demo-DB rows: untouched.** No junction rows ⇒ legacy requests read as anchor-only (their amber block stays 30-min until cancelled). Req #1–#3 are the user's real pending requests — **never touch** (standing rule; no backfill, no migration of live rows).
9. **Tests**: extend `tests/Feature/OccConcurrencyTest.php` (multi-slot race: A reserves span while B targets a mid-span slot → B loses with `slot_not_available`; ordered-lock path), span-validation feature tests (bounds, non-contiguous/nonexistent slot), cancel-releases-N, engine span-vector tests (busy at tail slot → whole submit rejected), venue read-model span rendering.

## U5a decision absorbed (canonical span)

**Span = the lecturer's explicit selection, validated and persisted server-side; the original session's duration is only the UI default.**
- NOT server-re-derived from `class_sessions` duration — the frozen slot panel lets the lecturer resize the block explicitly (`:2912–2915`, up to 8 slots); silently overriding that selection would break the frozen UX (99.9% rule).
- The client already defaults `BLOCK_SPAN` from `&duration=` (original class length) and locks auto-picks against resizing (`urlSpanLocked :1195`) — the desired default behavior exists; the server's job is to validate bounds + vector-check + persist the actual span.
- Persisted span = the junction rows (source of truth); no span column needed on `replacement_requests`.

## Open design questions for /sdd-propose

1. Junction table vs derived span (recommended above: junction; the alternative — deriving release-set by contiguous-scan heuristics — works but is implicit and breaks the venue orphan defence anyway).
2. `validateSlot($span)` extension vs new `validateSpan()` method (recommend extension with default 1 — callers unchanged, OCC test untouched).
3. Audit granularity: one row per slot flip (keeps `time_slot_id` populated; N rows for N slots) vs one span row with `details.span` (recommend per-row for audit-trail fidelity with the existing schema; conflict rows carry span in `details`).
4. Max span bound: client caps at 8 slots (4h) — mirror exactly? Also day-end fit server-side (client already rejects near-run-end via `canPlaceBlock :1534`).
5. Whether to fold in the matrix-ineligible booked-venue honest copy ("is conflicted" → room-type reason) — same consumer flow, copy-only; separate change keeps this one single-purpose.
6. Conventional commit prefix: `feat:` (backend write-path) + `ui:` (venue read-model comment/render) — likely split commits.

## Sequencing notes

- No hard dependency on `venue-page-book-affordance` (U2a) — different files mostly (`VenueTimetable.php` vs the blade script); both touch the venue page's *rendering* vocabulary, so landing U2a first avoids merge friction in the blade.
- Server on :8000; demo DB never re-seeded (req #1–#3 must survive every test run — the new tests must create their own fixtures in the testing DB only, the `OccConcurrencyTest` pattern).
- `/sdd-propose full-block-reservation` blocked until the sdd-reviewer agent recovers (same gate as U2a's pending confirmation rounds).
