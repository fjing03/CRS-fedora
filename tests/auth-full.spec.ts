/**
 * auth-full.spec.ts — real-browser coverage of the auth functions not covered
 * by login-staff-prefix.spec.ts (staff ID format rules) or nav-identity.spec.ts
 * (successful logins as a side effect).
 *
 * Covered here:
 *   - negative logins (wrong password, unknown ID) on both portals
 *   - client-side format gate on the STUDENT portal (staff portal has its own spec)
 *   - empty-password gate
 *   - logout round-trip (bespoke hidden-form POST via .logout-btn) for both roles
 *   - in-browser role rejection (403) — RouteGateMatrixTest only covers this at PHP level
 *   - session expiry via role_lifetime (server middleware) — requires the
 *     temporary staff lifetime of 1 minute in FortifyServiceProvider
 *     (run with: PW_SHORT_LIFETIME=1 npx playwright test tests/auth-full.spec.ts)
 *
 * NOT tested here: the client-side session countdown
 * (resources/views/partials/ui-session-countdown.blade.php) — it is an
 * unfinished stub: 26 lines of HTML, no JavaScript, and @include'd nowhere.
 * A client-countdown test should be added when that feature is built.
 *
 * Lockout safety: staff accounts lock after 3 wrong-password attempts
 * (FortifyServiceProvider). Only ONE wrong attempt is made against the real
 * account 5425; a later successful login clears the counter. Unknown IDs and
 * students never increment the counter.
 */
import { test, expect, type Page } from '@playwright/test';

const PW = 'Tarumt@2026';

async function gotoLogin(page: Page, type: 'staff' | 'student'): Promise<void> {
  await page.goto(type === 'staff' ? '/login/staff' : '/login/student');
  await expect(page.locator('#loginBtn')).toBeDisabled();
}

async function submitLogin(page: Page, id: string, type: 'staff' | 'student', password = PW): Promise<void> {
  await gotoLogin(page, type);
  await page.fill('#login_id', id);
  await page.fill('#password', password);
  await page.click('#loginBtn');
}

async function login(page: Page, id: string, type: 'staff' | 'student', password = PW): Promise<void> {
  await submitLogin(page, id, type, password);
  await page.waitForURL(
    type === 'staff' ? '**/my-timetable-ui' : '**/student-my-timetable-ui',
    { timeout: 15_000 },
  );
}

test.describe('login — negative credentials', () => {
  test('staff wrong password: error shown, stays on login page', async ({ page }) => {
    await submitLogin(page, '5425', 'staff', 'definitely-wrong-password');
    expect(page.url()).toContain('/login/staff');
    await expect(page.locator('.error-msg')).toBeVisible();
  });

  test('student wrong password: error shown, stays on login page', async ({ page }) => {
    await submitLogin(page, '25RSD0001', 'student', 'definitely-wrong-password');
    expect(page.url()).toContain('/login/student');
    await expect(page.locator('.error-msg')).toBeVisible();
  });

  test('unknown staff ID (format-valid): error shown', async ({ page }) => {
    await submitLogin(page, '9999', 'staff', PW);
    expect(page.url()).toContain('/login/staff');
    await expect(page.locator('.error-msg')).toBeVisible();
  });

  test('unknown student ID (format-valid): error shown', async ({ page }) => {
    await submitLogin(page, '25XYZ9999', 'student', PW);
    expect(page.url()).toContain('/login/student');
    await expect(page.locator('.error-msg')).toBeVisible();
  });
});

test.describe('login — student portal format gate', () => {
  const cases: Array<[string, boolean]> = [
    // regex: ^\d{2}[A-Za-z]{3}\d{4}$ — 2 digits, 3 letters, 4 digits
    ['25RSD0001', true],
    ['25rsd0001', true], // letters are case-insensitive
    ['2RSD0001', false], // 1 leading digit
    ['25RSD001', false], // 3 trailing digits
    ['25R5D0001', false], // digit inside the letter block
    ['25RSD00001', false], // 5 trailing digits
  ];

  for (const [id, valid] of cases) {
    test(`"${id}" → submit ${valid ? 'enabled' : 'disabled'}`, async ({ page }) => {
      await gotoLogin(page, 'student');
      await page.fill('#login_id', id);
      await page.fill('#password', PW);
      if (valid) {
        await expect(page.locator('#loginBtn')).toBeEnabled();
      } else {
        await expect(page.locator('#loginBtn')).toBeDisabled();
      }
    });
  }
});

test.describe('login — empty-password gate', () => {
  test('valid ID with empty password keeps submit disabled', async ({ page }) => {
    await gotoLogin(page, 'staff');
    await page.fill('#login_id', '5425');
    await expect(page.locator('#loginBtn')).toBeDisabled();
    await page.fill('#password', 'x');
    await expect(page.locator('#loginBtn')).toBeEnabled();
  });
});

test.describe('logout', () => {
  test('staff logout returns to staff login and kills the session', async ({ page }) => {
    await login(page, '5425', 'staff');
    await page.click('.logout-btn');
    await page.waitForURL('**/login/staff', { timeout: 10_000 });

    // session must be dead: authenticated route bounces back to login
    await page.goto('/my-timetable-ui');
    await page.waitForURL('**/login**', { timeout: 10_000 });
  });

  test('student logout returns to student login', async ({ page }) => {
    await login(page, '25RSD0001', 'student');
    await page.click('.logout-btn');
    await page.waitForURL('**/login/student', { timeout: 10_000 });
  });
});

test.describe('role gating in the browser (403)', () => {
  test('student blocked from lecturer-only page with 403', async ({ page }) => {
    await login(page, '25RSD0001', 'student');
    const resp = await page.goto('/my-timetable-ui');
    expect(resp?.status()).toBe(403);
  });

  test('plain lecturer blocked from student-only page with 403', async ({ page }) => {
    await login(page, '5770', 'staff');
    const resp = await page.goto('/student-my-timetable-ui');
    expect(resp?.status()).toBe(403);
  });
});

test.describe('session expiry (staff, temporary 1-minute test lifetime)', () => {
  // Run with: PW_SHORT_LIFETIME=1 npx playwright test tests/auth-full.spec.ts --grep "session expiry"
  // Requires the temporary staff role_lifetime=1 in FortifyServiceProvider (revert to 30 after).
  test.skip(process.env.PW_SHORT_LIFETIME !== '1', 'run only with the temporary 1-minute staff lifetime');

  test.setTimeout(150_000);

  test('server middleware expires the session after role_lifetime', async ({ page, context }) => {
    await login(page, '5425', 'staff');

    // Close the page so no client-side JS interferes; only the server-side
    // EnsureSessionLifetime check should be able to expire the session.
    await page.close();
    await new Promise((resolve) => setTimeout(resolve, 65_000));

    const page2 = await context.newPage();
    await page2.goto('/my-timetable-ui');
    await page2.waitForURL('**/login/staff', { timeout: 10_000 });
    await expect(page2.locator('.error-msg')).toContainText('Session expired');
  });
});
