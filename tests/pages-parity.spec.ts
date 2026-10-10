/**
 * pages-parity.spec.ts — real-browser pins for the all-pages-design-parity
 * SDD change: cohort / my-timetable / student pages render the frozen glue.
 *
 * Logins: 4288 ×1, 5652 ×1, 25DFT0001 ×1 — within the 5/min/ID budget.
 * Expectations derive from seed data (never "always 0").
 */
import { test, expect, type Page } from '@playwright/test';

const BASE = 'http://localhost:8000';
const PW = 'Tarumt@2026';

async function loginStaff(page: Page, id: string): Promise<void> {
  await page.goto(`${BASE}/login/staff`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', id);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 15_000 });
}

async function loginStudent(page: Page): Promise<void> {
  await page.goto(`${BASE}/login/student`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', '25DFT0001');
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(student-my-timetable|dashboard)/, { timeout: 15_000 });
}

test('cohort: frozen legend + ownership axis works + 2-segment tooltips', async ({ page }) => {
  await loginStaff(page, '4288'); // teaches RSD3(S1)G1/G2 blocks (dataset)
  await page.goto(`${BASE}/cohort-timetable-ui`);

  // walk faculty → cohort dropdowns until RSD3(S1)G1 is reachable (client-driven)
  await page.waitForFunction(
    () => (document.getElementById('facultySelect') as HTMLSelectElement | null)?.options.length > 1,
    undefined,
    { timeout: 15_000 },
  );
  const facVals = await page.$$eval('#facultySelect option', (els) =>
    els.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''),
  );
  let picked = false;
  for (const v of facVals) {
    await page.selectOption('#facultySelect', v);
    const has = await page.$$eval('#cohortSelect option', (els) =>
      els.some((e) => e.textContent?.includes('RSD3(S1)G1')),
    );
    if (has) {
      await page.selectOption('#cohortSelect', { label: 'RSD3(S1)G1' });
      picked = true;
      break;
    }
  }
  expect(picked).toBe(true);

  // C1: frozen 3-item legend + ownership hint
  await expect(page.locator('.legend-bar .legend-item')).toHaveText(['Normal', 'Pending', 'Conflict / Public Holiday']);
  await expect(page.locator('.legend-ownership-hint')).toContainText('thick border (3px)');

  // C5: the ownership axis actually works — 4288's own blocks render event-mine
  const mine = page.locator('.timetable .event-mine').first();
  await expect(mine).toBeVisible({ timeout: 15_000 });

  // C3: 2-segment tooltip — lecturer · status (no doubled lecturer, no '—' filler)
  const tip2 = await mine.getAttribute('data-tip2');
  expect(tip2).toMatch(/^[^·]+ · [^·]+$/);

  // C2: frozen card ids present
  for (const id of ['sumTotal', 'sumHours', 'sumMyClasses', 'sumMyHours', 'sumConflict']) {
    await expect(page.locator(`#${id}`)).toBeVisible();
  }
});

test('my-timetable: B005 conflicted class shows Replace Now (M3)', async ({ page }) => {
  await loginStaff(page, '5652'); // Daniel Royd Michael — owns the AMIT2034 B005 derived conflict
  await page.goto(`${BASE}/my-timetable-ui`);

  const conflict = page.locator('.timetable .event-conflict').first();
  await expect(conflict).toBeVisible({ timeout: 15_000 });
  await conflict.click();
  await expect(page.locator('#classModal')).toBeVisible();
  await expect(page.locator('#btnReplaceNow')).toBeVisible();
});

test('student: real identity overrides mock persona; frozen cards present', async ({ page }) => {
  await loginStudent(page);
  await page.goto(`${BASE}/student-my-timetable-ui`);

  // S3: value pin — mock-data ships 'En. Lim Jia Zheng' as default; the page
  // must override it with the authenticated student's name.
  const name = await page.evaluate(() => (window as any).MockData?.currentUser?.name ?? '');
  expect(name).toContain('25DFT0001');

  // S1: frozen 5-card set + frozen default legend (4 block-class chips)
  for (const id of ['sumTotal', 'sumHours', 'sumReplacement', 'sumPending', 'sumConflict']) {
    await expect(page.locator(`#${id}`)).toBeVisible();
  }
  await expect(page.locator('.legend-bar .legend-item')).toHaveCount(4);
});
