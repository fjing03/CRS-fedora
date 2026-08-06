import { test } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Request History page', () => {
  test('My Request History', async ({ page }) => {
    await checkPage(page, '/my-request-history-ui', ['#timetable'], 'My Request History');
  });
});
