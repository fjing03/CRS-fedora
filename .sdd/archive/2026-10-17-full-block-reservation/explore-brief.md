# Explore Brief: full-block-reservation

**Status:** re-verified + grilling complete 2026-10-16 (supersedes the 2026-10-13 round; read-only — no code touched). Ready for `/sdd-propose full-block-reservation`.
**Origin:** bug-hunt Fix 3 (user-reported span hole) + U5a canonical-span decision. **Prev blockers resolved:** U2a (venue-page book affordance) and `arrange-intent-session-choice` both landed + archived (2026-10-16) — no merge friction remains; the deferred "is conflicted consumer copy" item was RETIRED inside the archived change (T8 five-branch copy set) and is OUT of this change's scope (grill Q1).

## The hole — re-verified live at HEAD 2026-10-16

A 2h (4×30-min) replacement reserves and renders as ONE 30-min slot — the other 3 stay legally bookable.

| Layer | Current evidence (2026-10-16 line refs) | Behavior today |
|---|---|---|
| Submit action | `app/Actions/SubmitReplacementRequest.php:33` (`__invoke(..., int $anchorSlotId, ...)` → `:60` engine single-slot check → `:76` `occ->validateAndReserve($anchorSlotId, …)`) | reserves ONLY the anchor slot |
| OCC validator | `app/Services/OCCValidator.php:21–67` (`validateAndReserve` single-slot txn; `auditConflict` `:69`) | lock 1 row → status check → version-checked flip → 1 audit row |
| Cancel | `app/Actions/CancelPendingRequest.php:33` (`whereKey($request->replacement_time_slot_id)`) | releases ONLY the single linked slot |
| Engine | `app/Services/MatrixIntersectionEngine.php:172` (`validateSlot(...)` — no span param) | anchor cell only |
| Client payload | `submitBlocks` `app/Livewire/ReplacementArrangement.php:461–590`; **client already sends `endHour` per block** (blade `proceed()` `blocks.push({ venue, week, day, startHour, endHour })`) | **server ignores `endHour`**: resolves ONE slot at `startHour` (`Carbon 08:00 + startHour×30`) and submits single |
| Client selection model | blade `selectedBlock {day, startHour, endHour}` + per-week memory (`selectedSlotsByVenue`) | the span facts live client-side already |
| Schema | `2026_08_03_000009_...:21` single `replacement_time_slot_id` FK | no span model |
| Unique protection | `2026_08_03_000006_*` partial unique per slot `(venue, day, start, week) WHERE status IN ('pending','occupied')` | per-slot only |
| Venue read-model | `app/Livewire/VenueTimetable.php:255–285` — pending slots joined via `replacement_time_slot_id`; **orphan defence `:274–279`** (pending slot with no active request → emit NOTHING) | single amber 30-min event per pending request |

**🔴 The trap (unchanged, drives the design):** if span reservation flips slots 2..N to `pending` without a request linkage, the orphan defence emits nothing → post-U2a those cells render as BOOKABLE `cell-available` — the double-book hole would move from the DB into the UI. Every reserved slot MUST link back to its request.

## Grilling decisions (2026-10-16, one at a time with the user — ALL RESOLVED)

| # | Question | Decision |
|---|---|---|
| Q1 | Scope confirm | Brief ①–⑦ minus the moot copy deferral; span read from the **already-sent `endHour`** (no new client field — zero client payload change) |
| Q2 | Span bookkeeping | **A: junction table** `replacement_request_slots` (additive migration: `replacement_request_id` FK cascade, `time_slot_id` FK unique, timestamps). Legacy anchor column stays (backward compat). |
| Q3 | Engine checker | **A: extend `validateSlot()` with optional `int $span = 1`** — one rule set, callers unchanged |
| Q4 | Audit granularity | **A: one audit row per slot flip** (each carries its own `time_slot_id`; the log table is currently UI-invisible but display-friendly via the junction) |
| Q5 | Max span | **FOCS scope: 4 slots = 2 hours max, server-enforced + day-end fit check;** the arrangement slot-picker **also caps at 4** (no dead-end picks). Non-FOCS 3h classes out of scope. |
| Q6 | Legacy demo-DB rows | **Moot — verified live: the demo DB holds 0 requests, 0 audit rows, 0 reserved slots** (the old req #1–3 are gone; user asked to delete them, nothing existed). No backfill; clean slate. |
| Q7 | Tests | Approved — disposable `class_replacement_testing` DB only (multi-slot race, span bounds, non-contiguous, cancel-releases-N, engine span vectors, venue span rendering) |
| Q8 | Race-lose copy | **B: specific message naming the blocked time** — e.g. *"Your 2-hour block runs into an existing booking at 12:30–13:00 — pick another slot."* OCC result must carry the failing slot's time; a `conflictText` branch (`span_overlap`) renders it in the existing Submission-Conflict modal list |
| Q9 | Approval flow | **Out of scope** — future D7 reuses the release logic; no approve UI now |

## Fix shape (proposal-ready, updated)

1. **Junction table `replacement_request_slots`** (additive migration): `replacement_request_id` FK cascade, `time_slot_id` FK **unique**, timestamps. Every reserved slot FKs its request; legacy anchor column stays.
2. **Span source = the already-sent `endHour`.** Server: `span = clamp(endHour − startHour, 1, 4)`; integer + bounds + contiguity validated server-side (never trust the client beyond validation).
3. **`SubmitReplacementRequest(..., int $span = 1)`**: resolve the N contiguous slots (anchor + next start-times, same venue/day/week), reserve all-or-nothing in ONE transaction (`SELECT … WHERE id IN (…) FOR UPDATE` ordered by id → no deadlock ordering trap), per-slot status check + version-checked flip, junction rows, **audit row per flip**. Record the FIRST failing slot's time for the `span_overlap` message on partial failure (txn atomic → nothing to compensate beyond the existing D5 request delete).
4. **Engine `validateSlot(..., int $span = 1)`**: anchor..anchor+span−1 all `available`; lecturer/cohort busy vectors across the full span; venue vector unchanged; day-end fit server check.
5. **`CancelPendingRequest` releases the span via the junction** (ordered slot release, per-slot version++, audited). Orphan defence stays as the safety net.
6. **Venue read-model** keys the pending join through the junction; one event per reserved slot (existing per-slot shape) → `mergeTwinEvents` coalesces into one amber span block. Update the stale "exactly ONE anchor half-hour slot (spec Amendment 2b)" comment (`VenueTimetable.php:255–259`).
7. **Arrangement grid ownership read — WITHDRAWN "verify only" (round-4 🔴1):** `venueSlotsByWeekPayload`'s pending ownership map is ANCHOR-keyed; a span's tail slots render as "someone ELSE's" even on the owner's grid — the junction-derived ownership read is IN scope (item 8 of the proposal).
8. **Conflict message**: new `span_overlap` branch in `conflictText` + OCC result carries the failing slot's start/end (Q8).
9. **Slot picker cap = 4** (client, so the budget UI matches the server bound; Q5).
10. **Tests** (Q7, testing DB only): extend `tests/Feature/OccConcurrencyTest.php` (A reserves span while B targets a mid-span slot → B loses with the named time; ordered-lock path), span bounds (0, 5, non-int, non-contiguous/nonexistent slots → reject), cancel-releases-N, engine span-vectors (busy tail slot → whole submit rejected), venue read-model span rendering (junction rows → one coalesced amber span).

## U5a (canonical span) — holds

Span = the lecturer's explicit selection, validated + persisted server-side (the junction IS the persisted span). The original session's duration is only the UI default; a manual pick may resize within the 4-slot cap. The client defaults `BLOCK_SPAN` from `&duration=` and locks auto-picks (`urlSpanLocked`) — existing frozen UX.

## Rejected approaches (WHY)

- Derived-span heuristics (contiguous scan, no junction): implicit, fragile, breaks the orphan-defence flow (Q2 B).
- Separate `validateSpan()` method: two rule sets to keep in sync — drift risk (Q3 B).
- One audit row per submit with `details.span`: loses per-slot `time_slot_id` keys the log format always carried (Q4 B).
- 8-slot/4h max (client) or no max: FOCS classes run ≤2h; a hacked request could lock arbitrary runs (Q5 B).
- Legacy backfill: moot (nothing to backfill) + standing no-touch rule (Q6).
- Generic "slot not available" for span races: user explicitly wants the blocked time named (Q8 B).

## Key cross-module data flows

- `proceed()` → `submitBlocks(blocks[{venue,week,day,startHour,endHour}], code, orig, context{sessionId,week})` → component derives `span` per block → duplicate/holiday pre-checks (unchanged) → `SubmitReplacementRequest(user, session, requestWeek, anchorSlotId, span)` → `MatrixIntersectionEngine::validateSlot(anchor,…, span)` → `OCCValidator::validateAndReserve(span)` (txn, ordered locks, junction rows, audit rows) → results[] (`ok`,`conflict_reason`,+ failing slot time for `span_overlap`) → client Submission-Conflict modal rows (existing).
- Cancel: `/my-request-history-ui` → `CancelPendingRequest` → junction release loop → slots `available`, versions++ → venue page + arrangement grids re-render as free.
- Venue read-model: pending slots ⟕ `replacement_request_slots` ⟕ requests → per-slot events → `mergeTwinEvents` → one amber span.
- Open for propose: exact line anchors re-derived (brief line refs already refreshed 2026-10-16); `span_max` constant placement (service const vs config) — decide at propose; commit split `feat:` (backend) + `ui:` (venue read-model) + `test:` (`docs:` changelogs).
