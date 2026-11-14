import { test, expect, type Page } from '@playwright/test';

/**
 * SDD guide-toggle-icon-only — one question-mark icon opens the inline guide,
 * and the page description lives inside the guide content.
 */

const LECTURER_PAGES = [
  { path: '/my-timetable-ui', desc: 'View your weekly class schedule' },
  { path: '/venue-timetable-ui', desc: "Inspect any venue's weekly occupancy" },
  { path: '/cohort-timetable', desc: 'View the weekly timetable' },
  { path: '/replacement-home-ui', desc: 'Every class that needs a replacement' },
  { path: '/replacement-arrangement', desc: null },
];

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

  for (const entry of LECTURER_PAGES) {
    test(`${entry.path}: one icon, old bar gone, description in the guide`, async ({ page }) => {
      await page.goto(entry.path, { waitUntil: 'domcontentloaded' });
      await waitForGuide(page);

      await expect(page.locator('.guide-toggle-icon')).toHaveCount(1);
      await expect(page.locator('.guide-toggle')).toHaveCount(0);

      if (entry.desc) {
        await expect(page.locator('.page-desc')).toHaveCount(0);
      }

      const icon = page.locator('.guide-toggle-icon');
      await expect(icon).toHaveAttribute('aria-expanded', 'false');
      await expect(page.locator('#guideBlock')).not.toHaveClass(/open/);

      await icon.click();
      await expect(page.locator('#guideBlock')).toHaveClass(/open/);
      await expect(icon).toHaveAttribute('aria-expanded', 'true');

      if (entry.desc) {
        await expect(page.locator('#guideBlock .guide-description')).toContainText(entry.desc);
      } else {
        await expect(page.locator('#guideBlock .guide-description')).toHaveCount(0);
        await expect(page.locator('#guideBlock .guide-list li').first()).toBeVisible();
      }

      const paddingTop = await page.locator('#guideBlock .guide-content').evaluate(
        (el) => parseFloat(getComputedStyle(el).paddingTop)
      );
      expect(paddingTop).toBeGreaterThanOrEqual(8);

      await icon.click();
      await expect(page.locator('#guideBlock')).not.toHaveClass(/open/);
      await expect(icon).toHaveAttribute('aria-expanded', 'false');
    });
  }
});

test.describe('staff PL page', () => {
  test.use({ storageState: '.auth/staff-5425.json' });

  test('/request-approval-ui: one icon and the description inside', async ({ page }) => {
    await page.goto('/request-approval-ui', { waitUntil: 'domcontentloaded' });
    await waitForGuide(page);
    await expect(page.locator('.guide-toggle')).toHaveCount(0);
    await expect(page.locator('.page-desc')).toHaveCount(0);
    await page.locator('.guide-toggle-icon').click();
    await expect(page.locator('#guideBlock .guide-description')).toContainText('Review and manage replacement requests');
  });
});

test('student pages: description-only guides, no empty list', async ({ page }) => {
  await page.goto('/login/student');
  await page.fill('#login_id', '25RSD0001');
  await page.fill('#password', 'Tarumt@2026');
  await page.click('button[type="submit"]');
  await page.waitForURL(/student-my-timetable/, { timeout: 30_000 });

  const pages = [
    { path: '/student-my-timetable-ui', desc: 'View your weekly class schedule' },
    { path: '/student-class-replacements', desc: 'Conflicted CLASSES and where each one' },
  ];
  for (const entry of pages) {
    await page.goto(entry.path, { waitUntil: 'domcontentloaded' });
    await waitForGuide(page);
    await expect(page.locator('.guide-toggle')).toHaveCount(0);
    await expect(page.locator('.guide-list')).toHaveCount(0);
    await expect(page.locator('.page-desc')).toHaveCount(0);
    await page.locator('.guide-toggle-icon').click();
    await expect(page.locator('#guideBlock .guide-description')).toContainText(entry.desc);
  }
});
