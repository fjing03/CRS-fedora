## 2026-11-10 — Modal consistency (#1–#4)

**Follow-up (same day, user ruling A):** the **Subject** row left Class Information — the modal
subtitle above the tabs already prints `CODE — Name (Type)`, so the pop-up said it twice. Class
Information is now **Session ID · Lecturer · Cohort · Total Students** on all four timetable pages,
and the subtitle is built from ONE `subjectText` value. Measured on My Timetable, Cohort Timetable,
Venue Timetable and Student My Timetable: same subtitle, no Subject row, zero console errors.

**Follow-up (same day, user ruling B):** Slot Details stopped printing the room twice — the
**Room Name** and **Capacity** rows left Class Information, and the Schedule's **Venue** row now
carries them: **`B002 — Tutorial Room (35 seats)`** (code leads, the name loses the copy of the code
it already carries, seats optional). The same one wording applies everywhere a Venue row appears, so
My Timetable / Cohort / Student Class Details now read `B111 — Lecture Hall` instead of
`Lecture Hall B111`. Measured on room B002: five Class Information rows, `B002 — Tutorial Room
(35 seats)`, zero console errors.

**Follow-up (same day, size only):** every **modal-footer button is now exactly 34 px** — the
size the Close buttons already used (the footers carried 31 / 32 / 34 / 37 px before). The rule is
scoped to `.modal-footer`, so tables, cards and toolbars keep their own sizes; no button was re-ordered,
re-coloured or re-worded. Measured: 34 px on Class / Cancel Class / Request / Approve / Replacement /
Slot modals, no clipped labels, zero console errors.

The details modals now share one shell and one set of rows across the system (user brief
"UI/UX Update — Modal Consistency"; the same change landed on Class Details / Slot Details /
Request Details / Replacement Details).

- **Width** — every details modal is **616 px**, the same as the Replacement Details modal
  (`theme.css` `.modal.modal--detail` was 480).
- **Timeline rail removed** — `.modal-timeline`, its "ask SV" note and the dot styles are gone,
  with `DetailModal.timeline()` and the pending-rail config that fed it.
- **Subtitle carries the class type** — `AMSE1003 — Software Engineering (Lecture)`.
- **Replace Now is a primary button** — blue (`--color-primary`), with a swap icon before the
  label (my-timetable, cohort-timetable, venue-timetable).
- **Class Information** — leads with **Session ID** (`#99`, the `class_sessions` id; `sessionId`
  still wins over the grid's row key), then **one Subject row**
  (`AMSE1003 — Software Engineering (Lecture)`), Lecturer, Cohort, Total Students. The separate
  Subject Name / Class Type / Class Ref rows and the "Subject Code" label are gone.
- **Schedule** — **Venue** (code + name), **Week**, **Day & Date** (`Mon, 9 Nov 2026`),
  **Time & Duration** (`9:00 AM ~ 11:00 AM (2 hours)`). The separate Day / Date / Start / End
  rows are gone. The class modals now receive the viewed week, so the Week row is real data.
- **One wording for the facts** — `ModalFacts.dayDate / timeDuration / venue` in `ui-common.js`;
  `venueLine()` and `request-approval`'s `venueText()` delegate to it, so a room name that already
  carries its code is never printed twice (`Tutorial Room B002`, not `B002 — Tutorial Room B002`).
- **No "NaN minutes"** — `durationWords()` and `rangeHours()` refuse a non-number instead of
  printing it.

Verified with Playwright on the demo data (`:8010`), 22/22 checks: widths 616 on all four pages,
subtitle with the type, zero timeline elements, Replace Now blue + icon, the new row order and
formats on both schedules of Request Details and Replacement Details, no NaN, no duplicated venue
code, zero console errors. Pinned by `tests/Feature/ModalConsistencyTest.php` (9/9).

## 2026-11-09 (user brief) — the hover tooltip clears the hour header, and a holiday badge stays red on a Today holiday

**Two one-rule style fixes in the shared stylesheet** (`public/css/theme.css`), so every grid page
moves together: My Timetable, Student My Timetable, Cohort Timetable, Venue Timetable, and the day
columns of Replacement Arrangement. No markup, no builder, no booking logic touched.

**1. Tooltip behind the hour header.** Hovering a class block drew its info tip *underneath* the
08:00/09:00 header row. Cause: the tip is `.event-block::after` (z-index 50) but the block is a child
of `td.hour-cell`, and a block's own z-index never escapes its table cell — measured: raising the
block to z-index 500 changed nothing, while raising the **cell** fixed it on all four lecture pages.
The cell is now raised only while its own block is hovered:
`.timetable td.hour-cell:has(.event-block:hover) { z-index: 20 }` — above the sticky header (10) and
the sticky day column (15). The tip's text, position, colours and timing are unchanged. (Replacement
Arrangement is unaffected: it has no `.event-block`; its cell tips use the shared `data-tip`.)

**2. Holiday badge blue on a Today holiday.** On Mon 9 Nov 2026 (Week 8, Deepavali in lieu) the
PUBLIC HOLIDAY badge printed blue. Not a cascade accident from `.today-badge`: a dedicated rule
`.time-col.today.offday .holiday-badge` (added 2026-08-13 by `f5fe0de`, "align to venue-timetable
design") replaced the holiday colour with `--color-primary-container`. Now the badge keeps the
holiday red everywhere, and that blue variant survives only as the column-hover chip
(`.time-col.offday:hover .holiday-badge`, inverse surface) — because an offday column turns solid
red on hover, where a red badge would disappear. **This was the user's choice (option B, 2026-11-09).**

Verified: all four lecture pages show the tip above the header (pixel-sampled band + screenshots);
Mon 9 Nov shows red PUBLIC HOLIDAY + blue TODAY at rest, dark chip while the column is hovered; Week
14's Christmas Thu/Fri holidays stay plain red; ordinary days carry no badge; mobile 390 px card
unchanged (red chip on the blue today card); zero console errors on five pages.

Receipts: `tests/Feature/TimetableBadgeAndTooltipCssTest.php` — 3/3 source invariants (no non-hover
rule may repaint the badge blue; the hover chip exists; the raised cell out-ranks both sticky
layers, compared as numbers, not literals). **Red-check:** restoring the two old rules fails all 3.
`pint` passed, `phpstan` 0, `view:cache` OK, targeted PHPUnit 30/30.

## 2026-11-07 (date/time batch 3, `docs/date-time-format-audit-2026-11-07.md`) — day headers and week ranges lose the leading zero

Day headers read `Thu 5 Nov 2026` (was `Thu 05 Nov 2026`) and the week dropdown / mobile week chip
read `5 Nov 2026 ~ 11 Nov 2026` (was `05 Nov 2026 ~ 11 Nov 2026`). Same shared builder
(`generateWeekData`, `DateHelper.fmt/fmtShort`), so every grid page moves together; the lead-time
note's cutoff date follows. The holiday skip compares the same unpadded shape, so a holiday on a
single-digit day still blocks the slot.

## 2026-11-07 (date/time batch 2, `docs/date-time-format-audit-2026-11-07.md`) — event blocks and the cancel summary join with `~`

The shared grid event block — used by My Timetable, Student, Cohort, Venue and Arrangement —
printed `2:00 PM - 3:00 PM`; it now prints `2:00 PM ~ 3:00 PM`. The Cancel Class success summary
(shared `ClassCancellation`) follows the same change. One builder, so every grid page moves
together; no guard, state or layout changed.

## 2026-11-05 — the Cancel Class? tooltip now names the date, and holidays count as non-working

The user's follow-up brief on the button's hover text (shared `CancelClass.renderButton` — My Timetable, Cohort and
Venue all get it). Two lines, still the shared multi-line `data-tip` design:

> You Can Cancel Classes Scheduled From **Tue, 3 Nov 2026** Onwards.
> Rule: The Earliest Class You Can CANCEL Is 2 Working Days Ago.

- The date is computed at render time by `CancelClass._earliestEligibleLabel()`, which reads the SAME
  `ClassCancellation._cutoffDate()` the S5 guard compares against — one computation, two consumers, so the tooltip
  can never advertise a window the button does not honour.
- `_cutoffDate()` now walks back over **weekends AND the semester's public holidays**, exactly as the brief asks.
  Holiday dates come from `MockData.holidays` (`week` + `dayIndex`) anchored on `MockData.semester.startDate`
  (`_holidayDateKeys()`) — never a hardcoded calendar; a missing payload degrades to the weekend-only rule.
- **Behaviour change, flagged:** eligibility itself follows the same rule. A public holiday inside the two-day
  window pushes the cutoff earlier, so a class on the date that used to squeak in is now refused. The 2026-10-27 law
  had holidays excluded from the count by documented assumption; this brief overrides that. Every other guard
  (ownership, status, Sunday/holiday day, isCancellable's own checks) is unchanged.
- The tooltip design, the button label and the click behaviour are untouched.

**Measured** live on the demo data (today = Thu 5 Nov 2026): the tooltip renders
`You Can Cancel Classes Scheduled From Tue, 3 Nov 2026 Onwards.` / `Rule: The Earliest Class You Can CANCEL Is
2 Working Days Ago.` as two lines (379 × 38 px) above the button. Walk-back probes: today Tue 10 Nov → **Thu 5 Nov**
(skips the Deepavali-in-lieu Monday; the weekend-only walk would say Fri 6 Nov), today Mon 28 Dec → **Tue 22 Dec**
(skips Christmas Eve + Christmas Day), today Wed 4 Nov → Mon 2 Nov, today Thu 5 Nov → Tue 3 Nov.

## 2026-11-05 — "Cancel this Class?" earns its own facts box, a required-reason line and a stated window

The user's 2026-11-05 UI/UX pass on the shared Cancel Class modal (this page includes
`partials/ui-cancel-class-modal`; Cohort and Venue include the same partial, so they get it too):

| Item | Now |
|---|---|
| Title | `Cancel this Class?` (was `Cancel Class`) |
| Width | **+40%** — `.modal--cancel` = **588 px** (420 × 1.4). Declared before the responsive `.modal` media queries, so tablet/phone caps still win |
| Facts | one grey box — the **same** `dl.cancel-details` element the Confirm Cancellation modal uses on `/my-replacements` |
| Section title | `Class Information` (was `This Class`) |
| Rows | Course, **Week**, Day & Date (full weekday), Time (**~** + duration), Venue (`CODE — Name`), Cohort(s) (`+`), Students (`A + B = total`) |
| After the box | a full-width rule, then the static instruction `You MUST Select a Cancellation Reason` |
| Removed | the dynamic `Please select a cancellation reason.` hint (the instruction replaces it; the hint now only carries genuine refusals, e.g. the blank-"Other" message) |
| Bottom-left | `No, Keep Class` (was `Keep Class`) |
| Button tooltip | states the real window — 2 **working** days back, today included, future classes included |

Everything is read from the page payload — the day's own date string, the server's positional `studentCounts` for the
breakdown, and a new `venueName` on the timetable event so the Venue row can print `B010 — Computer Lab B010`.
`CancelClassModal.studentsFor()` (the old twin-sum helper) died with the old preview; the shared
`totalStudentsLabel()` law supplies the breakdown now.

**Receipt (live `:8000`, as 5425, 2026-11-05):** `BMIT2013 (P) — Web-Based Integrated Systems` · `Week 7` ·
`Wednesday · 04 Nov 2026` · `9:00 AM ~ 11:00 AM (2 hours)` · `B010 — Computer Lab B010` ·
`RSD2 (S1) G2 + RSD2 (S1) G3` · `5 + 9 = 14`; modal measured **588 px** at a 1440-wide viewport (560 on the tablet
cap); tooltip renders in three readable lines above the button; the hint line is empty with no reason picked.

**Two findings recorded, not fixed:** `--color-outline-variant` is used by 9 rules in `theme.css` but is **never
defined**, so every hairline that reaches for it renders nothing — the new divider uses the canonical
`--color-outline` instead. And the label reads **Course** (the documented house copy law, and what the merged
My Replacements page prints) rather than the prompt table's "Subject"; it is a one-word change if the user prefers
the old word.

## 2026-11-05 — a refused cancellation now explains itself

Found while rewriting the S6 receipt: when the Cancel Class confirm was **refused**, the modal's message line came out
**blank** — the button re-armed, the dialog stayed open, and nothing said why.

The cause was order, not wording. `_refreshGate()` re-arms the Yes button *and* rewrites the hint from
`ClassCancellation.validate()` — which is an empty string whenever the chosen reason is perfectly valid. The refusal
copy was written **before** that call, so the gate erased it a line later. The message never had a chance.

Both refusal branches now gate first and write the message second, on a **freshly queried** element (the Livewire
round-trip can re-render the modal, so the pre-await node may be detached):

| Refusal | Message now shown |
|---|---|
| the class was cancelled elsewhere (another tab, another user) | `This class is already cancelled.` (the server's own copy) |
| the class left the 2-working-day window, or stopped being cancellable | the guard's own sentence |

Measured in the two-tab receipt (S6): the second tab cancels the class, the first tab's confirm is refused, and the
line reads `This class is already cancelled.` — with no success state, no stored entry, and the block still on that
tab's grid. The validation hints (S7/S8) are untouched: those come from the gate itself and still read correctly.

## 2026-11-05 — the dead undo path is gone (cleanup)

With the Undo affordance retired, everything that only existed to serve it was deleted rather than left lying
around:

- `ClassCancellation.undoViaServer()` (the server round-trip), `ClassCancellation.undo()` (the client reversal),
  the `priorStatus` field it restored, `UNDO_TOAST_MS`, and the dead `?restored=1` arrival toast (its only producer
  was that cross-page redirect).
- `CancelsClasses::cancelClassUndo()` — the Livewire method — and its `class_cancel_undone` audit action. No caller
  was left, and nothing else referenced them. The trait's now-unused `AuditLog` / `DB` imports went too.
- `showUndoToast()` → **`showCancelToast()`**, so the name stops promising an undo it no longer offers. The snooze
  rule (5 s, ✕ or full display counts as seen) is unchanged.
- The generic toast `undoCallback` API stays: other features still use it (notification hide, clearing a selection).

**Kept on purpose:** `exceptionId` on the ledger entry — the server-verified handle that stops a real cancellation
from being dropped as "stale" on reload. That never belonged to the undo.

Nothing user-visible changed; the toast text is identical. `node --check` and PHPStan stay clean.

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
