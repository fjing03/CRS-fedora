# explore-brief — `arrangement-week-keep-on-subject-switch`

> Source: `prompts/Week Selector Auto-Selection When Switching Subjects.md` (user brief, 2026-11-07)
> + an `/sdd-explore` grilling session with the user on **2026-11-08** (8 decisions, all answered).
> Investigation only — no code was written for this lane.

## 1. The problem, measured

On **`/replacement-arrangement`** (Replacement Arrangement page), changing the **Subject** dropdown
makes the page jump the **Week** dropdown to the new subject's first clashing class, throwing away the
week the user was reading.

Reproduced live on the demo DB (staff 5425, `:8010`, 2026-11-08):

| Step | Subject | Week shown | Slot box |
|---|---|---|---|
| Open for AMSE1003 (session 99) | AMSE1003 | Week 8 | `Week 8 · Nov 9, 2026 · 9:00 AM ~ 11:00 AM` |
| User browses to **Week 12** by hand | AMSE1003 | **Week 12** | unchanged |
| Switch subject → AMSE2003 | AMSE2003 | **Week 14** ← the jump | `Week 14 · Dec 24, 2026 · 9:00 AM ~ 11:00 AM` (auto-ticked) |

The brief's own example (`?session=67&week=14` → switch to BMSE2163 (P)) shows the same call, but the
jump is only *visible* when the user was viewing a different week.

**Where the jump lives** — `applySubjectChange()` in
`resources/views/livewire/replacement-arrangement.blade.php`:

| Line (approx) | What it does |
|---|---|
| 3614–3616 | POST-boot subject switch **auto-pins** the earliest conflicted occurrence (`window.pinnedOccurrence`, `currentSessionId`) |
| 3636–3639 | writes the slot-box trigger text from that pinned row (`slotTriggerLabel`) |
| **3652–3658** | **the jump**: `weekNav.selectWeek(firstConflictRow.week)` + `currentWeek`/`window.currentWeek` + `saveCurrentWeek()` |
| 3704–3723 | the tail auto-**commits** the pin-matching picker row (`selectSlot(..., {auto:true, subjectSwitch:true})`) — which is also what syncs the **venue** to the class's own room (the no-rows fallback is `syncVenueFamily(...)`) |

The pre-boot guard (`window.__bootDone`) means pinned **arrivals** never jump — only post-boot subject
switches do.

## 2. Facts the grilling established (measured, not assumed)

1. **"Bookable" today is date-only.** `weekHasBookableSlot(weekData, i)` (`public/js/ui-common.js:150`):
   not Sunday, not a holiday, not inside the 3-working-day cut-off. It never looks at the subject, the
   room, or the conflicts.
2. **The week dropdown already hides unbookable weeks**, and `WeekNavigator.selectWeek()` (ui-common
   `:520`) silently steps forward to the nearest visible week, staying put when none is visible. Any
   "keep the week" decision must therefore be made **before** calling `selectWeek()`.
3. **The page never sits on a hidden week.** Forcing the remembered week to Week 3 (a past week) makes
   the page open on **Week 8** (the first listed week) — verified.
4. **The 2026-10-24 "whole grid red" bug is fixed and NOT reachable any more.** Measured: a Practical
   class settles on B010 and its room menu offers **Lab only**; a Lecture settles on B100 and offers
   **Tutorial / Lecture Hall only**. Two independent guards: the venue-sync tail (3704–3723) and the
   type-filtered venue menu. So "wrong room ⇒ every week red" can no longer happen through this page.
5. **The landing rules** (boot, ~4491–4505): a **pinned** arrival (`?session=`) always lands on the
   **earliest bookable** week (even over a remembered week); a plain visit with a remembered week
   resumes it; otherwise earliest bookable. Verified: memory = Week 11 → page opens on Week 11.
6. **Free cells exist in every week** on the current demo data for AMSE2003 (L): week 8 → 40, week 12
   → 75, week 14 → 54 green cells (so the "no free cell" case is rare, but exists in principle).
7. **A week may legitimately hold a clash of the subject itself** — week 14 holds AMSE2003's own
   Thursday class (Christmas Eve holiday) **and** 54 free cells.
8. **Every other week-jump path is out of scope**: booking-intent resolve (3202), saved-block restore
   (3470), booking-intent snap-back (3526), the "Earliest bookable" button (4627), arrows/dropdown.

## 3. Decisions (user's answers, 2026-11-08)

| # | Question | Ruling | Why the alternatives lost |
|---|---|---|---|
| **D1** | What happens to the auto-pin + auto-commit? | **A — keep both; stop only the week jump** | B (prefer a clash inside the kept week) adds rules for a case the strip already explains; C (drop the pin) reverses the 2026-10-19/20 and 2026-11-01 rulings and adds a click to every switch |
| **D2** | What does "bookable for the new subject" mean? | **B — the date test AND ≥1 free cell in the room the page settles on** | A (date test only) could keep a week with nothing bookable; C (any allowed room) could keep a week whose on-screen room is full, making the screen all red |
| **D3** | A kept week that holds the subject's own clash? | **A — still bookable if it has any free cell** | B would discard the very week the class sits in (week 14: 54 free cells) |
| **D4** | What is "the previous viewed week"? | **A — whatever the selector shows at switch time** (remembered / landed / chosen by hand) | B (only a hand-chosen week) would move the user away from the week on screen — the exact complaint |
| **D5** | The kept week is not bookable (edge: page open across the cut-off) | **A — treat as unbookable → earliest bookable** | B (reveal the hidden week) would put a week in the list the page normally hides — two rules disagreeing |
| **D6** | No bookable week at all (end of semester) | **A — leave the week unchanged, silent** | B/C jump to an arbitrary week; the 2026-10-20 "silent by decision" ruling stands |
| **D7** | Scope | **A — the subject switch only**; keep the booking-intent snap-back | B would break the "book this slot" flow, which deliberately owns the week |
| **D8** | Receipts | **A — rewrite `tests/arrangement-conflict-autoselect.spec.ts`** (keep its clash fixtures + pin identity checks, flip the week checks, add the four scenarios) | B would leave a spec asserting the opposite behaviour, with duplicated fixtures |

## 4. The rule to implement (one sentence)

> On a post-boot **subject switch**, keep the week the selector is on **if** that week is bookable for
> the newly selected subject — meaning it passes the existing date test **and** has at least one free
> cell in the room the page will settle on — otherwise move to the **earliest** such week; if no week
> qualifies, leave the week untouched and say nothing.

Nothing else changes: the earliest clash is still pinned, the slot box still names it, it is still
auto-committed, the venue still syncs, and every other navigation path is untouched.

## 5. Mapping tables (complete, no "e.g.")

### 5.1 "Bookable for the subject" — the predicate the change must add

| Check | Source today | Needed? |
|---|---|---|
| Not Sunday | `weekData[i].days[d].sunday` / `abbr === 'Sun'` | reuse (`weekHasBookableSlot`) |
| Not a holiday | `weekData[i].days[d].holiday` | reuse |
| Not inside the 3-working-day cut-off | `isSlotTooSoon(weekData, i, d)` | reuse |
| Not in the past | covered by `isSlotTooSoon` / the hidden-week list | reuse |
| Room admits the class type (venue matrix) | `venueMatrixAllows(type, rawRoomType, venueCode, moduleCode, cohortCodes)` | **new use** at week level |
| Room not blocked for this session | `(MockData.blockedVenues[sessionKey] || []).includes(venue)` | **new use** |
| No busy-vector hit for this session in that week | `(MockData.busyByWeek[sessionKey] || {})[week]` → tuples `[day, from, to, …]` | **new use** |
| At least one cell passing all of the above | — | **the new predicate** |

The busy/blocked data is already per **session** (`currentSessionId`) and per **week**, which is what
the grid paints (blade ~1816–1818: `engineBlocked`). The same three inputs give "free cell".

### 5.2 "The room the page settles on"

`firstConflictRow.venue` — the pinned occurrence's own room (the same value the tail's pin matcher
compares: `s.venue === firstConflictRow.venue`). **Ordering note for the design:** the venue only moves
inside the tail (`selectSlot` → venue sync, or `syncVenueFamily`), i.e. *after* today's week jump. The
week decision must therefore either (a) be computed against `firstConflictRow.venue` up front, or
(b) run after the pin is committed. (a) is the smaller change and cannot disagree with the strip.

### 5.3 Subject-switch decisions vs the existing states

| State at switch time | Today | After D1–D8 |
|---|---|---|
| No subject before (`firstConflictRow === null`) | picker-first, no jump | unchanged |
| Pre-boot arrival (`window.__bootDone === false`) | no pin, no jump; landing rule owns the week | unchanged |
| Post-boot switch, kept week bookable for the new subject | jumps to the clash week | **stays on the kept week**; pin + auto-commit unchanged |
| Post-boot switch, kept week not bookable | jumps to the clash week | **earliest bookable week** (may or may not be the clash week) |
| Post-boot switch, nothing bookable | jumps to the clash week | week unchanged, silent |
| Booking intent armed | intent snap-back owns the week | unchanged (D7) |

## 6. Cross-module data flows touched

```
onSubjectChange()                     (blade, <select id="subjectSelector">)
  └─ applySubjectChange(code)
       ├─ clearPreview()
       ├─ extractSlotsForSubject(code) → candidates (sorted week, day, start)
       ├─ [existing] window.pinnedOccurrence = {sessionId, week+1}; currentSessionId = …
       ├─ renderSlotPicker(slots); slotTriggerText = slotTriggerLabel(firstConflictRow)
       ├─ [CHANGED] decide the week:  keep | earliest-bookable | leave
       │     ├─ needs: weekData (generateWeekData), MockData.busyByWeek[sessionId][week],
       │     │         MockData.blockedVenues[sessionId], window.__ruleMap,
       │     │         venueMatrixAllows(), isSlotTooSoon(), weekHasBookableSlot()
       │     └─ [existing] saveCurrentWeek(); buildTimetable()
       └─ [existing] selectSlot(pin, idx, {auto, subjectSwitch}) | syncVenueFamily(…)
                        └─ commitSlotSelection → venue sync (round 2/3), URL truth
```

Server side: **none**. The page is a client-side grid over `PAGE_DATA` payloads
(`busyByWeek`, `blockedVenues`, `ruleMap`, `courses`); no backend change, no schema change, no
migration, no new dependency.

## 7. Acceptance criteria

1. Switch subject while viewing a week that is bookable for the new subject → **the week does not
   change**; the slot box still names the auto-pinned clash and it stays ticked (D1).
2. Switch subject while viewing a week that is **not** bookable for the new subject (no free cell in
   the settled room) → the week becomes the **earliest bookable** one.
3. Several weeks clash for the new subject → the earliest-bookable search **skips** the weeks with no
   free cell (it is not "the first clash's week").
4. No bookable week at all → the week stays as it was, no message, no crash (D6).
5. A kept week that contains the subject's own clash is **kept** when it has a free cell (D3).
6. Pre-boot arrival with `?session=…&week=…` → landing rule unchanged (earliest bookable), pin intact.
7. Booking-intent flows (`?venue&date&time`) → unchanged (D7).
8. Venue, slot picker, submit path, URL truth and the auto-commit → byte-identical.
9. All four brief scenarios pass, plus the existing pin-identity checks in the rewritten spec.

## 8. Receipts plan (D8)

`tests/arrangement-conflict-autoselect.spec.ts` — rewrite:

- **Keep**: the fixture setup (cs 67 @ week 6, cs 23 @ week 9, testing DB only) and the
  "which session got pinned" assertions per switch (`{sessionId: 67, week: 6}`, then `23 / 9`, then
  back to `99 / 8`).
- **Flip**: every "the grid jumped to the occurrence's week" assertion → "the week stayed where the
  user was".
- **Add**: the four brief scenarios (kept week bookable / not bookable → earliest / several clashes
  skipped / nothing bookable), driven by moving the week by hand before the switch.
- Guard rails to respect: `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`; the spec
  writes `class_exceptions` rows and sweeps them; run it against the seeded testing DB, never a demo
  DB; `TestingFixtureSeeder` traps apply if it is used (it deletes requests).

## 9. Open questions for `/sdd-propose`

1. **Where to put the predicate.** A new shared JS helper in the blade (page-local, since it needs the
   page's `MockData` surfaces) vs a `ui-common.js` helper next to `weekHasBookableSlot`. Recommendation:
   page-local function in the blade + a thin `ui-common` helper only if a second page ever needs it.
2. **Cost.** The check scans 6 days × up to 20 half-hour cells for the settled room, on every switch —
   trivial, but worth noting so the design states it (no debounce needed).
3. **Terminology in the UI.** No new label is planned. The slot box already names its week, which is
   how the user learns the pin may live in another week (D1's accepted trade-off). Confirm no hint
   text is wanted.
4. **A second witness.** The rewritten spec is the only automated receipt; if a quick manual receipt is
   wanted, the demo script is: session 99 → Week 12 → switch to AMSE2003 → expect Week 12 kept.
