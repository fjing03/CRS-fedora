import { expect, test, type Page } from '@playwright/test';

const PAGE = '/venue-timetable-ui';

async function showRules(page: Page, touch = false) {
  const activate = touch ? 'tap' : 'click';
  const opener = page.getByRole('button', { name: 'How to use this page' });
  const guide = page.locator('#guideModal');
  await opener[activate]();
  await expect(guide.getByRole('tab', { name: 'Getting started' })).toHaveAttribute('aria-selected', 'true');
  await guide.getByRole('tab', { name: 'Venue rules' })[activate]();
  return { guide, opener, rules: guide.getByRole('tabpanel', { name: 'Venue rules' }) };
}

async function chooseRoom(page: Page, type: string, floor: string, code: string) {
  await page.locator('.venue-dd-trigger').click();
  const picker = page.locator('.venue-dd-panel');
  await picker.locator(`[data-type="${type}"]`).click();
  await picker.locator('[data-block="B"]').click();
  await picker.locator(`[data-floor="${floor}"]`).click();
  await Promise.all([
    page.waitForURL(new RegExp(`venue=${code}$`), { waitUntil: 'domcontentloaded' }),
    picker.locator(`[data-code="${code}"]`).click(),
  ]);
}

test.describe('Programme Leader', () => {
  test.use({ storageState: '.auth/staff-5425.json' });

  test('rules follow the selected room, not the selected week', async ({ page }) => {
    await page.goto(`${PAGE}?venue=B009`);
    const week = page.locator('#weekSelect');
    await week.selectOption({ index: 2 });
    const originalWeek = await week.inputValue();
    const { guide, opener, rules } = await showRules(page);
    await expect(rules.getByRole('heading', { name: /B009/ })).toBeVisible();
    await expect(rules.getByText('Practical (P)', { exact: true })).toBeVisible();
    await expect(rules.locator('tbody tr')).toHaveCount(13);
    await expect(rules.getByRole('row', { name: /AMCS1013/ })).toContainText('Tutorial (T)');
    await expect(rules.getByRole('row', { name: /AMCS1013/ })).not.toContainText('Lecture (L)');
    const ruleText = await rules.textContent();
    await page.keyboard.press(']');
    await expect(week).toHaveValue(originalWeek);
    await page.keyboard.press('Escape');
    await expect(guide).toBeHidden();
    await expect(opener).toBeFocused();
    await page.keyboard.press(']');
    await expect(week).not.toHaveValue(originalWeek);
    await showRules(page);
    await expect(rules).toHaveText(ruleText!);
    await page.keyboard.press('Escape');

    await chooseRoom(page, 'LectureHall', 'Floor 1', 'B111');
    await showRules(page);
    await expect(rules.getByRole('heading', { name: /B111/ })).toBeVisible();
    await expect(rules.getByText('Lecture (L)', { exact: true })).toBeVisible();
    await expect(rules.getByText('No module exceptions are listed for this room.')).toBeVisible();
    await expect(rules.getByRole('table')).toHaveCount(0);
    await page.keyboard.press('Escape');

    await chooseRoom(page, 'Lab', 'Ground Floor', 'B006');
    await showRules(page);
    await expect(rules.getByRole('heading', { name: /B006/ })).toBeVisible();
    await expect(rules.getByText('Practical (P)', { exact: true })).toBeVisible();
    await expect(rules.getByText(/Every cohort must be from.*DFT, DSF, RSD/)).toContainText('including the defaults');
    await expect(rules.locator('tbody tr')).toHaveCount(13);
  });

  for (const theme of ['light', 'dark']) {
    test(`wide roster stays readable at 320px in ${theme}`, async ({ browser }, info) => {
      const context = await browser.newContext({ storageState: '.auth/staff-5425.json', viewport: { width: 320, height: 568 }, hasTouch: true, isMobile: true });
      try {
        const page = await context.newPage();
        await page.addInitScript(value => localStorage.setItem('theme', value), theme);
        await page.goto(`${PAGE}?venue=B006`);
        const { guide, opener, rules } = await showRules(page, true);
        await expect(guide.getByRole('tab')).toHaveText(['Getting started', 'Full reference', 'Venue rules']);
        const pane = guide.locator('.modal-body');
        expect(await pane.evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
        const tabs = guide.getByRole('tab');
        const close = guide.locator('.btn-close-modal');
        for (const control of [opener, ...await tabs.all(), guide.locator('.modal-close'), close]) {
          const bounds = await control.boundingBox();
          expect(bounds!.width).toBeGreaterThanOrEqual(48);
          expect(bounds!.height).toBeGreaterThanOrEqual(48);
        }
        await page.screenshot({ path: info.outputPath('rules-top.png'), animations: 'disabled' });
        const last = rules.locator('tbody tr').last();
        await last.scrollIntoViewIfNeeded();
        await expect(last).toBeInViewport();
        await expect(guide.getByRole('tab', { name: 'Venue rules' })).toBeInViewport({ ratio: 0.99 });
        await expect(close).toBeInViewport({ ratio: 0.99 });
        await page.screenshot({ path: info.outputPath('rules-end.png'), animations: 'disabled' });
        await close.tap();
        await expect(opener).toBeFocused();
      } finally {
        await context.close();
      }
    });
  }

  test('desktop keyboard reaches the rules tab without changing other guide tabs', async ({ page }, info) => {
    await page.addInitScript(() => localStorage.setItem('theme', 'dark'));
    await page.goto(`${PAGE}?venue=B006`);
    const opener = page.getByRole('button', { name: 'How to use this page' });
    const guide = page.locator('#guideModal');
    await opener.press('Enter');
    await expect(guide.getByRole('tab')).toHaveText(['Getting started', 'Full reference', 'Venue rules', 'Shortcuts']);
    await page.keyboard.press('Tab');
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('ArrowRight');
    const rules = guide.getByRole('tab', { name: 'Venue rules' });
    await expect(rules).toBeFocused();
    await expect(rules).toHaveAttribute('aria-selected', 'true');
    await page.screenshot({ path: info.outputPath('desktop-rules.png'), animations: 'disabled' });
    await page.keyboard.press('ArrowRight');
    await expect(guide.getByRole('tab', { name: 'Shortcuts' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(guide).toBeHidden();
    await expect(opener).toBeFocused();
  });
});

for (const { staffId, hasMatch } of [{ staffId: '5770', hasMatch: true }, { staffId: '4363', hasMatch: false }]) {
  test.describe(`Ordinary lecturer ${staffId}`, () => {
    test.use({ storageState: `.auth/staff-${staffId}.json` });

    test('the initial guide markup includes only their matching exceptions', async ({ page }) => {
      const response = await page.goto(`${PAGE}?venue=B006`);
      const html = await response!.text();
      const codes = await page.evaluate(source => {
        const doc = new DOMParser().parseFromString(source, 'text/html');
        return Array.from(doc.querySelectorAll('#guidePanel-venue-rules tbody td:first-child strong'), el => el.textContent);
      }, html);
      expect(codes).toEqual(hasMatch ? ['BMIT2013'] : []);
      const { rules } = await showRules(page);
      await expect(rules.getByText('Practical (P)', { exact: true })).toBeVisible();
      await expect(rules.getByText(/Every cohort must be from.*DFT, DSF, RSD/)).toBeVisible();
      if (hasMatch) {
        const exception = rules.getByRole('row', { name: /BMIT2013/ });
        await expect(exception.getByRole('cell').last()).toHaveText('Lecture (L)');
      } else {
        await expect(rules.getByText('No exceptions apply to the classes you teach this semester.')).toBeVisible();
      }
    });
  });
}
