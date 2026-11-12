# Spec — cancel-day-cancel

Capability: the shared *Cancel this Class?* modal can cancel **every class the lecturer teaches that day**
in one decision. Every requirement is testable from the DOM after opening the modal on a known class.

## R1 — The block appears only when it has work to do

- R1.1 On a day where the lecturer teaches no other class, **no block is rendered** (no
  `#cancelDayScope` content, no `Also cancel my other 0 classes`).
- R1.2 On a day with N ≥ 1 other classes, the block renders between the Class Information box and the
  Cancellation Reason heading.
- R1.3 The block's sentence is `Also cancel my other <N> classes on <Weekday>` with N the number of
  **ticked** others.

## R2 — Every class is its own choice

- R2.1 Each other class is a row with its own checkbox.
- R2.2 A row reads `h:mm AM ~ h:mm AM · CODE(T) · ROOM · COHORT` in that order (12-hour range, module
  code with the class type in brackets, room code, cohort).
- R2.3 A class with several cohorts prints `<n> cohorts`; the cohort names are available on the hover.
- R2.4 The whole-day checkbox selects all rows; a partly-selected list shows the indeterminate mark.
- R2.5 Unticking every row clears the whole-day box, hides the list and restores `Yes, Cancel Class`.
- R2.6 The block opens folded or unfolded at the user's choice; the chevron folds and unfolds the list
  without changing the selection, the count or the button.

## R3 — The red button always states the size of the decision

- R3.1 With the whole-day box unticked the button reads `Yes, Cancel Class`.
- R3.2 With k other classes ticked the button reads `Yes, Cancel <k + 1> Classes`.
- R3.3 The button stays disabled until a cancellation reason is chosen, exactly as today.
- R3.4 The modal title never changes: `Cancel this Class?` in every state, the success screen included.

## R4 — What is cancelled

- R4.1 The set is every class the caller teaches on that date in that week, across modules, cohorts and
  rooms, **including** the class the modal was opened for.
- R4.2 Occurrences that already hold a `class_exceptions` row (already cancelled, or already moved by an
  approved replacement) are **not** in the set.
- R4.3 Ownership, the 2-working-day window, the holiday/Sunday ban and the reason vocabulary are checked
  **again on the server** for every item; a failure cancels nothing.
- R4.4 The server resolves the set itself; a tampered client list cannot widen it.

## R5 — What happens to requests on those classes

- R5.1 A pending or approved request on a class in the set is closed with it: status `cancelled`,
  `decided_at` set, its slots released, its junction rows deleted.
- R5.2 Such a class carries the amber `1 request waiting` pill in the list, stacked under its cohort.
- R5.3 The success screen states how many requests were withdrawn.

## R6 — The success screen

- R6.1 Reads `✓ <N> classes cancelled`, then the date, the reason and the withdrawn-request count.
- R6.2 Lists every cancelled class in the row format, the class the modal was opened for first, the rest
  in time order; a withdrawn request is a **red** pill (`1 request withdrawn`) under its cohort.
- R6.3 Offers exactly one button: **Close**.
- R6.4 Closing refreshes the timetable from the server, so every cancelled class shows as cancelled.

## R7 — Untouched behaviour

- R7.1 With the whole-day box unticked, the modal behaves byte-identically to today (one class).
- R7.2 The reason list, the "Other" box, the required asterisk, the `CANNOT UNDONE` strip, `No, Keep
  Class` and the refusal hint all keep today's wording and placement.
- R7.3 The *Cancel Class?* button still appears only on the logged-in lecturer's own cancellable classes,
  on all three timetables.

## Scenarios

| # | Given | Then |
|---|---|---|
| S1 | Wednesday, 4 classes that day, opened the 11:00 one | block shows `other 3 classes on Wednesday`; three rows; button `Yes, Cancel 4 Classes`; the 13:00 row carries the amber request pill |
| S2 | the same, but the user unticks the 15:00 row | sentence reads `other 2 classes`, button `Yes, Cancel 3 Classes`, whole-day box shows the half-tick |
| S3 | the same, then the user folds the block | list hidden; tick, `3` and `Yes, Cancel 4 Classes` still shown; unfolding restores the list |
| S4 | Friday, one class only | no block; modal identical to today |
| S5 | the user confirms S1 | 4 exceptions written, 1 request withdrawn with its slots released, one audit row each, students and PL told; success lists 4 with the red pill and one Close |
| S6 | a class in the set no longer passes the window on the server | nothing written; the hint names the refusal; nothing in the grid changes |
| S7 | the same day opened on the Cohort Timetable and on the Venue Timetable | the same block and the same set, including classes the page itself does not show |
| S8 | 390 px wide | the modal is a bottom sheet; rows wrap (time, then class + room, then the stack); nothing clips |
