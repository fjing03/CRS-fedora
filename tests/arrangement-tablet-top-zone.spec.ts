import { expect, test } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/arrangement-tablet-top-zone.spec.ts');

const BASE = process.env.CRS_BASE;
const PINNED = '/replacement-arrangement?session=49&week=14&from=my-timetable';
const STAFF = '.auth/staff-5770.json';

for (const [width, height, touch, theme] of [
  [768, 1024, true, 'dark'], [769, 1024, true, 'light'], [820, 1180, true, 'light'],
  [1024, 768, true, 'dark'], [1024, 768, false, 'light'],
  [1100, 768, false, 'light'], [1101, 768, false, 'dark'],
] as const) {
  test(`Arrangement keeps the right setup layout at ${width}×${height} (${touch ? 'touch' : 'mouse'})`, async ({ browser }, info) => {
    const context = await browser.newContext({ storageState: STAFF, viewport: { width, height }, isMobile: touch, hasTouch: touch });
    try {
      await context.addInitScript(value => localStorage.setItem('theme', value), theme);
      const page = await context.newPage();
      await page.goto(`${BASE}${PINNED}`, { waitUntil: 'networkidle' });
      const zone = page.locator('#topZone');
      const grid = page.locator('#gridScroll');
      if (width === 768) {
        await expect(page.locator('body')).toHaveClass(/arrangement-mobile-mode/);
        await expect(grid).toBeHidden();
        return;
      }
      await expect(page.locator('#classDetailsCard')).toHaveClass(/has-slot/);
      const boxes = await zone.locator(':scope > .ra-block').evaluateAll(cards => cards.map(card => {
        const { left, right, top } = card.getBoundingClientRect();
        return { left, right, top, overflow: card.scrollWidth - card.clientWidth };
      }));
      expect(boxes).toHaveLength(4);
      const [conflict, details, replacement, summary] = boxes;
      expect(details.top).toBe(conflict.top);
      expect(details.left).toBeGreaterThan(conflict.right);
      if (width === 1101) {
        expect(replacement.top).toBe(conflict.top);
        expect(summary.top).toBeGreaterThan(replacement.top);
        return;
      }
      expect(replacement.top).toBeGreaterThan(conflict.top);
      expect(replacement.top).toBe(summary.top);
      expect(replacement.left).toBe(conflict.left);
      expect(summary.left).toBe(details.left);
      expect(boxes.every(card => card.overflow <= 1 && card.right <= width)).toBe(true);
      const gridBox = await grid.boundingBox();
      expect(gridBox).not.toBeNull();
      expect(gridBox!.y + 60).toBeLessThan(height);
      if (width !== 1024) return;
      await page.screenshot({ path: info.outputPath('setup.png') });

      const collapse = page.locator('#conflictCollapse');
      const toggle = async () => touch ? collapse.tap() : collapse.click();
      await expect(collapse).toHaveAttribute('aria-expanded', 'true');
      await toggle();
      await expect(collapse).toHaveAttribute('aria-expanded', 'false');
      await toggle();
      await expect(collapse).toHaveAttribute('aria-expanded', 'true');
      const cell = page.locator('#tableBody .cell-content.cell-available').first();
      if (touch) await cell.tap();
      else await cell.click();
      await expect(page.locator('#summaryGrid .sel-summary-card')).toBeVisible();
    } finally {
      await context.close();
    }
  });
}

test('without an original slot, tablet hides empty Class Details and keeps the Selection Summary full-width', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 1024, height: 768 }, storageState: STAFF });
  try {
    const page = await context.newPage();
    await page.goto(`${BASE}/replacement-arrangement`, { waitUntil: 'networkidle' });
    await expect(page.locator('#classDetailsCard')).toBeHidden();
    const conflict = await page.locator('#subjectInfo').boundingBox();
    const replacement = await page.locator('#replacementHeading').locator('xpath=ancestor::section[1]').boundingBox();
    const summary = await page.locator('#selSummary').boundingBox();
    expect(replacement!.y).toBe(conflict!.y);
    expect(summary!.y).toBeGreaterThan(replacement!.y);
    expect(summary!.x).toBe(conflict!.x);
    expect(summary!.x + summary!.width).toBeCloseTo(replacement!.x + replacement!.width);
  } finally {
    await context.close();
  }
});
