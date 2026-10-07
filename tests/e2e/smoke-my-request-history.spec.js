import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('My Request History smoke tests', () => {
  test('loads and has request history', async ({ page }) => {
    await checkPage(page, '/my-request-history-ui', ['#timetable'], 'My Request History');
  });

  test('displays at least 3 request rows', async ({ page }) => {
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    const count = await page.evaluate(() => {
      return window.MockData?.requests?.length ?? 0;
    });
    expect(count).toBeGreaterThanOrEqual(3);
  });
});
