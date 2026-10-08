# Proposal — merge-upstream-fjing-4dc4d06

**Status:** draft (Batch 1) · **Date:** 2026-10-08 · **Predecessor:** `2026-10-07-sync-upstream-fjing-ui`
**Baseline:** explore-brief.md (this change) — all ground truth verified first-hand there.

## Why

The other (UI) workstation pushed 5 UI commits to `upstream/fjing` (`36c4d2c..4dc4d06`, tip `4dc4d06`) while Wave 2 (`repair-timetable-seed-data`) was frozen. The user confirmed the venue timetable page redesign is live on that machine and chose to merge the delta **now**, before Wave 3 (`wire-backend-into-refactored-ui`) wires the backend into the UI — so Wave 3 targets the newest UI, not a stale one.

Carrying the delta into Wave 3 instead would (a) mix a mechanical merge into a behavioural SDD cycle, (b) risk the Wave-3 design referencing pre-redesign templates, (c) let the divergence window grow.

## What

1. **Merge `upstream/fjing` (`4dc4d06`) into `fedora-backend`** as a regular merge commit (no ff, no squash, no cherry-picks — explore-brief §5). Supersedes the predecessor's "zero collision" claim (which was scoped to Wave-2's file set) with the brief §2 census: 7 shared paths, all non-overlapping hunks.
2. **Execution-time pre-merge gate (mandatory, in this order, immediately before merging):**
   a. `git rev-parse upstream/fjing` must equal `4dc4d06` — if the tip moved (the other workstation pushes actively), **stop and re-explore**;
   b. re-run `git merge-tree --write-tree HEAD 4dc4d06` — non-zero exit or any conflicted-file list → **proposal invalid, stop** (frozen-ledger rule: the merge runs with an empty resolution ledger only if the preflight still proves one);
   c. `git status --porcelain` intersected with the 23-path delta census must be **empty** — else abort and report; never `stash`, never `rm`.
   Merge by SHA: `git merge --no-ff 4dc4d06`. Rollback: `git merge --abort` pre-commit; post-commit assert `HEAD^1 = 52e25ca` then `git reset --hard 52e25ca` (only after a tree-clean check).
3. **Post-merge semantic check of all 7 path-overlap files** (one JS + 6 changelogs): merged `ui-common.js` must keep our Wave-1 `jumpToToday` guards beside their 7 hunks; each merged `page-changelogs/*` must keep a well-formed section structure. Also grep merged `mock-data.js`: real subject names present (their `f5d12ed` fix) and `currentUser` still `5770`/LJZ (unchanged = Wave 3's job). Cross-check the merged rendering against upstream's own acceptance notes in `.sdd/archive/2026-10-07-venue-event-blocks/` (arrives with the merge; read-only).
4. **Re-run the house gates** at the merge commit (unchanged set, unchanged expectations; smoke scope per criterion 5 — richer than the single-page default).
5. Update this repo's own records: append the merge entry to `page-changelogs/backend-automated-by-ai.md`.

## Expected post-merge tree state

Exactly upstream's 23 delta paths updated/added on top of our HEAD; no other path may differ from pre-merge HEAD. `dataset/`, `database/`, `tests/Feature/`, `routes/`, `app/` untouched.

## Out of scope

- Wave 3 work: auth identity wiring (static `ui-nav-bar` panel / `mock-data.js` `currentUser`), the `UpcomingReplacements`→`ReplacementHistory` 3-batch unfreeze, any backend wiring.
- The doc-repair follow-up (`pkill -9 php` lines, CodingMAIN `:146` enum) and the `[W-2]` test diagnostic one-liner.
- Pushing: **nothing leaves the machine** without explicit user authorization (upstream is pull-only, permanently).

## Success criteria

| # | Criterion | Evidence |
|---|---|---|
| 1 | Merge commit exists with two parents (our HEAD, `4dc4d06`); `git merge-tree` preflight = 0 conflicts; no manual conflict resolution | `git log --format=%P -1`, preflight output in review-log |
| 2 | Post-merge diff vs pre-merge HEAD touches **only** the 23 delta paths | `git diff --name-only HEAD^1..HEAD` ⊆ delta census (explore-brief §1) |
| 3 | Wave-1's `jumpToToday` guards survive in merged `ui-common.js`; merged `mock-data.js` carries their real-subject-names fix; `currentUser` hardcode state unchanged (still 5770 — Wave 3's job) | grep + semantic check note |
| 4 | Gates at merge commit: phpunit **110/110** (incl. 5 seed invariants, 35/44/1988), phpstan **0** (`--memory-limit=1G --no-progress` — plain `types:check` is the known 128M-crash form, never a merge regression), pint adminer-only baseline | gate outputs in tasks/verify notes |
| 5 | Live smoke (post safe serve-restart): **all 3 touched blades** render with their delta features — `venue-timetable-UI` (cohort-style event blocks), `replacement-home-UI` (replacement origin trail + conflict colouring), `CohortTimetable-UI` (holiday badges / tabbed modal grouping per their archived design notes); real subject names visible instead of placeholders; no page blank/broken | curl HTTP 200s + recorded per-page check |
| 6 | Changelog entry appended; change archived | file diff + `.sdd/archive/…` |

## Risks

| Risk | Mitigation |
|---|---|
| Upstream tip moves past `4dc4d06` before execution (active pushing workstation) | Gate 2a SHA pin — stop and re-explore if moved |
| Merge-tree clean ≠ semantically clean | Gate 2b re-run + criterion 3's explicit per-file eyeball of the 7 shared paths |
| Smoke serve-restart hazard: AGENTS.md's literal `pkill -9 php` regex-kills the live FPM pool | Carry the user-confirmed safe sequence from the predecessor: `pkill -f "[a]rtisan serve"`, `rm -f storage/framework/views/*.php`, `php artisan serve --port=8000` — never `pkill -9 php` (doc-repair follow-up stays out of scope) |
| Their `.spec.ts` expectations diverge from our merged backend data | Not a gate (Playwright not in house gates); noted as Wave-3 input |
| New `event-conflict` CSS class vs our conflict colouring expectations | Backend data unaffected; visual class added only; smoke checks rendering |
| Bad merge needs undoing | Gate 2's rollback protocol (abort pre-commit; reset to `52e25ca` post-commit after tree-clean check) |
