/* Test D (true single-cohort): BMIT2154(L) = RSD2(S1)G1 only, 16 students. */
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ storageState: '.auth/staff-5425.json', viewport: { width: 1680, height: 950 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(20000);
  await page.goto('http://127.0.0.1:8000/login/staff');
  await page.waitForLoadState('networkidle');
  await page.locator('input[name="login_id"]').fill('5425');
  await page.locator('input[name="password"]').fill('Tarumt@2026');
  await page.locator('form button[type="submit"], form input[type="submit"], button:has-text("Sign In"), #submitBtn').first().click();
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 20000 });
  await page.goto('http://127.0.0.1:8000/cohort-timetable');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(900);
  // select RSD2(S1)G1
  const val = await page.evaluate(() => {
    const sel = document.getElementById('cohortSelect');
    const opt = [...sel.options].find(o => o.textContent.replace(/\s+/g, ' ').includes('RSD2(S1)G1'));
    return opt ? opt.value : null;
  });
  await page.locator('#cohortSelect').selectOption(val);
  await page.waitForTimeout(900);
  for (let w = 1; w <= 14; w++) {
    await page.locator('#weekSelect').selectOption(String(w - 1));
    await page.waitForTimeout(300);
    const b = page.locator('.timetable .event-block:has-text("BMIT2154(L)")').first();
    if (await b.count()) {
      await b.click();
      await page.waitForTimeout(400);
      const rows = await page.locator('#eventModal .detail-row, #eventModal tr, #eventModal .modal-body p, #eventModal *').allTextContents();
      const modal = page.locator('#eventModal');
      const rows2 = await modal.locator('.detail-row, tr, .modal-body p').allTextContents();
      console.log('D-single rows:', rows2.map(r => r.replace(/\s+/g, ' ').trim()).filter(Boolean));
      await page.screenshot({ path: '/tmp/classinfo-single.png' });
      await page.keyboard.press('Escape');
      return;
    }
  }
  console.log('BMIT2154(L) not found');
  await browser.close();
})().catch(e => { console.log('FATAL:', e.message); });
