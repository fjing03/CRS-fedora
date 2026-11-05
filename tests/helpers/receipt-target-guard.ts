/**
 * receipt-target-guard — THE STANDING GUARD from the 2026-11-01 DATA-LOSS
 * INCIDENT (docs/demo-db-data-changes.md, top entry): the notif-new-request-ux
 * T7 fixture family wrote directly onto the demo DB (class_replacement_fresh,
 * :8000) and wiped the user's Oct-31 submissions. Every destructive
 * spec/fixture MUST abort unless it provably targets the disposable testing DB.
 *
 * Fail-closed contract (exact strings — no localhost, no :8000, no defaults):
 *   CRS_BASE === 'http://127.0.0.1:8030'   (the dedicated testing serve)
 *   CRS_MODE === 'testing'
 *   + CRS_ALLOW_SQL === '1' for the SQL-fixture specs (requireAllowSql option)
 *
 * Two refusal scopes:
 *   - abortUnlessTestingTarget(label, opts?) → MODULE level: console.error
 *     box + process.exit(1) BEFORE any test runs (Playwright imports spec
 *     modules during collection, so the refusal precedes the auth setup
 *     project, browsers, logins, and SQL). Use in files whose every test writes.
 *   - throwUnlessTestingTarget(label, opts?) → FIRST LINE of a single
 *     destructive test inside a mixed read/write file: Playwright red-fails
 *     THAT test only; its read-only siblings keep their usual runs; nothing
 *     clicks before the throw. Loud red by design — a silent skip is how a
 *     misfire gets missed.
 *
 * House law, restated: no receipt work touches :8000 / class_replacement_fresh
 * ever again — read-only only, from agents. (AGENTS.md TC70 law unchanged.)
 */

const REQUIRED_BASE = 'http://127.0.0.1:8030';

export interface GuardOptions {
  /** SQL-fixture specs additionally require CRS_ALLOW_SQL=1. */
  requireAllowSql?: boolean;
}

/** True only when every required switch proves the disposable testing target. */
export function isSanctionedTestingTarget(opts: GuardOptions = {}): boolean {
  if (process.env.CRS_MODE !== 'testing') return false;
  if (process.env.CRS_BASE !== REQUIRED_BASE) return false;
  if (opts.requireAllowSql && process.env.CRS_ALLOW_SQL !== '1') return false;
  return true;
}

/** The exact blessed invocation, printed verbatim by both refusal modes. */
export const GUARD_INVOCATION = [
  '  DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed',
  '  DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &',
  '  CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \\',
  '    npx playwright test <the-spec>.spec.ts',
].join('\n');

function guardFailure(label: string, opts: GuardOptions = {}): string {
  const found = [
    `CRS_MODE=${process.env.CRS_MODE ?? '<unset>'}`,
    `CRS_BASE=${process.env.CRS_BASE ?? '<unset>'}`,
    opts.requireAllowSql ? `CRS_ALLOW_SQL=${process.env.CRS_ALLOW_SQL ?? '<unset>'}` : null,
  ].filter(Boolean).join('  ');
  return `⛔ receipt-target-guard REFUSED: ${label}\n`
    + `  Found:    ${found}\n`
    + `  Required: CRS_MODE=testing AND CRS_BASE=${REQUIRED_BASE}`
    + `${opts.requireAllowSql ? ' AND CRS_ALLOW_SQL=1' : ''}\n`
    + `  This spec writes DB rows. The demo DB (class_replacement_fresh, :8000)\n`
    + `  has been read-only for agents since the 2026-11-01 wipe — see\n`
    + `  docs/demo-db-data-changes.md. Target the disposable testing DB instead:\n`
    + `${GUARD_INVOCATION}`;
}

/** MODULE-level abort: loud console.error box + exit 1 before anything runs. */
export function abortUnlessTestingTarget(label: string, opts: GuardOptions = {}): void {
  if (isSanctionedTestingTarget(opts)) return;
  console.error(guardFailure(label, opts));
  process.exit(1);
}

/** TEST-body refusal: throw → Playwright red-fails THIS test only; nothing ran. */
export function throwUnlessTestingTarget(label: string, opts: GuardOptions = {}): void {
  if (isSanctionedTestingTarget(opts)) return;
  throw new Error(guardFailure(label, opts));
}
