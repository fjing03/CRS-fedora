import { test, expect, type Page } from '@playwright/test';

/* SDD submission-conflict-refresh — the CONFLICT-mechanics receipts, run
   against the DISPOSABLE testing DB (the same receipts strategy B rules as
   notifications-island-testing-db.spec.ts): the race needs a WRITE — the
   FIRST submission reserves the slot (a real row, sanctioned on the testing
   DB), and the SECOND (the STALE grid from context A) fails the pre-reserve
   = the user's real concurrent story. A demo-DB run is FORBIDDEN — the guard
   SKIPS a bare run.

   REQUIRES the testing DB reseeded + a serve on :8030 (the island lane's two
   pre-commands), then:
   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
     npx playwright test tests/submission-conflict-refresh.spec.ts

   RE-SEED BETWEEN RUNS: the receipt writes (the ctxB reserve) and the post-
   conflict state can fence the picked spans for the next run.

   The week hop = W9 (the 0-based value '8') BEFORE every pick — the landing
   W7-FRI carries the seed's own AMSE2003 make-up pending slate. The pick =
   FRI 12:00–14:00 (data-day 4, hour 8, span 4) — 12:00 = hour idx 8. Week
   values are 0-based (the clear-guards precedent); the page MAY resolve the
   hop one week further (its own bookable-slot filter) — every pick asserts
   the landed span directly, never the week label. */

const BASE = process.env.CRS_BASE ?? '';
const ALLOWED = process.env.CRS_MODE === 'testing' && process.env.CRS_ALLOW_SQL === '1' && BASE.includes(':803');
test.use({ baseURL: BASE });

const PW = 'Tarumt@2026';
const WEEK_HOP = '8'; // → W9 (the page may resolve to the next bookable week)
const CONTROL_HOP = '10'; // → W11 (clean after the receipt)
const pickedCell = '#tableBody td[data-day="4"][data-hour="8"] .cell-content'; // FRI 12:00, the span-4 slot (12:00–14:00)
const URL = '/replacement-arrangement?venue=B110&session=67&week=14&code=AMSE2003&cohort=' + encodeURIComponent('DSF2 (S1) G1') + '&from=replacement-home';

async function loginStaff(page: Page): Promise<void> {
  await page.goto('/login/staff');
  await page.fill('#login_id', '5425');
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 30_000 });
}

/** the pinned arrival → hop the clean FRI week → pick FRI 12:00–14:00 → '4 of 4' */
async function arriveHopPick(page: Page, bustSeed: string): Promise<void> {
    await page.goto(URL + '&bust=' + bustSeed, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await page.waitForTimeout(3000); // the pinned arrival (the bridge + the vectors paint)
  await page.locator('#weekSelector').selectOption(WEEK_HOP);
    await page.waitForTimeout(1200);
  await page.locator(pickedCell).click();
    await page.waitForTimeout(800);
  }

test('the staged race reaches the Submission Conflict modal; Refresh Page reloads with NO browser dialog; the guard stays alive elsewhere', async ({ browser }) => {
  test.skip(!ALLOWED, "The :8030-only receipts: run with CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 (the island lane's two pre-commands).");

  test.setTimeout(600_000);

  // ── ctxA: load, hop, select FRI 12:00–14:00; DO NOT submit (the stale state) ──
  const ctxA = await browser.newContext();
  const pageA = await ctxA.newPage();
  const dialogsA: string[] = [];
  pageA.on('dialog', async (d) => { dialogsA.push(d.type()); await d.dismiss().catch(() => {}); });
  await loginStaff(pageA);
  const bust = Date.now();
  await arriveHopPick(pageA, 'ra-' + bust);

  // ── ctxB: the fresh view submits FIRST — the slot gets reserved (the sanctioned write) ──
  const ctxB = await browser.newContext();
  const pageB = await ctxB.newPage();
  await loginStaff(pageB);
  await arriveHopPick(pageB, 'rb-' + bust);
  await pageB.evaluate(() => { (window as any).__scrMarker = 'pre'; });
  await pageB.evaluate(() => (window as any).proceed());
  await pageB.waitForTimeout(600);
  await expect(pageB.locator('#confirmModal')).toContainText('Confirm Your Selection', { timeout: 5000 });
  await pageB.locator('#modalConfirmBtn').click(); // 'Yes, Submit Request'
  // the success path navigates to My Request History (the design aftermath)
  await pageB.waitForTimeout(4000); // the probe's exact shape
  await pageB.waitForURL(/my-request-history-ui/, { timeout: 60_000 });
  await ctxB.close();

  // ── ctxA submits the SAME slot from its STALE grid → the pre-reserve fails → the conflict modal ──
  await pageA.evaluate(() => { (window as any).__scrMarker = 'pre'; });
  await pageA.evaluate(() => (window as any).proceed());
  await pageA.waitForTimeout(600);
  await expect(pageA.locator('#confirmModal')).toContainText('Confirm Your Selection', { timeout: 5000 });
  await pageA.locator('#modalConfirmBtn').click(); // 'Yes, Submit Request'
  await pageA.waitForTimeout(4000); // the probe's exact shape
  const conflict = pageA.locator('#confirmModal');
  await expect(conflict).toContainText('Submission', { timeout: 15_000 });
  await expect(conflict).toContainText('NOTE', { timeout: 5000 });
  await expect(conflict).toContainText('Another staff member may have booked', { timeout: 5000 });
  await expect(conflict).toContainText('Refresh Page', { timeout: 5000 });

  // THE AMENDMENT: the click reloads with NO browser leave-confirmation
  dialogsA.length = 0;
  await pageA.locator('#modalConfirmBtn').click(); // 'Refresh Page'
  await pageA.waitForLoadState('load', { timeout: 60_000 }).catch(() => {});
  await pageA.waitForTimeout(1500);
  expect(dialogsA).toEqual([]); // the leave-confirmation must NOT appear
  const fresh = await pageA.evaluate(() => (window as any).__scrMarker === undefined);
  expect(fresh).toBe(true); // the page genuinely reloaded (the marker died with the page)
  await expect(pageA).toHaveURL(/replacement-arrangement\?/);
  expect(await pageA.evaluate(() => JSON.stringify((window as any).pinnedOccurrence || null)))
    .toContain('67'); // the URL context (session/week/etc.) rode the reload

  // THE CONTROL: the unsaved-changes guard STAYS alive on every other path —
  // a fresh selection + a manual reload pops the browser leave-confirmation.
  // The control's week hop lands past the receipt's own reservation.
  await pageA.waitForTimeout(3400);
  await pageA.locator('#weekSelector').selectOption(CONTROL_HOP);
  await pageA.waitForTimeout(1300);
  await pageA.locator(pickedCell).click();
  await pageA.waitForTimeout(900);
  await expect(pageA.locator('#infoTotal')).toContainText('4 of 4 slots');
  dialogsA.length = 0;
  await pageA.reload();
  await pageA.waitForTimeout(1500);
  expect(dialogsA).toContain('beforeunload'); // the guard pops OUTSIDE the Refresh Page path
});
