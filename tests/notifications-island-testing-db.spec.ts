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

/**
 * Fixture hygiene (notif-new-request-ux T7): every probe insert records its
 * REAL id here (INSERT ... RETURNING id) and afterAll deletes EXACTLY those
 * rows — request first, then the notifications pointing at it. This makes the
 * whole spec re-run-idempotent WITHOUT touch the settled app code: the
 * second run against the swept state is the idempotency receipt.
 */
const sweepNotifIds: number[] = [];
const sweepReqIds: number[] = [];
const FIXTURE_TITLE = 'PW island receipt';

function sql(query: string): void {
  // single-line the query: the sh -c hop must not carry literal \n into psql
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' });
}

/** Run a query and return its single-line stdout (for RETURNING ids). */
function sqlOut(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' }).toString().trim();
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
  const notifId = sqlOut(`INSERT INTO notifications (user_id, role_view, type, title, body, link, request_id, class_session_id, week, read_at, created_at, updated_at)
       VALUES (${userId}, 'lecturer', 'update', '${FIXTURE_TITLE} — a visible fixture row', 'BMSE3153 (P) · W14 · fixture row', '/my-request-history-ui', NULL, NULL, 14, NULL, now(), now())
       RETURNING id;`);
  sweepNotifIds.push(Number(notifId));
}

function insertCancelledLinkRow(userId: number): void {
  // a REAL cancelled request + a notification pointing at it — the D4
  // lifecycle's disabled render (any page: the one-feed panel shows it everywhere)
  const reqId = Number(sqlOut(`INSERT INTO replacement_requests (semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id, status, submitted_at, created_at, updated_at)
       VALUES (1, ${userId}, 23, 8, 32612, 'cancelled', now(), now(), now()) RETURNING id;`));
  sweepReqIds.push(reqId);
  const notifId = sqlOut(`INSERT INTO notifications (user_id, role_view, type, title, body, link, request_id, class_session_id, week, read_at, created_at, updated_at)
       VALUES (${userId}, 'lecturer', 'approved', '${FIXTURE_TITLE} — cancelled link', 'BMSE3153 (P) · W8 · fixture row', '/my-request-history-ui', ${reqId}, NULL, 8, NULL, now(), now())
       RETURNING id;`);
  sweepNotifIds.push(Number(notifId));
}

/**
 * beforeAll residue sweep for the test-3 probe family: a freshly seeded
 * testing DB holds ZERO replacement_requests and ZERO notifications, so any
 * leftover submitted probe row (title has no fixture marker) or cs23/w8
 * request here is residue from an older crashed run — never seed data.
 */
function sweepProbeResidue(): void {
  const uid = staffUserId();
  sql(`DELETE FROM notifications WHERE type = 'submitted' AND title = 'New replacement request' AND user_id = ${uid};`);
  sql(`DELETE FROM replacement_requests WHERE class_session_id = 23 AND week_number = 8 AND id NOT IN (SELECT request_id FROM notifications WHERE request_id IS NOT NULL);`);
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
  sweepProbeResidue();
});

/**
 * Per-test sweep: each test sweeps EXACTLY the rows it inserted (id-keyed),
 * so the next test starts from a ZERO mailbox — the badge assertions in the
 * lifecycle + D15 tests count their OWN fixtures alone. Playwright runs
 * afterEach (and afterAll) even when a test fails.
 */
test.afterEach(() => {
  if (!ALLOWED) return;
  // title-linked rows FIRST, then the exact-id sweeps catch the rows the
  // title family cannot see (the submitted probe row + its request).
  sweepFixtures();
  if (sweepReqIds.length) {
    sql(`DELETE FROM notifications WHERE request_id = ANY(ARRAY[${sweepReqIds.join(',')}]);`);
    sql(`DELETE FROM replacement_requests WHERE id = ANY(ARRAY[${sweepReqIds.join(',')}]);`);
  }
  if (sweepNotifIds.length) {
    sql(`DELETE FROM notifications WHERE id = ANY(ARRAY[${sweepNotifIds.join(',')}]);`);
  }
  sweepNotifIds.length = 0;
  sweepReqIds.length = 0;
});

test.afterAll(() => {
  if (!ALLOWED) return;
  sweepFixtures();
  sweepProbeResidue();
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
  // the rows never delete — flip the unread-only toggle to see them READ.
  // SDD notif-new-request-ux (T7 flip): the read STAMP only renders the read
  // class for the NON-submitted types (the `update` fixture rows) — the
  // submitted family rides the D15 chain (no submitted fixture here).
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
  // SDD notif-new-request-ux T7: the D1 exact cancelled string (the fixture's
  // OWN 'update' row escapes the D15 chain by type; the inactive rendition
  // here rides the link-row's request status set to CANCELLED by the fixture.
  // Note the INSERTED row's type='approved' rides the notif-click-highlight
  // both-clickable law — but the FIXTURE's request = CANCELLED and the row's
  // own link = /my-request-history-ui... the D15 chain applies to
  // submitted/awaiting types only — this receipts row stays the both-clickable
  // family: the INACTIVE span here comes from the legacy D4 law.)
  await expect(disabled).toHaveAttribute('data-tip', 'This notification is inactive because the request was cancelled by the requester.');
  await expect(disabled).not.toHaveAttribute('href');
  await expect(disabled).not.toHaveAttribute('data-id');
  // the active rows keep their anchors
  await expect(page.locator('#notifList a.notif-row')).toHaveCount(1);
});

test('SDD notif-new-request-ux: the decided submitted row goes inactive WITHOUT read_at; the badge drops; the D15 family renders', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt');
  const userId = staffUserId();
  // the fixture: a real pending request + a submitted notification for 5425.
  // RETURNING id captures EXACTLY the row this probe owns (upsert or insert);
  // the never-blanket UPDATE (previously WHERE class_session_id = 23 AND
  // week_number = 8) is GONE — a fresh seed leaves no other row to hit.
  const reqId = sqlOut(`INSERT INTO replacement_requests (semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id, status, submitted_at, created_at, updated_at)
       VALUES (1, (SELECT id FROM users u JOIN lecturers l ON l.user_id = u.id WHERE l.staff_id = '5425'), 23, 8, 32612, 'pending', now(), now(), now())
       ON CONFLICT (class_session_id, week_number) WHERE status IN ('pending','approved') DO UPDATE SET status = 'pending', submitted_at = now()
       RETURNING id;`);
  sweepReqIds.push(Number(reqId));
  sql(`UPDATE notifications SET request_id = NULL WHERE type = 'submitted' AND request_id IS NOT NULL AND request_id <> ${reqId} AND user_id = ${userId};`);
  sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, request_id, week, read_at, created_at, updated_at)
       VALUES (${userId}, 'pl', 'submitted', 'New replacement request', 'BMSE3153 (P) · W8 · submitted by 5425', '/request-approval-ui', ${reqId}, 8, NULL, now(), now());`);
  // a SECOND unpinned row keeps the list rendered before the decision
  insertLecturerRow(userId);

  await login(page);
  await page.goto(`${BASE}/request-approval-ui?bust=pw-t9`);
  await page.click('button.notif-btn');
  // the badge = 2 (the submitted row unread + the visible fixture)
  await expect(async () => {
    expect(await badgeNumber(page)).toBe(2);
  }).toPass({ timeout: 20_000 });

  // the DEcide: approve the request server-side (the lifecycle write)
  sql(`UPDATE replacement_requests SET status = 'approved' WHERE id = ${reqId};`);

  // the D15 classifier's predicate EXCLUDES inactive rows from the unread-only
  // list (the same literal predicate as the badge) — flip the toggle to make
  // the whole mailbox visible BEFORE the (up to) 10 s poll morph carries the
  // inactive span into the re-render (the Feature receipt's unreadOnly=false
  // contract, NotificationsBridgeTest :149/:286).
  await page.click('.notif-filters .toggle-wrapper');
  await page.waitForTimeout(600); // the wire:click re-render

  // the 10 s poll morph: up to 15 s covers ONE full poll cycle + render margin
  // (fixed timing of 1.8 s + the default 5 s could straddle two tick shots)
  const inactive = page.locator('#notifList span.notif-row--inactive');
  await expect(inactive).toHaveCount(1, { timeout: 15_000 });
  await expect(inactive).toHaveAttribute('data-tip', 'This notification is inactive because the request was approved.');
  const stamped = execSync(PSQL + JSON.stringify(`SELECT read_at FROM notifications WHERE request_id = ${reqId}`)).toString().trim();
  expect(stamped, 'read_at stays untouched for the inactive rows').toBe('');
  // the same morph derives the badge — but if the bell runs its own poll
  // cycle, wrap it in toPass too
  await expect(async () => {
    expect(await badgeNumber(page)).toBe(1, 'the fixture row carries the badge alone');
  }).toPass({ timeout: 15_000 });
});
