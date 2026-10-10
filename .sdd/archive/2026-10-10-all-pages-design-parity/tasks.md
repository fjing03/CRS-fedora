# Tasks — all-pages-design-parity

Ordered, each ≤2h. Reference: frozen templates at `upstream/fjing` `f8b35a2` — copy page-glue verbatim, divergences per design §4. **No push without explicit user authorization.** "Playwright green" = gate set only (`venue-db`, `timetable-wiring`, `nav-identity` + new pins); legacy specs stay parked.

## Phase A — Cohort page (S1, design §1)

- [ ] T1. Legend: replace the 5-item colour-swatch include with the frozen 3-item include (`ownershipHint => true`, block-class swatches, frozen tips) — verbatim.
- [ ] T2. Cards: swap to frozen 5 (`sumTotal, sumHours, sumMyClasses, sumMyHours, sumConflict`) with keyword-span descriptions verbatim; align the summary JS to the frozen template's pattern (shared `computeSummary` already fills the frozen ids — verify; zero-fill id lists swap to frozen ids; add cancelled filter).
- [ ] T3. Tooltip: delete `tooltipExtra` from the `buildTimetableGrid` call (2-segment `lecturer · status`).
- [ ] T4. Ownership: replace `e.isMine === true` tests (statusClassFn + `replacementNoteFn.checkOwnership`) with `e.lecturer === MockData.currentUser.name` (frozen idiom; blade already sets currentUser at L101 — no payload change).
- [ ] T5. PH exception: `statusClassFn` owner-gates holidays/conflicts per the frozen template verbatim (own → `event-conflict`, others → `event-public-holiday`).
- [ ] T6. Glue: `showPrint => true` on week-nav; guide text → frozen wording; adopt frozen week persistence key (`cohortTimetableWeek`); record divergences (no state persistence, dynamic disabled) as code comments.

## Phase B — My timetable (S2, design §2)

- [ ] T7. Card descriptions → keyword spans + frozen wording (ids/labels unchanged).
- [ ] T8. Modal: `isConflict = day.holiday || event.status === 'conflict'` (frozen form).
- [ ] T9. Tooltip fallback: `e.cohort || ''` (drop `|| e.venue || '—'`); `showPrint => true`; guide text → frozen; cancelled filter.

## Phase C — Student page (S3, design §3)

- [ ] T10. Set `MockData.currentUser` from the authenticated student (my-timetable payload pattern).
- [ ] T11. Cards → frozen 5 + keyword spans; delete `tooltipExtra`; `showPrint => true`; guide text → frozen; cancelled filter.

## Phase D — Tests + changelogs (S5, design §6)

- [ ] T12. Playwright pins (respect ≤5 logins/min/ID; derive expectations from data, never "always 0"):
  - cohort (login 4288): 3-item legend + ownership hint; own block renders `event-mine`; `data-tip2` matches exactly one `·`.
  - my-timetable (login 5652): B005 own conflicted modal shows Replace Now.
  - student (login 25DFT0001): `MockData.currentUser.name` equals the student's real name (value pin — mock-data default persona must be overridden).
  - Home: a new `pages-parity.spec.ts` (safer than extending `timetable-wiring` — 4288 is already at 3 logins there; the gate-set ceiling is 5/min/ID).
- [ ] T13. Feature tests: confirm all stay green unchanged — no Feature test asserts legend/card DOM (verified), so no Feature edits are expected; any red gate here means a payload regression, not a test to update.
- [ ] T14. Changelogs: `cohort-timetable-ui-changelog.md`, `my-timetable`/`student-my-timetable` changelog files — one entry each (parity rewrite + bug fixes + divergences).

## Phase E — Verify (S6, one pass)

- [ ] T15. Stale-cache restart (two commands per AGENTS.md) + per-page 200 checks during apply.
- [ ] T16. Gates: `composer run lint:check`; `vendor/bin/phpstan analyse --memory-limit=1G`; `php artisan test` full; Playwright gate set; records-intact vs `/tmp/opencode/records-pre-merge2.txt`; `route:list` sanity.
- [ ] T17. Fix any red → re-run affected gates. Verify review vs frozen artifacts → archive to `.sdd/archive/` + `docs(sdd)` commit. **No push.**
