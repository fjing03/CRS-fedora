# review-log — venue-intent-ghost-success-spot

- The user's live bug report: the B002/Fri-12:00 modal arrange → the success
  toast lied (the pre-build consume judged the not-yet-painted grid;
  loadCurrentWeek's restore guard despawned the block silently against the
  real occupancy — Friday 13:00–16:00 occupied).
- The fix (1903bea): the boot-tail revalidation via the SAVED entry
  (selectedBlock is already nulled by loadCurrentWeek); on a ghost: drop
  the entry, retract the toast (toast.dismiss), re-arm the intent from the
  untouched urlParams via resolveBookingIntent (the cancel rule honored) and
  let evaluateBookingFit speak the honest manual banner, with the
  banner-kind clause armed for the session path too (from MockData facts).
- Gates: the file 10/10 (the NEW B002 row), the battery spot 17/17, pint ✓,
  phpstan-1G 0, phpunit 230/230; the changelog dated.
- **VERIFY (2026-10-23, tree = 1903bea = origin): the file 10/10** fresh —
  the B002 row green. Tasks 5/5. PASS.
