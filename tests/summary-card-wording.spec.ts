import { test, expect } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('tests/summary-card-wording.spec.ts');

const BASE = process.env.CRS_BASE!;
const LIST = 'Counts match the filtered list.';
const WEEK = 'Counts for the displayed week.';
const POINTER = 'Hover or click a card to see what it counts.';
const TOUCH = 'Tap a card to see what it counts.';

for (const touch of [false, true]) {
  test(`all seven live summaries name their scope and ${touch ? 'touch' : 'pointer'} gesture`, async ({ browser }) => {
    const options = { viewport: { width: touch ? 390 : 1440, height: 900 }, hasTouch: touch, isMobile: touch };
    const staff = await browser.newContext({ ...options, storageState: '.auth/staff-5425.json' });
    const student = await browser.newContext({ ...options, storageState: '.auth/student-24RSD0009.json' });
    try {
      const staffPage = await staff.newPage();
      const studentPage = await student.newPage();

      for (const [page, route, scope] of [
        [staffPage, 'my-replacements', LIST],
        [staffPage, 'request-approval-ui', LIST],
        [staffPage, 'my-timetable-ui', WEEK],
        [staffPage, 'cohort-timetable', WEEK],
        [staffPage, 'venue-timetable-ui', WEEK],
        [studentPage, 'student-class-replacements', LIST],
        [studentPage, 'student-my-timetable-ui', WEEK],
      ] as const) {
        await page.goto(`${BASE}/${route}`, { waitUntil: 'networkidle' });
        if (route === 'request-approval-ui' && await page.locator('#emptyState').isVisible()) {
          // An empty queue intentionally hides its summary. Reveal the existing shell in this browser only.
          await expect(page.locator('#summarySection')).toBeHidden();
          await page.evaluate(() => (window as unknown as { syncSummarySection(visible: boolean): void }).syncSummarySection(true));
        }
        if (touch) {
          const opener = page.locator(scope === WEEK ? '#ttSummaryButton' : '#summaryButton');
          await expect(opener, route).toBeVisible();
          await opener.tap();
        }
        const hint = page.locator('#summarySection .summary-hint');
        await expect(hint, route).toBeVisible();
        await expect(hint, route).toHaveText(`${scope} ${touch ? TOUCH : POINTER}`, { useInnerText: true });
      }
    } finally {
      await Promise.all([staff.close(), student.close()]);
    }
  });
}

test('Venue explains Bookable Slots without renaming the Available legend', async ({ browser }) => {
  for (const width of [320, 390]) {
    const context = await browser.newContext({
      viewport: { width, height: 844 }, hasTouch: true, isMobile: true,
      storageState: '.auth/staff-5425.json',
    });
    try {
      const page = await context.newPage();
      await page.goto(`${BASE}/venue-timetable-ui`);
      await page.locator('#ttSummaryButton').tap();
      const sheet = page.locator('#ttSheet');
      await expect(sheet.locator('.legend-bar').getByText('Available', { exact: true })).toBeVisible();
      const card = sheet.locator('.summary-card').filter({ has: page.locator('#sumAvailable') });
      await expect(card.locator('.summary-label')).toHaveText('Bookable Slots');
      await expect(card.locator('#sumAvailable')).toHaveText(/^\d+$/);
      await card.tap({ position: { x: 30, y: 30 } });
      const back = card.locator('.summary-card-back');
      await expect(back).toContainText('Not too soon');
      expect(await back.evaluate(el => el.scrollHeight - el.clientHeight)).toBeLessThanOrEqual(1);
      await card.locator('.summary-info').tap();
      const modal = page.locator('#summaryExplainModal');
      await expect(modal.locator('#summaryExplainTitle')).toHaveText('Bookable Slots');
      await expect(modal).toContainText('Free slots that are too soon to book, on Sundays or on public holidays are excluded.');
    } finally {
      await context.close();
    }
  }
});
