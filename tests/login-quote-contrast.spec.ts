import { test, expect } from '@playwright/test';

/**
 * SDD `login-daily-quote` — contrast receipt (AC7).
 *
 * Measures the quote's rendered text colour against the ACTUAL painted pixel
 * behind it. The card is translucent over a photographic backdrop, so the
 * computed `background-color` is not the answer — the pixel is.
 *
 * Read-only. Run with `--project=chromium --no-deps`, `CRS_BASE=:8000`.
 */

function relativeLuminance([r, g, b]: number[]): number {
  const channel = (v: number) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

function contrast(a: number[], b: number[]): number {
  const la = relativeLuminance(a);
  const lb = relativeLuminance(b);
  const [hi, lo] = la > lb ? [la, lb] : [lb, la];
  return (hi + 0.05) / (lo + 0.05);
}

for (const theme of ['dark', 'light'] as const) {
  test(`${theme}: the quote clears 4.5:1 on its real background`, async ({ page }) => {
    await page.addInitScript((t) => localStorage.setItem('theme', t), theme);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/login/staff');

    // freeze the backdrop zoom so the sampled pixel is stable
    await page.addStyleTag({
      content: `*, *::before, *::after { animation: none !important; transition: none !important; }`,
    });
    await page.waitForTimeout(150);

    const quote = page.locator('.login-quote blockquote');
    await expect(quote).toBeVisible();

    // the computed text colour (resolved from the token)
    const fg = await quote.evaluate((el) => {
      const m = getComputedStyle(el).color.match(/[\d.]+/g)!.map(Number);
      return m.slice(0, 3);
    });

    // sample the painted background just outside the text, inside the card
    const box = (await quote.boundingBox())!;
    const shot = await page.screenshot({
      clip: {
        x: Math.round(box.x + box.width / 2) - 4,
        y: Math.round(box.y) - 6,          // 6px above the first line of text
        width: 8,
        height: 4,
      },
    });

    // decode the tiny PNG through the browser, which is easier than a PNG lib
    const bg = await page.evaluate(async (b64) => {
      const img = new Image();
      img.src = `data:image/png;base64,${b64}`;
      await img.decode();
      const c = document.createElement('canvas');
      c.width = img.width;
      c.height = img.height;
      const ctx = c.getContext('2d')!;
      ctx.drawImage(img, 0, 0);
      const d = ctx.getImageData(0, 0, c.width, c.height).data;
      let r = 0, g = 0, bl = 0;
      const n = d.length / 4;
      for (let i = 0; i < d.length; i += 4) { r += d[i]; g += d[i + 1]; bl += d[i + 2]; }
      return [Math.round(r / n), Math.round(g / n), Math.round(bl / n)];
    }, shot.toString('base64'));

    const ratio = contrast(fg, bg);
    console.log(
      `[contrast] ${theme}: fg rgb(${fg.join(',')}) on bg rgb(${bg.join(',')}) = ${ratio.toFixed(2)}:1`,
    );

    expect(ratio, `${theme} quote contrast`).toBeGreaterThanOrEqual(4.5);
  });
}
