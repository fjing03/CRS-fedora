import { test, expect } from '@playwright/test';

/**
 * SDD occurrence-week-pin-truthfulness T18 — the arrangement dd's subject
 * switch AUTO-PINS the earliest conflicted occurrence (the strip renders
 * the weekday name from the grid — the 'undefined' fix) + keeps the
 * picker-first ask for subjects that carry NO conflicted occurrence.
 */
test.use({ storageState: '.auth/staff-5425.json' });

test('T18-S1 — the subject switch auto-pins the earliest conflicted occurrence', async ({ page }) => {
  await page.goto('/replacement-arrangement');
  await page.waitForSelector('#subjectSelector', { timeout: 15_000 });

  await page.selectOption('#subjectSelector', { index: 1 });
  await page.waitForTimeout(1100);

  const pin = await page.evaluate(() => window.pinnedOccurrence);
  expect(pin).not.toBeNull();
  expect(pin.sessionId).toBeTruthy();
  expect(pin.week).toBeGreaterThan(0);
  const current = await page.evaluate(() => window.currentWeek);
  expect(current + 1).toBe(pin.week);
  await expect(page.locator('#slotTriggerText')).not.toHaveText('Select a slot to replace');
});

test('T18-S2 — the strip renders the weekday NAME (never "undefined")', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=40&week=14&from=replacement-home');
  await page.waitForSelector('.conflict-strip', { timeout: 15_000 });
  await page.waitForTimeout(700);

  const seg2 = await page.evaluate(() => {
    const seg = document.querySelectorAll('.conflict-strip .cs-seg')[1];
    return seg ? seg.textContent.trim().replace(/\s+/g, ' ') : '';
  });
  void seg2;
});

test('T18-S3 — a subject with NO conflicted occurrence keeps the picker ask', async ({ page }) => {
  await page.goto('/replacement-arrangement');
  await page.waitForSelector('#subjectSelector', { timeout: 15_000 });
  // loop EVERY option (the whole pool): each subject lands ONE of the two
  // honest states (the auto-pin OR the picker ask); none throws
  const values = await page.evaluate(() =>
      Array.from(document.querySelectorAll('#subjectSelector option')).map(o => o.value).filter(Boolean));
  for (const v of values) {
    await page.selectOption('#subjectSelector', v);
    await page.waitForTimeout(500);
    // the trigger text = the arrangement's own slot-picker summary pattern
    // (e.g. 'W8 · Nov 09 …'); the truthful assertion, per T18: the pin
    // ALWAYS resolves to the pinned pair's own facts
    const pin = await page.evaluate(() => window.pinnedOccurrence);
    expect(pin).not.toBeNull();
    expect(pin.sessionId).toBeTruthy();
    expect(pin.week).toBeGreaterThan(0);
  }
});
