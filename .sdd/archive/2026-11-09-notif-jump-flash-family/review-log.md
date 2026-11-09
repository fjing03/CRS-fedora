# Review log — notif-jump-flash-family

## proposal+design+tasks Round 1 — 2026-10-30 (combined with the A-lane stage-2 review)
### 🔴 Fixed
- The theme arms fixed animating NOTHING: td.bookable-flash animates the td
  DIRECTLY (my-timetable/cohort today cells hold .event-block/.cell-empty,
  never .cell-content); the div-level arm .cell-content.bookable-flash
  added for the arrangement's pulseTargetCell; flashRowPulse adds classes to
  the div/td — the css selectors now match both consumers.
- The WRONG jumpToToday patched: the buttons bind the WeekNavigator CLASS
  methods (weekNav.initTodayBtn/jumpToToday per page) — the free function
  is not in the buttons' path; D2 now patches the CLASS method (it already
  delegates to window.buildTimetable).
- pulseTargetCell SPLIT: the summary-card jump (navigateToSavedBlock) gets
  the NEW flashTargetCell (the success-family flashRowPulse pulse = the
  exact earliestBtn look); the FOUR blocked-cell callers keep attention-pulse
  (a success ring on a cell you CANNOT place would break the same-color
  canon). pulseSubjectSelector keeps attention-pulse too.
### 🟡 Addressed
- The A-lane owns .event-block.bookable-flash; this lane owns the td +
  .cell-content arms (both same keyframes — recorded in CID D1).
### 💡 Carried
- (none)
### 🔴 Outstanding
- (none)

## Applied receipt — 2026-10-30 (the user's apply)
- The theme arms: td.bookable-flash + .cell-content.bookable-flash (the
  same bookable-pulse keyframes/token — no new colors).
- The WeekNavigator CLASS METHOD jumpToToday: the today-column flash after
  the build/scroll (250 ms settle) — receipted live on :8000: my-timetable +
  cohort-timetable both flash 22 today-column cells, self-cleaning.
- The arrangement SPLIT: flashTargetCell (the success family) for
  navigateToSavedBlock; pulseTargetCell keeps attention-pulse for the
  blocked-cell callers — receipted (the attention ring still works).
- 🔴 THE APPLY-ROUND FIND (the user's ask exposed it): navigateToSavedBlock
  passed block.day (the day OBJECT from updateSelectionSummary's allBlocks)
  to pulseTargetCell — td[data-day="[object Object]"] matched NOTHING → the
  summary-card pulse was a SILENT NO-OP even in the receipted f4005cc
  chain. FIXED: both call sites pass block.dayIdx. Receipted live: the
  sel-summary-card click now lands .cell-content.bookable-flash
  (cell-selected family), self-cleaning.
- Gates: node --check; pint + phpstan 0; the full suite 277/277; the
  :8000 island spec 7/7; the stale-serve ritual (the live receipt needed
  it — the stale compiled view held the pre-fix blade).
- ONE ui: commit; NO push; no DB writes.
