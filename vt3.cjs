const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ storageState: '.auth/staff-5425.json', viewport: { width: 1680, height: 950 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(12000);
  await page.goto('http://127.0.0.1:8000/login/staff');
  await page.waitForLoadState('networkidle');
  await page.locator('input[name="login_id"]').fill('5425');
  await page.locator('input[name="password"]').fill('Tarumt@2026');
  await page.locator('form button[type="submit"], form input[type="submit"], button:has-text("Sign In"), #submitBtn').first().click();
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 20000 });
  await page.goto('http://127.0.0.1:8000/venue-timetable-ui');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(700);
  // drive the class API directly: hidden select or the raw mock
  const out = await page.evaluate(() => {
    const mock = window.MockData.venues || [];
    const b5 = mock.find(v => v.code === 'B005') || {};
    return { name: b5.name, type: b5.type, capacity: b5.capacity, realNames: window.MockData.venueRealNames && window.MockData.venueRealNames.B005 };
  });
  console.log('mock B005:', JSON.stringify(out));
  // drive the venue page's own dropdown via hidden select if any:
  const sel = await page.evaluate(() => {
    const selist = [...document.querySelectorAll('select')].map(s => ({ id: s.id, n: s.name, opts: [...s.options].slice(0,3).map(o=>o.textContent.trim()) }));
    return selist;
  });
  console.log('selects:', JSON.stringify(selist));
  await browser.close();
})().catch(e => { console.log('FATAL:', e.message); });
