# Spec — student-modal-parity

Capability: the student **Class Replacements** pop-up (`#classModal` on `/student-class-replacements`)
presents the same information, in the same shape, as the staff **Replacement Details** pop-up
(`#quickViewModal` on `/my-replacements`), minus anything a student must not see.

Every requirement is testable from the DOM after opening one row.

## R1 — Header

- R1.1 The title reads exactly `Replacement Details`.
- R1.2 The subtitle reads `<CODE> — <Name> (<Class Type long>)` with an em dash, e.g.
  `BMIT2013 — Web-Based Integrated Systems (Lecture)`; no middle dot, no leading `#`.

## R2 — Facts strip

- R2.1 A `.detail-facts` strip renders above the tabs with exactly ONE item, labelled `Conflict`.
- R2.2 A holiday row's strip prints the badge IN FULL: `Public Holiday — <holiday name>`
  (e.g. `Public Holiday — Deepavali Holiday (In Lieu)`), with no `data-tip`.
- R2.3 A cancelled-class row's strip prints `Class Cancelled` and nothing more — a leave reason is
  never sent to a student.
- R2.4 The badge keeps its existing classes and colours (`badge-holiday` / `badge-reason`).

## R3 — Tabs

- R3.1 No tab is labelled `Conflict Reason` (nor `Course`).
- R3.2 When the row has a requested slot the tabs are, in order:
  `Requested Slot Schedule`, `Original Slot Schedule`, `Class Info`, `Request Status (<short>)`.
- R3.3 When the row has no requested slot the tabs are `Original Slot Schedule`, `Class Info`,
  `Request Status (<short>)`.
- R3.4 The pop-up opens on `Requested Slot Schedule` when present, otherwise on `Class Info`.
- R3.5 `Request Status` carries the short status in brackets when the payload provides it, and reads
  bare `Request Status` when it does not.

## R4 — Tab content

- R4.1 `Original Slot Schedule` rows, in order: `Class ID` (`#<sessionId>`, strong) · `Week` ·
  `Day & Date` (full day name, date, countdown in brackets) · `Time & Duration` · `Venue`.
- R4.2 `Requested Slot Schedule` rows, in order: `Request ID` (`#<id>`, strong) · `Week` ·
  `Day & Date` · `Time & Duration` · `Venue`.
- R4.3 `Class Info` rows, in order: `Lecturer` · `Cohorts` · `Students`; an unknown student count
  renders `—`, never `0`.
- R4.4 `Request Status` shows the status badge, the completed note (completed rows only) and the
  requested-at line (when present). It shows no rejection reason and no remark.

## R5 — Footer

- R5.1 `Close` is the LEFT-most footer button and carries the outline style.
- R5.2 `Go to My Timetable` is the RIGHT-most footer button, carries the primary action style, and
  shows the calendar glyph.
- R5.3 `Go to My Timetable` is hidden unless the row's status is `approved` or `completed` AND the
  row has both a request id and a requested slot.
- R5.4 The two buttons never swap sides, whatever the row.

## R6 — Preserved behaviour

- R6.1 Clicking a row or card still opens the same pop-up; Escape, the header ✕ and an overlay click
  still close it.
- R6.2 The jump still navigates to `/student-my-timetable-ui?repl-hl=<requestId>&repl-w=<week>`.
- R6.3 The table, the phone cards, the filters, the summary cards and the week navigation are
  untouched by this change.

## Scenarios

| # | Given | Then |
|---|---|---|
| S1 | BMIT2013 row, approved, week 7 | title `Replacement Details`; subtitle `BMIT2013 — Web-Based Integrated Systems (Lecture)`; strip `Conflict  Public Holiday — Deepavali Holiday (In Lieu)`; 4 tabs opening on Requested; `Request ID #27`, `Class ID #24`; Class Info has the lecturer, `RSD2 (S1) G2`, a student count; Close left, blue jump right |
| S2 | a pending row | same shape, no `Go to My Timetable` |
| S3 | an unarranged row (no request) | 3 tabs, opens on `Class Info`, no Requested tab, footer shows Close only |
| S4 | a cancelled-class row | strip reads `Class Cancelled`, no leave reason anywhere in the pop-up |
| S5 | any row, 390px wide | the pop-up opens, its footer stacks, no horizontal overflow |
