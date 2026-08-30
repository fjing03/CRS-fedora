# Leftover Tasks — Post-Merge `upstream/fjing` → `fedora-backend`

> Generated: 2026-08-27 | Branch: `fedora-backend` @ `c149ee4` | Merge: `fd8c403` (upstream/fjing 267 commits) | Status: smoke 12/12 routes 200, PHPUnit 94/94, PHPStan 0 errors

Source: leftover from "do until 3" + "do until 6" batches. This file tracks only what is **not yet committed/pushed**.

---

## 1. Done (for reference)

- [x] Dirty-tree commit `cec6157` + backup branch `backup/fedora-backend-pre-merge`
- [x] `git fetch upstream` + merge `upstream/fjing` → `fedora-backend` (`fd8c403`, 13 UI conflicts resolved theirs-first per policy)
- [x] `style: pint fixes` `bc3bc28` (12 files incl. `public/adminer.php`)
- [x] `fix(db): resolve phpstan type errors` `943270e` — VenuesSeeder restructure, `array<string,int>` PHPDoc, `intakeYearShort` guard
- [x] `fix(auth): abort(403)` `c149ee4` — CheckRole/CheckPl + 7 stale test assertions → `vendor/bin/phpunit` 94/94
- [x] Remove worktree `/tmp/opencode/premerge-check` + `git worktree prune`
- [x] Smoke per AGENTS.md: `pkill -9 php && rm storage/framework/views/*.php && php artisan serve --port=8000` → `/`, `/login/*`, 9 UI templates 200, `/dashboard` 302→login

Uncommitted aside: `?? .commandcode/taste/` (untracked, unrelated).

---

## 2. Still Pending — "until 6" remainder

### 2.1 Update `page-changelogs/*.md` (REQUIRED by AGENTS.md)

28 UI files changed in `cec6157..fd8c403` — each affected page needs a dated entry `[2026-08-27] Sync upstream/fjing refactor (merge fd8c403)`.

**Files changed in merge (UI only):**
```
public/css/macos-design.css, theme.css
public/js/logout-modal.js, mock-data.js, ui-common.js
resources/views/auth/login-staff.blade.php, login-student.blade.php
resources/views/layouts/login-template.blade.php, ui-template.blade.php
resources/views/partials/ui-class-detail-modal, ui-empty-state, ui-grid-table,
  ui-guide-block, ui-legend-bar, ui-logout-modal, ui-nav-bar, ui-summary-bar,
  ui-today-btn, ui-venue-dropdown
resources/views/ui-design-templates/CohortTimetable, my-request-history,
  MyTimetable, replacement-arrangement, replacement-home, request-approval,
  student-my-timetable, upcoming-replacements, venue-timetable
```

**Changelogs to append (10):**
- [x] `page-changelogs/my-timetable-changelog.md` (2026-08-31)
- [x] `page-changelogs/cohort-timetable-ui-changelog.md` (2026-08-31)
- [x] `page-changelogs/student-my-timetable-ui-changelog.md` (2026-08-31)
- [x] `page-changelogs/my-request-history-changelog.md` (+ note TASK-006: student nav now points to `upcoming-replacements-ui`) (2026-08-31)
- [x] `page-changelogs/upcoming-replacements-ui-changelog.md` (2026-08-31)
- [x] `page-changelogs/replacement-home-changelog.md` (2026-08-31)
- [x] `page-changelogs/replacement-arrangement-changelog.md` (2026-08-31)
- [x] `page-changelogs/request-approval-changelog.md` (2026-08-31)
- [x] `page-changelogs/venue-timetable-ui-changelog.md` (new page from upstream) (2026-08-31)
- [x] `page-changelogs/login-oop-refactor-changelog.md` (auth pages: 40ba963 + P-prefix eeaa5c4) (2026-08-31)

**Entry template (match house style `head -20 my-timetable-changelog.md`):**
```md
## [2026-08-27] Sync upstream/fjing UI refactor (merge fd8c403)

Merged 267 commits from `FjingXR/class-replacement-system.git#Fjing` into `fedora-backend`.
Conflict policy: theirs-first for UI (views/theme/mock-data/routes), theirs wins on 13 conflicted files.
Verification: `migrate:fresh --seed` 23 venues, PHPStan 0, PHPUnit 94/94, smoke 12/12 routes 200.

### Files Changed
- `resources/views/...` — take theirs
- `public/...` — take theirs
```

After editing: `git add page-changelogs/*.md && git commit -m "docs: sync page changelogs for upstream/fjing merge fd8c403"`

### 2.2 Final verification commit

Before pushing, re-run per AGENTS.md:
```bash
vendor/bin/pint --test --memory-limit=1G  # or non-parallel fallback
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/phpunit --no-progress
```

- [x] 2026-08-31: pint **passed**, PHPStan **0 errors**, PHPUnit **94/94** (377 assertions) — all green on `da3ee50`

### 2.3 Push (ONLY when you say)

Git policy (`CodingMAINfedora.md` §10.6): `origin` = `fjing03/CRS-fedora.git` ONLY. Never push to `upstream`.

Current: `fedora-backend` is 4 commits ahead of `origin/fedora-jing` + merge commit.
```bash
git push origin fedora-backend        # or fedora-backend:fedora-backend as needed
# Do NOT run: git push upstream ...
```

---

## 3. Beyond "until 6" — Phase 2 (not started)

**Goal:** Wire backend into refactored UI (user said "wire the backend, shall i use sdd skills?").

- [ ] Load `sdd-workflow` skill (mandatory before any `/sdd-*`)
- [ ] `sdd-propose` change `wire-backend-into-refactored-ui` under `.sdd/changes/` — follow `prompts/sdd-propose-ui-page.md` for any NEW pages (venue-timetable, upcoming-replacements)
- [ ] Map each refactored template → Livewire/controller → existing services:
  - `MatrixIntersectionEngine` (4-vector), `OCCValidator` + `OCCResult`, slot state machine
  - RBAC gates `CheckRole`/`CheckPl` on all new routes (FR 4.13/4.14)
  - Emails via DB queue (FR 1.9/2.13/4.15/4.16)
  - Replace `window.MockData` reads with real DB data
  - Theme tokens per `theme.css` §10.0
- [ ] `design.md` → `tasks.md` → apply incrementally (engine → OCC → approval/dashboard/emails)

Existing SDD context: `.sdd/changes/` has 18 prior changes (see `ls .sdd/changes/`).

---

## 4. Quick resume commands

```bash
git status
git log --oneline -6
cat LEFTOVER_TASKS.md
# continue changelogs:
# edit page-changelogs/*.md as per §2.1, then verify + commit + push (with approval)
```
