# Proposal — MSI Handoff Integration (port artifacts · two-machine workflow · Wave 3b addendum)

> **Status:** Micro-SDD (docs-only chore — proposal + tasks, no design/specs batch).
> **Branch:** `fedora-backend`. **Machines:** MSI (primary) ← HP handoff 2026-10-10.

## 1. Why This Change Is Needed

The HP laptop exported its session context to `/home/philler/Downloads/HP-HANDOFF.md`
(2026-10-10, 10 sections, START/END clean-tree proof). The MSI session reviewed it,
cross-checked every verifiable claim against the repo, and filled the handoff's own
flagged gap (§5: "file-by-file comparison — that's the one gap"). Three concrete
follow-ups resulted:

1. **Preserve artifacts that exist on `fedora-frontend` and nowhere else.**
   File-by-file verdict on the branch's 91 unique files (2026-10-10, MSI):
   47 byte-identical to backend copies · ~27 older drafts/metadata-only diffs ·
   5 superseded code files · **15 files with NO backend counterpart:**
   - `.sdd/changes/frontend-read-wiring/` — 7 files; header says
     **"Status: Frozen (Batch 1)"** → a frozen decision record (API-read wiring plan
     behind the now-commented `/api/v1` routes). Frozen = load-bearing by house rules.
   - `.sdd/changes/playwright-ui-smoke-suite/` — 7 files; Draft, superseded by the
     current gate set (`venue-db`, `timetable-wiring`, `nav-identity`, `pages-parity`)
     but no other copy exists.
   - `tests/Feature/Api/ApiReadEndpointsTest.php` — tests the `/api/v1` read routes
     that `b1af915` commented out as orphans; parked alongside them.
   These must be **ported before any `fedora-frontend` deletion** (user has explicitly
   put deletion on hold for now).

2. **No two-machine workflow doc exists.** `AGENTS.md` now documents branch/push
   policy, but nothing records: the MSI/HP machine roster, the serialize rules
   (one machine edits at a time · pull-before-start · no parked dirty trees), or
   the per-machine DB landscape. MSI-specific DB facts verified 2026-10-10:
   `.env` → `class_replacement_fresh`; `class_replacement` here is a **stale legacy
   DB** (185 users, pre-`time_slots` schema — NOT demo data); `test1` stray;
   no crontab for `philler`.

3. **Two handoff guards are not yet registered in `wire-backend-into-refactored-ui`.**
   Review of the handoff against the frozen artifacts confirmed the OCC-FK fix (D5),
   anchor-slot limitation (D11), D7 release label and real-records guard are ALREADY
   recorded and review-verified — no duplication needed. Two items are genuinely
   missing from the change's addendum vehicle:
   - The records-intact obligation now has a committed tool:
     `php artisan crs:db-row-counts` (`DbRowCountsCommand`) — the post-3a addendum
     still says "cheap psql count snapshot".
   - Upstream `/replacement-history-ui` legacy closure fallback is safe ONLY while
     `class_exists('App\Livewire\ReplacementHistory')` is false; when the component
     lands the legacy route must be removed deliberately, not silently.

## 2. In scope

| # | Task | Deliverable |
|---|------|-------------|
| 1 | Port the 15 files | `.sdd/archive/fedora-frontend-legacy/` + provenance README |
| 2 | Two-machine workflow doc | `docs/two-machine-workflow.md` + `docs/README.md` index row |
| 3 | Handoff guards | dated addendum sections in `.sdd/changes/wire-backend-into-refactored-ui/explore-brief.md` |

## 3. Out of scope

- **Deleting `fedora-frontend` / `fedora-jing` / `fedora`** — explicit user hold;
  deletion stays blocked until the port is pushed to origin and the user says go.
- Wave 3b (Slices B/C) — still parked awaiting the user's explicit go.
- HP-side chores (re-point branch tracking, archive `venue-legend-parity`,
  `composer update --lock`) — recorded for the next HP session, not done here.
- No PHP/Blade/JS behaviour changes; no DB writes. Gates: pint + phpstan
  (the ported `.php` test lands under `.sdd/` — outside phpstan paths; pint
  verified by `lint:check`).

## 4. Success criteria

- [ ] All 15 files present under `.sdd/archive/fedora-frontend-legacy/` with README;
      `git status` otherwise clean per commit.
- [ ] `docs/README.md` indexes the new workflow doc; doc states only MSI-verified facts.
- [ ] explore-brief addenda dated 2026-10-10, following the post-Wave-1 vehicle precedent.
- [ ] `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G` green.
- [ ] Conventional commits, code/docs split per house rule; **no push** (user's word required).
