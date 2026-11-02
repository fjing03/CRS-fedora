import { test, expect } from '@playwright/test';

/**
 * venue-status-parity (2026-11-02) — the READ-ONLY receipt for the venue/cohort
 * bug batch. Nothing here writes: every step is a GET + a DOM read.
 *
 * Fixtures it pins (the demo DB's live truth — see the investigation report):
 *   · class_exceptions: session 23 = BMSE3153(P) B009, week 7, annual_leave
 *   · replacement_requests 26: session 40 = AMSE2003(P), occurrence week 14,
 *     PENDING, junction = 2 slots in B010 week 7 Thu 14:00–15:00
 *   · holidays: W8 Mon, W14 Thu (Christmas Eve), W14 Fri
 *   · viewer = staff 5425 (user 1, Surayaini) — owns every class above
 *
 * If the demo DB is re-seeded/replaced, re-derive these anchors from
 * `docs/demo-db-data-changes.md` + the investigation table before trusting the
 * red/green result (the assertions are intentionally literal, like
 * tests/venue-db.spec.ts).
 */

const VENUE = '/venue-timetable-ui';
const COHORT = '/cohort-timetable';
const MY = '/my-timetable-ui';
const W7 = 6;   // 0-based week index for human week 7
const W8 = 7;
const W14 = 13;

test.use({ storageState: '.auth/staff-5425.json' });

/** Open a venue week and return the rendered blocks (class + text). */
async function venueWeek(page: import('@playwright/test').Page, venue: string, weekIdx: number) {
  await page.goto(`${VENUE}?venue=${venue}&_=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.locator('#weekSelect').selectOption({ index: weekIdx });
  await page.waitForTimeout(900);
  return page.evaluate(() => Array.from(document.querySelectorAll('.event-block')).map((b) => ({
    cls: (b as HTMLElement).className.replace('event-block ', '').trim(),
    text: (b as HTMLElement).innerText.replace(/\s+/g, ' ').trim(),
  })));
}

async function openBlockAndReadFooter(
  page: import('@playwright/test').Page,
  text: string,
  modal = '#classModal',
  mustAlsoContain?: string,
) {
  const target = mustAlsoContain
    ? page.locator('.event-block', { hasText: text }).filter({ hasText: mustAlsoContain }).first()
    : page.locator('.event-block', { hasText: text }).first();
  await target.click();
  await page.waitForTimeout(700);
  const result = await page.evaluate((sel) => {
    const m = document.querySelector(sel) as HTMLElement | null;
    const body = (m?.innerText || '');
    const ref = body.match(/Class Ref\s*#?\s*(\d+)/);
    return {
      classRef: ref ? '#' + ref[1] : 'ABSENT',
      footer: Array.from(m?.querySelectorAll('.modal-footer button, .modal-footer a') || [])
        .map((x) => (x.textContent || '').trim() + '[' + getComputedStyle(x).display + ']'),
    };
  }, modal);
  await page.locator(`${modal} .btn-close-modal`).click().catch(() => {});
  return result;
}

test('venue B010 week 7 — the pending block keeps its date, its class ref and its request link', async ({ page }) => {
  const blocks = await venueWeek(page, 'B010', W7);
  const amber = blocks.find((b) => b.text.includes('AMSE2003(P)') && b.text.includes('2:00 PM'));

  // bug #1: the note the two reference pages had all along
  expect(amber?.cls).toBe('span-2 event-mine-pending');
  expect(amber?.text).toBe('AMSE2003(P) B010 2:00 PM - 3:00 PM (Pending since 02 Nov 2026)');

  // bug #5 + #6: the CLASS ref (not the slot id) and the shared footer action
  // the AMBER block (2:00 PM), not the class's own 9:00 AM block
  const modal = await openBlockAndReadFooter(page, 'AMSE2003(P)', '#classModal', '2:00 PM');
  expect(modal.classRef).toBe('#40');
  expect(modal.footer.join(' ')).toContain('View Full Request[inline-flex]');
  expect(modal.footer.join(' ')).toContain('Replace Now[none]');

  // bug #9 (the user's `+` ruling): Total is a composition card too
  const cards = await page.evaluate(() => ({
    total: document.getElementById('sumTotal')?.textContent,
    available: document.getElementById('sumAvailable')?.textContent,
    occupied: document.getElementById('sumOccupied')?.textContent,
    tails: document.querySelectorAll('.summary-value .sum-pending').length,
  }));
  expect(cards.total).toBe('118+2');
  expect(cards.available).toBe('56');          // plain — already the post-hold count
  expect(cards.occupied).toBe('24+2');
  expect(cards.tails).toBe(4);                 // Total + Occupied + the two My cards
});

test('venue B009 week 7 — a cancelled class is red, labelled and replaceable', async ({ page }) => {
  const blocks = await venueWeek(page, 'B009', W7);
  const cancelled = blocks.filter((b) => b.text.includes('BMSE3153(P)'));

  // bug #2: the same status/appearance the other two pages always had
  expect(cancelled).toHaveLength(1);
  expect(cancelled[0].cls).toBe('span-2 event-conflict');
  expect(cancelled[0].text).toBe('BMSE3153(P) B009 1:00 PM - 2:00 PM (Cancelled)');

  const modal = await openBlockAndReadFooter(page, 'BMSE3153(P)');
  expect(modal.classRef).toBe('#23');
  expect(modal.footer.join(' ')).toContain('Replace Now[flex]');   // a cancelled class NEEDS one
  expect(modal.footer.join(' ')).not.toContain('Cancel Class');    // bug #7 — the action is spent
});

test('venue B010 week 14 — the class carrying a proposal is not deleted', async ({ page }) => {
  const blocks = await venueWeek(page, 'B010', W14);
  const proposed = blocks.find((b) => b.text.includes('AMSE2003(P)'));

  // bug #3: present, red, and honest about the proposal
  expect(proposed?.cls).toBe('span-2 event-conflict');
  expect(proposed?.text).toBe('AMSE2003(P) B010 9:00 AM - 10:00 AM (Proposed ⏳)');

  // the pair is HANDLED → no dead-end Replace Now; the Class Ref still resolves
  const modal = await openBlockAndReadFooter(page, 'AMSE2003(P)');
  expect(modal.classRef).toBe('#40');
  expect(modal.footer.join(' ')).toContain('Replace Now[none]');

  // the derived block is counted again (it used to vanish from the card too)
  const occupied = await page.evaluate(() => document.getElementById('sumOccupied')?.textContent);
  expect(occupied).toBe('24+0');
});

test('cohort page — Replace Now follows the served conflict fact, not a guess', async ({ page }) => {
  const openCohortWeek = async (cohortRe: RegExp, weekIdx: number, blockText: string) => {
    await page.goto(`${COHORT}?_=${Date.now()}`, { waitUntil: 'networkidle' });
    const faculties = await page.locator('#facultySelect option').evaluateAll((o) => o.map((x) => (x as HTMLOptionElement).value).filter(Boolean));
    for (const f of faculties) {
      await page.selectOption('#facultySelect', f);
      await page.waitForTimeout(500);
      const options = await page.locator('#cohortSelect option').evaluateAll((o) => o.map((x) => ({ v: (x as HTMLOptionElement).value, t: x.textContent || '' })));
      for (const hit of options.filter((o) => cohortRe.test(o.t))) {
        await page.selectOption('#cohortSelect', hit.v);
        await page.waitForTimeout(700);
        await page.locator('#weekSelect').selectOption({ index: weekIdx });
        await page.waitForTimeout(700);
        const block = page.locator('#tableBody .event-block', { hasText: blockText }).first();
        if (!(await block.count())) continue;
        const text = (await block.innerText()).replace(/\s+/g, ' ').trim();
        await block.click();
        await page.waitForTimeout(600);
        const display = await page.evaluate(() => getComputedStyle(document.getElementById('btnReplaceNow') as HTMLElement).display);
        await page.locator('#eventModal .btn-close-modal').click().catch(() => {});
        return { text, display };
      }
    }
    throw new Error('fixture block not found: ' + blockText);
  };

  // bug #4 — the reported case: a proposal already exists → no button
  const proposed = await openCohortWeek(/DSF2/i, W14, 'AMSE2003(P)');
  expect(proposed.text).toContain('(Proposed ⏳)');
  expect(proposed.display).toBe('none');

  // the inverse (a cancelled class still needs one) + the holiday sanity case
  const cancelled = await openCohortWeek(/RSD3/i, W7, 'BMSE3153(P)');
  expect(cancelled.text).toContain('(Cancelled)');
  expect(cancelled.display).toBe('flex');

  const holiday = await openCohortWeek(/DSF1/i, W8, 'AMSE1003(L)');
  expect(holiday.display).toBe('flex');
});

test('my-timetable is untouched — plain numbers, same class ref', async ({ page }) => {
  await page.goto(`${MY}?_=${Date.now()}`, { waitUntil: 'networkidle' });
  const cards = await page.evaluate(() => ({
    total: document.getElementById('sumTotal')?.textContent,
    tails: document.querySelectorAll('.summary-value .sum-pending').length,
  }));
  // the shared-helper default is frozen: no split tails on this page
  expect(cards.tails).toBe(0);
  expect(cards.total).toMatch(/^\d+$/);

  await page.locator('#weekSelect').selectOption({ index: W7 });
  await page.waitForTimeout(800);
  const modal = await openBlockAndReadFooter(page, 'BMSE3153(P)');
  expect(modal.classRef).toBe('#23');
  expect(modal.footer.join(' ')).toContain('Replace Now[flex]');
});
