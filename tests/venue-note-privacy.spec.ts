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

const VENUE = 'http://localhost:8000/venue-timetable-ui';
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
  await page.goto('http://localhost:8000/my-timetable-ui', { waitUntil: 'domcontentloaded' });
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
