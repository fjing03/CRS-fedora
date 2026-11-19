import { test, expect } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/login-id-format-feedback.spec.ts');

const roles = [
  { role: 'staff', partial: '542', alternate: 'p5425', valid: ['5425', 'P5425'],
    correction: 'Enter 4 digits, with or without an uppercase P.', hint: '4 digits, optional "P" prefix' },
  { role: 'student', partial: '25RS0001', alternate: '25R1D0001', valid: ['25RSD0001', '25rsd0001'],
    correction: 'Enter 2 digits, 3 letters, then 4 digits (for example, 25RSD0001).', hint: 'Format: 2 digits + 3 letters + 4 digits' },
];

for (const { role, partial, alternate, valid, correction, hint } of roles) {
  for (const { width, height, theme } of [
    { width: 1440, height: 900, theme: 'dark' }, { width: 390, height: 430, theme: 'dark' },
    { width: 320, height: 568, theme: 'light' }, { width: 320, height: 430, theme: 'light' },
  ]) {
    test(`${role}: inline correction at ${width}×${height} ${theme}`, async ({ browser, baseURL }, info) => {
      const touch = width < 768;
      const context = await browser.newContext({ viewport: { width, height }, hasTouch: touch, isMobile: touch });
      try {
        if (theme === 'light') await context.addInitScript(() => localStorage.setItem('theme', 'light'));
        const page = await context.newPage();
        await page.goto(`${baseURL}/login/${role}`);
        await expect(page.locator('html')).toHaveClass(theme);
        const id = page.locator('#login_id');
        const password = page.locator('#password');
        const submit = page.locator('#loginBtn');
        const message = page.locator('#loginIdHint');
        await expect(id).toHaveAttribute('aria-describedby', 'loginIdHint');
        await expect(message).toHaveText(hint);
        await expect(submit).toBeDisabled();

        await id.fill(partial);
        await expect(message).toHaveText(hint);
        if (touch) await password.tap();
        else await id.press('Tab');
        await expect(password).toBeFocused();
        await password.fill('browser-only-example');
        await expect(message).toHaveText(correction);
        await expect(id).toHaveAttribute('aria-invalid', 'true');
        await expect(submit).toBeDisabled();
        await message.scrollIntoViewIfNeeded();
        const rendered = await message.evaluate(el => {
          const style = getComputedStyle(el);
          const lum = (rgb: string) => (rgb.match(/[\d.]+/g) ?? []).slice(0, 3).map(n => Number(n) / 255)
            .map(v => v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4)
            .reduce((a, v, i) => a + v * [0.2126, 0.7152, 0.0722][i], 0);
          const [a, b] = [lum(style.color), lum(style.backgroundColor)].sort((x, y) => x - y);
          const range = document.createRange();
          range.selectNodeContents(el);
          return { contrast: (b + 0.05) / (a + 0.05), opacity: style.opacity,
            fits: Array.from(range.getClientRects()).every(r => r.left >= 0 && r.right <= innerWidth),
            lines: range.getClientRects().length, noPageOverflow: document.scrollingElement!.scrollWidth === innerWidth };
        });
        expect(rendered.contrast).toBeGreaterThanOrEqual(4.5);
        expect(rendered.opacity).toBe('1');
        expect(rendered.fits).toBe(true);
        expect(rendered.noPageOverflow).toBe(true);
        if (width === 320 && role === 'student') expect(rendered.lines).toBeGreaterThan(1);
        await info.attach('rendered', { body: JSON.stringify(rendered), contentType: 'application/json' });
        await page.screenshot({ path: info.outputPath('inline-feedback.png') });

        await id.fill(alternate);
        await expect(message).toHaveText(correction);
        await expect(submit).toBeDisabled();
        await id.fill('   ');
        await expect(message).toHaveText(hint);
        await expect(id).not.toHaveAttribute('aria-invalid', 'true');
        await id.blur();
        await expect(message).toHaveText(hint);
        for (const value of valid) {
          await id.fill(value);
          await expect(submit).toBeEnabled();
          await expect(message).toHaveText(hint);
        }
        await id.fill(partial);
        await expect(message).toHaveText(hint);
        await id.blur();
        await expect(message).toHaveText(correction);
        await id.fill(valid[0]);
        await expect(message).toHaveText(hint);
        await expect(id).not.toHaveAttribute('aria-invalid', 'true');
        await expect(submit).toBeEnabled();
      } finally {
        await context.close();
      }
    });
  }
}
