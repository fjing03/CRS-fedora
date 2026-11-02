# AGENTS.md — TARUMT Class Replacement System

This file lives in `prompts/for-ai-to-read/` since 2026-11-02; the root
`AGENTS.md` is a pointer that tells every session (including forked ones) to read
it. Paths below are relative to the repository root unless they start with `../`.

## Read first (every session)
- **`CodingMAIN.md`** — the single source of truth. Read it in full before any coding/design work.
  It contains: project overview, problem domain, architecture (5 objectives + slot state machine),
  tech stack & commands (incl. DB safety + stale-cache restart), domain model, RBAC matrix,
  **FR & NFR (§7)**, dataset, page inventory, git policy (§10.6), and **Coding Conventions §10** (incl. UI Design Rules §10.0).
- **`my-coding-rules.md`** (this folder) — the user's coding rules in quick always-read form (distilled from CodingMAIN §10: the 10 UI rules + canonical color tables, OOP/PHP/naming, commit/SDD policy); read it before writing or changing any code.
- Command Code memory import — the full rules are inlined into memory every turn via @path (other harnesses: read the file directly): `@prompts/for-ai-to-read/my-coding-rules.md`
- **`FR&NFR copy.md`** (this folder) — the requirements as the codebase sees them (Chapter 3 §3.4; **49 FRs / 26 NFRs**, version `v2026-11-02`, change log at the bottom). Change it only when forced, keep the numbering stable, and mirror every edit into `CodingMAIN.md` §7 + the report.
- **`docs/venue-restrictions.md`** — B005 no-Diploma REPEALED 2026-10-27 (Diploma cohorts allowed; SDD `b005-diploma-allowed`), B006 networking priority, P→labs. Read before any venue/booking work.
- `docs/` holds project docs; **`docs/archive/`** holds superseded ones (FYP-BRIEFING, BACKEND-TASKS, LEFTOVER_TASKS, old CodingMAIN variants) — do not treat archived files as current policy. Index: `docs/README.md`.

## Running /sdd-propose (or /sdd-apply) for a NEW UI frontend-only page
**MANDATORY:** before generating the proposal/design/tasks, the agent MUST read and follow:
[`../sdd-propose-ui-page.md`](../sdd-propose-ui-page.md)

That spec enforces, for every new UI page:
1. Color consistency — only `public/css/theme.css` CSS custom-property tokens; never hardcode hex/rgb.
2. Same name + same color for the same meaning — follow the canonical legend/status→color map in §10.0.
3. OOP concepts — `@extends('layouts.ui-template')`, `@include` partials, shared `theme.css` + `ui-common.js` + `mock-data.js`; no copy-paste.
4. **Mock data: read from `window.MockData` in `public/js/mock-data.js` (single source).** Never re-declare cohorts/lecturers/venues/semester or duplicate page datasets inline. New data → add ONE section to `mock-data.js`. Treat `MockData` as read-only; `slice()`/spread before mutating.
5. Minimise plain text, maximise icon buttons (reuse `resources/views/flux/icon/`).
6. Don't overwhelm — detail/secondary info goes in modals, not the page surface.
7. **Promote-on-3rd-duplication (DRY/OOP)** — if a markup block / CSS class / JS helper / mock-data slice ends up the SAME across 3+ pages, promote it to a shared file (Blade partial `resources/views/partials/`, `theme.css`, `ui-common.js`, or `mock-data.js`) and refactor the new page AND existing pages to use it. Record every promotion in the SDD `design.md` under "Promoted to shared".
8. Minimise steps — fewest clicks possible; pre-select defaults, don't hop pages for one task; if a flow exceeds ~3 clicks, rethink it.
9. Confirm critical actions (delete/submit/cancel/approve/reject) with a popup ("Are you sure?") so users dare to explore unfamiliar features and can always back out.

The agent must also read the closest existing template under `resources/views/ui-design-templates/`
and the matching `page-changelogs/*.md` to learn house style before designing.

## Standing rules (all tasks)
- **Current phase — read-only honesty:** timetable pages render real DB data; no booking/replacement writes until Wave 3b is explicitly green-lit. Cards show honest zeros — never fabricate pending/replacement counts.
- Reuse Flux/Livewire/Tailwind + `theme.css` + `ui-common.js`; no new dependencies.
- Conventional commits: `ui:`, `feat:`, `fix:`, `refactor:`, `oop:`, `style:`, `docs:`.
- Working branch: `fedora-backend` (push target `origin` = `fjing03/CRS-fedora.git` only; `upstream` is PULL-ONLY — see CodingMAIN.md §10.6). Never commit secrets. Never push unless the user explicitly says so.
- Before finishing a task that touches PHP: run `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G` (`composer run types:check` crashes at the default 128M memory limit).
- After any UI page change: update the matching `page-changelogs/*.md`.
- **After any UI Blade change:** clear stale cache before verifying — run TWO separate commands, never one combined string: first `pkill -f "[a]rtisan serve" || true`, then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`. Always kill old server first — old processes hold stale compiled views in memory. (Never `pkill -9 php` — it force-kills every PHP process on the machine. And never combine the kill and the `php artisan serve` start into one shell string — the kill pattern then matches the combined command's own wrapper and kills it.)
- DB is PostgreSQL (user `philler`). Demo DBs = `class_replacement_fresh` (:8000) + `class_replacement_demo` (:8001 twin) on CachyOS, `class_replacement` on the HP — **never re-seeded, never `migrate:fresh`**; real imported data, user-gated per write, logged in `docs/demo-db-data-changes.md`. **⛔ `--env=testing` trap:** no `.env.testing` exists — `--env=testing` on a destructive command silently boots `.env` = the demo DB. Select the testing DB ONLY via `DB_DATABASE=class_replacement_testing`. **Snapshot law:** verified `pg_dump -Fc` (`pg_restore --list` must pass) before any demo-DB write and after any demo-worthy milestone, into `/home/philler/Desktop/tarumt/backups/` (HP: `/home/jinglinux/tarumt/backups/`). Tests use the disposable `class_replacement_testing`, where `php artisan migrate:fresh --seed` is fine. **Schema migrations are additive-only on the demo DBs** (add a nullable column / index; never `change`/`drop`/data statements): verified dump → rehearse on `class_replacement_testing` → print the target → plain `migrate` on `class_replacement_fresh`, then `DB_DATABASE=class_replacement_demo …` → row-count receipt + log in `docs/demo-db-data-changes.md`. Restore + the canonical viva-state dump (`class_replacement_demo-post-recovery-2026-11-01.dump` = live on BOTH :8000/:8001 since 2026-11-01): `/home/philler/Desktop/tarumt/backups/README.md` (HP: `/home/jinglinux/tarumt/backups/README.md`).
