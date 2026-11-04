import { test } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('My Replacements — the retired URL is an ALIAS', () => {
  /* replacement-merge-one-table M2: /my-request-history-ui is a route entry for
     the merged page, never a redirect — same page, same table, new name. */
  test('the alias renders the merged page', async ({ page }) => {
    await checkPage(page, '/my-request-history-ui', ['#timetable'], 'My Replacements');
  });
});
