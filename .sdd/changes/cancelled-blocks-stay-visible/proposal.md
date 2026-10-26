# proposal — `cancelled-blocks-stay-visible` (2026-10-27)

## Why (the user's law, verbatim intent)

"Keep Cancelled Classes Visible on the Timetable" — the cancel flow currently
REMOVES the cancelled block (the `ResolvesTimetableTimeline` week loop's
`exceptionKeys` skip fires before anything renders). The law:

1. **Cancelled** → the block STAYS in its original slot, rendered with the
   existing `event-block span-n event-conflict` family (NO new CSS); the
   tip reads `{Cohort · Cancelled — Reason}` (e.g.
   `DSF2(S1)G1 · CANCELLED — ANNUAL LEAVE` — one uppercase string via the
   grid's existing `:801` :topper).
2. **While waiting for replacement** → the block stays visible until the
   staff member proposes a replacement AND the PL approves it.
3. **After proposing** → the block stays while the request is PENDING PL
   approval (never removed merely because a replacement was proposed).
4. **After PL approval** → the block is removed per the EXISTING
   approved-replacement workflow (the D7 original-release + the landing
   week renders the approved replacement).

Constraints honoured: no DOM hiding, original date/time/venue preserved,
only the existing `event-conflict` styling reused, no breakage of the
rendering or replacement flows.

## The current mechanics (investigated, all anchors re-greppable)

- `ResolvesTimetableTimeline::buildEventsByWeek` (:163–): the loop head
  `if (isset($exceptionKeys[$s->id.':'.$w])) continue;` — ANY exception row
  removes the block. Shared by **MyTimetable, CohortTimetable,
  StudentMyTimetable** (one `use ResolvesTimetableTimeline` family).
- The pending family (USER DIRECTIVE 2026-10-17): `pendingByMakeupSlot`
  renders the AMBER block at the proposed slot's own day/time/venue;
  `pendingByOccurrence` paints the natural occurrence's `proposed` hint
  ("(Proposed ⏳)"). **Both become reachable only after the skip is lifted.**
- Approved: `approvedByWeek` lands the replacement block in the slot's own
  week (span-aware, occurrence-week-pin-truthfulness D-P10 CHOICE A) and
  `ApproveRequest::releaseOriginal` stamps a `class_exceptions` row
  (CURRENTLY `reason = 'official_event'`) to release the original week.
- The client grid (`ui-common.js buildTimetableGrid` :805–): the class
  family `event-conflict / event-normal / event-replacement / event-pending`
  — `status === 'cancelled'` falls through (no class). The TIP assembly
  (`div.dataset.tip2 = [tipCtx, tipLect, eventStatusLabel(e, isConflict)]`).
- Review R1 🟡 receipts already verified in code: `eventStatusLabel`
  HAS a cancelled branch (:2343 — a modification target, not a new one);
  `pendingByMakeupSlot` keyed on the MAKE-UP SLOT's own week means the
  amber block ALREADY renders today for cancelled+proposed receipts — only
  the `(Proposed ⏳)` hint was unreachable; the rejection teardown needs
  zero work (`pendingBy*` maps key off `status === 'pending'` only — a
  rejected request leaves both maps automatically).
- `buildReplacementNote` (:650): already carries `(Proposed ⏳)` and
  `(Pending since …)` notes — the cancelled note is the third sibling.
- `computeSummary(events.filter(e => e.status !== 'cancelled'))` — 3 blade
  spots drop cancelled blocks from the counts.
- `ClassCancellation.cancel`/`applyLedger` (in-tab flips, the ledger machinery
  from class-cancel-db-persist) already write `status='cancelled'` +
  `cancelledReason` onto the eventRef — nothing currently hides them (the
  vanishing was SERVER-side after a reload; in-tab the skip never ran).

## The discriminator problem (the frozen decision set wants it answered)

The exception row is doing DUAL DUTY — (a) a user cancellation; (b) the D7
original-release stamp (`reason='official_event'`). With the skip lifted,
case (b) would wrongly re-render every RELEASED original week as a red
cancelled block — the exact opposite of law ④.

**Decision (frozen): adopt the archived receipt's own future-work pin**
(`.sdd/archive/2026-10-17-wire-backend-into-refactored-ui/design.md`:196 —
"a tiny future CHECK-widening migration adding `'replaced'`"):
- **ROUND-2 SUPERSEDE: the discriminator is READ-TIME** — skip iff the
  pair is exception'd AND an approved replacement request covers it (the
  release stamp always coexists with an approved pair; a user cancel never
  does). NO check-value regime: no CHECK widen (10 stays), NO
  `ApproveRequest` edit, NO backfill, NO demo-DB paste, and ALL 9 test
  fixture stamps stay `official_event` (audit: the 7 `ReplacementFlowTest`
  stamps are occurrence-PIN fixtures, not release stamps). The release
  value's dual duty is harmless forever because the approved pair decides.

## Scope

| File | Change |
|---|---|
| `app/Concerns/ResolvesTimetableTimeline.php` | the loop head: `if ($exception && ($exception->reason === 'replaced' || a pending covers the occurrence still renders the cancelled block? see design D1))` — the cancelled week renders `status='cancelled'` + `cancelledReason` (the titleCased LABEL string — the payload contract) + the birth-time `occurrenceFact` (kind exception) so the popup's Replace-Now handoff stays available for the cancelled-only state; the PENDING-on-cancelled case renders BOTH the cancelled block (+`(Proposed ⏳)` note via `proposed`) and the amber make-up block (the existing maps, now reachable); the `replaced` release keeps the pre-lane skip |
| `app/Support/ReasonLabel.php` (NEW) | the ONE label law: `from(string $enum): string` — medical_leave → 'Medical/Sick Leave', other → 'Other', the rest titleCase; `ReplacementHome::titleCase` + `ApiReadController::$reasonMap` delegate to it (the promote-on-3rd receipt) |
| `public/js/ui-common.js` | FOUR touch points, named: (①) the two upstream day-event filters `ui-common.js:726` and `:754` — `e.status !== 'cancelled'` → cancelled events REJOIN the grid (today they are discarded before the renderer on both paths — otherwise the server's cancelled events stay invisible on every page); (②) the renderer class family :805 — `status==='cancelled'` → `event-conflict` (reuse — no new CSS); (③) `buildReplacementNote` :650 — the `(Cancelled — label)` in-block note; (④) `eventStatusLabel`'s EXISTING cancelled branch (:2343, returns 'Cancelled') gains the REASON: `'Cancelled — ' + labelFor(e.cancelledReason)` (no new branch; :801's `.toUpperCase()` is existing grid behaviour and stays); `labelFor` passthrough (enum → label; already-labelled values ride) |
| `resources/views/livewire/{my,cohort,student-my}-timetable.blade.php` | the `computeSummary(…filter(e => e.status !== 'cancelled'))` filters RE-OPTIONALISED — the DECISION (see Open decisions) |
| `tests/Feature/*` fixtures | the release-stamp fixtures `official_event` → `replaced` (the ×9 receipt) |
| NEW small `tests/Feature/CancelledBlockStaysVisibleTest.php` | 4 receipts: cancelled-only renders; cancelled+pending renders BOTH; the approved pair skips the original (the derivation); the legacy official_event row renders honestly |
| the changelogs | my-timetable, cohort, student, venue (the venue needs no code change — a receipt note confirming its slot-world is exception-agnostic) |

OUT of scope: `VenueTimetable` (slot-world, no exception awareness today —
unchanged by design; the cancelled venue slot keeps showing the class until
the approved replacement releases it — matches law ③/④), the arrangement
page (already handles proposal-from-cancel via the pinned handoff), DB row
shapes, the undo machinery (the class-cancel-db-persist lane).

## Legacy data — superseded (R1 🔴1 → R2 fold)

The pair-join backfill and the 10→11 CHECK widen are both STRUCK: the
read-time derivation (the approved-pair lookup in `buildEventsByWeek`)
discriminates legacy and future release stamps identically, so the demo DB
needs no paste in this lane. The R1 sheet text lives in `review-log.md`
for the audit trail.

## Open decisions (the user locks these before apply)

1. **Students see it too?** The cancelled block will render red for STUDENTS
   as well (one shared trait). Recommended: YES (honest world-view; the
   class genuinely isn't running).
2. **Summary counts** (Review R1 🔴3 re-machine): `computeSummary`
   (:883–893) never counts cancelled blocks in its conflicts tally, so
   merely un-filtering is mechanically incapable. The locked mechanics:
   - the conflicts tally (:890) gains `|| e.status === 'cancelled'`;
   - the TOTAL class count and the HOURS metric EXCLUDE cancelled blocks
     (a class that doesn't run is not taught — the honest-numbers law, and
     the frozen S9/S11 spec receipt `#sumTotal = 6` keeps its meaning);
   - four call sites total (not "3"): `my-timetable.blade.php:244`,
     `cohort-timetable.blade.php:538`, `student-my-timetable.blade.php:124`
     keep their `.filter(e => e.status !== 'cancelled')` (it is CORRECT for
     total/hours already), `cohort-timetable.blade.php:463` (already
     unfiltered) is untouched; the tally change is ONE edit in
     `computeSummary` (ui-common.js :890) serving all pages.
   The user's lock: the My Conflicts card DOES reflect the red cancelled
   blocks; My Classes/My Hours do not inflate with cancelled hours.
3. **Block inner text**: `(Cancelled — Annual Leave)` note inside the red
   block? Recommended: YES (parallel to `(Proposed ⏳)`), or the tip only.

## The demo-DB safety sheet (the USER pastes)

```sql
ALTER TABLE class_exceptions DROP CONSTRAINT class_exceptions_reason_check;
ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_reason_check
    CHECK (reason IN ('public_holiday','annual_leave','medical_leave','official_event','emergency_leave',
                      'hospitalisation_leave','maternity_leave','compassionate_leave','paternity_leave','other',
                      'replaced'));
```
(No audit change — `class_cancelled` stays.)

## Acceptance

A lecturer cancels an in-window class: the block STAYS on every owned
timetable surface, red (`event-conflict`), tip `{cohort · Cancelled —
Reason}`, popup offers Replace-Now. They propose: the block STAYS (+
`(Proposed ⏳)`), the amber make-up block appears at its own slot. PL
approves: the ORIGINAL block vanishes (the `replaced` release re-keys the
skip), the approved landing block renders in the slot's own week (the
existing D-P10 receipt). PL rejects: the block returns to plain cancelled
red. Reloads agree with the server at every step. Undo (within 5 s or the
home chip) restores the prior state (class-cancel-db-persist's machinery).
