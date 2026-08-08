import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('My Timetable smoke tests', () => {
  test('loads and has timetable', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    await expect(page).toHaveTitle(/My Timetable/);
  });

  test('displays a seeded module code for lecturer 5425', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    const hasCode = await page.evaluate(() => {
      const data = window.MockData?.myTimetable?.eventsByWeek;
      if (!data) return false;
      return Object.values(data).flat().some(e => e.code === 'BMIT9012');
    });
    expect(hasCode).toBeTruthy();
  });
});
