# Tasks — `request-completed-status`

Each task is small and independently checkable. `specs/request-status-display/spec.md` is the
acceptance oracle; `design.md` names the exact anchors. Nothing here writes to a demo database.

## T1 — The helper (R1)

- [x] `app/Services/ReplacementRequestMapper.php`: add `effectiveStatus(ReplacementRequest $r, Carbon $semesterStart): string`
      exactly as `design.md` §1 (prompt §6 body): non-`approved` short-circuit → span date `null`
      short-circuit → `Carbon::parse($date)->startOfDay()->lt(today()->startOfDay())`.
- [x] Docblock names C1/C2 and the "never guesses Completed" rule.
- Receipt: `php -l`, PHPStan clean, and T5's feature tests.

## T2 — The PL queue + the API ride it (R2)

- [x] `ReplacementRequestMapper::toRow()`: `$prettyStatus = ucfirst($this->effectiveStatus($r, $semesterStart));`
- [x] Confirm nothing else in `toRow()` reads `$r->status` for display (only the pending branch
      and `slotValidity` semantics, which must stay keyed on the STORED status — a past approved
      row is still a decided row).
- Receipt: `ApprovalWorkflowTest` new case; `RequestApproval` unchanged (grep).

## T3 — My Requests row (R2)

- [x] `app/Livewire/MyRequestHistory.php`: `'status' => ucfirst($mapper->effectiveStatus($r, $semesterStart)),`
      (reuse the `$mapper` local already in the method — the current line calls `app(...)` three
      times; the third call is replaced, the existing local is reused).
- Receipt: `ReplacementHistoriesTest` 3 new cases.

## T4 — Replacement Home payload (R2)

- [x] `app/Livewire/ReplacementHome.php`: `$latestRequests` gains `->with(['replacementTimeSlot.venue'])`
      and `requestStatus` becomes `$mapper->effectiveStatus($latest, $startDate)` (raw lowercase).
- [x] Docblock updated: the badges lane's "no `completed` state exists in this lane" note is
      superseded by this lane (C1/C2).
- Receipt: `ReplacementHomeTest` 2 cases (past → `completed`, future → `approved`) + the badges
      lane's holiday test expectation updated to `completed`.

## T5 — My Requests: timeline retime (R6)

- [x] `my-request-history.blade.php` `openModalById()`: add `completedTime` and use
      `completedTime || reviewTime || '—'` for the final step only.
- [x] Uses `formatDate()` (string split) — no `new Date(...)`.
- Receipt: code receipt + the modal still opens on the demo DB (existing spec).

## T6 — My Requests: the reveal exception (R5)

- [x] `jumpToRequestRow()`: the not-found branch gains the sole-cause check, the visible untick,
      `saveFilters()`, `renderTable()`, the recomputed index, and keeps the toast for every other
      cause.
- Receipt: new browser spec S5.1; toast branch by code receipt + the badges-lane spec.

## T7 — Replacement Home: the toggle + the filter + the counts (R3)

- [x] Toggle markup in `.toolbar-left` (verbatim from My Requests, `id="completedToggle"`,
      `id="hideCompleted"`, checked, no storage).
- [x] `completedHidden(c)` + `visibleBaseRows()` helpers.
- [x] `buildTable()`: the filter clause; `updateResultCount` total; `updateSummary()` total.
- [x] `change` listener on `#hideCompleted`.
- Receipt: new browser spec S3.1–S3.4.

## T8 — Feature tests (R1 + R2)

- [x] `tests/Feature/ReplacementHistoriesTest.php`: yesterday → `Completed`; today → `Approved`;
      no slot → `Approved`.
- [x] `tests/Feature/ApprovalWorkflowTest.php`: past approved via `toRow()` → `Completed`, bucket
      still `queue`.
- [x] `tests/Feature/ReplacementHomeTest.php`: past → `completed`; future → `approved`; flip the
      holiday test's expectation.
- Receipt: the three filtered runs, then the full suite.

## T9 — Browser spec (R3 + R4 + R5)

- [x] `tests/request-completed-status.spec.ts`: self-guarded to the disposable testing DB
      (`CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`), id-keyed fixture insert
      (one approved request, replacement 2 days ago, on a conflicted occurrence for staff 5425) and
      an id-keyed `afterAll` sweep.
- [x] Receipts: toggle default + absent row; untick → badge + href; click → My Requests, toggle
      un-ticked, row visible and reading Completed.
- [x] Read-only sanity run on `:8000` (toggle present/checked, row count unchanged, no completed
      row) — stated, not fabricated.

## T10 — Gates

- [x] `composer run lint:check`
- [x] `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` (**never** `types:check`)
- [x] full `php artisan test` on `DB_DATABASE=class_replacement_testing` (baseline 315; quote real)
- [x] `npx playwright test tests/status-tooltips-filter-chips.spec.ts tests/request-approval.spec.ts`
      on `:8000` (must stay green)
- [x] `node --check public/js/ui-common.js` (unchanged file — cheap sanity)

## T11 — Changelogs + one commit

- [x] `page-changelogs/replacement-home-changelog.md` — the toggle, its default, the filter, the
      counts, the badge link.
- [x] `page-changelogs/my-request-history-changelog.md` — the timeline retime + the reveal
      exception.
- [x] `page-changelogs/request-approval-changelog.md` — the derived status + the shifted counts
      (no code change).
- [x] One commit, staging **only** this lane's files. **Never push. Never archive.**

## T12 — Review round 2

- [x] Verify each requirement against the code (not the intent); append to `review-log.md`;
      leave `sdd.yaml` at `applied` or `verify-failed`.

## Honesty notes to carry into the changelog

1. The viva twin's request 27 reads **Approved today (3 Nov)** and **Completed from 4 Nov** — C2
   is a strict `before today`. So the live viva receipt today is the boundary half; the past-date
   half is receipted on the disposable testing DB.
2. The reveal exception's *other-cause* branches (status/search/week) are code receipts plus the
   existing badges-lane toast receipt — the demo DB cannot produce a Completed row.
3. The API has no HTTP-level test; it rides `toRow()`, which is asserted directly.
4. `effectiveStatus()` adds 2 queries per approved row (junction + venue) — measured, accepted.
