# Tasks — merge-upstream-ui-2026-10

Frozen baseline: proposal.md (R2 PASS + legend erratum), design.md (R2 PASS). Each task ≤ 2h.

## T1 — Pre-merge housekeeping commit
- [ ] `git add .sdd/changes/venue-timetable-db .sdd/changes/merge-upstream-ui-2026-10 .sdd/changes/wire-backend-into-refactored-ui/explore-brief.md` and commit as `docs(sdd): venue-timetable-db artifacts + Wave-3b pre-emption note + merge-upstream-ui-2026-10 batch 1-3` (worktree clean except intentional additions).
- [ ] Confirm `git status` clean before merging (merge commit must carry no unrelated changes).

## T2 — Execute the merge
- [ ] `git merge upstream/fjing --no-edit` (merge commit, two parents).
- [ ] Resolve the ONE expected conflict: `page-changelogs/my-timetable-changelog.md` — keep BOTH sides' entries. Any other conflict → STOP, log in review-log, re-assess.
- [ ] Post-merge sanity: merge diff covers the 40 expected files; `git log --oneline -3` shows the merge commit.

## T3 — Records-intact check #1 (post-merge, pre-migrate)
- [ ] Re-run the exact-count query (class_sessions 101, time_slots 38640, session_cohorts 155, users 266, venues 23, modules 42, cohorts 14, holidays 3, lecturers 14, students 252, semesters 1) — must equal `/tmp/opencode/records-pre-merge-exact.txt`.
- [ ] If any migration is pending (`php artisan migrate:status`), run `php artisan migrate` ONLY (never `migrate:fresh --seed`), then re-check counts — expected delta 0.

## T4 — Component adaptation (`app/Livewire/VenueTimetable.php`)
- [ ] Add `isMine` to baseEvent (single source of truth: `class_sessions.lecturer_id === auth()->id()`); `.vt-cell-yours/.vt-cell-others` classes consume it.
- [ ] Replace `sumPending` with `sumMyClasses` (my session ROWS in venue+week — each class counts separately, not merged blocks) and `sumMyHours` (my occupied `time_slots` × 0.5; display strips trailing `.0`).
- [ ] Twin-merge (defensive): group events by `venue + day_of_week + start_time` before grid build; severity `conflict > pending > replacement > normal`; join cohort labels; sum students.
- [ ] `statusClassFn`: add `conflict → 'event-conflict'` (unreachable in v1 — no fake data).

## T5 — Blade adaptation (`resources/views/livewire/venue-timetable.blade.php`)
- [ ] Legend → **7 items** via `ui-legend-bar` (labels/colours/swatch classes verbatim: Available / Your Classes / Others' Classes / Others' Pending `--color-surface-variant` / Your Pending `--color-tertiary-container` / Your Conflict `class: event-conflict` / Others' Conflict `--color-error-container`). Tips verbatim EXCEPT action-implying ones (known instance: Available's "click to book" → honest "Free slot…" rewording per venue-timetable-db R1 fix 3).
- [ ] Summary cards → pinned 5-card set: `#sumTotal` (Total Slots), `#sumAvailable`, `#sumMyClasses` (card-replacement), `#sumMyHours` (card-hours), `#sumOccupied` (Occupied). Remove the `#sumPending` card.
- [ ] Keep the honest hint line; NO `ui-cancel-class-modal` include, NO Book button.
- [ ] Append the adaptation entry to `page-changelogs/venue-timetable-ui-changelog.md`.

## T6 — Spec amendment (`tests/venue-db.spec.ts`)
- [ ] Test 2 (summary cards): login `4288`; keep `#sumTotal`='120', `#sumAvailable`='72', `#sumOccupied`='48'; add `#sumMyClasses`='8', `#sumMyHours`='14'. Tests 1 & 3 untouched.
- [ ] Rate-limit budget intact: 3 tests × 1 login, IDs `4288`/`5425` (separate 5/min buckets).

## T7 — Feature tests (`tests/Feature/VenueTimetableTest.php`, testing DB only)
- [ ] Update any test referencing `sumPending`/old card structure.
- [ ] NEW twin-merge test: two crafted sessions, same venue/day/start, different cohorts → one merged event, severity-ordered status, cohorts joined, `sumMyClasses` counts 2.
- [ ] NEW ownership test: two lecturers' sessions in one venue+week → cards reflect only `auth()->id()` rows.

## T8 — Full gate run (mirrors design §3 exactly)
- [ ] Server refresh if views stale: `pkill -f "[a]rtisan serve" || true` + `rm -f storage/framework/views/*.php` + isolated restart. **NEVER `pkill -9 php`.**
- [ ] `composer run lint:check` → clean.
- [ ] `composer run types:check` (phpstan) → 0 errors.
- [ ] `php artisan test` → all green (baseline 121/121 + new feature tests).
- [ ] Playwright: `venue-db.spec.ts` 3/3 · `timetable-wiring.spec.ts` 5/5 · `nav-identity.spec.ts` 3/3.
- [ ] Upstream subset: `confirm-guards.spec.ts` + `ui-regression.spec.ts` + `venue-timetable.spec.ts` → green.
- [ ] **Excluded**: `cancel-class.spec.ts` (write-path mock UI, login-heavy/rate-limit-flaky) — deferred debt, recorded at verify.
- [ ] SQL spot-check: B006 W1 (Total 120 / Available 72 / Occupied 48 / MyClasses 8 / MyHours 14 via user 3) + one W8 re-check.
- [ ] Records-intact check #2: exact counts still equal baseline.

## T9 — Verification & archive (after user-visible verify stage)
- [ ] Append verify verdict to `review-log.md`; run sdd-verify checklist.
- [ ] Archive BOTH changes (`merge-upstream-ui-2026-10` + `venue-timetable-db`) to `.sdd/archive/`, applying the venue-timetable-db proposal soft-freeze erratum (week-filter wording) at archive time.
- [ ] Final commit: `docs(sdd): archive merge-upstream-ui-2026-10 + venue-timetable-db`.
- [ ] NO push without explicit user authorization (`adc95bb` and everything above it stay local until then).
