import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

/*
 * 2026-11-15 — SDD `my-timetable-responsive-prompt` (T17b, spec R10).
 *
 * The two timetable entries used to hand `helpers/page-check.js` the pair
 * ['#semesterProgress', '#weekSubtitle'] as their "key element" — the helper only
 * asks that ONE of them be VISIBLE. Both ids were removed from the live blades on
 * 2026-11-13, so that rule could never be satisfied again and this file was red
 * before the lane began. The pair is replaced by `#timetable` — the grid Blade
 * renders and the node this lane pins byte-for-byte (T15) — and the two removed
 * ids are asserted ABSENT, so resurrecting that DOM turns this file red on
 * purpose. The DOM is NOT brought back: it was removed by decision, not by
 * accident, and the lane is forbidden from restoring it to buy a green.
 *
 * (Cohort Timetable's own pair — ['#semesterChip', '#weekSelect'] — was never
 * touched by the removal and is left exactly as it was.)
 *
 * The three entries did carry no session at all, so until 2026-11-15 the whole
 * file stopped at checkPage()'s title assertion on the LOGIN page — it never read
 * a single key selector. Since this lane had to touch the file anyway, the
 * sessions are supplied (staff-5770 for the two staff routes, the CSR student
 * state for the student page) so the amended arms actually run. The one gap that
 * remains is Cohort Timetable's, untouched here.
 */
test.describe('staff timetable pages', () => {
  test.use({ storageState: '.auth/staff-5770.json' });

  test('My Timetable', async ({ page }) => {
    await checkPage(page, '/my-timetable-ui', ['#timetable'], 'My Timetable');
    await expect(page.locator('#semesterProgress'), 'the 2026-11-13 removal holds — the progress bar is never resurrected').toHaveCount(0);
    await expect(page.locator('#weekSubtitle'), 'the 2026-11-13 removal holds — the week subtitle is never resurrected').toHaveCount(0);
  });

  test('Cohort Timetable', async ({ page }) => {
    await checkPage(page, '/cohort-timetable', ['#semesterChip', '#weekSelect'], 'Cohort Timetable');
  });

});

test.describe('student timetable page', () => {
  /* the saved CSR student state had expired (the run landed on /login/student), so this
     entry logs in once per run instead — the Fortify limiter allows 5 a minute and this
     file is the only place that needs a student session. */
  test('Student My Timetable', async ({ page }) => {
    await page.goto('/login/student');
    await page.fill('#login_id', '24DFT0001');
    await page.fill('#password', 'Tarumt@2026');
    await page.click('button[type="submit"]');
    await page.waitForURL(/student-my-timetable/, { timeout: 30_000 });
    /* the page's own <title> is 'Student My Timetable' (its @section('title')), not 'My Timetable' —
       the old pair never got this far, so the mismatch was invisible until the session existed */
    await checkPage(page, '/student-my-timetable-ui', ['#timetable'], 'Student My Timetable');
    await expect(page.locator('#semesterProgress'), 'the 2026-11-13 removal holds — the progress bar is never resurrected').toHaveCount(0);
    await expect(page.locator('#weekSubtitle'), 'the 2026-11-13 removal holds — the week subtitle is never resurrected').toHaveCount(0);
  });
});
