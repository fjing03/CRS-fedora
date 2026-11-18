import { expect, test } from '@playwright/test';

const guides = [
  { path: '/my-timetable-ui', title: 'My Timetable', role: 'staff', start: '1. Find the class', reference: 'Read the class blocks', weekKeys: true },
  { path: '/cohort-timetable', title: 'Cohort Timetable', role: 'staff', start: '1. Check which cohort you’re viewing', reference: 'Read the cohort schedule', weekKeys: true },
  { path: '/student-my-timetable-ui', title: 'Student My Timetable', role: 'student', start: '1. Find the day', reference: 'Read the timetable and week summary', weekKeys: true },
  { path: '/student-class-replacements', title: 'Class Replacements', role: 'student', start: '1. Find the original class', reference: 'Why a class may still be awaiting arrangement', weekKeys: false },
] as const;

const STUDENT_AUTH = '.auth/student-24RSD0009.json';

for (const guide of guides) {
  test.describe(`${guide.title} (${guide.role})`, () => {
    test.use({ storageState: guide.role === 'staff' ? '.auth/staff-5770.json' : STUDENT_AUTH });

    for (const { name, viewport, touch } of [
      { name: 'desktop', viewport: { width: 1440, height: 900 }, touch: false },
      { name: 'tablet', viewport: { width: 820, height: 1180 }, touch: true },
      { name: 'phone', viewport: { width: 320, height: 568 }, touch: true },
    ]) {
      test.describe(name, () => {
        test.use({ viewport, hasTouch: touch, isMobile: touch });
        for (const theme of ['light', 'dark']) {
          test(`${theme}: readable guide and reachable controls`, async ({ page }, info) => {
            await page.addInitScript(value => localStorage.setItem('theme', value), theme);
            await page.goto(guide.path);
            const opener = page.getByRole('button', { name: 'How to use this page' });
            const dialog = page.getByRole('dialog', { name: /How to use this page/ });
            const start = dialog.getByRole('tab', { name: 'Getting started' });
            const reference = dialog.getByRole('tab', { name: 'Full reference' });
            const shortcuts = dialog.getByRole('tab', { name: 'Shortcuts' });
            const headerClose = dialog.locator('.modal-close');
            const footerClose = dialog.getByRole('button', { name: 'Close', exact: true }).last();
            const pane = dialog.locator('.modal-body');
            const activate = touch ? 'tap' : 'click';
            const hasShortcuts = guide.weekKeys && viewport.width > 768;
            await expect(opener).toHaveCount(1);
            await expect(page.locator('.guide-toggle, #guideBlock, .page-desc')).toHaveCount(0);
            await opener[activate]();
            await expect(opener).toHaveAttribute('aria-expanded', 'true');
            await expect(dialog.locator('.modal-subtitle')).toHaveText(guide.title);
            await expect(dialog.getByRole('tab')).toHaveCount(hasShortcuts ? 3 : 2);
            await expect(start).toHaveAttribute('aria-selected', 'true');
            await expect(headerClose).toBeFocused();
            await expect(dialog.getByRole('heading', { name: guide.start })).toBeVisible();
            if (guide.title === 'Cohort Timetable') {
              await expect(dialog.getByRole('tabpanel')).not.toContainText('the page is set to your own cohort');
            }
            await page.screenshot({ path: info.outputPath('getting-started.png'), animations: 'disabled' });
            await reference[activate]();
            await expect(dialog.getByRole('heading', { name: guide.reference })).toBeVisible();
            await page.screenshot({ path: info.outputPath('full-reference.png'), animations: 'disabled' });
            const last = dialog.getByRole('tabpanel').locator(':scope > :last-child');
            await last.scrollIntoViewIfNeeded();
            await expect(last).toBeInViewport();
            expect(await pane.evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
            const controls = [start, reference, headerClose, footerClose, ...(hasShortcuts ? [shortcuts] : [])];
            for (const control of controls) {
              await expect(control).toBeInViewport({ ratio: 0.99 });
            }
            if (touch) {
              for (const control of [opener, ...controls]) {
                const box = await control.boundingBox();
                expect(box!.width).toBeGreaterThanOrEqual(48);
                expect(box!.height).toBeGreaterThanOrEqual(48);
              }
            }
            if (hasShortcuts) {
              await shortcuts[activate]();
              await expect(dialog.getByRole('tabpanel')).toContainText('Go to the next week.');
              await page.screenshot({ path: info.outputPath('shortcuts.png'), animations: 'disabled' });
            }
            await footerClose[activate]();
            await expect(dialog).toBeHidden();
            await expect(opener).toBeFocused();
            await expect(opener).toHaveAttribute('aria-expanded', 'false');
            await opener[activate]();
            await expect(start).toHaveAttribute('aria-selected', 'true');
            expect(await pane.evaluate(el => el.scrollTop)).toBe(0);
          });
        }
      });
    }

    test('keyboard focus stays inside and Escape returns to the opener', async ({ page }) => {
      await page.setViewportSize({ width: 769, height: 900 });
      await page.goto(guide.path);
      const opener = page.getByRole('button', { name: 'How to use this page' });
      const dialog = page.locator('#guideModal');
      const start = dialog.getByRole('tab', { name: 'Getting started' });
      const reference = dialog.getByRole('tab', { name: 'Full reference' });
      const shortcuts = dialog.locator('#guideTab-shortcuts');
      const headerClose = dialog.locator('.modal-close');
      const footerClose = dialog.locator('.btn-close-modal');
      const week = page.locator('#weekSelect');
      let selectedWeek: string | undefined;
      if (guide.weekKeys) {
        await expect(week.locator('option').nth(3)).toBeAttached();
        await week.selectOption({ index: 2 });
        selectedWeek = await week.inputValue();
        const previous = await week.locator('option').nth(1).getAttribute('value');
        const next = await week.locator('option').nth(3).getAttribute('value');
        await opener.focus();
        await page.keyboard.press('[');
        await expect(week).toHaveValue(previous!);
        await page.keyboard.press(']');
        await expect(week).toHaveValue(selectedWeek);
        await page.keyboard.press(']');
        await expect(week).toHaveValue(next!);
        await page.keyboard.press('[');
        await expect(week).toHaveValue(selectedWeek);
      }
      await opener.press('Enter');
      await expect(headerClose).toBeFocused();
      await page.keyboard.press('Shift+Tab');
      await expect(footerClose).toBeFocused();
      await page.keyboard.press('Tab');
      await expect(headerClose).toBeFocused();
      await page.keyboard.press('Tab');
      await expect(start).toBeFocused();
      await page.keyboard.press('ArrowRight');
      await expect(reference).toBeFocused();
      await page.keyboard.press('Home');
      await expect(start).toBeFocused();
      await page.keyboard.press('End');
      if (guide.weekKeys) {
        await expect(shortcuts).toBeFocused();
        await expect(shortcuts).toHaveAttribute('aria-selected', 'true');
        for (const key of ['[', ']']) {
          await page.keyboard.press(key);
          await expect(week).toHaveValue(selectedWeek!);
        }
        await page.setViewportSize({ width: 768, height: 900 });
        await expect(shortcuts).toBeHidden();
        await expect(start).toBeFocused();
      } else {
        await expect(reference).toBeFocused();
        await expect(shortcuts).toHaveCount(0);
      }
      await page.keyboard.press('Escape');
      await expect(dialog).toBeHidden();
      await expect(opener).toBeFocused();
      await expect(page.locator('.modal-overlay:visible')).toHaveCount(0);
    });
  });
}

test.describe('Student route boundaries', () => {
  test.use({ storageState: STUDENT_AUTH, viewport: { width: 1440, height: 900 } });
  test('Cohort Timetable remains staff-only', async ({ page }) => {
    const response = await page.goto('/cohort-timetable');
    expect(response?.status()).toBe(403);
  });

  test('Class Replacements: Enter and Space open the focused row’s read-only details', async ({ page }) => {
    await page.goto('/student-class-replacements');
    const row = page.locator('#upcomingBody tr[data-id]').first();
    const details = page.locator('#classModal');
    for (const key of ['Enter', 'Space']) {
      await row.press(key);
      await expect(details).toBeVisible();
      await expect(page.locator('.modal-overlay:visible')).toHaveCount(1);
      await details.getByRole('button', { name: 'Close', exact: true }).click();
      await expect(details).toBeHidden();
    }
  });
});
