import { test, expect } from '@playwright/test';

/**
 * SDD `student-side-health-check` S1 (user report 2026-11-08) — the READ-ONLY
 * receipt for the student timetable's "Today".
 *
 * The bug: `StudentMyTimetable`'s data bridge never cleared the mock-era anchor
 * (`MockData.mockNow = new Date(2026, 9, 5)` in `public/js/mock-data.js`), so the
 * Today button jumped to **Week 3 · 5 Oct 2026** and no day in the real current
 * week carried the "Today" badge. The fix is the same one line the other seven
 * live pages carry.
 *
 * This spec signs in as the seeded student itself (the staff storageState is a
 * bounce on a student route) — one login per run, well inside the 5/min limiter.
 * It writes nothing, so no receipt-target guard is needed; it still asserts the
 * page, never a fixture.
 */
const BASE = (process.env.CRS_BASE ?? 'http://localhost:8000');
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** The page's own label format, e.g. "8 Nov 2026" (no leading zero on the day). */
function dayLabel(d: Date): string {
  return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

test.use({ storageState: { cookies: [], origins: [] } });

test('the student timetable puts "Today" on the real today, and Today lands there', async ({ page }) => {
  await page.goto(`${BASE}/login/student`, { waitUntil: 'domcontentloaded' });
  await page.fill('#login_id', '24DFT0001');
  await page.fill('#password', 'Tarumt@2026');
  await page.waitForFunction(() => {
    const btn = document.getElementById('loginBtn') as HTMLButtonElement | null;
    return !!btn && !btn.disabled;
  }, { timeout: 10_000 });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => {}),
    page.click('#loginBtn'),
  ]);

  await page.goto(`${BASE}/student-my-timetable-ui`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);

  // the mock anchor must be OFF on this page (the bug's root cause)
  expect(
    await page.evaluate(() => String((window as any).MockData?.mockNow)),
    'the student page must clear MockData.mockNow (else "today" is 5 Oct 2026)',
  ).toBe('null');

  const expected = dayLabel(new Date());

  // 1. the real current week is the one on screen, and IT carries the Today badge
  const badgeDay = page.locator('td.time-col', { has: page.locator('.today-badge') });
  await expect(badgeDay, 'exactly one day column carries the Today badge').toHaveCount(1);
  await expect(badgeDay).toContainText(expected);

  // 2. the Today button keeps the page on that day (never Week 3 / 5 Oct 2026)
  await page.click('#todayBtn');
  await page.waitForTimeout(900);

  const subtitle = (await page.locator('#weekSubtitle').textContent()) ?? '';
  expect(subtitle).not.toContain('5 Oct 2026');
  expect(subtitle).toContain(String(new Date().getFullYear()));

  const badgeAfter = page.locator('td.time-col', { has: page.locator('.today-badge') });
  await expect(badgeAfter, 'after pressing Today the badge still sits on the real date').toHaveCount(1);
  await expect(badgeAfter).toContainText(expected);
});
