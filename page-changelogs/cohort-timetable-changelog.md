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
