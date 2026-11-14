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
