import { expect, test } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/login-retry-guidance.spec.ts');

for (const [role, label, fictionalId] of [
  ['staff', 'Staff ID', 'P0000'], ['student', 'Student ID', '00ZZZ0000'],
] as const) {
  test(`${role}: an ordinary rejection explains how to retry`, async ({ browser, baseURL }, info) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 430 }, isMobile: true, hasTouch: true });
    try {
      const page = await context.newPage();
      await page.goto(`${baseURL}/login/${role}`);
      const id = page.locator('#login_id');
      const password = page.locator('#password');
      const button = page.locator('#loginBtn');
      await id.fill(fictionalId);
      await password.fill('fictional-example-not-a-password');
      await expect(button).toBeEnabled();
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), button.tap()]);
      await expect(page).toHaveURL(`${baseURL}/login/${role}`);

      const alert = page.getByRole('alert');
      const copy = ['These credentials do not match our records.', `Check your ${label} format and re-enter your password.`];
      if (role === 'staff') copy.push('Forgot your password? Reset it through the TARUMT intranet.');
      await expect(alert.locator('p')).toHaveText(copy);
      await expect(id).toHaveValue(fictionalId);
      await expect(id).not.toHaveAttribute('aria-invalid', 'true');
      await expect(password).toHaveValue('');
      await expect(button).toBeDisabled();

      for (const theme of ['dark', 'light']) {
        if (theme === 'light') {
          await page.locator('.theme-toggle').tap();
          await page.setViewportSize({ width: 320, height: 430 });
        }
        await expect(page.locator('html')).toHaveClass(theme);
        await alert.scrollIntoViewIfNeeded();
        await expect(alert).toBeInViewport({ ratio: 1 });
        const contrast = await alert.evaluate(el => {
          const style = getComputedStyle(el);
          const canvas = document.createElement('canvas');
          const ctx = canvas.getContext('2d')!;
          const rgb = (color: string) => {
            ctx.clearRect(0, 0, 1, 1);
            ctx.fillStyle = color;
            ctx.fillRect(0, 0, 1, 1);
            return Array.from(ctx.getImageData(0, 0, 1, 1).data);
          };
          const fg = rgb(style.color);
          const bg = rgb(style.backgroundColor);
          const lum = (channels: number[]) => channels.slice(0, 3).map(c => c / 255)
            .map(c => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)
            .reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
          const [low, high] = [lum(fg), lum(bg)].sort((a, b) => a - b);
          return { ratio: (high + 0.05) / (low + 0.05), alpha: bg[3], fits: el.scrollWidth <= el.clientWidth };
        });
        expect(contrast.alpha).toBe(255);
        expect(contrast.ratio).toBeGreaterThanOrEqual(4.5);
        expect(contrast.fits).toBe(true);
        await info.attach(`alert-${theme}`, { body: JSON.stringify(contrast), contentType: 'application/json' });
        await alert.screenshot({ path: info.outputPath(`alert-${theme}.png`) });
      }

      await password.tap();
      await expect(password).toBeFocused();
      await password.fill('another-browser-only-example');
      await expect(button).toBeEnabled();
      await button.scrollIntoViewIfNeeded();
      await expect(button).toBeInViewport({ ratio: 1 });
    } finally {
      await context.close();
    }
  });
}
