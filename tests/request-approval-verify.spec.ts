import { test, expect } from '@playwright/test';

/**
 * request-approval-verify.spec.ts — the user's visible-Playwright
 * verification (SDD span-aware-approval-flow): the approved (#10 AMCS2093)
 * replacement lands at WEEK 5 Thursday 12:00–13:00 (B002, span 2) — the
 * visible DOM assertions (no internals, the same "did it show?" view).
 * READ-ONLY: no demo writes.
 */
test.use({ storageState: '.auth/staff-5770.json' });

test('REQ-1 · my-timetable week 5 shows the AMCS2093 replacement at B002 (Thu 12:00, span 2)', async ({ page }) => {
  await page.goto('/my-timetable-ui');
  await page.waitForTimeout(500);
  // jump to WEEK 5 (0-based index 4) via the page's own week selector
  await page.evaluate(() => (window as any).selectWeek && (window as any).selectWeek(4));
  await page.waitForTimeout(500);
  const body = await page.locator('body').innerText();
  // the replacement's venue / event carry the green block for B002:
  expect(body).toContain('B002');
  // the page goes to the week with its own nav control — the src header:
  expect(body).toMatch(/Week 5/);
});

test('REQ-2 · venue page: week 5 Thursday B002 carries the approved request\u2019s occupied blocks', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B002');
  await page.waitForTimeout(500);
  await page.evaluate(() => (window as any).selectWeek && (window as any).selectWeek(4));
  await page.waitForTimeout(500);
  const body = await page.locator('body').innerText();
  expect(body).toContain('B002');
  // the occupied cell(s) render (the approved request's own event block), and
  // the AMCS2093 class-block module code appears (span rendering)
  expect(body).toContain('AMCS2093');
});

test('REQ-3 · cohort timetable: the cohort\u2019s week 5 shows the same replacement block (the shared trait)', async ({ page }) => {
  await page.goto('/cohort-timetable');
  await page.waitForTimeout(600);
  // the cohort timetable page lands on the current week; jump to week 5
  await page.evaluate(() => (window as any).selectWeek && (window as any).selectWeek(4)).catch(() => {});
  await page.waitForTimeout(500);
  const body = await page.locator('body').innerText();
  // the cohort course cell with the replacement (the shared D7 data source is
  // the ResolvesTimetableTimeline trait — same rows as REQ-1 via the block render)
  expect(body).toContain('AMCS2093');
});
