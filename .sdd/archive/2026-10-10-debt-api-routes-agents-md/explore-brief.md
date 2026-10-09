# Explore Brief — debt-api-routes-agents-md

Date: 2026-10-10. Mode: **micro-SDD** (user-approved option 1) — proposal covers scope + tasks; design/specs intentionally omitted for this ~9-line change.

## Fact base (verified)

1. **AGENTS.md line 37** instructs agents to clear stale caches with `pkill -9 php && rm -f …` — force-kills every PHP process on the machine and has been actively worked around all session (safe pattern: isolated `pkill -f "[a]rtisan serve" || true`). One-line doc fix.
2. **`routes/api.php`** registers 8 GET endpoints under the `v1` prefix (semester, meta/cohorts, timetable/my, timetable/cohort, timetable/student, arrangement/slots, requests/my, requests/conflicts) → `Api\Http\Controllers\Api\ApiReadController`. Codebase-wide grep: **zero production callers** (app/, resources/, public/js, Livewire, gated tests). The only reference anywhere is `tests/e2e/api-wiring.spec.js` — a **legacy e2e suite** the user already ruled "do nothing" (not in any gate list).
3. `bootstrap/app.php` registers `routes/api.php` (line 19) and has an unrelated `api/*` middleware exception (line 36) — both stay untouched; an empty routes file is harmless.
4. User instruction: **comment out, do not delete** — so re-enabling (e.g. when the booking write path needs an API) is uncommenting, and the intent is visible in the file itself.
5. Worktree clean at `412302c`; UI work parked (user: "wait UI stable").

## Consequence accepted

`tests/e2e/api-wiring.spec.js` would fail if anyone ever runs it — accepted: legacy suite, outside all gates, user decision stands.
