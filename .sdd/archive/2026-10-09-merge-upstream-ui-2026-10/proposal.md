# Proposal — merge-upstream-ui-2026-10

## Why

Upstream `fjing` advanced by 21 commits (`4dc4d06..e7f8036`, pushed 2026-10-09): the "new UI design update" batch — venue timetable redesign (7-item legend, loud conflict stripes, twin-merge, My-Teaching summary cards), a real Cancel Class flow (mock-UI), toast-undo fixes, de-staled Playwright specs, and mock-data/tooling updates. `fedora-backend` must absorb this so the backend-driven pages stay consistent with the current UI design system, and so the freshly built `venue-timetable-db` Livewire view does not harden against a superseded design.

## What's in scope

1. **Merge** `upstream/fjing` (`e7f8036`) into `fedora-backend`.
   - Exactly 1 expected conflict: `page-changelogs/my-timetable-changelog.md` (both sides appended) → keep both entries.
   - All other overlapping files (`theme.css`, `ui-common.js`, `ui-template.blade.php`, 5 changelogs) auto-merge per the recorded `merge-tree` run.
2. **Venue Livewire view adaptation** (`resources/views/livewire/venue-timetable.blade.php` + `app/Livewire/VenueTimetable.php`) to the post-merge venue design:
   - 7-item legend (Available / Your Classes / Others' Classes / Others' Pending / Your Pending / Your Conflict / Others' Conflict) via the updated `ui-legend-bar` partial contract. *(Soft-freeze erratum 2026-10-09: originally miscounted as "6 items"; authoritative count from `upstream/fjing` template is 7 — e7f8036 split Your Conflict into Your/Others Conflict.)*
   - Ownership-aware summary cards. **Pinned final card set (5 cards, in this order, with element ids):**
     | # | Label | Id | Source |
     |---|---|---|---|
     | 1 | Total | `#sumTotal` | kept as-is (DB grid slot count) |
     | 2 | Available | `#sumAvailable` | kept as-is (DB grid slot count) |
     | 3 | My Teaching Classes | `#sumMyClasses` | NEW — sessions where `class_sessions.lecturer_id === auth()->id()` in this venue+week |
     | 4 | My Teaching Hours | `#sumMyHours` | NEW — sum of those sessions' durations (each slot = 30 min) |
     | 5 | Occupied | `#sumOccupied` | kept as-is — label stays **Occupied** (venue-timetable-db design R2/R3 wording; renaming to upstream's "Unavailable" would be a regression) |
     `#sumPending` is **removed** (no pending rows exist in v1 data; upstream dropped the Pending card too). `tests/venue-db.spec.ts` card assertions are amended in this change: `#sumTotal`/`#sumOccupied` assertions retained, assertions for the two new ids added (values must match a SQL spot-check).
   - Server-side twin-merge: `class_sessions` rows sharing venue + day + start are combined-lecture twins → merge before grid build (status severity, cohorts joined).
   - Adopt the `.event-conflict` CSS contract in `statusClassFn` (no conflict rows exist in v1 data — mapping is future-proofing only, no fake data).
   - Changelog: add the adaptation entry to `page-changelogs/venue-timetable-ui-changelog.md` (AGENTS.md standing rule; file has upstream-sync precedent).
3. **Records-intact guard**: row counts of all tables before vs after merge + any migrate; expected delta = 0 (batch contains no DB files).
4. **Gates**: `composer run lint:check`, phpunit full suite, phpstan (`types:check`), venue-db + timetable-wiring + nav-identity Playwright specs, upstream ungated spec subset (exact list pinned in tasks.md).

## Standing guards (operative during apply — AGENTS.md's `pkill -9 php` line is superseded here)

1. **NO `pkill -9 php`, ever.** Server restart = isolated `pkill -f "[a]rtisan serve" || true` + `rm -f storage/framework/views/*.php`.
2. **NO `migrate:fresh --seed` on the demo DB `class_replacement`** (forbidden on demo data; tests use `class_replacement_testing`).
3. Nothing pushed without explicit user authorization.

## Out of scope

- Booking write path (OCC version, pending slots) — stays Slice B.
- `ui-cancel-class-modal` include in the DB view — write-path UI; v1 stays read-only and honest (no fake buttons).
- Legacy Playwright suites — do nothing (user decision stands).
- Wave 3b — parked (user decision stands).
- Any `git push` — only with explicit user authorization.
- The discarded honorific commit `4ac803a` — already content-present in fedora-backend; do not restore.
- The stray `fedora-backend` branch on the upstream remote — pull-only repo, not ours to manage.

## Success criteria

- Merge commit exists on `fedora-backend` with only the one expected changelog conflict resolved.
- Venue page (`/venue-timetable-ui`) renders the new 7-item legend and ownership-aware cards from real DB data; combined-lecture twins merge into single blocks.
- Records-intact: identical row counts before/after.
- All gates green (`lint:check` clean, phpunit ≥121 tests, phpstan 0 errors, venue-db 3/3, timetable-wiring, nav-identity, pinned upstream spec subset).
- `venue-timetable-db` verify folded into this change's verify pass; both changes archive together afterwards. When archiving, apply the venue-timetable-db proposal soft-freeze erratum (scope item 2 week-filter wording) so the archived artifact matches what was built.
