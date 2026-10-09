# Tasks — venue-event-blocks-db

Ordered; each ≤2h. S1 merge first (the rewrite depends on merged theme.css/ui-common.js). Conventional commits. **No push without explicit user authorization.**

## Phase A — Merge (proposal S1, design §7)

- [ ] T1. Pre-flight: confirm clean tree (only `.sdd/changes/venue-event-blocks-db/` untracked), capture records baseline (`/tmp/opencode/records-pre-merge2.txt` snapshot re-verified against live DB).
- [ ] T2. `git merge --no-commit upstream/fjing`; inspect: confirm the single conflict is `page-changelogs/replacement-arrangement-changelog.md` and no others; spot-check the 5 auto-merged changelogs for doubled headings. Abort + reassess if reality differs from the frozen forecast.
- [ ] T3. Resolve the conflict: keep both sides' entries in chronological order (ours 2026-10-09 top entries + upstream's appended batch entries). Commit the merge: `Merge remote-tracking branch 'upstream/fjing' into fedora-backend`.
- [ ] T4. Records-intact check vs baseline (must be identical: class_sessions 101, time_slots 38640, …, replacement_requests 0). Confirm no migration files arrived (`git diff HEAD~1 --stat -- database/migrations/` empty).

## Phase B — Venue rewrite (proposal S2, design §1–§5, spec R1–R8)

- [ ] T5. `resources/views/livewire/venue-timetable.blade.php`:
  - Replace the 7-item legend include with the 4-item legend + `ownershipHint => true` (design §2, labels/tips per upstream template, Available tip = honest read-only copy).
  - Rewrite the guide-block "Slot colours" bullet to the two-axis language.
  - Keep `booking-hint-line`; no booking affordance added.
  - Replace `statusClassFn`-based `buildTimetableGrid` call with the `cellRender`-mode call: page-level cellRender adapted from the upstream venue template (empty Sunday/holiday → `cell-sun`/`cell-ph`; own event on holiday → `event-conflict` block; others' offday events hidden; normal cells → blocks with `event-mine`/`event-others`/pending/conflict pairs per design §1.1; `dataset.tip2 = lecturer · status` verbatim; `dataset.name` kept; span/occupied continuation handling preserved; `cell-empty` for free slots).
- [ ] T6. `app/Livewire/VenueTimetable.php`: fix `$totalsByWeek` — `sumAvailable` excludes Sunday + public-holiday slots (design §3); leave twin-merge, payload shape, auth, `?venue=` untouched.
- [ ] T7. Summary-bar descriptions: adopt `info-keyword`/`warn-keyword` spans; card 5 stays `sumOccupied`/"Occupied".
- [ ] T8. Stale-cache restart per AGENTS.md (isolated artisan-serve kill + clear compiled views) and eyeball the page: 4-item legend, blocks, PH cells, cards.

## Phase C — Tests (proposal S3, design §6, spec R1–R8)

- [ ] T9. `tests/Feature/VenueTimetableTest.php`: update payload assertions to new totals semantics; add the crafted-row PH test (session for test lecturer on an imported holiday date → eventsByWeek contains it, holidaysJs exposes it, sumAvailable excludes the slot, sumTotal/sumMyClasses/sumMyHours include it per design §3); keep twin-merge test untouched. `migrate:fresh --seed` allowed on the TESTING db only.
- [ ] T10. `tests/venue-db.spec.ts` re-pin (≤5 logins/min, 4 tests, one login each): 4-item legend + ownership hint; `event-mine`/`event-others` block assertions (`.vt-cell-*` pins deleted); **the existing B005 diploma-cohort conflict test (L102–116) SURVIVES — conflict is reachable via derived venue-restriction violation (owner → `event-conflict`); extend to pin the others'-side `event-public-holiday` where feasible**; tooltip `data-name` + `data-tip2` shape; PH/`cell-ph` class-presence + no-green-tint (conditional on week data); summary card values incl. new `sumAvailable`; dropdown 3-category cascade — **delete the standalone `data-type="CiscoLab"` group assertion (L79–81)**, add B006-under-Lab cascade + room `data-tip`.
- [ ] T11. `page-changelogs/venue-timetable-ui-changelog.md`: 2026-10-10 entry per design §8 (rewrite, two-axis, legend, PH exception, Available fix, Occupied + My-Teaching-PH deviations, booking still Slice B).

## Phase D — Verify (proposal S4, one pass)

- [ ] T12. Gates: `composer run lint:check`; `composer run types:check`; `php artisan test` (full suite); Playwright `venue-db`, `timetable-wiring`, `nav-identity` (mock specs `venue-timetable.spec.ts` / `confirm-guards.spec.ts` / `warning-keywords.spec.ts` stay excluded).
- [ ] T13. Records-intact re-check vs baseline; `php artisan route:list` sanity (no `/api/v1` resurrection).
- [ ] T14. Fix any red gate → re-run affected gates. Verify review (implementation vs frozen artifacts), then archive: `.sdd/archive/` + `docs(sdd)` commit. No push.
