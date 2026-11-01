# Tasks — notif-jump-flash-family

## T1. The helper-first ordering (≤0.1h)
- [x] Confirm notif-click-highlight applied (flashRowPulse in ui-common).

## T2. The theme cell arm (≤0.2h)
- [x] theme.css: `td.bookable-flash` (animate the td directly) +
      `.cell-content.bookable-flash` (the arrangement's div target) — the
      lane-OWNED arms; the same keyframes/token.
- [x] node check / no PHP.

## T3. The today button + the summary-card pulse (≤0.5h)
- [x] The WeekNavigator CLASS METHOD jumpToToday (the buttons bind the
      class methods, not the free function): the today-column flash after
      the build/scroll.
- [x] The NEW flashTargetCell(day, hour) for navigateToSavedBlock (the
      summary-card jump = the success-family pulse); pulseTargetCell stays
      attention-pulse for the FOUR blocked-cell callers (the same-color
      law).

## T4. Receipts + gates (≤0.75h)
- [x] #todayBtn on my-timetable + venue (and cohort for the family receipt).
- [x] The sel-summary-card jump+flash; the pending-intent path pulse receipt.
- [x] The gates: lint (blade only…) + phpstan + the full suite + node check.
- [x] The changelog (the arrangement + the timetable pages); ONE commit
      (`ui:`), no push; sdd.yaml → applied on green.
