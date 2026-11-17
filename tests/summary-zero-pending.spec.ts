import { test, expect, type Page } from '@playwright/test';

// Read-only receipts: synthetic DOM/totals in this browser only, no SQL or actions.
if (process.env.CRS_BASE !== 'http://127.0.0.1:8030') {
  throw new Error('summary-zero-pending requires CRS_BASE=http://127.0.0.1:8030');
}
test.use({ storageState: '.auth/staff-5425.json' });

const ids = ['sumTotal', 'sumHours', 'sumMyClasses', 'sumMyHours'];
async function values(page: Page, cardIds = ids) {
  return page.evaluate((names) => names.map((id) => {
    const el = document.getElementById(id)!;
    return { id, text: el.textContent, tail: el.querySelector('.sum-pending')?.textContent ?? null };
  }), cardIds);
}

for (const width of [390, 1440]) {
  test(`shared writer: zero, fractional pending, transitions and opt-out at ${width}`, async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/staff-5425.json', viewport: { width, height: 900 },
      hasTouch: width === 390, isMobile: width === 390,
    });
    const page = await context.newPage();
    try {
      await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
      // My cards are absent on this page; add inert test targets for the SAME writer.
      await page.evaluate((names) => names.forEach((id) => {
        if (!document.getElementById(id)) {
          const el = document.createElement('span'); el.id = id; el.hidden = true;
          document.body.appendChild(el);
        }
      }), ids);
      const render = (pending: boolean, split: boolean, empty = false) => page.evaluate(({ pending, split, empty }) => {
        const w = window as any;
        const events = empty ? [] : [{ eventKey: 'normal', di: 0, start: 0, end: 1, status: 'normal', lecturer: w.MockData.currentUser.name }];
        if (pending) events.push({ eventKey: 'pending', di: 0, start: 2, end: 2, status: 'pending', lecturer: w.MockData.currentUser.name });
        w.computeSummary(events, [{ holiday: false }], { splitPending: split });
      }, { pending, split, empty });

      await render(false, true);
      expect((await values(page)).map(v => [v.text, v.tail])).toEqual([['1', null], ['1', null], ['1', null], ['1', null]]);
      await render(true, true);
      expect((await values(page)).map(v => [v.text, v.tail])).toEqual([['1+1', '+1'], ['1+0.5', '+0.5'], ['1+1', '+1'], ['1+0.5', '+0.5']]);
      await render(false, true);
      expect((await values(page)).every(v => v.text === '1' && v.tail === null)).toBe(true);
      await render(true, false);
      expect((await values(page)).map(v => [v.text, v.tail])).toEqual([['2', null], ['1.5', null], ['2', null], ['1.5', null]]);
      await render(false, true, true);
      expect((await values(page)).every(v => v.text === '0' && v.tail === null)).toBe(true);
      if (width === 390) {
        await page.locator('#ttSummaryButton').tap();
        await expect(page.locator('#ttSheet')).toBeVisible();
        await expect(page.locator('#ttSheet #sumTotal')).toHaveText('0');
        await expect(page.locator('#ttSheet .sum-pending')).toHaveCount(0);
        await render(true, true);
        await expect(page.locator('#ttSheet #sumHours .sum-pending')).toHaveText('+0.5');
      }
    } finally { await context.close(); }
  });

  test(`Venue writer: numeric-string zero and positive transitions at ${width}`, async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/staff-5425.json', viewport: { width, height: 900 },
      hasTouch: width === 390, isMobile: width === 390,
    });
    const page = await context.newPage();
    try {
      // Expose the actual closure's totals in this RESPONSE ONLY. The writer is unmodified.
      await page.route('**/venue-timetable-ui*', async route => {
        const response = await route.fetch();
        const html = await response.text();
        const marker = 'const totalsData = ';
        expect(html).toContain(marker);
        await route.fulfill({ response, body: html.replace(marker, 'const totalsData = window.__summaryTestTotals = ') });
      });
      await page.goto('/venue-timetable-ui', { waitUntil: 'networkidle' });
      const render = (pending: number | string) => page.evaluate((part) => {
        const w = window as any;
        // These are confirmed values, NOT full totals: preserve Venue's distinct accounting.
        Object.assign(w.__summaryTestTotals[w.currentWeek], {
          total: 100, occupied: 20, myClasses: 2, myHours: '1.5',
          pending: part, myPendingClasses: part, myPendingHours: part,
        });
        w.updateSummary();
      }, pending);
      const venueIds = ['sumTotal', 'sumOccupied', 'sumMyClasses', 'sumMyHours'];
      for (const zero of [0, '0', '0.0']) {
        await render(zero);
        expect((await values(page, venueIds)).map(v => [v.text, v.tail])).toEqual([['100', null], ['20', null], ['2', null], ['1.5', null]]);
      }
      await render('0.5');
      expect((await values(page, venueIds)).map(v => [v.text, v.tail])).toEqual([['100+0.5', '+0.5'], ['20+0.5', '+0.5'], ['2+0.5', '+0.5'], ['1.5+0.5', '+0.5']]);
      await render('0');
      expect((await values(page, venueIds)).every(v => v.tail === null)).toBe(true);
      await render(2);
      expect((await values(page, venueIds)).every(v => v.tail === '+2')).toBe(true);
      await render(0);
      if (width === 390) {
        await page.locator('#ttSummaryButton').tap();
        await expect(page.locator('#ttSheet #sumTotal')).toHaveText('100');
        await expect(page.locator('#ttSheet .sum-pending')).toHaveCount(0);
      }
    } finally { await context.close(); }
  });
}
