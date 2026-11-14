import { test, expect } from '@playwright/test';

/**
 * Selection Summary is NOT foldable (user, 2026-11-14).
 *
 * It used to carry a chevron that folded its body away — which could hide the
 * picked slots, the totals AND the Submit button. The control is gone: the card
 * is always open, and the other two cards keep their own toggles, proving the
 * removal was scoped to this card only.
 *
 * (This file used to pin the fold itself; rewritten to pin its absence.)
 */
test.use({ storageState: '.auth/staff-5770.json' });

const PINNED = '/replacement-arrangement?session=49&week=14&from=my-timetable';

test('Selection Summary has no fold control and its body is always open', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await page.goto(PINNED, { waitUntil: 'networkidle' });
  await page.waitForSelector('#subjectInfo', { timeout: 15_000 });
  await page.waitForTimeout(900);

  await page.locator('#tableBody .cell-content.cell-available').first().click();
  await expect(page.locator('#topZone')).toHaveClass(/has-summary/);

  const card = page.locator('#selSummary');
  const body = page.locator('#summaryBody');

  /* the control is GONE — not hidden, not disabled */
  await expect(page.locator('#summaryToggle')).toHaveCount(0);
  await expect(card.locator('.ra-card-toggle')).toHaveCount(0);

  /* and the body is open, with the picked slot and the action still reachable */
  await expect(card).not.toHaveClass(/is-collapsed/);
  await expect(body).toBeVisible();
  await expect(page.locator('#summaryGrid .sel-summary-card')).toHaveCount(1);
  await expect(page.locator('#summaryGrid .sel-summary-card')).toBeVisible();
  await expect(page.locator('#summaryEmpty')).toBeHidden();

  /* nothing folds it: the card cannot gain is-collapsed from the page script */
  await page.evaluate(() => {
    document.getElementById('summaryBody')?.removeAttribute('hidden');
  });
  await expect(card).not.toHaveClass(/is-collapsed/);
  await expect(body).toBeVisible();

  /* the removal was SCOPED: Conflict Schedule and Class Details keep their toggles */
  await expect(page.locator('#conflictCollapse')).toHaveCount(1);
  await expect(page.locator('#detailsToggle')).toHaveCount(1);
});
