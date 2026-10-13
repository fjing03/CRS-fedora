/**
 * type-labels.spec.ts — B1 (venue-pending-and-type-labels): the shared
 * UiLabels.classType helper must render P as Practical/(P) on the
 * replacement-home table, while L and T stay byte-identical to the old
 * binary ternary (regression guard).
 *
 * Fixtures (demo DB, AMCS2093 — rows identified by venue+time, not index):
 *   #39 P  Thu 13:30–2:30 PM  B010   (was mislabelled "(T)" before B1)
 *   #49 P  Fri 11:00–12:00 AM B011   (was mislabelled "(T)" before B1)
 *   #97 L  Mon 11:00          B110
 *   #94 T  Thu 14:30          B107
 *
 * ONE test = ONE login: the Fortify login limiter is 5/min per
 * login_id|login_type|ip (FortifyServiceProvider), so per-test logins
 * trip 429 on back-to-back runs (learned the hard way — see the
 * change's review-log).
 */
import { test, expect, type Page } from '@playwright/test';

const PW = 'Tarumt@2026';

async function loginAsStaff(page: Page, id = '5770'): Promise<void> {
  await page.goto('/login/staff');
  await page.fill('#login_id', id);
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  // artisan serve is single-threaded — first boot / DB-cold logins can spike
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 30_000 });
}

/** The single home-table row for an AMCS2093 session, pinned by venue+time. */
function sessionRow(page: Page, venue: string, time: string) {
  return page.locator('#tableBody tr', { hasText: 'AMCS2093' })
    .filter({ hasText: venue })
    .filter({ hasText: time });
}

test('AMCS2093 type labels: P rows say "(P)"/Practical, L and T stay byte-identical', async ({ page }) => {
  await loginAsStaff(page);
  await page.goto('/replacement-home-ui');
  // skeleton loader paints before the real rows — wait for a real row
  await expect(page.locator('#tableBody tr').first()).toBeVisible({ timeout: 15_000 });

  // #39 — Thu 13:30–2:30 PM B010 (P; was "(T)" before B1)
  const thu = sessionRow(page, 'B010', '1:30 PM');
  await expect(thu).toHaveCount(1);
  await expect(thu.locator('.cell-type-label')).toHaveText('(P)');

  // #49 — Fri 11:00 AM–12:00 PM B011 (P; was "(T)" before B1)
  const fri = sessionRow(page, 'B011', '11:00 AM');
  await expect(fri).toHaveCount(1);
  await expect(fri.locator('.cell-type-label')).toHaveText('(P)');

  // quick-view modal renders the long form for the picked P session
  await thu.click();
  await expect(page.locator('#quickViewModal')).toBeVisible();
  await expect(page.locator('#quickViewModal')).toContainText('Practical');
  // scope to the modal — a second hidden .btn-close-modal (keyboard shortcuts) exists earlier in the DOM
  await page.locator('#quickViewModal .btn-close-modal').click();

  // regression guard — L and T rows byte-identical to the old ternary output
  const lec = sessionRow(page, 'B110', '11:00 AM');
  await expect(lec).toHaveCount(1);
  await expect(lec.locator('.cell-type-label')).toHaveText('(L)');

  const tut = sessionRow(page, 'B107', '2:30 PM');
  await expect(tut).toHaveCount(1);
  await expect(tut.locator('.cell-type-label')).toHaveText('(T)');
});
