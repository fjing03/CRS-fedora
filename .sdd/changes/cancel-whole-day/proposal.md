# Proposal — cancel-whole-day

**Date:** 2026-11-11 · **Surface:** the shared *Cancel this Class?* modal · **No code written yet.**
Baseline: `explore-brief.md` (same folder) — the eight user rulings and the measured code facts.

## Why

Cancelling a whole day costs one full trip through the modal per class. Measured on the demo data,
staff 5425 (Surayaini) teaches **4 classes on a Wednesday** — two modules, two cohorts, three rooms.
A lecturer taking a day of leave opens four modals, picks the same reason four times, and confirms four
times. The checkbox makes that one decision, and it does it inside the modal the user already has open,
so nothing new has to be learned.

## User decision record (2026-11-11)

| # | Decision |
|---|---|
| 1 | Its own small block between the grey **Class Information** box and **Cancellation Reason** |
| 2 | The set is **every class the lecturer teaches that day** (all modules, cohorts, rooms) |
| 3 | The label carries the count; the **list of the other classes appears when ticked** |
| 4 | Pending requests on those classes are **cancelled with them** (marked in the list) |
| 5 | Success screen: **✓ N classes cancelled**, the list, and **one Close** button |
| 6 | The block is **hidden** when the day has no other class |
| 7 | Label: **Also cancel my other 3 classes on Wednesday** |
| 8 | The red button reads **Yes, Cancel <N> Classes** while ticked, **Yes, Cancel Class** when not |

**Rev 2 (prototype round, 2026-11-11):**

| # | Decision |
|---|---|
| 9 | **No title change** — *Cancel this Class?* in every state, success screen included |
| 10 | **Every row is its own checkbox**; the whole-day box is a select-all with a half-tick when the list is partly selected |
| 11 | **Row format** `9:00 AM ~ 10:00 AM · AMSE4342(L) · DSF1 (S1) G1`; several cohorts print **`n cohorts`** with the names on the hover |
| 12 | **The label's number follows the ticks** — unticking one reads *"other 2 classes"* and the button *Yes, Cancel 3 Classes*; unticking all closes the list and restores *Yes, Cancel Class* |

**Rev 3 (prototype round, second pass):**

| # | Decision |
|---|---|
| 13 | **The block folds** — a chevron collapses the list; tick, count and button number survive; ticking the whole-day box re-opens it |
| 14 | **The row carries the room code**: `9:00 AM ~ 10:00 AM · AMSE1003(L) · B009 · DSF1 (S1) G1` |
| 15 | **Cohort over mark, stacked right** — amber for a request that will be cancelled, red for one that was withdrawn |

## In scope

1. **The block** in the modal: a whole-day checkbox carrying the count, and — once ticked — the list of
   the other classes that day, **each row its own checkbox**:
   `9:00 AM ~ 10:00 AM · AMSE1003(L) · DSF1 (S1) G1`, with `2 cohorts` + a hover for a shared class, and
   a mark on any row whose request will be cancelled too. The whole-day box selects all; the label's
   number and the red button both follow what is actually ticked.
2. **The set**, computed on the server for the opened class's week and day from the caller's own
   sessions, excluding occurrences that already have an exception (already cancelled, or already moved by
   an approved replacement).
3. **The armed button** naming the selected total (decision 8), and the success screen listing exactly
   the classes that were ticked.
4. **One day-cancel action**: cancel every occurrence in the set with the chosen reason, in **one
   transaction**, all-or-nothing; close any pending/approved request those occurrences hold (the parked
   `bulk-cancel-class` rule), release its slots, and let the existing per-class notification fan-out run.
5. **The success state** for a day cancel: the total, the list, how many requests were withdrawn, and one
   Close. The grid is refreshed from the server instead of being patched four times by hand.
6. **The same behaviour on all three timetables** (My Timetable, Cohort Timetables, Venue Timetable) —
   the modal is shared and the cancel button is already owner-gated there.
7. **A static prototype** of the block, the list and the success screen (decision from the user: the
   prototype comes before any real code).

## Out of scope (deliberately)

- The parked bulk lane's own UI: the semester-bar *Cancel Class* button, the From/To range picker, the
  per-day header ticks, and the `notifications.batch_id` migration. This feature needs none of them; if
  that lane ships later it can build on the same action.
- No email (no cancel path sends mail today).
- No undo — Cancel Class stays FINAL.
- No new status value, no change to the reason vocabulary, the cancel window or the holiday ban.
- No change to the single-class flow's words or layout beyond the block itself.

## One mechanism, not two

The day cancel is the parked lane's `CancelClassBatch` action with a one-day set. To avoid a second
cancel implementation:

- `CancelClassBatch` (new action) owns: ownership per occurrence, the 2-working-day window, the
  holiday/Sunday ban, the reason vocabulary, duplicate detection, request closure, audit, notifications,
  all-or-nothing.
- `CancelClass` (today's single path) keeps its signature and delegates to the same action with a
  one-item set, so both paths cannot disagree.
- The request closure is the parked lane's rule word for word: status `cancelled`, `decided_at` set,
  slots released through the shared `ReleaseRequestSlots`, junction rows deleted.

## Approach (implementation sketch)

| Layer | Work |
|---|---|
| Read | `#[Renderless] myDayClasses(int $week, int $day)` on `app/Concerns/CancelsClasses.php` → the caller's own classes that date with `sessionId`, start/end, code, type, cohort labels, room, `request` status, and `cancellable` + `why`. One implementation serves all three pages |
| Action | `app/Actions/CancelClassBatch.php` (new) + `app/Actions/ReleaseRequestSlots.php` (new, extracted from the three existing callers) + `CancelClass` delegating |
| Bridge | `#[Renderless] cancelClassDay(int $week, int $day, string $reason, ?string $otherDetail)` on the same concern → the action; returns `{ok, cancelled: [...], requestsClosed: n, hint?}` |
| Client | `CancelClassModal`: fetch the day set when the modal opens, render the block + list, gate on the tick, retitle the red button, send the day call, render the success list |
| CSS | the block, its list and the request mark — existing tokens only, no new colours |
| Prototype | `ui-prototype/cancel-whole-day.html` — the block, ticked state, list, button, success screen |

## Sequencing (one concern per commit)

1. `refactor(oop): one slot-release action` — `ReleaseRequestSlots`; the three existing callers use it;
   their tests are unchanged and green (no behaviour change).
2. `feat(cancel): a class cancel closes the request it holds` — `CancelClassBatch` + `CancelClass`
   delegating + the request-closure rule + PHPUnit receipts.
3. `feat(cancel): the server can name my classes for a day` — the two renderless methods + receipts.
4. `ui(cancel): the modal can cancel a whole day` — the block, list, gated button text, day confirm,
   new success state + the Playwright receipt (enabled/hidden, list, tick, success, 390 px).
5. `docs:` the two changelogs + the checklist/status line.

The prototype (step 0) is a throwaway HTML file under `ui-prototype/`, never committed unless the user
asks.

## Acceptance

1. **Block** — hidden when the day has no other class; otherwise a checkbox whose label names the count
   and the weekday.
2. **List** — appears only when ticked; time order; each line `HH:MM CODE Type — COHORT`; a line with a
   waiting request says so.
3. **Red button** — `Yes, Cancel 4 Classes` while ticked, `Yes, Cancel Class` when not, and it stays
   disabled until a reason is chosen.
4. **Set correctness** — every class the lecturer teaches that day is included except occurrences already
   cancelled or already moved by an approved replacement.
5. **Atomicity** — if any item fails re-checking on the server, nothing is cancelled and the hint names
   the blocker.
6. **Requests** — a pending (or approved) request on a set class is closed with it; the count is reported.
7. **Success** — ✓ N classes cancelled, the list, the number of requests withdrawn, one Close; the grid
   shows all N classes cancelled afterwards.
8. **Both other pages** — the same block and result on Cohort Timetables and Venue Timetable, where the
   set spans cohorts and rooms the page itself is not showing.
9. **Gates** — pint, phpstan 0, full PHPUnit, `node --check`, `view:cache`, Playwright on `:8030`.

## Risks

| Risk | Mitigation |
|---|---|
| A day cancel closes requests students were waiting on | The list marks them and the success text counts them; the parked lane's notification wording says exactly that |
| The set silently omits a class the user expected | The list shows what will be cancelled; a class already moved by an approved replacement is genuinely off the timetable |
| Two cancel paths drifting | Both go through `CancelClassBatch`; the single path is a one-item set |
| Client-side patching of several grids drifts | The success path refreshes from the server instead of patching four blocks by hand |
| Scope creep into the parked bulk lane | Explicitly out of scope above; the action is shared, the UI is not |

## Prototype

Built and reviewed: `ui-prototype/cancel-whole-day.html` — five states on one sheet (unticked · ticked ·
a day with nothing else · success · phone at 390 px), loading the real `theme.css` from `:8000`. It
encodes decisions 1–12; the real build reproduces the block, the row format and the two live counts,
not the sheet.
