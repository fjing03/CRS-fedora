# Tasks: full-block-reservation

All tasks ≤ 2h. Baselines: `proposal.md` FROZEN (5 rounds) · `design.md` FROZEN (5 rounds) · `specs/span-reservation/spec.md` FROZEN (1 round).

> **⛔ GUARDS:** demo DB (`class_replacement_fresh`) never re-seeded/migrated — ALL phpunit fixtures in `class_replacement_testing` only (fresh+seed allowed); **no destructive write-path E2E against the demo DB**; TC70 must NEVER execute before retirement (`--grep-invert "TC70"`); stale-cache ritual (two separate commands) after blade edits; gates = `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G` + `php artisan test`; conventional commits; never push without the user's explicit say-so; note: "D5" in code comments = the compensation helper in `SubmitReplacementRequest` (not design section D5).

## Phase 1 — Schema + models

- [x] **T1 — Migration `2026_10_16_000001_create_replacement_request_slots_table.php`** (D0/spec R1.2): `id`, `replacement_request_id` FK cascade, `time_slot_id` FK cascade + **UNIQUE**, timestamps. `php artisan migrate` on the TESTING DB only; the demo DB migrates later with the user's go (it holds zero rows — verified 2026-10-16). Feature-test the cascade (request delete → rows gone) + the unique (double-link rejected).
- [x] **T2 — `App\Models\ReplacementRequestSlot` model + `ReplacementRequest::requestSlots(): hasMany`** (additive; fillables = the two FKs; timestamps).

## Phase 2 — Write path

> **EXECUTION ORDER (tasks Round-1 🔴1): T3 → T6 → T7 → T4 → T5.** The T-numbers keep their D-section mapping (T4 = design D2 action flow, T5 = design D3 OCC two-phase, T6 = OCCResult, T7 = design D4 engine span). Extensions run BEFORE their consumers: T6 (the OCCResult extension) → T7 (the span-aware engine) → T4 (the action consumes BOTH) → T5 (the OCC two-phase consumes the T6 signature).

- [x] **T3 — `submitBlocks` span derivation + bounds** (D1/proposal item 2): per block `rawSpan = (int)endHour − (int)startHour`; `< 1 || > 4` → `invalid_span` loser **before** the duplicate/holiday pre-checks (no write/no audit); order the bounds check first (round-5 fold); thread the failing kind through results (D6 transport: reason + kind token — **kind threading continued in T9**).
- [x] **T4 — `SubmitReplacementRequest::__invoke(..., int $span = 1)`** (D2):
  - step 2: the action-level span guard (`span < 1 || span > 4` → `invalid_span`, no write/audit — covers direct callers);
  - step 3: span pre-resolve — query `start_time (+id)` ordered; count < span → `slot_not_available` + derived expected time + `failing_kind='missing'`; non-`available` → + `failing_kind='occupied'`; engine `validateSlot(..., $span)` pre-check across the span.
  - return shape: `['ok', 'conflict_reason', 'request_id', 'failing_start', 'failing_end', 'failing_kind', 'holder_start', 'holder_end']` — request_id preserved; the holder keys resolve ONCE per failing occupied slot (live junction lookup — user amendment).
- [x] **T5 — `OCCValidator::validateAndReserve(..., int $span = 1)` two-phase** (D3/R1): ordered `WHERE id IN (…) ORDER BY id FOR UPDATE` lock of ALL span rows + the `count($locked) === $span` defensive guard → check ALL statuses (adapted token: span 1 → `slot_not_available`; span > 1 → `span_overlap`) → single conflict audit row (token + failing slot id/times in details) → only then flip ALL (version-checked) → junction inserts LAST → per-slot success audits. Peri-flip branch: THROW a RuntimeException subtype; the catch re-throws unchanged; the conflict audit re-write happens AFTER the rollback (best-effort, `concurrent_reserve_conflict` forensic token).
- [x] **T6 — `OCCResult` extension** (round-2 🟡): `failing_start`/`failing_end` nullable fields + `conflict(reason, ?TimeSlot $slot = null, ?string $failingStart = null, ?string $failingEnd = null)`; NO kind param (the kind stamps action-side). Existing 1-arg calls byte-identical.
- [x] **T7 — `MatrixIntersectionEngine::validateSlot(..., int $span = 1)`** (D4/R3): per-slot vector evaluation across the span (busy vectors + holiday per slot); optional busy-vector hoist (💡 — N ≤ 4); default-1 callers byte-identical.

## Phase 3 — Release

- [x] **T8 — `CancelPendingRequest` junction release** (D5/R2): ONE transaction; resolve the release set (junction rows; legacy fallback `[anchor]`); lock ordered; per-slot version-checked flip + flip audit rows ONLY for slots that were `pending` (skip = no false rows); DELETE the junction rows; request `cancelled` + decided_at + the existing summary audit row.

## Phase 4 — Copy + transport

- [x] **T9 — `conflictText` 4-arg routing table** (D6/R4): the frozen branches — occupied → named-time; missing → bounds-style (times dropped); no-kind+no-times → canned; `span_overlap` → named-time + the HOLDER-CONTEXT suffix (transported `holder_start`/`holder_end`; cell-only fallback when unresolvable — user amendment 2026-10-16); NEW `reserve_interrupted` branch (tasks Round-2 🔴1, mirroring the design-D6 soft-freeze: the action's peri-flip catch maps the rolled-back txn to this USER-FACING reason; `concurrent_reserve_conflict` stays the post-rollback forensic AUDIT token only — surface-specific, never cross-asserted).
  - `invalid_span` new branch; unlisted → generic. `submitBlocks` carries reason + kind token into result rows; the action's `! $result->success` branch stamps `failing_kind = 'occupied'` UNCONDITIONALLY on OCC-race losers (copies the times).

## Phase 5 — Read-model family

- [x] **T10 — Venue page events** (D7.1/R5.1): pending join through the junction → ONE event per REQUEST (id = anchor slot id, `end = start + span − 1`); the "Amendment 2b" comment becomes span-aware; orphan defence untouched; `mergeTwinEvents` still runs (defensive parity).
- [x] **T11 — Arrangement ownership read** (D7.2/R5.2): `venueSlotsByWeekPayload` derives owner per slot from the junction (pending-geometric ONLY — approved/cancelled keep legacy anchor reads); statuses 3 (mine) / 4 (else's) correct for span tails.
- [x] **T12 — `ApiReadController`** (D7.3–4/R5.3–4): `myRequests()` — pending rows get the junction-derived true range; approved/cancelled keep the legacy anchor join; the venue-slots API map — pending rows mark `anchor..anchor+span−1`, the legacy join narrowed approved-only (no double-marking).
- [x] **T13 — `MyRequestHistory`** (D7.5/R5.5): pending rows derive `replacementDate/Time/Venue` from the junction (eager-loads gain `requestSlots`); legacy `replacementTimeSlot` retained for approved/cancelled; no `Carbon::parse` hazard.

## Phase 6 — Cap family (blade)

- [x] **T14 — Arrangement caps = 4** (D8/R6): both boot clamps `Math.min(Math.max(hrs, 0.5), 2)` hours; resize clamp 4 slots; `MAX_SELECTION` comment ("the global budget stays 4"); the two stale comments reword (`≈:3395–3397`, `≈:3134`); banner clamp `≈:2373` UNTOUCHED (display-only). Stale-cache ritual after.

## Phase 7 — Tests

- [x] **T15a — Bounds suite** (R7.5, R4.1) *(verify addendum 2026-10-17: the lettered T15 contracts now EXIST as code — `tests/Feature/SpanReservationTest.php`, 12 tests)*: derived 0 / negative / 6 → `invalid_span`, span-3 (1.5h) legal, BOTH surfaces (submitBlocks + direct action call), no write / no audit on rejects, the `invalid_span` **rendered bounds copy**. (🟡3 fold: neither `span_overlap` nor `invalid_span` ever carries a kind — the D2 stamp's coverage pinned here.)
- [x] **T15b — Winner + race double-arm** (R7.1–2): 4-slot winner (4 junction rows / 4 flips / 4 audits); the race harness runs the R7.2 three-part shape TWICE — span-1 `slot_not_available` byte-mirror arm (+ rendered named-time copy) and span>1 `span_overlap` arm + the holder-context suffix assertion; winner-untouched + either-loser compensation (request deleted, ZERO flips/junction/flip-audits, audit survives id-nulled, A's span untouched). Token duality pinned per arm (🟡6): the two tokens are mutually exclusive at the validator mint point; NO `reserve_interrupted`/`concurrent_reserve_conflict` markers in race outcomes.
- [x] **T15c — Steady-state pair + kind discipline** (R7.3 + the R7.9 byte-compat clause, D9, D2 step 3): missing-mid → `failing_kind='missing'` + derived expected `failing_start` + bounds-style copy (times dropped); occupied-mid → `'occupied'` + named-time copy + holder-context assertion; the null-kind byte-compat sweep over every non-slot-availability path. (🟡2 fold.)
- [x] **T15d — Peri-flip rollback** (R7.4): injectable `unique(time_slot_id)` at step 5; throw-out-of-txn contract → request row GONE, conflict audit survives `replacement_request_id` NULLed, non-500 `reserve_interrupted` surfaced + the `concurrent_reserve_conflict` forensic token in the audit details; the "step-4 unreachable by construction" code-path note for verify. (🟡6's both-pins wording.)
- [x] **T15e — Cancel family + engine** (R3.1 + R7.9): cancel-releases-N (4 slots available, version++ each, per-slot audits, junction rows deleted); legacy anchor-only cancel fixture (byte-identical, test-constructed); engine span vectors (busy TAIL slot → whole submit rejected pre-reserve) + default-1 caller byte-identical. (🟡4 fold.)
- [x] **T16a — Read-model tests (Livewire surfaces)** (D9/R7): venue one-amber-span; arrangement owner statuses (mine/else) + post-cancel free.
- [x] **T16b — Read-model tests (API + history)** (D9/R7) *(verify addendum 2026-10-17: landed in `SpanReservationTest` — API `myRequests()` junction TRUE range + history pending TRUE range / cancel-reverts-to-anchor; the API route itself stays ORPHANED per debt-api-routes, the controller is invoked directly)*: `myRequests()` ranges (pending junction / cancelled anchor); API map full-span marking; history page true-range pending + anchor-only post-cancel.
- [x] **T17 — Playwright:** `tests/ui-regression.spec.ts` **TC-1.4** re-pointed to the 4-cap ("of 4", the DESIGNED contract change — changelog decision-note) **+ the auto-shrink assertion folded (tasks Round-1 🟡6): a `&duration=3` handoff derives span 4 (2h) — asserted in TC-1.4's update itself**; run the venue fleet + arrangement suites with the TC70 grep-invert guard; everything else regress-green (no other spec pins a >4-slot contract — the verify verdict is recorded in T18's gate result).

## Phase 8 — Gates + close-out

- [x] **T18 — Gates.** (per-phase quick `composer run lint:check`+phpstan checkpoints deliberate: consolidated here at close-out rather than per-task, plus per-task `php -l` where useful.) Final: `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G` + `php artisan test` (≥ 178; re-derive the baseline count at apply) + Playwright suites per T17. Demo DB untouched all along (status checks via tinker when in doubt).
- [x] **T19 — Docs + SDD closure.** Changelogs: `page-changelogs/replacement-arrangement-changelog.md` (picker cap + span_overlap copy + TC-1.4 decision-note), `page-changelogs/venue-timetable-ui-changelog.md` (one-event-per-request amber spans), the my-request-history page's changelog (span display); `docs/` OCC-contract note if it mentions single slots. Implementer completion note in `review-log.md`; `change-status.sh`; report to user → `/sdd-verify` → suggest `/sdd-archive`.
