import { test, expect } from '@playwright/test';

/**
 * SDD `login-daily-quote` — AC6's second clause, which the main spec left
 * unmeasured: below the gate the form must be reached WITHOUT scrolling past a
 * hidden quote, i.e. the phone/short-window layout is the pre-lane layout.
 *
 * Read-only. `--project=chromium --no-deps`, `CRS_BASE=:8000`.
 */

async function settle(page: import('@playwright/test').Page): Promise<void> {
  await page.addStyleTag({
    content: `*, *::before, *::after { animation: none !important; transition: none !important; }`,
  });
  await page.evaluate(
    () => new Promise<void>((r) => requestAnimationFrame(() => requestAnimationFrame(() => r()))),
  );
}

test('below the gate the Log in button is reachable without scrolling past the quote', async ({
  page,
}) => {
  for (const vp of [
    { width: 768, height: 800 },
    { width: 769, height: 699 },
    { width: 390, height: 844 },
  ]) {
    await page.setViewportSize(vp);
    await page.goto('/login/staff');
    await settle(page);

    // the quote contributes no height at all — it is display:none, not hidden
    const quoteHeight = await page
      .locator('.login-quote')
      .evaluate((el) => el.getBoundingClientRect().height);
    expect(quoteHeight, `${vp.width}x${vp.height} quote must take zero space`).toBe(0);

    // and the button is inside the viewport, so no scroll is needed to reach it
    const box = (await page.locator('#loginBtn').boundingBox())!;
    const bottom = Math.round(box.y + box.height);
    console.log(`[below-gate] ${vp.width}x${vp.height} button bottom=${bottom}`);

    expect(
      bottom,
      `${vp.width}x${vp.height}: the form must not be pushed out of view`,
    ).toBeLessThanOrEqual(vp.height);
  }
});
