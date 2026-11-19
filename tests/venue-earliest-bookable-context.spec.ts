import { expect, test } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/venue-earliest-bookable-context.spec.ts');

const BASE = process.env.CRS_BASE;
const STAFF = '.auth/staff-5425.json';
const dimensions = [
  [320, 568, 'light'], [390, 844, 'dark'], [667, 375, 'light'],
  [820, 1180, 'light'], [1024, 768, 'dark'],
] as const;

for (const [width, height, theme] of dimensions) {
  test(`Earliest bookable keeps room and summary visible at ${width}×${height}`, async ({ browser }, info) => {
    const context = await browser.newContext({
      storageState: STAFF, viewport: { width, height }, isMobile: true,
      hasTouch: true, reducedMotion: 'reduce', colorScheme: theme,
    });
    try {
      await context.addInitScript(value => localStorage.setItem('theme', value), theme);
      const page = await context.newPage();
      await page.goto(`${BASE}/venue-timetable-ui?venue=B002`, { waitUntil: 'networkidle' });
      const earliest = page.locator('#earliestBtn');
      const summary = page.locator('#ttSummaryButton');
      const room = page.locator('#ttSticky .tt-room-code');
      await expect(room).toHaveText('Room B002');
      const tip = await earliest.getAttribute('data-tip');
      const date = tip?.match(/(\d{1,2} \w{3} \d{4}) \(Week (\d+)\)/);
      expect(date).not.toBeNull();

      // When repeat-jumping from late in the timetable, focus must scroll the
      // target back into view rather than leave it under the docked chrome.
      if (width === 1024) await page.evaluate(() => window.scrollTo(0, 650));
      await earliest.tap();
      await expect(page.locator('#weekSelect')).toHaveValue(String(Number(date![2]) - 1));
      const target = page.locator('#ttWindowGrid .cell-available:focus');
      await expect(target).toHaveCount(1);
      const day = await target.getAttribute('data-free-day');
      const slot = await target.getAttribute('data-free-slot');
      await expect(page.locator(`#ttDayStrip [data-day="${day}"]`)).toHaveAttribute('aria-pressed', 'true');
      const earliestHeading = page.locator('#ttWindowGrid .tt-whead').filter({ has: page.locator('.bookable-badge') });
      await expect(earliestHeading).toContainText(date![1]);
      const availableOnDay = page.locator(`#ttWindowGrid .cell-available[data-free-day="${day}"]`);
      const slots = await availableOnDay.evaluateAll(elements => elements.map(el => Number(el.getAttribute('data-free-slot'))));
      expect(Number(slot)).toBe(Math.min(...slots));
      await expect(room).toBeInViewport({ ratio: 1 });
      await expect(summary).toBeInViewport({ ratio: 1 });
      const geometry = await target.evaluate(el => {
        const slot = el.getBoundingClientRect();
        const chrome = document.getElementById('ttSticky')!.getBoundingClientRect();
        return { slotTop: slot.top, slotBottom: slot.bottom, chromeBottom: chrome.bottom, height: innerHeight, scroll: scrollY };
      });
      expect(geometry.slotTop).toBeGreaterThanOrEqual(geometry.chromeBottom);
      expect(geometry.slotBottom).toBeLessThanOrEqual(geometry.height + 1);
      if (width === 820) expect(geometry.scroll).toBe(0);
      if (width === 1024) {
        const heading = await earliestHeading.boundingBox();
        expect(heading!.y).toBeGreaterThanOrEqual(geometry.chromeBottom);
      }
      await page.screenshot({ path: info.outputPath('after-earliest.png') });
      await summary.tap();
      await expect(page.locator('#ttSheet')).toBeVisible();
      await page.locator('#ttSheet .tt-sheet-close').tap();
      await expect(page.locator('#ttSheet')).toBeHidden();

      if (width === 820) {
        // The label follows a real room navigation, not a second selection state.
        await page.locator('#venueSelectDropdown .venue-dd-trigger').tap();
        const nextRoom = page.locator('#venueTimetablePicker .venue-picker-item[data-venue-code="B005"]');
        await nextRoom.tap();
        await expect(room).toHaveText('Room B005');
      }
    } finally {
      await context.close();
    }
  });
}

test('Venue sticky room and tools are scoped to the small Venue grid', async ({ browser }) => {
  const context = await browser.newContext({ storageState: STAFF, viewport: { width: 1280, height: 900 } });
  try {
    const page = await context.newPage();
    await page.goto(`${BASE}/venue-timetable-ui?venue=B002`, { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeVisible();
    await expect(page.locator('#ttTools')).toBeHidden();
    await expect(page.locator('.tt-room-code')).toBeHidden();
    await page.locator('#earliestBtn').click();
    await expect(page.locator('#tableBody')).toContainText('Bookings open');
    await page.setViewportSize({ width: 820, height: 1180 });
    await page.goto(`${BASE}/cohort-timetable`, { waitUntil: 'networkidle' });
    await expect(page.locator('#ttTools')).toBeVisible();
    await expect(page.locator('#ttSticky #ttTools, .tt-room-code')).toHaveCount(0);
  } finally {
    await context.close();
  }
});
