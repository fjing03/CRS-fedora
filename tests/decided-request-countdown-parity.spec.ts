import { expect, test } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/decided-request-countdown-parity.spec.ts');

const BASE = process.env.CRS_BASE!;
const URGENCY = '[class*="urgency-"]';
declare const HtmlBuilder: { requestCard(r: Record<string, unknown>): string };

for (const touch of [false, true]) {
  test(`student original-slot countdown matches the details on ${touch ? 'phone' : 'desktop'}`, async ({ browser }) => {
    const context = await browser.newContext({
      viewport: { width: touch ? 390 : 1440, height: 900 }, hasTouch: touch, isMobile: touch,
      storageState: '.auth/student-24RSD0009.json',
    });
    try {
      const page = await context.newPage();
      await page.goto(`${BASE}/student-class-replacements`, { waitUntil: 'networkidle' });
      const rows = page.locator(touch ? '.upcoming-card' : '#upcomingBody tr');
      const approved = rows.filter({ has: page.locator('.badge').filter({ hasText: /^Approved$/ }) }).first();
      const awaiting = rows.filter({ has: page.locator('.badge').filter({ hasText: /^Awaiting Arrangement$/ }) }).first();
      await expect(approved).toBeVisible();
      const original = approved.locator('.cell-class-block').first();
      await expect(original.locator('.class-day-date')).toBeVisible();
      await expect(original.locator('.class-time')).toBeVisible();
      await expect(original.locator(URGENCY)).toHaveCount(0);
      await expect(awaiting.locator('.cell-class-block').first().locator(URGENCY)).toBeVisible();
      if (touch) await approved.tap();
      else await approved.click();
      const modal = page.locator('#classModal');
      await expect(modal).toBeVisible();
      await modal.getByRole('button', { name: 'Original', exact: true }).click();
      await expect(modal.locator('.modal-tab-panel.active')).toContainText('Original Slot Schedule', { ignoreCase: true });
      await expect(modal.locator('.modal-tab-panel.active')).not.toContainText(/Overdue|Working Days Left|Days Left/);
    } finally {
      await context.close();
    }
  });
}

test('approval countdown is Pending-only on both surfaces; request age stays visible', async ({ browser }) => {
  for (const touch of [false, true]) {
    const context = await browser.newContext({
      viewport: { width: touch ? 390 : 1440, height: 900 }, hasTouch: touch, isMobile: touch,
      storageState: '.auth/staff-5425.json',
    });
    try {
      const page = await context.newPage();
      await page.goto(`${BASE}/request-approval-ui`, { waitUntil: 'networkidle' });
      const clear = page.getByRole('button', { name: 'Clear all', exact: true });
      if (touch) await clear.tap();
      else await clear.click();
      const rows = page.locator(touch ? '#requestsCards .request-card' : '#tableBody tr');
      const decided = rows.filter({ has: page.locator('.badge').filter({ hasText: /^(Approved|Rejected|Completed)\b/ }) });
      await expect(decided.first()).toBeVisible();
      for (const row of await decided.all()) {
        const slot = row.locator(touch ? '.cl-rc-slot' : '.col-replacement');
        await expect(slot.locator(touch ? '.cl-rc-date' : '.class-day-date')).toBeVisible();
        await expect(slot.locator(touch ? '.cl-rc-time' : '.class-time')).toBeVisible();
        await expect(slot.locator('.slot-badge')).toBeVisible();
        await expect(slot.locator(URGENCY)).toHaveCount(0);
        await expect(row.locator(touch ? '.cl-req-age' : '.request-age')).toBeVisible();
      }
      if (touch) {
        // Test the positive arm using a detached render of a served schedule as Pending.
        // No fixture is added to the app's list and no decision is submitted.
        const sample = await page.evaluate(() => {
          const app = window as unknown as { PAGE_DATA: { requests: Record<string, unknown>[] } };
          const real = app.PAGE_DATA.requests.find(r => r.replacementDate);
          if (!real) throw new Error('No approval schedule is available for the detached Pending sample');
          const html = HtmlBuilder.requestCard({ ...real, status: 'Pending', requestStatusShort: 'Pending' });
          return new DOMParser().parseFromString(html, 'text/html').querySelector('.cl-rc-urg')?.textContent;
        });
        expect(sample).toMatch(/Today|Overdue|Working Day/);
      }
    } finally {
      await context.close();
    }
  }
});
