import { test, expect, type Page } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * cancel-whole-day (2026-11-11) — T5's receipt: the modal's whole-day block and
 * the day confirm, end to end on the DISPOSABLE database.
 *
 * It writes (four classes cancelled, four audit rows, the request paths closed),
 * so it is guarded: run it only against `class_replacement_testing` on :8030,
 * after `migrate:fresh --seed` + `TestingFixtureSeeder`:
 *
 *   DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030
 *   CRS_BASE=http://localhost:8030 npx playwright test tests/cancel-whole-day.spec.ts
 *
 * What it pins (spec R1–R6): the block appears with the day's OTHER classes,
 * every row is its own tick, the sentence says "on the same day", the head tick
 * and the red button follow the ticks, a reason arms the button, the confirm
 * cancels the whole day in one action, the success screen lists all of them with
 * ONE Close (no Arrange), and the server afterwards has nothing left that day.
 */

abortUnlessTestingTarget('tests/cancel-whole-day.spec.ts', { requireAllowSql: true });

const PW = 'Tarumt@2026';

async function login(page: Page) {
  await page.goto('/login/staff');
  await page.fill('#login_id', '5425');
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 30000 });
}

test('a whole day cancels in one action and says so', async ({ page }) => {
  test.setTimeout(120_000);
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

  await page.setViewportSize({ width: 1440, height: 950 });
  await login(page);
  await page.goto(`/my-timetable-ui?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);

  // a day this lecturer teaches more than one class on, ahead of the window
  const pageWeek = await page.evaluate(() => Number((window as any).currentWeek) + 1);
  const target = await page.evaluate(async (pw) => {
    const lw = (window as any).pageLivewire ? (window as any).pageLivewire() : (window as any).Livewire.first();
    for (let w = pw; w <= 13; w++) {
      for (let d = 0; d <= 5; d++) {
        const res = await lw.call('myDayClasses', w, d);
        if (res && res.ok && res.classes && res.classes.length >= 2) {
          return { week: w, day: d, ids: res.classes.map((c: any) => c.sessionId), count: res.classes.length };
        }
      }
    }
    return null;
  }, pageWeek);
  console.log('target:', JSON.stringify(target));
  expect(target).not.toBeNull();
  if (!target) return;

  await page.evaluate((wk) => { const f = (window as any).selectWeek; if (f) f(String(wk)); }, target.week);
  await page.waitForTimeout(1100);
  await page.evaluate((ids) => {
    const blocks = Array.from(document.querySelectorAll('#tableBody .event-block')) as HTMLElement[];
    const hit = blocks.find((b) => ids.includes(Number(b.dataset.sid))) || blocks[0];
    hit?.click();
  }, target.ids);
  await page.waitForTimeout(700);
  await page.click('[data-cancel-class-btn]');
  await page.waitForTimeout(1300);

  // the block: all rows ticked, the button names the total
  const before = await page.evaluate(() => ({
    rows: document.querySelectorAll('#cancelDayScope .cancel-day-row').length,
    yes: document.getElementById('confirmCancelClassBtn')!.textContent!.trim(),
    disabled: (document.getElementById('confirmCancelClassBtn') as HTMLButtonElement).disabled,
  }));
  const sentence = await page.evaluate(() => (document.querySelector('#cancelDayScope .cancel-day-toggle span') as HTMLElement).innerText.trim().replace(/\s+/g, ' '));
  console.log('sentence:', sentence);
  expect(sentence.toLowerCase()).toBe(`also cancel my other ${target.count - 1} classes on the same day`);
  console.log('block:', JSON.stringify(before));
  expect(before.rows).toBe(target.count - 1);
  /* 2026-11-11 (user): the block opens UNTICKED and folded, so the button still
     says the single-class words until the head tick is pressed */
  expect(before.yes).toBe('Yes, Cancel Class');
  expect(before.disabled).toBe(true);          // no reason yet

  // tick the head → every row selected, the words follow
  await page.click('#cancelDayScope .cancel-day-toggle input');
  await page.waitForTimeout(250);
  const all = await page.evaluate(() => ({
    rowsTicked: Array.from(document.querySelectorAll('#cancelDayScope .cancel-day-row input')).filter((b) => (b as HTMLInputElement).checked).length,
    yes: document.getElementById('confirmCancelClassBtn')!.textContent!.trim(),
    folded: document.getElementById('cancelDayScope')!.classList.contains('is-collapsed'),
  }));
  console.log('all ticked:', JSON.stringify(all));
  expect(all.rowsTicked).toBe(target.count - 1);
  expect(all.yes).toBe(`Yes, Cancel ${target.count} Classes`);
  expect(all.folded).toBe(false);

  // untick one row → the sentence and the button both follow
  await page.evaluate(() => {
    const b = document.querySelectorAll('#cancelDayScope .cancel-day-row input')[0] as HTMLInputElement;
    b.checked = false; b.dispatchEvent(new Event('change', { bubbles: true }));
  });
  const partial = await page.evaluate(() => ({
    count: document.querySelector('#cancelDayScope .cd-count')!.textContent!.trim(),
    yes: document.getElementById('confirmCancelClassBtn')!.textContent!.trim(),
  }));
  console.log('partial:', JSON.stringify(partial));
  expect(partial.yes).toBe(`Yes, Cancel ${target.count - 1} Classes`);

  // tick it back, pick a reason, confirm
  await page.evaluate(() => {
    const b = document.querySelectorAll('#cancelDayScope .cancel-day-row input')[0] as HTMLInputElement;
    b.checked = true; b.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await page.click('#cancelReasonList input[value="medical_leave"]');
  await page.waitForTimeout(200);
  expect(await page.evaluate(() => (document.getElementById('confirmCancelClassBtn') as HTMLButtonElement).disabled)).toBe(false);

  /* the week the page is actually on (selectWeek takes the component's own
     index) — this is the week the cancel will write, so it is the week the
     after-check must ask about */
  const weekCancelled = await page.evaluate(() => Number((window as any).currentWeek) + 1);

  await page.click('#confirmCancelClassBtn');
  await page.waitForTimeout(2200);

  const done = await page.evaluate(() => {
    const box = document.getElementById('cancelDaySuccess') as HTMLElement;
    return {
      shown: box && box.style.display !== 'none',
      head: (box.querySelector('p') || {}).textContent,
      lines: Array.from(box.querySelectorAll('.cancel-day-done li')).length,
      closeVisible: getComputedStyle(document.getElementById('cancelDayCloseBtn') as HTMLElement).display !== 'none',
      arrangeVisible: getComputedStyle(document.getElementById('cancelArrangeNowBtn') as HTMLElement).display !== 'none',
      laterVisible: getComputedStyle(document.getElementById('cancelLaterBtn') as HTMLElement).display !== 'none',
    };
  });
  console.log('success:', JSON.stringify(done));
  expect(done.shown).toBe(true);
  expect(done.head).toContain(`${target.count} classes cancelled`);
  expect(done.lines).toBe(target.count);
  expect(done.closeVisible).toBe(true);
  expect(done.arrangeVisible).toBe(false);
  expect(done.laterVisible).toBe(false);

  // the label says "on the same day", the day's name is gone from the sentence
  // (checked before the cancel, from the same DOM the user reads)

  // the server agrees: that day now has nothing left to cancel (fresh page read,
  // so the call never rides a component the modal may have re-rendered)
  await page.evaluate(() => (window as any).closeModal && (window as any).closeModal());
  await page.waitForTimeout(400);
  await page.goto(`/my-timetable-ui?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  const after = await page.evaluate(async (t) => {
    const lw = (window as any).pageLivewire ? (window as any).pageLivewire() : (window as any).Livewire.first();
    const res = await lw.call('myDayClasses', t.week, t.day);
    return res && res.classes ? res.classes.length : -1;
  }, { week: weekCancelled, day: target.day });
  console.log('classes left that day:', after);
  expect(after).toBe(0);

  await page.screenshot({ path: '/tmp/opencode/day-success.png' });
  console.log('errors:', errors.length, errors.slice(0, 4));
});
