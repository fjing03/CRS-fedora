import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Student My Timetable smoke tests', () => {
  test('loads and has timetable', async ({ page }) => {
    await page.goto('/student-my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    await expect(page).toHaveTitle(/My Timetable/);
  });

  test('displays a base module code from cohort data', async ({ page }) => {
    await page.goto('/student-my-timetable-ui', { waitUntil: 'networkidle' });
    const hasCode = await page.evaluate(() => {
      const data = window.MockData?.cohortTimetable;
      if (!data) return false;
      return data.events.some(e => e.cohortId === 'dft1s1g1' && e.event.code === 'BMIT2020');
    });
    expect(hasCode).toBeTruthy();
  });
});
