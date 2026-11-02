# Bug: Conflicted Booking Intent Incorrectly Displayed as Selected

**Test account:** Staff 5425

## 1. Reproduction Steps

1. Log in as Staff 5425.
2. Navigate to `/venue-timetable-ui`.
3. Select:
   - Subject: `AMSE2003`
   - Type: `(L)`
   - Week: `14`
   - Day: Thursday, 24 December 2026
   - Time: 09:00–11:00
4. Click **Arrange**.
5. Confirm the booking intent message:

   `Arrange a replacement in B002 on Fri, 06 Nov 2026 at 09:00?`

6. The system navigates to:

   `http://127.0.0.1:8000/replacement-arrangement?venue=B002&date=06%20Nov%202026&time=09%3A00&from=venue-timetable&code=AMSE2003&session=67&cohort=DSF2%20(S1)%20G1&week=14`

## 2. Reported Issues

### Bug 1 — Booking Intent Shows Conflicting Slot as Pre-selected

The booking intent correctly identifies that the original booked slot is no longer available on the B002 grid because another class occupies it.

However, the message also says:

- `Pre-selected AMSE2003 (L)`
- `the pre-selection stays`

This is misleading because the original booking intent is conflicted.

**Expected behaviour:**

- Clearly indicate that the original booked slot is conflicted/unavailable.
- Do not describe the conflicted slot as pre-selected.
- Tell the user to manually choose another available slot.
- Preserve the subject/session context if required, but distinguish it from an actual selected replacement slot.

### Bug 2 — Summary Card Incorrectly Displays a Selection

The summary card currently displays:

- Venue: `B002`
- Week 7 · Fri
- Date: `06 Nov 2026`
- Time: `9:00 AM – 11:00 AM · 4 slots (2 Hours)`

However, this slot is conflicted and should not be treated as a selected replacement.

**Expected behaviour:**

- Do not display this conflicted booking intent as a selected slot in the summary card.
- Do not create or display an `event-block event-selection` for it.
- Only show summary cards for genuinely selected replacement slots.
- The user should be able to manually select another free slot.

## 3. Investigation Requirements

- Trace how the booking intent is passed from `venue-timetable-ui` to `replacement-arrangement`.
- Check the conflict-detection logic and how it determines whether a slot is pre-selected.
- Investigate why the summary card is created despite the absence of an `event-block event-selection`.
- Check whether the booking intent and saved-selection state are incorrectly sharing data.
- Preserve legitimate pre-selections and existing replacement-arrangement behaviour.

**Important:** Do not simply hide the messages or summary card using CSS. Fix the underlying state/logic so conflicted slots are not treated as actual selections.

Report the root cause, affected files/functions, and proposed fix before implementation. Do not modify code or database records without my approval.
