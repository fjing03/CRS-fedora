# tasks — `venue-intent-ghost-success-spot`

- [x] T1 (the decode, receipts): the repro probes — the exact URL grounds the
  success toast + `r:manual?false` (the = the stale pre-rebuild grid), cells
  0 at EVERY sample, the banner empty; the final DOM shows B002-Fri
  13:00–16:00 = `cell-occupied` (the real blocker). The chain = the
  pre-build consume → the ghost place → `loadCurrentWeek()`'s restore-guard
  death (a span cell not `cell-available`) → the silent despawn.
- [x] T2 (the fix, ONE strike in the boot tail): the ghost is detectable
  ONLY via the saved entry (`selectedBlock` = already nulled by
  `loadCurrentWeek`) — after the boot's real-grid paint, re-validate the
  SAVED selection; on a mismatch: drop the entry, retract the lying toast
  (`toast.dismiss()`), re-arm the intent from the untouched urlParams
  (`resolveBookingIntent()` — the cancel rule honored) and let
  `evaluateBookingFit()` speak the honest manual banner, with the
  venue-intent-banner-kind clause ARMED for the session path too (from
  MockData's module/session facts — the modal offered exactly this session
  for this venue).
- [x] T3 (the rows): the NEW B002 row (the honest end-state = no ghost cells
  + the banner's slot complaint + the kind clause + the bar hidden); the
  honest-success control stays green (the C2 legal-slot row: the toast +
  the landed block).
- [x] T4 (the gates): venue-intent-modal **10/10**; the battery spot
  (cross-week + venue-book + pin-guard + earliest-week) **17/17**; pint ✓;
  phpstan-1G **0**; phpunit **230/230** (the blade = a PHP file). TC70 never
  runs; the demo DB untouched.
- [x] T5 (the docs): the dated changelog entry on the arrangement page.
