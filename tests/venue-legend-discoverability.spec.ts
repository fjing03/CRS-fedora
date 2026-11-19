import { expect, test } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/venue-legend-discoverability.spec.ts');

const BASE = process.env.CRS_BASE;
const STAFF = '.auth/staff-5425.json';

for (const [width, height, touch, theme] of [
  [320, 568, true, 'light'], [390, 844, true, 'dark'], [820, 1180, true, 'light'],
  [1280, 900, true, 'dark'], [1440, 900, false, 'light'], [1440, 900, false, 'dark'],
] as const) {
  test(`Venue legend is readable and opens its tips at ${width}px, ${theme}, ${touch ? 'touch' : 'mouse'}`, async ({ browser }, info) => {
    const context = await browser.newContext({
      storageState: STAFF, viewport: { width, height }, hasTouch: touch, isMobile: touch, colorScheme: theme,
    });
    try {
      await context.addInitScript(value => localStorage.setItem('theme', value), theme);
      const page = await context.newPage();
      await page.goto(`${BASE}/venue-timetable-ui?venue=B002`, { waitUntil: 'networkidle' });
      const opener = page.locator('#ttSummaryButton');
      const legend = page.locator('.tt-responsive[data-tt-page="venue"] .legend-bar');
      await expect(legend).toHaveCount(1);
      if (width < 1280) {
        await expect(opener).toHaveText('Legend & summary');
        await expect(opener).toBeInViewport({ ratio: 1 });
        await opener.tap();
        await expect(page.locator('#ttSheetTitle')).toContainText('Legend & summary');
        await expect(page.locator('#ttSheetBody .legend-bar')).toHaveCount(1);
      } else {
        await expect(opener).toBeHidden();
        await expect(page.locator('#ttSheetBody .legend-bar')).toHaveCount(0);
      }
      await expect(legend).toBeVisible();
      const helper = legend.locator(touch ? '.hint-touch' : '.hint-hover');
      const hiddenHelper = legend.locator(touch ? '.hint-hover' : '.hint-touch');
      await expect(helper).toBeVisible();
      await expect(hiddenHelper).toBeHidden();
      await expect(helper).toHaveText(touch ? 'Tap a legend item to see what it means.' : 'Hover over a legend item to see what it means.');
      const contrast = await helper.evaluate(el => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const ctx = canvas.getContext('2d')!;
        const luminance = (colour: string) => {
          ctx.clearRect(0, 0, 1, 1);
          ctx.fillStyle = colour;
          ctx.fillRect(0, 0, 1, 1);
          return [...ctx.getImageData(0, 0, 1, 1).data].slice(0, 3).map(c => c / 255)
            .map(c => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)
            .reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
        };
        const style = getComputedStyle(el);
        const fg = luminance(style.color);
        const bg = luminance(getComputedStyle(el.closest('.legend-bar')!).backgroundColor);
        return {
          ratio: (Math.max(fg, bg) + 0.05) / (Math.min(fg, bg) + 0.05),
          opacity: getComputedStyle(el.closest('.legend-hint')!).opacity,
        };
      });
      expect(contrast.opacity).toBe('1');
      expect(contrast.ratio).toBeGreaterThanOrEqual(4.5);
      await info.attach('legend-contrast', { body: JSON.stringify(contrast), contentType: 'application/json' });

      const available = legend.getByRole('img', { name: /^Available:/ });
      const meaning = await available.getAttribute('data-tip');
      expect(meaning).toContain('Free slot');
      if (touch) await available.tap();
      else await available.hover();
      const tooltip = page.locator('.data-tip-tooltip');
      await expect(tooltip).toBeVisible();
      await expect(tooltip).toHaveText(meaning!);
      await expect(tooltip).toHaveClass(/data-tip-tooltip--wrap/);
      const fit = await tooltip.evaluate(el => {
        const rect = el.getBoundingClientRect();
        return rect.left >= 0 && rect.right <= innerWidth && rect.top >= 0 && rect.bottom <= innerHeight
          && el.scrollWidth <= el.clientWidth;
      });
      expect(fit).toBe(true);
      await page.screenshot({ path: info.outputPath('legend-tip.png') });
      if (!touch) {
        await page.locator('#venueCapacity').hover();
        await expect(tooltip).toContainText('Capacity');
        await expect(tooltip).not.toHaveClass(/data-tip-tooltip--wrap/);
      }
    } finally {
      await context.close();
    }
  });
}

test('Cohort keeps its original sheet name and legend guidance', async ({ browser }) => {
  const context = await browser.newContext({ storageState: STAFF, viewport: { width: 820, height: 1180 } });
  try {
    const page = await context.newPage();
    await page.goto(`${BASE}/cohort-timetable`, { waitUntil: 'networkidle' });
    const opener = page.locator('#ttSummaryButton');
    await expect(opener).toHaveText('Week summary');
    await opener.click();
    await expect(page.locator('#ttSheetTitle')).toContainText('Week summary');
    await expect(page.locator('#ttSheetBody .legend-hint .hint-hover')).toHaveText('Hover a colour to learn more');
    await expect(page.locator('#ttSheetBody [data-tip-wrap]')).toHaveCount(0);
  } finally {
    await context.close();
  }
});
