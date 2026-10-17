All verifications complete. Here's the report.

## 🔍 Review Report: venue-page-book-affordance — design.md round-2 confirmation

**Round context:** design.md round-2 confirmation review; proposal.md is FROZEN (4 rounds). Prior real rounds: proposal R1–R4, design R1 (both real sdd-reviewer), design R2 + tasks R1 (implementer self-reviews, honestly flagged NOT independent). Prior-fix verification was in scope.

### Verification performed (against source, not just prose)

**Claimed round-1 fixes — all confirmed actually fixed:**
- ✅ **🔴 Clock-anchor bridge (design R1):** end-to-end chain re-verified in source. `mock-data.js:62` sets `MockData.mockNow = new Date(2026, 9, 5)`; layout loads the bridge on every page; `getTodayMs()` (`ui-common.js:2206`) is falsy-safe on `null` → real clock; the 4 `mockNow = null` precedents exist exactly where cited; no *other* anchor dependency is silently affected — the venue blade overrides `semester`/`holidays` from the real DB (:111–112), so nulling only shifts which week opens, exactly as D1 declares. Crucially, the bridge placement in T5 (next to :111–112) is **before** both `generateWeekData()` (:114, which itself consumes `getTodayMs()` at `ui-common.js:57`) and `currentWeekIndex()` (:119) — the only correct order.
- ✅ **🔴 D2 closure placement:** `venueInfo` (:126), `weekData` (:114–115), `currentWeek` all live inside the `DOMContentLoaded` closure (:110–308); `day.abbr` is in scope for the ported aria-label (cellRender loop binds `day`).
- ✅ **🟡 TC70 mechanism correction:** verified — `tests/venue-timetable.spec.ts:709` expects `#eventModal`/`#cancelClassOverlay`/`#confirmCancelClassBtn`; the live blade has only `classModal` (:154/:166/:294) and zero cancel-class machinery. "Dies before the write today; guard = defense-in-depth against selector-repair" is accurate.
- ✅ **🔴 Baseline ordering (self-review fix):** tasks T2 explicitly pins "PRE-CHANGE blade ... Must run before T4" with the contamination rationale. Verified landed.
- ✅ **🟡 D6/tasks drift (self-review fix):** the optional `focusedCell = null` hardening is present in T4 with its changelog-note condition.
- ✅ Task-level ⛔ TC70 guard + apply-gate banners exist at the top of tasks.md.
- ✅ **Only blade: T5 adds `mockNow = null;` next to the existing real-data bridge lines (:111–112), design D1 declared behavior change** before both `generateWeekData()` (:114, which itself calls `getTodayMs()` and would bake the anchor into week flags) and `currentWeekIndex()` (:119).

**Scope conformance:** design touches exactly the proposal's 1 runtime file; test/config/docs inventory matches the frozen impact scope; all non-goals respected (D1's port correctly **omits** the frozen template's mobile-card appends, verified in source at :894+; no arrow-nav, no banner, no consumer code — D4 verified: `BLOCK_SPAN :1190`, `resolveBookingIntent :2472`, null-bridge precedent `:1184`). D0 line cites spot-checked against the frozen template (`:861–884` branch structure, `BOOK_SPAN :1044`, tooltip `:1046–1085`, no-fit guard `:872`, `BOOK_SPAN * 30` toast `:1048`) — all accurate. Shared-infra cites verified: `gridScroll` id via `partials/ui-grid-table.blade.php:2/:10`, `toast = new ToastManager()` `ui-common.js:2603`, `theme.css .cell-available :2077` / `.cell-too-soon :2197`, live dead CSS `:16–22`, legend tip `:79`, hint `:102`. Internally, SC1–SC7 all map to design sections; every proposal in-scope item and copy string is covered.

### 🔴 Critical Issues (implementation blockers)

**None found.** I looked hard at the four declared decisions the review round targets — the `mockNow` bridge, byte-faithful quirks, TC70 handling, closure placement — and none sends implementation the wrong direction. The bridge decision is not only sound but necessary: without it, the anchor-feed mismatch (producer Oct-8 vs consumer Oct-16 cutoff) fails proposal SC3's honesty requirement on the very week the page opens; the declared behavior change is correctly scoped (only week-open/Today/arrows shift — verified nothing else consumes the anchor on this page).

### 🟡 Should Fix (non-blocking, one-line edits — not freeze blockers)

None blocking freeze; two worth landing at/before apply:
- **Tasks.md TC70-guard banner cross-references the wrong task.** Banner says "must not execute in ANY Playwright run until it is retired in **T9**" — but retirement is **T8** ("retire TC70 (BEFORE any full-suite run)"); T9 is TC46/TC49–51. The error direction is conservative (guard over-stays one task), so it can't cause the incident it guards against — but an executing agent following the banner literally could hesitate at T10's "no grep needed post-T8" instruction. Fix in the tasks confirmation round.
- **D5 reconciliation step 1 is silent on pre-change ordering** — the fix for the self-review's 🔴 lives only in tasks T2 ("pre-change blade, before T4"). That's a valid fix location, but for the frozen design to be self-consistent with its own tasks, add the five words "on the pre-change blade" to D5 step 1.

### ✅ What's Done Well

- The clock-anchor bridge analysis is the standout: correct mechanism, correct ordering dependency (bridge before `generateWeekData`/`currentWeekIndex`), honest declaration of the behavior change, changelog + live-verification hooks (T13's "opens on the real current week" canary), and §10.0 bridge-allowance framing.
- TC70 handling is defense-in-depth done right: precise today-mechanism (dies at :709), guard applicable to *any* Playwright run, disposition (retire) before the gate, and T8 correctly ordered before T10's first unfiltered run.
- D6 quirk decisions are honest byte-faithfulness with an explicitly optional, costed hardening rather than silent drift.
- Test plan shows real Playwright literacy: fresh-context-per-test sessionStorage model, grep/setup-project interplay hedged with a concrete mitigation, limiter math, and the venue-db re-point preserving assertion meaning.

### 💡 Optional Suggestions

- **D2's no-fit toast should keep the frozen expression, not the unfolded literal.** Frozen source computes `'A booking needs ' + (BOOK_SPAN * 30) + ' minutes …'` (`:1048`); D2/T5 hard-code `'120 minutes'`. Output is identical for `BOOK_SPAN=4`, but the design's own byte-faithful default argues for keeping the frozen expression and not introducing a gratuitous one-character-class deviation. Cosmetic only.
- **T8 contains editorializing self-negotiation** ("Keep it in T9's run out of habit? No — TC70 is gone; run plain."). Replace with a plain instruction; a checklist carrying inner monologue invites misreading during apply.
- **D5 test 4** says "cells in the real current week render `cell-too-soon`" — an implementer should target non-weekend, non-holiday weekday cells only (Sun/PH rows render other classes; past-weekedge scenarios are covered by `isSlotTooSoon`'s `slot <= today` rule). One parenthetical in the spec task avoids a false-fail diagnosis.
- **Grep/setup-project semantics**: rather than relying on remembered filter semantics, have T1 empirically verify with `npx playwright test --list -g "TC46"`; the design's `--project=setup` mitigation is already the right fallback.

### ⚖️ Review Verdict

**design.md round 2 CONFIRMED — freeze eligible.** Both design rounds' claimed fixes (sdd-reviewer round-1's 🔴/🟡s and the self-review's 🔴s) are verified as actually landed in source and artifacts; D0–D7 stay inside the frozen proposal scope with no contradiction. The two 🟡 items are wording-level, non-behavioral, and best fixed in the tasks.md confirmation round (which this session still requires under the apply gate — tasks.md R1 remains a self-review). No condition attaches to the design freeze itself; `/sdd-apply` remains blocked solely by the pending tasks.md confirmation, per the existing gate.
