# Tasks — msi-handoff-integration

> Micro-SDD: tasks execute immediately after proposal freeze. Each task ≤ 1 hour.

## Batch 1 — Port `fedora-frontend` unique artifacts

- [x] 1.1 `git checkout origin/fedora-frontend -- .sdd/changes/frontend-read-wiring .sdd/changes/playwright-ui-smoke-suite tests/Feature/Api/ApiReadEndpointsTest.php`
- [x] 1.2 Move the three paths under `.sdd/archive/fedora-frontend-legacy/` (preserve
      internal structure: `<change>/` folders + `tests/Feature/Api/`)
- [x] 1.3 Write `.sdd/archive/fedora-frontend-legacy/README.md`: provenance (branch,
      date, verdict table 47/27/5/15), why each group was ported, supersession status,
      explicit note that `fedora-frontend` deletion is still on user hold
- [x] 1.4 Verify: `git status` shows exactly the expected adds; no source-path leftovers

## Batch 2 — Two-machine workflow doc

- [x] 2.1 Write `docs/two-machine-workflow.md`: machine roster (MSI primary / HP
      secondary), branch policy (one trunk; graveyard branches), the 3 serialize rules,
      DB guardrails (HP demo `class_replacement` untouchable; MSI `class_replacement` =
      stale legacy, run on `class_replacement_fresh`; testing DB disposable; restore =
      pristine dump only), per-machine boot notes, push policy (`origin` only, explicit
      user word)
- [x] 2.2 Add Active row to `docs/README.md` index
- [x] 2.3 Verify: every MSI claim in the doc matches the 2026-10-10 checks in this
      change's proposal §1.2 (no unverified statements)

## Batch 3 — Handoff guards addendum

- [x] 3.1 Append dated `## Handoff integration addendum — 2026-10-10` sections to
      `.sdd/changes/wire-backend-into-refactored-ui/explore-brief.md`:
      (a) records-intact tool now `crs:db-row-counts`, (b) upstream
      `/replacement-history-ui` legacy-fallback removal guard, (c) note that
      HP-HANDOFF.md knowledge was diffed against this change — OCC-FK/D7/D11/
      real-records guard already present, nothing duplicated
- [x] 3.2 Verify: no frozen artifact (proposal/design/specs/tasks) touched — addendum
      vehicle only, per post-Wave-1 precedent

## Gates & wrap-up

- [x] 4.1 `composer run lint:check` green
- [x] 4.2 `vendor/bin/phpstan analyse --memory-limit=1G` green
- [x] 4.3 Commits (house split, no push): `docs(sdd): preserve unique fedora-frontend
      artifacts` · `docs: add two-machine workflow guide` · `docs(sdd): record handoff
      guards in wire-backend explore-brief`
- [x] 4.4 `/sdd-verify` sanity pass, then archive with
      `docs(sdd): archive msi-handoff-integration`
