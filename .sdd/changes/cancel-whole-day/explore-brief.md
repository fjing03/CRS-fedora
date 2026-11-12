# Explore brief — cancel-whole-day

**Date:** 2026-11-11 · **Mode:** explore only (no code, no tests) · **Surface:** the shared
*Cancel this Class?* modal (`partials/ui-cancel-class-modal.blade.php` + `CancelClassModal` in
`ui-common.js`), used by My Timetable, Cohort Timetables and Venue Timetable.

## The ask

One new checkbox in the *Cancel this Class?* modal: **also cancel every class the lecturer teaches that
day**. The user's framing: "for Cancel Classes for that WHOLE day".

Eight decisions were taken in a grilling session on 2026-11-11 (all answered by the user):

| # | Decision |
|---|---|
| 1 | **Placement:** its own small block between the grey Class Information box and the Cancellation Reason section |
| 2 | **Set:** every class the lecturer teaches that day — across modules, cohorts and rooms |
| 3 | **Preview:** the label carries the count, and the list of the other classes appears once ticked |
| 4 | **Requests:** the day's classes are cancelled anyway, and any still-pending request on them is cancelled with them |
| 5 | **Success:** one **Close** button; the success text lists every cancelled class |
| 6 | **Nothing to offer:** the block is hidden when the day has no other class |
| 7 | **Label:** `Also cancel my other 3 classes on Wednesday` |
| 8 | **Red button:** becomes `Yes, Cancel 4 Classes` while ticked |

## What exists today (measured)

**The modal** (`ui-cancel-class-modal.blade.php`): header *Cancel this Class?* → `#cancelClassImpact`
(the grey `dl.cancel-details` facts box, filled by `_renderImpact()`) → `.modal-divider` →
`#cancelReasonList` (8 radios, 2 per row, `Other` revealing `#cancelOtherWrap`) → `#cancelReasonHint` →
footer: *No, Keep Class* (left) · `#cancelConfirmWrap` = the shared compact irreversible line + red
*Yes, Cancel Class* · then the success-state pair *Arrange Replacement Now* / *I'll Do It Later*.

**The JS** (`ClassCancellation` + `CancelClassModal` in `ui-common.js`): `renderButton()` gates on
`ClassCancellation.isCancellable()` — S2 is `event.lecturer === MockData.currentUser.name`, so the button
only ever appears on the logged-in lecturer's own classes, on every page. `confirm()` calls the Livewire
trait method `cancelClass(sessionId, week, reason, otherDetail)`, then applies the client mutation +
ledger and swaps to the success state.

**The server** (`app/Concerns/CancelsClasses.php`, used by `MyTimetable`, `CohortTimetable`,
`VenueTimetable`, `ReplacementHome`): `#[Renderless] cancelClass()` resolves the session with
`where('lecturer_id', auth()->id())`, validates the reason, and calls `(new CancelClass)(...)`.
`CancelClass::__invoke()` re-checks ownership, the 1–14 week range and the reason, writes the
`class_exceptions` row, one `AuditLog` row, and fires `NotificationEmitter::classCancelled()` after
commit. **It does not touch any request** on that occurrence.

**Where the day's classes are available:** `MyTimetable` already ships `eventsByWeek` — every class the
lecturer teaches, per week — so the day's set is computable in the browser there. `CohortTimetable`
(`eventsByCohort`) and `VenueTimetable` ship one cohort's / one room's grid, i.e. **only a slice** of the
lecturer's day, so those two pages cannot answer "my other classes that day" from their payload.

## The parked lane this must not duplicate

`.sdd/changes/bulk-cancel-class/` (2026-11-10, proposal written, never implemented) is the same problem
at a larger grain. Its rules are directly reusable, its UI is not.

**Reuse (one mechanism, not two):**

| Parked ruling | Why it matters here |
|---|---|
| `CancelClassBatch` — cancels a SET of occurrences in one transaction, all-or-nothing, re-checking every rule | The whole-day tick is a set of one day; this is the same action |
| Cancelling a class **closes the request it holds** (pending or approved → `cancelled`, `decided_at` set, slots released, junction rows deleted) | Decision 4 is exactly this rule |
| The action, not the page, owns the rules (ownership · 2-working-day window · holiday/Sunday · reason vocabulary · duplicate exception) | Same guards, same refusals |
| One `AuditLog` row per cancelled class | Same |
| Per-class notification fan-out; no email | Same |
| `ReleaseRequestSlots` (one release implementation for every caller) | Prerequisite for closing requests cleanly |

**Do not reuse (out of scope here):** the semester-bar *Cancel Class* button, the From/To range picker,
the per-day header ticks, and the additive `notifications.batch_id` migration. The day tick needs none of
them; if the bulk lane ships later it can add them on top of the same action.

## Feasibility findings

1. **One shared read is enough for all three pages.** A `#[Renderless] myDayClasses(int $week, int $day)`
   on `CancelsClasses` can return the caller's own classes for that date with everything the block and
   the list need: `sessionId`, start/end, module code, class type, cohort label(s), room, and a
   `request` field (`pending`/`approved`/`none`). My Timetable could compute it client-side, but using
   the same read everywhere keeps one truth (and one place to change).
2. **Same date ⇒ same guards.** Every class in the set shares the opened class's date, so the
   2-working-day window, the holiday/Sunday ban and the week number are identical for all of them; no
   per-class divergence is possible.
3. **Classes already off the timetable are naturally excluded.** An approved replacement already stamps
   the original occurrence with a `class_exceptions` row (`ApproveRequest::releaseOriginal`,
   reason `official_event`), and a previous cancel has its own row — so "the day's set" = sessions of mine
   that day in that week **without** an exception row.
4. **The single-class path stays as it is for its own case**, but the request-closure rule (decision 4)
   means the day path must close requests — which today's `CancelClass` does not do.
5. **The success state is single-class shaped**: `#cancelClassSummary` prints one line, and the two
   footer buttons act on `ctx.event`. A day cancel needs the swap to a list + one Close (decision 5).
6. **The client mutation is single-class too**: `ClassCancellation.cancel()` mutates the grid for one
   event and writes one ledger entry. A day cancel must mutate every affected block (on My Timetable and
   on the cohort/venue grids it appears in) or simply refresh from the server.

## Open technical questions for the proposal stage (not user decisions)

- **Atomicity:** one action for the whole set (recommended, matching the parked lane) versus four calls.
- **After success:** mutate the visible grid client-side (four ledger entries, four block updates) or
  reload the page's data once. Reloading is simpler and cannot drift; the modal's Close can force a
  refresh.
- **The pending-request notice:** reuse the parked lane's *"Replacement class cancelled"* wording, or the
  existing `CancelPendingRequest` notification for a self-cancelled request.
- **Ledger:** the client ledger is per occurrence; a day cancel writes one entry per class.

## Risks

| Risk | Note |
|---|---|
| Two cancel mechanisms (single + day) drifting apart | Both should end in the same action; the single path can delegate with a one-item set |
| Cohort/Venue pages showing a stale day list | The read happens when the modal opens, not when the page loads |
| A day that includes a class with a waiting request surprises the PL | Decision 4 accepts this; the list marks the row and the success text counts the withdrawn requests |
| Larger blast radius than the modal shows | Decisions 3, 7 and 8 put the count in the label, the classes in a list, and the number on the red button |

## Rev 2 — the prototype round (2026-11-11, late)

The prototype (`ui-prototype/cancel-whole-day.html`) was reviewed and the user added four rulings and
changed one:

| # | Decision |
|---|---|
| 9 | **No title change.** The modal keeps *Cancel this Class?* in every state, including the success screen, even when four classes are cancelled |
| 10 | **Every class in the list is its own checkbox.** The whole-day box is a select-all: ticking it ticks every row, unticking clears them, and a partly-ticked list shows the indeterminate (half) tick |
| 11 | **Row format:** `9:00 AM ~ 10:00 AM · AMSE4342(L) · DSF1 (S1) G1` — 12-hour range, module code with the class type in brackets, then the cohort. A class with several cohorts prints **`n cohorts`**, and the names ride the **hover** (the shared `data-tip`, one cohort per line) |
| 12 | **The label's number follows the ticks** (supersedes the flat "other 3"): untick one and it reads *"Also cancel my other 2 classes on Wednesday"*, and the button reads *Yes, Cancel 3 Classes*. Unticking every row closes the list and restores *Yes, Cancel Class* |

Decision 3 (the list appears once ticked) and decision 8 (the button names the total) now read through
decision 12: both numbers are the **selected** rows, not the day's total.

Measured on the sheet: 0 console errors; no row clips at 390 px (the phone frame needed its own scoped
rules because a media query cannot fire inside a 1400 px page — the real build needs only the media
query).

## Rev 3 — the prototype round, second pass (2026-11-11)

| # | Decision |
|---|---|
| 13 | **The block folds.** A chevron at the right of the header collapses the list; the tick, the count and the red button's number survive it. Turning the whole-day tick ON expands the list (a folded list under a fresh tick would hide exactly what is about to happen) |
| 14 | **The row carries the room code:** `9:00 AM ~ 10:00 AM · AMSE1003(L) · B009 · DSF1 (S1) G1` — time, module + type, room, then the cohort column |
| 15 | **The cohort and the request mark stack on the right**: the cohort on top, the mark under it. **Amber** (`--color-warning-container`) = a request that will be cancelled; **red** (`--color-error-container`) = a request that was withdrawn (the success screen) |

Measured on the sheet: 0 console errors; every row and every success line reports
`scrollWidth <= clientWidth` at both 1400 px and the 390 px frame; collapsing leaves the count (`3`) and
the button (`Yes, Cancel 4 Classes`) untouched.
