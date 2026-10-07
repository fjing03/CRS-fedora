import { test } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Timetable pages', () => {
  test('My Timetable', async ({ page }) => {
    await checkPage(page, '/my-timetable-ui', ['#semesterProgress', '#weekSubtitle'], 'My Timetable');
  });

  test('Cohort Timetable', async ({ page }) => {
    await checkPage(page, '/cohort-timetable-ui', ['#semesterChip', '#weekSelect'], 'Cohort Timetable');
  });

  test('Student My Timetable', async ({ page }) => {
    await checkPage(page, '/student-my-timetable-ui', ['#semesterProgress', '#weekSubtitle'], 'My Timetable');
  });
});
