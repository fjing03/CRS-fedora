import { test, expect } from '@playwright/test';

// own storageState — the demo-fixture user 5770 owns the conflicted rows
// (never the shared/global default: tests without test.use() fall back to a
// logged-OUT context, which redirects to /login/staff and has no table)
test.use({ storageState: '.auth/staff-5770.json' });

/**
 * SDD qv-arrange-null-order-fix — the Quick-View "Arrange Replacement"
 * button's no-op regression: capture-then-hide order (design D1).
 *
 * Repro (pre-fix): clicking #qvArrangeBtn threw
 * `Cannot read properties of null (reading 'sessionId')` — hideQuickView()
 * nulled qvCurrent BEFORE goToReplacementWith read it, so no navigation.
 */
test('qv Arrange button navigates to the arrangement handoff', async ({ page }) => {
  await page.goto('/replacement-home-ui');
  // the skeleton loader fills the grid on a delay — wait for a REAL row
  // (not the skeleton's placeholder rows) before interacting
  await page.waitForSelector('#tableBody tr:not(.skeleton-row)', { timeout: 15000 });
  await page.waitForTimeout(600);

  // open a row's Quick View (the results table's own click affordance —
  // id = the ui-grid-table partial's shared body id)
  await page.locator('#tableBody tr:not(.skeleton-row)').first().click();
  await page.waitForTimeout(400);
  await expect(page.locator('#quickViewModal')).toHaveClass(/show/);

  // the row's identity (the handoff URL pins it: ?session=<id>&week=<n>)
  const expected = await page.evaluate(() => {
    const rows = (window.PAGE_DATA && window.PAGE_DATA.conflictedClasses) || window.MockData.conflictedClasses;
    const mine = rows.find((r: any) => r.lecturer === window.MockData.currentUser.name);
    return mine ? { session: String(mine.sessionId), week: String(mine.week) } : null;
  });
  expect(expected).not.toBeNull();

  // click Arrange — the throw is gone, the navigation must happen
  await page.locator('#qvArrangeBtn').click();
  await page.waitForURL(/replacement-arrangement/, { timeout: 10000 });

  const url = new URL(page.url());
  expect(url.pathname).toBe('/replacement-arrangement');
  expect(url.searchParams.get('session')).toBe(expected!.session);
  expect(url.searchParams.get('week')).toBe(expected!.week);
  expect(url.searchParams.get('from')).toBe('replacement-home');
});
