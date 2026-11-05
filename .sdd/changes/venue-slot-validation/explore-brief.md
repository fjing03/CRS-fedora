# Explore brief — venue-slot-validation

Source brief: `prompts/Venue Timetable Slot Validation.md` (page `venue-timetable-ui`).
Explored 2026-11-06 against demo data (`class_replacement_fresh`, lecturer 5425, venue B002).

## The reported case, measured

B002, Week 9 (Mon, 16 Nov 2026) — `time_slots` for day 0:

| Slot | State |
| --- | --- |
| 13:00–13:30 | available |
| 13:30–14:00 | available |
| 14:00–15:00 | occupied `MPU-3232 (L)` |

5425's arrange pool at B002 (conflicted + venue-admitted): `AMSE2003 (L)` 2 h, `AMSE2003 (T)` 1 h,
`AMSE1003 (L)` 2 h → the shortest is 1 h.

Driving the live page reproduced the complaint exactly:

| Cell | Grid state | Pop-up after Arrange |
| --- | --- | --- |
| 13:30 | `cell-no-room` ("Not Enough Slot") | — (click is refused, toast) |
| 13:00, subject `AMSE1003` (2 h) | open | **"None of your classes can run in B002 at this cell"** immediately |
| 13:00, subject `AMSE2003` (1 h Tutorial) | open | Tutorial listed, Arrange available |

So the rule was right but the **order** was wrong: `refreshSessions()` filters each subject's
sessions by duration-fit and, when nothing survives, prints the warning **before** the user has
chosen a class. The user reads that as "the slot is broken", when it only means "the subject you
happen to be looking at is too long".

## Decision chain (what the new brief settles)

1. **Hover must not assume a duration.** §5: "Do not reject a slot based on an assumed class
   duration." §2: "Continue checking the minimum required slot length of **one hour**."
   → the hover floor is a fixed **1 continuous hour (2 half-hour slots)**, not the previous
   shortest-class-of-the-pool number.
2. **Validation runs after the class is picked** (§2 Stage 2) and fails into a **warning modal**
   (§3), never into the pre-selection notice.
3. Everything else about the pickers, the tooltip and the handoff URL is frozen (§5).

## Rejected approaches and why

| Approach | Why rejected |
| --- | --- |
| Keep the pre-filter but soften the copy | The brief forbids the warning at Stage 1 in any wording ("Do NOT show the NOT ENOUGH FREE TIME BELOW warning at this stage"). |
| Grey out non-fitting rows in the session dropdown | The brief freezes the picker flow and asks for the check "after class selection"; a greyed row is the same early decision in another costume. |
| Keep `minClassSlots` as the hover floor | It is exactly the "assumed class duration" §5 forbids; it also made the hover change when an unrelated class left the pool. |
| Browser `alert()` | Explicitly forbidden (§3). |
| Validate in PHP on submit only | Too late — the user would land on the arrangement page before learning the slot is too short; §3 wants the warning on this page, before any navigation. |

## Final solution (facts an implementer must not re-derive)

- **Hover floor (Stage 1):** `MIN_BOOK_SLOTS = 2` fixed (1 h). A free cell is `cell-no-fit` when
  `hi + 2 > hours.length`, `cell-no-room` when the room to the next event is `< 2`. The
  `minClassSlots` payload/helper from `venue-timetable-bookable-cells` becomes dead and is removed.
- **Stage 2 gate:** `confirmIntentSession()` (the Arrange button) validates the **picked row**:
  1. `hi + durationSlots > hours.length` → day-end warning;
  2. overlap with `eventsData[currentWeek]` → span warning;
  3. otherwise the existing handoff runs **unchanged** (same URL contract).
- **Session rows carry the duration:** `data-slots` on `.session-dd-item`.
- **Rows are never filtered:** every session of the selected subject is listed; the first
  fitting row is pre-selected when one exists (keeps the old convenience), else the first row.
- **`intentContext` gains `di` and `hi`** (plus the venue code) so the post-selection check does
  not need a closure.
- **Warning modal:** new `#slotWarningOverlay`, warning icon, `#slotWarningHead` /
  `#slotWarningBody` / `#slotWarningGuide` / `#slotWarningTail`, ONE close action
  ("Pick another slot") + the header ×. Span copy keeps the brief's mandated sentence:
  title `None of your classes can run in <venue> at this cell`, body
  `<start> plus the selected class duration (<duration>) runs into an existing event below`
  (with the next class's start appended when known), guide `NOT ENOUGH FREE TIME BELOW`,
  tail `— pick a Free Cell.`
- **`#intentNotice` survives only for the room-type case** (`fitting.length === 0`: "this room
  type hosts only …") — a fact about the venue, not about a duration.

## Known open questions

- Should the *day-end* warning keep a distinct title? Chosen: yes, "This class does not fit
  before the day end (18:30)" — the brief only mandates the span wording, and reusing "None of
  your classes" for a past-the-day-end class would be untrue (an earlier cell would work).
- The warning closes back to the grid (brief §3), not back to the pickers; a user who wants
  another class at the same cell re-clicks the cell.
