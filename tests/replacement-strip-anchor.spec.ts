import { test, expect } from '@playwright/test';

/* SDD replacement-strip-stale-anchor (Bug 1 of the 5425 investigation):
   after a POST-BOOT subject change, the Conflict Schedule strip must anchor
   to the LIVE auto-pinned pair (the new subject's earliest conflicted
   occurrence), not the boot-frozen virtual anchor. Uses staff 5425's own
   storage state — the inventory carries the receipt rows. */

test.use({ storageState: '.auth/staff-5425.json' });

test('strip follows the live pin after a subject switch (Bug 1)', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=99&week=8&from=replacement-home');
  await page.waitForTimeout(3000);
  // pre-change truth (the boot anchor, unchanged behavior):
  await expect(page.locator('#subjectInfo')).toContainText('AMSE1003');
  await expect(page.locator('#subjectInfo')).toContainText('Week 8');
  // the switch: subjectSelector → BMSE2163:
  await page.selectOption('#subjectSelector', 'BMSE2163');
  await page.waitForTimeout(2200);
  const strip = await page.evaluate(() => document.getElementById('subjectInfo').textContent.replace(/\s+/g, ' ').trim());
  expect(strip).toContain('BMSE2163');
  expect(strip).toContain('Week 14');
  expect(strip).toContain('Dec 24 2026');
  expect(strip).toContain('B010');
  expect(strip).not.toContain('Nov 09 2026 · 9:00 AM–11:00 AM @ B111');
  const trig = await page.evaluate(() => document.getElementById('slotTriggerText').textContent);
  expect(trig).toContain('W14 · Dec 24 · 12:00 PM–1:00 PM'); /* the SAME format as a picked slot (user, 2026-10-24) */
});
