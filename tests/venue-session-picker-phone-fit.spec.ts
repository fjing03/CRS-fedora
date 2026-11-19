import { expect, test, type Locator } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/venue-session-picker-phone-fit.spec.ts');

const cases = [
  { width: 320, height: 568, touch: true, theme: 'light' },
  { width: 360, height: 800, touch: true, theme: 'dark' },
  { width: 390, height: 844, touch: true, theme: 'light' },
  { width: 430, height: 932, touch: true, theme: 'dark' },
  { width: 600, height: 850, touch: true, theme: 'light' },
  { width: 820, height: 1180, touch: true, theme: 'light' },
  { width: 1440, height: 900, touch: false, theme: 'dark' },
] as const;

for (const { width, height, touch, theme } of cases) {
  test(`Venue Session picker fits at ${width}×${height}, ${theme}`, async ({ browser }, info) => {
    const context = await browser.newContext({
      viewport: { width, height }, hasTouch: touch, isMobile: touch, colorScheme: theme,
      storageState: '.auth/staff-5425.json',
    });
    try {
      await context.addInitScript(value => localStorage.setItem('theme', value), theme);
      const page = await context.newPage();
      const hit = (element: Locator) => touch ? element.tap() : element.click();
      const url = `${process.env.CRS_BASE}/venue-timetable-ui`;
      await page.goto(url, { waitUntil: 'networkidle' });
      await hit(page.locator('#earliestBtn'));
      const slot = page.locator(width < 1280
        ? '#ttWindowGrid .cell-available[data-free-slot]:visible'
        : '#timetable .cell-available:visible').first();
      await hit(slot);
      await hit(page.locator('#tooltipBookBtn'));
      const modal = page.locator('#intentSessionModal');
      const trigger = modal.locator('#intentSessionTrigger');
      const label = trigger.locator('.session-dd-label');
      const arrow = trigger.locator('.dd-arrow');
      const subject = modal.locator('#intentSubjectSelect');
      await expect(modal).toBeVisible();
      await expect(label).toHaveText(/\d{2}:\d{2} ~ \d{2}:\d{2}/);
      // Let the sheet's own opening transition finish before comparing its bounds.
      await modal.locator('.modal').evaluate(async el => {
        await Promise.allSettled(el.getAnimations().map(animation => animation.finished));
      });

      const fit = await trigger.evaluate(el => {
        const box = el.getBoundingClientRect();
        const body = el.closest('.modal-body')!.getBoundingClientRect();
        const text = el.querySelector('.session-dd-label')!;
        const arrow = el.querySelector('.dd-arrow')!.getBoundingClientRect();
        const subject = document.getElementById('intentSubjectSelect')!.getBoundingClientRect();
        return {
          inside: box.left >= Math.max(0, body.left) && box.right <= Math.min(innerWidth, body.right),
          aligned: Math.abs(subject.right - box.right) <= 1,
          height: box.height,
          arrowInside: arrow.width > 0 && arrow.right < box.right,
          fullText: text.scrollWidth <= text.clientWidth + 1 && text.scrollHeight <= text.clientHeight + 1,
        };
      });
      expect(fit).toMatchObject({ inside: true, aligned: true, arrowInside: true, fullText: true });
      if (touch) expect(fit.height).toBeGreaterThanOrEqual(48);
      await expect(arrow).toBeVisible();
      const labelPosition = await subject.evaluate(el => {
        const row = el.closest('.detail-row')!;
        return getComputedStyle(row).flexDirection;
      });
      expect(labelPosition).toBe(width <= 480 ? 'column' : 'row');
      await page.screenshot({ path: info.outputPath('picker-closed.png') });

      await hit(trigger);
      const panel = modal.locator('#intentSessionPanel');
      const option = panel.locator('.session-dd-item').first();
      await expect(panel).toBeVisible();
      const listFit = await panel.evaluate(el => {
        const box = el.getBoundingClientRect();
        const option = el.querySelector('.session-dd-item')!;
        const r = option.getBoundingClientRect();
        const hit = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
        const trigger = document.getElementById('intentSessionTrigger')!.getBoundingClientRect();
        return {
          inside: box.left >= 0 && box.right <= innerWidth && box.top >= 0 && box.bottom <= innerHeight,
          above: box.bottom < trigger.top,
          noHorizontalScroll: el.scrollWidth <= el.clientWidth + 1,
          optionHit: !!hit && option.contains(hit),
        };
      });
      expect(listFit).toMatchObject({ inside: true, noHorizontalScroll: true, optionHit: true });
      if (width <= 600) expect(listFit.above).toBe(true);
      await page.screenshot({ path: info.outputPath('picker-open.png') });
      const optionText = await option.innerText();
      await hit(option);
      await expect(panel).toBeHidden();
      await expect(label).toHaveText(optionText);
      await hit(modal.locator('#intentCancelBtn'));
      await expect(modal).toBeHidden();
      expect(page.url()).toBe(url);
    } finally {
      await context.close();
    }
  });
}
