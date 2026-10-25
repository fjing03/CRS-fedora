import { test, expect, type Page } from '@playwright/test';

/* SDD cohort-venue-replace-now — the acceptance receipts (T4).
   The user law 2026-10-24: "Cohort page & Venue page shud've Replace Now
   button too. for OWN Original Conflicted Class."
   Fixtures verified LIVE (2026-10-24, /tmp/cv-probe10/12/14):
   - 5425 (Surayaini Binti Basri) owns AMSE2003 (L)/(T) on cohort
     DSF2(S1)G1 — W14 (index 13) Thu 24 Dec = a HOLIDAY day (the class
     rows carry status 'normal' but sit ON the holiday: the page's own
     isConflict definition catches it — the D1 apply-refinement);
   - DSF1(S1)G1 carries 5425's AMSE1003 rows at non-holiday weeks
     (status normal, no holiday) = the own-NON-conflict hidden row;
   - Lim Jia Zheng (5770) owns the AMCS2093 rows in DSF2(S1)G1 = the
     others'-conflict hidden rows (the AMCS2093 (T) is holiday-only too;
     (L)/(P) rows at non-holiday weeks stay clean). */

const BASE = 'http://localhost:8000';
const PW = 'Tarumt@2026';

async function loginStaff(page: Page, id: string): Promise<void> {
  await page.goto(`${BASE}/login/staff`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', id);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 15_000 });
}

/** walks the faculty/cohort dropdowns until the named cohort label lands */
async function openCohort(page: Page, cohortRe: RegExp): Promise<void> {
  await page.goto(`${BASE}/cohort-timetable`);
  await page.waitForTimeout(3000);
  const facVals: string[] = await page.$$eval('#facultySelect option', (els) =>
    els.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''),
  );
  for (const v of facVals) {
    await page.selectOption('#facultySelect', v);
    await page.waitForTimeout(350);
    const labels: string[] = await page.$$eval('#cohortSelect option', (els) =>
      els.map((e) => (e as HTMLOptionElement).label).filter(Boolean),
    );
    for (const lbl of labels.slice(1)) {
      if (!cohortRe.test(lbl)) continue;
      await page.locator('#cohortSelect').selectOption({ label: lbl });
      await page.waitForTimeout(600);
      return;
    }
  }
  throw new Error(`cohort ${cohortRe} not reachable (fixture gap)`);
}

test('cohort: OWN conflicted occurrence shows Replace Now + the honest handoff URL', async ({ page }) => {
  await loginStaff(page, '5425');
  await openCohort(page, /DSF2\(S1\)G1/);
  await page.selectOption('#weekSelect', '13'); // index 13 = W14 (the holiday week)
  await page.waitForTimeout(1200);
  const t = page.locator('#tableBody .event-block').filter({ hasText: 'AMSE2003' }).nth(1); // the (T) row
  await t.click();
  await expect(page.locator('#eventModal')).toBeVisible();
  const btn = page.locator('#eventModal #btnReplaceNow');
  await expect(btn).toBeVisible();
  await btn.click();
  await page.waitForURL(/replacement-arrangement/, { timeout: 15_000 });
  // the handoff = the D1 pair + the HUMAN week + the cohort from-param
  await expect(page).toHaveURL(/session=\d+&week=14&from=cohort-timetable/);
  // the arrangement honoured the pin (the own session resolves server-side)
  await page.waitForTimeout(3000);
  const strip = page.locator('#subjectInfo');
  await expect(strip).toContainText('Conflict Schedule');
});

test('cohort: OTHERS’ class modal HIDES Replace Now', async ({ page }) => {
  await loginStaff(page, '5425');
  await openCohort(page, /DSF2\(S1\)G1/);
  await page.selectOption('#weekSelect', '13');
  await page.waitForTimeout(1200);
  // Lim Jia Zheng's AMCS2093 rows in this cohort (non-own)
  const other = page.locator('#tableBody .event-block').filter({ hasText: 'AMCS2093' }).first();
  await other.click();
  await expect(page.locator('#eventModal')).toBeVisible();
  await expect(page.locator('#eventModal #btnReplaceNow')).toBeHidden();
});

test('cohort: OWN non-conflict class modal HIDES Replace Now', async ({ page }) => {
  await loginStaff(page, '5425');
  await openCohort(page, /DSF1\(S1\)G1/);
  await page.selectOption('#weekSelect', '4'); // a non-holiday week
  await page.waitForTimeout(1200);
  const own = page.locator('#tableBody .event-block').filter({ hasText: 'AMSE1003' }).first();
  await own.click();
  await expect(page.locator('#eventModal')).toBeVisible();
  await expect(page.locator('#eventModal #btnReplaceNow')).toBeHidden();
});

test('venue regression: the own-conflict Replace Now hands off the HUMAN week (+1 receipt)', async ({ page }) => {
  await loginStaff(page, '5770');
  // fixture verified live (cv-probe16): 5770's OWN AMCS2093 (P) sits on B010
  // at W14 (index 13, Christmas Eve) — status 'conflict' + mine true. B107's
  // AMCS2093 (T) rows are HEALTHY at W10 (the frozen lane's rnHidden receipt).
  await page.goto(`${BASE}/venue-timetable-ui?venue=B010`);
  await page.waitForTimeout(2500);
  await page.locator('#weekSelect').waitFor({ state: 'visible', timeout: 10_000 });
  await page.selectOption('#weekSelect', '13'); // index 13 = W14 (the holiday week)
  await page.waitForTimeout(900);
  const own = page.locator('#tableBody .event-block').filter({ hasText: 'AMCS2093' }).first();
  await own.click();
  await expect(page.locator('#classModal')).toBeVisible();
  const btn = page.locator('#classModal #btnReplaceNow');
  await expect(btn).toBeVisible();
  await btn.click();
  await page.waitForURL(/replacement-arrangement/, { timeout: 15_000 });
  // the +1 fix: the handoff carries the HUMAN week (14), never the 0-based index (13)
  await expect(page).toHaveURL(/week=14&from=venue-timetable/);
});

test('back-nav: from=cohort-timetable routes the way BACK to the cohort page', async ({ page }) => {
  await loginStaff(page, '5425');
  // the arrangement page's back button honours the route map (read-only: the
  // plain landing's no-selection goBack path navigates at once)
  await page.goto(`${BASE}/replacement-arrangement?session=67&week=14&from=cohort-timetable`);
  await page.waitForTimeout(3000);
  await page.locator('.back-btn').click();
  await page.waitForTimeout(1400);
  // T3's canonical route row (routes/web.php :37 — NOT the temporary -ui redirect)
  await expect(page).toHaveURL(/cohort-timetable(?:$|\?)/);
});
