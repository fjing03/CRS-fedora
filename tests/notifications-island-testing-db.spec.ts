import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * SDD notifications-real-bridge — the WRITABLE island receipts, run against
 * the DISPOSABLE testing DB (the lane's receipts strategy B), because the
 * demo DB is sacred (`class_replacement_fresh` gets hand-run seed rows only).
 *
 * REQUIRES the testing DB reseeded + a serve on :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/notifications-island-testing-db.spec.ts
 *
 * The guard SKIPS a bare run (no env pair) so a full sweep can never fire the
 * fixtures at the demo DB. House login = the :8030 staff 5425 (the seeding
 * persona). Two tests total: the Fortify limiter is 5/min per login_id+ip and
 * auth.setup.ts already spends a 5425 login per run.
 */

const BASE = process.env.CRS_BASE ?? 'http://localhost:8000';
const ALLOWED = process.env.CRS_MODE === 'testing'
  && process.env.CRS_ALLOW_SQL === '1'
  && BASE.includes(':803');

const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';
const FIXTURE_TITLE = 'PW island receipt';

function sql(query: string): void {
  // single-line the query: the sh -c hop must not carry literal \n into psql
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' });
}

/** The demo persona's user id in the testing DB (Surayaini, staff 5425). */
function staffUserId(staffId = '5425'): number {
  const out = execSync(
    PSQL + JSON.stringify(`SELECT u.id FROM users u JOIN lecturers l ON l.user_id = u.id WHERE l.staff_id = '${staffId}' LIMIT 1`),
  ).toString().trim();
  return Number(out);
}

function sweepFixtures(): void {
  // the cancelled-request fixture too — but ONLY the one a fixture
  // notification points at (never any pre-existing demo/seed row)
  sql(`DELETE FROM replacement_requests WHERE id IN (SELECT request_id FROM notifications WHERE title LIKE '${FIXTURE_TITLE}%' AND request_id IS NOT NULL);`);
  sql(`DELETE FROM notifications WHERE title LIKE '${FIXTURE_TITLE}%';`);
}

function insertLecturerRow(userId: number): void {
  sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, request_id, class_session_id, week, read_at, created_at, updated_at)
       VALUES (${userId}, 'lecturer', 'update', '${FIXTURE_TITLE} — visible on the lecturer tab', 'BMSE3153 (P) · W14 · fixture row', '/my-request-history-ui', NULL, NULL, 14, NULL, now(), now());`);
}

function insertCancelledLinkRow(userId: number): void {
  // a REAL cancelled request + a notification pointing at it — the D4
  // lifecycle's disabled render (lecturer tab: seen on the default pages)
  sql(`INSERT INTO replacement_requests (semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id, status, submitted_at, created_at, updated_at)
       VALUES (1, ${userId}, 23, 8, 32612, 'cancelled', now(), now(), now());`);
  const reqId = execSync(PSQL + JSON.stringify(`SELECT id FROM replacement_requests WHERE status = 'cancelled' ORDER BY id DESC LIMIT 1`)).toString().trim();
  sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, request_id, class_session_id, week, read_at, created_at, updated_at)
       VALUES (${userId}, 'lecturer', 'approved', '${FIXTURE_TITLE} — cancelled link', 'BMSE3153 (P) · W8 · fixture row', '/my-request-history-ui', ${reqId}, NULL, 8, NULL, now(), now());`);
}

async function login(page: Page): Promise<void> {
  await page.goto(`${BASE}/login/staff?_=pw-login`);
  await page.fill('#login_id', '5425');
  await page.fill('#password', 'Tarumt@2026');
  await page.click('#loginBtn');
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 30_000 });
}

async function badgeNumber(page: Page): Promise<number> {
  const badge = page.locator('#notifBadge');
  return (await badge.isVisible()) ? Number(await badge.textContent()) : 0;
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  if (!ALLOWED) return;
  sweepFixtures();
});

test.afterAll(() => {
  if (!ALLOWED) return;
  sweepFixtures();
});

test('the badge lifecycle: growth within a poll cycle, durable POST shrink, mark-all zero', async ({ page }) => {
  test.skip(!ALLOWED, 'run with CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 against the disposable testing DB');
  const userId = staffUserId();
  insertLecturerRow(userId); // badge = 1 before the page even loads

  await login(page);
  await page.goto(`${BASE}/my-timetable-ui?_=pw-t1`);
  await expect(page.locator('.notif-btn')).toBeVisible();
  expect(await badgeNumber(page)).toBe(1);

  // the no-reload sentinel
  await page.evaluate(() => { (window as any).__pwSentinel = true; });

  insertLecturerRow(userId); // a DB-side event mid-test

  // ≤1 poll cycle after the insert (wire:poll.10s)
  await expect(async () => {
    expect(await badgeNumber(page)).toBe(2);
  }).toPass({ timeout: 20_000 });
  expect(await page.evaluate(() => (window as any).__pwSentinel)).toBe(true, 'no reload happened');

  // the durable POST: click the newest unread row → native nav + keepalive POST
  await page.click('button.notif-btn');
  await page.locator('#notifList a.notif-row[data-id]').first().click();
  await page.waitForURL(/my-request-history-ui/);
  await page.goBack();
  await expect(async () => {
    expect(await badgeNumber(page)).toBe(1, 'the clicked row reads in the DB');
  }).toPass({ timeout: 20_000 });

  // mark-all (island action — Option A): zero the badge, keep the rows
  await page.click('button.notif-btn');
  await expect(page.locator('#notifMarkAll')).toBeVisible();
  await page.click('#notifMarkAll');
  await page.waitForTimeout(1200); // the wire:click re-render (the panel island)
  // the BELL badge refreshes on its own next poll cycle (≤10 s; the D3 law)
  await expect(async () => {
    expect(await badgeNumber(page)).toBe(0, 'mark-all zeroed the mailbox');
  }).toPass({ timeout: 20_000 });
  await expect(page.locator('#notifMarkAll')).toBeHidden();
  // the rows never delete — flip the unread-only toggle to see them READ
  await page.click('.notif-filters .toggle-wrapper');
  await expect(page.locator('#notifList a.notif-row--read').first()).toBeVisible();
});

test('a cancelled request renders the row disabled with the user tooltip', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt');
  const userId = staffUserId();
  insertLecturerRow(userId);    // 1 active unread row keeps the list rendered
  insertCancelledLinkRow(userId); // + the D4 disabled row

  await login(page);
  await page.goto(`${BASE}/my-timetable-ui?_=pw-t2`);
  await page.click('button.notif-btn');

  const disabled = page.locator('#notifList span.notif-row--inactive');
  await expect(disabled).toHaveCount(1);
  await expect(disabled).toHaveAttribute('data-tip', 'This notification is inactive because the request was cancelled.');
  await expect(disabled).not.toHaveAttribute('href');
  await expect(disabled).not.toHaveAttribute('data-id');
  // the active rows keep their anchors
  await expect(page.locator('#notifList a.notif-row')).toHaveCount(1);
});
