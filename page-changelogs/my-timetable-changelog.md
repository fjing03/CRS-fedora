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

