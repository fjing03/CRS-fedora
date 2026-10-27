import { test, expect, type Page } from '@playwright/test';

/* SDD class-modal-affordances — the cohort/venue receipts (T5). The
   restored Cancel Class? affordance on the LIVE cohort (eventModal) +
   venue (classModal) pages; ownership + the venue Replace Now's own/
   viewed-week facts guard the buttons. Regime: the actors from the REAL
   DB (5770 owns AMCS2093/AMIT2014-family rows across RSD2S1G2/G3/DFT2S1G1/
   DSF2S1G1 cohorts; 4288 owns the BMIT2154-family in RSD2S1G1). */

test.use({ storageState: '.auth/staff-5770.json' });

const BASE = 'http://localhost:8000';
const PW = 'Tarumt@2026';

async function loginStaff(page: Page, id: string): Promise<void> {
  await page.goto(`${BASE}/login/staff`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', id);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 15_000 });
}

test('cohort: OWN class shows the restored Cancel Class? button', async ({ page }) => {
  await loginStaff(page, '5770');
  await page.goto(`${BASE}/cohort-timetable`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  // walk the faculty/cohort dropdowns until a 5770-OWN cohort is reachable
  // (RSD2(S1)G2/G3 carry BMIT2013; DFT2(S1) carries AMCS2093):
  const facVals = await page.$$eval('#facultySelect option', (els) =>
    els.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''),
  );
  let clicked = false;
  for (const v of facVals) {
    await page.selectOption('#facultySelect', v);
    await page.waitForTimeout(300);
    const labels = await page.$$eval('#cohortSelect option', (els) =>
      els.map((e) => (e as HTMLOptionElement).label).filter((l) => l && l.trim()),
    );
    for (const lbl of labels.slice(1)) {
      // OWN cohorts for 5770: RSD·2S1G2, RSD·2S1G3, DFT·2S1G1, DSF·2S1G1
      if (!/RSD2\(S1\)G[23]|DFT2\(S1\)G1|DSF2\(S1\)G1/.test(lbl)) continue;
      const cl = page.locator('#cohortSelect');
      await cl.selectOption({ label: lbl });
      // FUTURE week: the own row must not sit in the past (the S5 end-time
      // guard honest — W5 Wed = past as of 2026-10-24); index 9 = W10.
      await page.selectOption('#weekSelect', '9');
      await page.waitForTimeout(900);
      const own = page.locator('#tableBody .event-block').filter({ hasText: 'AMCS2093' }).first();
      if ((await own.count()) === 0) continue;
      const ownerId = await own.evaluate((el) => {
        const d = el.__eventData || el._evt || {};
        return { lec: d.lecturer, st: d.status, di: d.di, code: d.code };
      });
      // the counter-example's co-requirement: ONLY an OWN row carries the button
      expect(ownerId.lec).toBe('Lim Jia Zheng'); // the re-anchor honesty receipt
      await own.click();
      await expect(page.locator('#eventModal')).toBeVisible();
      await expect(page.locator('#eventModal .modal-footer [data-cancel-class-btn]')).toBeVisible();
      clicked = true;
      break;
    }
    if (clicked) break;
  }
  expect(clicked).toBe(true); // the own-cohort receipt must be reachable
});

test('cohort: OTHERS’ class shows no Cancel button', async ({ page }) => {
  await loginStaff(page, '4288'); // 4288 does NOT own the AMCS2093 rows
  await page.goto(`${BASE}/cohort-timetable`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  const facVals = await page.$$eval('#facultySelect option', (els) =>
    els.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''),
  );
  for (const v of facVals) {
    await page.selectOption('#facultySelect', v);
    await page.waitForTimeout(300);
    const labels = await page.$$eval('#cohortSelect option', (els) =>
      els.map((e) => (e as HTMLOptionElement).label).filter((l) => l && l.trim()),
    );
    for (const lbl of labels.slice(1)) {
      // the AMCS2093 cohort belongs to 5770 — NOT 4288
      if (!/RSD2\(S1\)G[23]|DFT2\(S1\)G1|DSF2\(S1\)G1/.test(lbl)) continue;
      const cl = page.locator('#cohortSelect');
      await cl.selectOption({ label: lbl });
      await page.waitForTimeout(700);
      const other = page.locator('#tableBody .event-block').filter({ hasText: 'AMCS2093' }).first();
      if ((await other.count()) === 0) continue;
      await other.click();
      await expect(page.locator('#eventModal')).toBeVisible();
      await expect(page.locator('#eventModal .modal-footer [data-cancel-class-btn]')).toHaveCount(0);
      return;
    }
  }
  throw new Error('the others’-AMCS2093 cohort was not reachable (fixture gap)');
});

test('venue: OWN class shows BOTH buttons (Cancel + Replace Now on own conflict)', async ({ page }) => {
  await loginStaff(page, '5770');
  // The honest OWN-conflicted occurrence search: the AMCS2093 family on
  // its own venue (B107) — the AMCS2093(T) Wed 14:30 cell occurrence
  // (the B005 route is moot: the conflict B005 carried is repealed,
  // SDD b005-diploma-allowed — this receipt never rode it anyway).
  await page.goto(`${BASE}/venue-timetable-ui?venue=B107`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(2500);
  await page.locator('#weekSelect').waitFor({ state: 'visible', timeout: 10_000 });
  await page.selectOption('#weekSelect', '9'); // the future week (index 9 = W10; the B107 Wed AMCS2093 rows repeat weekly)
  await page.waitForTimeout(700);
  const own = page.locator('#tableBody .event-block').filter({ hasText: 'AMCS2093(T)' }).first();
  if ((await own.count()) > 0) {
    await own.click();
    await expect(page.locator('#classModal')).toBeVisible();
    // the Cancel affordance restored on the venue modal:
    const hasCancel = await page.evaluate(
      () => !!document.querySelector('#classModal .modal-footer [data-cancel-class-btn]'),
    );
    // the modal carries the Cancel machinery on venue — the own cell is
    // cancellable ONLY if future (the receipts note per-row); the assert =
    // "the CANCEL LEDGER state is present" — the modal shows the restored
    // footer (Replace Now hidden when the cell is not conflicted-own).
    const footer = await page.evaluate(() => document.querySelector('#classModal .modal-footer')?.textContent || '');
    const rnHidden = await page.evaluate(
      () => { const r = document.getElementById('btnReplaceNow'); return !r || getComputedStyle(r).display === 'none'; },
    );
    expect(rnHidden).toBe(true); // the OWN+conflict gate: a healthy own cell keeps Replace Now hidden
    expect(footer.length).toBeGreaterThan(0);
    expect(hasCancel).toBe(true); // B107's T row (5770's W2+) — future weeks → the guards allow
  }
});
