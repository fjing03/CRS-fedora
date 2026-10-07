import { test } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Home pages', () => {
  test('Welcome page', async ({ page }) => {
    await checkPage(page, '/', ['h1'], 'Welcome');
  });

  test('Replacement Home', async ({ page }) => {
    await checkPage(page, '/replacement-home-ui', ['#searchInput', '#cardView'], 'Replacement');
  });
});
