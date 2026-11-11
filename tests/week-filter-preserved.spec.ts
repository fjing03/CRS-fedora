import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * week-filter-preserved (2026-11-06) — the user's bug report: after an action
 * that re-renders the PAGE component (Approve / Reject on request-approval-ui,
 * Cancel Pending on my-replacements) the Week dropdown went blank while its
 * filter chip stayed and printed a bare "Week: ".
 *
 * Cause (measured, not guessed): the week selects have NO server-side options —
 * every page builds them with populateWeekSelect() from weekRanges/weekData. A
 * page-component round-trip morphs the DOM from the server's HTML, where that
 * <select> is empty, so the options were deleted and the value collapsed to ''.
 * The chip then rebuilt from an empty select.
 *
 * Fix: ui-common.js snapshots every registered select's value before the
 * round-trip and restores the options + value before the caller's .then()
 * (withWeekLivewireGuard, plus a 'commit'-hook net). This spec is the receipt:
 * all three actions, one week selected, filter still there afterwards.
 *
 * WRITES (it really approves/rejects/cancels request 26) → testing lane only;
 * the shared guard aborts any bare/demo-targeted run at collection.
 *
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/week-filter-preserved.spec.ts
 */
abortUnlessTestingTarget('tests/week-filter-preserved.spec.ts', { requireAllowSql: true });

/* ONE login per run via the setup project (Fortify limiter is 5/min). */
test.use({ storageState: '.auth/staff-5425.json' });

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';
/* 2026-11-07 (user): the two pages filter on DIFFERENT dates — My Replacements on
   the ORIGINAL CLASS week (14 for request 26), the PL queue on the REQUESTED
   REPLACEMENT week (the fixture's proposal lands in week 7, Fri 6 Nov). */
const WEEK = '14';          // request 26's original-class week (My Replacements)
const PL_WEEK = '7';        // request 26's requested-replacement week (PL queue)
const ALL_WEEKS = 15;       // 'all' + weeks 1..14

/**
 * Request 26 must be PENDING for every test (the seeder is idempotent: it clears
 * its own rows first), so each test starts from the same fixture.
 */
function sql(query: string): void {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' });
}

test.beforeEach(() => {
  /* The seeder is idempotent for its OWN rows, but a decision leaves an
     audit_logs row (and a notification) that still points at the request, so the
     FK blocks the delete. Clear the WHOLE trail first (audit_logs.replacement_request_id,
     notifications.request_id) — a spec that created its own request leaves an id
     this fixture list does not cover, and the seeder then dies on the FK. The
     testing DB is disposable. */
  sql('delete from audit_logs');
  sql('delete from notifications');
  execSync('DB_DATABASE=class_replacement_testing php artisan db:seed --class=TestingFixtureSeeder', {
    cwd: ROOT,
    stdio: 'pipe',
  });
});

async function openWithWeek(page: Page, url: string, week: string = WEEK, text: string = 'Week 14') {
  await page.goto(url, { waitUntil: 'networkidle' });
  await expect(page.locator('#weekFilter option')).toHaveCount(ALL_WEEKS);
  await page.selectOption('#weekFilter', week);
  await expect(page.locator('#weekFilter')).toHaveValue(week);
  await expect(weekChip(page)).toContainText(text);
}

/** The PL queue's week is the REQUESTED REPLACEMENT week (week 7 in the fixture). */
async function openWithRequestedWeek(page: Page, url: string) {
  await openWithWeek(page, url, PL_WEEK, 'Week 7');
}

function weekChip(page: Page) {
  return page.locator('#filterChips .filter-chip', { hasText: 'Week:' });
}

/**
 * The bug's exact shape, asserted in both halves: the dropdown keeps its value
 * AND its label, and the chip still names the week instead of printing
 * "Week: " with nothing after the colon.
 */
async function expectWeekFilterKept(page: Page, week: string = WEEK, text: string = 'Week 14') {
  await expect(page.locator('#weekFilter option')).toHaveCount(ALL_WEEKS);
  await expect(page.locator('#weekFilter')).toHaveValue(week);
  await expect(page.locator('#weekFilter option:checked')).toContainText(text);
  await expect(weekChip(page)).toHaveCount(1);
  await expect(weekChip(page)).toContainText(text);
  await expect(weekChip(page)).not.toHaveText(/Week:\s*×$/);
}

test('U1 — Approve keeps the Week filter', async ({ page }) => {
  await openWithRequestedWeek(page, '/request-approval-ui');

  // the pending row for request 26 carries its PROPOSAL in week 7 — that is the
  // week this page filters on, so the row is visible here
  await expect(page.locator('#tableBody tr')).toHaveCount(1);

  await page.locator('#tableBody .btn-approve').click();
  await expect(page.locator('#approveNotesModal')).toHaveClass(/show/);
  await page.fill('#approveNotesInput', 'week-filter receipt');
  await page.click('#confirmApproveBtn');

  // the receipt: the row left the list, and the week survived the re-render
  await expect(page.locator('#tableBody tr')).toHaveCount(0);
  await expectWeekFilterKept(page, PL_WEEK, 'Week 7');

  // and the filter still WORKS: a week with a decided row still filters on it
  await page.selectOption('#weekFilter', 'all');
  await expect(page.locator('#tableBody tr')).toHaveCount(0); // Pending-only default
});

test('U2 — Reject keeps the Week filter', async ({ page }) => {
  await openWithRequestedWeek(page, '/request-approval-ui');

  await expect(page.locator('#tableBody tr')).toHaveCount(1);

  await page.locator('#tableBody .btn-reject').click();
  await expect(page.locator('#rejectReasonModal')).toHaveClass(/show/);
  await page.fill('#rejectReasonInput', 'week-filter receipt');
  await page.click('#confirmRejectBtn');

  await expect(page.locator('#tableBody tr')).toHaveCount(0);
  await expectWeekFilterKept(page, PL_WEEK, 'Week 7');
});

test('U3 — Cancel Pending keeps the Week filter (my-replacements)', async ({ page }) => {
  await openWithWeek(page, '/my-replacements');

  /* 2026-11-07 (user): this page's weeks are the ORIGINAL SLOT weeks, and both the
     "all" option and the chip say so (re-worded to SLOT on 2026-11-10). */
  expect(await page.locator('#weekFilter option[value="all"]').innerText()).toBe('All Original Slot Weeks');
  await expect(page.locator('#filterChips .filter-chip', { hasText: 'Original Slot Week:' })).toContainText('Original Slot Week: Week 14');

  const cancelBtn = page.locator('button[aria-label="Cancel this request"]').first();
  await expect(cancelBtn).toBeVisible();
  await cancelBtn.click();
  await expect(page.locator('#cancelConfirmOverlay')).toBeVisible();
  await page.click('#confirmCancelAction');

  // the cancel REALLY happened (its toast is the receipt), so the assertions
  // below can never pass vacuously
  await expect(page.locator('#toastBar')).toContainText('cancelled');

  // the cancelled row stays on the page (status flips), so the filter must too
  await expect(page.locator('#filterChips .filter-chip', { hasText: 'Week:' })).toContainText('Week 14');
  await expectWeekFilterKept(page);

  /* 2026-11-11 (user report, second half of the same family): the chip printed
     `Original Slot Week: ×` with no words, because the week box was momentarily
     UNSELECTED during a re-render. An empty box is now read as "no week filter":
     the week chip disappears (it is not drawn blank) and the table is NOT emptied
     — before the fix that state matched zero rows. */
  await page.evaluate(() => {
    const sel = document.getElementById('weekFilter') as HTMLSelectElement;
    sel.selectedIndex = -1;                      // exactly what the morph leaves behind
    (window as unknown as { buildTable: () => void }).buildTable();
  });
  await expect(page.locator('#filterChips .filter-chip', { hasText: 'Slot Week:' })).toHaveCount(0);
  expect(await page.locator('#tableBody tr').count()).toBeGreaterThan(0);

  /* …and the shared restore settles the box on the neutral "all" instead of
     leaving it on -1 (selectedIndex -1 was the state that broke all three readers). */
  await page.evaluate(() => {
    const w = window as unknown as { snapshotWeekSelects: () => void; restoreWeekSelects: () => void };
    w.snapshotWeekSelects();
    w.restoreWeekSelects();
  });
  await expect(page.locator('#weekFilter')).toHaveValue('all');
});
