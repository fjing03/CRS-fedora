import { test, expect, type Page } from '@playwright/test';

/**
 * Responsive venue picker + full-width week navigation.
 *
 * 1. `.venue-dd-panel` shrinks to the viewport and is re-clamped when hover
 *    reveals more columns (the old fit ran once, before the panel grew).
 * 2. `.week-nav` / `.week-select` consume the full card width.
 * 3. <=768px uses the searchable venue sheet, not the four-column dropdown.
 */
test.use({ storageState: '.auth/staff-5770.json' });

async function openArrangement(page: Page): Promise<void> {
  await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
  /* #subjectInfo is intentionally hidden inside the mobile shell, so wait on
     the venue trigger — it is visible in both lanes (relocated on mobile). */
  await page.locator('#buildingSelectorDropdown .venue-dd-trigger').waitFor({ state: 'visible', timeout: 15_000 });
  await page.waitForTimeout(700);
}

async function openPanel(page: Page): Promise<void> {
  await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
  await expect(page.locator('#buildingSelectorDropdown')).toHaveClass(/open/);
  await expect(page.locator('#buildingSelectorDropdown .venue-col.visible')).toHaveCount(1);
}

async function revealAllColumns(page: Page): Promise<void> {
  const panel = page.locator('#buildingSelectorDropdown .venue-dd-panel');
  await panel.locator('.venue-col').nth(0).getByText('Tutorial').first().hover();
  await expect(panel.locator('.venue-col.visible')).toHaveCount(2);
  await panel.locator('.venue-col').nth(1).getByText('Block B').first().hover();
  await expect(panel.locator('.venue-col.visible')).toHaveCount(3);
  await panel.locator('.venue-col').nth(2).getByText('Ground Floor').first().hover();
  await expect(panel.locator('.venue-col.visible')).toHaveCount(4);
  await page.waitForTimeout(150);
}

test('wide desktop: week nav fills the card and the select takes the slack', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openArrangement(page);

  const nav = page.locator('.ra-replacement-week .week-nav');
  const select = page.locator('.ra-replacement-week #weekSelector');
  const navBox = await nav.boundingBox();
  const selectBox = await select.boundingBox();
  expect(navBox).not.toBeNull();
  expect(selectBox).not.toBeNull();

  expect(Math.abs((navBox as any).width - (selectBox as any).width)).toBeGreaterThan(60);
  expect((selectBox as any).width).toBeGreaterThan((navBox as any).width * 0.6);

  const leftArrow = await page.locator('.ra-replacement-week .week-arrow').first().boundingBox();
  const rightArrow = await page.locator('.ra-replacement-week .week-arrow').last().boundingBox();
  expect(Math.abs((leftArrow as any).x - (navBox as any).x)).toBeLessThanOrEqual(1.5);
  expect(Math.abs(((rightArrow as any).x + (rightArrow as any).width) - ((navBox as any).x + (navBox as any).width))).toBeLessThanOrEqual(1.5);
});

test('narrow desktop: all four venue columns stay inside the viewport', async ({ page }) => {
  await page.setViewportSize({ width: 820, height: 900 });
  await openArrangement(page);
  await openPanel(page);
  await revealAllColumns(page);

  const panel = page.locator('#buildingSelectorDropdown .venue-dd-panel');
  const box = await panel.boundingBox();
  expect(box).not.toBeNull();
  expect((box as any).x).toBeGreaterThanOrEqual(11);
  expect((box as any).x + (box as any).width).toBeLessThanOrEqual(820 - 11);
  expect(await panel.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);

  const columns = page.locator('#buildingSelectorDropdown .venue-col.visible');
  await expect(columns).toHaveCount(4);
  for (let i = 0; i < 4; i++) {
    const col = await columns.nth(i).boundingBox();
    expect(col).not.toBeNull();
    expect((col as any).x).toBeGreaterThanOrEqual((box as any).x - 1);
    expect((col as any).x + (col as any).width).toBeLessThanOrEqual((box as any).x + (box as any).width + 1);
  }
});

test('mobile: venue trigger opens the searchable sheet, selection survives', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await openArrangement(page);
  await expect(page.locator('body')).toHaveClass(/arrangement-mobile-mode/);

  await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
  const dialog = page.locator('#arrangementMobileVenue');
  await expect(dialog).toBeVisible();
  await expect(page.locator('#buildingSelectorDropdown')).not.toHaveClass(/open/);

  const fits = await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth + 1);
  expect(fits).toBe(true);

  const search = dialog.locator('[data-arrangement-mobile-venue-search]');
  await search.fill('B010');
  const items = dialog.locator('.arrangement-mobile-venue-item');
  await expect(items).toHaveCount(1);
  await expect(items.first()).toContainText('B010');
  await items.first().click();

  await expect(dialog).toBeHidden();
  await expect(page.locator('#buildingSelectorDropdown .venue-dd-trigger')).toContainText('B010');
  await expect(page.locator('#buildingSelectorDropdown')).not.toHaveClass(/open/);

  await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
  await expect(dialog).toBeVisible();
  await expect(dialog.locator('.arrangement-mobile-venue-item.selected')).toContainText('B010');
  await dialog.locator('[data-arrangement-mobile-venue-close]').click();
  await expect(dialog).toBeHidden();
});

/**
 * 2026-11-14 — `arrangement-venue-picker-collapse`: the arrangement sheet's grey
 * category captions became folding bars. `aria-expanded` on the header button is
 * the only state; a re-render (open / every keystroke) deliberately resets it.
 */
test('mobile: venue category headers fold, and a search opens every matching group', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await openArrangement(page);
  await expect(page.locator('body')).toHaveClass(/arrangement-mobile-mode/);

  const dialog = page.locator('#arrangementMobileVenue');
  await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
  await expect(dialog).toBeVisible();

  const headers = dialog.locator('.arrangement-mobile-venue-group');
  expect(await headers.count(), 'more than one group, or folding proves nothing').toBeGreaterThan(1);

  /* 1. default: first open, the rest closed — and the state is on the BUTTON */
  await expect(headers.first()).toHaveJSProperty('tagName', 'BUTTON');
  await expect(headers.first()).toHaveAttribute('aria-expanded', 'true');
  for (let i = 1; i < await headers.count(); i++) {
    await expect(headers.nth(i)).toHaveAttribute('aria-expanded', 'false');
    await expect(dialog.locator(`#${await headers.nth(i).getAttribute('aria-controls')}`)).toBeHidden();
  }

  /* 2. the bar reads as chrome, not as a row */
  const barBg = await headers.first().evaluate((el) => getComputedStyle(el).backgroundColor);
  const itemBg = await dialog.locator('.arrangement-mobile-venue-item').first().evaluate((el) => getComputedStyle(el).backgroundColor);
  const bodyBg = await dialog.locator('.arrangement-mobile-venue-body').evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(barBg).not.toBe(itemBg);
  expect(barBg).not.toBe(bodyBg);

  /* 3. each header owns one chevron and a resolving aria-controls */
  for (let i = 0; i < await headers.count(); i++) {
    const header = headers.nth(i);
    await expect(header.locator('svg.arrangement-mobile-venue-group-chevron')).toHaveCount(1);
    const controls = await header.getAttribute('aria-controls');
    expect(controls).toBeTruthy();
    await expect(dialog.locator(`#${controls}`)).toHaveCount(1);
  }

  /* 4. fold the open one, open the closed one */
  await headers.first().click();
  await expect(headers.first()).toHaveAttribute('aria-expanded', 'false');
  await expect(dialog.locator(`#${await headers.first().getAttribute('aria-controls')}`)).toBeHidden();
  await headers.nth(1).click();
  await expect(headers.nth(1)).toHaveAttribute('aria-expanded', 'true');
  await expect(dialog.locator(`#${await headers.nth(1).getAttribute('aria-controls')}`)).toBeVisible();

  /* the chevron turns — poll, a bare read races the 0.15s transition */
  await expect
    .poll(async () => headers.first().locator('svg.arrangement-mobile-venue-group-chevron').evaluate((el) => getComputedStyle(el).transform))
    .toBe('matrix(0, -1, 1, 0, 0, 0)');

  /* 5. rule 2 — a search spanning groups opens EVERY matching group.
     `tutorial` matches through humanizeType → 'Tutorial Room'. The count guard
     above is the point: on one group this assertion would pass vacuously. */
  await dialog.locator('[data-arrangement-mobile-venue-search]').fill('tutorial');
  await expect.poll(async () => headers.count()).toBeGreaterThan(1);
  const matching = await headers.count();
  expect(matching, 'a multi-group search, not a single-venue one').toBeGreaterThan(1);
  for (let i = 0; i < matching; i++) {
    await expect(headers.nth(i)).toHaveAttribute('aria-expanded', 'true');
    await expect(dialog.locator(`#${await headers.nth(i).getAttribute('aria-controls')}`)).toBeVisible();
  }

  /* 6. picking still works after folding was added (the whole sheet still closes) */
  await dialog.locator('[data-arrangement-mobile-venue-search]').fill('B010');
  await dialog.locator('.arrangement-mobile-venue-item').first().click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('#buildingSelectorDropdown .venue-dd-trigger')).toContainText('B010');
});
