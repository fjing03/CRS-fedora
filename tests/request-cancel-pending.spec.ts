import { test, expect, type Page } from '@playwright/test';
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
   run is re-runnable after a reseed. The week hop = W9 and the pick =
   FRI 12:00–14:00 match the frozen race-receipt targets (see
   submission-conflict-refresh). */

// The receipt-target-guard (the 2026-11-01 DATA-LOSS INCIDENT's standing
// guard): module-level abort BEFORE anything loads — the demo DB is read-only.
abortUnlessTestingTarget('tests/request-cancel-pending.spec.ts', { requireAllowSql: true });

const BASE = process.env.CRS_BASE as string;
test.use({ baseURL: BASE });

const PW = 'Tarumt@2026';
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
  await row.locator('.col-action .btn-icon--danger').click();
  await expect(page.locator('#cancelConfirmOverlay')).toBeVisible({ timeout: 5_000 });
  // my-replacements UI/UX update (2026-11-04): the warning is Title-Cased
  // with CANCEL. 2026-11-05 (user ruling): ONE irreversible line for every final
  // decision — shared partial, `UNDONE` as the bold warning-coloured keyword.
  await expect(page.locator('#cancelConfirmBody')).toContainText('Are you sure you want to CANCEL this Replacement Request?');
  await expect(page.locator('#cancelConfirmBody')).toContainText('This action cannot be UNDONE.');
  await expect(page.locator('#cancelConfirmBody .warn-action')).toHaveText('UNDONE');
  await expect(page.locator('#cancelConfirmDetails dt')).toHaveCount(4);

  const dialogs: string[] = [];
  page.on('dialog', async (d) => { dialogs.push(d.type()); await d.dismiss().catch(() => {}); });
  await page.locator('#confirmCancelAction').click(); // 'Yes, Cancel Request'
  expect(dialogs).toEqual([]); // the confirmation is in-app only

  // ── M1: the CLASS stays on the list; only its status changes ──
  const cancelled = page.locator('#tableBody tr:has(.col-status .badge:has-text("Cancelled by YOU"))').first();
  await expect(cancelled).toBeVisible({ timeout: 20_000 });

  // ── and the record persists as cancelled on a fresh load ──
  await page.goto('/my-replacements', { waitUntil: 'domcontentloaded' });
  await expect(
    page.locator('#tableBody tr:has(.col-status .badge:has-text("Cancelled by YOU"))').first()
  ).toBeVisible({ timeout: 20_000 });

  // ── the slot is free again (the release) ──
  await arriveHopPick(page);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
});
