import { expect, test } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/login-role-switch-touch.spec.ts');

for (const [from, to] of [['staff', 'student'], ['student', 'staff']] as const) {
  for (const [width, height, touch, theme] of [
    [320, 430, true, 'light'], [844, 390, true, 'dark'], [1440, 900, true, 'light'], [1440, 900, false, 'dark'],
  ] as const) {
    test(`${from} to ${to} at ${width}×${height} with ${touch ? 'touch' : 'mouse'}`, async ({ browser, baseURL }, info) => {
      const context = await browser.newContext({ viewport: { width, height }, hasTouch: touch, isMobile: touch });
      try {
        await context.addInitScript(value => localStorage.setItem('theme', value), theme);
        const page = await context.newPage();
        await page.goto(`${baseURL}/login/${from}`);
        await page.evaluate(async () => {
          await Promise.allSettled(document.getAnimations().filter(animation =>
            animation.effect?.getComputedTiming().endTime !== Infinity,
          ).map(animation => animation.finished));
          window.scrollTo(0, 0);
        });
        await expect(page.locator('#loginBtn')).toBeInViewport({ ratio: 1 });
        const link = page.getByRole('link', { name: `${to} login`, exact: false });
        await link.scrollIntoViewIfNeeded();
        await expect(link).toBeInViewport({ ratio: 1 });
        const box = await link.evaluate(el => {
          const r = el.getBoundingClientRect();
          const range = document.createRange();
          range.selectNodeContents(el);
          return {
            width: r.width, height: r.height, display: getComputedStyle(el).display,
            coarse: matchMedia('(any-pointer: coarse)').matches,
            topGutter: range.getBoundingClientRect().top - r.top,
            upperEdgeHitsLink: el.contains(document.elementFromPoint(r.left + r.width / 2, r.top + 3)),
            lowerEdgeHitsLink: el.contains(document.elementFromPoint(r.left + r.width / 2, r.bottom - 3)),
          };
        });
        expect(box.coarse).toBe(touch);
        await info.attach('hit-area', { body: JSON.stringify(box), contentType: 'application/json' });
        if (touch) {
          expect(box.width).toBeGreaterThanOrEqual(48);
          expect(box.height).toBeGreaterThanOrEqual(48);
          expect(box.topGutter).toBeGreaterThan(3);
          expect(box.upperEdgeHitsLink).toBe(true);
          expect(box.lowerEdgeHitsLink).toBe(true);
          await page.screenshot({ path: info.outputPath('role-switch.png') });
          await link.tap({ position: { x: box.width / 2, y: 3 } });
        } else {
          expect(box.display).toBe('inline');
          expect(box.height).toBeLessThan(24);
          await link.click();
        }
        await expect(page).toHaveURL(`${baseURL}/login/${to}`);
      } finally {
        await context.close();
      }
    });
  }
}
