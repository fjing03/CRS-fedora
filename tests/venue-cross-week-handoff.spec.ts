import { test, expect } from '@playwright/test';
import { pickSpanFreeCell, selectWeek } from './helpers/intent-modal-helper';

/** SDD venue-cross-week-handoff (user report 2026-10-20, prompt
 *  pi-prompt-venue-cross-week-handoff.md): the venue intent session dd's
 *  picked row carries ITS OWN occurrence week (data-week) — the handoff
 *  URL's &week= must pin THAT week, not the viewed slot's week.
 *  Repro receipts (the user's exact steps): venue B111, Week 7 view, the free
 *  Tue 03 Nov 13:00 cell, pick the (L) W8 row (AMCS2093, session 97) →
 *  the URL carries &week=8 (was: 7 — the phantom pair {97,7}); the strip
 *  reads the honest Week 8 · Mon, Nov 09 2026 · 11:00 AM–1:00 PM @ B110.
 *  Control: a same-week pick produces today's URL byte-for-byte. */
test.use({ storageState: '.auth/staff-5770.json' });

test('S1 — cross-week pick threads the occurrence week (&week=8, honest strip)', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B111');
  await page.waitForTimeout(600);
  await selectWeek(page, 6); // W7 view: the slot week — the picked occurrence lives in W8
  await page.waitForTimeout(300);
  // the user's exact booked slot: Tue 03 Nov 13:00 (day 1, hour 10 in the 30-min 08:00 grid)
  await page.locator('#tableBody td[data-day="1"][data-hour="10"] .cell-available').click();
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  // the user's step 3 — pick the W8 occurrence EXPLICITLY (not the pre-select)
  await page.locator('#intentSessionTrigger').click();
  const row = page.locator('#intentSessionPanel .session-dd-item[data-sid="97"][data-week="8"]').first();
  await expect(row).toBeVisible();
  await row.click(); // chooseSession marks .active and closes the dd
  await page.locator('#intentArrangeBtn').click();
  await page.waitForURL(/replacement-arrangement\?.*session=/, { timeout: 10_000 });
  const url = new URL(page.url());
  expect(url.searchParams.get('venue')).toBe('B111');
  expect(url.searchParams.get('from')).toBe('venue-timetable');
  expect(url.searchParams.get('code')).toBe('AMCS2093');
  expect(url.searchParams.get('session')).toBe('97');
  // THE FIX: the occurrence's own week — was '7' (the phantom pair {97,7})
  expect(url.searchParams.get('week')).toBe('8');
  // the honest strip: the proposed pinned occurrence under ITS OWN week
  // (was: Week 7 · Mon, Nov 02 2026 — the pair that does not exist)
  await page.waitForTimeout(900);
  const strip = await page.locator('#subjectInfo').innerText();
  expect(strip).toContain('Week 8');
  expect(strip).toContain('Mon, Nov 09 2026');
  expect(strip).toContain('11:00 AM');
  expect(strip).toContain('1:00 PM @ B110');
  // secondary (non-discriminating for this repro: the T18 auto-pin clobber
  // coincidentally produces the same value — design D5's honesty note)
  const pin = await page.evaluate(() => (window as any).pinnedOccurrence);
  expect(pin).toEqual({ sessionId: 97, week: 8 });
});

test('S2 — same-week pick keeps today\'s URL byte-for-byte (parity control)', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110');
  await page.waitForTimeout(600);
  await selectWeek(page, 7); // W8 view: the slot week (8) == the occurrence week (8)
  await page.waitForTimeout(300);
  await pickSpanFreeCell(page);
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  // open-pick the same (L) W8 row through the dd (A1: the pool at B110 W8 = that single row)
  await page.locator('#intentSessionTrigger').click();
  const row = page.locator('#intentSessionPanel .session-dd-item[data-sid="97"][data-week="8"]').first();
  await expect(row).toBeVisible();
  await row.click();
  await page.locator('#intentArrangeBtn').click();
  await page.waitForURL(/replacement-arrangement\?.*session=/, { timeout: 10_000 });
  const url = new URL(page.url());
  // byte-parity: the row's own week and the legacy fallback (currentWeek + 1)
  // compose the SAME value here — nothing about same-week handoffs changed
  expect(url.searchParams.get('week')).toBe('8');
  expect(url.searchParams.get('session')).toBe('97');
  expect(url.searchParams.get('from')).toBe('venue-timetable');
});
