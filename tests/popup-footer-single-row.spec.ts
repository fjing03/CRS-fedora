import { test, expect, type Page } from '@playwright/test';
import { isSanctionedTestingTarget, GUARD_INVOCATION } from './helpers/receipt-target-guard';

/**
 * popup-footer-single-row — the focused receipt for P3-b of the
 * `filters-panel-and-popup-footer` lane (docs/ui-drift-2026-11-17.md §9).
 *
 * Defect: the phone detail footer stacked a full-width Close bar above a lone centred
 * pill (three blocks, not a footer). Cause: `.btn-close-modal` is its own class, so the
 * ≤520 secondary rule never reached it. Law now: one row — secondary hugs the left, the
 * action hugs the right, a primary that cannot share the row takes a full line underneath
 * — and every target is ≥44px tall (48 on touch). Above 520 nothing changes.
 *
 * Read-only: opens pop-ups, resizes, measures. No fixture, no DB write, and it refuses to
 * run off the testing lane (fail-closed, same guard as its neighbours).
 */

test.beforeAll(() => {
  if (!isSanctionedTestingTarget()) throw new Error(`Testing target required.\n${GUARD_INVOCATION}`);
});

interface FooterGeom {
  dir: string;
  justify: string;
  footer: { x: number; y: number; w: number; h: number };
  padLeft: number;
  padRight: number;
  boxes: { label: string; x: number; y: number; w: number; h: number }[];
}

async function measureFooter(page: Page, overlay = '#classModal'): Promise<FooterGeom | null> {
  return page.evaluate((selector) => {
    const footer = document.querySelector(`${selector} .modal--detail .modal-footer`) as HTMLElement | null;
    if (!footer || footer.offsetParent === null) return null;
    const cs = getComputedStyle(footer);
    const fr = footer.getBoundingClientRect();
    return {
      dir: cs.flexDirection,
      justify: cs.justifyContent,
      footer: { x: Math.round(fr.x), y: Math.round(fr.y), w: Math.round(fr.width), h: Math.round(fr.height) },
      padLeft: parseFloat(cs.paddingLeft),
      padRight: parseFloat(cs.paddingRight),
      boxes: [...footer.querySelectorAll('button, a')]
        .filter((el) => (el as HTMLElement).offsetParent !== null)
        .map((el) => {
          const r = el.getBoundingClientRect();
          return { label: (el.textContent || '').trim(), x: r.x, y: r.y, w: r.width, h: r.height };
        }),
    };
  }, overlay);
}

test('phone detail footer is one row: secondary left, action right, nothing centred alone', async ({ browser }) => {
  for (const width of [320, 390, 520] as const) {
    const context = await browser.newContext({
      storageState: '.auth/staff-5425.json',
      viewport: { width, height: 844 },
      hasTouch: true,
      isMobile: true,
    });
    const page = await context.newPage();
    try {
      await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
      await page.waitForTimeout(600);
      const block = page.locator('.event-block:visible').first();
      await expect(block).toBeVisible({ timeout: 20_000 });
      await block.tap();
      await page.waitForTimeout(500);

      const g = await measureFooter(page);
      expect(g, `${width}: the detail pop-up is open`).not.toBeNull();
      expect(g!.dir, `${width}: the footer is one row`).toBe('row');
      expect(g!.justify, `${width}: space-between places the row's ends`).toBe('space-between');
      expect(g!.boxes.length, `${width}: at least two actions`).toBeGreaterThanOrEqual(2);

      const contentLeft = g!.footer.x + g!.padLeft;
      const contentRight = g!.footer.x + g!.footer.w - g!.padRight;
      const first = g!.boxes[0];
      const last = g!.boxes[g!.boxes.length - 1];
      expect(Math.abs(first.x - contentLeft), `${width}: the secondary hugs the LEFT edge`).toBeLessThanOrEqual(2);
      expect(Math.abs(last.x + last.w - contentRight), `${width}: the action hugs the RIGHT edge`).toBeLessThanOrEqual(2);
      expect(Math.abs(first.y - last.y), `${width}: actions share the same row`).toBeLessThanOrEqual(1);
      for (const b of g!.boxes) {
        expect(b.h, `${width}: "${b.label}" is at least 48px tall`).toBeGreaterThanOrEqual(48);
        expect(b.w).toBeGreaterThanOrEqual(48);
      }
    } finally {
      await context.close();
    }
  }
});

test.use({ storageState: '.auth/staff-5425.json' });

test.describe('touch replacement details', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

  test('Close aligns left and Arrange spans the row below it', async ({ page }) => {
    await page.goto('/my-replacements', { waitUntil: 'networkidle' });
    await page.locator('#cardView .replacement-card:visible').first().tap();
    const footer = page.locator('#quickViewModal .modal-footer');
    await expect(footer.locator('#qvArrangeBtn')).toBeVisible();
    await expect.poll(async () => {
      const g = await measureFooter(page, '#quickViewModal');
      if (!g) return false;
      const close = g.boxes.find(b => b.label === 'Close');
      const arrange = g.boxes.find(b => b.label === 'Arrange Replacement');
      if (!close || !arrange) return false;
      const left = g.footer.x + g.padLeft;
      const width = g.footer.w - g.padLeft - g.padRight;
      return Math.abs(close.x - left) < 2 && Math.abs(arrange.x - left) < 2
        && Math.abs(arrange.w - width) < 2 && arrange.y >= close.y + close.h
        && close.h >= 48 && arrange.h >= 48;
    }).toBe(true);
    await footer.getByRole('button', { name: 'Close', exact: true }).tap();
    await expect(footer).toBeHidden();
  });
});

test('desktop detail footer is untouched at 1440: one row, compact buttons', async ({ browser }) => {
  const context = await browser.newContext({ storageState: '.auth/staff-5425.json', viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  try {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);
    const block = page.locator('.event-block:visible').first();
    await expect(block).toBeVisible({ timeout: 20_000 });
    await block.click();
    await page.waitForTimeout(500);

    const g = await measureFooter(page);
    expect(g, 'the detail pop-up is open').not.toBeNull();
    expect(g!.dir, 'the desktop footer is one row').toBe('row');
    for (const b of g!.boxes) expect(b.h, `"${b.label}" keeps its compact height`).toBeLessThanOrEqual(40);
  } finally {
    await context.close();
  }
});
