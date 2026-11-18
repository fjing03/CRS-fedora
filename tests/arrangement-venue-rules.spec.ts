import { expect, test, type Page } from '@playwright/test';

const PATH = '/replacement-arrangement';
const ARRIVAL = `${PATH}?session=67&week=14&from=replacement-home`;

test.use({ storageState: '.auth/staff-5425.json' });

async function openVenueRules(page: Page, touch = false) {
  const activate = touch ? 'tap' : 'click';
  const opener = page.getByRole('button', { name: 'How to use this page' });
  const dialog = page.locator('#guideModal');
  await opener[activate]();
  await expect(dialog.getByRole('tab', { name: 'Getting started' })).toHaveAttribute('aria-selected', 'true');
  await dialog.getByRole('tab', { name: 'Venue rules' })[activate]();
  return { dialog, opener, rules: dialog.getByRole('tabpanel', { name: 'Venue rules' }) };
}

for (const theme of ['light', 'dark']) {
  test(`desktop ${theme}: a room-only explanation before an original class is selected`, async ({ page }, info) => {
    await page.addInitScript(value => localStorage.setItem('theme', value), theme);
    await page.goto(PATH);
    const { dialog, opener, rules } = await openVenueRules(page);
    await expect(dialog.getByRole('tab')).toHaveText(['Getting started', 'Full reference', 'Venue rules', 'Shortcuts']);
    await expect(rules.getByRole('heading', { name: /B103/ })).toBeVisible();
    await expect(rules.getByText('Choose an original slot', { exact: false })).toBeVisible();
    await expect(rules.getByText(/Default session types:/)).toBeVisible();
    await expect(rules.getByText('35 seats.', { exact: true })).toBeVisible();
    await expect(rules.getByText('Permitted by default', { exact: false })).toHaveCount(0);
    await page.screenshot({ path: info.outputPath('room-only.png'), animations: 'disabled' });
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
    await page.keyboard.press('?');
    await expect(dialog.getByRole('tab', { name: 'Shortcuts' })).toBeFocused();
    await page.keyboard.press('ArrowLeft');
    await expect(dialog.getByRole('tab', { name: 'Venue rules' })).toBeFocused();
  });
}

test.describe('ordinary lecturer', () => {
  test.use({ storageState: '.auth/staff-5770.json' });

  test('explains the lecturer’s current class without a room-wide roster', async ({ page }) => {
    await page.goto(`${PATH}?session=97&week=8&from=replacement-home`);
    const { rules } = await openVenueRules(page);
    await expect(rules.getByRole('heading', { name: /B110/ })).toBeVisible();
    await expect(rules.getByText(/AMCS2093.*Lecture \(L\)/)).toBeVisible();
    await expect(rules.getByText('Permitted by default for this room.')).toBeVisible();
    await expect(rules.getByText(/80 seats for 33 students/)).toBeVisible();
    await expect(rules.getByRole('table')).toHaveCount(0);
  });
});

test.describe('compact touch', () => {
  test.use({ viewport: { width: 320, height: 568 }, hasTouch: true, isMobile: true });

  for (const theme of ['light', 'dark']) {
    test(`${theme}: a pinned lecture and picked practical explain their own room without changing a saved slot`, async ({ page }, info) => {
      await page.addInitScript(value => localStorage.setItem('theme', value), theme);
      await page.goto(ARRIVAL);
      const { dialog, opener, rules } = await openVenueRules(page, true);
      const close = dialog.locator('.btn-close-modal');
      await expect(dialog.getByRole('tab')).toHaveText(['Getting started', 'Full reference', 'Venue rules']);
      await expect(rules.getByRole('heading', { name: /B100/ })).toBeVisible();
      await expect(rules.getByText(/AMSE2003.*Lecture \(L\).*24 Dec 2026/)).toBeVisible();
      await expect(rules.getByText('Permitted by default for this room.')).toBeVisible();
      await close.tap();

      const context = page.locator('[data-arrangement-mobile-context]');
      if (!await context.evaluate(el => (el as HTMLDetailsElement).open)) {
        await page.locator('[data-arrangement-mobile-context-summary]').tap();
      }
      await page.locator('#slotTrigger').tap();
      await page.locator('.slot-dd-item').filter({ hasText: /\(P\) Week 14/ }).tap();
      const picker = page.locator('#buildingSelectorDropdown .venue-dd-trigger');
      await picker.tap();
      await page.locator('#arrangementMobileVenue [data-venue-code="B006"]').tap();
      await openVenueRules(page, true);
      await expect(rules.getByRole('heading', { name: /B006/ })).toBeVisible();
      await expect(rules.getByText(/AMSE2003.*Practical \(P\).*25 Dec 2026/)).toBeVisible();
      await expect(rules.getByText(/Every cohort must be from.*DFT, DSF, RSD/)).toBeVisible();
      await expect(rules.getByText('All cohorts in this class meet the programme rule.')).toBeVisible();
      await expect(rules.getByText(/32 seats for 15 students/)).toBeVisible();
      const pane = dialog.locator('.modal-body');
      expect(await pane.evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
      await expect.poll(() => dialog.locator('.modal--guide').evaluate(el => {
        const rect = el.getBoundingClientRect();
        const viewport = visualViewport!;
        return rect.left >= viewport.offsetLeft - 1 && rect.right <= viewport.offsetLeft + viewport.width + 1;
      })).toBe(true);
      await page.screenshot({ path: info.outputPath('selected-class.png'), animations: 'disabled' });
      await rules.getByText('This guide doesn’t check', { exact: false }).scrollIntoViewIfNeeded();
      await expect(close).toBeInViewport({ ratio: 0.99 });
      await expect(dialog.getByRole('tab', { name: 'Venue rules' })).toBeInViewport({ ratio: 0.99 });
      await page.screenshot({ path: info.outputPath('capacity.png'), animations: 'disabled' });
      await close.tap();
      await expect(opener).toBeFocused();

      // Use the page's resolved cells to find a full practical slot. This only
      // creates a local saved selection; the test never sends a request.
      const run = await page.evaluate(() => {
        const available = (day: number, hour: number) => document.querySelector(
          `#tableBody td[data-day="${day}"][data-hour="${hour}"] .cell-content.cell-available`,
        );
        for (let day = 0; day < 6; day++) {
          if (!document.querySelector(`[data-arrangement-mobile-days] button[data-day-index="${day}"]`)) continue;
          for (let hour = 0; hour < 21; hour++) if (available(day, hour) && available(day, hour + 1)) return { day, hour };
        }
        return null;
      });
      expect(run, 'an existing bookable two-cell run in B006').not.toBeNull();
      await page.locator(`[data-arrangement-mobile-days] button[data-day-index="${run!.day}"]`).tap();
      await page.locator(`[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${run!.hour}"]`).tap();
      const saved = page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="span"]');
      const submit = page.locator('#submitRequestBtn');
      const week = page.locator('#weekSelector');
      await expect(saved).toHaveCount(1);
      const savedMarkup = await saved.evaluate(el => el.outerHTML);
      const wasReady = await submit.isEnabled();
      const beforeWeek = await week.inputValue();
      await picker.tap();
      await page.locator('#arrangementMobileVenue [data-venue-code="B009"]').tap();
      await expect(page.locator('#modalTitle')).toHaveText('Change Venue?');
      await page.locator('#confirmModal .btn-outline').tap();
      await expect(page.locator('#confirmModal')).toBeHidden();
      await openVenueRules(page, true);
      await expect(rules.getByRole('heading', { name: /B006/ })).toBeVisible();
      await close.tap();
      await expect(week).toHaveValue(beforeWeek);
      expect(await submit.isEnabled()).toBe(wasReady);
      expect(await saved.evaluate(el => el.outerHTML)).toBe(savedMarkup);
    });
  }
});
