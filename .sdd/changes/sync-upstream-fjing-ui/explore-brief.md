# Explore Brief — sync-upstream-fjing-ui

Date: 2026-10-06
Status: complete (analysis performed before change creation; this brief is the recorded result)

## 1. Goal

Fast-forward our fork onto upstream's `fjing` branch so the fedora backend sits on top of
upstream's latest UI work, then re-establish the two backend capabilities that only exist in
our `fedora-frontend` branch.

## 2. Ref topology

| Repo | Remote | Role |
|---|---|---|
| `/home/jinglinux/tarumt/CRS/class-replacement-system` | `github.com/FjingXR/class-replacement-system` | **pull only**, branch `fjing` |
| `/home/jinglinux/tarumt/CRS-fedora` | `github.com/fjing03/CRS-fedora` | **push here**, branch `fedora-backend` |
| `upstream` (in CRS-fedora) | `github.com/FjingXR/class-replacement-system` | **never push** |

Verified geometry at explore time:

- merge-base `HEAD` ↔ `upstream/fjing` = `f44cc5c`
- `f44cc5c` **is** an ancestor of `HEAD` → 3-way merge is well-formed, no criss-cross
- `HEAD` = `438bdfe`, **26 ahead / 71 behind** `upstream/fjing`
- upstream tip `36c4d2c` (moved from `aa7b726` during explore; +2 commits, both `.sdd/`-only)
- backup tag: `backup/pre-fjing-merge` → `438bdfe`

## 3. Locked decisions

1. Stop after Wave 2. Phases 5+6 stay deferred to `wire-backend-into-refactored-ui`.
2. `fedora-frontend` → **extract 3 artifacts, no branch merge.**
3. **Checkpoint-push the verified commits before the merge.** ✅ done (`438bdfe`).
4. **Adopt upstream's `ReplacementHistory` naming fully** — no aliasing back to `UpcomingReplacements`.
5. **Patch** the existing 33-session seeder; do not regenerate (Wave 2, out of scope here).

## 4. Rejected approaches

| Approach | Why rejected |
|---|---|
| Merge `origin/fedora-frontend` as a branch | Ships an entire divergent UI history; the 3 backend artifacts are the only value. Reviewable surface would be unbounded. |
| Merge upstream with `--strategy-option theirs` on conflicts | `routes/web.php` and `CodingMAIN.md` hold fork-specific decisions (`$uiPages` array, MPU-3133 FR 4.7). Blind acceptance silently deletes backend work. |
| Keep `UpcomingReplacements` naming, alias upstream's route | Two names for one capability, permanent. Decision 4 says adopt upstream's fully. |
| Renumber conflicts as "just take theirs" wholesale | 10 of 12 are *append-both* or *keep-mine* — not discardable. |
| Run `pkill -9 php` before gate verification | Matches `php-fpm` (PID 960 master + 5 workers), killing the FPM pool. Replaced with `pkill -f "artisan serve"` — see open questions. |

## 5. Conflict set — exactly 12 files

Computed as the intersection of `f44cc5c..HEAD` and `f44cc5c..upstream/fjing` changed-file sets.
Independently re-verified after upstream advanced to `36c4d2c`: **unchanged at 12.**

### 5.1 Hard conflicts (3)

| File | Resolution rule |
|---|---|
| `routes/web.php` | **Keep our `$uiPages` array** (lines 30–96; upstream still uses plain closures). Set `'/replacement-history-ui' => ['component' => 'App\Livewire\ReplacementHistory', 'legacy' => 'ui-design-templates.replacement-history-UI-design-template', 'nav' => 'replacement-history', 'mw' => ['auth','role:student']]`. Safe because line 88 `class_exists()` falls back to the legacy view — the component does not exist yet. |
| `CodingMAIN.md` | **Theirs** for the Objective-4 row, notifications, §6/§7.2/§7.3. **Mine** for MPU-3133 FR 4.7 (~line 59) and Staff-ID login (~line 111). |
| `public/js/ui-common.js` | Merge manually — keep shared-helper globals (commit `0a62f1a` depends on them). |

### 5.2 Append-both (8) — `page-changelogs/*`

`cohort-timetable`, `my-request-history`, `my-timetable`, `replacement-arrangement`,
`replacement-home`, `request-approval`, `student-my-timetable-ui`, `venue-timetable`

Both sides appended distinct entries → concatenate, preserve both, no entry dropped.

### 5.3 Modify/delete (1) — **accept the deletion**

`page-changelogs/upcoming-replacements-ui-changelog.md` — upstream deleted it (renamed to
`replacement-history`). We modified it. Take **theirs**: the file is superseded by decision 4.

## 6. Merge-safety checks already performed (read-only)

- `$homeUrl` uses `'{{ $homeUrl ?? '/' }}'` → has a default → no 500 on the merged view
- `notifCount` was removed upstream; our extra parameter is therefore harmless
- `ui-nav-bar.blade.php` + `ui-template.blade.php` are **untouched by our fork** → take theirs
  wholesale; upstream's logout flow is self-consistent
- Upstream's incoming diff: **114 files, 0 `database/` paths, 0 `dataset/` paths** (not truncated)
- Two upstream `.sdd/` archive commits land on files we never modified → zero conflict
- 4 upstream `sdd.yaml` adds hit paths absent locally → clean adds, no add/add

## 7. Post-merge edits — exactly 2

| File | Change |
|---|---|
| `app/Livewire/StudentMyTimetable.php:77` | `navItems` → point at `replacement-history` |
| `tests/Feature/RouteGateMatrixTest.php:20,33,41` | route-gate expectations follow decision 4 |

Already handled *upstream* (no edit needed): `student-my-timetable-UI-design-template.blade.php:6`
and `mock-data.js` comment — both already say "renamed from upcoming-replacements-ui 2026-10-06".

## 8. Artifact extraction from `origin/fedora-frontend` (3)

| Artifact | Destination |
|---|---|
| `BACKEND-TASKS.md` | repo root |
| `ReplacementRequestsSeeder.php` | `database/seeders/` |
| `tests/e2e/` | `tests/e2e/` |

**Keep HEAD's `playwright.config.ts`** — do not take theirs.

## 9. Verification gates

1. `composer run lint:check` → only pre-existing `public/adminer.php`
2. `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → 0 errors
   (plain `types:check` crashes at 128M — memory limit is required)
3. `php vendor/phpunit/phpunit/phpunit --no-coverage` → 105/105
4. `GET /replacement-history-ui` returns **200**
5. `/sdd-verify` → `/sdd-archive`

## 10. Known open questions

- **Process restart method.** Planned command was `pkill -9 php`, which also matches `php-fpm`
  and would kill PID 960 (master) + 5 workers. Proposed replacement: `pkill -f "artisan serve"`.
  Not yet confirmed by the user.
- Whether `auth-wiring` (the one stale UI change upstream did *not* archive) should be archived
  alongside the other 6 after the merge, or kept active.

## 11. Out of scope

- Wave 2 seed-data repair (`repair-timetable-seed-data`)
- Wave 3 = `wire-backend-into-refactored-ui` (frozen; needs a rename unfreeze first —
  see `design.md:31,52`, `specs/rbac-route-gating/spec.md:11,22`, `tasks.md:42`, which still
  name `UpcomingReplacements`)
- Any push to `upstream`
