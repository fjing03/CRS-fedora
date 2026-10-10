/**
 * venue-db.spec.ts — real-browser coverage for the DB-backed Venue Timetable
 * (SDD: venue-timetable-db; re-pinned to the event-blocks renderer by
 * venue-event-blocks-db). Legacy tests/venue-timetable.spec.ts stays
 * untouched (parked decision).
 *
 * Anchors (real data): 23 venues in 3 dropdown categories (CiscoLab folds
 * under Lab); B006 week 1 has 48 occupied slots incl. BMIT2154 (Mon 09:00);
 * B005 has the single derived conflict (AMIT2034, owner 5652); holidays W8
 * Mon + W14 Wed/Thu. Logins are rate-limited (5/min per ID) — 5 tests,
 * each ID logs in at most 3× within the run.
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

test('deep link preselects the venue; blocks paint the two-axis language; modal works', async ({ page }) => {
  await loginAsStaff(page);
  await page.goto('/venue-timetable-ui?venue=B006');

  // ?venue= preselect: the dropdown trigger shows the room code
  await expect(page.locator('.venue-dd-label')).toHaveText(/B006/, { timeout: 15_000 });
  // the BMIT2154 Monday-09:00 session paints its code into the grid
  await expect(page.locator('.timetable').first()).toContainText('BMIT2154');

  // legend: exactly 4 items in frozen order + the ownership hint (spec R3)
  const items = page.locator('.legend-bar .legend-item');
  await expect(items).toHaveText(['Available', 'Normal', 'Pending', 'Conflict / Public Holiday']);
  await expect(page.locator('.legend-ownership-hint')).toContainText('thick border (3px)');
  await expect(page.locator('.legend-ownership-hint')).toContainText('thin border (0.5px)');

  // event blocks: 5425 owns nothing in B006 → others' normal blocks
  const block = page.locator('.timetable .event-others').first();
  await expect(block).toBeVisible({ timeout: 15_000 });

  // tooltip contract (spec R4): data-name = class name, data-tip2 =
  // "lecturer · status"; theme.css .event-block::after composes the rendered
  // "name · lecturer · status" from these two attributes.
  const bmit = page.locator('.timetable .event-block', { hasText: 'BMIT2154' }).first();
  await expect(bmit).toBeVisible();
  await expect(bmit).toHaveClass(/span-4/);   // coalesced 2-hour lecture (09:00–11:00 = 4 half-hours)
  await expect(bmit).toHaveAttribute('data-name', 'Switching and Routing Technologies');
  await expect(bmit).toHaveAttribute('data-tip2', /.+ · .+/);

  // modal: real module + venue extraFields (capacity/room name)
  await block.click();
  await expect(page.locator('#classModal')).toBeVisible();
  await expect(page.locator('#classModal')).toContainText('BMIT2154');
  await expect(page.locator('#classModal')).toContainText('Cohort');
  await expect(page.locator('#classModal')).toContainText('Capacity');
  await page.click('.btn-close-modal');
});

test('summary cards numeric and repaint on client-side week navigation', async ({ page }) => {
  // Ownership cards are viewer-specific: 4288 (Dr. Christopher Lazarus,
  // user 3) — SQL-verified B006 week-1 owner: 8 classes / 28 slots = 14 h
  // (merge-upstream-ui-2026-10). Available still 72 in W1: no holiday days
  // that week (the exclusion fix only bites on W8/W14).
  await loginAsStaff(page, '4288');
  await page.goto('/venue-timetable-ui?venue=B006');

  const total = page.locator('#sumTotal');
  await expect(total).toHaveText('120', { timeout: 15_000 }); // 6 days × 20 DB rows
  await expect(page.locator('#sumOccupied')).toHaveText('48');
  await expect(page.locator('#sumAvailable')).toHaveText('72');
  await expect(page.locator('#sumMyClasses')).toHaveText('8');
  await expect(page.locator('#sumMyHours')).toHaveText('14');

  // client-side week navigation repaints grid + summary
  await page.click('.week-arrow[aria-label="Next week"]');
  await expect(total).toHaveText('120');
  await page.click('.week-arrow[aria-label="Previous week"]');
  await expect(page.locator('#sumOccupied')).toHaveText('48');

  // holiday week W8 (index 7): Monday renders offday cells — no green
  // Available tint, no normal blocks (others' classes hidden; spec R2)
  await page.selectOption('#weekSelect', '7');
  const mondayRow = page.locator('.timetable tbody tr').first();
  await expect(mondayRow.locator('.cell-ph').first()).toBeVisible();
  await expect(mondayRow.locator('.cell-empty')).toHaveCount(0);
  await expect(mondayRow.locator('.event-mine, .event-others')).toHaveCount(0);
});

test('venue dropdown: 3 categories (B006 under Lab), full-name tips, switching navigates', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(err.message));

  await loginAsStaff(page);
  await page.goto('/venue-timetable-ui?venue=B006');
  await expect(page.locator('.venue-dd-label')).toHaveText(/B006/, { timeout: 15_000 });

  // open the panel: the TYPE column — 3 categories post-merge (_typeOf folds
  // CiscoLab into Lab); the standalone CiscoLab group must be GONE (spec R5)
  await page.locator('.venue-dd-trigger').click();
  const typeCol = page.locator('.venue-col').first();
  await expect(typeCol).toBeVisible();
  for (const t of ['Tutorial', 'LectureHall', 'Lab']) {
    await expect(typeCol.locator(`.venue-col-item-parent[data-type="${t}"]`)).toBeVisible();
  }
  await expect(typeCol.locator('[data-type="CiscoLab"]')).toHaveCount(0);

  // B006 reachable under the Lab cascade: Lab → Block B → Ground Floor
  await typeCol.locator('.venue-col-item-parent[data-type="Lab"]').click();
  const blockCol = page.locator('.venue-col').nth(1);
  await blockCol.locator('.venue-col-item-parent').first().click();
  const floorCol = page.locator('.venue-col').nth(2);
  await floorCol.locator('.venue-col-item-parent').first().click();
  const rooms = page.locator('.venue-col').nth(3).locator('.venue-col-item-room');
  await expect(rooms.first()).toBeVisible();
  // B006 (the Cisco lab) is in the Lab cascade; every room carries a
  // full-name data-tip ("B006 · Cisco Lab · Ground Floor, Block B" shape)
  await expect(page.locator('.venue-col-item-room[data-code="B006"]')).toHaveCount(1);
  const tips = await rooms.evaluateAll((els: HTMLElement[]) => els.map((el) => el.getAttribute('data-tip') || ''));
  expect(tips.length).toBeGreaterThan(0);
  for (const tip of tips) expect(tip).toMatch(/^B\d+ · .+ · .+, Block B$/);

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
  // SURVIVES the re-pin (venue-event-blocks-db spec R1): conflict is reachable
  // in v1 data — owner sees the loud 3px event-conflict block.
  await loginAsStaff(page, '5652');
  await page.goto('/venue-timetable-ui?venue=B005');

  // Owner view: loud conflict block in week 1.
  await expect(page.locator('.timetable .event-conflict').first()).toBeVisible({ timeout: 15_000 });

  // It is the AMIT2034 block — modal opens with the "needs attention" copy.
  await page.locator('.timetable .event-conflict').first().click();
  await expect(page.locator('#classModal')).toBeVisible();
  await expect(page.locator('#classModal')).toContainText('AMIT2034');
  await page.click('.btn-close-modal');
});

test("B005 conflict from others' view renders the quiet hairline red (event-public-holiday)", async ({ page }) => {
  // Others' side of the same derived conflict (spec R1 owner-gating):
  // 5425 does not own the AMIT2034 session → event-public-holiday (error
  // fill, 0.5px hairline), never the loud event-conflict block.
  await loginAsStaff(page);
  await page.goto('/venue-timetable-ui?venue=B005');

  const quiet = page.locator('.timetable .event-public-holiday').first();
  await expect(quiet).toBeVisible({ timeout: 15_000 });
  await expect(quiet).toHaveAttribute('data-name', /.+/);
  await expect(quiet).toHaveAttribute('data-tip2', /.+ · .+/);
  await expect(page.locator('.timetable .event-conflict')).toHaveCount(0);
});
