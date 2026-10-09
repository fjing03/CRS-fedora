# Design — merge-upstream-ui-2026-10

Frozen baseline: `proposal.md` (Round 2 PASS). Baseline context: `explore-brief.md`.

## 1. Merge mechanics

1. **Preconditions (verified during explore):**
   - Merge-base `4dc4d06`; incoming range `4dc4d06..e7f8036` (21 commits, 40 files, zero backend files).
   - Worktree: HEAD `adc95bb` + uncommitted `.sdd/changes/venue-timetable-db/` (untracked), `.sdd/changes/merge-upstream-ui-2026-10/` (untracked), `M .sdd/changes/wire-backend-into-refactored-ui/explore-brief.md` (tracked-modified). **No path collision** with merge content (verified: merge's `.sdd/` paths are `archive/2026-10-08-cancel-class-enhancement/` and `changes/venue-legend-parity/` only) → the merge can run on this dirty tree. Do NOT stash.
   - Incoming `.sdd/changes/venue-legend-parity/` arrives as an active-looking dir (upstream closed it but left it in `changes/`); mirror upstream state as-is, handle at archive time.
2. **Command sequence:** `git merge upstream/fjing --no-edit` (merge commit, no rebase — preserves shared history).
3. **Conflict resolution (exactly 1 expected):** `page-changelogs/my-timetable-changelog.md` — keep BOTH sides' entries (fedora's entries followed by upstream's, chronological within the file's existing convention). If any other conflict appears: STOP, record it in review-log, re-assess before continuing.
4. **Post-merge sanity:** `git diff HEAD~1...HEAD --stat` covers all 40 expected files; `git log --oneline -3` shows the merge commit with two parents.

## 2. Venue Livewire view adaptation

### 2.1 Component (`app/Livewire/VenueTimetable.php`)

- **Ownership set:** query `class_sessions` ids for the selected venue+week where `lecturer_id === auth()->id()` (lecturer_id holds the USER id — compare with `auth()->id()`, never a staff number). Every event carries `isMine` (the `.vt-cell-yours/.vt-cell-others` cell classes already encode this; formalize it as a baseEvent field so the legend and cards share one source of truth).
- **New card aggregates** (replace `sumPending`):
  - `sumMyClasses` = count of MY sessions in venue+week — **each class counts separately** (upstream wording), i.e. session rows, NOT merged blocks.
  - `sumMyHours` = MY occupied `time_slots` rows × 0.5 h. Display strips a trailing `.0` (28 slots → `14`, 27 slots → `13.5`).
- **Remove `sumPending`** from the component and the blade (pinned card table in frozen proposal).
- **Twin-merge (defensive):** before grid build, group events by `venue + day_of_week + start_time`; merge status by severity `conflict > pending > replacement > normal`, join cohort labels, sum students. **No twins exist in v1 data** (verified by SQL — unique index on `time_slots(venue_id, day_of_week, start_time)` for occupied/pending makes them impossible until Slice B). The code path is contract-parity; covered by a crafted-row feature test (§2.3), not by seed data.
- **`statusClassFn` contract:** add `conflict → 'event-conflict'` mapping (loud striped style after merge). Unreachable in v1 (no conflict rows) — future-proofing only, no fake data. Existing `.vt-cell-*` classes untouched.

### 2.2 Blade (`resources/views/livewire/venue-timetable.blade.php`)

- **Legend → 7 items** via the updated `ui-legend-bar` partial, labels/colours/swatch classes **verbatim from the post-merge upstream template**: Available / Your Classes / Others' Classes / Others' Pending (`--color-surface-variant`) / Your Pending (`--color-tertiary-container`) / Your Conflict (`class: event-conflict`) / Others' Conflict (`--color-error-container`). **Tips: verbatim EXCEPT action-implying ones** — Available's tip drops upstream's "click to book" and keeps the honest rewording from venue-timetable-db design R1 fix 3 ("Free slot…"), per the frozen proposal's "read-only and honest" pin. Zero-count pending/conflict items still render — the legend documents semantics (the slots genuinely don't exist yet; Slice B will populate them).
- **Summary cards → the 5-card pinned set** from frozen proposal: `#sumTotal`, `#sumAvailable`, `#sumMyClasses` (NEW), `#sumMyHours` (NEW), `#sumOccupied` (label **Occupied** unchanged; card 1 keeps the blade's display label "Total Slots" — the pin that matters is the id). Card chrome classes: reuse upstream's (`card-replacement`, `card-hours`) — **verified present** in the post-merge `theme.css` (lines 1288–1294 via merge-tree blob check).
- **Changelog**: append the adaptation entry to `page-changelogs/venue-timetable-ui-changelog.md` (frozen proposal scope item 2).
- Keep the honest hint line (no Book button, no `ui-cancel-class-modal` include — write path is Slice B).

### 2.3 Tests

- **`tests/venue-db.spec.ts` — test 2 (summary cards; test 1 is the deep-link test, untouched):** login switches from `5425` (user 1, teaches nothing in B006) to **`4288`** (user 3, Dr. Christopher Lazarus — SQL-verified owner of B006 W1: 8 classes / 28 slots). Assertions: existing `#sumTotal`=`120`, `#sumAvailable`=`72`, `#sumOccupied`=`48` retained; NEW `#sumMyClasses`=`8`, `#sumMyHours`=`14`. Tests 1 and 3 unchanged (venue-level, login `5425`).
  - Rate-limit note: 3 tests × 1 login each, two distinct IDs — within the 5/min-per-ID budget.
- **`tests/Feature/VenueTimetableTest.php`:**
  - Update any test touching removed `sumPending` / card structure.
  - NEW feature test: twin-merge with crafted rows on `class_replacement_testing` (two sessions, same venue/day/start, different cohorts → one merged event, severity-ordered status, cohorts joined, `sumMyClasses` counts 2).
  - NEW/extended feature test: ownership cards — sessions of two lecturers in one venue+week → `sumMyClasses`/`sumMyHours` reflect only `auth()->id()`'s rows.
- **SQL spot-check at verify:** the §2.3 numbers re-confirmed by direct SQL after merge (data unchanged, but the check is the records-intact companion for the spec).

## 3. Verify plan (pinned gate list)

| Gate | Expectation |
|---|---|
| `composer run lint:check` | clean |
| `composer run types:check` (phpstan) | 0 errors |
| `php artisan test` (full suite) | ≥121 tests green (may grow with new feature tests) |
| `tests/venue-db.spec.ts` | 3/3 |
| `tests/timetable-wiring.spec.ts` | 5/5 |
| `tests/nav-identity.spec.ts` | 3/3 |
| Upstream subset: `tests/confirm-guards.spec.ts`, `tests/ui-regression.spec.ts`, `tests/venue-timetable.spec.ts` | green (they run against mock-UI pages, unchanged in structure by the backend) |

**Excluded from gates:** `tests/cancel-class.spec.ts` (NEW upstream, 480 lines) — write-path mock-UI flow, login-heavy (rate-limit 5/min per ID makes it flaky in this environment), and unrelated to any fedora backend surface. Deferred debt: run it once spec infra is tuned; recorded in review-log at verify.

**Records-intact:** exact row counts (class_sessions 101, time_slots 38640, session_cohorts 155, users 266, venues 23, modules 42, cohorts 14, holidays 3, lecturers 14, students 252, semesters 1) identical before/after merge + any migrate. Baseline snapshots: `/tmp/opencode/records-pre-merge{,-exact}.txt`.

**Server:** if views stale after Blade changes: `pkill -f "[a]rtisan serve" || true` + `rm -f storage/framework/views/*.php` + restart — **never `pkill -9 php`**.

## 4. Commit plan (no push without explicit authorization)

1. `chore(sdd)` — pre-existing dirty SDD state (venue-timetable-db artifacts + Wave-3b brief note) committed BEFORE the merge to keep the merge commit clean of unrelated changes.
2. Merge commit (`git merge upstream/fjing`).
3. `feat(venue)` — component + blade + spec amendments (adaptation).
4. `chore(sdd)` — archive `merge-upstream-ui-2026-10` AND `venue-timetable-db` (post-verify), including the venue-timetable-db proposal erratum.

## 5. Risks

- **Semantic (not textual) drift in auto-merged `ui-common.js`** (+910 lines): **retired by pre-check** — `WeekNavigator(semesterData, weekData, selectId, storageKey, weekFilter)`, `buildTimetableGrid(cfg)`, and `VenueDropdown(config)` signatures are identical pre/post-merge (only line shifts from mid-file additions); `timetable-wiring` + `nav-identity` specs remain the behavioral backstop.
