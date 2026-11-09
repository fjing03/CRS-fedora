# design — `arrangement-week-keep-on-subject-switch`

Baseline: `explore-brief.md` (8 rulings, measured) + frozen `proposal.md`.

## 1. Where the change lives

One page, one place: `resources/views/livewire/replacement-arrangement.blade.php`.

| # | Site (today) | Change |
|---|---|---|
| 1 | `applySubjectChange()` :3652–3658 — the `if (firstConflictRow && window.__bootDone && typeof weekNav !== 'undefined') { weekNav.selectWeek(firstConflictRow.week); … }` block | replaced by **the decision** (D3): compute the target week, and call `weekNav.selectWeek(...)` only when the target differs from the current week |
| 2 | new page-local helpers beside `venueEligible()` / `venueMatrixAllows()` | `weekBookableForSubject(weekIdx)` and `firstBookableWeekForSubject()` |
| 3 | `window` surface (read-only, next to `window.__venueSelected` :4530) | `window.__switchWeek` — the two helpers, for the Playwright receipt and for debugging |

Nothing else in the file changes. No new event, no Livewire action, no server call.

## 2. The predicate — "bookable for this subject"

`weekBookableForSubject(weekIdx)` = **date rule** AND **at least one free cell in the settled room**.

### 2.1 The date rule (unchanged, reused)

`weekHasBookableSlot(weekData, weekIdx)` — not Sunday, not a holiday, not inside the 3-working-day
window. Same helper the week selector's own filter uses (`buildWeekNav` :1264), so the decision and the
visible week list can never disagree.

### 2.2 The free-cell rule (new, mirrors the grid)

**The payload is a TAKEN register, not a free list.** `venueSlotsByWeekPayload()`
(`app/Livewire/ReplacementArrangement.php:588–595`) emits a row **only** for `time_slots` with
`status IN ('occupied','pending')`, as `[dayIdx, hourIdx, status, holderLabels]` under
`weekIdx → roomCode`. A half-hour with **no row** is exactly a candidate free cell: the grid's
`classifyState()` returns `null` there and paints `.cell-available` (blade :1800, :1900, :1969–1977).
So availability = **absence of an occupancy row**, guarded by the gates below.

For the settled room, the week is bookable when there exists some `(dayIdx, hourIdx)` in the grid span
(days from `weekData[weekIdx].days`, hours from the shared `hours` array, 08:00–18:30) where **all** of
these hold:

| Gate | Source (the grid's own code) |
|---|---|
| the day is not Sunday and not a holiday | `weekData[weekIdx].days[dayIdx].sunday / .abbr === 'Sun' / .holiday` |
| not inside the 3-working-day window | `isSlotTooSoon(weekData, weekIdx, dayIdx)` |
| the room admits this class | `venueEligible(venueRecord, courseCtx())` — the same function the venue menu filters with (:3774) |
| the room is not blocked for this occurrence | `(MockData.blockedVenues[sessionKey] || []).includes(roomCode)` |
| no busy-vector hit at that half-hour | `(MockData.busyByWeek[sessionKey][weekIdx] || []).some(b => b[0] === dayIdx && hourIdx >= b[1] && hourIdx < b[2])` |
| **no occupancy row at that half-hour** | `!(MockData.venueSlots[weekIdx]?.[roomCode] || []).some(r => r[0] === dayIdx && r[1] === hourIdx)` |

`sessionKey` = `String(firstConflictRow.sessionId)` — the occurrence-grain key the grid uses
(:1811–1813). `venueRecord` = `(MockData.venues || []).find(v => v.code === firstConflictRow.venue)`.

**Why the span walk and not just the rows:** the rows say where the room is *taken*; the free signal is
their complement inside the grid span, so the walk is over the span (6 × 22 = 132 lookups) with a
per-week taken-set built once from the room's rows. Scanning only the rows (the first draft of this
design) would have inverted the meaning and kept a week whose room is booked solid — the very failure
D2 exists to prevent. Cheapest shape: build the taken set per week, then short-circuit on the first span
cell that passes.

**Fail-open, scoped to the free-cell half only.** If any free-cell input is missing (`venueSlots`,
`ruleMap`, `busyByWeek`, `blockedVenues`, the venue record, or `courseCtx()`), the free-cell half
returns `true` (treat as bookable → keep the week). The **date rule is evaluated first and is never
fail-open** — so a payload glitch can never keep a week the selector itself hides (D5), and the
"determination can never disagree with the week list" guarantee in §2.1 holds.

### 2.3 The settled room

`firstConflictRow.venue` — the pinned occurrence's own room. It is the exact value the existing
post-switch tail compares (`s.venue === firstConflictRow.venue`, :3704–3710) and the value the venue sync
ends on (`selectSlot(..., {auto, subjectSwitch})` → commitSlotSelection's original-venue sync; the
no-matching-row fallback is `syncVenueFamily(firstConflictRow, 'auto-pin')`, :4057–4078).

**Honest exception:** `syncVenueFamily` (:4057–4078) settles on `filtered[0]` when the pinned room is
not in the menu's filtered list — and on the **current** venue when that one is still eligible (:4072–4075)
— so an *ineligible* pinned room would settle elsewhere. Brief §2 fact 4 measured that this cannot happen
for a pinned class (its own room is always eligible for its own type), so the predicate reads
`firstConflictRow.venue`. **No new venue logic**: the venue settles exactly as today —
see §6 ordering.

## 3. The decision

```
function decideSwitchWeek(firstConflictRow)        // called inside applySubjectChange
    kept = weekNav.currentWeek
    if (weekBookableForSubject(kept, firstConflictRow)) return { week: kept,     why: 'kept' }
    earliest = firstBookableWeekForSubject(firstConflictRow)
    if (earliest !== null)                             return { week: earliest, why: 'earliest' }
    return { week: kept,                               why: 'none' }   // silent, stay put
```

`firstBookableWeekForSubject()` walks `weekIdx = 0 … weekNav._semester.weeks - 1` and returns the first
index the predicate accepts, else `null`. It mirrors `firstBookableDay()` (ui-common :188) but with the
subject-aware predicate instead of the date-only one, and it is page-local on purpose (a second page
does not need it; `ui-common` stays untouched — proposal non-goal).

Call-site shape:

```js
if (firstConflictRow && window.__bootDone && typeof weekNav !== 'undefined') {
    const target = decideSwitchWeek(firstConflictRow);
    if (target.week !== weekNav.currentWeek) {
        weekNav.selectWeek(target.week);
        currentWeek = weekNav.currentWeek;
        window.currentWeek = currentWeek;
        saveCurrentWeek();
    }
    // unchanged when the week is kept: the tail's saveCurrentWeek()/buildTimetable() still run
}
```

Note: `selectWeek()` itself saves and rebuilds, exactly as today; when the week is kept we skip
`selectWeek` (no needless rebuild and no selector churn) and the existing tail (`saveCurrentWeek()`,
`buildTimetable()` under `window.__bootDone`) does its normal work.

### 3.1 The full state table (what the design must not change)

| State at switch time | Behaviour |
|---|---|
| `firstConflictRow === null` (the new subject has no clashing occurrence) | block never entered — picker-first, no pin, no jump (today's behaviour) |
| `window.__bootDone === false` (pre-boot arrival) | block never entered — the landing rule owns the week |
| kept week bookable | **kept** (the change) |
| kept week not bookable, some week is | **earliest bookable** (the change) |
| nothing bookable | kept, silent (the change) |
| booking intent armed | `consumeBookingIntent()` still runs later in the function and may set the week it was given — **the intent still wins** (proposal §"States this change does NOT touch") |

## 4. Data flow (unchanged except the one decision)

```
onSubjectChange()  →  applySubjectChange(code)
   clearPreview()
   extractSlotsForSubject(code)                    // occurrences, sorted week/day/start
   pinnedOccurrence = {sessionId, week+1}          // auto-pin (kept, D1)
   currentSessionId = pinned session               // → courseCtx() / sessionKey
   renderSlotPicker(slots); slotTriggerText = …    // the strip still names the pinned class (kept)
   decideSwitchWeek(firstConflictRow)              // ← NEW decision, uses firstConflictRow.venue
   renderTitleSummary(); buildVenueFilter()        // buildVenueFilter re-filters using courseCtx()
   consumeBookingIntent()                          // unchanged, still after the decision
   selectorTail: selectSlot(pin, {auto,subjectSwitch}) | syncVenueFamily(...)   // venue settles here
   buildTimetable()                                // renders the week the decision left us on
```

Server → client inputs used (all already on the page): `PAGE_DATA.venueSlots`, `PAGE_DATA.busyByWeek`,
`PAGE_DATA.blockedVenues`, `PAGE_DATA.venues`, `window.__ruleMap`, `weekData`, plus the shared helpers
`weekHasBookableSlot`, `isSlotTooSoon`, `venueEligible`.

## 5. Test strategy (D8)

`tests/arrangement-conflict-autoselect.spec.ts` — rewritten, run against the testing DB only.

| Scenario | How it is driven |
|---|---|
| 1. kept week bookable → week stays | session 99 arrival → move by hand to Week 12 → switch to AMSE2003 → expect the week still Week 12, pin unchanged, slot box names the Week 14 class |
| 2. kept week not bookable → earliest | SQL: fill the **post-switch settled room** (`firstConflictRow.venue` of the switch target — not the currently shown subject's room) for the kept week — one `time_slots` row per half-hour with `status = 'pending'`, swept after → switch → expect the earliest bookable week (which is later than the kept one) |
| 3. the walk skips a clash week that has no free cell | keep the user on session 67's clash week (week 6), fill the settled room there, then switch → the walk must pass **over** week 6 and land on the next week with a free cell (week 7). Asserting only "not week 9" would not exercise the skip |
| 4. nothing bookable → stay, silent | pure-function check via `window.__switchWeek` (no UI path can reach it: the selector never sits on a hidden week). Stub the in-page **taken register** so the settled room is fully taken in **every** week (one `[day, hour, status]` row per half-hour, per week — an *empty* register would mean the opposite: maximally free), call the decision, expect `why: 'none'` and the kept week returned |

Fixture discipline (unchanged from today's spec): `CRS_MODE=testing CRS_ALLOW_SQL=1
CRS_BASE=http://127.0.0.1:8030`, testing DB only, delete-then-insert keyed by exact `session:week`,
sweep in `afterAll`, never a demo DB. The existing pin-identity assertions stay; every "the grid jumped
to the occurrence's week" assertion flips to "the week stayed".

Manual receipt (demo DB): session 99 → Week 12 → switch to AMSE2003 → Week 12 kept.

## 6. What must stay byte-identical

- The auto-pin (`window.pinnedOccurrence`, `currentSessionId`) and the slot-box label.
- The post-switch tail: `selectSlot(..., {auto:true, subjectSwitch:true})`, its `syncVenueFamily`
  fallback, the venue sync, and the URL truth.
- `saveCurrentWeek()` / `buildTimetable()` calls and their order.
- `consumeBookingIntent()` position and behaviour (it runs after the decision — it must remain able to
  override the week when an intent is armed).
- `WeekNavigator`, `weekHasBookableSlot`, `firstBookableDay`, `ui-common.js` — untouched.
- Every other week-jump path: pinned arrivals, booking-intent resolve, saved-block restore, arrows,
  dropdown, "Earliest bookable".

## 7. Cost and risks

- **Cost:** one predicate call per switch + at most 14 week probes on the miss path. Each probe builds a
  taken-set once from that week's room rows, then walks the 6 × 22 span with a short-circuit — array
  lookups only. No async, no debounce, no measurable effect on the switch.
- **Risk 1 — stale-looking pin (accepted by D1):** the strip may name a week other than the visible one;
  the strip carries its own week label. Mitigation: none needed.
- **Risk 2 — predicate drift from the grid's painting:** mitigated by reusing the grid's own gates
  (`venueEligible`, `isSlotTooSoon`, `blockedVenues`, `busyByWeek`) rather than re-deriving them.
- **Risk 3 — the miss path can move the user backward** (kept Week 12 unbookable → earliest Week 8):
  that follows D5 as ruled, and the week stays inside the visible list. Noted in the page changelog so it
  is not read as a regression.
- **Rollback:** revert the one commit; no data, schema or config involved.

**Note for the implementer:** `venueEligible()`'s capacity arm duplicates engine Vector 4, which
`blockedVenues` already encodes per session. Harmless (same rule twice) — keep it and comment it, do not
"fix" the duplication into drift. `window.__switchWeek` is a receipt/debug surface only (house pattern:
`window.classifyCell`), not page UI.

## 8. Open question carried from the proposal review

**Armed intent ordering** (proposal review 🟡3): a subject switch executed while a booking intent is
armed must let the intent keep owning the week. The design satisfies it by leaving
`consumeBookingIntent()` where it is (after the decision). The implementation must not move it above
the decision, and the receipt must not assert "kept" for an intent-armed switch.
