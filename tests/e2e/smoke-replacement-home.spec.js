import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Replacement Home smoke tests', () => {
  test('loads and has replacement page', async ({ page }) => {
    await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    await expect(page).toHaveTitle(/Replacement/);
  });

  test('displays at least 1 conflicted class', async ({ page }) => {
    await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
    const count = await page.evaluate(() => {
      return window.MockData?.conflictedClasses?.length ?? 0;
    });
    expect(count).toBeGreaterThanOrEqual(1);
  });
});
