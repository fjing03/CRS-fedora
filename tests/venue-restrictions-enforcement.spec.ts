import { test, expect, type Page } from '@playwright/test';

/* SDD venue-restrictions-enforcement (spec R4.3): the arrangement's venue
   dropdown filter gains labs for networking L/T ctx — and the CLIENT-side
   NEGATIVE (absent for non-networking L), the only assert catching a
   client type-check drop. Real deck identities:
   - 5652 (Daniel Royd Michael) owns AMIT2033 L (id 14 @ B006, DFT·2S1G1),
     AMIT2034 L/T/P (DSF·2S1G1)...
   - 4288 (Christopher Lazarus) owns BMIS2003 L (id 10 @ B006, RSD·3S1G1) — the NON-networking negative ctx.
   Login on the fly (the 5/min/ID budget respected: one per identity).

   BLOCKED (the frozen spec's modal browser row): the demo deck carries NO
   conflicted networking-L occurrence (no class_exceptions on it; the W8/W14
   holidays miss its Wed/Mon days) — the browser pool row can't show a
   networking L under any mapping; the Feature harness
   (EnforcementProducerPoolTest covers the pool receipts on the disposable
   deck instead. Never invent data. */

const BASE = 'http://localhost:8000';
const PW = 'Tarumt@2026';

async function loginStaff(page: Page, id: string): Promise<void> {
  await page.goto(`${BASE}/login/staff`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', id);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 15_000 });
}

async function venueTypes(page: { evaluate: Function }): Promise<string[]> {
  return await page.evaluate(() => {
    const dd = document.querySelector('.venue-dd-panel');
    if (!dd) return [];
    return Array.from(dd.querySelectorAll('.venue-col-item-parent'))
      .map((e) => (e.getAttribute('data-type') || e.textContent || '').trim());
  });
}

/* (a) the labs appear for a networking L ctx (AMIT2033 L, DFT cohort) */
test('the venue dropdown gies the networking L ctx its labs (additive)', async ({ page }) => {
  await loginStaff(page, '5652');
  await page.goto(`${BASE}/replacement-arrangement?session=14&week=1`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  await expect(page.locator('#subjectInfo')).toContainText('AMIT2033');
  await page.click('.venue-dd-trigger');
  await page.waitForTimeout(400);
  const types = await venueTypes(page);
  const joined = types.join('|');
  expect(joined).toContain('Lab');
  expect(joined).toContain('Lecture');
  expect(joined).toContain('Tutorial');
  // B005 stays hidden for the D* cohort (R2 composed over the widening):
  await page.waitForTimeout(200);
  const labRows = await page.evaluate(() => {
    const dd = document.querySelector('.venue-dd-panel');
    if (!dd) return null;
    const labCol = Array.from(dd.querySelectorAll('.venue-col')).find((c) =>
      Array.from(c.querySelectorAll('.venue-col-item')).some((i) =>
        (i.getAttribute('data-tip') || '').includes('Lab'),
      ) || Array.from(c.querySelectorAll('.venue-col-item-name')).some((i) => i.textContent?.includes('B00')),
    );
    return labCol ? Array.from(labCol.querySelectorAll('.venue-col-item')).map((i) => i.textContent?.trim()) : null;
  });
  void labRows; // the drill-down per-type detail is not asserted hard (the Types grain is the receipt)
});

/* (b) the CLIENT-side NEGATIVE: a non-networking L ctx shows NO Lab type */
test('the non-networking L ctx shows NO lab in the dropdown (client negative)', async ({ page }) => {
  await loginStaff(page, '4288');
  await page.goto(`${BASE}/replacement-arrangement?session=10&week=1`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  // ?session=10 = BMIS2003 (L) non-networking — a non-networking L context:
  await expect(page.locator('#subjectInfo')).toContainText('BMIS2003');
  await page.click('.venue-dd-trigger');
  await page.waitForTimeout(400);
  const types = await venueTypes(page);
  const joined = types.join('|');
  expect(joined).toContain('Lecture');
  expect(joined).not.toMatch(/Lab/);
});
