# Tasks — notif-click-highlight

## T1. The handoff (≤0.5h)
- [x] The row partial: `data-notif-target` (r/s mapping per D2; the inactive
      arm untouched).
- [x] The capture listener: the href append BESIDE the durable POST.

## T2. The arrival helper + theme (≤0.75h)
- [x] `applyNotifHighlight()` in ui-common (the wired call at DOMContentLoaded
      AFTER initNotifPanel; the 5×~150 ms retry; the history cleanup).
- [x] ZERO css change — verify NO new classes (the existing bookable-flash
      pulse reused; the flashRowPulse promo is JS-only).
- [x] node --check.

## T3. The receipts (≤1h)
- [x] :8030 seeded flows: pl row → queue row FLASH (bookable-flash, ~1.3 s);
      history rejected row; READ rows too; the inactive row = no append/no
      params; the URL-cleanup receipt; the pagination-miss graceful skip
      receipt.
- [x] :8000 — the DOM/structural snapshot only (NO real row clicks — the
      durable POST has no env guard); island spec --grep-invert "TC70").
- [x] pint + phpstan 0 (the blade changed) + the full suite.

## T4. Bookkeeping (≤0.3h)
- [x] The changelog (the notifications panel + both target tables' pages);
      ONE commit (`feat:`), no push; sdd.yaml → applied on green.
- [x] STAGE-2 (the timetable surfaces) parked on the user's week ruling
      (documented in the review-log).

## T5. STAGE 2 (activated — ruling A + the cancel-row link law)
- [x] The emitter link law: the actor + the dept-PL rows → /my-timetable-ui
      (the students' rows untouched); php -l.
- [x] The row partial: `data-notif-week` emission; the append carries
      `notif-w`.
- [x] The /my-timetable-ui arrival: the week switch + the data-sid bridge
      stamp + the cell flash; the A-exception carve-out record.
- [x] The :8030 receipts: the owner's end-to-end (cancel → click → the week
      switch → the flash); the dept-PL copy graceful skip; the ?week=
      unchanged regression.
- [x] T2's stage-1 receipts re-run (no regression); the changelog.
- [x] COMMIT PLAN: ONE `feat:` commit covering STAGE-1 + STAGE-2.
