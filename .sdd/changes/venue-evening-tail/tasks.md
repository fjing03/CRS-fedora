# Tasks — venue-evening-tail

> **Apply status:** frozen 2026-11-14. Demo DB is read-only for agents; the
> testing DB is currently empty, so verification is a read-only probe.

- [x] T1. `lastOfferIndex(di)` + `isServedTail(di, hi)` helpers beside `roomCellState`.
- [x] T2. `nextActualBlockAfter` bounded by the last served row; `roomBeforeNextEvent` bounded by `hours.length`.
- [x] T3. `cellRender`: tail joins the available branch, counts, suppresses `cell-no-fit`, keeps `cell-no-room`.
- [x] T4. `tests/venue-book.spec.ts`: derivation updated to the new law; the `room === Infinity` arm and `noNextBlocker` assertion REPLACED by the day-end ground (`hi + room === hours.length`); the new assertions (R2–R5) added.
- [x] T5. Read-only probe on the demo lane (B101 week 9): widths/labels/counts.
- [x] T6. Changelog entry + `php artisan view:cache` + `git diff --check`.
- [x] T7. Tests + commit only this lane's files (never push).

## Round 2 — the evening becomes real (user, 2026-11-14)

- [x] T8. Data: add 18:00–18:30 and 18:30–19:00 rows for every venue/week/day (snapshot, rehearsal, log).
- [x] T9. `TimeSlotsSeeder` loops `$hour < 19`.
- [x] T10. Retire the red `.cell-no-fit` day-end arm; 18:30–19:00 reads green "Not Enough Slot".
- [x] T11. Tests: T6 rewritten, the audit's day-end arm removed, `cell-no-fit` count asserted 0.
- [x] T12. Verify on the demo page + changelog + data log.
