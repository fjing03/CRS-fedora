
## Proposal Round 1 — 2026-10-15 (sdd-reviewer via pi subprocess)

**VERDICT: BLOCKED** — 4 🔴 (all verified against source by the reviewer; full transcript: review-transcript-proposal-r1.md).
🔴1 venue-book U2a-T3 absent from test impact while "stays green" claimed (modal interposition is the designed contract change) → FIXED: scope item 6 + impact + SC6 now state venue-book evolves (T3 passes through the modal) by design.
🔴2 fallback prefill lacked occurrence binding (no pin → applySubjectChange binds module's FIRST session — type-incompatible for mixed modules) → FIXED: fallback sets `window.pinnedOccurrence` client-side (mirrors :2922–2924 pin path) before subject bind; impact scope now names the real consumer-JS bridge.
🔴3 duration × day-end false cause ("a block already holds it" for a span-fit failure) → FIXED: modal adds the duration-fit filter (compat = type + B005 + span-fit to day end); zero-compatible message gains the duration cause; consumer copy set gains a dedicated day-end branch; fallback uses the same symmetric rule.
🔴4 brief open-question 3 (occurrence label) un-carried → carried as an explicit USER DECISION with proposed default "{module} ({type}) · {day} {HH:MM}" — pending user confirmation before round 2.
🟡: brief↔proposal URL 7/8-param reconciliation → brief appended declaratively; `week` semantics pinned (replaced-occurrence pair + existing picker overwrite rule); cisco vocabulary normalization → design obligation; attribution-note host pinned (existing toast).
💡: cohort param parsed-but-unused wording fixed; matrix single-source pressure on ui-common reconciled (design-revisit clause); "2nd duplication" wording corrected (override of 3-rule under explicit mandate); spacedCohortLabel triplication noted as adjacent optional promotion (out of scope unless user opts in at design).
### 🔴 Outstanding
- (none pending code — 🔴4 awaits the user's label-format confirmation; round 2 re-reviews with it baked in.)

## Proposal Round 2 — 2026-10-15 (sdd-reviewer via pi subprocess)
**VERDICT: BLOCKED — 1 🔴.** All round-1 🔴 verified genuinely fixed (mechanism-level, source-grounded); the revision's transcription of the user-decided occurrence label dropped `W{n}` from the template + example (user-frozen verbatim includes "· W5,"). → FIXED declaratively; round-2 🟡s also landed: payload fact list gains day-of-week/start/end (label builder needs them; NOT in today's coursesPayload) + sessions emitted as an ARRAY ordered `(day_of_week, start_time, id)` ("payload session order" pinned for preselection + fallback symmetry); fallback's `intentWeek` explicit 0-based (same conversion family as the producer handoff). Transcript: review-transcript-proposal-r2.md.

## Proposal Round 3 — 2026-10-15 (sdd-reviewer via pi subprocess)
**VERDICT: READY TO FREEZE — no 🔴.** All round-2 fixes verified surgical and coherent; ordering tuple total (id tiebreaker); both 0↔1-based conversions stated symmetrically; week-semantics/duration-fit/pin-bridge intact. Residual 🟡 carried to design.md: the label's `{DD Mon YYYY}` derives from day-of-week × booked-week base date (VenueTimetable already computes it) — pin one source in design (or emit the date). 💡: append round-2 log entry (done); design notes `day_of_week` encoding. Transcript: review-transcript-proposal-r3.md. (Shell-note: a backtick in the round-3 prompt was command-substituted — cosmetic; the reviewer's output shows no impact.)

**PROPOSAL FROZEN — 2026-10-15 (3 rounds; 0 outstanding).**

## Design Round 1 — 2026-10-15 (sdd-reviewer via pi subprocess)
**VERDICT: BLOCKED — 🔴1 fallback span/sticky ordering (evaluateBookingFit runs inside applyUrlParams before the fallback; consumeBookingIntent short-circuits on the stale flag; 1h-session false day-end cause); 🔴2 D1 sessions-shape contradiction (array vs id-keyed map at :2341/:2700); 🟡 unfrozen consumer banner string; 🟡 ruleMap raw-vs-display; 🟡 double-jump test unnamed.** → FIXED: banner string resolved by USER DECISION (reuse producer zero-compatible wording verbatim on the banner); ruleMap designed over RAW encodings consumed as data + `venueEligible` untouched (withdrawal); double-jump test named in D4; also fixed 💡s (wire-call return shape, canonical bookVenue signature, title/buttons restated, module-order pinning). Transcript: review-transcript-design-r1.md.

## Design Round 2 — 2026-10-15 (sdd-reviewer via pi subprocess)
**VERDICT: BLOCKED — 🔴A the span "fix" was illusory (the :3156–3164 clamp is one-shot boot code running before the fallback; setting urlParams.duration alone is never re-read → false day-end cause survives); 🔴B NEW: applyDefaultSlotSelection's default pick (favourite/recent/first) rides over the fallback's pin mid-boot (the :3044 escape guard needs urlParams hydrated to fire); 🔴C D5 still claimed the withdrawn venueEligible refactor; 🟡-1 held↔span precedence; 🟡-2 :2657 in-the-way cause has no branch; 💡 anchors/pseudocode/ClassException-note/bridge-line.** → FIXED: D3 step 2 rewritten as the five-step hydration pipeline (legacy urlParams family → inline span consequences incl. `urlSpanLocked = true` → pin bridge → subject bind → flag-reset + evaluateBookingFit + consumeBookingIntent, boot's ONLY pick); fifth neutral copy branch added as recorded user decision; branch precedence reordered (held-cells outrank span); D5 withdrawal propagated; anchors/D2-notation/bridge/caveat lines adopted. Transcript: review-transcript-design-r2.md.

## Design Round 3 — 2026-10-15 (sdd-reviewer via pi subprocess)
**VERDICT: BLOCKED — 🔴-1 fallback lacks the intentCancelled short-circuit (discarded auto-selects resurrect on reload); 🔴-2 the fifth neutral branch unpinned (no trigger predicate/string; in-the-way case would misreport as occupancy); 🟡 held-cells discriminator needs a bounded scan predicate (else day-end failures report "a block already holds it" — the :1531 td-null behavior); 🟡 capacity-verdict honesty (blockedVenuesPayload renders the venue's cells occupied — verdict lands at grid+banner, not submit); 🟡 fallback placement ambiguous; cosmetic anchor residuals + D2 notation.** → FIXED (round 4): intentCancelled gate pinned; fifth branch given trigger predicate (`selectedBlock` mismatch) + frozen string + first precedence; held/span predicates pinned (bounded scan held-first); D6 grid-verdict caveat added; fallback placement pinned (immediately after applyUrlParams, before __bootDone); anchors (:3037/:3044/:3156–3164/:3141–3155) and D2's decidable notation (`ruleMap[session.type].includes(venue.rawRoomType)`) adopted. Transcripts: review-transcript-design-r3.md.

## Design Rounds 4–6 (interim) — 2026-10-15 evening (pi subprocess) — ⚠️ transcripts lost to /tmp cleanup; honest reconstruction from the implementer's fix record

Three additional real reviewer rounds ran on the 19:08 design state between 18:44 and 19:08:
- **Round 4:** verified 🔴A/B/C from Round-3 design genuinely fixed (hydration pipeline, span consequences incl. urlSpanLocked, D5 withdrawal propagated) but flagged 🟡 residuals (anchor drift survivors; garbled D2 producer pseudocode; splice-artifact risk in step 2a/2e phrasing). Fixes applied (~19:00 edit pass) — the round-4 edit itself corrupted D3 item 4 (orphaned fragments; numbering gap; splice artifact "skipped by that guard is SKIPPED").
- **Round 5:** BLOCKED — D3 item 4 corruption (rebuild demanded) + the fifth neutral branch unpinned. → D3 region rebuilt wholesale (clean items 3/4/5; five branches with pinned predicates: in-the-way `:2655–2661` first; too-soon; held-cells bounded scan; span/day-end only-when-held-false; type-ineligibility) + round-4-cosmetic ✅.
- **Round 6:** BLOCKED — sequencing impossibility: the pick fires inside `applySubjectChange`'s unconditional `consumeBookingIntent()` at `:2712` during step 2d's `onSubjectChange()`, so arming `fallbackAttribution` "after auto-select" or "right before a separate consume" was never reachable; → step 2d rewritten (reset flags → arm `fallbackAttribution` → onSubjectChange; `:2712` = the boot's ONLY pick), step 2e re-purposed as the staleness tail; item 5 aligned. A 7th call exited 1 with no output (transient); its rerun is merged into the confirmation round below.

All fixes are verifiably IN design.md (current text re-reviewed from scratch by the confirmation round — nothing here is trusted as frozen).

## Design Round 4 — 2026-10-15 (sdd-reviewer via pi subprocess) — CONFIRMATION re-review over the 6-round revision state
### 🟢 Verdict: CONFIRMED — design FROZEN eligible, 0 outstanding 🔴
Full source re-verification from scratch (nothing trusted from interim rounds): every load-bearing mechanism (hydration pipeline, inline span consequences + urlSpanLocked, round-6 sequencing :2712-only-pick, cancelled-gate, five-branch copy set with predicates, D0/D1 data-course rule map, 8-param contract) verified present and source-grounded; round-6 confirmation resolves the last decision-level contradiction. Transcript: review-transcript-design-r4-confirmation.md.
### 🟡 Fixed at freeze (soft-freeze amendments, reviewer-approved — no unfreeze)
1. Double-jump collapse landing assertion added to venue-intent-modal.spec (D4).
2. Zero-compatible copy strings pinned byte-exact (no `{types}` plural / no re-parenthesization on the banner).
3. D2 filter pseudocode decisionable (`venue.rawRoomType ∈ ruleMap[session.type]`).
4. `intentSessionOptions` ownership pinned (`payloadFor(auth()->id(), $week1)` — never emits another lecturer's sessions).

**DESIGN FROZEN — 2026-10-15 (7 real reviewer rounds total: R1–R6 fix rounds + R4-confirmation; 0 outstanding).**

## Tasks Round 1 — 2026-10-15 (sdd-reviewer via pi subprocess) — CONFIRMATION

### 🟢 Verdict: CONFIRMED — tasks FROZEN eligible, 0 outstanding 🔴

Reviewer verified from the frozen artifacts + source: full coverage (design D0–D6 → T1–T11 minus renumbered split; all proposal in-scope items), ordering safe (T1 extraction precedes both consumers; producer before consumer; stale-cache ritual named; no new destructive-write E2E against the demo DB — the change introduces no cancel/submit-anywhere path), limiter-safe (storageState one login per run), file paths match the frozen design, the 2a–2e pipeline faithfully mirrors D3. Transcript: review-transcript-tasks-r1.md.

### 🟡 Fixed at freeze (soft-freeze amendments, reviewer-approved)
1. T10 `phpsan` → `phpstan` typo (byte-correct frozen gate line).
2. T4 zero-fit now names BOTH byte-frozen notice states (base + duration extension).
3. T7 split into **T7a** (gate + hydration 2a–2c) / **T7b** (arm + bind + staleness tail) — heaviest region, truncation-safe.
4. T5 gains the focus-restoration clause (D2 item 5); T10 gains the room-matrix arrangement regression name; T11 names the two changelog files.
### 💡 Carried
- T7 hydration-family anchor note (`:3141–3156` vs D3's `:3147–3156`) — cosmetic.

**TASKS FROZEN — 2026-10-15 (1 confirmation round; 0 outstanding). All four artifacts hold real freezes: proposal (×3), design (×7), tasks (×1). `/sdd-apply` is cleared per the user's standing gate rules — NOT started here.**

## Apply completion note — 2026-10-15 (implementer)

Implemented T1–T5 + T7a/7b/T8 + T10 gates + T11 docs. NOTE per user direction: **T6/T9 Playwright spec authorship + runs were SKIPPED at the user's explicit request — they will test manually.** Gate results: pint ✓, phpstan 0 ✓, phpunit 178/178 ✓ (service contract tests included), blade JS node-parse ✓, stale-cache ritual done (server live on :8000). Placement amendment (soft-freeze): the fallback runs after the boot's final buildTimetable (documented in design D3 — the "before __bootDone" premise would run on an unbuilt DOM). Files touched: app/Services/LectureSessionCatalog.php (new), app/Livewire/{ReplacementArrangement,VenueTimetable}.php, resources/views/livewire/{venue-timetable,replacement-arrangement}.blade.php, resources/views/partials/ui-intent-session-modal.blade.php (new), tests/Feature/LectureSessionCatalogTest.php (new, 5 tests), 2 changelogs, design.md (placement amendment). Committed as feat/ui/test/docs split below. The manual-test matrix (user-runnable) is in the session chat.

## Apply round 2 — 2026-10-15/16 (user-reported RuntimeException + playwright self-test per user OK)

- **Bug:** `RuntimeException` `VenueTimetable.php:119` — `intentSessionOptions` (wire call) found `currentVenueFacts = null`: Livewire does NOT persist private render state into subsequent wire-call requests (user hit it live).
- **Fix:** the wire-callable method re-derives the venue facts from the hydrated public `$venueCode` + a DB lookup (unknown code → empty options, never an error); the cache property/helper deleted. Also: the modal partial's inline onclick handlers resolve at global scope → `closeIntentSessionModal/intentOverlayClose/confirmIntentSession` exported on `window` inside the DOMContentLoaded closure (same pattern as window.prevWeek).
- **Playwright (user-authorized):** `tests/venue-intent-modal.spec.ts` (promoted from the smoke) — 6/6 green: B110 wire-call modal L-only offered (regression guard), B009 compatible-only + byte-frozen label, 8-param handoff + landing, C2 fallback prefill w/ attributed toast (AMCS2093 auto-picked into B110 intent), B1 zero-compat message via a proper 4363 storageState (in-test login-swap turned out to be a test-artifact — app auth was correct). `venue-book` U2a-T3 reconciled to the modal route (designed contract change); `venue-timetable` TC42 locator scoped `#classModal .btn-close-modal` (shared-class strict-mode collision). Regression: venue-timetable 56(+1 scoped)/57, type-labels, venue-db — green (one flake-class logo flake not seen). Gates re-run: pint ✓ phpstan 0 ✓ phpunit 178/178 ✓.
- **Playwright-major remainder:** T6/T9 stay as landed (venue-intent-modal 6; intent-fallback C2 covered inside it). Full-suite T12 scope still governed by U2a's decision; user explicitly waived manual-only testing in favor of playwright self-test for this change's verification.

## Apply round 3 — 2026-10-16 (user decision: conflicted-only modal offers)

**USER AMENDMENT (decision 2026-10-15/16):** the "Select a Session" modal offers only CONFLICTED occurrences (ReplacementHome parity: exception-cancelled / public-holiday / B005-restricted), for the BOOKED week — via the venue page, 5770 sees only AMCS2093 (its Deepavali W8 conflict); healthy classes (BMIT2013) are never offered. Implemented in THREE layers, all consuming the same `LectureSessionCatalog` conflict facts (ReplacementHome-parity logic, D1 lockstep):
- Producer: `VenueTimetable::intentSessionOptions` filters `entry.conflicted`; service `conflictFacts()` computes per-occurrence reasons in the booked week (active requests excluded — "already being handled", D1).
- Fallback: `ReplacementArrangement::intentConflictsPayload()` page-data (intent week, derived from date param) + the fallback's candidate gate.
- Consumer/fallback placement amendment: the fallback runs AFTER the boot's final `buildTimetable` (soft-freeze – `isCellAvailable` needs the DOM) + 2e "rebuild + re-consume" (span fix ordering).
- **Playwright-fix:** the venue modal's span-filter needed VENUE-occupancy verification — the day-end check alone let a chosen cell's 2h span cross `AMIS1012`'s B110 Mon block (W8) — added `spanOccupied()` client filter (soft-freeze note), spec helper `pickSpanFreeCell` now spans-aware (shared `tests/helpers/intent-modal-helper.ts` – DRY, used by both venue specs).
- **Label trim (user decision 2026-10-16):** the session dropdown drops the subject NAME (the subject dropdown shows it) — label = `{module} ({type}) · W{n}, {day abbr}, {DD Mon YYYY} · {HH:MM}–{HH:MM}`.
- Results: venue-intent-modal 7/7, venue-book 6/6, venue-db 6/6, venue-timetable 56/56, type-labels ✓; pint ✓ phpstan 0 ✓ phpunit 178/178 ✓. The stale "healthy-class offered" rumors (test asserts) were fixed to the real holiday-day mapping (Deepavali Mon; Xmas Eve Thu; Xmas Day Fri in W14).

## Apply round 4 — 2026-10-16 (user-directed post-freeze amendments; verified live)

Five user-reported UX rounds after the conflicted-only amendment, each landed + changelog-recorded (venue-timetable + replacement-arrangement page changelogs) as user decisions against the frozen 99.9%-UI contract:
1. **Week-agnostic pool** (`1a8db7c`): a conflicted class is arrangeable at ANY week past the lead time — the pool no longer pins to the conflict week; `conflictedFor()` semester-wide; consumer fallback parity. The frozen "conflicted for the BOOKED week" grain is superseded by user directive.
2. **Occurrence-grain rows + shared tooltip** (`3a532b6`): session picker rebuilt on the venue-dd trigger/panel pattern (`.session-dd` token block in theme.css) because native `<option>` cannot host tooltips; rows = `(Type) W{week}, {Day}, {Date} . {HH:MM} ~ {HH:MM}` + full-details data-tip (frozen label format superseded by user request).
3. **Spurious confirm-modal fix** (`4b80c4c`): the legacy occurrence pick skips on `?session=` landings (it double-picked over the booking-intent auto-select); session-path 2e re-consume parity + cosmetic pinned-occurrence anchor.
4. **Honest zero-reason split** (`8a32874`): span-blocked vs past-day-end counted separately (the B002 Mon 13:30 case blamed the day end).
5. **Hint removal + gap guidance + rich typography** (`b95ed4c`, `33fd6a2`): the conflict-week sentence + Jump buttons removed (user: they implied a restriction); gap message "at least a {n}-hour gap"; approved CAPS/bold/red-amber treatment on all three notices (`theme.css .intent-notice .notice-*`).

Verification sweep (2026-10-16): T1–T11 mapped to landed code; `conflictWeeks` wire field removed (consumer gone); gates re-run green (pint ✓, phpstan 0 ✓, phpunit 178/178 ✓, Playwright venue suites 18/18 + full venue fleet 74/74). T9 remains covered by venue-intent-modal C2 (no separate intent-fallback spec — recorded deviation, tasks.md annotated).
