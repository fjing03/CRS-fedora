# tasks.md — `venue-intent-banner-kind` (the user's option 2, one voice)

- [x] T1 (the premise carried from venue-c2-toast-noise 56d769b): the manual
  branch is not silent (the amber banner + the pulse + the subject pre-select);
  the floating toast = rejected redundancy; the kind move = complete the
  banner with the fallback attribution.
- [x] T2 (the edit): `renderBookingIntent()`'s `intentNeedsManual` branch
  appends the `<em>` clause when `fallbackAttribution` is live:
  *Pre-selected <module> (<type>) — its sessions fit <venue>; pick another
  free slot — the pre-selection stays.* — one blade, no CSS, no app logic.
- [x] T3 (the row): `C2b — the manual arrival banner is KIND-COMPLETE (one
  voice, the attribution clause)` = the receipted trap arrival (W8-Tue 11:00)
  asserts the pre-selection stayed bound + the banner's 4 clauses (the slot
  complaint, the attribution, the fit, the survives) + the third-voice guard
  (no 'auto-picked' in the toast bar).
- [x] T4 (the gates): venue-intent-modal **9/9** (26.2s; the row 2.7s; ×2 solo
  green); battery spot (cross-week + venue-book + pin-guard) **14/14**;
  pint ✓; phpstan-1G **0**; phpunit **230/230**. PHP touched → the full gates
  ran. TC70 never runs; the demo DB untouched.
- [x] T5 (the docs): the replacement-arrangement-changelog dated entry.
