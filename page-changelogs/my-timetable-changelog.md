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
