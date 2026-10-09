# Proposal — debt-api-routes-agents-md (micro)

Mode: micro-SDD (user-approved). This proposal carries scope + tasks; design/specs omitted — the change is ~9 lines across 2 files. Reviewer: please review this single artifact.

## Why

Two deferred debts, both zero-risk cleanups explicitly ordered by the user:

1. **AGENTS.md teaches a harmful command.** Line 37 instructs agents to restart the dev server with `pkill -9 php` — force-kills every PHP process (can corrupt the dev server, kills unrelated processes). Every session this session had to work around it with the safe isolated pattern. Fixing the doc protects all future agents.
2. **8 orphaned API endpoints.** `routes/api.php` serves 8 `GET /api/v1/*` endpoints (`ApiReadController`) with **zero production callers** — the only references are two **legacy e2e specs**, `tests/e2e/api-wiring.spec.js` and `tests/e2e/full-suite.spec.js`, both outside all gates and "do nothing" per user decision. Dead routes = confusion + needless attack surface. User instruction: **comment out, don't delete**.

## Scope + tasks

### T1 — AGENTS.md line 37 fix
Replace the kill command with the battle-tested safe pattern, keeping the same one-line structure and intent:
`pkill -9 php` → `pkill -f "[a]rtisan serve" || true` (rest of the line — `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &` and the stale-views rationale — unchanged).

### T2 — comment out `routes/api.php` endpoints
- Comment out all 8 `Route::get(...)` lines **and** the `Route::prefix('v1')` group wrapper (balanced braces keep the file valid PHP).
- Comment out BOTH `use` lines as well (`App\Http\Controllers\Api\ApiReadController` and `Illuminate\Support\Facades\Route`) with a one-line "uncomment together with the routes below" note — otherwise `lint:check` (pint `laravel` preset, `no_unused_imports`) fails on the dead imports.
- Add a short comment block explaining: endpoints orphaned 2026-10 (no production callers), commented not deleted for easy re-enable when the booking write path needs an API, see SDD archive `debt-api-routes-agents-md`.
- Do NOT touch: `bootstrap/app.php` (registration stays — empty routes file is harmless), `ApiReadController.php` (class stays).

### T3 — verify + commit
- Gates: `composer run lint:check`, `composer run types:check` (phpstan), `php artisan test` (full suite — must stay ≥121 green).
- Confirm no gated spec hits `/api/v1` (legacy `api-wiring.spec.js` + `full-suite.spec.js` do, but are outside all gates; re-check post-edit that no PHP test or gated spec does).
- Single commit: `chore(debt): comment out 8 orphaned api/v1 routes; fix AGENTS.md pkill -9 line`.
- No push without explicit authorization.

## Out of scope

- Deleting anything (user: comment only).
- `ApiReadController.php` removal, `bootstrap/app.php` changes.
- `tests/e2e/api-wiring.spec.js` + `tests/e2e/full-suite.spec.js` — legacy, do nothing (would fail if run; accepted).
- Any UI work (parked: "wait UI stable").

## Success criteria

- AGENTS.md no longer instructs `pkill -9 php`; safe pattern documented instead.
- All 8 endpoints commented in `routes/api.php`; file remains valid PHP; routes vanish from `php artisan route:list`.
- All gates green; production behaviour unchanged (nothing called these endpoints).
