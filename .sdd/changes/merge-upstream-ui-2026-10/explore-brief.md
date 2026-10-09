# Explore Brief — merge-upstream-ui-2026-10

Date: 2026-10-09
Merge source: `upstream/fjing` @ `e7f8036` (user pushed new UI batch from another machine)
Target: `fedora-backend` @ `adc95bb` (HEAD, unpushed)
Merge-base: `4dc4d06` — everything through `4dc4d06` is already in fedora-backend; this merge brings in the 21 commits `4dc4d06..e7f8036`.

## Scope of the incoming batch (verified)

`git diff --stat fedora-backend...upstream/fjing` → 40 files, +5900/−478.

### Backend-sensitive files: NONE
- No `routes/`, `app/`, `database/` (migrations/seeders), `config/`, `composer.*`, `package.json`, `.env` changes.
- **No DB schema or data impact.** Standing records-intact guard still runs (row counts before/after must be identical), but expected delta = 0.

### Content classes
1. **UI templates** (`ui-design-templates/*.blade.php`): cohort, my-timetable, replacement-arrangement, replacement-home, student-my-timetable, **venue-timetable (113 lines)**.
2. **Shared JS**: `public/js/ui-common.js` +910 (conflict red, twin merge, 6-item legend support, modal rows, toast undo fixes, resize de-dupe).
3. **Shared CSS**: `public/css/theme.css` 11 lines — `.event-conflict` becomes loud striped red (caution stripes via `color-mix`, 2px error border).
4. **New partial**: `resources/views/partials/ui-cancel-class-modal.blade.php` (+36).
5. **Modified partial**: `partials/ui-legend-bar.blade.php` — swatch may carry a real block class (e.g. `event-conflict`) instead of flat colour.
6. **Mock data**: `mock-data.js` +92; new `scripts/check_mock.js` (380 lines, mock-data validator).
7. **Tests (upstream's own Playwright specs)**: new `cancel-class.spec.ts` (480), updated `confirm-guards`/`ui-regression`/`venue-timetable` (de-staled upstream, 37→0 failures there).
8. **Changelogs**: several `page-changelogs/*.md`.

## Conflict analysis (real `git merge-tree --write-tree` run)

- Exactly **1 content conflict**: `page-changelogs/my-timetable-changelog.md` — both sides appended entries. Resolution: keep both (chronological concatenation).
- `theme.css` and `ui-common.js` auto-merge cleanly despite being touched on both sides (fedora appended `.vt-cell-*` classes at EOF; upstream edits are mid-file).

## Side quest settled before this change

- Discarded local commit `4ac803a` (honorific split): its content is **already in fedora-backend** (migration `2026_10_02_024758_add_honorific_to_users_table.php` + `User::displayName()`) and **already applied to the demo DB** (`users.honorific` populated). NOT restored — restoring would create a duplicate migration. Nothing to do.
- Upstream repo `FjingXR` has a stray `fedora-backend` branch @ `d0c297e` — verified to be an old ancestor of our branch (2026-08-03 state). Harmless, not ours to delete (pull-only rule).

## Interaction with `venue-timetable-db` (the pre-empting change)

`venue-timetable-db` (in `.sdd/changes/`, apply complete, verify pending due to reviewer outage) built the Livewire view `resources/views/livewire/venue-timetable.blade.php` against the OLD venue template. This merge changes that design:

| Upstream change | Impact on Livewire view |
|---|---|
| Legend: 4 → **6 items** (Your/Others × Pending/Conflict split; conflict swatch = real block class) | View's legend partial must adopt the 6-item set; needs ownership split in data (mine vs others per slot) |
| Summary cards: "Pending (sumPending)" → **"My Teaching Classes (sumMyClasses)" + "My Teaching Hours (sumMyHours)"**; Available + Unavailable kept | View's DB-status cards (Total/Available/Pending/Occupied) should align: ownership-aware cards computable server-side (`class_sessions.lecturer_id === auth()->id()`) |
| `.event-conflict` loud stripes + owner-gated colouring (mine=loud red, others=quiet red) | View's `statusClassFn` mapping must follow; DB has no "conflict" status v1 — conflict slots arise only from the booking write path (Slice B), so v1 keeps current statuses but adopts the CSS contract |
| Twin-merge (combined lectures: same venue+day+start, one row per cohort → merge by severity, cohorts joined) | Server-side: `class_sessions` rows sharing venue/day/start = combined-lecture twins → merge in the component before grid build |
| `ui-cancel-class-modal` partial include | Booking write path is Slice B — view does NOT include the modal (no fake buttons); note only |
| Resize-listener de-dupe in template JS | Template-side; view's own WeekNavigator usage unaffected |

**Decision (user-approved flow):** this merge change ALSO adapts the venue Livewire view to the new design, then ONE verify pass covers the final state; `venue-timetable-db` archives together with this change afterwards.

## Rejected approaches

- **Restoring `4ac803a`** — duplicate migration collision with fedora-backend's identical file. Rejected.
- **Merge without SDD** — violates "No proposal, no change" for upstream merges (standing post-3a rule).
- **Separate follow-up change for venue view adaptation** — would force two verify rounds on the same page for one logical outcome; folded into this change instead.
- **Deleting the stray upstream `fedora-backend` branch** — pull-only repo; not ours to manage.

## Standing guards for the merge itself

1. Records-intact check: row counts of ALL tables before vs after merge + any migrate (expected: identical).
2. NO `migrate:fresh --seed` on demo DB `class_replacement` (forbidden ever).
3. No `pkill -9 php`; server restart = isolated `pkill -f "[a]rtisan serve" || true` + `rm -f storage/framework/views/*.php`.
4. Explicit push only: nothing pushed without user authorization (HEAD `adc95bb` is still unpushed).

## Known open questions

- Upstream's de-staled Playwright specs (incl. new `cancel-class.spec.ts`) run against the mock-UI pages; in fedora they should behave identically (mock pages unchanged in structure) — verify will run the ungated subset.
- Whether fedora's `.vt-cell-*` CSS (appended for the DB view) collides semantically with upstream's `.event-conflict` restyle — no (different class namespaces), but verify checks the venue page visually via specs.
