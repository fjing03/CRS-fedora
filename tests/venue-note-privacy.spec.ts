import { test, expect } from '@playwright/test';

/**
 * venue-note-privacy (2026-11-02, user bug report) — the READ-ONLY receipt for
 * the `.ev-note` ownership gate on /venue-timetable-ui.
 *
 * The block notes are REQUEST facts: "(Pending since 02 Nov 2026)" and
 * "(Proposed ⏳)" (and "(Cancelled)" / "(Replaced for …)"). Another lecturer
 * must not read them off the room screen — only the class's own lecturer, or
 * the request's proposer on an amber make-up block.
 *
 * Fixture (demo DB): request 26 = PENDING for class_session 40 → AMSE2003(P),
 * occurrence week 14, make-up in B010 week 7 Thu 14:00–15:00. Owner = staff
 * 5425 (user 1, Surayaini — also the proposer); unrelated staff = 5770.
 *
 * Nothing here writes: two logged-in contexts + GETs + DOM reads.
 */

const VENUE = (process.env.CRS_BASE ?? 'http://localhost:8000') + '/venue-timetable-ui';
const W7 = 6;    // 0-based week index for human week 7
const W14 = 13;

async function blocksFor(page: import('@playwright/test').Page, weekIdx: number) {
  await page.goto(`${VENUE}?venue=B010&_=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.locator('#weekSelect').selectOption({ index: weekIdx });
  await page.waitForTimeout(900);
  return page.evaluate(() => Array.from(document.querySelectorAll('.event-block'))
    .map((b) => ({
      text: (b as HTMLElement).innerText.replace(/\s+/g, ' ').trim(),
      notes: Array.from(b.querySelectorAll('.ev-note')).map((n) => (n.textContent || '').trim()),
    }))
    .filter((x) => x.text.includes('AMSE2003(P)')));
}

async function openAs(browser: import('@playwright/test').Browser, staffId: string) {
  const context = await browser.newContext({ storageState: `.auth/staff-${staffId}.json` });
  const page = await context.newPage();
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(String(e).slice(0, 120)));
  // the storageState is host-scoped to localhost:8000 — prove we are really the
  // authenticated identity before trusting any assertion below
  await page.goto((process.env.CRS_BASE ?? 'http://localhost:8000') + '/my-timetable-ui', { waitUntil: 'domcontentloaded' });
  expect(new URL(page.url()).pathname).toBe('/my-timetable-ui');
  return { context, page, errors };
}

test('the owner sees the request notes on their own class', async ({ browser }) => {
  const { context, page, errors } = await openAs(browser, '5425');

  const w7 = await blocksFor(page, W7);
  const amber = w7.find((b) => b.text.includes('2:00 PM'));
  expect(amber?.notes).toEqual(['(Pending since 02 Nov 2026)']);

  const w14 = await blocksFor(page, W14);
  const conflict = w14.find((b) => b.text.includes('9:00 AM'));
  expect(conflict?.notes).toEqual(['(Proposed ⏳)']);

  expect(errors).toEqual([]);
  await context.close();
});

test('unrelated staff see the same blocks with NO notes', async ({ browser }) => {
  const { context, page, errors } = await openAs(browser, '5770');

  const w7 = await blocksFor(page, W7);
  const amber = w7.find((b) => b.text.includes('2:00 PM'));
  // the block itself is unchanged (colour = status, for everyone)…
  expect(amber?.text).toBe('AMSE2003(P) B010 2:00 PM - 3:00 PM');
  // …but the request fact is not theirs to read
  expect(amber?.notes).toEqual([]);

  const w14 = await blocksFor(page, W14);
  const conflict = w14.find((b) => b.text.includes('9:00 AM'));
  expect(conflict?.text).toBe('AMSE2003(P) B010 9:00 AM - 10:00 AM');
  expect(conflict?.notes).toEqual([]);

  expect(errors).toEqual([]);
  await context.close();
});

/** Open the amber (pending) block and read what the POPUP exposes.
 *  Requested At/By + Status live in the STATUS tab (the row buckets), so the
 *  helper reads that pane; the timeline + footer are modal-global. */
async function pendingPopup(page: import('@playwright/test').Page) {
  await page.goto(`${VENUE}?venue=B010&_=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.locator('#weekSelect').selectOption({ index: W7 });
  await page.waitForTimeout(900);
  await page.locator('.event-block', { hasText: 'AMSE2003(P)' }).filter({ hasText: '2:00 PM' }).first().click();
  await page.waitForTimeout(700);

  const readPane = () => page.evaluate(() => {
    const modal = document.querySelector('#classModal') as HTMLElement | null;
    const row = (label: string) => {
      const rows = Array.from(modal?.querySelectorAll('.detail-row') || []);
      const hit = rows.find((r) => (r.querySelector('.detail-label')?.textContent || '').trim() === label);
      return hit ? (hit.querySelector('.detail-value')?.textContent || '').trim() : null;
    };
    return {
      text: (modal?.innerText || ''),
      requestedAt: row('Requested At'),
      requestedBy: row('Requested By'),
      status: row('Status'),
      lecturer: row('Lecturer'),
      footer: Array.from(modal?.querySelectorAll('.modal-footer button, .modal-footer a') || [])
        .map((x) => (x.textContent || '').trim()),
    };
  });

  const classInfo = await readPane();          // Class Information (default tab)
  const tabs = page.locator('#classModal .modal-tab, #classModal [data-tab]');
  for (let i = 0; i < await tabs.count(); i++) {
    if (/status/i.test((await tabs.nth(i).innerText()).trim())) { await tabs.nth(i).click(); break; }
  }
  await page.waitForTimeout(400);
  const status = await readPane();
  const timelineSubmitted = /Submitted/.test(classInfo.text) || /Submitted/.test(status.text);

  await page.locator('#classModal .btn-close-modal').click().catch(() => {});
  return { classInfo, status, timelineSubmitted };
}

test('the popup hides the request facts from unrelated staff (A+A ruling)', async ({ browser }) => {
  const outsider = await openAs(browser, '5770');
  const seen = await pendingPopup(outsider.page);
  expect(seen.status.requestedAt).toBeNull();          // no "Requested At" row anywhere
  expect(seen.status.requestedBy).toBeNull();          // no "Requested By" row anywhere
  expect(seen.timelineSubmitted).toBe(false);          // no "Submitted 02 Nov 2026, 07:00 PM" step
  expect(seen.classInfo.lecturer).toBe('Surayaini Binti Basri');  // the CLASS facts stay public
  expect(seen.status.status).toBe('Pending');          // the STATUS stays (already public via the amber block)
  expect(outsider.errors).toEqual([]);
  await outsider.context.close();

  const owner = await openAs(browser, '5425');
  const own = await pendingPopup(owner.page);
  expect(own.status.requestedAt).toBe('02 Nov 2026, 07:00 PM');  // the owner still sees the stamp…
  expect(own.status.requestedBy).toBe('Surayaini Binti Basri');  // …and who asked
  expect(own.timelineSubmitted).toBe(true);                      // …and the Submitted timeline step
  expect(owner.errors).toEqual([]);
  await owner.context.close();
});
