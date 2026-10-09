# Proposal — venue-event-blocks-db

## Why

Two work streams converge here:

1. **Upstream UI batch 2 arrived and is stable.** User confirmed "UI is stable now" — the last parked reason for deferring venue work is gone. Batch `e7f8036..f8b35a2` (12 commits, 38 files, +1767/−234, zero backend files) refines the shared design system and **freezes the venue event-blocks design**: two-axis language (colour = status, border = ownership), 4-item legend, uniform status tooltips, PH-day own-class exception, B006-under-Lab dropdown.
2. **Our DB venue page renders the old language.** `VenueTimetable.php` + `venue-timetable.blade.php` still use status cells (`.vt-cell-*`) and the dead 7-item legend. With the design now frozen, the deferred rewrite (`venue-event-blocks-db`) must be done against exactly this target — merging first, rewriting second, in ONE change so the rewrite targets the final frozen design and there is one verify pass.

## Scope

### S1 — Merge upstream/fjing into fedora-backend
- Merge `upstream/fjing` (`e7f8036..f8b35a2`). Verified at current `HEAD` (`deab68e`) via `git merge-tree --write-tree`: **exactly one conflict — `page-changelogs/replacement-arrangement-changelog.md`** (both sides appended; resolve by keeping both entries in order). Five other both-sides-modified changelogs auto-merge cleanly (disjoint hunks). Review R1's contrary forecast was empirically resolved in favour of this one (merge-tree output + `git diff e7f8036..HEAD -- page-changelogs/`).
- Zero backend files incoming; **records-intact guard**: row counts must match `/tmp/opencode/records-pre-merge2.txt` before and after (class_sessions 101, time_slots 38640, … replacement_requests 0). No re-seed, no migrations.
- Upstream's new/updated mock specs (`confirm-guards.spec.ts`, `warning-keywords.spec.ts`, `venue-timetable.spec.ts`) are auth-free mock-page specs → declared **N/A** on our auth-first routes, excluded from gates (standing decision).

### S2 — Rewrite the DB venue page to event blocks
- `app/Livewire/VenueTimetable.php` + `resources/views/livewire/venue-timetable.blade.php`: replace the status-cell grid with cohort-style **event blocks** per the frozen design (details in design.md):
  - two-axis language: colour = status, border = ownership (3px = viewer's own, hairline = others')
  - legend: **4 items** (Available + Normal/Pending/Conflict chips using real block classes) + ownership hint line
  - uniform tooltips per upstream verbatim: `data-tip2` = `lecturer · status`; the class name joins via `data-name` + CSS `::after` prefixing (theme.css `.event-block::after`), so the **rendered** tooltip = `name · lecturer · status`
  - **PH-day exception** (reachable in real DB: 3 holidays): viewer's own classes on holidays render loud red `event-conflict`; others' stay empty `PH` cells; Sundays always empty
  - venue dropdown: B006 CiscoLab grouped under Lab; room options carry full-name `data-tip`
  - summary cards: our 5-card set is **kept, including the 5th card `sumOccupied`/"Occupied"** — a documented deviation from upstream's `sumUnavailable`/"Unavailable" (read-only page counts what's booked; frozen last merge). Descriptions adopt upstream's `info-keyword`/`warn-keyword` spans. One semantic fix rides along: **Available must exclude Sunday and public-holiday slots** (the component currently counts them Available while our own legend tip says they can't be booked)
  - **booking stays out** (read-only v1): available slots get an honest "booking coming soon" affordance; no fake booking UI
  - `event-pending` / `event-conflict` code paths built but unreachable from data (zero rows pre-Slice-B) — Slice B later only feeds data
- Keep existing component behaviour: week navigation, venue filtering, twin-merge of combined-lecture rows, auth.

### S3 — Re-pin tests + changelog
- `tests/venue-db.spec.ts`: rewrite pins from `.vt-cell-*` / 7-item legend to event blocks / 4-item legend + ownership hint; keep ≤5 logins/min per ID (standing rate-limit rule).
- `tests/Feature/VenueTimetableTest.php`: update class assertions (cells → blocks); twin-merge crafted-row test stays. **PH-day own-class exception**: pin via a crafted-row Feature test (same reflection pattern as twin-merge) if no real holiday/own-class instance exists in the displayed data — this guarantees the marquee criterion is tested, not silently skipped.
- `page-changelogs/venue-timetable-ui-changelog.md`: record the rewrite.

### S4 — Verify (one pass) + archive
- Gates: `composer run lint:check`, `composer run types:check`, `php artisan test` (full suite), Playwright gates (`venue-db`, `timetable-wiring`, `nav-identity`) — upstream's mock specs (`venue-timetable.spec.ts`, `confirm-guards.spec.ts`, `warning-keywords.spec.ts`) stay **excluded** — records-intact check.
- Archive to `.sdd/archive/`. **No push without explicit authorization.**

## Out of scope

- Booking write path / Slice B (parked Wave 3b) — pending/conflict states stay data-unreachable.
- Any change to upstream's shared files beyond the merge itself.
- Re-enabling commented `/api/v1` routes.
- Fabricating pending/conflict demo data.
- Backend logic changes (merge introduces none; rewrite is view/component-level).

## Success criteria

- Merge complete; all row counts identical pre/post; no migrations added by the merge.
- Venue DB page renders event blocks in the frozen two-axis language with 4-item legend + ownership hint; PH-day own-class exception proven (real holiday data where it exists in the window, else crafted-row Feature test); B006 under Lab with tooltips.
- `tests/venue-db.spec.ts` passes against the new renderer; all gates green (pint, phpstan, phpunit, 3 Playwright gates).
- Changelog updated; change archived; nothing pushed without user authorization.
