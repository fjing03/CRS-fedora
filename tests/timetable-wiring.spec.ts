import { test, expect, Page } from '@playwright/test';

/**
 * Slice A e2e (SDD wire-backend-into-refactored-ui):
 * real-data timetables render through the Livewire components.
 * Credentials: seeded staff (staff_id, no P prefix) + seeded student.
 */
const BASE = 'http://localhost:8000';
const STAFF_ID = '4288';
const STAFF_PASSWORD = 'Tarumt@2026';
const STUDENT_ID = '25DFT0001';
const STUDENT_PASSWORD = 'Tarumt@2026';

async function collectErrors(page: Page): Promise<string[]> {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(err.message));
  return errors;
}

async function loginStaff(page: Page) {
  await page.goto(`${BASE}/login/staff`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', STAFF_ID);
  await page.fill('input[name="password"]', STAFF_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 10_000 });
}

async function loginStudent(page: Page) {
  await page.goto(`${BASE}/login/student`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', STUDENT_ID);
  await page.fill('input[name="password"]', STUDENT_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(student-my-timetable|dashboard)/, { timeout: 10_000 });
}

test.describe('Slice A — real-data timetables', () => {
  test('My Timetable renders the lecturer grid with real sessions', async ({ page }) => {
    const errors = await collectErrors(page);
    await loginStaff(page);

    await page.goto(`${BASE}/my-timetable-ui`, { waitUntil: 'networkidle' });
    await expect(page.locator('#semesterChip')).toContainText('202605');

    // Grid built by the shared engine: header + body populated.
    await expect(page.locator('#tableHead th')).not.toHaveCount(0);
    const events = page.locator('.timetable .event-block');
    await expect(events.first()).toBeVisible();
    expect(await events.count()).toBeGreaterThan(0);

    // Week selector has all 14 weeks.
    const weekOptions = await page.locator('#weekSelect option').count();
    expect(weekOptions).toBe(14);

    // Canonical legend (§10.0 table A).
    await expect(page.locator('.legend-item')).toContainText(['Normal Class']);

    // Click the first class → detail modal opens with real fields.
    await events.first().click();
    await expect(page.locator('#classModal')).toBeVisible();
    await expect(page.locator('#modalBody')).toContainText('Cohort');

    expect(errors, `JS errors: ${errors.join('; ')}`).toHaveLength(0);
  });

  test('My Timetable week navigation repaints the grid', async ({ page }) => {
    await loginStaff(page);
    await page.goto(`${BASE}/my-timetable-ui`, { waitUntil: 'networkidle' });

    await expect(page.locator('.timetable .event-block').first()).toBeVisible();

    // The current week is DATE-RELATIVE (the real-records import set the
    // canonical 202605 semester: today = Week 3, not Week 1) — read it and
    // assert relative movement instead of hardcoded week numbers.
    const subtitle = page.locator('#weekSubtitle');
    const startLabel = (await subtitle.textContent()) ?? '';
    const startWeek = parseInt(startLabel.match(/Week (\d+)/)?.[1] ?? '1', 10);
    expect(startWeek).toBeGreaterThanOrEqual(1);

    await page.click('.week-arrow[aria-label="Next week"]');
    await expect(subtitle).toContainText(`Week ${startWeek + 1}`);

    await page.selectOption('#weekSelect', '0'); // index 0 = week 1
    await expect(subtitle).toContainText('Week 1');
  });

  test('Cohort Timetable lets a lecturer pick faculty + cohort and render', async ({ page }) => {
    const errors = await collectErrors(page);
    await loginStaff(page);

    await page.goto(`${BASE}/cohort-timetable-ui`, { waitUntil: 'networkidle' });
    await expect(page.locator('#emptyState')).toBeVisible(); // guidance first

    // Pick the first faculty, then the first cohort in it.
    const facultyOptions = page.locator('#facultySelect option:not([value=""])');
    await facultySelectHelper(page);

    async function facultySelectHelper(p: Page) {
      const value = await facultyOptions.first().getAttribute('value');
      await p.selectOption('#facultySelect', value!);
      const cohortOptions = p.locator('#cohortSelect option:not([value=""])');
      await expect(cohortOptions.first()).toBeAttached();
      const cohortValue = await cohortOptions.first().getAttribute('value');
      await p.selectOption('#cohortSelect', cohortValue!);
    }

    // Grid renders for the chosen cohort (empty state must be hidden).
    await expect(page.locator('#emptyState')).toBeHidden();
    await expect(page.locator('#tableHead th')).not.toHaveCount(0);

    // Switch to another cohort via the dropdown — grid rebuilds client-side.
    const cohortCount = await page.locator('#cohortSelect option:not([value=""])').count();
    if (cohortCount > 1) {
      const second = await page.locator('#cohortSelect option:not([value=""])').nth(1).getAttribute('value');
      await page.selectOption('#cohortSelect', second!);
      await expect(page.locator('#tableHead th')).not.toHaveCount(0);
    }

    expect(errors, `JS errors: ${errors.join('; ')}`).toHaveLength(0);
  });

  test('Student My Timetable is read-only with own cohort data', async ({ page }) => {
    const errors = await collectErrors(page);
    await loginStudent(page);

    await page.goto(`${BASE}/student-my-timetable-ui`, { waitUntil: 'networkidle' });
    await expect(page.locator('#semesterChip')).toContainText('202605');
    await expect(page.locator('.timetable .event-block').first()).toBeVisible();

    // View-only: no Replace Now / cancel affordances anywhere on the page.
    await expect(page.locator('#btnReplaceNow')).toHaveCount(0);

    // Week navigation works.
    await page.click('.week-arrow[aria-label="Next week"]');
    await expect(page.locator('#weekSubtitle')).toContainText(/Week \d+/);

    expect(errors, `JS errors: ${errors.join('; ')}`).toHaveLength(0);
  });

  test('Guests are redirected to the student login from every UI route', async ({ page }) => {
    for (const uri of [
      '/my-timetable-ui',
      '/cohort-timetable-ui',
      '/student-my-timetable-ui',
      '/request-approval-ui',
    ]) {
      await page.goto(`${BASE}${uri}`, { waitUntil: 'domcontentloaded' });
      expect(page.url()).toContain('/login/student');
    }
  });
});
