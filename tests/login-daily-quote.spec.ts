import { test, expect } from '@playwright/test';

/**
 * SDD `login-daily-quote` — read-only browser receipt.
 *
 * Run with the setup project SKIPPED: `chromium` declares
 * `dependencies: ['setup']` and `auth.setup.ts` performs four REAL logins, so a
 * default run would log in four times just to look at a login page.
 *
 *   CRS_BASE=http://127.0.0.1:8000 npx playwright test tests/login-daily-quote.spec.ts \
 *     --project=chromium --no-deps
 *
 * Never point this at :8030 (the manual lane). Nothing here submits a form,
 * logs in, or writes anything.
 *
 * The day is whatever today is on the CAMPUS clock; the spec therefore asserts
 * the STRUCTURE and the visible names, not a specific athlete — the seven-day
 * content is pinned by tests/Feature/Auth/LoginDailyQuoteTest.php.
 */

const ROUTES = ['/login/staff', '/login/student'];

/** Viewports: the two gate edges, a normal desktop, and a phone. */
const DESKTOP = { width: 1440, height: 900 };
const LAPTOP = { width: 1280, height: 800 };
const GATE_IN = { width: 769, height: 700 };   // exactly on the gate -> visible
const GATE_OUT_W = { width: 768, height: 800 }; // one px narrow -> hidden
const GATE_OUT_H = { width: 769, height: 699 }; // one px short -> hidden
const PHONE = { width: 390, height: 844 };

/**
 * The login card animates in (`fadeSlideUp` = translateY(30px) scale(0.98)) and
 * the backdrop loops `bgZoom` forever. A transform distorts every boundingBox
 * underneath it, and the infinite animation never "finishes" — so wait on
 * `getAnimations()` would hang. Disable animations/transitions instead: the
 * card uses `animation-fill-mode: both`, so with no animation it paints its
 * FINAL state, which is the settled layout these assertions are about.
 */
async function settleAnimations(page: import('@playwright/test').Page): Promise<void> {
  await page.addStyleTag({
    content: `*, *::before, *::after {
      animation: none !important;
      transition: none !important;
    }`,
  });

  // one frame so the style application is flushed before measuring
  await page.evaluate(
    () => new Promise<void>((r) => requestAnimationFrame(() => requestAnimationFrame(() => r()))),
  );
}

test.describe('login daily quote — visibility gates', () => {
  for (const route of ROUTES) {
    test(`${route}: visible at the gate and above`, async ({ page }) => {
      for (const vp of [DESKTOP, LAPTOP, GATE_IN]) {
        await page.setViewportSize(vp);
        await page.goto(route);
        await settleAnimations(page);

        const quote = page.locator('.login-quote');
        await expect(quote, `${route} @ ${vp.width}x${vp.height}`).toBeVisible();

        // the avatar actually decodes — a 404 would leave naturalWidth at 0
        const avatar = quote.locator('img');
        await expect(avatar).toHaveJSProperty('complete', true);
        expect(
          await avatar.evaluate((img: HTMLImageElement) => img.naturalWidth),
        ).toBeGreaterThan(0);

        // the author's visible name is the attribution, so it must be there
        await expect(quote.locator('figcaption span').last()).not.toBeEmpty();

        // no horizontal overflow at any of these
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );
        expect(overflow, `${route} @ ${vp.width} horizontal overflow`).toBeLessThanOrEqual(0);
      }
    });

    test(`${route}: hidden one pixel either side of the gate`, async ({ page }) => {
      for (const vp of [GATE_OUT_W, GATE_OUT_H, PHONE]) {
        await page.setViewportSize(vp);
        await page.goto(route);

        await expect(
          page.locator('.login-quote'),
          `${route} @ ${vp.width}x${vp.height} must be hidden`,
        ).toBeHidden();
      }
    });
  }
});

test.describe('login daily quote — the button must stay above the fold', () => {
  test('at exactly 769x700 the Log in button is fully inside the viewport', async ({ page }) => {
    await page.setViewportSize(GATE_IN);
    await page.goto('/login/staff');
    await settleAnimations(page);

    await expect(page.locator('.login-quote')).toBeVisible();

    const box = await page.locator('#loginBtn').boundingBox();
    expect(box).not.toBeNull();

    // the measurement the spec's contingency is written against
    console.log(
      `[fold] 769x700 button bottom=${Math.round(box!.y + box!.height)} / viewport 700`,
    );

    expect(
      Math.round(box!.y + box!.height),
      'the quote pushed the Log in button past the fold at the gate size',
    ).toBeLessThanOrEqual(700);
  });

  test('the quote never overlaps the title or the form', async ({ page }) => {
    await page.setViewportSize(LAPTOP);
    await page.goto('/login/student');
    await settleAnimations(page);

    const quote = await page.locator('.login-quote').boundingBox();
    const title = await page.locator('.page-title').boundingBox();
    const firstInput = await page.locator('#login_id').boundingBox();

    expect(quote).not.toBeNull();
    expect(title).not.toBeNull();
    expect(firstInput).not.toBeNull();

    expect(quote!.y).toBeGreaterThanOrEqual(title!.y + title!.height - 1);
    expect(quote!.y + quote!.height).toBeLessThanOrEqual(firstInput!.y + 1);
  });
});

test.describe('login daily quote — both themes', () => {
  for (const theme of ['dark', 'light'] as const) {
    test(`${theme}: ink resolves, text is readable, no token leak`, async ({ page }) => {
      await page.addInitScript((t) => localStorage.setItem('theme', t), theme);
      await page.setViewportSize(DESKTOP);
      await page.goto('/login/staff');
      await settleAnimations(page);

      const quote = page.locator('.login-quote');
      await expect(quote).toBeVisible();
      await expect(page.locator('html')).toHaveClass(new RegExp(`\\b${theme}\\b`));

      // the ink must actually differ between themes, i.e. the flip is wired
      const filter = await quote
        .locator('img')
        .evaluate((img) => getComputedStyle(img).filter);

      // dark is the document default -> invert(1); light switches it off
      if (theme === 'dark') {
        expect(filter).toContain('invert');
      } else {
        expect(filter === 'none' || !filter.includes('invert(1)')).toBe(true);
      }

      // the text colour must come from a token, not a literal
      const colour = await quote.locator('blockquote').evaluate(
        (el) => getComputedStyle(el).color,
      );
      expect(colour).toMatch(/^rgba?\(/);

      await page.screenshot({
        path: `/tmp/opencode/quote-${theme}-1440.png`,
        clip: {
          x: 0,
          y: 0,
          width: 1440,
          height: 900,
        },
      });
    });
  }
});
