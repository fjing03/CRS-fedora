# Proposal — sync-upstream-fjing-ui

**Batch 1 of 4** · Created 2026-10-06 · Baseline: `explore-brief.md`

## Why

Our fork (`fedora-backend`, tip `9d87a79` at time of writing; the concurrent session moved it
past the `438bdfe` checkpoint during review) diverged from upstream at `f44cc5c` and has since
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

1. **Merge `upstream/fjing` into `fedora-backend`**, resolving **6 real conflicts** (ground truth
   from `git merge-tree`, not the 12-path changed-file intersection originally stated — see
   `explore-brief.md` §5 correction) under the per-file rules, and **verifying the 7 paths that
   auto-merge silently** (enumerated in `explore-brief.md` §5 — one list, one number, no local
   copy to drift). Adopt upstream's `ReplacementHistory` naming everywhere.
2. **Apply exactly 2 post-merge edits** that realign our code to decision 4.
3. **Extract exactly 3 artifacts** from `origin/fedora-frontend`. No branch merge.
4. **Run the 5 verification gates**, ending with `GET /replacement-history-ui` → 200.

## In scope

- **Preparation (already complete):** `git fetch upstream` → `f44cc5c..36c4d2c`; checkpoint push
  of 7 commits to `origin/fedora-backend`.
  ⚠ **`backup/pre-fjing-merge` → `438bdfe` is now stale.** The concurrent session has since added
  4 commits (HEAD `9d87a79`: `633eeb3` fix(auth), `3ec2a73` migrations, `45f725e` dataset+sdd,
  `9d87a79` FR 4.3 report). Resetting to the tag would **destroy those 4**. The rollback path
  must be a **fresh tag taken at merge time**, not this one.
- **Pre-merge gate:** intersect the incoming path set with the working tree (see Risks)
- `git merge --no-ff upstream/fjing` and manual resolution of the **6** real conflicts
  (`CodingMAIN.md`, `routes/web.php`, 3 × `page-changelogs/*` content, 1 modify/delete), plus
  **verification of the 7** that auto-merge — see the single enumerated list in
  `explore-brief.md` §5
- 2 post-merge edits (`StudentMyTimetable.php:77`, `RouteGateMatrixTest.php:20,33,41`)
- Extraction of `BACKEND-TASKS.md`, `ReplacementRequestsSeeder.php` → **`database/seeders/`**,
  `tests/e2e/` — upstream touches **0** seeders and **0** `tests/` paths, so all three land clean
- Gate runs and the route smoke test

## Out of scope

- Wave 2 seed-data repair — separate change `repair-timetable-seed-data`
- Wave 3 backend wiring — vehicle `wire-backend-into-refactored-ui`, already frozen
- Pushing to `upstream` — forbidden
- Any change to `database/migrations/*` or `dataset/*` — those paths belong to the concurrent
  session (they committed them at 18:49–18:53, so they are tracked, not merely "owned");
  upstream's incoming diff contains 0 such paths (verified). `database/seeders/` is **not**
  excluded: it is the extraction destination named above.
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
| 2 | All **6 real conflicts** resolved per the per-file rules: `routes/web.php` keeps our `$uiPages` array; for `CodingMAIN.md`, the frozen **`design.md` §4.2 14-hunk table governs** — mostly *theirs*, with exactly two evidence-based overrides: **H4 (RBAC matrix) keeps our cohort-scoped row** (`Ours: cohort-scoped, FR 1.3–1.4` — upstream's `View global replacement history ledger \| ✅ \| ✅ \| ✅` violates FR 1.3/1.4/2.15, verified against `../final/FR&NFR.md`) and **H9 takes theirs wholesale (§7.2, all 13 rows)** — FR 4.7's supersession gloss is **deliberately dropped** (absent from the FR source) and FR 4.11 is 3-state, matching `../final/FR&NFR.md:61` and the DB CHECK at `2026_08_03_000006:31`. Staff-ID rows unchanged (ours' FR 2.1 == FR source verbatim). The 3 page-changelog content conflicts append **both** sides with none dropped, and `upcoming-replacements-ui-changelog.md` stays deleted. Additionally **verify the 7 auto-merged paths** honored intent — `public/js/ui-common.js`'s shared-helper globals survive, and none of them is hand-rewritten. Note `page-changelogs/todo list/todo-list.md` is **both-modified** (our `633eeb3` + upstream) yet merges silently, so check it explicitly. Ⓑ2 **surgical unfreeze 2026-10-06** — the original clause "CodingMAIN.md keeps our FR 4.7" was corrected to this row after Batch 2 Round 1/2 evidence ([B2-10], mirrors the 12→6 precedent) |
| 3 | 8 `page-changelogs/*` entries from **both** sides present, none dropped |
| 4 | `upcoming-replacements-ui-changelog.md` deleted, not resurrected |
| 5 | `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` (0 errors) + `php vendor/phpunit/phpunit/phpunit --no-coverage` (105/105), all green. Note: plain `composer run types:check` (mandated by `AGENTS.md:35`) exhausts PHP's default 128M and crashes — use the `--memory-limit=1G` form so a memory error is never misread as a merge regression |
| 6 | The 3 extracted artifacts present at their destinations; **our fork's** `playwright.config.ts` is retained and the `fedora-frontend` copy is discarded |
| 7 | Pre-merge gate passes: intersecting `git status --porcelain` against the 114-path incoming set yields **zero** collisions |
| 8 | Post-merge `git status --porcelain` shows **no regressions**: the only entries are `.commandcode/` (untracked, intentionally local) plus this change's own files. *(Originally specified as "8 foreign untracked paths" — superseded: the concurrent session committed those 3 migrations + 4 `dataset/*` files at 18:49–18:53, so they are tracked now. Verified pre-merge: 4 local paths, 0 collisions.)* |
| 9 | SDD gate 5 completed: `/sdd-verify` passes and `/sdd-archive` moves the change to `.sdd/archive/`. *(Closes advisory A1 — this is the 5th of the "5 verification gates" in `explore-brief.md` §9, previously referenced by* What *item 4 but absent from this table.)* |

## Risks

| Risk | Mitigation |
|---|---|
| Merge rewrites the working tree while the other session writes into it | **Pre-merge gate (mandatory, run immediately before merging):** write the incoming 114-path set to a file and intersect it with `git status --porcelain`. **Abort and report** if the intersection is non-empty — do not `stash`, do not `rm`, do not force. ⚠ **The "they only own `database/`+`dataset/`" premise is no longer true** — the concurrent session has since committed *code* (`app/Providers/FortifyServiceProvider.php` in `633eeb3`). The gate, not that premise, is now the actual safety mechanism, which is why it must be re-run at execution time. Latest run 2026-10-06 19:0x: **0 collisions across 4 local paths vs 114 incoming** |
| `routes/web.php` resolution breaks unrelated routes | `class_exists()` at line 88 falls back to the legacy view; line 88's fallback means a missing component degrades to a view, never a 500 |
| Adopting `ReplacementHistory` orphans a nav label | Covered by the 2 post-merge edits; `ui-nav-bar.blade.php` is untouched by our fork and taken wholesale from upstream |
| Test suite diverges after taking upstream's UI | Baseline recorded at 105/105 before the merge; any regression is attributable to a specific resolution |
| Running the cache-clear/restart step kills the FPM pool mid-gate | **Use `pkill -f "artisan serve"` — explicitly confirmed by the user 2026-10-06 (closes explore-brief §10).** **Deviates from `AGENTS.md:37`**, which mandates `pkill -9 php`; that pattern regex-matches `php-fpm`, and 6 FPM processes are live on this machine (PIDs 960, 974–978), so it would SIGKILL the pool. `AGENTS.md:37` **and `CodingMAIN.md:108`** must both be amended in a **follow-up change**, not this one — the identical `pkill -9 php` appears in the AGENTS standing rules *and* in the project's single-source-of-truth document, so fixing only one leaves the dangerous line in place. For this change the proposal takes precedence over `AGENTS.md`. Full restart sequence: `pkill -f "artisan serve" && rm -f storage/framework/views/*.php && php artisan serve --port=8000 &` |
