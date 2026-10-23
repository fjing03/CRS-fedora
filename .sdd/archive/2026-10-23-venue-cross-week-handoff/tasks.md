# Tasks — venue-cross-week-handoff

- [x] T1. `resources/views/livewire/venue-timetable.blade.php` —
  `confirmIntentSession` (:552–562) passes `pickedRow.dataset.week` as the
  7th arg of `bookVenueWithSession` (empty-string-safe, design D1).
- [x] T2. Same file — `bookVenueWithSession` (:565/:580) gains the
  `occurrenceWeek` param; `&week=` becomes
  `occurrenceWeek ? occurrenceWeek : currentWeek + 1` (the builder's rest
  untouched). Consumer/server deliberately NOT edited (design D2).
- [x] T3. NEW `tests/venue-cross-week-handoff.spec.ts` — the cross-week
  pick repro row (W7 slot → the `(L) W8, Mon, Nov 9` row → `&week=8` →
  the honest strip; the pin assert as a non-discriminating secondary
  receipt) + the same-week byte-parity control row (design D6).
- [x] T4. Stale-cache ritual (`pkill` → rm compiled views → serve,
  two separate commands); re-run `tests/auth.setup.ts --project=setup`
  if storage states expired; run the new spec +
  `tests/venue-intent-modal.spec.ts` + `tests/venue-book.spec.ts` +
  `tests/default-pin.spec.ts` with `--grep-invert "TC70"` — A6/C2,
  the venue-book 8-param assert and default-pin's hydrated-pin boot
  rows must stay green byte-for-byte.
- [x] T5. `page-changelogs/replacement-arrangement-changelog.md` — dated
  fix entry (the param semantics from design D3); AND
  `page-changelogs/venue-timetable-ui-changelog.md` — dated fix entry
  (the handoff URL + the `bookVenueWithSession` signature); both state
  design D5's residual honestly (non-earliest cross-week picks reach
  full end-to-end truth only when `session-arrival-pin-guard` lands).
- [x] T6. Record the live receipts (URL + strip + pin) from the repro
  row in the changelog entries; run the standing gates (`php -l` on the
  blade). NO phpunit delta (blade-JS only).
- [x] APPLIED receipts (2026-10-20): the 3 blade edits (T1's 7-arg call +
  comment; T2's `occurrenceWeek` param + `&week=` row-week with the
  viewed-week fallback — the comment corrected declaratively: the
  PRE-SELECT path threads too, `chooseSession(first)` marks `.active`,
  so the row-'' fallback applies only to weekless rows; `php -l` clean).
  T3: `tests/venue-cross-week-handoff.spec.ts` S1 (the exact repro:
  `&week=8`, the honest strip `Week 8 … Nov 09 2026 … @ B110`, the pin
  `{97,8}`) + S2 (the same-week byte-parity control) — 2/2 green. T4: the
  ritual + the trio run = 16/17 green (default-pin 3/3, A6, U2a-T3 green;
  C2's toast-poll failure reproduced on the STASHED pre-change baseline =
  pre-existing noise, not from this change). T5/T6: both changelogs dated
  + the D5 residual wording recorded.
