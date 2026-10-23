# Tasks — arrival-grid-no-pin

- [ ] T1. `resources/views/livewire/replacement-arrangement.blade.php` —
  design D1's edit: the collapsed
  `if (!window.pinnedOccurrence && hadSavedWeek) {} else { earliest }`
  branch + the `selectWeek(pinnedIdx)` call and its BRIDGE PATCH comment
  DELETED (the arrangementWeek persistence dies with it); REWRITE the
  Q2-A block comment above it too (the :3506–3519 family) — its "the
  pinned bridge above stays first (URL > saved > earliest > week 1)"
  and "its entries = user-arrived or pinned positions" lines are STALE
  under the new law (round-1 🟡4a).
- [ ] T2. `tests/session-arrival-pin-guard.spec.ts` — S1 ONLY retunes
  (round-1 🟡1: S2 carries no landing asserts — leave it): the selector
  (earliest-derived via firstBookableDay) + DELETE the arrangementWeek
  assert + REWRITE S1's inline T6-B landing comment (the receipts
  future reviewers read). S3 survives.
- [ ] T3. `tests/arrangement-earliest-week-default.spec.ts` — D-row3
  rewritten: the pinned arrival lands EARLIEST (the pin keeps the data
  pair); D-row1/2 unchanged + re-run; REWORD the suite-header priority
  chain ("URL pin > saved week > …" → "plain: saved→earliest; pinned:
  earliest; combined pinned+intent → the destination jump wins")
  (round-1 🟡4c) — and fix the proposal's mojibake if any survives.
- [ ] T4. NEW `tests/arrival-grid-no-pin.spec.ts` — the law's rows
  (design D4's four receipts; the spelled add-init discipline:
  memoryless rows remove BOTH keys; the saved-week row = the
  delete→derive→reseed two-hop; row 2 also asserts arrangementWeek
  STILL equals the seeded value — the rent-is-dead receipt).
- [ ] T5. The ritual (`pkill` → rm compiled views → serve, two separate
  commands; re-auth if storage states expired) + the Playwright battery
  EXACTLY per design D5 (`--grep-invert "TC70"`; C2 = the noise;
  re-baseline A6 at the run).
- [ ] T6. `page-changelogs/replacement-arrangement-changelog.md` — dated
  entry: the arrival-grid law (the reversal + the boundary sentences:
  session pins = data only; destination dates = landings (the user's
  A); combined pinned+intent URLs → the resolve jump wins the landing;
  the degenerate end-of-semester note; the bridge's storage-rent
  story).
