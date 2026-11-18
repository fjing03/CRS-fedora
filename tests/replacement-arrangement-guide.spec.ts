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
      test(`${theme}: visible tabs, reading scroll and reset`, async ({ page }, testInfo) => {
        await page.addInitScript(value => localStorage.setItem('theme', value), theme);
        const { opener, dialog } = await openArrangement(page);
        await expect(page.locator('html')).toHaveClass(theme === 'dark' ? /\bdark\b/ : /^(?!.*\bdark\b)/);
        const start = dialog.getByRole('tab', { name: 'Getting started' });
        const reference = dialog.getByRole('tab', { name: 'Full reference' });
        const shortcuts = dialog.locator('#guideTab-shortcuts');
        const body = dialog.locator('.modal-body');
        const footerClose = dialog.locator('.btn-close-modal');
        const activate = touch ? 'tap' : 'click';

        if (touch) {
          await expect(page.locator('body')).toHaveClass(/arrangement-mobile-mode/);
          await page.keyboard.press('?');
          await expect(dialog).toBeHidden();
        }
        await opener[activate]();
        await expect(opener).toHaveAttribute('aria-expanded', 'true');
        await expect(start).toHaveAttribute('aria-selected', 'true');
        await expect(dialog.getByRole('tabpanel')).toContainText('three working hours or less');
        await expect(dialog.locator('#guidePanel-reference')).toBeHidden();
        await page.screenshot({ path: testInfo.outputPath('getting-started.png'), animations: 'disabled' });

        await reference[activate]();
        await expect(reference).toHaveAttribute('aria-selected', 'true');
        await expect(dialog.getByRole('tabpanel')).toContainText('Approval deadline');
        await expect(dialog.locator('#guidePanel-start')).toBeHidden();
        await expect.poll(() => body.evaluate(el => el.scrollTop)).toBe(0);
        await expect(body.evaluate(el => el.scrollWidth - el.clientWidth)).resolves.toBeLessThanOrEqual(1);
        await page.screenshot({ path: testInfo.outputPath('full-reference.png'), animations: 'disabled' });

        await dialog.getByText('Nothing is sent again automatically.', { exact: false }).scrollIntoViewIfNeeded();
        await expect.poll(() => body.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
        const tabs = touch ? [start, reference] : [start, reference, shortcuts];
        for (const control of [...tabs, footerClose, dialog.locator('.modal-close')]) {
          await expect(control).toBeInViewport({ ratio: 1 });
        }
        if (touch) {
          expect(await page.evaluate(() => matchMedia('(any-pointer: coarse)').matches)).toBe(true);
          for (const control of [opener, ...tabs, footerClose, dialog.locator('.modal-close')]) {
            const box = await control.boundingBox();
            expect(box?.width).toBeGreaterThanOrEqual(48);
            expect(box?.height).toBeGreaterThanOrEqual(48);
          }
        }

        await start[activate]();
        await expect.poll(() => body.evaluate(el => el.scrollTop)).toBe(0);
        if (touch) {
          await expect(shortcuts).toBeHidden();
          await reference.focus();
          await page.keyboard.press('ArrowRight');
          await expect(start).toBeFocused();
          await expect(start).toHaveAttribute('aria-selected', 'true');
          await reference.tap();
          for (const control of [...tabs, footerClose]) {
            expect(await control.evaluate(el => {
              const rect = el.getBoundingClientRect();
              const viewport = window.visualViewport!;
              return rect.right <= viewport.width + 1 && rect.bottom <= viewport.height + 1;
            })).toBe(true);
          }
        } else {
          await shortcuts.click();
          await expect(shortcuts).toHaveAttribute('aria-selected', 'true');
          await expect(dialog.getByRole('tabpanel').getByRole('row')).toHaveCount(9);
          await expect(body.evaluate(el => el.scrollWidth - el.clientWidth)).resolves.toBeLessThanOrEqual(1);
          await page.screenshot({ path: testInfo.outputPath('shortcuts.png'), animations: 'disabled' });
        }
        await expect(dialog.locator('.guide-tabs').evaluate(el => el.scrollWidth - el.clientWidth)).resolves.toBeLessThanOrEqual(1);
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
  await expect(reference).toHaveAttribute('aria-selected', 'true');
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(opener).toBeFocused();
  expect(await gridFocus.evaluate(el => el.parentElement?.outerHTML)).toBe(focusedCell);
  expect(await page.locator('#submitRequestBtn').isEnabled()).toBe(selection);

  await page.locator('#weekSelector').focus();
  await page.keyboard.press('?');
  await expect(dialog).toBeHidden();

  for (const key of ['Enter', 'Space']) {
    await opener.focus();
    await page.keyboard.press('?');
    const shortcuts = dialog.getByRole('tab', { name: 'Shortcuts' });
    await expect(shortcuts).toHaveAttribute('aria-selected', 'true');
    await expect(shortcuts).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
    await page.keyboard.press(key);
    await expect(dialog.getByRole('tab', { name: 'Getting started' })).toHaveAttribute('aria-selected', 'true');
    await page.keyboard.press('Escape');
  }

  await opener.click();
  await dialog.click({ position: { x: 5, y: 5 } });
  await expect(dialog).toBeHidden();
  await expect(opener).toBeFocused();

  await page.keyboard.press('?');
  const shortcutTab = dialog.locator('#guideTab-shortcuts');
  await expect(shortcutTab).toHaveAttribute('aria-selected', 'true');
  await page.setViewportSize({ width: 600, height: 800 });
  await expect(shortcutTab).toBeHidden();
  const startTab = dialog.getByRole('tab', { name: 'Getting started' });
  await expect(startTab).toHaveAttribute('aria-selected', 'true');
  await expect(startTab).toBeFocused();
});
