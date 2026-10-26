import { test, expect } from '@playwright/test';

/* SDD arr-slot-original-venue-sync (Bug 2 of the 5425 investigation, user law
   2026-10-24): every NON-AUTO slotTrigger pick re-selects the picked conflict
   schedule's ORIGINAL room — value + label parity through the revertingChange
   pair, options keep the buildVenueFilter law. Rows are from the REAL deck
   (probe receipts, staff-5425's storage state):
   - 99/8  = AMSE1003 (L) W8 Mon Nov 09 9–11 @ B111
   - 38/14 = BMSE2163 (P) W14 Thu Dec 24 12:00–13:00 @ B010
   - 67/14 = AMSE2003 (L) W14 Thu Dec 24 9–11 @ B100 (+ its T row @ B100)
   BLOCKED (documented, never invented): a same-session pick with a DIFFERENT
   room (pure multi-week Gap A) and an orig-NOT-offered keep-current row —
   no session on this demo deck carries multiple conflicted occurrences, and
   every orig room self-fits its own ctx (labs P-only, L/T halls+rooms,
   capacity-gated). The fallback half stays covered by its verbatim D1 clone
   (the arrival auto law) in the spot battery. */

test.use({ storageState: '.auth/staff-5425.json' });

test('the label follows the picked schedule ORIGINAL room (session-change pick)', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=99&week=8&from=replacement-home');
  await page.waitForTimeout(3000);
  await page.selectOption('#subjectSelector', 'BMSE2163');
  await page.waitForTimeout(2200);
  await page.click('#slotTrigger');
  await page.waitForTimeout(300);
  await page.click('.slot-dd-item .slot-dd-item-label');
  await page.waitForTimeout(2200);
  await expect(page.locator('.venue-dd-label')).toHaveText('B010 — Lab');
  await expect(page.locator('#slotTriggerText')).toContainText('W14 · Dec 24 · 12:00 PM–1:00 PM');
});

test('alternation across rows with different original venues (auto-pin follows, picks keep parity)', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=38&week=14&from=replacement-home');
  await page.waitForTimeout(3000);
  await expect(page.locator('.venue-dd-label')).toHaveText('B010 — Lab');
  await expect(page.locator('#venueCapacity')).toHaveText(/28/s);
  // → AMSE1003: the ROUND-2 law — the auto-pin follows the row's ORIGINAL room immediately (no manual pick)
  await page.selectOption('#subjectSelector', 'AMSE1003');
  await page.waitForTimeout(2200);
  await expect(page.locator('.venue-dd-label')).toHaveText('B111 — Lecture Hall');
  await expect(page.locator('#venueCapacity')).toHaveText(/80/s);
  // the manual pick on the same subject re-fires the same orig parity (equal-target refire):
  await page.click('#slotTrigger');
  await page.waitForTimeout(300);
  await page.click('.slot-dd-item .slot-dd-item-label');
  await page.waitForTimeout(2200);
  await expect(page.locator('.venue-dd-label')).toHaveText('B111 — Lecture Hall');
  // → AMSE2003 (both rows @ B100): the auto-pin follows B100
  await page.selectOption('#subjectSelector', 'AMSE2003');
  await page.waitForTimeout(2200);
  await page.click('#slotTrigger');
  await page.waitForTimeout(300);
  await page.click('.slot-dd-item .slot-dd-item-label');
  await page.waitForTimeout(2200);
  await expect(page.locator('.venue-dd-label')).toHaveText('B100 — Tutorial');
});

test('same-session pick (Gap A) still runs the venue family + parity refire', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=38&week=14&from=replacement-home');
  await page.waitForTimeout(3000);
  await expect(page.locator('.venue-dd-label')).toHaveText('B010 — Lab');
  await page.click('#slotTrigger');
  await page.waitForTimeout(300);
  await page.click('.slot-dd-item .slot-dd-item-label'); // the same row the arrival auto-pinned (sessionId 38 = current) → the family was SKIPPED pre-fix
  await page.waitForTimeout(2200);
  await expect(page.locator('.venue-dd-label')).toHaveText('B010 — Lab');
  expect(await page.evaluate(() => window.__venueSelected())).toBe('B010'); // the equal-target parity refire (design D3)
  await expect(page.locator('#slotTriggerText')).toContainText('W14 · Dec 24 · 12:00 PM–1:00 PM');
});

test('the 68/14 → BMSE2163 sighting: the auto-pin follows @ B010 BEFORE any manual pick (round-2 law, the whole-red grid)', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=68&week=14&from=replacement-home');
  await page.waitForTimeout(3000);
  await expect(page.locator('.venue-dd-label')).toHaveText('B100 — Tutorial'); // the arrival law pins the booked venue
  await page.selectOption('#subjectSelector', 'BMSE2163');
  await page.waitForTimeout(2500);
  await expect(page.locator('.venue-dd-label')).toHaveText('B010 — Lab'); // follows the strip WITHOUT a manual pick
  // the grid is NOT all-red: bookable cells exist under the new ctx
  const avail = await page.evaluate(() => document.querySelectorAll('#tableBody .cell-available').length);
  expect(avail).toBeGreaterThan(0);
});

test('subject-change interplay: orig wins over the inherited room + options keep the eligibility law', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=99&week=8&from=replacement-home');
  await page.waitForTimeout(3000);
  await expect(page.locator('.venue-dd-label')).toHaveText('B111 — Lecture Hall');
  /* venue-trigger-label law (user 2026-10-27): the seat count LEFT the label
     but the full name + capacity RIDE the trigger's hover tip */
  await expect(page.locator('#buildingSelectorDropdown .venue-dd-trigger'))
    .toHaveAttribute('data-tip', 'B111 — Lecture Hall (80 seats)');
  await page.selectOption('#subjectSelector', 'AMSE2003');
  await page.waitForTimeout(2200);
  await page.click('#slotTrigger');
  await page.waitForTimeout(300);
  await page.click('.slot-dd-item .slot-dd-item-label'); // W14 9–11 @ B100 — orig offered → it wins (Gap B)
  await page.waitForTimeout(2200);
  await expect(page.locator('.venue-dd-label')).toHaveText('B100 — Tutorial');
  // options law untouched: the filtered family for (L, 15) shows the eligible TYPES only
  expect(await page.evaluate(() => document.getElementById('venueCountNote').textContent))
    .toContain('Showing venues that fit 15 students');
  await page.click('.venue-dd-trigger');
  await page.waitForTimeout(400);
  const types = await page.evaluate(() =>
    Array.from(document.querySelectorAll('.venue-dd-panel .venue-col-item-parent')).map(e => e.textContent.trim()));
  expect(types.some(t => t.includes('Lecture Hall'))).toBe(true);
  expect(types.some(t => t.includes('Tutorial'))).toBe(true);
  expect(types.some(t => t === 'Lab')).toBe(false); // labs are P-only (the ruleMap law untouched)
});

test('value/label parity + the venue chips followed (applyVenueChange ran)', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=38&week=14&from=replacement-home');
  await page.waitForTimeout(3000);
  await page.selectOption('#subjectSelector', 'AMSE1003');
  await page.waitForTimeout(2200);
  await page.click('#slotTrigger');
  await page.waitForTimeout(300);
  await page.click('.slot-dd-item .slot-dd-item-label');
  await page.waitForTimeout(2200);
  const label = (await page.evaluate(() => document.querySelector('.venue-dd-label').textContent)).trim();
  const code = label.split(' ')[0];
  expect(code).toBe('B111');
  expect(await page.evaluate(() => window.__venueSelected())).toBe('B111');
  await expect(page.locator('#venueCapacity')).toHaveText(/80/s); // the chips followed
  await expect(page.locator('#venueCapacity')).toHaveAttribute('data-tip', /80 seats/);
  const descTip = await page.evaluate(() => document.getElementById('arrVenueDescription').getAttribute('data-tip'));
  expect(descTip && descTip.length > 0).toBe(true);
});
