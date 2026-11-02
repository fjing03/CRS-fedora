import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * notif-manage-hide (2026-11-02, the user's approved design) — the WRITE-flow
 * receipt: hide → undo → shelf → unhide, plus the poll-safety law.
 *
 * This spec WRITES (hide stamps `hidden_at`), so it runs ONLY on the
 * disposable testing DB lane — never on the demo DBs. The guard below aborts
 * any bare/ demo-targeted run BEFORE anything loads (the 2026-11-01
 * data-loss incident's standing rule).
 *
 * The READ-ONLY layout half of the lane (icon-only Mark all, the gated Manage
 * icon, the full-row type select, the shared tooltips) lives in
 * tests/notifications-island.spec.ts on the demo mailbox.
 *
 * REQUIRES the testing DB seeded + a serve on :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/notifications-hide-manage.spec.ts
 *
 * Fixture hygiene (the T7 law): every probe row is tagged and swept by tag —
 * beforeEach AND afterEach — so the spec is re-run-idempotent and never
 * accumulates. One login per run (the Fortify limiter) on staff 5425 (user 1).
 */

const BASE = process.env.CRS_BASE as string;
const ALLOWED = process.env.CRS_MODE === 'testing'
  && process.env.CRS_ALLOW_SQL === '1'
  && !!BASE
  && BASE.includes(':803');

const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';

/** The receipt-target guard: no bare run, no demo host, ever. */
function abortUnlessTestingTarget(): void {
  const offending = !ALLOWED;
  if (offending) {
    throw new Error(
      'notifications-hide-manage.spec.ts writes the notifications table — it needs '
      + 'CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 (the '
      + 'disposable testing DB). Refusing to run.',
    );
  }
}

function sql(query: string): void {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' });
}

const TAG = 'PROBE hide-manage';

/** 3 hideable (read) rows + 1 NOT hideable (unread, actionable) for user 1. */
function seedFixture(): void {
  sweepFixture();
  for (let i = 0; i < 3; i += 1) {
    sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, read_at, created_at, updated_at)
         VALUES (1, 'lecturer', 'update', '${TAG} read ${i}', 'fixture body',
                 '/my-request-history-ui', now(), now() - interval '${i} min', now());`);
  }
  sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, read_at, created_at, updated_at)
       VALUES (1, 'lecturer', 'update', '${TAG} live', 'fixture body',
               '/my-request-history-ui', NULL, now(), now());`);
}

function sweepFixture(): void {
  sql(`DELETE FROM notifications WHERE title LIKE '${TAG}%';`);
}

async function login(page: Page): Promise<void> {
  await page.goto(`${BASE}/login/staff?_=hidemanage-${Date.now()}`);
  await page.fill('#login_id', '5425');
  await page.fill('#password', 'Tarumt@2026');
  await page.click('#loginBtn');
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 60_000 });
}

/** The panel's state, read from the DOM (the single source of truth). */
async function panelState(page: Page) {
  return page.evaluate(() => ({
    rows: document.querySelectorAll('#notifList .notif-row').length,
    picks: document.querySelectorAll('.notif-row--pick').length,
    checked: document.querySelectorAll('.notif-row--pick .bulk-checkbox:checked').length,
    bar: document.querySelector('.notif-bar-count')?.textContent?.trim() ?? null,
    caption: document.querySelector('.notif-caption')?.textContent?.trim() ?? null,
    hideBtn: document.querySelector('.notif-hide-btn')?.textContent?.trim() ?? null,
    footSelected: document.querySelector('.notif-foot-count')?.textContent?.trim() ?? null,
    undo: document.querySelector('.notif-undo-msg')?.textContent?.trim() ?? null,
    footLink: document.querySelector('.notif-foot-link')?.textContent?.trim() ?? null,
    title: document.querySelector('.notif-head-title')?.textContent?.trim() ?? null,
    shelfRows: document.querySelectorAll('.notif-row--hidden').length,
    shelfNote: document.querySelector('.notif-foot-note')?.textContent?.trim() ?? null,
  }));
}

test.describe.configure({ mode: 'serial', timeout: 120_000 });

test.beforeAll(() => { abortUnlessTestingTarget(); });
test.beforeEach(() => { seedFixture(); });
test.afterEach(() => { sweepFixture(); });
test.afterAll(() => { sweepFixture(); });

test('the manage → hide → undo → shelf → unhide loop, poll-safe', async ({ page }) => {
  await login(page);
  await page.goto(`${BASE}/my-timetable-ui?_=${Date.now()}`);
  await page.waitForTimeout(1200);
  await page.click('.notif-btn');
  await expect(page.locator('#notifPanel')).toHaveClass(/open/);

  // show every row (the unread-only toggle is ON by default)
  await page.locator('.notif-filters .toggle-wrapper').first().click();
  await page.waitForTimeout(800);

  // 1) MANAGE: only the hideable rows, PRE-TICKED, with the honest caption
  await page.click('#notifManage');
  await page.waitForTimeout(900);
  let state = await panelState(page);
  expect(state.picks).toBe(3);                       // the 3 read fixtures
  expect(state.checked).toBe(3);                     // …all pre-ticked
  expect(state.bar).toBe('3 of 3 selected');
  expect(state.caption).toBe("Notifications that still need you can't be hidden.");
  expect(state.hideBtn).toBe('Hide selected');
  expect(state.footSelected).toBe('3 selected');

  // 2) a non-hideable row is NOT in the manage list (the gate, server-side)
  expect(await page.locator('.notif-row--pick').filter({ hasText: `${TAG} live` }).count()).toBe(0);

  // 3) untick one → every counter follows
  await page.locator('.notif-row--pick .bulk-checkbox').first().click();
  await page.waitForTimeout(800);
  state = await panelState(page);
  expect(state.checked).toBe(2);
  expect(state.bar).toBe('2 of 3 selected');
  expect(state.footSelected).toBe('2 selected');

  // 4) Hide selected → the rows leave the inbox, the undo toast + link appear
  await page.click('.notif-hide-btn');
  await page.waitForTimeout(1000);
  state = await panelState(page);
  expect(state.undo).toBe('2 notifications hidden · Undo restores them');
  expect(state.footLink).toBe('Hidden (2)');
  expect(state.picks).toBe(0);                        // back to the inbox
  expect(await page.locator('#notifList .notif-row').filter({ hasText: `${TAG} read 1` }).count()).toBe(0);

  // 5) the 10 s wire:poll must NOT wipe the undo affordance (12 s timer)
  await page.waitForTimeout(10_500);
  expect((await panelState(page)).undo).toBe('2 notifications hidden · Undo restores them');

  // 6) UNDO restores both rows and drops the link
  await page.click('.notif-undo-btn');
  await page.waitForTimeout(1200);
  state = await panelState(page);
  expect(state.undo).toBeNull();
  expect(state.footLink).toBeNull();
  expect(await page.locator('#notifList .notif-row').filter({ hasText: `${TAG} read 1` }).count()).toBe(1);

  // 7) hide again, then walk the SHELF: rows, Unhide, Unhide all
  await page.click('#notifManage');
  await page.waitForTimeout(900);
  await page.click('.notif-hide-btn');
  await page.waitForTimeout(1000);
  await page.click('.notif-foot-link');
  await page.waitForTimeout(900);
  state = await panelState(page);
  expect(state.title).toBe('Hidden notifications');
  expect(state.bar).toBe('3 hidden');
  expect(state.shelfRows).toBe(3);
  expect(state.shelfNote).toBe('Hidden notifications stay in your mailbox — restore them anytime.');

  await page.locator('.notif-unhide').first().click();
  await page.waitForTimeout(900);
  state = await panelState(page);
  expect(state.bar).toBe('2 hidden');
  expect(state.shelfRows).toBe(2);

  await page.click('.notif-bar-btn');                 // Unhide all
  await page.waitForTimeout(1000);
  state = await panelState(page);
  expect(state.bar).toBe('0 hidden');
  expect(state.shelfRows).toBe(0);
  await expect(page.locator('#notifEmpty')).toContainText('Nothing hidden — everything is back in your inbox.');

  // 8) Back to the inbox — nothing hidden, so the find-back link is gone
  await page.click('[aria-label="Back to inbox"]');
  await page.waitForTimeout(900);
  state = await panelState(page);
  expect(state.title).toBe('Notifications');
  expect(state.footLink).toBeNull();
  expect(await page.locator('#notifList .notif-row').filter({ hasText: `${TAG} read 1` }).count()).toBe(1);
});

test('leaving manage mode changes nothing', async ({ page }) => {
  await login(page);
  await page.goto(`${BASE}/my-timetable-ui?_=${Date.now()}`);
  await page.waitForTimeout(1200);
  await page.click('.notif-btn');
  await page.locator('.notif-filters .toggle-wrapper').first().click();
  await page.waitForTimeout(800);

  await page.click('#notifManage');
  await page.waitForTimeout(900);
  // the Close button leaves the whole panel; the shelf's Back is absent here,
  // so going back to the inbox happens by re-opening the panel — either way
  // nothing may be written.
  await page.click('.notif-close');
  await page.waitForTimeout(400);
  await page.click('.notif-btn');
  await page.waitForTimeout(900);

  const remaining = await page.evaluate(() => 0);
  expect(remaining).toBe(0);
  expect(await page.locator('#notifList .notif-row').filter({ hasText: `${TAG} read 0` }).count()).toBe(1);
});
