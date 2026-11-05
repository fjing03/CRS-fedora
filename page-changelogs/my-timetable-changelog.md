## 2026-11-05 — Cancel Class is FINAL (the Undo is gone)

User decision (option B): cancelling a class can no longer be reversed. The confirm dialog now says so, and the
toast no longer offers an Undo.

- The confirm state carries one line above the buttons: **This action cannot be `UNDONE`.** — `UNDONE` in
  capitals, bold, **warning** colour (`--color-warning` via the existing `.warn-action` token class; no new class,
  no new colour). Measured in the real modal: keyword colour `rgb(212, 136, 15)`, weight `700`; line 14 px on the
  normal surface colour.
- `ClassCancellation.showUndoToast()` now shows the message with **no callback** — the toast is a confirmation you
  dismiss. Measured: the toast bar's Undo affordance computes `display: none`; the message text is unchanged.
- `ClassCancellation.undoViaServer()` and `Livewire::cancelClassUndo()` are marked **retired** (no caller left);
  they are kept this pass so nothing else can break, and are safe to delete together later.
- The "Just cancelled" chip's Undo on My Replacements was already switched off, so the surfaces now agree.

**Why:** an undone cancellation left its "Scheduled class cancelled" notification behind with no class to point at
(see the notifications-panel entry). Making the action final removes that whole class of stale notices going
forward; the read-time check cleans up the ones already delivered.

## 2026-11-04 — the TODAY button's flash is blue, like the button

User: the Today jump's highlight should be the button's own blue. It painted the shared green
(`--color-success`) like every other jump.

A second keyframe, `bookable-pulse-primary`, uses `--color-primary` with **exactly** the same shape as the
green one — 0.50 s × 3, a 4 px ring at 63 % — so only the colour changes, never the rhythm or the strength.
It is scoped to the today column (`td.today-cell.bookable-flash`, which outranks `td.bookable-flash`), so
Notifications, Earliest bookable and `Go to My Timetable` stay green.

Measured after a real Today jump from another week: animation `bookable-pulse-primary 0.5s x3`, **3**
pulses, peak ring alpha 0.629, 22 today cells flashing, ring resolved to `color(srgb 0 0.301961 0.596078 /
0.63)` — i.e. `#004D98` at 63 %. The jump flash still reports `bookable-pulse` (green), so the two families
stay distinct. No page errors.

## 2026-11-04 — the pulse: 3 flashes, faster, 40% brighter

User: the highlight should flash **three** times (seven was the first ask, then "3 times is enough"),
brighter and quicker. `bookable-pulse` (the shared flash — Today, Earliest bookable, Notifications, and
the new `Go to My Timetable` jump) changes from **one 1.15 s pulse to three 0.50 s pulses**, and the ring
goes from 3 px at 45 % opacity to **4 px at 63 %** — 40 % brighter, by the same measure.

`flashRowPulse()` held the class for 1.3 s, which was sized for one pulse; it now keeps it for the whole
run (3 × 500 ms + slack) or the last flashes would never play.

**One animation for all four callers**, deliberately: the flash is a single language, so a jump feels the
same wherever it starts.

Measured by sampling the element every 16 ms after a real jump: peak ring opacity **0.629** (was 0.45),
peak ring **4.0 px** (was 3.0), **3** pulses counted, animation string
`bookable-pulse 0.5s x3 ease=ease-in-out`, the highlight present for ~1.6 s.

## 2026-11-04 — the page accepts `?repl-hl=` (a replacement's own arrival)

Replacement blocks now carry `data-rid` — the replacement request's id, stamped server-side on the
approved landing event. That is what the new arrival arm targets, because `data-sid` (the class) is shared
with the class's natural blocks and could flash the wrong cell.

`?repl-hl=<requestId>&repl-w=<week>` switches to the week through the page's own `selectWeek`, scrolls the
block into view and flashes it once with the shared `.bookable-flash` pulse. The params are stripped before
matching, so a refresh repeats nothing. **No fallback selector:** a miss flashes nothing.

Measured: `repl-hl=26&repl-w=7` flashed 26 only; `repl-hl=27&repl-w=8` flashed 27 only;
`repl-hl=26&repl-w=8` (wrong week) and `repl-hl=999` flashed nothing, with no console errors.

## 2026-11-04 — the page description speaks the same visual language as My Replacements

Minor UI update (user, all pages): the `.page-desc` line now bolds and uppercases its key terms with
the existing `.info-keyword` class — the convention the merged My Replacements page introduced. The
words are unchanged; only the emphasis markup is new (`description` → `descriptionHtml`), so the
rendered sentence reads identically.

## 2026-11-02 (shared modal) — visible demo reminder above the timeline rail

The class-detail popup's rail (the shared `DetailModal.timeline()`) now prints
one line of plain text directly above it: **"the timeline function got error --
ask SV does this useful/helpful for Them"**. A demo aid, not product copy. The
rail itself is the partially hardcoded one ("In progress" / "Next" are literals,
and it disappears once the request is decided) — parked in
`prompts/extra tasks/task-02-replacement-history-timeline.md`. Styled by
`.modal-timeline-note` (muted token, italic). Delete with the rail rebuild.

## 2026-11-02 (SDD `venue-status-parity` — bugs #5 + #10, shared modal) — the popup quotes the CLASS, and a cancelled class stops sounding healthy

Two one-line fixes in the shared `openClassModal()`; this page's behaviour is
otherwise **untouched** (it is the reference, not the target — its summary cards
stay plain and the new Playwright receipt asserts exactly that).

1. **`Class Ref` prefers the class identity (#5).** The row was
   `'#' + event.id`, and on the venue page `id` is the grid's ROW key (a
   `time_slot` id) — so the same class quoted `#40` here and `#34413` there. The
   row now reads `event.sessionId` when the payload carries one. On this page
   nothing changes (`id` already IS the class id — the receipt re-asserts
   `#23` for BMSE3153).
2. **`Status Description` gained the cancelled arm (#10).** A cancelled class
   used to read "Scheduled class with no issues" on every page (the ternary had
   no `cancelled` branch). It now reads "Class cancelled — a replacement is
   needed", matching the badge and the block note.

## 2026-10-27 (user law, SDD class-cancel-2-working-day-window) — the Cancel window: 2 working days before today + ALL future
**Where this page is concerned:** my-timetable — the Cancel Class birthplace (the modal + button the law widens).

**The law (user, verbatim example):** today Mon 26 Oct → cancellable = the
22nd (previous Thursday), 23rd (previous Friday), the 26th (today), and
>= the 27th. The OLD S5 gate ("END datetime strictly in the future") is
SUPERSEDED by a DATE-grain window: cancellable iff the class DATE >=
(today − 2 working days) — the whole today stays cancellable even after
its end time; a today 9 AM class reads ALLOWED at 3 PM.

**Mechanics (public/js/ui-common.js — the shared CancelClass machinery
all three table pages serve):** `_cutoffDate()` = the getTodayMs-anchored
walk-back skipping Sat+Sun exactly twice (holidays NOT counted — the
documented assumption; the S4 holiday guard still blocks cancelling ON a
holiday independently); `isCancellable`'s S5 = the midnight strip
(`clsDateMs >= cut`); the S9/S6 shortfall hint re-keyed TWO-WAY ("This
class is outside the 2-working-day cancellation window." iff genuinely
pre-cutoff, else the generic fallback — an already-cancelled twin keeps
an honest word); the modal copy untouched.

**Spec receipts (tests/cancel-class.spec.ts):** S5 stays green VERBATIM
(the Sep-21 fixture is outside ANY window either law sets); S6 retires
with the honest skip (the end-crossing scenario is legal-by-design under
the window law); S17's skip comment re-keyed (the Oct-21 replacement
occurrence sits pre-cutoff); S4 unchanged. The user tests the new law
MANUALLY (their explicit directive — no new spec, no battery run).

## 2026-10-27 (SDD class-cancel-db-persist) — Cancel Class writes the REAL database
**The forward path:** the confirm await now posts through ONE shared Livewire
trait (`App\Concerns\CancelsClasses` — the submitBlocks renderless-array
precedent; no HTTP route): the Yes pending-disables first, the S1–S5/S7-S8
pre-flight runs single-homed (`ClassCancellation.preflight`), then
`window.Livewire.first().call('cancelClass', sessionId, week+1, reason, otherDetail)`
writes the `class_exceptions` row (audited `class_cancelled`; an `Other`
detail rides the audit) and the SERVER response is the truth — ok → the
existing visuals byte-continue (success state, block vanish, ledger +
`exceptionId` stamped for the undo); fail → the honest hint, NO flip.
**The reason radios:** the frozen vocabulary (7 leaves + Other) — the radio
VALUES are the DB enums now (`medical_leave`…; the FROZEN label list), the
labels stay the user's plain text; the Yes gate re-keyed on `'other'`.
**The undo path:** the 5s toast Undo posts `cancelClassUndo` FIRST (one
shared `ClassCancellation.undoViaServer`) — deletes the DB row + audits
`class_cancel_undone` — and ONLY THEN rebuilds/deep-links (the browser
navigating mid-await would kill the undo). A failed undo keeps the cancel
standing with the honest hint.
**Sequels:** the real-DB receipt needs the demo-DB CHECK widen pasted by the
user (`docs/demo-db-data-changes.md`, 2026-10-27 entry). No new spec — the
user tests manually; `tests/cancel-class.spec.ts` locators re-keyed to the
enum values declaratively.

## 2026-10-27 (user law, SDD cancelled-blocks-stay-visible) — the cancelled block STAYS red until the PL approves
**The law (user, verbatim):** "A cancelled class must remain visible on the
timetable as a conflict requiring replacement until the PL approves the
proposed replacement. Cancellation alone must not cause the original
timetable block to disappear."

**Server (`ResolvesTimetableTimeline::buildEventsByWeek`):** the exception'd
pair now renders a `status:'cancelled'` event in its OWN slot (cancelled/
`cancelledReason` label via the ONE `App\Support\ReasonLabel` law, the
`(Proposed ⏳)` hint when a pending proposal covers it, the D-P4 fact ride so
the popup's Replace-Now handoff stays available). The read-time
discriminator: the original slot vanishes ONLY when an APPROVED replacement
request covers the pair (the approved landing block renders at the slot's
own week per the existing D-P10 workflow — 0 extra queries, no check-value
regime, no migration). A REJECTED request auto-reverts the plain
cancelled red.

**Client (ui-common.js):** the two day-event filters stopped discarding
cancelled events; the class family's FIRST arm maps `status:'cancelled'`
to the EXISTING `event-conflict` (no new CSS — the §10.0 law); the
`(Cancelled)` note is the third sibling in `buildReplacementNote` (the
TIP = `{cohort · CANCELLED}` via the existing composition — the user's
post-freeze decision: NO reason string in the tip/note; the label lives
on the home badge). `computeSummary`'s conflicts tally now INCLUDES
cancelled blocks; `myTeachingStats` keeps them OUT of Total Classes /
Teaching Hours (the honest-numbers law — a class that doesn't run isn't
taught) — the blade filters are gone (the full arrays reach the summary).

**Gates:** phpunit 262 green (4 new `CancelledBlockStaysVisibleTest`
receipts + the upserted `TimetableWiringTest` red-block test — the
cancelled-only week now renders the block, NOT the empty state); pint ✅;
phpstan ✅; NO new Playwright spec (the user tests manually).
## 2026-10-27 (user law, BMSE3153-class-info) — the multi-cohort breakdown in the Class Details
**The law:** no bare `27` for a multi-cohort class — the Class Details show
the per-cohort counts (`14 + 13 = 27`), calculated from the real data.
**Payload:** `ResolvesTimetableTimeline::baseEvent` adds the positional
`studentCounts` array (parallel to the `cohorts` list — the mock-data
convention, counts from `cohorts.student_count`).
**Modal (openClassModal extraFields):** a multi-cohort class renders
`Total Students = 14 + 13 = 27`; single-cohort keeps the plain count.
**Receipts (Playwright):** BMSE3153(P) #23 → Cohort `RSD3(S1)G2 + RSD3(S1)G3`
+ `14 + 13 = 27`; single-cohort BMIT2154(L) #7 → `RSD2(S1)G1` / `16`
(unchanged). No hardcoded values.

## 2026-10-27 · breakdown logic promoted to the shared totalStudentsLabel
Follows 910b97c: the inline `A + B = total` construction in the Class Details modal
now calls `window.totalStudentsLabel(event)` (ui-common.js) — the venue page's slot
modal became the 3rd copy, so per promote-on-3rd the helper moved to ui-common.js
and this page's copy was retired. Behavior unchanged (multi-cohort `A + B = total`,
single-cohort plain count, `—` absent).
## 2026-10-27 (SDD mock-statics-die) — receipt note
The `MockData.cohorts` registry + the `arrangementWeeks` scaffold (0
live consumers, the payload-driven walk supersedes) are deleted from
mock-data.js. This page's data was already fully DB-wired. The cohort
shrink (cohorts at 7–10 students) rides the payload — the Total Students
rows reflow automatically (`8 + 7 = 15` on BMSE3153(P)).
## 2026-10-27 (SDD b005-diploma-allowed) — receipt note
The b005-derived `conflict` status arm in the timeline payload dies:
every session renders normal unless a real exception/holiday hits it.
The standing AMIT2034(P) B005 block (a red fixture of this page for the
whole of the 10-27 session) renders clean.
## 2026-10-27 (SDD makeup-busy-vector) — receipt note
The arrangement availability now honours the approved/pending make-up
slots in the lecturer/cohort busy vectors (the bug9 double-book fix);
this page's own rendering was already correct (the `event-replacement`
block receipts rr #1) and is untouched.
