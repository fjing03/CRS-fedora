import { expect, test } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/request-approval-empty-queue.spec.ts');

const URL = `${process.env.CRS_BASE}/request-approval-ui`;
declare const MockData: { approvalRequests: unknown[] };
declare function renderTable(): void;

for (const touch of [false, true]) {
  test(`Request Approval distinguishes an empty queue from no matches on ${touch ? 'phone' : 'desktop'}`, async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/staff-5425.json', viewport: { width: touch ? 390 : 1440, height: 900 },
      hasTouch: touch, isMobile: touch,
    });
    try {
      const page = await context.newPage();
      await page.goto(URL, { waitUntil: 'networkidle' });
      const empty = page.locator('#emptyState');
      const title = empty.locator('#emptyTitle');
      const body = empty.locator('#emptyText');
      const viewAll = empty.getByRole('button', { name: 'View all requests', exact: true });
      const openFilters = page.getByRole('button', { name: 'Filters', exact: true });
      await expect(empty).toBeVisible();
      await expect(title).toHaveText('No pending requests');
      await expect(body).toHaveText('There are no requests waiting for your review.');
      await expect(viewAll).toBeVisible();

      if (touch) await openFilters.tap();
      else await openFilters.click();
      const search = page.locator('#searchInput');
      await search.fill('course-that-does-not-exist');
      await expect(title).toHaveText('No requests found');
      await expect(body).toHaveText('No requests match your search or filter criteria.');
      await expect(viewAll).toBeHidden();
      await search.clear();
      await expect(title).toHaveText('No pending requests');

      for (const id of ['weekFilter', 'weekFilterOriginal']) {
        const week = page.locator(`#${id}`);
        const firstWeek = await week.locator('option:not([value="all"]):not([value=""])').first().getAttribute('value');
        expect(firstWeek).not.toBeNull();
        await week.selectOption(firstWeek!);
        await expect(title).toHaveText('No requests found');
        await expect(viewAll).toBeHidden();
        await week.selectOption('all');
        await expect(title).toHaveText('No pending requests');
      }

      const close = page.getByRole('button', { name: 'Close Filters', exact: true });
      if (touch) {
        await close.tap();
        const hitbox = await viewAll.boundingBox();
        expect(hitbox!.width).toBeGreaterThanOrEqual(48);
        expect(hitbox!.height).toBeGreaterThanOrEqual(48);
        await viewAll.tap();
      } else {
        await close.click();
        await viewAll.click();
      }
      await expect(empty).toBeHidden();
      await expect(page.locator('#statusFilter')).toHaveValue('all');
      await expect(page.locator('#hideCompletedToggle')).not.toBeChecked();
      const rows = page.locator(touch ? '#requestsCards .request-card' : '#tableBody tr');
      await expect(rows).toHaveCount(4);
      await expect(rows.filter({ hasText: 'Completed' })).toBeVisible();
      await expect(page.locator('#filterChips')).toBeEmpty();
    } finally {
      await context.close();
    }
  });
}

test('Request Approval does not offer request history when the served list is empty', async ({ browser }) => {
  const context = await browser.newContext({ storageState: '.auth/staff-5425.json' });
  try {
    const page = await context.newPage();
    await page.goto(URL, { waitUntil: 'networkidle' });
    await expect(page.getByRole('button', { name: 'View all requests' })).toBeVisible();
    // Simulate a genuinely empty response in this browser only. No server writes.
    await page.evaluate(() => {
      MockData.approvalRequests = [];
      renderTable();
    });
    await expect(page.locator('#emptyTitle')).toHaveText('No pending requests');
    await expect(page.getByRole('button', { name: 'View all requests' })).toBeHidden();
  } finally {
    await context.close();
  }
});
