import { expect, test } from '@playwright/test';

// Guide-only checks. No booking or decision submissions.
test.use({ storageState: '.auth/staff-5425.json' });

const guides = [
  { page: 'My Replacements', path: '/my-replacements', start: 'Find the class', reference: 'What you can do at each status' },
  { page: 'Venue Timetable', path: '/venue-timetable-ui', start: 'Choose the room and date', reference: 'Why a free time might not work' },
  { page: 'Request Approval', path: '/request-approval-ui', start: 'Find the request', reference: 'If a decision does not go through' },
];

for (const { name, viewport, touch } of [
  { name: 'desktop', viewport: { width: 1440, height: 900 }, touch: false },
  { name: 'tablet', viewport: { width: 820, height: 1180 }, touch: true },
  { name: 'compact phone', viewport: { width: 320, height: 568 }, touch: true },
]) {
  test.describe(name, () => {
    test.use({ viewport, isMobile: touch, hasTouch: touch });
    for (const theme of ['light', 'dark']) {
      for (const guide of guides) {
        test(`${guide.page}, ${theme}: read both tabs and reopen at the start`, async ({ page }, info) => {
          await page.addInitScript(value => localStorage.setItem('theme', value), theme);
          await page.goto(guide.path);
          const opener = page.getByRole('button', { name: 'How to use this page' });
          const dialog = page.locator('#guideModal');
          const activate = touch ? 'tap' : 'click';
          const start = dialog.getByRole('tab', { name: 'Getting started' });
          const reference = dialog.getByRole('tab', { name: 'Full reference' });
          const close = dialog.locator('.btn-close-modal');
          const pane = dialog.locator('.modal-body');
          await expect(opener).toHaveCount(1);
          await opener[activate]();
          await expect(dialog.getByRole('tab')).toHaveCount(2);
          await expect(dialog.locator('.modal-subtitle')).toHaveText(guide.page);
          await expect(start).toHaveAttribute('aria-selected', 'true');
          await expect(dialog.getByRole('heading', { name: guide.start })).toBeVisible();
          await page.screenshot({ path: info.outputPath('getting-started.png'), animations: 'disabled' });
          await reference[activate]();
          await expect(reference).toHaveAttribute('aria-selected', 'true');
          const heading = dialog.getByRole('heading', { name: guide.reference });
          await expect(heading).toBeVisible();
          await page.screenshot({ path: info.outputPath('full-reference.png'), animations: 'disabled' });
          const last = dialog.getByRole('tabpanel').locator(':scope > :last-child');
          await last.scrollIntoViewIfNeeded();
          expect(await pane.evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
          for (const control of [start, reference, close, dialog.locator('.modal-close')]) {
            const geometry = await control.evaluate(el => {
              const { right, bottom, width, height } = el.getBoundingClientRect();
              return { right, bottom, width, height, viewportWidth: visualViewport!.width, viewportHeight: visualViewport!.height };
            });
            expect(geometry.right).toBeLessThanOrEqual(geometry.viewportWidth + 1);
            expect(geometry.bottom).toBeLessThanOrEqual(geometry.viewportHeight + 1);
            if (touch) {
              expect(geometry.width).toBeGreaterThanOrEqual(48);
              expect(geometry.height).toBeGreaterThanOrEqual(48);
            }
          }
          await close[activate]();
          await expect(dialog).toBeHidden();
          await expect(opener).toBeFocused();
          await opener[activate]();
          await expect(start).toHaveAttribute('aria-selected', 'true');
          expect(await pane.evaluate(el => el.scrollTop)).toBe(0);
        });
      }
    }
  });
}

for (const guide of guides) {
  test(`${guide.page}: guide owns keyboard navigation and Escape`, async ({ page }) => {
    await page.goto(guide.path);
    const opener = page.getByRole('button', { name: 'How to use this page' });
    const dialog = page.locator('#guideModal');
    const backgroundDialogs = page.locator('.modal-overlay.show:not(#guideModal)');
    if (guide.page === 'Request Approval') {
      // Include past requests if the fixture has no pending rows. This only changes a display filter.
      await page.locator('#statusFilter').evaluate((el: HTMLSelectElement) => {
        el.value = 'all';
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      await page.keyboard.press('ArrowDown');
      if (await page.locator('#tableBody tr[data-id]').count()) {
        await expect(page.locator('#tableBody .row-focused')).toHaveCount(1);
      }
    }
    const highlightedRows = await page.locator('#tableBody .row-focused').count();
    const week = await page.locator('#weekSelect').count() ? await page.locator('#weekSelect').inputValue() : null;
    await opener.focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    await expect(backgroundDialogs).toHaveCount(0);
    await page.evaluate(() => {
      document.addEventListener('keydown', e => {
        document.body.dataset.guideBackgroundKey = e.key;
      });
    });
    await dialog.getByRole('tab', { name: 'Getting started' }).focus();
    await page.keyboard.press('ArrowRight');
    await expect(dialog.getByRole('tab', { name: 'Full reference' })).toHaveAttribute('aria-selected', 'true');
    for (const key of [']', '/', '?', 'a', 'r', 'b', 'ArrowDown', 'Escape']) await page.keyboard.press(key);
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
    await expect(backgroundDialogs).toHaveCount(0);
    await expect(page.locator('body')).not.toHaveAttribute('data-guide-background-key');
    await expect(page.locator('#tableBody .row-focused')).toHaveCount(highlightedRows);
    if (week !== null) await expect(page.locator('#weekSelect')).toHaveValue(week);
  });
}
