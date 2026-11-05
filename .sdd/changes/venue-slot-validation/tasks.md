# Tasks — venue-slot-validation

## A · Hover floor (R1)
- [x] A1 `venue-timetable.blade.php`: replace `BOOK_SPAN` / `MIN_SLOTS` with one fixed
      `MIN_BOOK_SLOTS = 2` (comment: the brief's one-hour floor, never a class assumption) and
      repoint `cell-no-fit`, `cell-no-room`, the paint loop and the red-cell toast at it.
- [x] A2 Delete the `window.minClassSlots` line from the page payload.
- [x] A3 `VenueTimetable.php`: delete `minBookableSlots()` + `BOOK_SPAN_FALLBACK` and the
      `minClassSlots` payload key; keep `bookableCandidates()`.

## B · Stage 1 — no verdict before selection (R2)
- [x] B1 Lift `spanBlocked(di, hi, spanSlots)` and `nextEventStartAfter(di, hi)` out of
      `renderIntentSessionModal()`; make `roomBeforeNextEvent()` a wrapper over the latter.
- [x] B2 `refreshSessions()`: list every session, stamp `data-slots` / `data-fit`, pre-select the
      first fitting row (else the first), never touch `#intentNotice`, never hide the pickers.
- [x] B3 `arrangeBtn.disabled` iff there is no row at all.

## C · Stage 2 — the gate (R3)
- [x] C1 `openIntentSessionModal()`: add `di`, `hi`, `venue` to `intentContext`.
- [x] C2 `confirmIntentSession()`: day-end check, then overlap check, then the unchanged
      `bookVenueWithSession(...)` call.

## D · Warning modal (R4)
- [x] D1 `partials/ui-intent-session-modal.blade.php`: `#slotWarningOverlay` markup with
      `#slotWarningHead` / `#slotWarningBody` / `#slotWarningGuide` / `#slotWarningTail`,
      `#slotWarningPickBtn`, header ×, overlay click.
- [x] D2 `showSlotWarning(kind, facts)` + `closeSlotWarning(ev)` in the page script; duration
      formatter (`1 hour` / `1.5 hours` / `2 hours`); close the intent modal first.

## E · Tests
- [x] E1 `tests/Feature/VenueTimetableTest.php`: drop the two `minClassSlots` cases.
- [x] E2 `tests/venue-book.spec.ts`: red-cell toast → fixed 60 minutes; the two-state audit uses
      the fixed floor; add U2a-T11…T16 for the brief's six verification cases (warning modal copy,
      no navigation, subject switch shows no warning).
- [x] E3 Run the venue specs on a fresh `:8030` seed, plus the full PHPUnit suite, pint, PHPStan,
      `node --check`, `view:cache`.

## F · Records
- [x] F1 `page-changelogs/venue-timetable-ui-changelog.md` entry (what changed, what the user saw
      before, the measured receipts).
- [x] F2 `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` session entry.
