# Tasks — merge-upstream-fjing-4dc4d06

**Status:** draft (Batch 3) · **Frozen inputs:** explore-brief.md, proposal.md, design.md (§ refs normative)
**Nature:** mechanical merge — each task cites the design section that owns it. No task exceeds 2 h.

## Task 1 — Pre-merge gate (design §1, proposal What step 2)

- [x] **T1.1** G2a: `git rev-parse upstream/fjing` = `4dc4d06…` **and** `git rev-parse HEAD` = `52e25ca…`. Either fails → STOP, re-explore (record output in this file's execution notes).
- [x] **T1.2** G2b: `git merge-tree --write-tree HEAD 4dc4d06` → exit 0, no conflicted-file list. Else → proposal invalid, STOP.
- [x] **T1.3** G2c: `git status --porcelain` ∩ 23-path census = empty (expected status: untracked `.commandcode/` + this change's `.sdd/` dir only). Else → ABORT (never stash/rm).
- [x] **T1.4** Record all three gate outputs in the execution notes below.

## Task 2 — Merge (design §1 + §4)

- [x] **T2.1** `git merge --no-ff 4dc4d06 -m "<design §4 template, filled in>"`.
- [x] **T2.2** Post-merge structural check (criterion 1 + 2 owner): two-parent `%P` output; `git diff --name-only HEAD^1..HEAD` vs census — `comm -13` AND `comm -23` both empty (exact 23-path equality). Any output → §6 rollback (verify `HEAD^1 = 52e25ca` first), report, stop.
- [x] **T2.3** Record the structural-check outputs.

## Task 3 — Post-merge semantic checks (design §2, proposal criterion 3)

- [x] **T3.1** S1: Wave-1 `jumpToToday` guard sentinels present in merged `ui-common.js` (`grep -nE "window\.(currentWeek|buildTimetable|updateWeekSubtitle|updateSummary|updateProgress)"` — B2-16 bare-pipe form).
- [x] **T3.2** S2 + S2b: `event-conflict` class present; **added-line parity** `comm -23 <their-added> <head-added>` = empty (direction per frozen design; `sort` both sets).
- [x] **T3.3** S3: merged `mock-data.js` — `currentUser` still `staffId: '5770'` (unchanged); real subject names present.
- [x] **T3.4** S4: 6 merged `page-changelogs/*` — well-formed section structure, both sides' entries interleaved.
- [x] **T3.5** S5: read `.sdd/archive/2026-10-07-venue-event-blocks/design.md` (read-only) → extract the concrete smoke selectors **and record them in execution notes**; **fallback** if absent: derive from added class/token names in `git diff 36c4d2c..4dc4d06`.
- [x] **T3.6** S6: `git diff HEAD^1..HEAD -- resources/views/partials/ui-nav-bar.blade.php` = empty (Wave-3 scope untouched).
- [x] **T3.7** Record S1–S6 results (incl. the extracted selectors verbatim).

## Task 4 — House gates (design §5, proposal criterion 4)

- [x] **T4.1** phpunit: `php vendor/phpunit/phpunit/phpunit --no-coverage` → **110/110** (invariants 35/44/1988; assertion-count drift = investigate-don't-fail).
- [x] **T4.2** phpstan: `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → 0 errors (never plain `types:check`).
- [x] **T4.3** pint: `composer run lint:check` → adminer-only baseline.
- [x] **T4.4** Record gate outputs. *(Gate failure → design §6 rollback row.)*

## Task 5 — Live smoke (design §3, proposal criterion 5)

- [x] **T5.1** Safe serve-restart: `pkill -f "[a]rtisan serve" || true`; `rm -f storage/framework/views/*.php`; `php artisan serve --port=8000` — **never `pkill -9 php`** (live-FPM-pool hazard); verify `http 200` on `/login/student`. *(Smoke/gate failure → design §6 rollback row.)*
- [x] **T5.2** Login flows (student `25RSD0001` / staff `5425`, `Tarumt@2026`) → authenticated GETs of the 3 touched blades.
- [x] **T5.3** Per-theme visible assertions (selectors from T3.5): venue-timetable = cohort-style event blocks; replacement-home = origin trail + conflict colouring; cohort-timetable = holiday badges + tabbed modal grouping; real subject names somewhere visible.
- [x] **T5.4** Record HTTP codes + per-page assertion results.

## Task 6 — Records + archive (design §7, proposal criterion 6 + What step 5)

- [x] **T6.1** Append merge entry to `page-changelogs/backend-automated-by-ai.md` (delta summary, census note, gate + smoke results).
- [x] **T6.2** `/sdd-verify` pass (reviewer subagent, self-run per standing instruction) against the frozen proposal criteria 1–6 → verdict in review-log.md.
- [x] **T6.3** Commit: merge commit (T2.1, upstream content only) already exists; then `docs(sdd)` commit with this change's `.sdd/` dir + changelog append.
- [x] **T6.4** `/sdd-archive` → `.sdd/archive/2026-10-08-merge-upstream-fjing-4dc4d06/` + yaml `status: archived` / `archived: 2026-10-08` → archive commit.
- [x] **T6.5** Report to user. **No push** — local until the user explicitly authorizes `git push origin fedora-backend`. *✅ Reported: 8 commits ahead of origin, awaiting push authorization.*

## Execution notes

(appended during apply — gate outputs, selector extraction, smoke results)

### T1 — pre-merge gate (2026-10-08, all PASS)

- T1.1 G2a: `upstream/fjing` = `4dc4d06596caca44bee1ae6191478cc74af4753e` ✓; `HEAD` = `52e25caecb328a3020688ee91540157294bdc6d8` ✓
- T1.2 G2b: `git merge-tree --write-tree HEAD 4dc4d06` → exit 0, tree OID only, no conflicted-file list ✓
- T1.3 G2c: `comm -12 porcelain census` = empty ✓; status = `?? .commandcode/` + `?? .sdd/changes/merge-upstream-fjing-4dc4d06/` (exactly the §1-expected set)

### T2 — merge (2026-10-08)

- T2.1: merge commit created by 'ort' strategy, zero manual resolution; the 7 shared paths auto-merged (git reported auto-merging for all 6 changelogs + ui-common.js)
- T2.2: `%P` = `52e25ca… 4dc4d06…` (two parents, exact pins) ✓; census equality: `comm -13` empty, `comm -23` empty, postmerge = exactly 23 paths ✓
- T2.3: structural check PASS

### T3 — semantic checks S1–S6 (2026-10-08, all PASS)

- S1: 5 guard sentinels present (`window.currentWeek = currentWeek;` :286; 3× `typeof window.*` :287–292; :525) ✓
- S2: `div.classList.add('event-conflict')` at ui-common.js:759 ✓
- S2b: their-added = 99 lines, HEAD-added = 104 (99 + our 5 guard lines); `comm -23 their head` = empty ✓ — hunk-drop impossible
- S3: `currentUser: { name: 'En. Lim Jia Zheng', staffId: '5770' }` :80 **unchanged** (Wave 3's job); real names present (`courseCode: 'BMIT5678', courseName: 'Database Systems'` etc.); the single grep hit for "placeholder" is a benign capacity comment (`// capacities: Tutorial ≤35, LectureHall >35 (80 placeholder)…`)
- S4: all 6 merged changelogs well-formed (13/20/44/18/17/20 H2 sections, both sides' entries interleaved) ✓
- S5: **extracted smoke selectors (verbatim)** — venue: `event-block`, `span-N` (their design: `div.className = 'event-block span-' + info.span;`, `dataset.tip2`); conflict: `event-conflict` (ui-common :759); holiday: `event-public-holiday` (ui-common :756) + `.badge-public-holiday` (theme.css, new :+3); replacement-home: `badge`/`badgeClass(conflictReason)` + `urgency-badge` (blade :480/:482); real names: `BMIT5678 Database Systems`
- S6: `git diff HEAD^1..HEAD -- resources/views/partials/ui-nav-bar.blade.php` = 0 lines ✓ (Wave-3 scope untouched)

### T4 — house gates (2026-10-08, all PASS)

- T4.1 phpunit: **110/110**, 523 assertions (identical to frozen baseline — no drift) ✓
- T4.2 phpstan `--memory-limit=1G --no-progress`: **0 errors** ✓
- T4.3 pint lint:check: `public/adminer.php` only = baseline ✓

### T5 — live smoke (2026-10-08, PASS)

- T5.1 safe restart (bracket-trick pkill; views purged; serve3.log); `/login/student` = 200
- T5.2/T5.4 authenticated GETs, all **HTTP 200** with correct roles: staff 5425 → `/venue-timetable-ui`, `/replacement-home-ui`; student 25RSD0001 → `/cohort-timetable-ui`
- T5.3 per-theme assertions (curl = markup+assets; event blocks are JS-injected at runtime, so asset-level checks are the runtime truth per design §3 method):
  - served `/js/ui-common.js` carries the merge: 4 hits `event-conflict|event-block` (cohort-style blocks + conflict colouring)
  - served `/js/mock-data.js`: 7 × "Database Systems" real names (f5d12ed live)
  - served `/css/theme.css`: `.badge-public-holiday` present (holiday badge legend)
  - cohort page markup contains `event-public-holiday`; all 3 pages reference the merged assets
  - replacement-home: `badge`/`urgency-badge` markup is Livewire/JS-composed (0 static hits expected — asset-level badge class verified in theme.css; page 200 + assets live)
