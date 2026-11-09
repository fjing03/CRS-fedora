# Proposal — notif-jump-flash-family (SEPARATE lane, per the user's placement question)

## The user's ask (2026-10-30)

Two more flows must behave EXACTLY like the `#earliestBtn` jump-flash:
"shift to that selected cell and highlight it (in a flash)".

1. The **`sel-summary-card`** on /replacement-arrangement
   (`Click to Go to This Selected Slot on the Timetable.`) — it ALREADY
   week-jumps/remembers (`navigateToSavedBlock` → `selectWeek` +
   `pulseTargetCell`) but its pulse is the page's private `attention-pulse`
   (a blue ring, 1.2 s) — NOT the earliestBtn's `bookable-flash` look
   (the success ring, 1.15 s).
2. The **Today button (`#todayBtn`)** on EVERY timetable/venue page —
   `jumpToToday()` builds the current week's grid + scrolls to the grid...
   and highlights NOTHING (no cell flash).

## The change (reuse, the SAME flash family)

1. **The theme (ONE, tiny extension to the existing family — not a new
   class):** `tr.bookable-flash > td` covers whole-row pulses; the CELL
   dimension gets the SAME animation:
   `td.bookable-flash > .cell-content, tr.bookable-flash > td.cell-content { animation: bookable-pulse 1.15s ease-in-out 1; }`
   — the same keyframes (bookable-pulse, the success token), no new colors.
2. **`jumpToToday()`**: after the build + scroll, flash the TODAY cells —
   `flashRowPulse`-style across `td.today-cell`s (the class reuse; the
   same 1.15 s timing; scrollIntoView to the grid preserved).
3. **`pulseTargetCell` (the arrangement summary-card path)**: exchanges its
   private `attention-pulse` for the shared pulse — the SAME bookable-flash
   class via `flashRowPulse` (once notif-click-highlight lands its helper;
   ORDER: notif-click-highlight first). The `sel-summary-card` navigation
   (selectWeek + venue select) is untouched — only the flash look aligns.

## Why SEPARATE from notif-click-highlight

- notif-click-highlight is frozen at the request-rows + the cancel-row
  arrival surface (the notification-driven entry); these two are
  ENTRY-POINT flows (the summary card + the Today button) touching MANY
  pages' week-nav — cramming them in would unfreeze a reviewable lane for
  unrelated surfaces.
- Both lanes share the same `bookable-flash` family + the `flashRowPulse`
  helper (notif-click-highlight lands the helper first; this lane consumes
  it + adds the td/cell theme arm).

## Out of scope

- The notification emitter, the durable POST, RBAC; the DEEP-LINK laws
  (the today jump is a same-page action — no arrival exception involved).
- The private `attention-pulse` definition stays in the blade (its OTHER
  consumer — the pending-intent flow — keeps it); only pulseTargetCell
  swaps to the shared family.

## Success criteria (:8030 + :8000 those pages are read-only-capable)

- #todayBtn on my-timetable/cohort/venue: the grid builds the current week,
  scrolls, and the today column cells FLASH (bookable-flash, ~1.15 s) —
  receipted on 1–2 representative pages.
- The sel-summary-card: the jump lands on the selected venue+week+day and
  the target cell flashes with THE SAME look.
- No console errors; the existing gates (pint + phpstan 0 + the suite +
  node check + the island spec) green; ONE commit, no push.

## Risks

- The today-column flash touches EVERY page consuming #todayBtn — the risk
  is visual only (the same animation); the receipt covers the representative
  pages before the commit.
- pulseTargetCell's OTHER caller (the pending-intent path, ~3034) inherits
  the SAME look — intended (the same success-family flash); receipted.
