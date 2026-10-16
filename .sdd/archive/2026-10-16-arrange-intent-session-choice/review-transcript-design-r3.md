## 🔍 Review Report: arrange-intent-session-choice

**Batch under review:** `design.md` (revision 2, design round 3). **Frozen:** `proposal.md` (3 rounds), `explore-brief.md` + round-1 declarative addendum.
All findings verified against the live source: `resources/views/livewire/replacement-arrangement.blade.php`, `resources/views/livewire/venue-timetable.blade.php`, `app/Livewire/ReplacementArrangement.php`, `app/Livewire/VenueTimetable.php`.

---

### Round-2 claims — verification results

| Round-2 finding | Verdict |
|---|---|
| 🔴 A (clamp is one-shot boot code; fallback must perform hydration CONSEQUENCES) | ✅ **FIXED, verified.** D3 step 2b replicates the clamp family inline (source clamp block `:3156–3164`, the `urlSpanLocked`/`MAX_SELECTION`/`BLOCK_SPAN` semantics match `:2913–2915` and `:3161–3163`). The step-2 premise ("clamp block runs before `applyUrlParams`, setting `urlParams.duration` alone would never be re-read") is confirmed true — boot ordor: hydrate/clamp `:3141–3164` → `applyUrlParams()` invoked `:3231`. |
| 🔴 B (default slot pick rides over the fallback pin) | ✅ **FIXED, verified.** D3 step 2a hydrates the full family exactly like the pinned block (`:3146–3155`: code/day/start/originalVenue/duration). The escape guard verified live at **`:3044`** (`if (urlParams.code && urlParams.day !== null && urlParams.start !== null && urlParams.originalVenue) return;`) fires inside `applyDefaultSlotSelection` at the end of `renderSlotPicker` (`:2802`), which is called from `applySubjectChange` (step 2d) — so the favourite/recent/first pick is skipped and `consumeBookingIntent`'s own `selectBlock` (the pick inside `applySubjectChange`'s internal call, or step 2e's second call — exactly one pick either way) is the boot's only pick. ✓ |
| 🔴 C (D5 claimed the withdrawn venueEligible refactor) | ✅ **FIXED.** D0 and D5 both state venueEligible is unchanged, unification is future work, consistent with the frozen non-goal. |
| 🟡-1 (branch precedence: held-cells > span/day-end) | 🟡 **Partially fixed** — precedence reordered as required, but the discriminator that makes the order correct is still unpinned (see 🔴-2 below / 🟡-1 below). |
| 🟡-2 (:2657 in-the-way neutral branch) | 🟡→ **Escalated** — the fifth branch exists in the list but has no trigger predicate and no pinned string (see 🔴-2). |
| 💡 anchors / D2 pseudocode / ClassException labels / rule-map bridge / round-1 log entry | ClassException caveat (D6) ✅; rule-map bridge (D6) ✅; D5 withdrawal ✅. Anchors and D2 pseudocode remain partially stale (💡s below). Round-1 log entry exists in review-log ✓. |

---

### 🔴 Critical Issues (implementation blockers)

1. **🔴-1 (NEW) — The fallback has no `intentCancelled` gate; it resurrects an auto-select the user explicitly discarded.**
   D3 step 2 arms the fallback "when `currentCourse` is null and `pendingBookingIntent` is armed". But `resolveBookingIntent` (`:2472`) arms `pendingBookingIntent` **regardless of the cancel state** — `intentCancelled` is a separate flag, and the frozen semantic is explicit in the source: `:2481` "user discarded the auto-select — reload must not resurrect it", and the state comment at `:2478–2479` "a discarded auto-select also stays discarded… reloads don't resurrect it". `consumeBookingIntent` honors this (its first branch silently spends the intent), but the fallback runs **before** it and mutates state unconditionally: step 2a–2e bind the subject, set `pinnedOccurrence`, hydrate `urlParams`, and size the span. Net result after a discard + reload of the same hand-typed full-intent URL: subject pre-bound, pin re-armed to the discarded booking's occurrence, URL-context polluted — exactly the resurrection the sessionStorage family exists to prevent, and a behavioral contradiction of the frozen proposal non-goal "sessionStorage intent semantics byte-unchanged". No D4 test would catch it (the fallback spec lists no cancelled-reload case), so it ships.
   **Fix (declarative, design D3 + D4 only — no unfreeze):** arm condition gains "and not `intentCancelled`" (banner-dismissed/`intentBannerSuppressed` stays valid — dismissal deliberately keeps auto-select working); D4's `intent-fallback.spec.ts` gains the case: discarded booking + same URL reload → grid stays locked, subject NOT pre-bound.

2. **🔴-2 (residual of round-2 🟡-2, escalated) — The neutral fifth branch is a name, not a specification: no trigger predicate, no string, and as written it can never fire for its own intended cause.**
   D3 step 4 ends the precedence with "then the neutral fifth branch" and cites "frozen strings, proposal item 5 + round-2 additions" — but **proposal item 5 does not contain a fifth string** (its branches: ineligibility / day-end / occupancy / too-soon / toast), and nothing in this batch (design or artifacts) states the branch's trigger or text. Round-2's finding was that the **manual in-the-way cause** (`consumeBookingIntent :2657` region — `if (selectedBlock) { intentNeedsManual = true; … }`) deserves neutral copy, not the error-toned conflict treatment. But under the current design that case will **never reach the fifth branch**: when a selection is active, the selection's cells have `cell-available` removed (`:1480–1487` select path) and restored on deselect (`:1419–1426`) — so a held-cells predicate reading the DOM (`isCellAvailable`, `:1530–1532`) sees the user's own selection as "unavailable cells", and branch 2 ("a block already holds it") outranks branch 5 and renders first — a false error-toned cause against the user's own manual pick. Since round-2's precedence rule (held-cells first) actively guarantees this misreport under a literal reading, an implementer cannot satisfy both 🟡-1 and 🟡-2 as written. Neither D4 test ("day-end branch", "occupancy branch wording") exercises the in-the-way case, so it ships.
   **Fix (declarative, D3 + D4):** pin (a) the fifth branch's trigger: `selectedBlock` overlaps/predates the intent target — mirror `consumeBookingIntent`'s existing ordering (selection check **before** any cell-scan), i.e. "a user selection in the way" is evaluated before held-cells and renders neutral copy; (b) the fifth branch's user-frozen string (must be captured in the artifact — it currently exists only in round-2 conversation memory); (c) a D4 test: manual selection present → neutral copy, not "a block already holds it".

---

### 🟡 Should Fix (non-blocking but recommended)

1. **🟡-1 — The held-cells vs day-end discriminator is unbounded, which kills the day-end branch.** D3 step 4 orders held-cells before span/day-end but never says how "a block already holds it" is tested. Naively implemented as "any vertex in the span window is unavailable" it silently swallows the pure day-end case: for `start + BLOCK_SPAN > hours.length`, the trailing vertexes don't exist in the grid, so `isCellAvailable` returns false for them (`td` lookup null → false, `:1531`) and every day-end failure would report "a block already holds it" — re-opening the exact false-cause the round-1 🔴3 fix abolished. The D4 day-end-branch test would catch it during apply, but only after the wrong implementation is written. Pin the predicates: `dayEnd = i.hourIndex + BLOCK_SPAN > hours.length`; `held = ∃ h in [i.hourIndex, min(i.hourIndex+BLOCK_SPAN, hours.length)) with cell unavailable` — held-first with the bounded scan gives the round-2 ordering *and* a live day-end branch (overlap case: cells partly held + overrun → held wins, which is the defensible first-true-cause).
2. **🟡-2 — Capacity-incompatible pairs dead-end at the grid, not at submit — D6's honest-outcome claim is mislocated.** D6 says a type-eligible but capacity-rejected session "dead-ends at submit honestly". Verified reality: `blockedVenuesPayload` blocks venues failing type **or capacity** (`ReplacementArrangement.php:395–397`), and the grid renders those cells occupied (`:1655–1660` `engineBlocked`) — so every cell of the booked venue renders occupied and the user never reaches submit. The modal/producer filters deliberately ignore capacity (D0/D2), so this pair is producible by design. With the new copy set, the user gets candidate branch 2 "a block already holds it" — a false cause for a headcount problem — plus the booked venue vanishing from the venue dropdown mid-boot (`buildVenueFilter` → `venueEligible` includes capacity). Pre-existing machinery, but this change materially increases its exposure (modal-driven handoffs are the happy path now). Accept with honesty: one caveat line in D6 ("verdict lands at grid-render + banner, not the submit alert") and optionally route the observable symptom there, or note it in the changelog ledger.
3. **🟡-3 — Fallback placement is ambiguous.** "At the end of applySubjectChange-independent boot" doesn't say whether that is just after `applyUrlParams()` (`:3231`) / before `window.__bootDone = true` (`:3235`) or after the boot block. Both are workable (outcome-equivalent), but the two differ in rebuild count (`__bootDone` gating `saveCurrentWeek+buildTimetable` inside `applySubjectChange`, boot's final `buildTimetable` at `:3244`). Pin: immediately after `applyUrlParams()`, before `__bootDone = true`, so the boot's own `buildTimetable` remains the single final render. (Non-blocking because the end state is identical either way.)

---

### ✅ What's Done Well

- 🔴 A/🔴 B/🔴 C fixes are **mechanism-level and source-truthful**: the inline clamp replication matches `:3155–3164` and `:2913–2915` semantics precisely; the hydration family matches `:3146–3155` field-for-field; the escape-guard claim ACTUALLY holds at the line the design names; the D5 withdrawal is fully propagated (D0 + D5, with the non-goal citation).
- The "boot's ONLY pick" net rule in step 2e is now closed under the real source flow (`applySubjectChange`'s internal `consumeBookingIntent` call + step 2e's re-call can never produce two picks; stale-flag recovery through the second call is coherent).
- D1's id-map + parallel `order` array is the right resolution — verified against `courseCtx`'s map lookups (`:2341–2343`) and the wanted-session bind (`:2699–2701`).
- D0/D6 read correctly against the data: rule-map values match the raw DB encodings seen in `venuesPayload`/`VenueTimetable` (`lab`, `cisco_lab`, `lecture_hall`, `tutorial`); `b005_no_diploma_pattern` matches the live `venueEligible` test `/^\s*D/`; the B005 code constant matches `:2733`'s hardcoded `'B005'`.
- Round-3 🟡 (label date/week single source) properly resolved: emitted server-side, client does string assembly only — no client date math, consistent with the honest-zero/`ClassException` caveat in D6.

### 💡 Optional Suggestions

- **Anchor drift residuals** (cosmetic, names are given so no misdirection): `:2872` is not `applyDefaultSlotSelection` (it's `:3037`); the guard is `:3044` not `:3043`; the clamp block is `:3156–3164` (hydrate assignments are `:3146–3155`, so "3147–3156" ≈ right); `urlParams.code/code-course` — there is no `code-course` param in `readUrlParams`; `?session` hydrate block is `:3141–3155` not `:3087–3154`.
- **D2 step 2's pseudocode is still garbled**: "session.type ∈ ruleMap[session.type] against the venue's RAW venue.rawRoomType ∈ ruleMap[session.type]" is not decidable notation. One clean restatement — `ruleMap[session.type].includes(venue.rawRoomType)` with `'/^\s*D/.test(cohort pattern)'` — would make the CORE rule impossible to transcribe wrong (D0 already states it correctly; D2's restatement can be a copy of it).
- **Toast mechanism**: step 5 says the extended text is "set by the fallback path at auto-select time" — naming the actual mechanism (a marker flag consumed by `consumeBookingIntent`'s `:2671–2673` toast, since the pick fires inside step 2d's internal call) would save the implementer one discovery step.
- Append this round's entry to `review-log.md` (no design-round entries have been appended yet).

---

### ⚖️ Review Verdict

The round-2 🔴 A/B/C fixes all verified genuine and mechanism-level — the hydration-consequences design is now sound. But the revision is not yet freezable:

- **🔴-1**: missing `intentCancelled` gate on the fallback arm (contradicts a frozen semantic; no test would catch it).
- **🔴-2**: fifth neutral branch unpinned (no trigger predicate, no string, and the held-cells precedence would misreport the user's own selection as an error-toned occupancy cause).

Both are declarative fixes to `design.md` §D3 (+ two test lines in §D4); no frozen artifact is touched.

**VERDICT: BLOCKED** — outstanding 🔴: (1) fallback lacks the `intentCancelled` short-circuit — discarded auto-selects resurrect on reload; (2) the neutral fifth copy branch has no trigger predicate or frozen string, and as specified the in-the-way manual-selection case misreports as "a block already holds it". Fix these two in design (declarative), then re-review — remaining 🟡s (day-end/held discriminator bound, capacity caveat in D6, fallback placement) are strongly recommended but non-blocking.
