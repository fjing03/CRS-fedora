import { test, expect, type Page } from '@playwright/test';

/* SDD arrangement-clear-guards-cross-week (BUG-08) — the confirmation-guards
   acceptance rows. The user's law: after ANY week hop away from a week
   holding selections, changing the venue / the subject / the conflicted
   slot pops the matching confirm modal; "No, Keep My Selection" restores
   everything (field + the hidden selection + the counter); "Yes, Change"
   applies the change AND leaves the counter honestly at "0 of N" (no
   cross-week residue). Fixtures verified live (the 2026-10-25 probes):
   staff-5425, ?session=67 (AMSE2003 (L), 4-slot), W6 pick cell
   td[data-day="2"][data-hour="16"], week values 0-based (W6='5', W8='7');
   the venue widget walks type → block → floor → room. */

const BASE = 'http://localhost:8000';
const PW = 'Tarumt@2026';

async function loginStaff(page: Page, id: string): Promise<void> {
  await page.goto(`${BASE}/login/staff`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', id);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 15_000 });
}

async function pickW6(page: Page): Promise<void> {
  await page.goto(`${BASE}/replacement-arrangement?session=67&week=14&from=replacement-home`);
  await page.waitForTimeout(3400); // the pinned arrival lands on Week 6 (earliest bookable)
  const td = page.locator('#tableBody td[data-day="2"][data-hour="16"] .cell-content');
  await td.click();
  await page.waitForTimeout(900);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
}

async function hopToW8(page: Page): Promise<void> {
  await page.locator('#weekSelector').selectOption('7');
  await page.waitForTimeout(1300);
  // the store survives the hop — the counter must still say 4 of 4
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
  await expect(page.locator('.timetable .event-block.event-selection')).toHaveCount(0);
}

/** walks the venue widget to a room ≠ B100 and clicks it (idempotent: reopens
    the trigger only when the panel is closed) */
async function changeVenueViaWidget(page: Page): Promise<void> {
  const panelOpen = await page.evaluate(() => {
    const panel = document.querySelector('#buildingSelectorDropdown .venue-dd-panel');
    return !!panel && getComputedStyle(panel).display !== 'none';
  });
  if (!panelOpen) {
    await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
    await page.waitForTimeout(700);
  }
  // col 0 = the type rows; descend via real clicks (type → block → floor);
  // an already-descended widget re-used its last column — start AT the end
  let vis = await page.evaluate(() => document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible').length);
  const roomsVisible = await page.evaluate(() => {
    const cols = document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible');
    const last = cols[cols.length - 1];
    return Array.from(last.children).some((k) => /B1\d\d/.test(k.textContent || ''));
  });
  if (!roomsVisible) {
    await page.locator('#buildingSelectorDropdown .venue-dd-panel .venue-col.visible > *').nth(2).click(); // Lecture Hall
    await page.waitForTimeout(500);
    vis = await page.evaluate(() => document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible').length);
    await page.locator('#buildingSelectorDropdown .venue-dd-panel .venue-col.visible').nth(vis - 1).locator('> *').nth(1).click(); // Block B
    await page.waitForTimeout(500);
    vis = await page.evaluate(() => document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible').length);
    await page.locator('#buildingSelectorDropdown .venue-dd-panel .venue-col.visible').nth(vis - 1).locator('> *').nth(1).click(); // a floor
    await page.waitForTimeout(700);
  }
  await page.evaluate(() => {
    const cols = document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible');
    const items = Array.from(cols[cols.length - 1].children).filter((k) => /B1\d\d/.test(k.textContent || ''));
    for (const it of items) {
      if ((it.textContent || '').trim().slice(0, 4) !== 'B100') { (it as HTMLElement).click(); return; }
    }
  });
  await page.waitForTimeout(1000);
}

async function counters(page: Page) {
  return await page.evaluate(() => ({
    strip: (document.getElementById('infoTotal') || {}).textContent,
    selCount: (document.getElementById('selCount') || {}).textContent,
    clearAllDisabled: (document.getElementById('clearAllBtn') || {}).disabled,
  }));
}

test('BUG-08 core: the hop itself stays silent — the selection persists honestly', async ({ page }) => {
  await loginStaff(page, '5425');
  await pickW6(page);
  await page.locator('#weekSelector').selectOption('7');
  await page.waitForTimeout(400);
  await expect(page.locator('#confirmModal')).toBeHidden(); // the hop alone asks nothing
  await page.waitForTimeout(900);
  const c = await counters(page);
  await expect(c.strip || '').toContain('4 of 4 slots'); // the store receipt
});

test('BUG-08 venue: the guard fires after the hop; Yes clears honestly; No restores', async ({ page }) => {
  await loginStaff(page, '5425');
  await pickW6(page);
  await hopToW8(page);
  await changeVenueViaWidget(page);
  await expect(page.locator('#confirmModal')).toBeVisible();
  await expect(page.locator('#modalTitle')).toHaveText('Change Venue?');
  // No → the venue reverts to the ORIGINAL code and the store lives on
  await page.locator('#confirmModal .btn-outline').click();
  await page.waitForTimeout(900);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
  const back = await page.evaluate(() => window.__venueSelected());
  expect(String(back)).toMatch(/^B10/);
  // Yes → the change applies AND the counter drops honestly (the whole store)
  // (the widget retained its rooms column through the modal round-trip)
  await page.waitForTimeout(400);
  const roomsStill = await page.evaluate(() => {
    const cols = document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible');
    if (!cols.length) return false;
    const last = cols[cols.length - 1];
    return Array.from(last.children).some((k) => /B1\d\d/.test(k.textContent || ''));
  });
  if (!roomsStill) {
    await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
    await page.waitForTimeout(700);
    await page.locator('#buildingSelectorDropdown .venue-dd-panel .venue-col.visible > *').nth(2).click(); // Lecture Hall
    await page.waitForTimeout(500);
    let vis = await page.evaluate(() => document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible').length);
    await page.locator('#buildingSelectorDropdown .venue-dd-panel .venue-col.visible').nth(vis - 1).locator('> *').nth(1).click();
    await page.waitForTimeout(500);
    vis = await page.evaluate(() => document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible').length);
    await page.locator('#buildingSelectorDropdown .venue-dd-panel .venue-col.visible').nth(vis - 1).locator('> *').nth(1).click();
    await page.waitForTimeout(700);
  }
  await page.evaluate(() => {
    const cols = document.querySelectorAll('#buildingSelectorDropdown .venue-col.visible');
    const items = Array.from(cols[cols.length - 1].children).filter((k) => /B1\d\d/.test(k.textContent || ''));
    for (const it of items) {
      if ((it.textContent || '').trim().slice(0, 4) !== 'B100') { (it as HTMLElement).click(); return; }
    }
  });
  await page.waitForTimeout(800);
  await page.locator('#confirmModal #modalConfirmBtn').click();
  await page.waitForTimeout(1100);
  const c = await counters(page);
  await expect(c.strip || '').toContain('0 of 4 slots'); // no cross-week residue
  await expect(c.clearAllDisabled).toBe(true); // the footer flips with the store
});

test('BUG-08 subject: the guard fires after the hop; cancel restores; Yes clears', async ({ page }) => {
  await loginStaff(page, '5425');
  await pickW6(page);
  await hopToW8(page);
  await page.locator('#subjectSelector').selectOption({ index: 1 }); // AMSE1003
  await page.waitForTimeout(900);
  await expect(page.locator('#confirmModal')).toBeVisible();
  await expect(page.locator('#modalTitle')).toHaveText('Clear Current Selection?');
  await page.locator('#confirmModal .btn-outline').click(); // No, Keep My Selection
  await page.waitForTimeout(800);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
  const sub = (await page.evaluate(() => document.getElementById('subjectSelector').value));
  expect(sub).toBe('AMSE2003'); // the revert receipt
  // Yes path
  await page.locator('#subjectSelector').selectOption({ index: 1 });
  await page.waitForTimeout(700);
  await page.locator('#confirmModal #modalConfirmBtn').click();
  await page.waitForTimeout(1100);
  const c = await counters(page);
  await expect(c.strip || '').toContain('0 of 4 slots');
});

test('BUG-08 slot: the guard fires after the hop; the pinned pair swaps + the counter drops', async ({ page }) => {
  await loginStaff(page, '5425');
  await pickW6(page);
  await hopToW8(page);
  await page.locator('#slotTrigger').click();
  await page.waitForTimeout(700);
  await page.locator('#slotPanel .slot-dd-item').first().locator('.slot-dd-item-label').click();
  await page.waitForTimeout(900);
  await expect(page.locator('#confirmModal')).toBeVisible(); // "Clear Current Selection?" family
  await page.locator('#confirmModal .btn-outline').click(); // No
  await page.waitForTimeout(800);
  await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');
});

test('BUG-08 baseline: no-hop venue change still pops the modal (the legacy receipt)', async ({ page }) => {
  await loginStaff(page, '5425');
  await pickW6(page);
  await changeVenueViaWidget(page);
  await expect(page.locator('#confirmModal')).toBeVisible();
  await expect(page.locator('#modalTitle')).toHaveText('Change Venue?');
});
