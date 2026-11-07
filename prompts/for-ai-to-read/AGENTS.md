# AGENTS.md — TARUMT Class Replacement System

This file lives in `prompts/for-ai-to-read/` since 2026-11-02; the root
`AGENTS.md` is a pointer that tells every session (including forked ones) to read
it. Paths below are relative to the repository root unless they start with `../`.

## Read first (every session)
- **`CodingMAIN.md`** — the single source of truth. Read it in full before any coding/design work.
  It contains: project overview, problem domain, architecture (5 objectives + slot state machine),
  tech stack & commands (incl. DB safety + stale-cache restart), domain model, RBAC matrix,
  **FR & NFR (§7)**, dataset, page inventory, git policy (§10.6), and **Coding Conventions §10** (incl. UI Design Rules §10.0).
- **`my-coding-rules.md`** (this folder) — the user's coding rules in quick always-read form (distilled from CodingMAIN §10: the 11 UI rules + canonical color tables, OOP/PHP/naming, commit/SDD policy); read it before writing or changing any code.
- Command Code memory import — the full rules are inlined into memory every turn via @path (other harnesses: read the file directly): `@prompts/for-ai-to-read/my-coding-rules.md`
- **`FR&NFR copy.md`** (this folder) — the requirements as the codebase sees them (Chapter 3 §3.4; **49 FRs / 26 NFRs**, version `v2026-11-02`, change log at the bottom). **Editable only with the user's explicit approval** — never amend, renumber or "fix" a requirement on your own initiative; report the mismatch and ask, then mirror any approved edit into `CodingMAIN.md` §7 + the report.
- **`docs/venue-restrictions.md`** — B005 no-Diploma REPEALED 2026-10-27 (Diploma cohorts allowed; SDD `b005-diploma-allowed`), B006 networking priority, P→labs. Read before any venue/booking work.
- `docs/` holds project docs; **`docs/archive/`** holds superseded ones (FYP-BRIEFING, BACKEND-TASKS, LEFTOVER_TASKS, old CodingMAIN variants) — do not treat archived files as current policy. Index: `docs/README.md`.
- **`prompts/general&reusable prompt references/`** — the **toolkit**: paste-ready,
  project-agnostic or per-task *templates* only. Five files, and what each is for:
  `PI-PROMPT-TEMPLATE.md` (system-wide paste-ready prompt with a task card),
  `sdd-propose-template.md` (a new UI frontend-only page), `sdd-propose-backend-template.md`
  (a new backend feature), `sdd-propose-ui-page.md` (the **spec** the UI proposals must
  follow — auto-referenced below), `context-transfer-prompt.md` (hand a session's context to
  a fresh AI). **What does not belong there:** project *state*. An SDD tracker, a lane list,
  a changelog or a status file is not a reusable prompt and must not live in this folder —
  that truth lives in `.sdd/changes/`, `.sdd/archive/` and `page-changelogs/`. (A
  hand-maintained `sdd.md` tracker was moved in on 2026-11-03 and moved back out the same
  day for exactly that reason.)
- **`prompts/extra tasks/`** — parked, low-priority tasks for spare time. A file sitting there is **not** an instruction: never start one unless the user names it. Parked ≠ done, so never archive them silently; when one ships, move it to `prompts/completed prompt (Archieve)/` with an evidence row and ask first.

## Running /sdd-propose (or /sdd-apply) for a NEW UI frontend-only page
**MANDATORY:** before generating the proposal/design/tasks, the agent MUST read and follow:
[`../general&reusable prompt references/sdd-propose-ui-page.md`](<../general&reusable prompt references/sdd-propose-ui-page.md>)

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
- **Current phase — the write path is live:** timetable pages render real DB data, and submit
  (OCC-enforced), approve/reject, class cancel and pending-request cancel are all shipped; only the
  `/api/v1` surface is still parked (commented out in `routes/api.php`). Cards show the real counts
  that come from the payload — never fabricate a pending/replacement number.
  **Cancel Class is FINAL since 2026-11-05** — undo was removed from the UI, the JS and the server
  (`App\Concerns\CancelsClasses::cancelClassUndo()` no longer exists), so never re-offer it. Every
  final decision's confirm modal carries the shared sentence
  `partials.ui-irreversible-line` → *"This action cannot be UNDONE."*
  **Auto-reject since 2026-11-06:** a PENDING request whose proposed start is within (or exactly)
  **3 working hours** is rejected by the system — working time = TARUMT Sabah hours, Mon–Fri
  08:30–17:30, weekends and the semester's `holidays` rows excluded. `App\Support\WorkingHours`
  owns that clock; `php artisan requests:auto-reject` (scheduled every 5 min, manual in dev,
  `--dry-run` prints without writing) is the ONLY trigger — never sweep from a page render (that
  would write on GET against the demo DB), and never touch a non-pending row.
  **FR 2.17 rides the same clock on the server:** `SubmitReplacementRequest` refuses a slot whose
  date is inside the 3-working-day notice window (`slot_too_soon`) **and** a target that lands on a
  public holiday (`holiday`, the D2 rule) — a rule the page enforces must exist in the action too,
  never only in JavaScript.
- **A cancelled request displays as "no active request" (2026-11-06):** on My Replacements an
  owner-cancelled occurrence reads **Need to Arrange** with an em-dash REQUESTED REPLACEMENT cell and
  no Proposed Replacement Schedule tab, and the row pulses **yellow** on the cancel
  (`flashRowPulse(el, 'warning')` — the one flash mechanic in `ui-common.js`, never a second
  animation). The RECORD is never rewritten (still `cancelled`, audited, slots released), the status
  filter has no Cancelled option any more, and a filter saved before its removal maps onto Need to
  Arrange.
- **Notification row tooltips have ONE vocabulary (2026-11-06):**
  `app/Support/Notifications/NotificationTip.php` composes them for every live kind (Subject / schedules / reasons / `Request ID`;
  holiday-first reason in the Original line; `Automatically Rejected by System` when
  `approver_id IS NULL`; students without the Staff lines). Don't hand-write a tip in a page.
- **A JS-owned control must survive a Livewire round-trip:** a page component's re-render morphs the
  DOM from the server's HTML, so anything JS fills (the week selects, `#filterChips`) is wiped unless
  guarded — `withWeekLivewireGuard()` in `ui-common.js` brackets `pageLivewire().call()` (2026-11-06).
- Reuse Flux/Livewire/Tailwind + `theme.css` + `ui-common.js`; no new dependencies.
- Conventional commits: `ui:`, `feat:`, `fix:`, `refactor:`, `oop:`, `style:`, `docs:`.
- Working branch: `fedora-backend` (push target `origin` = `fjing03/CRS-fedora.git` only; `upstream` is PULL-ONLY — see CodingMAIN.md §10.6). Never commit secrets. Never push unless the user explicitly says so.
- Before finishing a task that touches PHP: run `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G` (`composer run types:check` crashes at the default 128M memory limit).
- After any UI page change: **create or update** the matching `page-changelogs/<page>-changelog.md` (append a dated entry, newest first; create the file when the page has none).
- **After any UI Blade change:** restart through **`scripts/dev-serve.sh [port]`** (default `8000`) — it kills that port, clears compiled views and starts with `revalidate_freq=0`, which is the only reliable cure for the OPcache trap (`opcache.enable_cli=On`, `revalidate_freq=180` → a long-running serve can return old bytecode for ~3 minutes). Never `pkill -9 php` (it force-kills every PHP process on the machine) and never combine the kill and the `php artisan serve` start into one shell string — the kill pattern then matches the combined command's own wrapper and kills it. Running through `scripts/dev-serve.sh` is the sanctioned way; if you must do it by hand, use two separate commands (`pkill -f "[a]rtisan serve" || true`, then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`).
- DB is PostgreSQL (user `philler`). Demo DBs = `class_replacement_fresh` (:8000) + `class_replacement_demo` (:8001 twin) on CachyOS, `class_replacement` on the HP — **never re-seeded, never `migrate:fresh`**; real imported data, user-gated per write, logged in `docs/demo-db-data-changes.md`. **⛔ `--env=testing` trap:** no `.env.testing` exists — `--env=testing` on a destructive command silently boots `.env` = the demo DB. Select the testing DB ONLY via `DB_DATABASE=class_replacement_testing`. **Snapshot law:** verified `pg_dump -Fc` (`pg_restore --list` must pass) before any demo-DB write and after any demo-worthy milestone, into `/home/philler/Desktop/tarumt/backups/` (HP: `/home/jinglinux/tarumt/backups/`). Tests use the disposable `class_replacement_testing`, where `php artisan migrate:fresh --seed` is fine. **Schema migrations are additive-only on the demo DBs** (add a nullable column / index; never `change`/`drop`/data statements): verified dump → rehearse on `class_replacement_testing` → print the target → plain `migrate` on `class_replacement_fresh`, then `DB_DATABASE=class_replacement_demo …` → row-count receipt + log in `docs/demo-db-data-changes.md`. Restore + the canonical viva-state dump (`class_replacement_demo-post-recovery-2026-11-01.dump` = live on BOTH :8000/:8001 since 2026-11-01): `/home/philler/Desktop/tarumt/backups/README.md` (HP: `/home/jinglinux/tarumt/backups/README.md`).
- **Ports (do not mix them up):** `:8000` = the dev server on `class_replacement_fresh`; `:8001` = the demo twin (`CRS-demo` clone, its own viva DB); **`:8030` = the disposable AI/E2E self-test lane** — `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`, used only by automated/Playwright checks, **never pointed at a demo DB**, and killed when the run ends.
- **Playwright receipts that WRITE are guarded fail-closed** (the 2026-11-01 DATA-LOSS incident's standing lock, lane `receipt-target-guard`): `tests/helpers/receipt-target-guard.ts` exposes `abortUnlessTestingTarget(specPath, { requireAllowSql })` — called as the FIRST statement after a spec's imports, so a bare run aborts at collection — and `throwUnlessTestingTarget(name)` as the FIRST line of a single dangerous test. Both require the EXACT strings `CRS_MODE=testing`, `CRS_BASE=http://127.0.0.1:8030` and (for SQL fixtures) `CRS_ALLOW_SQL=1`; there are no defaults and no soft skips, so a missing flag is a loud exit-1 refusal that prints the blessed invocation. The blessed form: `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test <spec>`. Never "fix" a refusal by loosening the helper.
