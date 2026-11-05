# Design — receipt-target-guard

## The guard contract (fail-closed, exact strings)
```
Required (every destructive spec, no defaults):
  CRS_BASE === 'http://127.0.0.1:8030'      # exact — rejects ':8000', 'localhost', ':8031'
  CRS_MODE === 'testing'
Additional, opt-in per spec (both write-capable suites carry the documented
`CRS_ALLOW_SQL=1` invocation in their headers, so both enable it):
  CRS_ALLOW_SQL === '1'
```
- WHY exact-string instead of the old `BASE.includes(':803')`: `includes` matches
  `:8030` but also anything containing ':803' (`:8031`…) and, worse, the island
  spec defaulted `BASE` to `http://localhost:8000`. Defaults are exactly the
  failure mode that produced the incident. Fail-closed = no default, no prefix.
- WHY the blessed string hardcodes `127.0.0.1`: the documented invocation for
  both suites has always been `CRS_BASE=http://127.0.0.1:8030` (both files'
  headers, unchanged since the notif lane). A typo'd variant now yields a loud
  box telling the operator the exact required string — ceremony over convenience,
  on purpose.

## New shared helper — `tests/helpers/receipt-target-guard.ts`
Three exports, one message builder:
- `isSanctionedTestingTarget(opts?: {requireAllowSql?: boolean}): boolean` — the pure check.
- `abortUnlessTestingTarget(label, opts?)` — MODULE scope: `console.error` box +
  `process.exit(1)` BEFORE any test runs (Playwright imports spec modules during
  collection, so the abort precedes the auth setup project, browser starts,
  logins, and SQL — the cheapest possible refusal).
- `throwUnlessTestingTarget(label, opts?)` — TEST-body scope: throws the same
  boxed message → Playwright red-fails THAT test only; its read-only siblings
  in the same file keep running; nothing clicks before the throw (first line).
- The failure box prints: what was found (all switches), what is required, and
  the full blessed invocation (the two `DB_DATABASE=class_replacement_testing`
  pre-commands + the playwright line) — the operator never has to guess.

## Why module-abort for 2 files but test-throw for the 2 mixed files
- The two write-capable specs are entirely destructive → module abort (the
  island file's memory-state — `sweepNotifIds` etc. — never even initialises).
  PLACEMENT LAW: the abort call is the FIRST statement after the imports —
  before any `test()` registration — because the old `test.skip(!ALLOWED, …)`
  lines evaluated during collection; a stale reference after the abort would
  crash collection with a ReferenceError instead of the clean guard box.
- `ui-regression.spec.ts` + `cancel-class.spec.ts` are mixed: ~20 read-only
  receipts + a handful of confirm-path writers. Module-aborting whole files
  would orphan legitimate :8000 read-only coverage for no gain. The throw sits
  as the FIRST line of each write-capable test, so the write path is unreachable
  at :8000 while everything else keeps its usual runs.
- Loud red by deliberate choice over `test.skip()`: skip is silent-ish (a green
  run with skipped rows is exactly how an operator misses a misfire); the
  incident rule says ABORT. A red test reads honestly: "this receipt refused
  to fire here".

## Interaction with existing house laws
- `--grep-invert "TC70"` remains the battery law (TC70 itself stays a retired
  comment; no behavioural change).
- The archived spot battery (25/25, 7 files) does not include either mixed
  file's write tests in its habit — but even if a future battery sweeps them,
  the guarded tests red-fail with the self-documenting box: correct behaviour.
- The old skip-style gates are REPLACED by the module abort — one mechanism,
  not two. Removal inventory (island file): THREE `test.skip(!ALLOWED, …)`
  lines (148 badge-lifecycle / 196 cancelled-row / 223 D15 — the last two
  shared the literal 'testing-DB-only receipt' text → replaceAll) and THREE
  `if (!ALLOWED) return;` hook gates (beforeAll/afterEach/afterAll); the
  conflict file: ONE skip line. Verified: zero `ALLOWED` references survive.
- OPERATOR EXPECTATION: full-directory `npx playwright test` now aborts at
  collection (the island module refuses) — fail-closed BY DESIGN; target
  FILES instead (the batteries' habit already does: explicit file lists +
  `--grep-invert "TC70"`).
- The five test-body throws show as PERMANENT REDS in any :8000 sweep of the
  two mixed files — deliberately honest ("this receipt refused to fire
  here"); never resolve them by un-guarding: run those receipts on :8030 or
  grep them out.
- `tests/Feature`/`tests/Unit` (phpunit) are out of scope: they run on the
  testing connection via phpunit.xml by construction.

## Rollout
1. Helper + the 2 module guards + the 5 test-body throws (T1–T2), one commit.
2. Receipts (T3): bare-run refusals ×2 + wrong-base refusal; `--list` on the
   mixed files; the full blessed invocation → both suites green on :8030
   (`DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`,
   dedicated serve `--port=8030` — the disposable DB only, never the demo).
3. Reviewer round (glm), review-log, status → applied (T4–T5), a second commit.

## Risk
- Playwright config `testDir: './tests'` pulls the new helper into collection
  only when imported — no stray collection.
- `localStorage.clear()`/`sessionStorage` hygiene in untouched specs — not in this lane.
