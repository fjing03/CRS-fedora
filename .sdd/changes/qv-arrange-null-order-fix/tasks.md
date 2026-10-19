# Tasks — qv-arrange-null-order-fix

- [ ] T1. `resources/views/livewire/replacement-home.blade.php` — `qvArrange()`
  rewrites to the frozen template's capture-then-hide shape (design D1's
  2-line fix: capture `sessionId`/`week` into locals BEFORE
  `hideQuickView()`, then navigate with the leads).
- [ ] T2. NEW `tests/replacement-home-qv.spec.ts` — the Playwright repro as a
  regression row (row click → quick view → `#qvArrangeBtn` → the URL carries
  `/replacement-arrangement?session=<id>&week=<n>&from=replacement-home`).
- [ ] T3. `page-changelogs/replacement-home-changelog.md` — dated fix entry.
- [ ] T4. Stale-cache ritual + run the new spec + spot gates (pint/lint-check
  where applicable — no PHP delta).
