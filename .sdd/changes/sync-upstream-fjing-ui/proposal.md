# Proposal — sync-upstream-fjing-ui

**Batch 1 of 4** · Created 2026-10-06 · Baseline: `explore-brief.md`

## Why

Our fork (`fedora-backend`, tip `438bdfe`) diverged from upstream at `f44cc5c` and has since
carried 26 commits of backend work — RBAC gates, real-data timetables, the honorific column
split, the OCC validator, the matrix intersection engine. Upstream's `fjing` branch has moved
71 commits in the same span, carrying UI work we do not have: the Replacement History rename,
sortable-table coverage, role-home logo, mock-data seeded from the 202505 timetable PDFs.

The two histories are drifting apart daily. Every day the fork sits unmerged, the conflict set
grows and the backend's route/UI contract stays pinned to an upstream that has already renamed
one of its core capabilities.

Separately, three backend deliverables exist only in our `fedora-frontend` branch and were
never brought across: the backlog document, the replacement-requests seeder, and the end-to-end
test suite. They are prerequisites for Wave 2 and Wave 3.

## What

1. **Merge `upstream/fjing` into `fedora-backend`**, resolving exactly 12 conflicting files
   under the per-file rules in the explore brief. Adopt upstream's `ReplacementHistory` naming
   everywhere.
2. **Apply exactly 2 post-merge edits** that realign our code to decision 4.
3. **Extract exactly 3 artifacts** from `origin/fedora-frontend`. No branch merge.
4. **Run the 5 verification gates**, ending with `GET /replacement-history-ui` → 200.

## In scope

- **Preparation (already complete):** `git fetch upstream` → `f44cc5c..36c4d2c`; rollback tag
  `backup/pre-fjing-merge` → `438bdfe`; checkpoint push of 7 commits to `origin/fedora-backend`
- **Pre-merge gate:** intersect the incoming path set with the working tree (see Risks)
- `git merge --no-ff upstream/fjing` and manual resolution of the 12-file conflict set
- 2 post-merge edits (`StudentMyTimetable.php:77`, `RouteGateMatrixTest.php:20,33,41`)
- Extraction of `BACKEND-TASKS.md`, `ReplacementRequestsSeeder.php` → **`database/seeders/`**,
  `tests/e2e/` — upstream touches **0** seeders and **0** `tests/` paths, so all three land clean
- Gate runs and the route smoke test

## Out of scope

- Wave 2 seed-data repair — separate change `repair-timetable-seed-data`
- Wave 3 backend wiring — vehicle `wire-backend-into-refactored-ui`, already frozen
- Pushing to `upstream` — forbidden
- Any change to `database/migrations/*` or `dataset/*` — another session owns those; upstream's
  incoming diff contains 0 such paths (verified). `database/seeders/` is **not** excluded: it is
  the extraction destination named above.
- Any change to `prompts/` — upstream modifies `prompts/run-auth-wiring.md`; take theirs
- Redrawing the ERD (item 5 of the other session's action plan)

## Deferred (open question carried from `explore-brief.md` §10, not resolved here)

- **`auth-wiring` disposition.** The brief framed this as "the one stale UI change upstream did
  *not* archive", but the fork has since **added 58 lines** to
  `.sdd/changes/auth-wiring/tasks.md` since the merge base while upstream touched only an
  unrelated `prompts/run-auth-wiring.md`. It is therefore active local work, not a discardable
  leftover. Whether to archive it alongside the other 6 or keep it active is **deferred to Wave 3
  input**, alongside the `UpcomingReplacements` → `ReplacementHistory` unfreeze debt. This change
  makes no decision on it and modifies no `auth-wiring` file.

## Success criteria

| # | Criterion |
|---|---|
| 1 | `routes/web.php` serves `/replacement-history-ui` and returns **200** |
| 2 | All 3 resolutions in the hard-conflict table honored — `$uiPages` array survives, our `CodingMAIN.md` FR 4.7 + Staff-ID rows survive |
| 3 | 8 `page-changelogs/*` entries from **both** sides present, none dropped |
| 4 | `upcoming-replacements-ui-changelog.md` deleted, not resurrected |
| 5 | Lint, PHPStan (0 errors), PHPUnit (105/105) all green |
| 6 | 3 artifacts present at their destinations; HEAD's `playwright.config.ts` unchanged |
| 7 | Pre-merge gate passes: intersecting `git status --porcelain` against the 114-path incoming set yields **zero** collisions |
| 8 | Post-merge `git status --porcelain` lists exactly the 8 known foreign untracked paths — `.commandcode/`, 3 × `database/migrations/2026_10_06_*`, 4 × `dataset/*` — plus this change's own `.sdd/changes/sync-upstream-fjing-ui/`, and nothing else |

## Risks

| Risk | Mitigation |
|---|---|
| Merge rewrites the working tree while the other session writes into it | **Pre-merge gate (mandatory, run immediately before merging):** write the incoming 114-path set to a file and intersect it with `git status --porcelain`. **Abort and report** if the intersection is non-empty — do not `stash`, do not `rm`, do not force. The gate re-validates a point-in-time claim (`0` `database/` + `0` `dataset/` paths, `0` tests/seeders touched) at execution time. Dry run on 2026-10-06 18:5x: **0 collisions across 10 local paths** |
| `routes/web.php` resolution breaks unrelated routes | `class_exists()` at line 88 falls back to the legacy view; line 88's fallback means a missing component degrades to a view, never a 500 |
| Adopting `ReplacementHistory` orphans a nav label | Covered by the 2 post-merge edits; `ui-nav-bar.blade.php` is untouched by our fork and taken wholesale from upstream |
| Test suite diverges after taking upstream's UI | Baseline recorded at 105/105 before the merge; any regression is attributable to a specific resolution |
| A `php-fpm` restart kills the FPM pool mid-gate | Use `pkill -f "artisan serve"`, never `pkill -9 php` |
