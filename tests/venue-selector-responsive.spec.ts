import { test, expect, type Page } from '@playwright/test';

/**
 * venue-timetable-ui — venue selector parity with replacement-arrangement.
 *
 * 1. The trigger uses the darker `surface-variant` fill, not white surface.
 * 2. The four-column panel stays inside the viewport at narrow desktop widths
 *    (the DevTools case); hover-revealed columns must re-clamp.
 * 3. At <=768px the cascade is replaced by one searchable bottom sheet.
 */
test.use({ storageState: '.auth/staff-5770.json' });

const PAGE = '/venue-timetable-ui';
const TRIGGER = '#venueSelectDropdown .venue-dd-trigger';
const PANEL = '#venueSelectDropdown .venue-dd-panel';
const SHEET = '#venueTimetablePicker';

async function openVenuePage(page: Page): Promise<void> {
  await page.goto(PAGE, { waitUntil: 'networkidle' });
  await page.locator(TRIGGER).waitFor({ state: 'visible', timeout: 15_000 });
  await page.waitForTimeout(400);
}

async function revealAllColumns(page: Page): Promise<void> {
  const panel = page.locator(PANEL);
  await panel.locator('.venue-col').nth(0).getByText('Tutorial').first().hover();
  await expect(panel.locator('.venue-col.visible')).toHaveCount(2);
  await panel.locator('.venue-col').nth(1).getByText('Block B').first().hover();
  await expect(panel.locator('.venue-col.visible')).toHaveCount(3);
  await panel.locator('.venue-col').nth(2).getByText('Ground Floor').first().hover();
  await expect(panel.locator('.venue-col.visible')).toHaveCount(4);
  await page.waitForTimeout(200);
}

test('trigger uses the darker surface-variant fill, not white', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openVenuePage(page);

  const colours = await page.evaluate(() => {
    const trigger = document.querySelector('#venueSelectDropdown .venue-dd-trigger') as HTMLElement;
    const root = document.documentElement;
    const probe = document.createElement('div');
    probe.style.background = 'var(--color-surface-variant)';
    root.appendChild(probe);
    const variant = getComputedStyle(probe).backgroundColor;
    probe.style.background = 'var(--color-surface)';
    const surface = getComputedStyle(probe).backgroundColor;
    probe.remove();
    return { trigger: getComputedStyle(trigger).backgroundColor, variant, surface };
  });

  expect(colours.trigger).toBe(colours.variant);
  expect(colours.trigger).not.toBe(colours.surface);
});

test('820px: four revealed columns stay 12px inside the viewport', async ({ page }) => {
  await page.setViewportSize({ width: 820, height: 900 });
  await openVenuePage(page);

  await page.locator(TRIGGER).click();
  await expect(page.locator('#venueSelectDropdown')).toHaveClass(/open/);
  await revealAllColumns(page);

  const panel = page.locator(PANEL);
  const box = await panel.boundingBox();
  expect(box).not.toBeNull();
  expect((box as any).x).toBeGreaterThanOrEqual(11);
  expect((box as any).x + (box as any).width).toBeLessThanOrEqual(820 - 11);
  expect(await panel.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);

  const columns = page.locator(`${PANEL} .venue-col.visible`);
  await expect(columns).toHaveCount(4);
  for (let i = 0; i < 4; i++) {
    const col = await columns.nth(i).boundingBox();
    expect(col).not.toBeNull();
    expect((col as any).x).toBeGreaterThanOrEqual((box as any).x - 1);
    expect((col as any).x + (col as any).width).toBeLessThanOrEqual((box as any).x + (box as any).width + 1);
  }
});

test('mobile: the cascade is replaced by a searchable sheet that fits', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await openVenuePage(page);

  await page.locator(TRIGGER).click();
  const sheet = page.locator(SHEET);
  await expect(sheet).toBeVisible();
  /* the wide cascade must not open behind/above the sheet */
  await expect(page.locator('#venueSelectDropdown')).not.toHaveClass(/open/);
  expect(await sheet.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);

  const search = sheet.locator('[data-venue-picker-search]');
  await search.fill('B010');
  const items = sheet.locator('.venue-picker-item');
  await expect(items).toHaveCount(1);
  await expect(items.first()).toContainText('B010');

  await search.fill('');
  await expect(sheet.locator('.venue-picker-item').first()).toBeVisible();
  await expect(sheet.locator('.venue-picker-group').first()).toContainText('Block');

  await sheet.locator('[data-venue-picker-close]').click();
  await expect(sheet).toBeHidden();
});

test('mobile: category headers are distinct bars with collapse/expand arrows', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await openVenuePage(page);

  await page.locator(TRIGGER).click();
  const sheet = page.locator(SHEET);
  await expect(sheet).toBeVisible();

  const groups = sheet.locator('.venue-picker-group');
  const count = await groups.count();
  expect(count).toBeGreaterThan(1);

  /* darker fill than the sheet body, so headers read as bars not rows */
  const colours = await sheet.evaluate((el) => {
    const group = el.querySelector('.venue-picker-group') as HTMLElement;
    const item = el.querySelector('.venue-picker-item') as HTMLElement;
    return {
      group: getComputedStyle(group).backgroundColor,
      item: getComputedStyle(item).backgroundColor,
      body: getComputedStyle(el).backgroundColor,
    };
  });
  expect(colours.group).not.toBe(colours.item);
  expect(colours.group).not.toBe(colours.body);

  /* every header carries an arrow and owns its item container */
  for (let i = 0; i < count; i++) {
    const group = groups.nth(i);
    await expect(group).toHaveJSProperty('tagName', 'BUTTON');
    await expect(group.locator('svg.venue-picker-group-chevron')).toHaveCount(1);
    const controls = await group.getAttribute('aria-controls');
    expect(controls).toBeTruthy();
    await expect(page.locator(`#${controls}`)).toHaveCount(1);
  }

  /* default: only the first category is expanded */
  await expect(groups.first()).toHaveAttribute('aria-expanded', 'true');
  for (let i = 1; i < count; i++) {
    await expect(groups.nth(i)).toHaveAttribute('aria-expanded', 'false');
  }

  const firstItems = page.locator(`#${await groups.first().getAttribute('aria-controls')}`);
  await expect(firstItems).toBeVisible();
  const secondItems = page.locator(`#${await groups.nth(1).getAttribute('aria-controls')}`);
  await expect(secondItems).toBeHidden();

  /* clicking the header collapses the first and expands the second */
  await groups.first().click();
  await expect(groups.first()).toHaveAttribute('aria-expanded', 'false');
  await expect(firstItems).toBeHidden();

  await groups.nth(1).click();
  await expect(groups.nth(1)).toHaveAttribute('aria-expanded', 'true');
  await expect(secondItems).toBeVisible();
  await expect(secondItems.locator('.venue-picker-item').first()).toBeVisible();

  /* arrow direction flips with the state */
  const openRotation = await groups
    .nth(1)
    .locator('svg')
    .evaluate((el) => getComputedStyle(el).transform);
  await groups.nth(1).click();
  await expect(groups.nth(1)).toHaveAttribute('aria-expanded', 'false');
  await expect
    .poll(() => groups.nth(1).locator('svg').evaluate((el) => getComputedStyle(el).transform))
    .not.toBe(openRotation);
});

test('mobile: search still filters and reveals its matches in collapsed groups', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await openVenuePage(page);

  await page.locator(TRIGGER).click();
  const sheet = page.locator(SHEET);
  await expect(sheet).toBeVisible();

  /* B100 sits on Floor 1, whose group starts collapsed */
  await sheet.locator('[data-venue-picker-search]').fill('B100');
  const items = sheet.locator('.venue-picker-item');
  await expect(items).toHaveCount(1);
  await expect(items.first()).toContainText('B100');
  /* a search hit must never be hidden behind a collapsed header */
  await expect(items.first()).toBeVisible();

  await sheet.locator('[data-venue-picker-search]').fill('zzzz');
  await expect(sheet.locator('.venue-picker-empty')).toBeVisible();
  await expect(items).toHaveCount(0);
});

test('mobile: picking a venue navigates through the existing ?venue= render', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await openVenuePage(page);

  const current = await page.locator(`${TRIGGER} .venue-dd-label`).textContent();
  const currentCode = (current || '').split('\u2014')[0].trim();

  await page.locator(TRIGGER).click();
  const sheet = page.locator(SHEET);
  await expect(sheet).toBeVisible();

  const target = await sheet.locator('.venue-picker-item').evaluateAll(
    (items, code) => {
      const other = items.find((item) => item.getAttribute('data-venue-code') !== code);
      return other ? other.getAttribute('data-venue-code') : null;
    },
    currentCode,
  );
  test.skip(target === null, 'the served venue list offers only the current room');

  await Promise.all([
    page.waitForURL(new RegExp(`venue=${target}`), { timeout: 15_000 }),
    sheet.locator(`.venue-picker-item[data-venue-code="${target}"]`).click(),
  ]);
  await expect(page.locator(SHEET)).toHaveCount(1);
  await expect(page.locator(SHEET)).toBeHidden();
});
