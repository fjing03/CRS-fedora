import { test, expect, type Page } from '@playwright/test';

// Guide content and interactions are tested in workflow-page-guides and timetable-page-guides.

async function waitForGuide(page: Page): Promise<void> {
  await page.waitForSelector('.guide-toggle-icon', { timeout: 15_000 });
}

async function iconBox(page: Page) {
  const box = await page.locator('.guide-toggle-icon').boundingBox();
  expect(box).not.toBeNull();
  return box as { x: number; y: number; width: number; height: number };
}

test.describe('staff lecturer pages', () => {
  test.use({ storageState: '.auth/staff-5770.json' });

  test('320/390/768/desktop: icon does not collide with the title or chips', async ({ page }) => {
    await page.setViewportSize({ width: 1360, height: 900 });
    for (const width of [320, 390, 768, 1360]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/my-timetable-ui', { waitUntil: 'domcontentloaded' });
      await waitForGuide(page);
      const icon = await iconBox(page);
      expect(icon.width).toBe(width <= 768 ? 44 : 40);
      expect(icon.height).toBe(width <= 768 ? 44 : 40);

      const title = page.locator('.page-title');
      const t = await title.boundingBox();
      expect(t).not.toBeNull();
      const overlaps = !(
        icon.x + icon.width <= (t as any).x ||
        (t as any).x + (t as any).width <= icon.x ||
        icon.y + icon.height <= (t as any).y ||
        (t as any).y + (t as any).height <= icon.y
      );
      expect(overlaps).toBe(false);
      expect(await title.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);

      const chip = page.locator('#semesterChip');
      if (await chip.isVisible()) {
        const c = await chip.boundingBox();
        expect(c).not.toBeNull();
        const chipOverlap = !(
          icon.x + icon.width <= (c as any).x ||
          (c as any).x + (c as any).width <= icon.x ||
          icon.y + icon.height <= (c as any).y ||
          (c as any).y + (c as any).height <= icon.y
        );
        expect(chipOverlap).toBe(false);
      }
    }
  });

});
