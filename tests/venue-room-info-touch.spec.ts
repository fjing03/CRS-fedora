import { expect, test, type Locator } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/venue-room-info-touch.spec.ts');

const BASE = process.env.CRS_BASE;
const B002 = 'Tutorial Room. For PRACTICAL and TUTORIAL.';

for (const [width, height, touch, theme] of [
  [320, 568, true, 'light'], [390, 844, true, 'dark'], [820, 1180, true, 'light'],
  [1280, 900, true, 'dark'], [1440, 900, false, 'light'],
] as const) {
  test(`Venue room info opens by ${touch ? 'tap' : 'click'} at ${width}px`, async ({ browser }, info) => {
    const context = await browser.newContext({
      storageState: '.auth/staff-5425.json', viewport: { width, height }, hasTouch: touch,
      isMobile: touch, colorScheme: theme,
    });
    try {
      await context.addInitScript(value => localStorage.setItem('theme', value), theme);
      const page = await context.newPage();
      await page.goto(`${BASE}/venue-timetable-ui?venue=B002`, { waitUntil: 'networkidle' });
      const hit = (element: Locator) => touch ? element.tap() : element.click();
      const trigger = page.locator('#venueDescription');
      const disclosure = page.locator('.venue-room-info');
      const note = disclosure.locator('.venue-room-info-note');
      await expect(page.locator('#venueCapacity')).toHaveText('35');
      await expect(trigger).toBeVisible();
      await expect(disclosure).not.toHaveAttribute('open', '');
      if (touch) {
        const box = (await trigger.boundingBox())!;
        expect(box.width).toBeGreaterThanOrEqual(48);
        expect(box.height).toBeGreaterThanOrEqual(48);
        const fullTarget = await trigger.evaluate(el => {
          const r = el.getBoundingClientRect();
          return [r.left + 1, r.right - 1].every(x => el.contains(document.elementFromPoint(x, r.top + r.height / 2)));
        });
        expect(fullTarget).toBe(true);
      }
      await hit(trigger);
      await expect(disclosure).toHaveAttribute('open', '');
      await expect(note).toHaveText(B002);
      await expect(note).toBeInViewport({ ratio: 1 });
      const geometry = await note.evaluate(el => {
        const box = el.getBoundingClientRect();
        const hit = document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2);
        return { onTop: hit === el, overflow: document.documentElement.scrollWidth - innerWidth };
      });
      expect(geometry).toEqual({ onTop: true, overflow: 0 });
      await page.screenshot({ path: info.outputPath('room-note.png') });
      await hit(trigger);
      await expect(disclosure).not.toHaveAttribute('open', '');

      if (width === 820) {
        await page.locator('#venueSelectDropdown .venue-dd-trigger').tap();
        await page.locator('#venueTimetablePicker .venue-picker-item[data-venue-code="B006"]').tap();
        await page.waitForURL('**/venue-timetable-ui?venue=B006');
        await expect(page.locator('#venueSelectDropdown .venue-dd-trigger')).toContainText('B006');
        await expect(disclosure).not.toHaveAttribute('open', '');
        await trigger.tap();
        await expect(note).toContainText(/Cisco Lab/i);
        await expect(note).not.toHaveText(B002);
      }
    } finally {
      await context.close();
    }
  });
}
