## 2026-11-10 (later) — Cancel this Class?: the Cancellation Reason block

User brief `prompts/cancel-class-reason-block.md`. Only the **Cancellation Reason** area of the
Cancel this Class? modal changed; the class facts, the eight reasons and their values, the "Other"
textarea and its required detail, the gating, the refusal hint and the success screen are untouched.

| # | Before | Now |
|---|---|---|
| 1 | `You MUST Select a Cancellation Reason` under the rule | **gone** — the rule runs straight into the reasons, and nothing replaced the line |
| 2 | eight reasons, one per row | **two per row** (4 rows); **one per row** under 520 px |
| 3 | `Cancellation Reason` heading, no marker | `Cancellation Reason` + a red `*` (`--color-error`), after the text, visual only |
| 4 | `This action cannot be UNDONE.` in the modal body, centred 14 px | **`CANNOT UNDONE`** in the FOOTER, right-aligned 11 px warning colour, directly above Yes, and **visible only while Yes is armed** |

Mechanics:

- `ui-irreversible-line.blade.php` gained an **opt-in** `compact` arm. Cancel Class passes
  `['compact' => true]` and gets `#cancelClassUndoWarn`; Cancel Replacement Request, Approve Request
  and Reject Request pass nothing and keep `This action cannot be UNDONE.` **byte-identical**
  (measured: 14 px, centred, `compactCount` 0 on `/my-replacements` and `/request-approval-ui`).
- `CancelClassModal._refreshGate()` owns the warning: shown exactly when
  `ClassCancellation.validate()` says the reason is valid, so a cleared reason or a blank "Other"
  hides it again (the inline hint still names the blank-"Other" reason).
- `_swapState()` hides the whole `.cancel-confirm-wrap`, so the warning can never appear in the
  success state (measured: wrap `display:none`, warning `offsetParent === null`).
- `.cancel-reason-required` CSS deleted; `.cancel-reasons`, `.cancel-reason-option` and
  `.cancel-required-mark` added (the radio labels lost their inline styles).
- `tests/cancel-class.spec.ts`: the removed line's assertion became an absence check, plus the
  asterisk, the warning's show/hide, and the 2-column → 1-column receipt.

Measured on all three pages (`/my-timetable-ui`, `/cohort-timetable`, `/venue-timetable-ui`):
no old line, 8 reasons in 4 rows × 2 columns, asterisk `rgb(198, 40, 40)`, warning hidden while Yes
is disabled → visible above Yes once a reason is picked, hidden again on a blank "Other", visible when
it is filled; 1 column and no horizontal overflow at 390 px; zero console errors.

## 2026-11-10 — Modal consistency (#1–#4)

**Follow-up (same day, user ruling B4):** ONE separator in a modal subtitle — the **em dash**.
Replacement Details was the odd one out (`AMSE1003 · Software Engineering (Lecture)`); all four now
read `CODE — Name (Type)`. Measured: Class Details `AMSE1003 — Software Engineering (Lecture)`,
Slot Details `MPU-3232 — Entrepreneurship (Lecture)`, Request Details `AMSE2003 — Software Testing
and Quality Assurance (Tutorial)`, Replacement Details `AMSE1003 — Software Engineering (Lecture)`.

**Follow-up (same day, user ruling B1):** every modal's **Close** is now the outline button —
transparent background, `--color-outline` hairline, `--color-on-surface` text, i.e. it matches
Replacement Details' Close exactly. Class Details / Slot Details used a filled chip before, so the
same word wore two skins. `.btn-close-modal` keeps its name (it is the close hook the markup and the
specs select) — only its skin changed.

**Follow-up (same day, user ruling B3):** the footer action is named **Arrange Replacement**
everywhere — the three timetable pages said "Replace Now" for the same journey. Same blue primary
button, same icon, same 34 px. Measured on My Timetable / Cohort / Venue: `Close h34 · Arrange
Replacement h34`, no footer overflow, and on a 390 px phone `Close h44 · Arrange Replacement h48`
(the app's own touch-target rule).

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

## 2026-11-05 (UI/UX, user brief) — the hover tooltip's own-class segment reads `YOU`, not `ME`

**Change:** `tipLecturerFn` (the shared `buildTimetableGrid` hook this page supplies) now
returns **`YOU`** for the viewer's own classes instead of `ME`. Everything else in the
tooltip is untouched — the cohort segment, the CAPS status word, and the ` · ` separator:

```
before:  AMSE2003 Operating Systems · ME  · DSF2(S1)G1 · NORMAL
after:   AMSE2003 Operating Systems · YOU · DSF2(S1)G1 · NORMAL
```

Others' classes keep printing the lecturer's name (`Daniel Royd Michael · …`), and the
ownership test is still the same name comparison `statusClassFn` uses for the block border,
so the voice and the border weight cannot disagree. No other event type, tooltip content or
behaviour changed.

**Measured** (demo data, lecturer 5425, cohort `DSF2(S1)G1`, read-only probe):
three `.event-block.event-mine` blocks reported
`YOU · NORMAL` / `YOU · NORMAL` / `YOU · NORMAL` (the cohort segment collapses when the
session carries one cohort — as designed), others' blocks kept their lecturers' names, and
**zero** blocks on the page carried a `ME · …` tip.

## 2026-11-05 — the Cancel Class? tooltip names the earliest eligible date (holiday-aware)

Same shared `CancelClass.renderButton` as My Timetable: `You Can Cancel Classes Scheduled From Tue, 3 Nov 2026
Onwards.` / `Rule: The Earliest Class You Can CANCEL Is 2 Working Days Ago.`, with the date read from the S5 cutoff
and weekends + public holidays excluded from the walk-back. Full detail + live receipt: `my-timetable-changelog.md`
(same date).

## 2026-11-05 — Cancel this Class? (shared modal restyle)

The shared `partials/ui-cancel-class-modal` follows the user's UI/UX pass: `Cancel this Class?` title, 40%-wider
shell (588 px), the grey Class Information box (Course / Week / full day + date / time + duration / venue
`CODE — Name` / cohorts `+` / students `A + B = total`), a rule + the static `You MUST Select a Cancellation
Reason` instruction, `No, Keep Class`, and the window tooltip on the button. Full detail + live receipt:
`my-timetable-changelog.md` (same date). Nothing page-specific.

## 2026-11-05 — Cancel Class is FINAL (shared modal)

The Cancel Class confirm dialog (this page includes `partials/ui-cancel-class-modal`) now states
**This action cannot be `UNDONE`.** — `UNDONE` capitals, bold, warning colour — and the post-cancel toast no longer
offers an Undo. Same change as My Timetable; nothing page-specific.

## 2026-11-04 — the page description speaks the same visual language as My Replacements

Minor UI update (user, all pages): the `.page-desc` line now bolds and uppercases its key terms with
the existing `.info-keyword` class — the convention the merged My Replacements page introduced. The
words are unchanged; only the emphasis markup is new (`description` → `descriptionHtml`), so the
rendered sentence reads identically.

## 2026-11-02 (SDD `venue-status-parity` — bug #4) — `Replace Now` now follows the served conflict fact

**The bug.** A conflicted class that already had a **proposed replacement** still
offered `Replace Now` — a dead end that invites a duplicate request. And the
inverse was wrong too: a **cancelled** class — the one that most needs a
replacement — had no button.

**Why.** The gate was a hand-rolled heuristic on the cloned event:
`isMine && (holiday || event.status === 'conflict')`. It could not see a live
proposal (the block is still `conflict`), and `cancelled` is not `conflict`.

**Now.** Byte-parity with `/my-timetable-ui`: the button keys on the SERVED
`occurrenceFact` — `null` means the pair is handled by a live pending/approved
request (or is normal) → no button; a real conflict fact (exception / holiday) →
button. The served array is re-read by `id`+`di` first, because Livewire's marker
morph can blank the DOM clone's extra key.

**Verified live.** cohort W14 `AMSE2003(P)` (proposal exists) = hidden (was
shown); cohort W7 `BMSE3153(P)` (cancelled) = shown (was hidden); cohort W8
`AMSE1003(L)` (holiday, no proposal) = shown (sanity). All three match
my-timetable.

Receipt: `tests/venue-status-parity.spec.ts` (third test group) — 8/8 × 2.

## 2026-11-02 (user ask 2026-10-31) — the summary cards split Confirmed + Pending (`X+Y`)

**What changed (display only).** Four of the five cards now read `X+Y`:
Total Classes, Teaching Hours, My Teaching Classes, My Teaching Hours.
`X` = the confirmed part (normal + replacement), `Y` = the **pending** part.
The **Conflicts card stays a plain number** — untouched, and still the only
card whose colour is red.

**Why.** A single number hid a real difference: "12 classes" told the user
nothing about how many of them were still waiting for approval. `12` → `11+1`
tells them at a glance, and the amber tail is the same amber as the grid's
`event-pending` block (§10.0 same-name-same-colour).

**The shared helper is FROZEN by default.** `computeSummary(events, days)` in
`public/js/ui-common.js` also serves `/my-timetable` and
`/student-my-timetable`; changing its default output would have moved numbers
on two pages nobody asked about. So the split is an **opt-in flag**:
`computeSummary(events, days, { splitPending: true })`, passed ONLY by this
page (both call sites, :463 and :549). Callers that pass nothing get
byte-identical output — proven by a live receipt that runs the same event
array through both modes (`4/5.5/4/5.5` plain vs `3+1` / `4+1.5` split).
`myTeachingStats()` gained **additive** keys (`pendingClasses`,
`pendingHours`); `classes`/`hours` keep their exact values, and its head-slot
dedupe is unchanged — a pending head counts as pending, not as a second class.

**Format law.** `X+Y` renders ALWAYS, even when `Y = 0` (the story stays
visible; an all-confirmed week reads `12+0`). Pending hours are the pending
events' `(end − start + 1) × 0.5` — the same span arithmetic as the confirmed
side, never a re-derivation.

**Styling.** One additive rule in `theme.css`:
`.summary-value .sum-pending { color: var(--color-tertiary); font-weight: 600; }`.
The `X` keeps the card's own colour (success green / primary blue / on-surface).
No inline styles, no per-page CSS.

**Back-face descriptions** (item 2c) re-worded on the 4 cards to explain the
split in house tone; the Conflicts description is unchanged verbatim.

**Receipts.** Live on the testing lane: all four cards carry a
`.summary-value .sum-pending` tail at `rgb(219, 168, 0)`, Conflicts reads a
plain `0`; dark + light + 390px all render the tail. `tests/pages-parity.spec.ts`
(summary ids) unaffected. Specs pinning the old exact strings were relaxed to
`X(+Y)?` patterns (`tests/ui-regression.spec.ts` TC-7.1).

**Not changed.** Which events are counted (statuses, dedupe, holiday rules),
the cancelled-blocks law, and every id (`sumTotal`… stay the same — this page's
JS writes them by id).

## 2026-10-27 (user law, SDD class-cancel-2-working-day-window) — the Cancel window: 2 working days before today + ALL future
**Where this page is concerned:** cohort-timetable — the eventModal Cancel family (same wiring).

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

## 2026-10-24 (SDD cohort-venue-replace-now — follow-up, user ask 2026-10-25) — the Replace Now styles PROMOTED to theme.css (the user's reuse ask)

**The ask (verbatim):** "why didnt it reuse the UI design Replace Now
button of page my timetable? id=btnReplaceNow, class=btn-replace-now".

**The honest receipt:** the lane DID reuse the class + id, but copied the
MARKUP from the venue page — which never carried the CSS (its button had
never rendered, so nobody needed styles there). The ONLY definition of
`.btn-replace-now` lived in my-timetable's page-local <style> block, so
the cohort + venue buttons rendered UNSTYLED (probe: padding 0 / radius 0
/ gray Arial 13px vs my-timetable's red pill) — the lane's own visibility
test couldn't see the difference.

**The fix (the promote-on-3rd-duplication rule, CodingMAIN §10.7):** the
verbatim my-timetable rule set moved to `public/css/theme.css` (the
canonical token source); my-timetable's local duplicate dropped. ONE
source, THREE buttons — the computed-styles parity receipt: bg
rgba(198, 40, 40, 0.35) (the --color-error-container family), 10px 20px,
radius 10px, 14px/500 on ALL THREE pages.

**Gates:** the two modal specs 11/11 combined; phpunit 258/258.

## 2026-10-24 (SDD cohort-venue-replace-now, user law) — Replace Now lands on the cohort eventModal (OWN + conflicted)

**The law (user, verbatim):** "Cohort page & Venue page shud've Replace Now
button too. for OWN Original Conflicted Class." — SUPERSEDES the frozen
class-modal-affordances D1/D3 "no Replace Now on cohort" decision.

**What landed:**
1. the eventModal footer gains the venue's `btnReplaceNow` module
   (`goToReplacementFor(currentModalEvent?.id, currentWeek + 1,
   'cohort-timetable')` — the +1 = the arrangement's pinned pair is
   1-based-validated; the page-family convention the BRIDGE PATCH spells);
2. the gate at openModal = the venue family
   (`isMine && (dayHoliday || status === 'conflict')` — the page's OWN
   conflict definition; the AMSE2003 (T) Dec-24 class is status-normal ON
   the W14 holiday, so the status key alone misses it); fail-closed.
3. review-R1 blocker fixed in the same pass: the page now MAINTAINS
   `window.currentModalEvent` (init at the bridge + assignment at
   openModal — absent before, the click threw ReferenceError);
4. the week navs (prev/next/selectWeek/goToday) now mirror
   `window.currentWeek` (the venue-page :814—826 parity — the inline
   button reads the GLOBAL; the stale boot week would have handed off the
   wrong pair);
5. BackNavigator gains `'cohort-timetable': '/cohort-timetable'` (the
   CANONICAL route — the `-ui` URL is the temporary redirect; the way
   back from an arrangement session opened here now returns HERE).

**Fixtures/receipts:** 5425 owns AMSE2003 (L)/(T) on DSF2(S1)G1; W14 Thu
24 Dec = the holiday. The new spec
`tests/cohort-venue-replace-now.spec.ts` 6/6 ×2 (own-holiday VISIBLE +
handoff `?session=68&week=14&from=cohort-timetable`; others' HIDDEN; own
non-conflict HIDDEN; the venue +1 regression row; the back-nav row).
Gates: pint · phpstan-1G 0 · phpunit 258/258 · class-modal-affordances
6/6 (the tripwire).

## 2026-10-24 (SDD class-modal-affordances) — the Cancel Class? affordance RESTORED on the cohort's eventModal

**The same restore** as my-timetable's (the legacy wiring lost in the
cutover): the page gains (1) the cancel-modal partial include, (2) the
footer section (left Close / right the runtime-appended Cancel button
per the frozen design — the cohort carries NO Replace Now), (3) the
twin-store bridge (MockData.myTimetable re-points to the SELECTED
cohort's rows per rebuild), (4) the CancelClass.renderButton wiring at
openModal (the onCancelled path = closeModal + buildTimetable +
updateSummary). The guards untouched (isCancellable: own/normal+
replacement/future/holiday). Persistence = restore-first (see the
my-timetable entry).

Receipts: the new browser rows — cohort own → the button appears;
others' class (4288 viewing 5770's AMCS2093 cohort) → the button count 0.

# Cohort Timetable — changelog

## 2026-10-23 (SDD week-memory-until-logout, user law 2026-10-20) — the first-ever load() closes the read gap; the memory is session long

The page's ONE honest gap closes: its first-ever `weekNav.load();` right
after `_currentWeek = currentWeek` (BEFORE the first populateWeekSelect/
buildTimetable) — the first paint now consumes the restored session
version; the write side needed nothing (prev/next/select/goToday already
funnel the class methods' `save()`). The ctor carries the 6-arg shape
`(…, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) })`
(key `cohortTimetableWeek` kept) so save()/load() live at
`weekmem-cohortTimetableWeek@<login_week_stamp>` in **sessionStorage** —
fresh visit = TODAY (the server's today family sets `_currentWeek` first,
the load overrides to the page's own saved version when one exists), and
a fresh login's stamp makes old keys inert. The faculty-switch
"re-anchor to today" tail stays DEliberate (a switch re-lands today — the
last-visit-dd D3 family; receipted as the page's own contract in the
change's review-log, each apply-era receipt #1). Receipts: M4
(cohort today family + the deliberate revisit receipt) ✓ —
`week-memory-until-logout.spec.ts` 8/8; pint ✓ · phpstan-1G 0 ✓ ·
phpunit 230/230 ✓.

## 2026-10-20 (SDD last-visit-dd-memory) — the dd memory: the last faculty+cohort pair survives the session

Staff arrivals restore the LAST-visited faculty+cohort pair (per-tab
sessionStorage, the id-suffixed key; the 2026-10-13 default rule stays the
verbatim fallback). The remember gate = USER PICKS only (the inline
onchange gets `{ origin: 'user' }`; the programmatic paths + the restore
never write). Non-taught but payload-valid picks restore (staff browse
them). Students stay pinned (FR 1.2 untouched).
## 2026-10-20 (user round, CHOICE A) — same my-timetable rule

The cohort page shares the timeline trait: the class's own natural block
survives the proposal's landing week; both the natural + the receipt/proposal
cells render. The replaced week's suppression stays keyed on the D7 stamp
(W14 is normal since it is replaced).
## 2026-10-19 (user request) — the approved block reads the REPLACEMENT BLUE + the date fix

The block for an approved request now carries the my-timetable page's own
replacement hue: new `.event-mine-replacement` (blue + 3px ownership border)
/ `.event-others-replacement` (blue + 0.5px hairline) class pairs in
`theme.css` — colour = status (same blue family on every timetable page),
borders stay two-axis. The `(Replaced for …)` note's date re-keyed to the
ORIGINAL class's own occurrence (W14 → 24-Dec-2026 instead of the landing
week's 22-Oct — the venue page's formula).
## 2026-10-19 (user report) — legend REPLACEMENT + the today clock

The cohort legend swaps the "replacement sessions fold in here" parenthesis for
the REAL **Replacement** legend item (blue, same swatch/features/scene order as
My Timetable's frozen legend) — an approved request now shows as the dark
block with its own legend entry.

The TODAY accuracy: the page's data bridge never cleared `MockData.mockNow`,
so `TodayButton / getTodayMs()` anchored the mock-era demo clock — the legacy
`MockData.mockNow = null;` line lands with the其他 real-clock pages (the same
line the venue page already carried — user report 2026-10-19).
## 2026-10-27 (SDD class-cancel-db-persist) — Cancel Class writes the REAL database
Shared machinery — no page-local changes beyond the component gaining the
`use CancelsClasses;` line. The confirm await posts `cancelClass` via the
shared trait (server truth; the pre-existing modal copy untouched), the
reason radios carry the DB enums with the 7-leaf + Other labels, and the
undo toast awaits `cancelClassUndo` before any navigation. Demo-DB CHECK
widen pasted by the user per `docs/demo-db-data-changes.md`.

## 2026-10-27 (user law, SDD cancelled-blocks-stay-visible) — the cancelled block STAYS red
The shared trait renders the cancelled block (cancelled + label + the
Proposed hint + the fact) on the cohort surface too. The page's own
two-axis `statusClassFn` gained the cancelled arm FIRST (§10.0: same
`event-conflict` red for everyone, ownership borders kept — 3px mine /
0.5px others'), otherwise the cancelled block would have rendered NEUTRAL
(the custom statusClassFn short-circuits the shared family). The summary
cards follow the locked law (conflicts INCLUDE cancelled; classes/hours
exclude). Same read-time discriminator as my-timetable.
## 2026-10-27 (user law, BMSE3153-class-info) — the modal shows the FULL membership on both cohort views
**The root cause:** the cohort page's session scope code (the `cohortSelect`
filter) was stamped as the modal's `Cohort` row — the filtered view label
masquerading as the class's cohort list. The membership lives in the event's
`cohorts` list already.
**The fix:** the modal's `Cohort` row = the session's OWN membership
(`cohorts.join(' + ')` for multi-cohort — `RSD3(S1)G2 + RSD3(S1)G3` in BOTH
the G2 and the G3 view; single-cohort keeps the scope label), and a NEW
`Total Students` row with the `A + B = total` breakdown from the payload's
positional `studentCounts` (the mock-data convention).
**cohortSelect semantics unchanged** — it still filters the displayed
timetable; it no longer masquerades as the data.

## 2026-10-27 · breakdown logic promoted to the shared totalStudentsLabel
Follows 910b97c: the inline `A + B = total` construction in the Class Details modal
now calls `window.totalStudentsLabel(event)` (ui-common.js) — the venue page's slot
modal became the 3rd copy, so per promote-on-3rd the helper moved to ui-common.js
and this page's copy was retired. Cohort row behavior unchanged (full membership
joined for both single- and multi-cohort views).
## 2026-10-27 (SDD mock-statics-die) — receipt note
No code changes here: the cohort page never consumed `MockData.cohorts` /
`arrangementWeeks` (the payload's per-event cohort fields + the real
semester walk cover it). The deleted statics are receipted in the
my-timetable/venue changelogs.
## 2026-10-27 (SDD b005-diploma-allowed) — receipt note
The derived B005 conflict arm is dead — the timeline concern's
`status` renders normal unconditionally for this page's payload (the
`cancelled-blocks-stay-visible`/cancelled branches untouched).
