# Tasks — arrangement-earliest-week-default

- [x] T1. `resources/views/livewire/replacement-arrangement.blade.php` —
  the boot edit (design D1's exact shape): the pre-load
  `hadSavedWeek` probe (both keys, try/catch), then the
  `else if (!hadSavedWeek) { const eb = firstBookableDay(weekData); if (eb)
  weekNav._currentWeek = eb.week; }` memoryless default between the
  pinned bridge and `populateWeekSelect(...)` — no save call, silent.
- [x] T2. NEW `tests/arrangement-earliest-week-default.spec.ts` — the
  rows: (1) add-init ENFORCES memoryless (delete both week keys) →
  plain landing → the selector equals firstBookableDay(window.weekData).week
  + the toast bar silent; (2) the SAVED week: land → pick a bookable
  idx at runtime (different from the default's) → seed arrangementWeek
  → reload → the selector = that idx (no hardcoded value); (3) the pin
  wins memoryless (?session=49&week=14 fresh context → the selector =
  '13' + arrangementWeek = '13'); per design D5 (all rows add-init
  enforce memoryless where stated).
- [x] T3. Stale-cache ritual (kill serve → rm compiled views → serve,
  two separate commands; re-auth via `tests/auth.setup.ts
  --project=setup` if storage states expired).
- [x] T4. Run EXACTLY: npx playwright test
  tests/arrangement-earliest-week-default.spec.ts
  tests/session-arrival-pin-guard.spec.ts tests/default-pin.spec.ts
  tests/replace-now.spec.ts tests/venue-cross-week-handoff.spec.ts
  tests/venue-intent-modal.spec.ts --grep-invert "TC70" (C2's fail =
  the documented pre-existing noise). php -l clean; no phpunit delta
  (blade-JS only).
- [x] T5. `page-changelogs/replacement-arrangement-changelog.md` — dated
  entry (the user's Q2-A/Q3-A decisions + the priority chain + the
  silent-landing note + the D3 intent nuance).
- [x] T6. Record the user's Q1–Q3 decisions verbatim in
  `explore-brief.md` (already written) and keep apply in
  `arrangement-earliest-week-default`'s own lane — never bundled with
  the pin-guard or cross-week changes (the user's SEPARATE ruling).
- [x] APPLIED receipts (2026-10-20): the D1 edit landed byte-for-byte
  (the pre-load both-keys probe + the memoryless `firstBookableDay`
  default; `php -l` clean). T2: the spec 3/3 (D-row1/D-row2/D-row3);
  the toolkit's first-run toast-silence assert was ROW-SCOPED down after
  the STASH-PROOF (the page's own "Undo" bar shows on plain landings
  pre-change too — pre-existing family, not this change's silence
  contract; the silence receipt = D1 contains zero toast calls).
  T4: the ritual + 71-green regression set with the documented
  pre-existing family (C2 noise + the two venue-timetable baseline
  rows). T5: the dated entry above. T6: the user's Q1–Q3 decisions
  verbatim already live in explore-brief; the lanes stay separate.
