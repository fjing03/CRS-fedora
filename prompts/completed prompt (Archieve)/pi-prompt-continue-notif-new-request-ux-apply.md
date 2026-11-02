# PI Prompt — RESUME + CLOSE the `notif-new-request-ux` apply (T1–T9)

> Handoff for a NEW PI chat session (2026-11-01). The previous session
> stopped midway during the APPLY phase. Read this BEFORE the frozen
> artifacts. Work top-to-bottom; do not re-open settled rulings.

## Read order (first 4 are non-negotiable)
1. `AGENTS.md` (repo root) — standing rules, DB safety, stale-serve ritual.
2. `prompts/my-coding-rules.md` — the 10 UI rules + canon color tables.
3. `PI_Chat_Context_Handover.md` — §0.6 "SESSION ADDENDUM — 2026-11-01"
   is the resume state; skim §0.5 for prior context.
4. `.sdd/changes/notif-new-request-ux/` — `proposal.md` (D1–D15),
   `design.md` (§1–§9), `tasks.md` (frozen T1–T9), `explore-notes.md`
   (611-line file:line fact base).

## Current state — SETTLED, do not re-open
- All the SDD rulings (D1–D15), including:
  - D1 exact cancelled string: "This notification is inactive because the
    request was cancelled by the requester."
  - D15 reason-first copy family (cancelled > approved > rejected > read).
  - D5 badge = actionable-unread, batch-derived in PHP (NO COUNT subquery;
    read_at never stamped).
  - D6/D7 render-time composition — NO emitter/API/schema/backfill.
- T1–T8 CODE WORK IS DONE. It lives UNCOMMITTED in the working tree:
  - `app/Services/ReplacementRequestMapper.php` (T1 mapper surgery)
  - `app/Livewire/NavNotifications.php` (T2)
  - `resources/views/partials/notif-row.blade.php` +
    `resources/views/livewire/nav-notifications.blade.php` (T3+T4)
  - `public/css/theme.css` — `.notif-row-subtitle` (T5)
  - `tests/Feature/NotificationsBridgeTest.php` — 13/13 GREEN (T6)
  - `tests/notifications-island-testing-db.spec.ts` (T7) — NOT green
  - `tests/notifications-island.spec.ts` (T8) — 8/8 GREEN on :8000
- Verification already passed before the stop: pint + PHPStan
  (`vendor/bin/phpstan analyse --memory-limit=1G --no-progress`,
  errors:0) + `php artisan test` (277 passed) + the ONE-query law in
  `NotificationsBridgeTest` green. Re-run them, but the code itself is
  frozen — do NOT refactor settled code.

## WHERE THE PREVIOUS SESSION STUCK — your resume point
T7 (:8030, testing DB) Playwright re-run ended `1 failed` + `2 did not
run` on the badge-lifecycle test ("the decided submitted row goes
inactive WITHOUT read_at; the badge drops; the D15 family renders").
Cause: prior destructive probe runs left residue in the testing DB (a
pending request `class_session_id=23, week=8, slot=32612` + accumulated
notification rows), so the slot/week uniques and the badge counts are
not re-run-idempotent. This is FIXTURE hygiene, NOT an app-code bug —
the app code is settled and must not change.

## Work items (in order)

### 1. Clean the junk BEFORE anything else
Delete these untracked leftovers (they must NOT enter any commit):
- `tests/ProbeRace.php`
- `tests/tmp-explore.spec.ts`

### 2. Re-verify T7 with a CLEAN testing DB
- Lint the spec: `npx tsc --noEmit` equivalent — at minimum `node
  --check` is not applicable to TS; rely on the Playwright run.
- Keep `.env.testing` → `class_replacement_testing` (already set).
- Manually make the fixture idempotent-safety part of the SPEC
  hygiene if needed (test-side only): the spec's destructive inserts
  must `DELETE` their own rows (request + notifications) in a
  `afterEach`/`afterAll` sweep keyed by the exact ids they inserted.
- Then:
  1. start `:8030` fresh: `pkill -f "[a]rtisan serve" || true`, then
     `rm -f storage/framework/views/*.php`, then
     `DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &`
  2. `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`
  3. `CRS_MODE=testing CRS_ALLOW_SQL=1 CSRF_BASE` — correct env is:
     `CRS_BASE=http://127.0.0.1:8030 npx playwright test
     tests/notifications-island-testing-db.spec.ts --reporter=line`
  4. **Never run TC70** — always `--grep-invert "TC70"` on any Playwright
     run that could match it.
- Re-run TWICE to prove idempotency (the second run against the
  swept state is the actual proof).

### 3. Re-verify T8 (read-only demo, :8000)
`npx playwright test tests/notifications-island.spec.ts --reporter=line`
— expect 8/8. NO writes to `:8000` ever.

### 4. Gates (all four, fresh)
- `composer run lint:check`
- `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
- `php artisan test` (full suite; expect 277)
- `node --check public/js/ui-common.js` (it was touched this lane chain)

### 5. Commit ladder — conventional, T4 rides the same commit as T3
Suggested topology (adjust only if a file genuinely belongs elsewhere):
1. `oop: the replacement request mapper grows span-string helpers +
   a batched spans loader (notif-new-request-ux T1)` — mapper only.
2. `feat: the notifications island composes submitted rows at render
   time — subtitle, desc, the 5-row tip, actionable-unread badge
   (notif-new-request-ux T2–T4)` — NavNotifications + both blades +
   partial + noif CSS if inseparable; else CSS separate below.
3. `style: the notif row gains the subtitle line token styling
   (notif-new-request-ux T5)` — theme.css, if not inside #2.
4. `test: the bridge suite asserts the new composition + the D15
   fail-open matrix (notif-new-request-ux T6)` — NotificationsBridgeTest.
5. `test: the per-env receipts — the :8030 badge lifecycle + the :8000
   real-row composer receipt (notif-new-request-ux T7/T8)` — both specs.
Grouping that MUST hold: T3+T4 in ONE commit; changelog + T9 line in
the closing `docs:` commit:
6. `docs: the notifications panel changelog closes notif-new-request-ux
   (T9) — F-2 badge semantics + the D2 awaiting side-effect + the
   mapper promotion`

**NEVER `git push`.** The user pushes manually.

### 6. T9 — changelog entry (the closing task)
Append to `page-changelogs/notifications-panel-changelog.md`:
- F-2 badge semantics: the bell + pill carry the ACTIVEABLE unread
  total (unread AND non-inactive), batch-derived in PHP — one render,
  no subquery; described-request reads neutral.
- The D2 side-effect recorded as DELIBERATE: `awaiting` reminders on a
  later-decided request now inherit the decided-state copy via D15.
- Mapper promotion note: span-string helpers now live in
  `ReplacementRequestMapper` (`stringsFromSpan`, `spansForRequests`,
  `dayName`) so MyRequestHistory / RequestApproval can adopt them later.

### 7. Final report to the user
One summary block: what landed per task, the actual green receipts
(T7 re-run ×2, T8 8/8, suite 277, lint/phpstan output lines), the
commit hashes, and the push reminder (user pushes).

## Hard rules for this session
- B1-level English only in replies.
- NEVER push; NEVER migrate:fresh or write the demo DB `:8000` /
  `class_replacement_fresh`; only the testing DB gets `migrate:fresh --seed`.
- Do NOT re-open D1–D15 or refactor the settled T1–T8 code; the only
  legitimate code changes are fixture/spec hygiene + the changelog.
- TC70 must never run.
