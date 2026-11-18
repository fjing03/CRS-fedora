import { expect, test, type Page } from '@playwright/test';

// Read-only pilot receipt. No booking submissions or SQL fixtures.
test.use({ storageState: '.auth/staff-5425.json' });

async function openArrangement(page: Page) {
  await page.goto('/replacement-arrangement');
  const opener = page.getByRole('button', { name: 'How to use this page' });
  const dialog = page.locator('#guideModal');
  await expect(opener).toHaveCount(1);
  await expect(dialog).toBeHidden();
  await expect(page.locator('#guideBlock')).toHaveCount(0);
  return { opener, dialog };
}

for (const { name, viewport, touch } of [
  { name: 'desktop', viewport: { width: 1440, height: 900 }, touch: false },
  { name: 'phone', viewport: { width: 390, height: 844 }, touch: true },
  { name: 'short phone', viewport: { width: 320, height: 568 }, touch: true },
]) {
  test.describe(name, () => {
    test.use({ viewport, isMobile: touch, hasTouch: touch });
    for (const theme of ['light', 'dark']) {
      test(`${theme}: both tabs, reading scroll and reset`, async ({ page }, testInfo) => {
        await page.addInitScript(value => localStorage.setItem('theme', value), theme);
        const { opener, dialog } = await openArrangement(page);
        await expect(page.locator('html')).toHaveClass(theme === 'dark' ? /\bdark\b/ : /^(?!.*\bdark\b)/);
        const start = dialog.getByRole('tab', { name: 'Getting started' });
        const reference = dialog.getByRole('tab', { name: 'Full reference' });
        const body = dialog.locator('.modal-body');
        const footerClose = dialog.locator('.btn-close-modal');
        const activate = touch ? 'tap' : 'click';

        await opener[activate]();
        await expect(opener).toHaveAttribute('aria-expanded', 'true');
        await expect(start).toHaveAttribute('aria-selected', 'true');
        await expect(dialog.getByRole('tabpanel')).toContainText('three working hours or less');
        await expect(dialog.locator('#guidePanel-reference')).toBeHidden();
        await dialog.screenshot({ path: testInfo.outputPath('getting-started.png'), animations: 'disabled' });

        await reference[activate]();
        await expect(reference).toHaveAttribute('aria-selected', 'true');
        await expect(dialog.getByRole('tabpanel')).toContainText('Approval deadline');
        await expect(dialog.locator('#guidePanel-start')).toBeHidden();
        await expect.poll(() => body.evaluate(el => el.scrollTop)).toBe(0);
        await expect(body.evaluate(el => el.scrollWidth - el.clientWidth)).resolves.toBeLessThanOrEqual(1);
        await dialog.screenshot({ path: testInfo.outputPath('full-reference.png'), animations: 'disabled' });

        await dialog.getByText('Nothing is sent again automatically.', { exact: false }).scrollIntoViewIfNeeded();
        await expect.poll(() => body.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
        for (const control of [start, reference, footerClose, dialog.locator('.modal-close')]) {
          await expect(control).toBeInViewport({ ratio: 1 });
        }
        if (touch) {
          expect(await page.evaluate(() => matchMedia('(any-pointer: coarse)').matches)).toBe(true);
          for (const control of [opener, start, reference, footerClose, dialog.locator('.modal-close')]) {
            const box = await control.boundingBox();
            expect(box?.width).toBeGreaterThanOrEqual(48);
            expect(box?.height).toBeGreaterThanOrEqual(48);
          }
        }

        await start[activate]();
        await expect.poll(() => body.evaluate(el => el.scrollTop)).toBe(0);
        await reference[activate]();
        await footerClose[activate]();
        await expect(dialog).toBeHidden();
        await expect(opener).toBeFocused();
        await opener[activate]();
        await expect(start).toHaveAttribute('aria-selected', 'true');
        await expect.poll(() => body.evaluate(el => el.scrollTop)).toBe(0);
        await dialog.locator('.modal-close')[activate]();
        await expect(opener).toHaveAttribute('aria-expanded', 'false');
      });
    }
  });
}

test('desktop guide owns keyboard actions without changing the grid or week', async ({ page }) => {
  const { opener, dialog } = await openArrangement(page);
  await expect(page.locator('#weekSelector option').first()).toBeAttached();
  await page.keyboard.press('ArrowDown');
  const gridFocus = page.locator('#tableBody .cell-focused');
  await expect(gridFocus).toHaveCount(1);
  const focusedCell = await gridFocus.evaluate(el => el.parentElement?.outerHTML);
  const week = await page.locator('#weekSelector').inputValue();
  const selection = await page.locator('#submitRequestBtn').isEnabled();
  await opener.click();
  await expect(dialog.locator('.modal-close')).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(dialog.locator('.btn-close-modal')).toBeFocused();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await expect(dialog.getByRole('tab', { name: 'Getting started' })).toBeFocused();
  await page.keyboard.press('ArrowRight');
  const reference = dialog.getByRole('tab', { name: 'Full reference' });
  await expect(reference).toBeFocused();
  await page.keyboard.press('Enter');
  await page.keyboard.press('Space');
  await expect(reference).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press(']');
  await expect(page.locator('#weekSelector')).toHaveValue(week);
  await page.keyboard.press('[');
  await expect(page.locator('#weekSelector')).toHaveValue(week);
  await page.keyboard.press('?');
  await expect(page.locator('#helpOverlay')).not.toHaveClass(/active/);
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(opener).toBeFocused();
  expect(await gridFocus.evaluate(el => el.parentElement?.outerHTML)).toBe(focusedCell);
  expect(await page.locator('#submitRequestBtn').isEnabled()).toBe(selection);

  await page.keyboard.press('?');
  await expect(page.locator('#helpOverlay')).toHaveClass(/active/);
  await page.keyboard.press('Escape');
  await expect(page.locator('#helpOverlay')).not.toHaveClass(/active/);

  await opener.click();
  await dialog.click({ position: { x: 5, y: 5 } });
  await expect(dialog).toBeHidden();
  await expect(opener).toBeFocused();
});

for (const { path, content } of [
  { path: '/venue-timetable-ui', content: '.guide-list li' },
  { path: '/my-timetable-ui', content: '.detail-group' },
]) {
  test(`${path}: legacy guide keeps its content without tabs`, async ({ page }) => {
    await page.goto(path);
    const opener = page.getByRole('button', { name: 'How to use this page' });
    const dialog = page.locator('#guideModal');
    await opener.click();
    await expect(dialog.locator(content).first()).toBeVisible();
    await expect(dialog.getByRole('tab')).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
  });
}
