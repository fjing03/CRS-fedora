/**
 * cancelled-slot-reuse-fixture — the shared fixture/action bridge for
 * tests/cancelled-slot-reuse.spec.ts (SDD cancelled-slot-reuse A20/A20b/A20c).
 *
 * It never writes a row itself. Everything routes through the guarded PHP
 * runner (tests/helpers/csr-fixture-runner.php), which:
 *   · is invoked ONLY with DB_DATABASE=class_replacement_testing in the child
 *     environment (no --env=testing — that would silently boot the demo DB);
 *   · queries the LIVE connection's current_database() BEFORE any write and
 *     refuses unless it answers 'class_replacement_testing';
 *   · prints one JSON receipt whose `db` key this helper re-checks before
 *     any value is used.
 *
 * Seams (all disclosed in the spec's test titles/comments too):
 *   · `seed`        — builds the isolated fixture and cancels the
 *                     withdraw/reject/auto-reject occurrences through the
 *                     app's REAL CancelClass action (exception + audit +
 *                     original release), never hand-shaped SQL;
 *   · `seed-pending`— the REAL SubmitReplacementRequest action from this
 *                     guarded testing-CLI (so the ending-lifecycle tests do
 *                     not re-click the submit UI three times);
 *   · `auto-reject` — the REAL AutoRejectRequest action with a frozen PHP
 *                     clock INSIDE that CLI process only. The scheduled
 *                     checker (requests:auto-reject, every 5 min) is NOT
 *                     changed, NOT stopped and NOT run here, and no UI clock
 *                     is frozen anywhere.
 *
 * The psql helper is read-only (house pattern from request-cancel-pending).
 */
import { execSync, spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { Browser, BrowserContext, Page } from '@playwright/test';

export const CSR_BASE = process.env.CRS_BASE as string; // guaranteed by the module guard

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const RUNNER = path.join('tests', 'helpers', 'csr-fixture-runner.php');
const TARGET_DB = 'class_replacement_testing';
const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';

export interface CsrWeek {
  week: number;
  date: string;
  slotIds: number[];
}

export interface CsrFixtureReceipt {
  db: string;
  marker: { module: string; venue: string; lecturerEmail: string };
  session: { id: number; day: number; start: string; end: string; type: string; durationSlots: number };
  lecturer: { userId: number; staffId: string; name: string };
  student: { userId: number; studentNo: string };
  cohort: { id: number; code: string; spaced: string };
  module: { id: number; code: string };
  venue: { id: number; code: string };
  semester: { id: number; weeks: number; noticeCutoff: string };
  weeks: Record<'approve' | 'withdraw' | 'reject' | 'autoReject', CsrWeek>;
}

export interface CsrPendingReceipt {
  db: string;
  requestId: number;
  week: number;
  anchorSlotId: number;
  span: number;
  junctionCount: number;
}

export interface CsrAutoRejectReceipt {
  db: string;
  ok: boolean;
  status: string;
  approverId: number | null;
  decidedAt: string | null;
  rejectionReason: string;
}

function runRunner(command: string, args: string[] = []): Record<string, unknown> {
  const res = spawnSync('php', [RUNNER, command, ...args], {
    cwd: ROOT,
    env: { ...process.env, DB_DATABASE: TARGET_DB },
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  if (res.status !== 0 || res.error) {
    throw new Error(
      `csr-fixture ${command} REFUSED/FAILED (exit ${res.status ?? 'spawn-error'}):\n`
      + `${res.stderr ?? ''}${res.error ? String(res.error) : ''}`,
    );
  }

  // the receipt is the LAST stdout line; earlier lines may carry PHP notices
  const jsonLine = (res.stdout ?? '')
    .split('\n')
    .map((l) => l.trim())
    .filter((l) => l.startsWith('{'))
    .pop();

  if (!jsonLine) {
    throw new Error(`csr-fixture ${command} printed no JSON receipt. stdout:\n${res.stdout ?? ''}`);
  }

  const parsed = JSON.parse(jsonLine) as Record<string, unknown>;

  /* the TS-side re-check of the runner's live current_database() receipt —
     the second half of the fail-closed pair */
  if (parsed.db !== TARGET_DB) {
    throw new Error(`csr-fixture ${command} answered db=[${String(parsed.db)}], not ${TARGET_DB}.`);
  }

  return parsed;
}

/** Cheap pre-flight: proves the runner's target gate before anything else. */
export function csrFixtureTarget(): void {
  runRunner('target');
}

/** Clean (marker-scoped) + build the isolated fixture + REAL pre-cancels. */
export function csrFixtureSeed(): CsrFixtureReceipt {
  return runRunner('seed') as unknown as CsrFixtureReceipt;
}

/**
 * Seed ONE pending request for a pre-cancelled fixture week through the REAL
 * SubmitReplacementRequest action (disclosed testing-CLI seam).
 */
export function csrSeedPending(week: number): CsrPendingReceipt {
  return runRunner('seed-pending', [String(week)]) as unknown as CsrPendingReceipt;
}

/**
 * Run the REAL AutoRejectRequest action on a fixture request with a frozen
 * PHP clock inside the CLI process only (disclosed seam — the live checker
 * stays untouched).
 */
export function csrAutoReject(requestId: number, frozenNow: string): CsrAutoRejectReceipt {
  return runRunner('auto-reject', [String(requestId), frozenNow]) as unknown as CsrAutoRejectReceipt;
}

/** One read-only scalar. House psql pattern (request-cancel-pending). */
export function sqlScalar(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return String(execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' })).trim();
}

export function sqlCount(query: string): number {
  return Number(sqlScalar(query));
}

export const FIXTURE_PASSWORD = 'Tarumt@2026';

/**
 * ONE UI login per identity per run (the Fortify limiter is 5/min per
 * login_id|login_type|ip) — the storage state is saved ONCE and every test
 * opens contexts from it. All four identities (fixture lecturer, fixture
 * student, the PL 5425, the unrelated outside staff 5770) log in exactly
 * once here, into their OWN .auth files — never the auth.setup project's
 * files, and never a second login inside a test.
 */
export async function loginOnce(
  browser: Browser,
  loginId: string,
  statePath: string,
  kind: 'staff' | 'student' = 'staff',
): Promise<void> {
  mkdirSync(path.dirname(statePath), { recursive: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 950 } });
  const page = await context.newPage();
  await page.goto(`${CSR_BASE}/login/${kind}`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.fill('#login_id', loginId);
  await page.fill('#password', FIXTURE_PASSWORD);
  await page.locator('#loginBtn').click();
  await page.waitForURL(/my-timetable|dashboard|student/, { timeout: 60_000 });
  await context.storageState({ path: statePath });
  await context.close();
}

/** A fresh context from a saved state + a console/pageerror collector. */
export async function openStaff(
  browser: Browser,
  statePath: string,
  viewport: { width: number; height: number } = { width: 1440, height: 950 },
): Promise<{ context: BrowserContext; page: Page; errors: string[] }> {
  const context = await browser.newContext({ storageState: statePath, viewport });
  const page = await context.newPage();
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('console', (m) => {
    /* network-level resource noise is not a page defect — pageerror + real
       console.error are (the venue-note-privacy house contract) */
    if (m.type() === 'error' && !/Failed to load resource/i.test(m.text())) {
      errors.push('console: ' + m.text());
    }
  });
  return { context, page, errors };
}
