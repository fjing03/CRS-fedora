import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('My Replacements smoke tests', () => {
  test('loads and has the merged table', async ({ page }) => {
    await checkPage(page, '/my-request-history-ui', ['#timetable'], 'My Replacements');
  });

  test('displays the occurrence rows', async ({ page }) => {
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    const count = await page.evaluate(() => {
      return window.MockData?.conflictedClasses?.length ?? 0;
    });
    expect(count).toBeGreaterThanOrEqual(1);
    await expect(page.locator('#tableBody tr').first()).toBeVisible({ timeout: 20000 });
  });
});
