/**
 * venue-db.spec.ts — real-browser coverage for the DB-backed Venue Timetable
 * (SDD: venue-timetable-db). Legacy tests/venue-timetable.spec.ts stays
 * untouched (parked decision).
 *
 * Anchors (real data): 23 venues in 4 type groups; B006 week 1 has 48
 * occupied slots incl. BMIT2154 (Mon 09:00). Logins are rate-limited
 * (5/min per ID) — 4 tests, one login each.
 */
import { test, expect, type Page } from '@playwright/test';

const PW = 'Tarumt@2026';

async function loginAsStaff(page: Page, id = '5425'): Promise<void> {
  await page.goto('/login/staff');
  await page.fill('#login_id', id);
  await page.fill('#password', PW);
  await page.click('#loginBtn');
  await page.waitForURL('**/my-timetable-ui', { timeout: 15_000 });
}

test('deep link preselects the venue; grid paints real modules; modal works', async ({ page }) => {
  await loginAsStaff(page);
  await page.goto('/venue-timetable-ui?venue=B006');

  // ?venue= preselect: the dropdown trigger shows the room code
  await expect(page.locator('.venue-dd-label')).toHaveText(/B006/, { timeout: 15_000 });
  // the BMIT2154 Monday-09:00 session paints its code into the grid
  await expect(page.locator('.timetable').first()).toContainText('BMIT2154');

  // event modal: real module + venue extraFields (capacity/room name)
  const cell = page.locator('.timetable .vt-cell-yours, .timetable .vt-cell-others, .timetable .vt-cell-pending').first();
  await cell.click();
  await expect(page.locator('#classModal')).toBeVisible();
  await expect(page.locator('#classModal')).toContainText('BMIT2154');
  await expect(page.locator('#classModal')).toContainText('Cohort');
  await expect(page.locator('#classModal')).toContainText('Capacity');
  await page.click('.btn-close-modal');
});

test('summary cards numeric and repaint on client-side week navigation', async ({ page }) => {
  // Ownership cards are viewer-specific: 5425 teaches nothing in B006, so the
  // cards test logs in as 4288 (Dr. Christopher Lazarus, user 3) — SQL-verified
  // B006 week-1 owner: 8 classes / 28 slots = 14 h (merge-upstream-ui-2026-10).
  await loginAsStaff(page, '4288');
  await page.goto('/venue-timetable-ui?venue=B006');

  const total = page.locator('#sumTotal');
  await expect(total).toHaveText('120', { timeout: 15_000 }); // 6 days × 20 slots
  await expect(page.locator('#sumOccupied')).toHaveText('48');
  await expect(page.locator('#sumAvailable')).toHaveText('72');
  await expect(page.locator('#sumMyClasses')).toHaveText('8');
  await expect(page.locator('#sumMyHours')).toHaveText('14');

  // client-side week navigation repaints grid + summary
  await page.click('.week-arrow[aria-label="Next week"]');
  await expect(total).toHaveText('120');
  await page.click('.week-arrow[aria-label="Previous week"]');
  await expect(page.locator('#sumOccupied')).toHaveText('48');

  // holiday week W8 (index 7): Monday shows no occupied/pending events
  await page.selectOption('#weekSelect', '7');
  const mondayRow = page.locator('.timetable tbody tr').first();
  await expect(mondayRow.locator('.vt-cell-yours, .vt-cell-others, .vt-cell-pending')).toHaveCount(0);
});

test('venue dropdown: 4 type groups, drill-down reaches rooms, switching navigates', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(err.message));

  await loginAsStaff(page);
  await page.goto('/venue-timetable-ui?venue=B006');
  await expect(page.locator('.venue-dd-label')).toHaveText(/B006/, { timeout: 15_000 });

  // open the panel: the TYPE column (4 real room_type groups, data-type = internal name)
  await page.locator('.venue-dd-trigger').click();
  const typeCol = page.locator('.venue-col').first();
  await expect(typeCol).toBeVisible();
  for (const t of ['Tutorial', 'LectureHall', 'Lab', 'CiscoLab']) {
    await expect(typeCol.locator(`.venue-col-item-parent[data-type="${t}"]`)).toBeVisible();
  }

  // drill down: Lab → first block → floor → rooms reachable
  await typeCol.locator('.venue-col-item-parent[data-type="Lab"]').click();
  const blockCol = page.locator('.venue-col').nth(1);
  await blockCol.locator('.venue-col-item-parent').first().click();
  const floorCol = page.locator('.venue-col').nth(2);
  await floorCol.locator('.venue-col-item-parent').first().click();
  const rooms = page.locator('.venue-col').nth(3).locator('.venue-col-item-room');
  await expect(rooms.first()).toBeVisible();

  // selecting another room navigates with ?venue= and repaints
  const target = rooms.first();
  const code = await target.getAttribute('data-code');
  await target.click();
  await page.waitForURL('**/venue-timetable-ui?venue=*', { timeout: 15_000 });
  await expect(page.locator('.venue-dd-label')).toHaveText(new RegExp(code!), { timeout: 15_000 });

  expect(errors).toEqual([]);
});

test('B005 diploma-cohort conflict renders owner-gated (b005-diploma-conflict)', async ({ page }) => {
  // Daniel Royd Michael (5652) owns the AMIT2034 P Wed-11:00 B005 session —
  // the single venue-restriction violation, flagged as a derived conflict.
  await loginAsStaff(page, '5652');
  await page.goto('/venue-timetable-ui?venue=B005');

  // Owner view: loud striped conflict block in week 1.
  await expect(page.locator('.timetable .event-conflict').first()).toBeVisible({ timeout: 15_000 });

  // It is the AMIT2034 block — modal opens with the "needs attention" copy.
  await page.locator('.timetable .event-conflict').first().click();
  await expect(page.locator('#classModal')).toBeVisible();
  await expect(page.locator('#classModal')).toContainText('AMIT2034');
  await page.click('.btn-close-modal');
});
