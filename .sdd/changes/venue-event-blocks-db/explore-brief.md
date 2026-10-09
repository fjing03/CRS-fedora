# Explore Brief — venue-event-blocks-db

Date: 2026-10-10. Combined change: **merge upstream UI batch 2** (`e7f8036..f8b35a2`) **+ rewrite the DB venue timetable to the event-blocks design**. One verify pass.

## Incoming batch (verified)

- Range `e7f8036..f8b35a2`: **12 commits, 38 files, +1767/−234**. Zero backend files (partials, ui-design-templates, theme.css, ui-common.js, mock-data.js, page-changelogs, tests, upstream's own SDD archive `warning-modal-keywords`).
- **Overlap model (corrected per review R1):** our side since `e7f8036` touched venue Livewire/blade files, Feature tests, seeders, nav partials, and 6 page-changelogs. Upstream's batch overlaps our side **only in `page-changelogs/`** — 6 files modified on both sides; 5 auto-merge cleanly (disjoint hunks), 1 conflicts (next bullet).
- `git merge-tree --write-tree HEAD upstream/fjing` at `deab68e` (actual output, exit=1): **exactly ONE conflict — `page-changelogs/replacement-arrangement-changelog.md`** (both sides appended: ours +11 lines via `git diff e7f8036..HEAD`; upstream appends its batch entries — resolve by keeping both, in order)
- Reviewer R1 disputed the conflict file/overlap; **empirically resolved in favour of the forecast** by re-running merge-tree at HEAD (single CONFLICT, that exact file) and `git diff e7f8036..HEAD -- page-changelogs/` (our +11 lines present). The reviewer's "byte-tail-identical, cannot conflict" claim came from reflog inference without shell access and does not hold.
- New upstream mock specs: `tests/confirm-guards.spec.ts` (new), `tests/warning-keywords.spec.ts` (new), `tests/venue-timetable.spec.ts` (updated) — all **auth-free mock-page specs** → N/A on our auth-first routes, excluded from gates (standing decision).

## Frozen venue design (locked by upstream at f8b35a2)

From the venue template + `ui-legend-bar` + spec diffs:

1. **Two-axis block language** (§10.0): colour = status (`event-normal` / `event-pending` / `event-conflict`), border = ownership (3px thick = viewer's own classes, 0.5px hairline = others'). Replacement sessions fold into Normal on this page.
2. **Legend = 4 items**: `Available` (colour swatch) + `event-normal` / `event-pending` / `event-conflict` chips (real block classes, fill+border), plus `ownershipHint` line ("thick border = your classes · thin border = others'"). Old 7-item legend is dead.
3. **Uniform tooltips**: `data-tip2` = `lecturer · status` (upstream-verbatim, template ~L835); class name joins via `data-name` + CSS `::after` prefix (theme.css `.event-block::after { content: attr(data-name) ' · ' attr(data-tip2) … }`) — **rendered** tooltip = `name · lecturer · status`. Re-pinned spec must assert what actually renders.
4. **PH-day exception**: viewer's OWN classes on public-holiday days render loud red `event-conflict` ("your class won't run"); others' slots stay empty `PH` cells. Sundays always empty.
5. **Venue dropdown**: CiscoLab (B006) grouped under Lab category (3 categories: Tutorial / LectureHall / Lab); every room option carries `data-tip` full name ("B006 · Cisco Lab").
6. **Summary cards**: same 5 cards/ids as our DB page (`sumTotal`, `sumAvailable`, `sumMyClasses`, `sumMyHours`, **`sumOccupied`/"Occupied"** — deliberate deviation from upstream's `sumUnavailable`/"Unavailable", documented in design.md); descriptions gain `info-keyword` / `warn-keyword` spans. Known inconsistency to fix in the rewrite: the component currently counts holiday slots as Available (VenueTimetable.php L132–134) while our own legend tip says holiday slots can't be booked.

## DB-side adaptation facts

- Current DB page: `app/Livewire/VenueTimetable.php` + `resources/views/livewire/venue-timetable.blade.php` render **status cells** (`.vt-cell-*`) with the 7-item legend. Both must be rewritten to the block renderer above.
- Real DB states: **zero pending/conflict rows** (impossible pre-Slice-B, unique index) → `event-pending`/`event-conflict` paths are built but unreachable from data — EXCEPT the **PH-day exception, which IS reachable** (3 holidays + real sessions) and must work.
- Ownership: the venue page is lecturer-facing; the component knows the current user → "your classes" = sessions of the viewing lecturer.
- Booking UI stays OUT (read-only v1): available slots get the honest "booking coming soon" affordance, no banner/tooltips pretending otherwise.
- Spec re-pins needed: `tests/venue-db.spec.ts` currently pins `.vt-cell-*` selectors + 7-item legend → rewrite for event blocks + 4-item legend. `tests/Feature/VenueTimetableTest.php` — twin-merge crafted-row test stays (twin merging remains in the component); assertions on cell classes move to block classes.
- `page-changelogs/venue-timetable-ui-changelog.md` must be updated (AGENTS standing rule).

## Records-intact baseline

`/tmp/opencode/records-pre-merge2.txt`: class_sessions 101, time_slots 38640, session_cohorts 155, users 266, venues 23, modules 42, cohorts 14, holidays 3, lecturers 14, students 252, semesters 1, replacement_requests 0.

## Rejected approaches

- **Two separate SDD changes** (merge, then rewrite): rejected — the rewrite depends on the merged theme.css/ui-common.js classes; one combined change = one review loop, one verify pass, no artificial sequencing.
- **Keep `.vt-cell-*` cells, restyle only**: rejected — user explicitly wants cohort-style parity with fjing; the design language is block-based, cells can't express the two-axis ownership border.
- **Port pending/conflict fabricated data to demo the states**: rejected — dishonest for a DB-backed page; states stay data-driven.
- **Micro-SDD**: rejected by scale (38-file merge + full renderer rewrite) — this needs the full artifact chain.

## Open questions

- None blocking. Push only on explicit user authorization (standing rule).
