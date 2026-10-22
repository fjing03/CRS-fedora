# Tasks — venue-cross-week-handoff

- [ ] T1. `resources/views/livewire/venue-timetable.blade.php` —
  `confirmIntentSession` (:552–562) passes `pickedRow.dataset.week` as the
  7th arg of `bookVenueWithSession` (empty-string-safe, design D1).
- [ ] T2. Same file — `bookVenueWithSession` (:565/:580) gains the
  `occurrenceWeek` param; `&week=` becomes
  `occurrenceWeek ? occurrenceWeek : currentWeek + 1` (the builder's rest
  untouched). Consumer/server deliberately NOT edited (design D2).
- [ ] T3. NEW `tests/venue-cross-week-handoff.spec.ts` — the cross-week
  pick repro row (W7 slot → the `(L) W8, Mon, Nov 9` row → `&week=8` →
  the honest strip; the pin assert as a non-discriminating secondary
  receipt) + the same-week byte-parity control row (design D6).
- [ ] T4. Stale-cache ritual (`pkill` → rm compiled views → serve,
  two separate commands); re-run `tests/auth.setup.ts --project=setup`
  if storage states expired; run the new spec +
  `tests/venue-intent-modal.spec.ts` + `tests/venue-book.spec.ts` +
  `tests/default-pin.spec.ts` with `--grep-invert "TC70"` — A6/C2,
  the venue-book 8-param assert and default-pin's hydrated-pin boot
  rows must stay green byte-for-byte.
- [ ] T5. `page-changelogs/replacement-arrangement-changelog.md` — dated
  fix entry (the param semantics from design D3); AND
  `page-changelogs/venue-timetable-ui-changelog.md` — dated fix entry
  (the handoff URL + the `bookVenueWithSession` signature); both state
  design D5's residual honestly (non-earliest cross-week picks reach
  full end-to-end truth only when `session-arrival-pin-guard` lands).
- [ ] T6. Record the live receipts (URL + strip + pin) from the repro
  row in the changelog entries; run the standing gates (`php -l` on the
  blade). NO phpunit delta (blade-JS only).
