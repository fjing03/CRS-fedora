import { expect, test, type Page } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/login-short-viewport.spec.ts');

async function settle(page: Page) {
  await page.evaluate(async () => {
    await Promise.allSettled(document.getAnimations().filter(animation =>
      animation.effect?.getComputedTiming().endTime !== Infinity,
    ).map(animation => animation.finished));
    window.scrollTo(0, 0);
  });
}

for (const [role, valid, invalid] of [
  ['staff', 'P5425', '542'], ['student', '25RSD0001', '25RS0001'],
] as const) {
  for (const theme of ['dark', 'light'] as const) {
    for (const [width, height] of [[320, 430], [390, 430], [844, 390], [320, 568]]) {
      test(`${role} ${theme}: the compact form fits ${width}×${height}`, async ({ browser, baseURL }, info) => {
        const context = await browser.newContext({ viewport: { width, height }, hasTouch: true, isMobile: true });
        try {
          await context.addInitScript(value => localStorage.setItem('theme', value), theme);
          const page = await context.newPage();
          await page.goto(`${baseURL}/login/${role}`);
          const id = page.locator('#login_id');
          const password = page.locator('#password');
          const submit = page.locator('#loginBtn');
          const hint = page.locator('#loginIdHint');
          const themeButton = page.locator('.theme-toggle');
          const eye = page.locator('.toggle-pw');
          await expect(page.locator('.login-quote')).toBeHidden();
          await settle(page);
          await expect(id).toBeInViewport({ ratio: 1 });
          await expect(password).toBeInViewport({ ratio: 1 });
          await expect(submit).toBeInViewport({ ratio: 1 });
          await expect(themeButton).toBeInViewport({ ratio: 1 });

          await id.fill(invalid);
          await password.tap();
          await expect(hint).toHaveClass(/is-invalid/);
          await expect(submit).toBeDisabled();
          await settle(page);
          await expect(hint).toBeInViewport({ ratio: 1 });
          await expect(password).toBeInViewport({ ratio: 1 });
          await expect(submit).toBeInViewport({ ratio: 1 });
          for (const control of [id, password, eye, themeButton, submit]) {
            const box = (await control.boundingBox())!;
            expect(box.width).toBeGreaterThanOrEqual(48);
            expect(box.height).toBeGreaterThanOrEqual(48);
          }
          const geometry = await page.evaluate(() => ({
            overflow: document.scrollingElement!.scrollWidth - innerWidth,
            buttonBottom: document.querySelector('#loginBtn')!.getBoundingClientRect().bottom,
            coarse: matchMedia('(any-pointer: coarse)').matches,
          }));
          expect(geometry.overflow).toBe(0);
          expect(geometry.coarse).toBe(true);
          await info.attach('geometry', { body: JSON.stringify(geometry), contentType: 'application/json' });
          await page.screenshot({ path: info.outputPath('compact-login.png') });
          await id.fill(valid);
          await password.fill('browser-only-example');
          await eye.tap();
          await expect(password).toHaveAttribute('type', 'text');
          await expect(submit).toBeEnabled();
        } finally {
          await context.close();
        }
      });
    }
  }

  test(`${role}: resize during entry and keep room to scroll after a server alert`, async ({ browser, baseURL }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
    try {
      const page = await context.newPage();
      await page.goto(`${baseURL}/login/${role}`);
      const id = page.locator('#login_id');
      await id.tap();
      await id.fill(valid);
      await page.setViewportSize({ width: 390, height: 430 });
      await expect(id).toBeFocused();
      await expect(id).toHaveValue(valid);
      await settle(page);
      const submit = page.locator('#loginBtn');
      await expect(submit).toBeInViewport({ ratio: 1 });

      // Browser-only alert: verify flow without making a login attempt.
      await page.locator('.login-card form').evaluate(form => {
        const alert = document.createElement('div');
        alert.className = 'error-msg';
        alert.textContent = 'These credentials do not match our records.';
        form.prepend(alert);
      });
      await page.setViewportSize({ width: 320, height: 300 });
      await submit.scrollIntoViewIfNeeded();
      await expect(submit).toBeInViewport({ ratio: 1 });
      await page.locator('#password').tap();
      await expect(page.locator('#password')).toBeFocused();
      const footerLink = page.locator('footer a').last();
      await footerLink.scrollIntoViewIfNeeded();
      await expect(footerLink).toBeInViewport({ ratio: 1 });
    } finally {
      await context.close();
    }
  });

  test(`${role}: normal masthead and quote gates are preserved at roomy sizes`, async ({ page }) => {
    for (const [width, height, quote] of [
      [390, 844, false], [768, 900, false], [1024, 700, false], [1024, 839, false],
      [769, 840, true], [1440, 900, true],
    ] as const) {
      await page.setViewportSize({ width, height });
      await page.goto(`/login/${role}`);
      await expect(page.locator('.login-heading')).toHaveCSS('display', 'contents');
      if (quote) await expect(page.locator('.login-quote')).toBeVisible();
      else await expect(page.locator('.login-quote')).toBeHidden();
      await settle(page);
      await expect(page.locator('#loginBtn')).toBeInViewport({ ratio: 1 });
    }
  });
}
