# Two-Machine Workflow — MSI (primary) + HP (secondary)

> Added 2026-10-10 (change `.sdd/changes/msi-handoff-integration/`).
> **Scope:** how the two dev machines share one branch safely, and where each
> machine's data lives. Git policy (push targets, upstream rule) stays in
> `CodingMAIN.md` §10.6 — this doc adds the machine layer on top.
> All MSI-side facts below were verified by command on 2026-10-10; HP-side facts
> come from the HP's own verified export (`HP-HANDOFF.md`, 2026-10-10).

## 1. The machines

| | **MSI** | **HP laptop** |
|---|---|---|
| OS / user / gh | CachyOS · `philler` · `FjingXR` | Fedora · `jinglinux` · `fjing03` |
| Role | **Primary** — day-to-day dev | Secondary — original dev machine |
| Working clone | `…/BMCS3404 PROJECT I (4)/CRS-fedora-backend` | `/home/jinglinux/tarumt/CRS-fedora` |
| `.env` DB | `class_replacement_fresh` | `class_replacement` (protected demo) |

## 2. Branch policy — one trunk, no machine branches

- `fedora-backend` is the **only working branch**; both machines pull/push here.
- **No per-machine or per-role branches.** The old split (`fedora-frontend` vs
  `fedora-backend`) stranded 9 commits and 90 files — the cleanup cost a whole
  analysis session. Splitting postpones conflicts instead of preventing them;
  with one human coordinating two laptops, serialization (§3) is enough.
- **Stale branches:** `fedora` (1 stray commit from 2026-08-03) and `fedora-jing`
  (0 unique commits) were **deleted from origin on 2026-10-10** (user-approved).
  `fedora-frontend` is **deliberately kept** (user decision, 2026-10-10): its
  15 unique files are preserved byte-identically in
  `.sdd/archive/fedora-frontend-legacy/`, so a future deletion loses nothing —
  but it stays until the user says otherwise.
- Remotes: `origin` = `fjing03/CRS-fedora` (**only** push target, and only on the
  user's explicit word) · `upstream` = `FjingXR/class-replacement-system`
  (**pull-only, never push**) · the HP additionally has a stale `local` mirror
  (`/home/jinglinux/CRS-fedora-local.git`) that makes its `git status` show a
  confusing "ahead 164" — cosmetic only, nothing depends on it.

## 3. The three serialize rules (both machines)

1. **Pull before you start; push right after each verified commit.**
   An idle machine sits 0 commits from `origin`.
2. **Only one machine actively edits at a time.** Announce it in the session
   ("HP is mid-change, MSI is verify-only").
3. **Never leave uncommitted work parked.** Commit it, or park it on a throwaway
   `wip-<machine>-<date>` branch. A clean `git status` = machine idle.

After a pull that touched Blade views: run the two-command stale-cache restart
(§5). Push only on the user's explicit word — silence is never a go.

## 4. Database landscape (verified 2026-10-10)

| DB | MSI | HP |
|---|---|---|
| `class_replacement` | ⚠️ **stale legacy DB** (185 users, pre-`time_slots` schema) — *not* demo data; do not use, do not assume it is the demo. Left in place, harmless. | 🛡️ **protected demo** — output of the real-records import (2026-10-08). **Never** `migrate:fresh` / `db:seed` / `migrate:fresh --seed`. Restore = pristine dump only, recipe in `/home/jinglinux/tarumt/backups/README.md`. |
| `class_replacement_fresh` | ✅ working DB (the `.env` target); reproducible from the committed pipeline | not used |
| `class_replacement_testing` | tests — disposable (`migrate:fresh --seed` fine) | same |
| `test1` | stray legacy DB, ignore | — |

- **The dataset is reproducible, the DB state is not:** rebuild data with
  `php artisan crs:import-real-schedule --verify` (real records; the ONLY writer
  of real records on a demo DB) or `RealScheduleSeeder` (testing DB). Exact DB
  state is preserved only by dumps.
- Records-intact gate before/after any merge or data-touching change:
  `php artisan crs:db-row-counts`.
- **No cron on either machine** (checked 2026-10-10) — backups are manual; the
  first pristine dump of `class_replacement_fresh` was taken 2026-10-10 (see §6),
  take another after any milestone worth keeping.

## 5. Per-machine boot ritual

Two separate commands, never one combined string (the kill pattern would
self-match), never `pkill -9 php`:

```bash
pkill -f "[a]rtisan serve" || true
rm -f storage/framework/views/*.php && php artisan serve --port=8000 &
```

Playwright gates need the server on `:8000` (`playwright.config.ts` baseURL);
gate specs: `venue-db`, `timetable-wiring`, `nav-identity`, `pages-parity`
(login IDs/roles inside the specs; `CodingMAIN.md` has the run commands).
PHP checks: `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G`
· `php artisan test` (run the three separately — the bundled `composer run test`
crashes at step 2 on the 128M memory limit).

## 6. What lives outside the repo

- **MSI** — project parent folder `BMCS3404 PROJECT I (4)/`: `final/` (FYP
  portfolio + `FR&NFR.md`, cited by `CodingMAIN.md`), `knowledge/` (FYP1 writing
  requirements), cross-machine task prompts (`HANDOFF-TASK.md`, `VERIFY-TASK.md`,
  `SETUP-TASK.md`), the archived old `class-replacement-system` clone
  (read-only reference). · Backups: `/home/philler/Desktop/tarumt/backups/`
  (first pristine dump of `class_replacement_fresh`, 2026-10-10, + restore README).
- **HP** — `/home/jinglinux/tarumt/backups/` (pristine dump + restore README) ·
  `/home/jinglinux/CRS-fedora-local.git` (stale mirror) ·
  `/home/jinglinux/tarumt/CRS/class-replacement-system` (pull-only upstream
  clone) · `../final/` next to the repo (requirement sources).
