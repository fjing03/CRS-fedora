import { expect, test, type Locator } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/request-approval-sort-parity.spec.ts');

const URL = `${process.env.CRS_BASE}/request-approval-ui`;
const STAFF = '.auth/staff-5425.json';
const ids = (rows: Locator) => rows.evaluateAll(elements => elements.map(el => el.getAttribute('data-id')));

test('Request Approval cards follow the desktop sort, including saved sorts and filters', async ({ browser }) => {
  const desktop = await browser.newContext({ storageState: STAFF, viewport: { width: 1440, height: 900 } });
  try {
    const page = await desktop.newPage();
    await page.goto(URL, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Clear all', exact: true }).click();
    const tableRows = page.locator('#tableBody tr');
    const desktopCards = page.locator('#requestsCards .request-card');
    const timestamp = page.getByRole('columnheader', { name: /Requested Timestamp/ });
    await expect(tableRows.first()).toBeVisible();
    await expect(timestamp).toHaveAttribute('aria-sort', 'ascending');
    const oldestFirst = await ids(tableRows);
    expect(oldestFirst.length).toBeGreaterThan(1);

    const phone = await browser.newContext({
      storageState: await desktop.storageState(),
      viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    });
    try {
      const mobile = await phone.newPage();
      await mobile.goto(URL, { waitUntil: 'networkidle' });
      await mobile.getByRole('button', { name: 'Clear all', exact: true }).tap();
      const cards = mobile.locator('#requestsCards .request-card');
      await expect(cards.first()).toBeVisible();
      expect(await ids(cards)).toEqual(oldestFirst);

      await timestamp.click();
      await expect(timestamp).toHaveAttribute('aria-sort', 'descending');
      const newestFirst = await ids(tableRows);
      expect(newestFirst[0]).not.toEqual(oldestFirst[0]);
      expect(await ids(desktopCards)).toEqual(newestFirst);
      const savedSort = await page.evaluate(() => localStorage.getItem('request-approval-filters'));
      expect(savedSort).not.toBeNull();
      await mobile.evaluate(saved => localStorage.setItem('request-approval-filters', saved!), savedSort);
      await mobile.reload({ waitUntil: 'networkidle' });
      expect(await ids(cards)).toEqual(newestFirst);

      await mobile.getByRole('button', { name: 'Filters', exact: true }).tap();
      await mobile.locator('#searchInput').fill('AMSE2003');
      await expect(cards).toHaveCount(2);
      const filtered = await ids(mobile.locator('#tableBody tr'));
      expect(await ids(cards)).toEqual(filtered);
      expect(filtered).toEqual(newestFirst.filter(id => filtered.includes(id)));

      // Close the filter panel before opening a decided request; submit nothing.
      await mobile.getByRole('button', { name: 'Close Filters', exact: true }).tap();
      await cards.first().tap();
      const details = mobile.locator('#modalOverlay');
      await expect(details).toBeVisible();
      await details.getByRole('button', { name: 'Original', exact: true }).tap();
      await expect(details.locator('.modal-tab-panel.active')).toContainText(`#${filtered[0]}`);
    } finally {
      await phone.close();
    }
  } finally {
    await desktop.close();
  }
});
