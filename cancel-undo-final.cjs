/* FINAL: one context — cancel W13 (value '12') with Paternity Leave → reload home → chip undo → all receipts. */
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const psql = (q) => execSync(`psql -U philler -d class_replacement_fresh -tAc "${q}"`).toString().trim();
const log = (...a) => console.log('▶', ...a);
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ storageState: '.auth/staff-5425.json', viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(15000);
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));

  await page.goto('http://127.0.0.1:8000/login/staff');
  await page.waitForLoadState('networkidle');
  await page.locator('input[name="login_id"]').fill('5425');
  await page.locator('input[name="password"]').fill('Tarumt@2026');
  await page.locator('form button[type="submit"], form input[type="submit"], button:has-text("Sign In"), #submitBtn').first().click();
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 20000 });

  await page.goto('http://127.0.0.1:8000/my-timetable-ui');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(600);
  await page.locator('#weekSelect').selectOption('12');
  await page.waitForTimeout(500);
  await page.locator('#tableBody .event-block:has-text("AMSE2003(L)")').first().click();
  await page.waitForTimeout(350);
  await page.locator('#classModal .modal-footer [data-cancel-class-btn]').first().click();
  await page.waitForTimeout(350);
  await page.locator('#cancelReasonList input[type="radio"][value="paternity_leave"]').check();
  await page.locator('#confirmCancelClassBtn').click();
  await page.waitForTimeout(800);
  log('1) cancel success:', (await page.locator('#cancelClassSuccessState').textContent().catch(() => '')).trim().replace(/\s+/g,' ').slice(0,80));
  const exRow = psql("SELECT id || '|s' || class_session_id || '|w' || week_number || '|' || reason FROM class_exceptions ORDER BY id DESC LIMIT 1");
  log('1) DB exception:', exRow);
  const auditRow = psql("SELECT action || ' | reason=' || (details::jsonb->>'reason') FROM audit_logs WHERE action='class_cancelled' ORDER BY id DESC LIMIT 1");
  log('1) DB audit:', auditRow);

  await page.locator('#cancelLaterBtn').click();
  await page.waitForTimeout(300);

  await page.goto('http://127.0.0.1:8000/replacement-home-ui');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(900);
  const chip = await page.evaluate(() => {
    const trs = [...document.querySelectorAll('#tableBody tr')];
    const hit = trs.find(tr => tr.textContent.includes('Week 13'));
    return hit ? { chip: !!hit.querySelector('.just-cancelled-chip'), undo: !!hit.querySelector('.just-cancelled-undo'),
                    badge: hit.textContent.includes('Paternity Leave') } : 'ROW NOT FOUND';
  });
  log('2) W13 row after reload:', JSON.stringify(chip));
  if (!chip || !chip.undo) { log('FAIL'); await browser.close(); return; }

  await page.locator('.just-cancelled-undo').first().click();
  await page.waitForTimeout(2000);
  log('3) toast after chip undo:', (await page.locator('#toastBar').textContent().catch(() => '')).replace(/\s+/g,' ').trim().slice(0,70));
  log('3) DB exception rows for that occurrence (expect 0):',
      psql(`SELECT count(*) FROM class_exceptions WHERE id=${exRow.split('|')[0]}`));
  log('3) DB class_cancel_undone audit:',
      psql("SELECT action || ' | erased reason=' || (details::jsonb->>'reason') || ' | session ' || (details::jsonb->>'class_session_id') || ' | week ' || (details::jsonb->>'week_number') FROM audit_logs WHERE action='class_cancel_undone' ORDER BY id DESC LIMIT 1"));

  const homeLeft = await page.evaluate(() =>
    [...document.querySelectorAll('#tableBody tr')].filter(tr => tr.textContent.includes('Week 13')).length);
  log('4) W13 rows left on home (expect 0):', homeLeft);

  await page.goto('http://127.0.0.1:8000/my-timetable-ui');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(600);
  await page.locator('#weekSelect').selectOption('12');
  await page.waitForTimeout(500);
  log('5) AMSE2003(L) back on W13 (expect >0):', await page.locator('#tableBody .event-block:has-text("AMSE2003(L)")').count());

  log('JS errors:', errors.length ? errors.join(' || ') : 'none');
  await browser.close();
})().catch(e => { console.log('FATAL:', e.message); process.exit(1); });
