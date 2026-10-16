# Design: arrange-intent-session-choice

**Frozen proposal:** `proposal.md` (3 rounds, frozen 2026-10-15). **Explore baseline:** `explore-brief.md` (+ round-1 declarative addendum).
**Verified source anchors used throughout** (reviewer-checked in proposal rounds 1–3): pinned contract `app/Livewire/ReplacementArrangement.php:125–150` + view `:3060–3154` (`evaluateBookingFit` def `:2561`, run inside `applyUrlParams` `:3096/:3135`); subject-first bind `view:2697–2701`; pin rewrite precedent `view:2922–2924`; `canPlaceBlock` `view:1534–1540`; intent machinery `view:2472–2640` ("is conflicted" at `:2523`); client matrix `view:2727–2737` (`venueEligible`); producer payloads `app/Livewire/VenueTimetable.php:58–160` (single-venue events), `app/Livewire/ReplacementArrangement.php:184–237` (`coursesPayload`).

## D0 — Core decision: ONE PHP-hosted rule, consumed as data (round-1 🟡 resolution)

The drift risk flagged with the cisco vocabulary split (`'CiscoLab'` vs `'Lab'` for the same DB `cisco_lab`) is eliminated by making the **eligibility rule live exactly once, PHP-side**, and shipping it as DATA — over the **raw DB encodings**, so display-vocabulary translation never touches the rule:

- `ruleMap(): array` on the shared service — canonical, JSON-able:
  ```php
  ['P' => ['lab', 'cisco_lab'], 'T' => ['tutorial'], 'L' => ['lecture_hall'],
   'b005_no_diploma_pattern' => '^\s*D', 'b005_venue_code' => 'B005']
  ```
  Keys/values are the DB's raw `venues.room_type` / `class_sessions.session_type` values.
- **Client consumption runs against the RAW room type, so the dual-vocabulary seam never opens:** the rule map hydrates into page-data on BOTH pages; on the arrangement page every venue entry gains its raw `room_type` field alongside the existing display `type` (see D6 — one page-data field, no behavior change). The fallback's eligibility check is `ruleMap[session.type].includes(venue.rawRoomType)` + the B005 cohort pattern on session cohorts — it does **NOT** run through the client `venueEligible` (display-vocab, capacity-aware; that function and the venue-dropdown filter behavior are UNCHANGED; unifying `venueEligible` with the raw map is future work, not this change). The earlier "refactor venueEligible to consume the map" sentence is WITHDRAWN.
- Drift guard: a PHPUnit contract test enumerates the map's expected behavior (mapping table + B005 rule) — the map is frozen data; the client merely consumes it.
- Round-3 🟡 resolved here: `{DD Mon YYYY}`/`W{n}`/day-abbr label data is **computed server-side** (D1) — the client derives nothing from "day-of-week × base date" guesswork.

## D1 — Shared service: `App\Services\LectureSessionCatalog`

Extracted from `ReplacementArrangement::coursesPayload()` (the promotion itself). Placement per CodingMAIN §10.5/§10.4 (core logic in service classes, none in views).

```php
public function payloadFor(int $lecturerUserId, ?int $week1 = null): array
public static function ruleMap(): array  // D0
```

- **`sessions` remains an ID-KEYED MAP (round-1 🔴2 resolution):** `sessions[(string) $s->id]` exactly as today's `coursesPayload` (`mapWithKeys((string) $s->id …)`), because the arrangement blade performs load-bearing id lookups at `courseCtx()` (`currentCourse.sessions[String(currentSessionId)]`, `:2341–2342`) and the wanted-session bind (`currentCourse.sessions[wanted]`, `:2700–2701`). An array would break the pinned/fallback bind silently (`sessions[wanted]` → undefined → fallback key "0" → module defaults). Shape rule: **id-map for lookups + a parallel `order` array of session ids** carrying the `(day_of_week, start_time, id)` canonical order; `coursesPayload` gains only that additive `order` array — existing consumers untouched, both lookups byte-preserved.
- Every session entry carries the frozen fact list: `id, type, venue, duration` (`durationSlots = round(duration * 2)`), **`day_of_week`** (DB encoding 0–5 = Mon–Sat as imported — the ordering/label key), **`startTime`/`endTime`** (HH:MM), `cohorts` (codes + display labels), `studentCount` — the facts today's `coursesPayload` does NOT yet emit (day-of-week, start/end) are added here (round-2 🟡 fix).
- **`payloadFor(..., $week1)` additionally emits per-session occurrence facts for that booked week:** `date` (`DD Mon YYYY`), `dayAbbr`, `weekNumber` (1-based W-number) — round-3 🟡 resolution: the label's date/week come from the server, one source; the service reuses the semester week-boundary math both components already run (`timelineWeeks` pattern).
- **Module ordering pinned (round-1 💡):** modules are emitted in **first-occurrence timetable order** — each module keyed by its earliest session in the `(day_of_week, start_time, id)` order, modules sorted by that key. This is what "subject-dropdown display order" means for the Q2 rule (the existing consumer dropdown mirrors the same insertion discipline today, `buildSubjectDropdown` insertion via `MockData.courses`).
- `ReplacementArrangement::coursesPayload()` delegates to `payloadFor(..., null)` (facts-only mode); public shape identical apart from the additive `order` array.

## D2 — Producer: the "Select a Session" modal

**Partial:** `resources/views/partials/ui-intent-session-modal.blade.php` — markup only (ids: `intentSessionModal`, `intentSubjectSelect`, `intentSessionSelect`, `intentNotice`, buttons `intentCancelBtn` / `intentArrangeBtn`); classes come from the shared modal kit (`.modal-overlay` idiom of `ui-class-detail-modal` + `.modal-footer` + §10.0 rule 9 bottom-sheet at ≤768px, already in the shared CSS set). One-line page-inline addition only if an id-style hook is genuinely missing (prefer zero).

**Wire flow (producer):**
1. `showAvailableTooltip` already captures `(di, hi)` and the week (`currentWeek`) — the tooltip's Arrange onclick becomes `openIntentSessionModal()` (closure-scoped; `currentWeek`, `hours`, `venueInfo` in scope — D2 lives inside the existing `DOMContentLoaded` closure, same placement rationale as U2a's D2).
2. `openIntentSessionModal()` calls the component: `Livewire.first().call('intentSessionOptions', currentWeek + 1)` (call-returns-value precedent: `my-request-history:646/:698`, `replacement-arrangement:1977`) → the new public method returns the modal model in ONE call:
   ```php
   public function intentSessionOptions(int $week1): array
   // ['modules' => [ ...filtered+ordered entries with per-session label facts
   //                 + durationSlots ... ], 'roomType' => raw, 'venue' => code]
   // eligibility — owner-pinned (uses `payloadFor(auth()->id(), $week1)`, so this wire-callable public method can never emit another lecturer's sessions):
   //   venue.rawRoomType ∈ ruleMap[session.type] + B005 cohort pattern → PHP-side, single source.
   ```
   No `hour` parameter — the **duration-fit check runs client-side** against the shared `hours` constant (`hour + durationSlots <= hours.length`): arithmetic only, not a rule (rules are PHP-frozen; `hours` is a client constant by nature). Duration-excluded entries exist in the payload but are simply not rendered (compatible-only policy) and count toward the notice's duration case.
3. Modal renders subject dropdown = modules with ≥1 fitting session (dropdown display order); session dropdown = the chosen module's fitting sessions **labeled verbatim** `{module} {name} ({type}) · W{n}, {day abbr}, {DD Mon YYYY} · {HH:MM}–{HH:MM}` (the user-frozen string; label built by a tiny client former from the emitted facts — string assembly only, no rule).
   Auto-preselect first module + its first fitting session in the emitted array order (Q2 rule; array order is the (day, start, id) tuple → deterministic first = earliest-in-week compatible).
4. **[Arrange]** → `bookVenue(venueCode, dateStr, timeStr, moduleCode, sessionId, cohortLabel)` — the U2a `bookVenue` keeps its sessionStorage intent-key cleaning verbatim and builds the **8-param URL**: `venue, date, time, from=venue-timetable, code=moduleCode, session=sessionId, cohort=cohortLabel, week = currentWeek + 1` (all `encodeURIComponent`d; `cohort` = the first display label of the chosen session's cohorts array, matching the template-era `&cohort=` single-label semantics). Frozen strings restated: title **"Select a Session"**, buttons **[Cancel] [Arrange]**.
5. **[Cancel]/Escape** hides the modal + restores focus to the triggering cell; nothing persisted (no sessionStorage writes).
6. **Zero- or partial-fit case:** if no session passes type+cohort → message *"None of your classes can run in {venue} — {room type} hosts {types} sessions only."* + [Cancel] only; if type-passing sessions all fail the duration check → same modal body with *"...and none of them fit before the day end ({end time})."* (per frozen copy). No dropdowns in either case.

## D3 — Consumer: fallback prefill + honest copy

**B-fallback (intent arrived, no `session` param, no subject picked) — round-1 🔴1 span/boot ordering; round-3 🔴-1 cancelled-gate; round-3 🟡-3 placement pinned.**

1. **Gate + placement:** the fallback is invoked **immediately after `applyUrlParams()` and before `window.__bootDone = true`** (`:3227`) — the boot's own final `buildTimetable` (`:3240`) stays the single render. Its FIRST step is the cancelled-gate: if `intentCancelled` (the existing sessionStorage flag family — a previously discarded auto-select for THIS booking) is armed, **the fallback does nothing** (no auto-pick; banner suppressed exactly as `consumeBookingIntent` spends the intent today) and the flow proceeds as if no fallback existed. Discarded selections never resurrect on reload.

2. **Pin bridge + REAL span sizing + guard-friendly hydration (round-2 🔴 A/B fix):** on match the fallback performs the hydration CONSEQUENCES itself (the `:3147–3164` clamp block is one-shot boot code that runs before `applyUrlParams` — setting `urlParams.duration` alone would never be re-read), in this order:
   a. **Hydrate the full legacy urlParams family from the matched session — the 🔴 B fix:** set `urlParams.code`, `urlParams.day`, `urlParams.start`, `urlParams.originalVenue`, and `urlParams.duration` exactly as the pinned-occurrence hydration block does (`:3147–3156`). This makes the existing `applyDefaultSlotSelection` escape guard (def `:3037`, guard `:3044`: `code && day !== null && start !== null && originalVenue`) fire untouched during the coming slot-picker render — the favourite/recent/first default pick is skipped by that guard, so nothing clobbers the fallback's pin mid-boot (the same protection the current code affords `?session=` handoffs).
   b. **Span consequences (🔴 A fix):** with `urlParams.duration` hydrating into the same family, replicate the one-shot clamp inline: `BLOCK_SPAN = Math.round(duration * 2)` after the 0.5–4h clamp family, `MAX_SELECTION = Math.max(MAX_SELECTION, BLOCK_SPAN)`, and `urlSpanLocked = true` (mirroring the pinned-hydration "initial authority": the auto-picked span is session-accurate and a later auto-resize cannot re-size it; a USER manual resize via the panel is unaffected).
   c. **Pin bridge:** set `window.pinnedOccurrence = { sessionId: O, week: intentWeek + 1 }` (`intentWeek` = the intent's 0-based week index; same 0-based family as the producer; mirrors the slot-picker pin rewrite `:2922–2924`).
   d. **Reset + arm + subject bind (round-6 🔴-1 sequenced):** reset the stale `intentNeedsManual/intentTooSoon` flags; set `fallbackAttribution = {module, type, venue}`; THEN trigger the existing URL-subject path programmatically (`sel.value = code; onSubjectChange()`), so `applySubjectChange:2697–2701` binds the compatible occurrence via the wanted-session logic, re-runs `evaluateBookingFit` in correct precedence, and consumes the intent at `:2712` — attributing the toast one-shot. All pre-existing downstream machinery unchanged.
   e. **Re-evaluation tail (round-6 🔴-1 reordered mechanism):** after `onSubjectChange()` returns from step 2d — note the pick already happened inside it at `:2712` (applySubjectChange's unconditional `consumeBookingIntent()`), which is therefore **the boot's ONLY pick; the fallback makes NO second direct consume call** — the flag reset + attribution arm happen BEFORE 2d (see below), so the sequence is: reset stale `intentNeedsManual/intentTooSoon`, set `fallbackAttribution = {module, type, venue}`, **then** call `onSubjectChange()` (2d) → `evaluateBookingFit` is re-run internally in the correct precedence and the toast site (`:2674`) renders the extended text one-shot. **Staleness tail:** when `onSubjectChange()` returns, if `fallbackAttribution` is still set (an exit path completed without a pick — short-circuit or spent intent), clear it; the fallback never leaves state behind.
3. **No-match (round-1 🟡, USER DECISION 2026-10-15): reuse the producer zero-compatible wording VERBATIM on the consumer banner** — no consumer-specific string is invented; the cause clause is the same sentence inside the banner's lead-in:
   *"Booking {venue} · {day}, {date} · {time} — none of your classes can run in {venue} — {room type} hosts {type} sessions only."* (byte-frozen: same clause as the modal, no re-parenthesization — R4 🟡2) (+ duration extension *"…and none of them fit before the day end."* when type-passing sessions all fail the fit check). The picked-subject naming variant (*"{module}({type}) has no {required-type} sessions that can run in {venue}"*, proposal item 5) applies exactly when a subject IS picked and is incompatible (manual pick, never overridden). All banner states keep the dismiss button as today.

4. **Copy set edits in `renderBookingIntent` (`:2514–2537`)** — replace `:2523`'s ternary with this five-branch set. **Trigger predicates pinned (round-3 🔴-2 / round-3 🟡-1):**
   - **in-the-way (FIRST):** trigger = the existing condition (`:2655–2661`) — a `selectedBlock` exists that is not the booked slot/venue/week. String: *"Your booked slot ({day}, {date} · {time}) is already covered by another of your selections — clear it or pick another slot manually."* (the user's OWN selection is the cause — never styled as external occupancy).
   - **too-soon (SECOND):** the unchanged frozen lead-time check + wording (*"…is within 3 working days — please pick a later slot manually."*).
   - **held-cells (THIRD):** `heldCellExists(i.hourIndex, i.dayIndex) = ∃ h ∈ [i.hourIndex, min(i.hourIndex + BLOCK_SPAN, hours.length))` with the occupying cell unavailable — the bounded scan; the `:1531` td-null → false behavior is why an unbounded scan false-causes day-end failures as "held". String: *"Your booked slot ({day}, {date} · {time}) is no longer available on the {venue} grid (a block already holds it) — pick another available slot manually."*
   - **span/day-end (FOURTH — only when `held` is false):** `spanOverrun = i.hourIndex + BLOCK_SPAN > hours.length`. String: *"Your class runs longer than the time left before the day end ({end time}) — pick an earlier slot or change the subject to a shorter session."*
   - **type-ineligibility (FIFTH):** a subject IS picked (user's manual pick — never overridden) and has no session whose type the venue allows: *"{module}({type}) has no {required-type} sessions that can run in {venue} ({room type})."* The no-subject venue-level case is item 3 above (producer wording verbatim).
   Predicate helpers (`spanFits`, `heldCellExists`) are additive — zero risk to existing `canPlaceBlock` callers (`:1534`). `evaluateBookingFit` (`:2561`) walks this precedence FIRST-true-wins.

5. **Attribution toast (frozen host; round-6 sequencing):** `fallbackAttribution` is armed in step 2d immediately before the subject bind (the pick happens inside `onSubjectChange` at `:2712`); the existing toast site (`:2674`) renders the extended text *"Pre-selected {module}({type}) — auto-picked because its sessions fit {venue}; change if needed."* one-shot and steps 2e's staleness tail clears the flag on any pick-less exit. The modal path (session-pinned) keeps the toast's current wording — the modal WAS the explicit choice.

**`?session=` modal path (no new consumer logic, verified twice):** `pinnedSessionPayload` resolves + hydrates (`:3087–3154`, week jump `:3198–3212`), `applySubjectChange` binds the pinned id, `consumeBookingIntent` auto-selects. Consumer `coursesPayload` call → service (D1) — same facts, no behavior change.

## D4 — Tests & gates

- **New: `tests/venue-intent-modal.spec.ts`** (one login/run, storageState): modal open on Arrange; compatible-only dropdown contents (type + B005 cohort rule live fixture); preselection order (earliest-in-week compatible = the (day,start,id) first); label format byte-assert incl. `W{n}`, date, end time; [Cancel]/Escape no-op; zero-compatible message (a lab with no P-type session for the logged-in lecturer —_probe dynamically from the payload, fall back to skip-with-note if the demo data lacks such a lecturer); handoff URL equality (all 8 params).
- **Updated: `tests/venue-book.spec.ts`** — U2a-T3 routed through the modal (click `#intentArrangeBtn`); added assert: modal visible pre-navigation; all other U2a tests unchanged (T2's tooltip sentence/btn-text asserts survive — the modal is an add-on, not a replacement of the tooltip).
- **New: `tests/intent-fallback.spec.ts`** — hand-typed full-intent URL (no `session`) → auto-prefill + attribution toast + pinned grid/week; manual incompatible pick → naming copy, no override; day-end branch; occupancy branch wording; `session`-param short-circuit (modal-context priority).
- **Regression set:** venue-db, venue-timetable (56), type-labels, room-matrix behavior in arrangement specs — all green required.
- **PHPUnit:** service unit tests (rule map, ordering tuple, week occurrence facts, B005 pattern) — pattern per existing Feature tests; phpunit expected green (173++).

## D5 — Docs, change log, hygiene

- `page-changelogs/venue-timetable-ui-changelog.md` (modal + contract) and the arrangement page's changelog (fallback + copy set) — every user-decision string dated 2026-10-15.
- "Promoted to shared": `LectureSessionCatalog` (payload + rule map) — recorded; venueEligible is UNCHANGED by this change (per the frozen non-goal — its display-vocab matrix stays for the venue-dropdown filter only; unification with the raw rule map is future work).
- `day_of_week` encoding note: DB imports use 0–5 = Monday–Saturday (Sunday absent from teaching data) — ordering + labels both read the same field; no re-encoding.
- Mobile: bottom-sheet variant verified live (§10.0 rule 9); touch targets ≥44px.

## D6 — Accepted-risk ledger (from the proposal)

- Capacity intentionally **not** re-applied at the modal/filter (engine + consumer venue dropdown enforce it; a type-eligible but capacity-rejected session dead-ends at submit honestly — pinned so nobody duplicates capacity logic here).
- `cohort` param parsed-but-unused on the consumer today — restored for contract fidelity, not function.
- **Round-1 🟡 amendment (honest PHP inventory):** `ReplacementArrangement.php` gains TWO page-data facts, within the existing `pageData()`/`venuesPayload` assembly (no behavior, no write-path): (1) `rawRoomType` added per venue entry (one field on the already-emitted Venue rows); (2) the rule map rides page-data via the service. The "no PHP change expected" expectation from the proposal is revised to exactly this (logged in review-log; verify will check D6 vs the diff).
- sessionStorage intent semantics byte-unchanged.
- **Rule-map bridge (round-2 💡-4):** both blades hydrate the map in their existing bridge blocks (`MockData.*`-style assignments in the layout-hydrate family, `:1170–1183` pattern) — one line each; named so the implementer doesn't discover it at runtime.
- **Capacity-verdict location (round-3 🟡-2 honesty):** `blockedVenuesPayload` blocks venues failing type OR capacity (`:404–421`) and the grid renders those cells occupied (`:1666–1671`) — a type-eligible-but-over-capacity pair (producible by the modal's deliberate capacity-blind filter) dies at the GRID + banner branch, not at the submit alert as D6 previously implied. Accepted honestly; a headcount-specific banner later is out of scope.
- **Honest-labels caveat (round-2 💡-3):** a session carrying a `ClassException`/holiday inside the booked week is still listed with that week's nominal date — the submit-side engine stays the honest authority; noted so no one "fixes" the modal by duplicating exception logic.
