import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Arrangement Page smoke tests', () => {
  test('loads and has arrangement page', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    await expect(page).toHaveTitle(/Replacement/);
  });

  test('has available (green) slots in venue data', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    const hasAvailable = await page.evaluate(() => {
      const slots = window.MockData?.venueSlots;
      if (!slots) return false;
      const venue = Object.values(slots)[0];
      if (!venue) return false;
      return venue.some(s => s[2] === 0);
    });
    expect(hasAvailable).toBeTruthy();
  });
});
