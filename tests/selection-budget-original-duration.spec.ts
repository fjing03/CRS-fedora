import { test, expect } from '@playwright/test';

/* SDD selection-budget-original-duration — the acceptance ledger. The user's
   law (2026-10-24, verbatim): the selection budget = the ORIGINAL conflicted
   class duration — ONE occurrence-length slot total. Numbers verified live:
   - ?session=68&week=14 = AMSE2003 (T) W14 Thu Dec 24 11:00–12:00 @ B100
     (the pinned 1-hour class — DSF2(S1)G1, the 2-slot span);
   - the pinned 2-hour sibling (AMSE2003 (L) W14 9:00–11:00 = 4 slots).
   The rows use B100 Fri 1:00 PM free cells (the user's own live example). */

test.use({ storageState: '.auth/staff-5425.json' });

test('the T (1h) budget: the SECOND 1-hour pick is refused — the summary keeps ONE selection', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=68&week=14&from=replacement-home');
  await page.waitForTimeout(3000);
  // W6 = index 5 (the option values are 0-based)
  await page.locator('#weekSelector').selectOption('5');
  await page.waitForTimeout(1500);
  // pick a free Fri 1:00 PM cell (B100): the first .cell-available in the Fri 13:00 column;
  // fall back to ANY first available cell (the B100 deck paints free Tues/Fri slots).
  let picked = false;
  const cells = page.locator('#tableBody .cell-content.cell-available');
  const n = Math.min(await cells.count(), 8);
  for (let i = 0; i < n; i++) {
    await cells.nth(i).click();
    if (await page.locator('.timetable .event-block.event-selection').count()) { picked = true; break; }
  }
  expect(picked).toBe(true);
  // the counter = the occurrence's span: "2 of 2 slots"
  await expect(page.locator('#infoTotal')).toContainText('2 of 2 slots');
  // W7 (index 6): the second 1-hour pick → refused
  await page.locator('#weekSelector').selectOption('6');
  await page.waitForTimeout(1500);
  await page.locator('#tableBody .cell-content.cell-available').first().click();
  await page.waitForTimeout(500);
  await expect(page.locator('#infoTotal')).toContainText('2 of 2 slots'); // STILL one selection
  // the refusal reads the occurrence-grain wording
  const toast = page.locator('#toastBar');
  await expect(toast).toContainText('2-slot limit', { timeout: 3000 });
  await expect(toast).toContainText('clear your selection to pick another');
});

test('the L (2h) budget: the SECOND 2-hour pick is refused (the regression guard for the old-correct path)', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=67&week=14&from=replacement-home');
  await page.waitForTimeout(3000);
  await page.locator('#weekSelector').selectOption('6'); // W7 = index 6 per the user's example
  await page.waitForTimeout(1500);
  let picked = false;
  const cells = page.locator('#tableBody .cell-content.cell-available');
  const n = Math.min(await cells.count(), 8);
  for (let i = 0; i < n; i++) {
    await cells.nth(i).click();
    if (await page.locator('.timetable .event-block.event-selection').count()) { picked = true; break; }
  }
  expect(picked).toBe(true);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
  await page.locator('#weekSelector').selectOption('5'); // W6 = index 5
  await page.waitForTimeout(1500);
  // try cells until the BUDGET toast lands — a random first cell may fail
  // the span-fit check ("Won't Fit") before the budget check ever runs.
  const cells6 = page.locator('#tableBody .cell-content.cell-available');
  const n6 = Math.min(await cells6.count(), 8);
  let budgetToastSeen = false;
  for (let i = 0; i < n6 && !budgetToastSeen; i++) {
    await cells6.nth(i).click();
    await page.waitForTimeout(400);
    budgetToastSeen = (await page.locator('#toastBar').textContent()).includes('4-slot limit');
  }
  await expect(budgetToastSeen).toBe(true);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
});
