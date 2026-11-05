import { test, expect, type Page } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/* UIUX Update — My Replacements After Submit (2026-11-05, user brief):
   a successful submission no longer toasts — it lands on My Replacements and
   the new row flashes with the SHARED three-pulse highlight.

   This receipt WRITES one pending request, so it needs the disposable testing
   DB and the blessed guard envs:

     DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
     DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030
     CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
       npx playwright test tests/my-replacements-after-submit.spec.ts

   RE-SEED between runs: the created request fences its occurrence. */

abortUnlessTestingTarget('tests/my-replacements-after-submit.spec.ts', { requireAllowSql: true });

const BASE = process.env.CRS_BASE as string;
test.use({ baseURL: BASE });

const PW = 'Tarumt@2026';
/* the pinned arrival the conflict lane already proves (5425's session 67) */
const ARRANGE = '/replacement-arrangement?venue=B110&session=67&week=14&code=AMSE2003'
  + '&cohort=' + encodeURIComponent('DSF2 (S1) G1') + '&from=replacement-home';
const CELL = '#tableBody td[data-day="4"][data-hour="8"] .cell-content'; // FRI 12:00 → 12:00–14:00

async function loginStaff(page: Page): Promise<void> {
  await page.goto('/login/staff');
  await page.fill('#login_id', '5425');
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 40_000 });
}

test('a submission lands on My Replacements with NO toast, and the new row pulses', async ({ page }) => {
  test.setTimeout(240_000);

  let arrivalUrl = '';
  page.on('framenavigated', (f) => {
    // the FIRST match is the document commit; history.replaceState() emits
    // framenavigated too, and that later URL is the already-stripped one.
    if (!arrivalUrl && f === page.mainFrame() && /\/my-replacements/.test(f.url())) arrivalUrl = f.url();
  });

  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(String(e)));

  await loginStaff(page);

  /* AC1 instrumentation — a toast would die with the old document, so the
     observer writes a localStorage flag instead (there is no redirect any more,
     but the flag stays honest across BOTH documents). */
  await page.addInitScript(() => {
    const arm = () => {
      const bar = document.getElementById('toastBar');
      if (!bar) return;
      const flag = () => {
        if (bar.classList.contains('visible') || getComputedStyle(bar).display === 'flex') {
          try { localStorage.setItem('__probeToastSeen', String(Date.now())); } catch (e) { /* ignore */ }
        }
      };
      flag();
      new MutationObserver(flag).observe(bar, { attributes: true, attributeFilter: ['class', 'style'] });
    };
    /* AC3 snapshot — the pulse lives ~1.5 s, so RECORD its computed animation
       while it is running instead of sampling it after the fact. */
    setInterval(() => {
      const row = document.querySelector('tr.bookable-flash');
      if (!row) return;
      /* the pulse arm is `tr.bookable-flash > td` — the TR itself animates
         nothing, so the snapshot must read a CELL. */
      const cs = getComputedStyle(row.querySelector('td') || row);
      try {
        localStorage.setItem('__probeFlash', JSON.stringify({
          requestId: row.dataset.requestId || '',
          name: cs.animationName, dur: cs.animationDuration, count: cs.animationIterationCount,
        }));
      } catch (e) { /* ignore */ }
    }, 40);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', arm);
    else arm();
  });

  // ── AC3 setup: pick the FRI 12:00–14:00 span on the pinned arrival ──
  await page.goto(ARRANGE + '&bust=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.waitForTimeout(3000);
  await page.locator('#weekSelector').selectOption('8');
  await page.waitForTimeout(1200);
  await page.locator(CELL).click();
  await page.waitForTimeout(800);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');

  // ── AC1: start watching from here — no toast may ever appear ──
  await page.evaluate(() => localStorage.removeItem('__probeToastSeen'));

  await page.evaluate(() => (window as any).proceed());
  await page.waitForTimeout(600);
  await expect(page.locator('#confirmModal')).toContainText('Confirm Your Selection', { timeout: 10_000 });

  // ── AC2: no 1.2 s pause — the old flow could not navigate faster than 1200 ms ──
  const t0 = Date.now();
  await page.locator('#modalConfirmBtn').click(); // 'Yes, Submit Request'
  await page.waitForURL(/\/my-replacements/, { timeout: 60_000 });
  const ms = Date.now() - t0;
  console.log('[receipt] submit → navigation ms =', ms);
  expect(ms).toBeLessThan(1200);

  // ── AC1: not one toast, at any point ──
  await page.waitForTimeout(1500);
  console.log('[receipt] toast flag =', await page.evaluate(() => localStorage.getItem('__probeToastSeen')));
  expect(await page.evaluate(() => localStorage.getItem('__probeToastSeen'))).toBeNull();
  await expect(page.locator('#toastBar.visible')).toHaveCount(0);

  // ── AC4: the arrival URL carried the new id, and the param is stripped ──
  console.log('[receipt] arrival url =', arrivalUrl);
  const m = arrivalUrl.match(/[?&]new-req=(\d+)/);
  expect(m, 'the redirect must carry ?new-req=<id>').not.toBeNull();
  const reqId = Number(m![1]);
  expect(reqId).toBeGreaterThan(0);

  // ── AC3 + AC5: the row that flashed is the new one, exactly one, shared pulse ──
  const row = page.locator(`tr[data-request-id="${reqId}"]`);
  await expect(row).toHaveCount(1, { timeout: 15_000 });
  await expect(row).toHaveClass(/bookable-flash/, { timeout: 15_000 });
  await expect(page.locator('tr.bookable-flash')).toHaveCount(1);
  await expect(row).toContainText(/Pending/i);

  const rawFlash = await page.evaluate(() => localStorage.getItem('__probeFlash'));
  console.log('[receipt] flash snapshot =', rawFlash);
  const anim = JSON.parse(rawFlash as string);
  expect(Number(anim.requestId)).toBe(reqId);
  expect(anim.name).toContain('bookable-pulse');
  expect(anim.dur).toBe('0.5s');
  expect(anim.count).toBe('3');

  // ── AC4: the URL is clean once settled, and a reload does not replay it ──
  await expect(page).toHaveURL(/\/my-replacements$/);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3500);
  expect(await page.locator('tr.bookable-flash').count()).toBe(0);

  // ── AC6: a filter that hides the row is cleared FOR THIS VISIT ONLY ──
  await page.evaluate(() => localStorage.setItem('mrh-filters', JSON.stringify({
    status: 'rejected', search: '', week: 'all', excludeCompleted: true,
  })));
  await page.goto('/my-replacements?new-req=' + reqId, { waitUntil: 'domcontentloaded' });
  await expect(page.locator(`tr[data-request-id="${reqId}"].bookable-flash`)).toHaveCount(1, { timeout: 20_000 });
  expect(await page.locator('#statusFilter').inputValue()).toBe('all');
  const saved = await page.evaluate(() => localStorage.getItem('mrh-filters'));
  console.log('[receipt] saved filters after the visit =', saved);
  expect(saved).toContain('"status":"rejected"'); // the clearance was NOT persisted

  // ── AC8: no console errors ──
  expect(errors).toEqual([]);
});
