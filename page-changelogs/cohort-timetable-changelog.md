# Cohort Timetable — changelog

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
