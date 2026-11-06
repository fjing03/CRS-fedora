import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/* SDD check #11 — UC07 / FR 2.10 "cancel my own pending request", the receipt
   that was missing (the confirm-guards spec covers change-confirm popups, not
   the cancellation flow). It SUBMITS one real request through the arrangement
   UI, cancels it through My Request History's frozen confirm modal, and then
   proves the slot came back: the same span is bookable again.

   REQUIRES the disposable testing DB + a serve on :8030:
     DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
     DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
     CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
       npx playwright test tests/request-cancel-pending.spec.ts --project=chromium --no-deps

   Net effect: one Cancelled request record remains as evidence, the picked
   span returns to Available (the cancel releases the reservation), and the
   run is re-runnable after a reseed.

   2026-11-06 (user brief "Cancelled Pending Requests"): the record stays
   Cancelled, but the PAGE displays that occurrence as NO ACTIVE REQUEST —
   badge "Need to Arrange" (outlined), an em dash in REQUESTED REPLACEMENT, and
   a details pop-up without the Proposed Replacement Schedule tab. Those three
   are asserted below against the row we just cancelled
   (`tr[data-request-id="…"]`), plus the untouched record through psql. The week hop = W9 and the pick =
   FRI 12:00–14:00 match the frozen race-receipt targets (see
   submission-conflict-refresh). */

// The receipt-target-guard (the 2026-11-01 DATA-LOSS INCIDENT's standing
// guard): module-level abort BEFORE anything loads — the demo DB is read-only.
abortUnlessTestingTarget('tests/request-cancel-pending.spec.ts', { requireAllowSql: true });

const BASE = process.env.CRS_BASE as string;
test.use({ baseURL: BASE });

const PW = 'Tarumt@2026';
const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';

function sql(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return String(execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' })).trim();
}
const WEEK_HOP = '8'; // → W9 (the page may resolve to the next bookable week)
const pickedCell = '#tableBody td[data-day="4"][data-hour="8"] .cell-content'; // FRI 12:00, span 4
const URL = '/replacement-arrangement?venue=B110&session=67&week=14&code=AMSE2003&cohort=' + encodeURIComponent('DSF2 (S1) G1') + '&from=replacement-home';

async function loginStaff(page: Page): Promise<void> {
  await page.goto('/login/staff');
  await page.fill('#login_id', '5425');
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 30_000 });
}

/** the pinned arrival → hop W9 → pick FRI 12:00–14:00 → '4 of 4 slots' */
async function arriveHopPick(page: Page): Promise<void> {
  await page.goto(URL + '&bust=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.waitForTimeout(3000); // the pinned arrival (the bridge + the vectors paint)
  await page.locator('#weekSelector').selectOption(WEEK_HOP);
  await page.waitForTimeout(1200);
  await page.locator(pickedCell).click();
  await page.waitForTimeout(800);
}

test('cancel own pending request leaves it Cancelled and releases the slot', async ({ page }) => {

  test.setTimeout(300_000);

  await loginStaff(page);
  await arriveHopPick(page);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');

  // ── submit for real (the sanctioned testing-DB write) ──
  await page.evaluate(() => (window as any).proceed());
  await page.waitForTimeout(600);
  await expect(page.locator('#confirmModal')).toContainText('Confirm Your Selection', { timeout: 5000 });
  await page.locator('#modalConfirmBtn').click(); // 'Yes, Submit Request'
  await page.waitForURL(/\/my-replacements/, { timeout: 60_000 }); // the design aftermath

  // ── the pending row (needs-action rows group FIRST, so it is not row 1) ──
  const row = page.locator('#tableBody tr:has(.col-status .badge:has-text("Pending"))').first();
  await expect(row).toBeVisible({ timeout: 20_000 });

  // ── cancel from the ROW's ban icon (M6), through the frozen confirm ──
  const onclick = (await row.locator('.col-action .btn-icon--danger').getAttribute('onclick')) || '';
  const requestId = Number((onclick.match(/openCancelConfirm\((\d+)/) || [])[1]);
  expect(requestId, 'the ban icon names the request id: ' + onclick).toBeGreaterThan(0);
  await row.locator('.col-action .btn-icon--danger').click();
  await expect(page.locator('#cancelConfirmOverlay')).toBeVisible({ timeout: 5_000 });
  // my-replacements UI/UX update (2026-11-04): the warning is Title-Cased
  // with CANCEL. 2026-11-05 (user ruling): ONE irreversible line for every final
  // decision — shared partial, `UNDONE` as the bold warning-coloured keyword.
  await expect(page.locator('#cancelConfirmBody')).toContainText('Are you sure you want to CANCEL this Replacement Request?');
  await expect(page.locator('#cancelConfirmBody')).toContainText('This action cannot be UNDONE.');
  /* two keyword spans live in the shared irreversible line (CANCEL … UNDONE) —
     the UNDONE one is the second, so a bare locator is a strict-mode violation */
  await expect(page.locator('#cancelConfirmBody .warn-action').last()).toHaveText('UNDONE');
  await expect(page.locator('#cancelConfirmDetails dt')).toHaveCount(4);

  /* the just-cancelled pulse (2026-11-06): watched from BEFORE the click, because
     the class lives one run (3 × 0.5 s) and the rebuild is synchronous */
  await page.evaluate(() => {
    (window as unknown as { __cancelFlash: unknown }).__cancelFlash = null;
    const body = document.getElementById('tableBody') as HTMLElement;
    const obs = new MutationObserver(() => {
      const tr = document.querySelector('#tableBody tr.bookable-flash--warning') as HTMLElement | null;
      if (!tr) return;
      const td = tr.querySelector('td') as HTMLElement | null;
      const cs = td ? getComputedStyle(td) : null;
      (window as unknown as { __cancelFlash: unknown }).__cancelFlash = {
        requestId: tr.getAttribute('data-request-id'),
        cls: tr.className,
        animation: cs ? cs.animationName : null,
        duration: cs ? cs.animationDuration : null,
        iterations: cs ? cs.animationIterationCount : null,
      };
      obs.disconnect();
    });
    obs.observe(body, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
  });

  const dialogs: string[] = [];
  page.on('dialog', async (d) => { dialogs.push(d.type()); await d.dismiss().catch(() => {}); });
  await page.locator('#confirmCancelAction').click(); // 'Yes, Cancel Request'
  expect(dialogs).toEqual([]); // the confirmation is in-app only

  /* ── M1 (2026-11-06 brief): the CLASS stays on the list, but the occurrence
     now reads as if no request had ever been made — Not "Cancelled by YOU". */
  const row2 = page.locator(`#tableBody tr[data-request-id="${requestId}"]`);
  const badge = row2.locator('.col-status .badge');
  await expect(badge).toHaveText('Need to Arrange', { timeout: 20_000 });
  await expect(badge).toHaveClass(/badge-outline/);
  await expect(badge).toHaveClass(/status-none/);
  await expect(badge).toHaveAttribute('data-tip', 'No request has been made for this class yet — it still needs arranging.');

  // the proposed schedule is gone from the table: an em dash, no date/time/venue
  await expect(row2.locator('.col-requested .cell-dash')).toHaveText('—');

  /* ── the CANCEL pulse: the Earliest/Today flash mechanic, warning tone ── */
  const flash = await page.evaluate(() => (window as unknown as { __cancelFlash: any }).__cancelFlash);
  expect(flash, 'the cancelled row pulses in the warning tone').not.toBeNull();
  expect(flash.requestId, 'the pulse lands on the row that was cancelled').toBe(String(requestId));
  expect(flash.cls).toContain('bookable-flash--warning');
  expect(flash.animation, 'the warning arm of the one pulse').toBe('bookable-pulse-warning');
  expect(flash.duration).toBe('0.5s');
  expect(flash.iterations).toBe('3');

  // …and from the details pop-up: no Proposed tab, no Request ID, two tabs only
  await row2.locator('.col-code').click();
  await expect(page.locator('#quickViewModal')).toBeVisible();
  await expect(page.locator('#quickViewModal .modal-tab')).toHaveCount(2);
  await expect(page.locator('#quickViewModal .modal-tab').first()).toHaveText(/Original Class Schedule/);
  await expect(page.locator('#quickViewModal .modal-tab').nth(1)).toHaveText(/Request Status \(Need to Arrange\)/);
  await expect(page.locator('#quickViewModal')).not.toContainText('Proposed Replacement Schedule');
  await expect(page.locator('#quickViewModal')).not.toContainText('Request ID');
  await page.locator('#quickViewModal .modal-close').click();
  await expect(page.locator('#quickViewModal')).toBeHidden();

  // ── the RECORD is untouched: still cancelled, still audited, junction gone ──
  expect(sql(`select status from replacement_requests where id = ${requestId}`)).toBe('cancelled');
  expect(sql(`select count(*) from replacement_request_slots where replacement_request_id = ${requestId}`)).toBe('0');
  expect(Number(sql(`select count(*) from audit_logs where replacement_request_id = ${requestId} and action = 'cancelled'`))).toBeGreaterThan(0);

  // ── and a fresh load shows the same display state ──
  await page.goto('/my-replacements', { waitUntil: 'domcontentloaded' });
  await expect(
    page.locator(`#tableBody tr[data-request-id="${requestId}"] .col-status .badge`)
  ).toHaveText('Need to Arrange', { timeout: 20_000 });
  await expect(
    page.locator(`#tableBody tr[data-request-id="${requestId}"] .col-requested .cell-dash`)
  ).toHaveText('—');

  // ── the slot is free again (the release) ──
  await arriveHopPick(page);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
});
