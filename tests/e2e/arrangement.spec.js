import { test } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Arrangement page', () => {
  test('Replacement Arrangement', async ({ page }) => {
    await checkPage(page, '/replacement-arrangement', ['#buildingSelector', '#progressWrapper'], 'Replacement');
  });
});
