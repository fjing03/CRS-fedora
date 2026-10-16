## 🔍 Review Report: arrange-intent-session-choice — design round 4 (confirmation)

**Frozen status:** design.md under review; **proposal.md is FROZEN** (3 rounds, 0 outstanding). Review-log rounds 1–3 verified against source, plus source-verified checks of interim-round fix mechanisms are carried out below.

I have re-verified **every interim-round fix mechanism from actual source**, not from the log. Results:

### (a) D0/D1 — all verified ✅
- **Dual-vocabulary drift is real** (verified both sides): `ReplacementArrangement.php:158–176` `venuesPayload()` maps `cisco_lab → 'Lab'`; `VenueTimetable.php:63–74` maps `cisco_lab → 'CiscoLab'`. D0's ruleMap over **raw encodings** (`'P' => ['lab','cisco_lab']…`) plus per-venue `rawRoomType` page-data addition (D6, one additive field on rows already emitted — verified rows exist) correctly closes the seam. `venueEligible` at view `:2727–2737` stays untouched (display-vocab, capacity-aware) — the fallback explicitly does *not* route through it. Withdrawal propagated to D5. ✅
- **Id-keyed map is load-bearing, verified:** `courseCtx` uses `currentCourse.sessions[String(currentSessionId)]` (`~:2341–2343`), wanted-bind uses `currentCourse.sessions[wanted]` with `wanted = String(sessionId)` (`~:2699–2702`). An array would make both lookups `undefined` → fallback key `"0"` → module-first default. D1's "id-map for lookups + parallel `order` array `[(day_of_week, start_time, id)]`" is the correct shape. ✅
- **Missing facts confirmed absent today:** `coursesPayload()` (`:184–237`) per-session emits only `type/venue/duration/studentCount/cohorts/cohortCounts` — no `day_of_week`, no start/end. D1's additions are genuinely additive; `durationSlots = round(duration*2)` carried. ✅
- Module ordering (first-occurrence tuple order) and server-side week-occurrence facts (`date`/`dayAbbr`/`weekNumber` via existing `timelineWeeks` math) pinned in D1. ✅

### (b) D2 — all verified ✅
- Wire precedents exact: `my-request-history.blade.php:646/:698` and blade `:1977` (`submitBlocks` … `.then`) — `Livewire.first().call('intentSessionOptions', week1)` with clean return keys `{modules, roomType(raw), venue}` is precedented and decidable. ✅
- Current `bookVenue(venueCode, date, time)` at `venue-timetable.blade.php:270` extends to the canonical 6-arg signature building the 8-param URL; `showAvailableTooltip(ev, di, hi)` at `:231` with closure-scoped `currentWeek`/`dateStr` verified (`:237–238`). ✅
- Title **"Select a Session"** + **[Cancel] [Arrange]** restated (D2 §4). ✅

### (c) D3 — all verified ✅
- **Placement exactly as claimed:** boot order is `applyUrlParams()` at `:3224`, `window.__bootDone = true` at `:3227` (byte-exact), boot's final `buildTimetable()` at `:3240` (byte-exact). The fallback slot between `:3224` and `:3227` is real, and `applySubjectChange`'s `if (window.__bootDone)` deferment keeps `:3240` the single render. ✅
- **Cancelled gate real:** `intentCancelled` family exists — decl `:2464`, sessionStorage read keyed by `intentKey` at `:2509`, with "reload must not resurrect it" comment; `renderBookingIntent` honors it at `:2519`. Gate-first is correct. ✅
- **Step 2a/2b hydration mechanisms real:** the pinned-occurrence block (`~:3141–3146`) sets exactly `code/day/start/originalVenue/duration`; the one-shot clamp (`~:3147–3164`) computes `BLOCK_SPAN = Math.round(hrs*2)` + `MAX_SELECTION` + `urlSpanLocked` **once at boot** — so replicating it inline in the fallback (2b) is *required*, and the 🔴A critique was genuinely about a real one-shot constraint. Escape guard `urlParams.code && day !== null && start !== null && originalVenue` verified at `:3043` (≈ `:3044`) — with 2a's five fields set, the default pick is skipped. 🔴A/🔴B fixes are real mechanisms, not prose. ✅
- **Round-6 sequencing verified decisive:** `consumeBookingIntent()` is called **unconditionally** at `:2712` inside `applySubjectChange` (before any `__bootDone` guard), so the boot's only pick fires inside the fallback's own `onSubjectChange()` — the rewritten step 2d (reset → arm `fallbackAttribution` → bind) and 2e staleness tail are the only coherent order. Verified sound. ✅
- **Five-branch copy mechanics real:** in-the-way branch `:2655–2661` exact (`if (selectedBlock)`); too-soon string `:2523`; `isCellAvailable` null-cell → false at `:1531–1532`; `canPlaceBlock` `:1534–1540` exact; `evaluateBookingFit` def `:2561` exact. The bounded-scan `heldCellExists` (capped at `hours.length`) correctly avoids the td-null false-cause. ✅

### (d) D4 — U2a-T3 ✅ / one gap found 🟡
- U2a-T3 routed through the modal (`#intentArrangeBtn`) + modal-visible assert is present; T2 survives. ✅
- **The double-jump collapse test is NOT in the current D4** — see 🟡1 below. I verified the collapse itself is logically sound (`weekNav.selectWeek` at `:3214` then `consumeBookingIntent`'s compare-jump `:2650–2652` — same-week no-op by construction), but the frozen proposal's "explicit test" obligation is currently uncarried.

### (e) D5/D6 — all verified ✅
- `venueEligible` untouched (verified both in D0/D5 text and against source); capacity-verdict honesty verified against source: `blockedVenuesPayload` (`:404–417`) blocks by type **or** headcount via `eligibleVenues`/`requiredHeadcount`, and the grid renders `cell-occupied` on `engineBlocked` (`~:1666–1671`) — verdict at grid+banner, honestly recorded. sessionStorage byte-unchanged (no design step adds a key; the pin bridge is `window`, not storage). ✅

---

### 🔴 Critical Issues
**None.** Every load-bearing mechanism from the interim rounds is verified present and source-grounded; the round-6 sequencing fix resolves the last decision-level contradiction.

### 🟡 Should Fix (soft-freeze amendments before /sdd-apply)
1. **Double-jump collapse assertion dropped from D4.** Review-log round 1 claims "double-jump test named in D4", but the current `venue-intent-modal.spec.ts` list carries no landing assertion (only the fallback spec's "pinned grid/week"). The frozen proposal's risk item explicitly demands one. Fix declaratively: add one line — *post-[Arrange] navigation: grid lands on the pinned week, booked slot auto-selected, exactly one week jump (server pin `:3214` vs intent compare-jump `:2650` collapse)* — to venue-intent-modal.spec (or the updated T3).
2. **"Verbatim" banner copy is not byte-verbatim.** Frozen proposal says *"…{room type} hosts **{type}** sessions only"* after an em-dash; design D2§6/D3§3 render `{types}` and re-parenthesize on the banner. Since these are user-frozen decision strings (2026-10-15), pin the byte-exact strings (or record the parenthesization/singular-collection change as a dated user decision) — otherwise two implementers produce different banners.
3. **D2 filter pseudocode glitch:** "session.type ∈ ruleMap[session.type]" is circular — the intended decidable predicate is `venue.rawRoomType ∈ ruleMap[session.type]` (as D0 already states). One-word fix.
4. **`intentSessionOptions` ownership pin:** state explicitly that it calls `payloadFor(auth()->id(), $week1)` so the publicly wire-callable method can never emit another lecturer's sessions (D1's signature supports it; one clause).

### ✅ What's Done Well
- Every prior 🔴 has a real, source-verified mechanism behind it (not prose hand-waving): the 2a-guard/2b-inline-clamp pair is the correct fix for a genuine one-shot boot constraint; the round-6 reorder is provably the only coherent sequencing given `:2712`'s unconditional consume.
- Anchors are overwhelmingly byte-accurate (`:3227`, `:3240`, `:2561`, `:2655–2661`, `:646/:698`, `:1531–1540` exact).
- The D0 raw-encoding decision honestly documents the withdrawn `venueEligible` refactor instead of papering over it, and D6's capacity-verdict relocation is exactly what the source shows.

### 💡 Optional Suggestions
- A few anchors drift ±1–3 lines (`:2523` vs `:2524`, `:3044` vs `:3043`, `:2922–2924` vs `:2925`) — harmless since mechanisms bind; no action needed unless touching those regions anyway.
- Add one clause that `fallbackAttribution` is also cleared after the toast render on the pick path (the "one-shot" wording implies it; make it explicit so the tail is symmetric).

### ⚖️ Review Verdict
All (a)–(e) confirmation items verified from source; no decision-level defects remain. The four 🟡s are declarative-only amendments (test-line addition, byte-pinned copy, one-word pseudocode fix, one ownership clause) — none changes an algorithm, semantic, or responsibility, so they land via soft freeze, not unfreeze.

**VERDICT: CONFIRMED — design FROZEN eligible, 0 outstanding 🔴** (apply the 4 soft-freeze 🟡 amendments before `/sdd-apply`).
