/* verify the trigger tooltip using the 4-column descent (Types -> Lab -> Lab 5 -> B005) */
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ storageState: '.auth/staff-5425.json', viewport: { width: 1680, height: 950 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(15000);
  await page.goto('http://127.0.0.1:8000/login/staff');
  await page.waitForLoadState('networkidle');
  await page.locator('input[name="login_id"]').fill('5425');
  await page.locator('input[name="password"]').fill('Tarumt@2026');
  await page.locator('form button[type="submit"], form input[type="submit"], button:has-text("Sign In"), #submitBtn').first().click();
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 20000 });
  await page.goto('http://127.0.0.1:8000/venue-timetable-ui');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(900);
  await page.locator('.venue-dd-trigger').first().click();
  await page.waitForTimeout(500);
  // walk visible columns by clicking the row containing the next label
  for (const label of ['Computer Lab', 'Lab 5']) {
    const row = page.locator('.venue-col-item:visible', { hasText: label }).first();
    if (await row.count()) { await row.click(); await page.waitForTimeout(450); }
  }
  const room = page.locator('.venue-col-item:visible, .venue-col-item-room:visible', { hasText: 'B005' }).first();
  await room.click();
  await page.waitForTimeout(700);
  const state = await page.evaluate(() => {
    const t = document.querySelector('.venue-dd-trigger');
    return {
      label: (t.querySelector('.venue-dd-label') || {}).textContent || null,
      tip: t.getAttribute('data-tip'),
    };
  });
  console.log('state after B005 select:', JSON.stringify(state));
  await page.locator('.venue-dd-trigger').first().hover();
  await page.waitForTimeout(800);
  await page.screenshot({ path: '/tmp/venue-tip-b005.png' });
  await browser.close();
})().catch(e => { console.log('FATAL:', e.message); });
