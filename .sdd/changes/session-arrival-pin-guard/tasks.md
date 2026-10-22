# Tasks — session-arrival-pin-guard

- [x] T1. `resources/views/livewire/replacement-arrangement.blade.php` —
  the T18 auto-pin block (:2961–2975): add the `window.__bootDone`
  conjunct to the `if (firstConflictRow …)` gate at :2965 (**no local
  `urlParams` reroute**); DELETE the entire :2967–2973 shadow block
  (`urlParams = urlParams || {}; window.urlParams = Object.assign({},
  …)` — lines gone); keep the `pinnedOccurrence` + `currentSessionId`
  writes exactly as shipped; refresh the comment (design D1's exact
  shape) and refresh the ±1–3 drifted line citations (:2987 trigger,
  :2374 anchor, :3408–3411 boot) in the touch.
- [x] T2. NEW `tests/session-arrival-pin-guard.spec.ts` — the three rows
  (direct 49 arrival, direct 39 arrival, Replace-Now click-through with
  the `tr[data-day-index="4"]`-scoped locator + `#btnReplaceNow` pre-
  assert; design D6's exact asserts incl. the trigger NOT "Auto-pinned").
- [x] T3. Stale-cache ritual (kill serve → rm compiled views → serve,
  two separate commands; re-auth via `tests/auth.setup.ts --project=setup`
  if storage states expired).
- [x] T4. Run `tests/session-arrival-pin-guard.spec.ts` +
  `tests/default-pin.spec.ts` (3 rows) + `tests/replace-now.spec.ts` +
  `tests/venue-intent-modal.spec.ts` (and
  `tests/venue-cross-week-handoff.spec.ts` if already landed) with
  `--grep-invert "TC70"` — default-pin's 3 rows and the venue-intent
  A6/C2 rows must stay green unchanged (the byte-parity contract);
  re-run the /tmp pin-probe shape on both user URLs for the manual
  receipts.
- [x] T5. `page-changelogs/replacement-arrangement-changelog.md` — dated
  fix entry (the arrival-pin hijack root, the gate + pure-deletion
  shape, the honest strip + pin receipts, the sequencing note with
  `venue-cross-week-handoff`, the documented auto-nav residual) + the
  fold-in note under the still-open `occurrence-week-pin-truthfulness`
  change's T18 amendment lineage (this fix amends its T18 rider).
- [x] T6. EXECUTED (user decision **B**, given explicitly 2026-10-20 in
  chat: "B"); the T18 auto-nav block (:3000–3010, the `selectWeek(firstConflictRow.week)`
  family) gains the same `window.__bootDone` conjunct — pre-boot arrivals
  keep the grid standing on the pinned week (W14 for the 49 receipt) and
  no silent `weekNav.save()`/`saveCurrentWeek()` runs from the auto-nav
  (the boot bridge's pinned-week jump at :3519 remains the last one, so
  `localStorage['arrangementWeek']` = the user's clicked week, not the
  earliest conflict's 7). POST-boot subject switches byte-identical
  (default-pin T18-S1 green). S1's T6 asserts: `#weekSelector` = '13' +
  `arrangementWeek` = '13' (the binary-window.currentWeek probe is
  unusable pre-boot — the gated block only writes it post-boot).
- [ ] T7. Apply sequencing guard: run only together with (or after)
  `venue-cross-week-handoff` — never this change alone before the venue
  threading (proposal's sequencing note).
- [x] APPLIED receipts (2026-10-20): the gate + pure-deletion edit landed
  (`php -l` clean; `window.urlParams` now appears ONLY inside the
  comment — the write is gone). T2: the 3-row spec
  (`tests/session-arrival-pin-guard.spec.ts`) 3/3 green. T4: the ritual
  + the regression set (default-pin 3/3 + replace-now +
  venue-intent-modal + venue-cross-week-handoff) = 15 green with ONLY
  the stash-proven pre-existing C2 toast-poll noise failing (same
  signature without both changes); the /tmp pin-probe manual receipts
  on both user URLs = the honest strips + pins recorded above. T7: the
  sequencing guard satisfied — `venue-cross-week-handoff` (0eec527)
  landed first.
- [ ] T6 stays OPEN (the auto-nav residual's one-token gate) — pending
  the user's explicit word per the proposal's NOT-in-scope contract.
