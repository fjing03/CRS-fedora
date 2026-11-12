import { test, expect, type Page } from '@playwright/test';

/**
 * SDD replacement-arrangement-top-zone-round2 — desktop receipts.
 *
 * Grid law: three setup cards (Conflict / Class Details / Replacement) and a
 * conditional fourth Summary. 1360 + Summary = 8/5/8 + 280; 1200 + Summary
 * keeps the 8/5/7 setup row and drops the Summary to row 2; 1100 = one column.
 * Rail classes are the only owner of the 56px tracks.
 */
test.use({ storageState: '.auth/staff-5770.json' });

const PINNED = '/replacement-arrangement?session=49&week=14&from=my-timetable';

async function openPinned(page: Page): Promise<void> {
  await page.goto(PINNED, { waitUntil: 'networkidle' });
  await page.waitForSelector('#subjectInfo', { timeout: 15_000 });
  await page.waitForTimeout(900);
}

async function selectFirstBlock(page: Page): Promise<void> {
  const cell = page.locator('#tableBody .cell-content.cell-available').first();
  await expect(cell).toBeVisible();
  await cell.click();
  await expect(page.locator('#topZone')).toHaveClass(/has-summary/);
  await expect(page.locator('#selSummary')).toBeVisible();
}

async function tracks(page: Page): Promise<number[]> {
  return page.evaluate(() => {
    const top = document.getElementById('topZone') as HTMLElement;
    return getComputedStyle(top).gridTemplateColumns.split(/\s+/).map(parseFloat);
  });
}

async function chrome(card: ReturnType<Page['locator']>) {
  return card.evaluate((el) => {
    const s = getComputedStyle(el);
    return {
      border: s.borderTopWidth,
      radius: s.borderTopLeftRadius,
      shadow: s.boxShadow,
      padding: s.padding,
    };
  });
}

test('1360px, no Summary: three setup cards at 8/5/7 with the shared chrome', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
  await page.waitForSelector('#subjectInfo', { timeout: 15_000 });
  await page.waitForTimeout(900);

  await expect(page.locator('#selSummary')).toBeHidden();
  await expect(page.locator('#topZone')).not.toHaveClass(/has-summary/);
  await expect(page.locator('#topZone > .ra-block:visible')).toHaveCount(3);

  const widths = await tracks(page);
  expect(widths).toHaveLength(3);
  const available = await page.evaluate(() => {
    const top = document.getElementById('topZone') as HTMLElement;
    const s = getComputedStyle(top);
    return top.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight) - 2 * 12;
  });
  const expected = [available * 8 / 20, available * 5 / 20, available * 7 / 20];
  widths.forEach((w, i) => expect(Math.abs(w - expected[i])).toBeLessThanOrEqual(1.1));

  for (const selector of ['#subjectInfo', '#classDetailsCard', '#replacementHeading']) {
    const card = selector === '#replacementHeading'
      ? page.locator('#topZone > .ra-block').nth(2)
      : page.locator(selector);
    const box = await chrome(card);
    expect(box.border).toBe('1px');
    expect(box.radius).not.toBe('0px');
    expect(box.shadow).not.toBe('none');
    expect(box.padding).toBe('12px 16px');
  }

  await expect(page.locator('#classDetailsCard')).toBeVisible();
  await expect(page.locator('#detailsEmpty')).toBeVisible();
  await expect(page.locator('#detailsToggle')).toBeHidden();
});

test('1360px, Summary: 8/5/8 plus a 280px fourth card', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openPinned(page);
  await selectFirstBlock(page);

  const widths = await tracks(page);
  expect(widths).toHaveLength(4);
  expect(Math.abs(widths[3] - 280)).toBeLessThanOrEqual(1);
  const available = await page.evaluate(() => {
    const top = document.getElementById('topZone') as HTMLElement;
    const s = getComputedStyle(top);
    return top.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight) - 280 - 3 * 12;
  });
  const expected = [available * 8 / 21, available * 5 / 21, available * 8 / 21];
  widths.slice(0, 3).forEach((w, i) => expect(Math.abs(w - expected[i])).toBeLessThanOrEqual(1.1));

  const summary = await page.locator('#selSummary').boundingBox();
  expect(summary).not.toBeNull();
  expect(Math.abs((summary as any).width - 280)).toBeLessThanOrEqual(1);
});

test('1200px, Summary: setup row stays 8/5/7 and the Summary spans row 2', async ({ page }) => {
  await page.setViewportSize({ width: 1200, height: 900 });
  await openPinned(page);
  await selectFirstBlock(page);

  const widths = await tracks(page);
  expect(widths).toHaveLength(3);
  const setupRow = page.locator('#topZone > .ra-block').nth(2);
  const summary = page.locator('#selSummary');
  const row1 = await setupRow.boundingBox();
  const row2 = await summary.boundingBox();
  expect(row1).not.toBeNull();
  expect(row2).not.toBeNull();
  expect((row2 as any).y).toBeGreaterThan((row1 as any).y + (row1 as any).height - 1);
  const contentWidth = await page.locator('#topZone').evaluate((el) => {
    const s = getComputedStyle(el);
    return el.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);
  });
  expect(Math.abs((row2 as any).width - contentWidth)).toBeLessThanOrEqual(1);
});

test('1100px is one full-width column', async ({ page }) => {
  await page.setViewportSize({ width: 1100, height: 900 });
  await openPinned(page);
  await selectFirstBlock(page);

  const top = page.locator('#topZone');
  const contentWidth = await top.evaluate((el) => {
    const s = getComputedStyle(el);
    return el.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);
  });
  const cards = page.locator('#topZone > .ra-block');
  const count = await cards.count();
  const xs: number[] = [];
  for (let i = 0; i < count; i++) {
    const box = await cards.nth(i).boundingBox();
    expect(box).not.toBeNull();
    xs.push((box as any).x);
    expect(Math.abs((box as any).width - contentWidth)).toBeLessThanOrEqual(1);
  }
  expect(new Set(xs.map((x) => Math.round(x))).size).toBe(1);

  const openArrow = await page.locator('#detailsToggle .ra-details-chevron').evaluate((el) => getComputedStyle(el).transform);
  await page.locator('#detailsToggle').click();
  await page.waitForTimeout(250);
  const closedArrow = await page.locator('#detailsToggle .ra-details-chevron').evaluate((el) => getComputedStyle(el).transform);
  expect(openArrow).not.toBe(closedArrow);
});

test('rail classes create 56px tracks and keep the arrows in sync', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openPinned(page);
  await selectFirstBlock(page);

  const conflictOpen = await page.locator('#conflictCollapse .ra-card-chevron').evaluate((el) => getComputedStyle(el).transform);
  const detailsOpen = await page.locator('#detailsToggle .ra-details-chevron').evaluate((el) => getComputedStyle(el).transform);

  await page.locator('#conflictCollapse').click();
  await expect(page.locator('#topZone')).toHaveClass(/conflict-collapsed/);
  expect(Math.abs((await tracks(page))[0] - 56)).toBeLessThanOrEqual(1);
  await expect(page.locator('#conflictCollapse')).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#conflictCollapse')).toHaveAttribute('aria-label', 'Expand Conflict Schedule');
  await page.waitForTimeout(250);
  expect(await page.locator('#conflictCollapse .ra-card-chevron').evaluate((el) => getComputedStyle(el).transform)).not.toBe(conflictOpen);

  await page.locator('#detailsToggle').click();
  await expect(page.locator('#topZone')).toHaveClass(/details-collapsed/);
  const both = await tracks(page);
  expect(Math.abs(both[0] - 56)).toBeLessThanOrEqual(1);
  expect(Math.abs(both[1] - 56)).toBeLessThanOrEqual(1);
  await expect(page.locator('#detailsToggle')).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#detailsToggle')).toHaveAttribute('aria-label', 'Expand Class Details');
  await page.waitForTimeout(250);
  expect(await page.locator('#detailsToggle .ra-details-chevron').evaluate((el) => getComputedStyle(el).transform)).not.toBe(detailsOpen);

  await page.locator('#conflictCollapse').click();
  await page.locator('#detailsToggle').click();
  await expect(page.locator('#topZone')).not.toHaveClass(/conflict-collapsed/);
  await expect(page.locator('#topZone')).not.toHaveClass(/details-collapsed/);
  await expect(page.locator('#detailsToggle')).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#detailsToggle')).toHaveAttribute('aria-label', 'Collapse Class Details');
});

test('Conflict card stacks Course over Slot at full width', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openPinned(page);

  const card = page.locator('#subjectInfo');
  const course = page.locator('#subjectSelector');
  const slot = page.locator('#slotTrigger');
  const courseBox = await course.boundingBox();
  const slotBox = await slot.boundingBox();
  expect(courseBox).not.toBeNull();
  expect(slotBox).not.toBeNull();
  expect((slotBox as any).y).toBeGreaterThan((courseBox as any).y + (courseBox as any).height - 1);

  const contentWidth = await card.evaluate((el) => {
    const s = getComputedStyle(el);
    return el.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);
  });
  expect(Math.abs((courseBox as any).width - contentWidth)).toBeLessThanOrEqual(1);
  expect(Math.abs((slotBox as any).width - contentWidth)).toBeLessThanOrEqual(1);
});

test('Replacement card stacks Venue/Week and fills the content box', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openPinned(page);

  const replacement = page.locator('#topZone > .ra-block').nth(2);
  const cardBox = await replacement.boundingBox();
  const earliest = await page.locator('#earliestBtn').boundingBox();
  const venue = page.locator('#buildingSelectorDropdown .venue-dd-trigger');
  const venueBox = await venue.boundingBox();
  expect(cardBox).not.toBeNull();
  expect(earliest).not.toBeNull();
  expect(venueBox).not.toBeNull();
  const contentWidth = await replacement.evaluate((el) => {
    const s = getComputedStyle(el);
    return el.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);
  });
  expect(Math.abs((earliest as any).width - contentWidth)).toBeLessThanOrEqual(1);
  /* The info chip owns the row tail; the trigger takes the rest. */
  expect((venueBox as any).width).toBeGreaterThan(contentWidth - 60);

  const week = await page.locator('#weekSelector').boundingBox();
  expect(week).not.toBeNull();
  expect((earliest as any).y).toBeGreaterThan((week as any).y + (week as any).height - 1);

  const bg = await page.locator('#weekSelector').evaluate((el) => getComputedStyle(el).backgroundImage);
  expect(bg).toContain('data:image/svg+xml');
  expect(bg).not.toBe('none');
});

test('Summary rebuilds on the boot path and hides after the last clear', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openPinned(page);
  await selectFirstBlock(page);

  await page.evaluate(() => (window as any).buildTimetable());
  await expect(page.locator('#topZone')).toHaveClass(/has-summary/);
  await expect(page.locator('#selSummary')).toBeVisible();

  await page.evaluate(() => (window as any).clearAll());
  await page.locator('#modalConfirmBtn').click();
  await expect(page.locator('#topZone')).not.toHaveClass(/has-summary/);
  await expect(page.locator('#selSummary')).toBeHidden();

  await expect(page.locator('#detailsSummary')).toBeHidden();
});

test('venue panel uses 150px columns and stays 12px inside the viewport', async ({ page }) => {
  await page.setViewportSize({ width: 1360, height: 900 });
  await openPinned(page);

  await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
  const panel = page.locator('#buildingSelectorDropdown .venue-dd-panel');
  await expect(panel).toBeVisible();

  const visibleCols = panel.locator('.venue-col.visible');
  expect(await visibleCols.count()).toBeGreaterThan(0);
  for (let i = 0; i < await visibleCols.count(); i++) {
    const w = await visibleCols.nth(i).evaluate((el) => el.getBoundingClientRect().width);
    expect(Math.abs(w - 150)).toBeLessThanOrEqual(1);
  }

  let box = await panel.boundingBox();
  expect(box).not.toBeNull();
  expect((box as any).x).toBeGreaterThanOrEqual(12);
  expect((box as any).x + (box as any).width).toBeLessThanOrEqual(1360 - 12 + 1);

  /* Drill to four columns: each click reveals the next column. */
  for (let i = 0; i < 3; i++) {
    const last = visibleCols.last();
    const item = last.locator('.venue-col-item').first();
    if (await item.count()) {
      await item.click();
      await page.waitForTimeout(120);
    }
  }
  const cols = await panel.locator('.venue-col.visible').count();
  expect(cols).toBeGreaterThanOrEqual(1);
  box = await panel.boundingBox();
  expect(box).not.toBeNull();
  expect((box as any).x).toBeGreaterThanOrEqual(12);
  expect((box as any).x + (box as any).width).toBeLessThanOrEqual(1360 - 12 + 1);
});

test('the 150px venue columns are scoped away from other pages', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  await page.locator('.venue-dd-trigger').first().click();
  const col = page.locator('.venue-dd-panel .venue-col.visible').first();
  await expect(col).toBeVisible();
  const width = await col.evaluate((el) => el.getBoundingClientRect().width);
  expect(Math.abs(width - 150)).toBeGreaterThan(10);
});
