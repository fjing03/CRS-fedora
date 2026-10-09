import { test, expect, Page } from '@playwright/test';

/**
 * Ad-hoc Playwright verification for SDD change `wire-existing-backend`
 * (identity panel + role-aware nav) — the JS-runtime layer that PHPUnit/curl
 * cannot see: real browser rendering, drawer interaction, console errors.
 */

async function login(page: Page, id: string, type: 'staff' | 'student'): Promise<void> {
  await page.goto(`/login/${type}`);
  await page.fill('#login_id', id);
  await page.fill('#password', 'Tarumt@2026');
  await page.click('#loginBtn');
  await page.waitForLoadState('networkidle');
}

test('5425 panel shows real identity, no mock, drawer works, no console errors', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await login(page, '5425', 'staff');
  await page.goto('/my-timetable-ui');

  // Desktop panel = real identity (was: LJZ / 5770 / Lecturer for everyone)
  await expect(page.locator('.user-panel .user-name')).toHaveText('Pn. Surayaini Binti Basri');
  await expect(page.locator('.user-panel .user-id')).toHaveText('5425');
  await expect(page.locator('.user-panel .user-role')).toHaveText('Lecturer (PL)');

  // No mock identity rendered anywhere visible
  await expect(page.locator('body')).not.toContainText('En. Lim Jia Zheng');
  await expect(page.locator('.user-panel')).not.toContainText('5770');
  await expect(page.locator('.user-panel .user-avatar')).toHaveText('SB');

  // PL nav carries Request Approval (desktop + drawer = 2 in DOM); lecturer items present
  await expect(page.locator('a[href="/request-approval-ui"]')).toHaveCount(2);
  await expect(page.locator('a[href="/venue-timetable-ui"]').first()).toBeVisible();

  // Mobile drawer renders the same identity after interaction (drawer is a
  // ≤768px surface — the hamburger is hidden at the desktop viewport)
  await page.setViewportSize({ width: 375, height: 667 });
  await page.click('#navHamburger');
  await expect(page.locator('#navDrawer .nav-drawer-user-name')).toHaveText('Pn. Surayaini Binti Basri');
  await expect(page.locator('#navDrawer .nav-drawer-user-id')).toHaveText('5425');

  expect(consoleErrors, 'no JS page errors').toEqual([]);
});

test('student panel + student-only nav links', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await login(page, '25RSD0001', 'student');
  await page.goto('/student-my-timetable-ui');

  await expect(page.locator('.user-panel .user-name')).toHaveText('Student 25RSD0001');
  await expect(page.locator('.user-panel .user-id')).toHaveText('25RSD0001');
  await expect(page.locator('.user-panel .user-role')).toHaveText('Student');

  // Student whitelist: both items (student-nav-remove-cohort — Cohort Timetables removed)
  for (const href of ['/student-my-timetable-ui', '/replacement-history-ui']) {
    await expect(page.locator(`a[href="${href}"]`).first()).toBeAttached();
  }
  // Cohort timetable page removed from the student side (nav + route gate)
  await expect(page.locator('a[href="/cohort-timetable-ui"]')).toHaveCount(0);
  // Lecturer-only links absent
  for (const href of ['/my-timetable-ui', '/venue-timetable-ui', '/replacement-home-ui', '/request-approval-ui']) {
    await expect(page.locator(`a[href="${href}"]`)).toHaveCount(0);
  }

  // Direct URL visit: the route is role:lecturer now — no cohort page renders.
  const resp = await page.goto('/cohort-timetable-ui');
  const status = resp ? resp.status() : 0;
  const hasCohortHeader = await page.locator('.page-header h1, h1').first().textContent().then((t) => (t ?? '').includes('Cohort Timetable')).catch(() => false);
  if (status === 200 && hasCohortHeader) {
    throw new Error(`student reached /cohort-timetable-ui (HTTP ${status}, cohort h1 rendered)`);
  }

  expect(consoleErrors, 'no JS page errors').toEqual([]);
});

test('plain lecturer 5770 has no approval link, sees own name', async ({ page }) => {
  await login(page, '5770', 'staff');
  await page.goto('/my-timetable-ui');

  await expect(page.locator('.user-panel .user-name')).toHaveText('En. Lim Jia Zheng');
  await expect(page.locator('.user-panel .user-id')).toHaveText('5770');
  await expect(page.locator('.user-panel .user-role')).toHaveText('Lecturer');
  await expect(page.locator('a[href="/request-approval-ui"]')).toHaveCount(0);
});
